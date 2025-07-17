<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка прав доступа
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}

// Параметры фильтрации
$searchName = $_GET['search_name'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

// Получаем список животных с фильтрами
$sql = "SELECT * FROM animals WHERE 1=1";
$params = [];

if (!empty($searchName)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$searchName%";
}

if (!empty($selectedType)) {
    $sql .= " AND type = ?";
    $params[] = $selectedType;
}

if (!empty($selectedStatus)) {
    $sql .= " AND status = ?";
    $params[] = $selectedStatus;
}

// Сортировка сначала по статусу (available > sick > adopted), потом по ID
$sql .= " ORDER BY 
    CASE 
        WHEN status = 'available' THEN 1
        WHEN status = 'sick' THEN 2
        WHEN status = 'adopted' THEN 3
        ELSE 4
    END ASC,
    id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll();

$pageTitle = "Управление животными | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1>Управление животными</h1>
                <a href="admin_animal_edit.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Добавить животное
                </a>
            </div>
            
            <!-- Фильтры -->
            <div class="filters">
                <form method="get" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search_name">Поиск по имени:</label>
                            <input type="text" id="search_name" name="search_name" 
                                   value="<?= htmlspecialchars($searchName) ?>" 
                                   placeholder="Имя животного">
                        </div>
                        
                        <div class="filter-group">
                            <label for="type">Тип:</label>
                            <select id="type" name="type">
                                <option value="">Все</option>
                                <option value="cat" <?= $selectedType === 'cat' ? 'selected' : '' ?>>Кошка</option>
                                <option value="dog" <?= $selectedType === 'dog' ? 'selected' : '' ?>>Собака</option>
                                <option value="kitten" <?= $selectedType === 'kitten' ? 'selected' : '' ?>>Котенок</option>
                                <option value="puppy" <?= $selectedType === 'puppy' ? 'selected' : '' ?>>Щенок</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Статус:</label>
                            <select id="status" name="status">
                                <option value="">Все</option>
                                <option value="available" <?= $selectedStatus === 'available' ? 'selected' : '' ?>>Доступен</option>
                                <option value="sick" <?= $selectedStatus === 'sick' ? 'selected' : '' ?>>Болеет</option>
                                <option value="adopted" <?= $selectedStatus === 'adopted' ? 'selected' : '' ?>>Усыновлен</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-outline">Применить</button>
                        <a href="admin_animals.php" class="btn btn-outline">Сбросить</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($animals)): ?>
                <div class="empty-state">
                    <i class="fas fa-paw"></i>
                    <p>Животные не найдены</p>
                </div>
            <?php else: ?>
                <div class="animals-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>Тип</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($animals as $animal): ?>
                                <tr class="status-<?= strtolower($animal['status']) ?>">
                                    <td>
                                        <div class="animal-name-with-photo">
                                            <?php if (!empty($animal['image_path'])): ?>
                                                <img src="<?= SITE_URL . '/assets/images/animals/' . htmlspecialchars($animal['image_path']) ?>" 
                                                     alt="<?= htmlspecialchars($animal['name']) ?>" class="animal-thumb">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($animal['name']) ?>
                                        </div>
                                    </td>
                                    <td><?= getAnimalType($animal['type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($animal['status']) ?>">
                                            <?= getAnimalStatus($animal['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-sm btn-action">
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