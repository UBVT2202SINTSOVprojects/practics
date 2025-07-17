<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка прав доступа
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}

// Обработка изменения заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_application'])) {
    $applicationId = (int)$_POST['application_id'];
    $newStatus = $_POST['new_status'];
    $oldStatus = $_POST['old_status'];
    $date = $_POST['date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $phone = trim($_POST['phone'] ?? '');
    $userComment = trim($_POST['user_comment'] ?? ''); // Комментарий пользователя
    $visitComment = trim($_POST['visit_comment'] ?? ''); // Комментарий сотрудника
    $type = $_POST['type'];
    
    // Формируем полные значения datetime
    $startDateTime = $date . ' ' . $startTime . ':00';
    $endDateTime = $date . ' ' . $endTime . ':00';
    
    // Проверяем существование заявки
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    
    if ($application) {
        try {
            $pdo->beginTransaction();
            
            // Обновляем заявку
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET start_time = ?,
                    end_time = ?,
                    phone = ?,
                    comment = ?,
                    visit_comments = ?,
                    type = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $startDateTime,
                $endDateTime,
                $phone,
                $userComment, // Сохраняем комментарий пользователя
                $visitComment, // Сохраняем комментарий сотрудника
                $type,
                $newStatus,
                $applicationId
            ]);
            
            // Если статус изменился на canceled или completed - добавляем запись в историю
            if (($newStatus === 'canceled' || $newStatus === 'completed') && $newStatus !== $oldStatus) {
                // Формируем детали для истории
                $action = $type === 'reservation' ? 'visit' : $type;
                $details = ($type === 'adoption' ? 'Усыновление' : 'Резервирование');
                
                if ($newStatus === 'canceled') {
                    $details .= " Отменено";
                    $details .= " " . date('d.m.Y H:i', strtotime($startDateTime));
                    $details .= " - " . date('H:i', strtotime($endDateTime));
                    $details .= " (отменено сотрудником)";
                } else {
                    $details .= " Завершено";
                    $details .= " " . date('d.m.Y H:i', strtotime($startDateTime));
                    $details .= " - " . date('H:i', strtotime($endDateTime));
                }
                
                // Добавляем запись в историю
                $stmt = $pdo->prepare("
                    INSERT INTO history 
                    (user_id, animal_id, application_id, action, status, date, details, visit_comments) 
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([
                    $application['user_id'],
                    $application['animal_id'],
                    $applicationId,
                    $action,
                    $newStatus,
                    $details,
                    $visitComment
                ]);
                
                // Если это заявка на усыновление и ее отменили - обновляем статус животного
                if ($type === 'adoption' && $newStatus === 'canceled') {
                    $stmt = $pdo->prepare("
                        UPDATE animals 
                        SET status = 'available' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$application['animal_id']]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Заявка успешно обновлена";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Ошибка при обновлении заявки: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Заявка не найдена";
    }
    
    redirect('admin_dashboard.php');
}

// Получаем заявки на сегодня
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.*, 
           u.username as user_name, 
           u.first_name as user_first_name, 
           u.last_name as user_last_name,
           an.name as animal_name,
           an.image_path as animal_image,
           an.type as animal_type
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN animals an ON a.animal_id = an.id
    WHERE DATE(a.start_time) = ?
    ORDER BY 
        CASE WHEN a.status = 'active' THEN 0 
             WHEN a.status = 'created' THEN 1
             ELSE 2 END,
        a.start_time ASC
");
$stmt->execute([$today]);
$todayApplications = $stmt->fetchAll();

$pageTitle = "Админ-панель | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <h1>Заявки на сегодня (<?= date('d.m.Y') ?>)</h1>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if (empty($todayApplications)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>На сегодня нет заявок</p>
                </div>
            <?php else: ?>
                <div class="applications-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Животное</th>
                                <th>Тип</th>
                                <th>Время</th>
                                <th>Статус</th>
                                <th>Телефон</th>
                                <th>Коммент. пользователя</th>
                                <th>Коммент. сотрудника</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayApplications as $app): ?>
                                <tr class="status-<?= strtolower($app['status']) ?>">
                                    <td><?= $app['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($app['user_first_name'] . ' ' . $app['user_last_name']) ?>
                                        <small>(<?= htmlspecialchars($app['user_name']) ?>)</small>
                                    </td>
                                    <td>
                                        <div class="animal-info">
                                            <img src="<?= SITE_URL . '/assets/images/animals/' . htmlspecialchars($app['animal_image']) ?>" 
                                                 alt="<?= htmlspecialchars($app['animal_name']) ?>" class="animal-thumb">
                                            <?= htmlspecialchars($app['animal_name']) ?>
                                            <small>(<?= getAnimalType($app['animal_type']) ?>)</small>
                                        </div>
                                    </td>
                                    <td><?= $app['type'] === 'adoption' ? 'Усыновление' : 'Резервирование' ?></td>
                                    <td>
                                        <?= date('H:i', strtotime($app['start_time'])) ?> - 
                                        <?= date('H:i', strtotime($app['end_time'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($app['status']) ?>">
                                            <?= getStatusText($app['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($app['phone']) ? htmlspecialchars($app['phone']) : '—' ?></td>
                                    <td><?= !empty($app['comment']) ? htmlspecialchars($app['comment']) : '—' ?></td>
                                    <td><?= !empty($app['visit_comments']) ? htmlspecialchars($app['visit_comments']) : '—' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-action" 
                                                onclick="openEditModal(
                                                    <?= $app['id'] ?>, 
                                                    '<?= $app['status'] ?>', 
                                                    '<?= $app['type'] ?>',
                                                    '<?= date('Y-m-d', strtotime($app['start_time'])) ?>',
                                                    '<?= date('H:i', strtotime($app['start_time'])) ?>',
                                                    '<?= date('H:i', strtotime($app['end_time'])) ?>',
                                                    `<?= htmlspecialchars($app['phone'], ENT_QUOTES) ?>`,
                                                    `<?= htmlspecialchars($app['comment'], ENT_QUOTES) ?>`,
                                                    `<?= htmlspecialchars($app['visit_comments'], ENT_QUOTES) ?>`
                                                )">
                                            <i class="fas fa-edit"></i> Изменить
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования заявки -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Редактирование заявки</h2>
        <form method="post" id="editForm">
            <input type="hidden" name="application_id" id="modalAppId">
            <input type="hidden" name="old_status" id="modalOldStatus">
            <input type="hidden" name="edit_application" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="type">Тип заявки:</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="reservation">Резервирование</option>
                        <option value="adoption">Усыновление</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_status">Статус:</label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <option value="created">Создана</option>
                        <option value="active">Активна</option>
                        <option value="completed">Завершена</option>
                        <option value="canceled">Отменена</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date">Дата:</label>
                    <input type="date" name="date" id="date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="start_time">Время начала:</label>
                    <input type="time" name="start_time" id="start_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="end_time">Время окончания:</label>
                    <input type="time" name="end_time" id="end_time" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">Телефон:</label>
                <input type="tel" name="phone" id="phone" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="user_comment">Комментарий пользователя:</label>
                <textarea name="user_comment" id="user_comment" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="visit_comment">Комментарий сотрудника:</label>
                <textarea name="visit_comment" id="visit_comment" class="form-control" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
</div>

<script>
function openEditModal(appId, currentStatus, currentType, currentDate, currentStartTime, currentEndTime, currentPhone, currentUserComment, currentVisitComment) {
    document.getElementById('modalAppId').value = appId;
    document.getElementById('modalOldStatus').value = currentStatus;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('type').value = currentType;
    document.getElementById('date').value = currentDate;
    document.getElementById('start_time').value = currentStartTime;
    document.getElementById('end_time').value = currentEndTime;
    document.getElementById('phone').value = currentPhone || '';
    document.getElementById('user_comment').value = currentUserComment || '';
    document.getElementById('visit_comment').value = currentVisitComment || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Закрытие модального окна при клике вне его
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Валидация формы - комментарий сотрудника обязателен при отмене
document.getElementById('editForm').addEventListener('submit', function(e) {
    const status = document.getElementById('new_status').value;
    const visitComment = document.getElementById('visit_comment').value;
    
    if (status === 'canceled' && visitComment.trim() === '') {
        e.preventDefault();
        alert('Пожалуйста, укажите причину отмены заявки в комментарии сотрудника');
    }
    
    // Проверяем что время окончания позже времени начала
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    
    if (startTime >= endTime) {
        e.preventDefault();
        alert('Время окончания должно быть позже времени начала');
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>