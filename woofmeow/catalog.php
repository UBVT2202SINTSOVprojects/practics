<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем уровень доступа пользователя
$isAdminOrStaff = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'staff']);

// Получаем параметры фильтрации
$type = $_GET['type'] ?? null;
$gender = $_GET['gender'] ?? null;
$breed = $_GET['breed'] ?? null;
$search = $_GET['search'] ?? null;

// Формируем SQL запрос с учетом фильтров
$sql = "SELECT * FROM animals WHERE 1=1";
$params = [];

// Для обычных пользователей показываем только доступных животных
if (!$isAdminOrStaff) {
    $sql .= " AND status = 'available'";
}

if ($type && in_array($type, ['cat', 'dog', 'kitten', 'puppy'])) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

if ($gender && in_array($gender, ['male', 'female'])) {
    $sql .= " AND gender = ?";
    $params[] = $gender;
}

if ($breed) {
    $sql .= " AND breed LIKE ?";
    $params[] = "%$breed%";
}

if ($search) {
    $sql .= " AND (name LIKE ? OR breed LIKE ? OR color LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$sql .= " ORDER BY name ASC";

// Получаем список всех пород для фильтра
$breedsStmt = $pdo->query("SELECT DISTINCT breed FROM animals ORDER BY breed ASC");
$allBreeds = $breedsStmt->fetchAll(PDO::FETCH_COLUMN);

// Выполняем запрос для животных
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll();

$pageTitle = "Каталог животных | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/catalog.css">
<div class="catalog-container">
    <h1>Наши питомцы</h1>
    
    <!-- Фильтры (остаются без изменений) -->
    <div class="filters">
        <form method="get" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="type">Вид:</label>
                    <select id="type" name="type" onchange="this.form.submit()">
                        <option value="">Все</option>
                        <option value="cat" <?= $type === 'cat' ? 'selected' : '' ?>>Кошки</option>
                        <option value="dog" <?= $type === 'dog' ? 'selected' : '' ?>>Собаки</option>
                        <option value="kitten" <?= $type === 'kitten' ? 'selected' : '' ?>>Котята</option>
                        <option value="puppy" <?= $type === 'puppy' ? 'selected' : '' ?>>Щенки</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="gender">Пол:</label>
                    <select id="gender" name="gender" onchange="this.form.submit()">
                        <option value="">Все</option>
                        <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Мальчик</option>
                        <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Девочка</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="breed">Порода:</label>
                    <select id="breed" name="breed" onchange="this.form.submit()">
                        <option value="">Все</option>
                        <?php foreach ($allBreeds as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= $breed === $b ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group search-group">
                    <label for="search">Поиск:</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search ?? '') ?>" 
                           placeholder="Имя, порода, окрас...">
                    <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                </div>
                
                <button type="button" class="btn btn-outline" onclick="resetFilters()">Сбросить</button>
            </div>
        </form>
    </div>
    
    <!-- Результаты -->
    <div class="animals-grid">
        <?php if (empty($animals)): ?>
            <div class="empty-state">
                <i class="fas fa-paw"></i>
                <p>По вашему запросу животных не найдено</p>
                <a href="catalog.php" class="btn btn-primary">Показать всех</a>
            </div>
        <?php else: ?>
            <?php foreach ($animals as $animal): ?>
                <div class="animal-card">
                    <div class="animal-image" style="background-image: url('<?= SITE_URL . '/assets/images/animals/' . htmlspecialchars($animal['image_path']) ?>')"></div>
                    <div class="animal-info">
                        <div class="animal-header">
                            <h2><?= htmlspecialchars($animal['name']) ?></h2>
                            
<?php if ($isAdminOrStaff): ?>
    <div class="animal-status status-<?= $animal['status'] ?>">
        <?= getAnimalStatus($animal['status']) ?>
    </div>
<?php endif; ?>
                            
                            <div class="animal-meta">
                                <span class="animal-type <?= $animal['type'] ?>">
                                    <?= getAnimalType($animal['type']) ?>
                                </span>
<span class="animal-gender <?= $animal['gender'] ?>">
    <?= $animal['gender'] === 'male' ? ' Мальчик' : ' Девочка' ?>
</span>
                                <span class="animal-age">
                                    <?= formatAge($animal['age_months']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="animal-details">
                            <p><strong>Порода:</strong> <?= htmlspecialchars($animal['breed']) ?></p>
                            <p><strong>Окрас:</strong> <?= htmlspecialchars($animal['color']) ?></p>
                            <p class="animal-description"><?= htmlspecialchars(shortenDescription($animal['description'])) ?></p>
                        </div>
                        
                        <div class="animal-actions">
                            <a href="animal.php?id=<?= $animal['id'] ?>" class="btn btn-primary">
                                <i class="fas"></i> Подробнее
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function resetFilters() {
    window.location.href = 'catalog.php';
}
</script>

<?php
require_once 'includes/footer.php';

// Вспомогательные функции



function getNoun($number, $one, $two, $five) {
    $n = abs($number) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $five;
    if ($n1 > 1 && $n1 < 5) return $two;
    if ($n1 == 1) return $one;
    return $five;
}

?>