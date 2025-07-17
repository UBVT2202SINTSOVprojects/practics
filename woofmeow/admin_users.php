<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка прав доступа - только для администраторов
if (!is_logged_in() || $_SESSION['user_type'] !== 'admin') {
    redirect('dashboard.php');
}

// Параметры фильтрации
$searchTerm = $_GET['search'] ?? '';
$selectedType = $_GET['type'] ?? '';

// Получаем список пользователей с фильтрами
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($selectedType)) {
    $sql .= " AND user_type = ?";
    $params[] = $selectedType;
}

// Сортировка по имени и фамилии
$sql .= " ORDER BY first_name, last_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = "Управление пользователями | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1>Управление пользователями</h1>
                <a href="admin_user_edit.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Добавить пользователя
                </a>
            </div>
            
            <!-- Фильтры -->
            <div class="filters">
                <form method="get" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Поиск:</label>
                            <input type="text" id="search" name="search" 
                                   value="<?= htmlspecialchars($searchTerm) ?>" 
                                   placeholder="Имя, фамилия, email или логин">
                        </div>
                        
                        <div class="filter-group">
                            <label for="type">Тип пользователя:</label>
                            <select id="type" name="type">
                                <option value="">Все</option>
                                <option value="admin" <?= $selectedType === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                <option value="staff" <?= $selectedType === 'staff' ? 'selected' : '' ?>>Сотрудник</option>
                                <option value="user" <?= $selectedType === 'user' ? 'selected' : '' ?>>Пользователь</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-outline">Применить</button>
                        <a href="admin_users.php" class="btn btn-outline">Сбросить</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>Пользователи не найдены</p>
                </div>
            <?php else: ?>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>Логин</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Тип</th>
                                <th>Дата регистрации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '—' ?></td>
                                    <td>
                                        <span class="user-type-badge user-type-<?= strtolower($user['user_type']) ?>">
                                            <?= getUserTypeText($user['user_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <a href="admin_user_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-action">
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