<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Если пользователь staff или admin - перенаправляем в админ-панель
if (in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    redirect('admin_dashboard.php');
}
// Обработка отмены заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_application'])) {
    $applicationId = (int)$_POST['application_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Получаем информацию о заявке
        $stmt = $pdo->prepare("
            SELECT a.*, an.name as animal_name 
            FROM applications a
            JOIN animals an ON a.animal_id = an.id
            WHERE a.id = ? AND a.user_id = ? AND a.status IN ('created', 'active')
        ");
        $stmt->execute([$applicationId, $_SESSION['user_id']]);
        $application = $stmt->fetch();
        
        if ($application) {
            // Добавляем запись в историю
            $action = $application['type'] === 'reservation' ? 'visit' : $application['type'];
            $details = $application['type'] === 'reservation' 
                ? "Посещение отменено: " . date('d.m.Y H:i', strtotime($application['start_time'])) . 
                  " - " . date('H:i', strtotime($application['end_time']))
                : "Усыновление отменено: " . date('d.m.Y H:i', strtotime($application['start_time'])) . 
                  " - " . date('H:i', strtotime($application['end_time']));
            
            $stmt = $pdo->prepare("
                INSERT INTO history 
                (user_id, animal_id, application_id, action, status, date, details) 
                VALUES (?, ?, ?, ?, 'canceled', NOW(), ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $application['animal_id'],
                $applicationId,
                $action,
                $details . " (отменено пользователем)"
            ]);
            
            // Обновляем статус заявки
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'canceled' 
                WHERE id = ?
            ");
            $stmt->execute([$applicationId]);
            
            // Если это была последняя заявка на животное - обновляем его статус
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM applications 
                WHERE animal_id = ? 
                AND status IN ('created', 'active')
            ");
            $stmt->execute([$application['animal_id']]);
            
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("
                    UPDATE animals 
                    SET status = 'available' 
                    WHERE id = ?
                ");
                $stmt->execute([$application['animal_id']]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Заявка успешно отменена";
        } else {
            $_SESSION['error_message'] = "Заявка не найдена или уже обработана";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Ошибка при отмене заявки: " . $e->getMessage();
    }
    
    redirect('dashboard.php');
}

// Принудительно обновляем статусы заявок при каждом входе в ЛК
updateApplicationStatuses($pdo);

// Получаем активные заявки пользователя
$stmt = $pdo->prepare("
    SELECT a.*, an.name as animal_name, an.image_path, an.type as animal_type, an.status as animal_status
    FROM applications a
    JOIN animals an ON a.animal_id = an.id
    WHERE a.user_id = ? AND a.status IN ('created', 'active')
    ORDER BY 
        CASE WHEN a.status = 'active' THEN 0 ELSE 1 END,
        a.start_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$active_applications = $stmt->fetchAll();

// Получаем историю пользователя (из таблицы history)
$stmt = $pdo->prepare("
    SELECT h.*, an.name as animal_name, an.image_path, an.type as animal_type
    FROM history h
    JOIN animals an ON h.animal_id = an.id
    WHERE h.user_id = ?
    ORDER BY h.date DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll();

// Получаем расписание приюта
$schedule = getShelterSchedule($pdo);
$isOpen = isShelterOpen($pdo);

$pageTitle = "Личный кабинет | " . SITE_NAME;
require_once 'includes/header.php';

// Отображаем сообщения об успехе/ошибке
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/dashboard.css">
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <div class="dashboard-actions">
            <a href="catalog.php" class="btn btn-primary">
                <i class="fas fa-paw"></i> Найти питомца
            </a>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Блок с расписанием и функциями -->
        <div class="dashboard-sidebar">
            <div class="info-card">
                <div class="schedule-notice">
                    <h3><i class="fas fa-clock"></i> Часы работы приюта</h3>
                    <ul class="schedule-list">
                        <?php foreach ($schedule as $day => $time): ?>
                            <li>
                                <span class="day"><?php echo htmlspecialchars($day); ?></span>
                                <span class="time"><?php echo htmlspecialchars($time); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="current-status">
                        Статус: 
                        <span class="<?php echo $isOpen ? 'status-open' : 'status-closed'; ?>">
                            <?php echo $isOpen ? 'ОТКРЫТ' : 'ЗАКРЫТ'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Доступные функции</h3>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4>Резервирование</h4>
                        <ul class="feature-rules">
                            <li><i class="fas fa-check-circle"></i> Посещение питомца</li>
                            <li><i class="fas fa-check-circle"></i> Максимум 2 заявки</li>
                            <li><i class="fas fa-check-circle"></i> Продолжительность 1-3 часа</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h4>Усыновление</h4>
                        <ul class="feature-rules">
                            <li><i class="fas fa-check-circle"></i> Максимум 2 заявки</li>
                            <li><i class="fas fa-check-circle"></i> Приехать с документами</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Основное содержимое -->
        <div class="dashboard-content">
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><i class="fas fa-tasks"></i> Активные заявки</h2>
                    <span class="badge"><?php echo count($active_applications); ?></span>
                </div>
                <?php if (empty($active_applications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>У вас нет активных заявок</p>
                        <a href="catalog.php" class="btn btn-outline">Найти питомца</a>
                    </div>
                <?php else: ?>
                    <div class="applications-grid">
                        <?php foreach ($active_applications as $app): ?>
                            <div class="application-card">
    <div class="animal-image" style="background-image: url('<?php echo SITE_URL . '/assets/images/animals/' . htmlspecialchars($app['image_path']); ?>')"></div>
    <div class="application-info">
        <h3><?php echo htmlspecialchars($app['animal_name']); ?></h3>
        <div class="animal-meta">
            <span class="animal-type <?php echo htmlspecialchars($app['animal_type']); ?>">
                <?php echo getAnimalType($app['animal_type']); ?>
            </span>
            <span class="application-type <?php echo $app['type']; ?>">
                <?php echo $app['type'] === 'adoption' ? 'Усыновление' : 'Посещение'; ?>
            </span>
        </div>
        
<div class="time-slot">
    <i class="fas fa-calendar-day"></i>
    <?php echo date('d.m.Y', strtotime($app['start_time'])); ?>
</div>

<?php if ($app['type'] === 'reservation'): ?>
    <div class="time-slot">
        <i class="fas fa-clock"></i>
        <?php echo date('H:i', strtotime($app['start_time'])); ?> - <?php echo date('H:i', strtotime($app['end_time'])); ?>
    </div>
<?php else: ?>
    <div class="time-slot">
        <i class="fas fa-clock"></i>
        Начало приема: <?php echo date('H:i', strtotime($app['start_time'])); ?>
    </div>
<?php endif; ?>

        <div class="application-status status-<?php echo strtolower($app['status']); ?>">
            <?php echo getStatusText($app['status']); ?>
        </div>
        
        <form method="POST" class="cancel-form">
            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
            <button type="submit" name="cancel_application" class="btn btn-danger">
                <i class="fas fa-times-circle"></i> Отменить заявку
            </button>
        </form>
    </div>
</div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> История действий</h2>
                </div>
                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <p>История действий пуста</p>
                    </div>
                <?php else: ?>
                    <div class="history-cards">
                        <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <div class="history-image" style="background-image: url('<?php echo SITE_URL . '/assets/images/animals/' . htmlspecialchars($item['image_path']); ?>')"></div>
                                <div class="history-details">
                                    <h4><?php echo htmlspecialchars($item['animal_name']); ?></h4>
                                    <div class="history-meta">
                                        <span class="action-type <?php echo $item['action']; ?>">
                                            <?php echo getActionText($item['action']); ?>
                                        </span>
                                        <span class="action-date">
                                            <?php echo date('d.m.Y H:i', strtotime($item['date'])); ?>
                                        </span>
                                        <span class="history-status <?php echo strtolower($item['status']); ?>">
                                            <?php 
                                            echo getStatusText($item['status']);
                                            // Добавляем пояснение для отмененных заявок
                                            if ($item['status'] === 'canceled') {
                                            } elseif ($item['status'] === 'missed') {
                                                echo ' (посещение пропущено)';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['details'])): ?>
                                        <p class="action-details"><?php echo htmlspecialchars($item['details']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>