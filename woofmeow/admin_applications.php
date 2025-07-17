<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка прав доступа
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}

// Параметры фильтрации
$searchUser = $_GET['search_user'] ?? '';
$searchAnimal = $_GET['search_animal'] ?? '';
$selectedDate = $_GET['date'] ?? '';

// Получаем список заявок с фильтрами
$sql = "SELECT a.*, 
               u.username as user_name, 
               u.first_name as user_first_name, 
               u.last_name as user_last_name,
               u.phone as user_phone,
               u.email as user_email,
               an.name as animal_name,
               an.image_path as animal_image,
               an.type as animal_type
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN animals an ON a.animal_id = an.id
        WHERE 1=1";

$params = [];

if (!empty($searchUser)) {
    $sql .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchParam = "%$searchUser%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($searchAnimal)) {
    $sql .= " AND an.name LIKE ?";
    $params[] = "%$searchAnimal%";
}

if (!empty($selectedDate)) {
    $sql .= " AND DATE(a.start_time) = ?";
    $params[] = $selectedDate;
}

$sql .= " ORDER BY 
            CASE WHEN a.status = 'created' THEN 0 
                 WHEN a.status = 'active' THEN 1
                 ELSE 2 END,
            a.start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$pageTitle = "Управление заявками | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1>Управление заявками</h1>
                <a href="admin_application_edit.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Создать заявку
                </a>
            </div>
            
            <!-- Фильтры -->
            <div class="filters">
                <form method="get" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search_user">Поиск по пользователю:</label>
                            <input type="text" id="search_user" name="search_user" 
                                   value="<?= htmlspecialchars($searchUser) ?>" 
                                   placeholder="Имя, фамилия или логин">
                        </div>
                        
                        <div class="filter-group">
                            <label for="search_animal">Поиск по животному:</label>
                            <input type="text" id="search_animal" name="search_animal" 
                                   value="<?= htmlspecialchars($searchAnimal) ?>" 
                                   placeholder="Имя животного">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Дата:</label>
                            <input type="date" id="date" name="date" 
                                   value="<?= htmlspecialchars($selectedDate) ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-outline">Применить</button>
                        <a href="admin_applications.php" class="btn btn-outline">Сбросить</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Заявки не найдены</p>
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
                                <th>Дата и время</th>
                                <th>Статус</th>
                                <th>Телефон</th>
                                <th>Комментарий пользователя</th>
                                <th>Комментарий сотрудника</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
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
                                        <?= date('d.m.Y H:i', strtotime($app['start_time'])) ?> - 
                                        <?= date('H:i', strtotime($app['end_time'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($app['status']) ?>">
                                            <?= getStatusText($app['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($app['phone']) ? htmlspecialchars($app['phone']) : htmlspecialchars($app['user_phone']) ?></td>
                                    <td>
    <?= !empty($app['comment']) ? htmlspecialchars($app['comment']) : '—' ?>
</td>
                                    <td>
                                        <?= !empty($app['visit_comments']) ? htmlspecialchars($app['visit_comments']) : '—' ?>
                                    </td>
                                    <td>
                                        <a href="admin_application_edit.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-action">
                                            <i class="fas fa-edit"></i> Редактировать
                                        </a>
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

<?php
require_once 'includes/footer.php';
?>