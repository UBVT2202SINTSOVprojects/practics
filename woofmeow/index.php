<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Главная | " . SITE_NAME;
require_once 'includes/header.php';

// Получаем несколько случайных животных для показа на главной
$stmt = $pdo->query("SELECT * FROM animals WHERE status = 'available' ORDER BY RAND() LIMIT 3");
$featuredAnimals = $stmt->fetchAll();

// Получаем расписание приюта
$schedule = getShelterSchedule($pdo);
$isOpen = isShelterOpen($pdo);
$heroOfTheDay = getHeroOfTheDay($pdo);

function getHeroOfTheDay($pdo) {
    $today = date('Y-m-d');
    $cacheKey = 'hero_of_the_day_' . $today;
    
    if (isset($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }
    
    $stmt = $pdo->query("SELECT * FROM animals WHERE status = 'available' ORDER BY RAND() LIMIT 1");
    $hero = $stmt->fetch();
    
    $_SESSION[$cacheKey] = $hero;
    return $hero;
}
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
<div class="home-container">
    <!-- Герой-секция -->
<?php if ($heroOfTheDay && !empty($heroOfTheDay['id'])): ?>
<section class="hero-of-the-day">
    <h2>Герой дня</h2>
    <p class="section-subtitle">Особенный питомец, который сегодня заслуживает вашего внимания</p>
    
    <div class="animal-card hero-card">
        <!-- Простая проверка изображения без сложных условий -->
        <div class="animal-image" style="background-image: url('<?= SITE_URL ?>/assets/images/animals/<?= htmlspecialchars($heroOfTheDay['image_path'] ?? 'default.jpg') ?>')"></div>
        
        <div class="animal-info">
            <h3><?= htmlspecialchars($heroOfTheDay['name']) ?></h3>
            <div class="animal-meta">
                <span class="animal-type <?= $heroOfTheDay['type'] ?>">
                    <?= getAnimalType($heroOfTheDay['type']) ?>
                </span>
                <span class="animal-gender <?= $heroOfTheDay['gender'] ?>">
                    <?= $heroOfTheDay['gender'] === 'male' ? ' Мальчик' : ' Девочка' ?>
                </span>
                <span class="animal-age">
                    <?= formatAge($heroOfTheDay['age_months']) ?>
                </span>
            </div>
            <p class="animal-breed"><strong>Порода:</strong> <?= htmlspecialchars($heroOfTheDay['breed']) ?></p>
            <p class="animal-description"><?= !empty($heroOfTheDay['description']) ? htmlspecialchars($heroOfTheDay['description']) : 'Нет описания' ?></p>
            <a href="animal.php?id=<?= $heroOfTheDay['id'] ?>" class="btn btn-primary">Подробнее</a>
        </div>
    </div>
</section>
<?php endif; ?>

    <!-- Секция с избранными животными -->
    <section class="featured-animals">
        <h2>Кто ищет дом</h2>
        <p class="section-subtitle">Познакомьтесь с некоторыми из наших подопечных</p>
        
        <div class="animals-grid">
            <?php if (empty($featuredAnimals)): ?>
                <div class="empty-state">
                    <i class="fas fa-paw"></i>
                    <p>Сейчас все животные нашли дом</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredAnimals as $animal): ?>
                    <div class="animal-card">
                        <div class="animal-image" style="background-image: url('<?= SITE_URL . '/assets/images/animals/' . htmlspecialchars($animal['image_path']) ?>')"></div>
                        <div class="animal-info">
                            <h3><?= htmlspecialchars($animal['name']) ?></h3>
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
                            <p class="animal-breed"><?= htmlspecialchars($animal['breed']) ?></p>
                            <a href="animal.php?id=<?= $animal['id'] ?>" class="btn btn-outline">Подробнее</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="view-all">
            <a href="catalog.php" class="btn btn-primary">Посмотреть всех</a>
        </div>
    </section>

    <!-- Информационная секция -->
    <section class="info-section">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-home"></i>
            </div>
            <h3>Наш приют</h3>
            <p>Более 100 животных ежегодно находят новый дом благодаря нашей работе</p>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-hand-holding-heart"></i>
            </div>
            <h3>Как помочь</h3>
            <p>Вы можете помочь животным разными способами - от волонтерства до пожертвований</p>
            <a href="help.php" class="info-link">Подробнее <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h3>Часы работы</h3>
            <div class="schedule-mini">
                <?php foreach (array_slice($schedule, 0, 3) as $day => $time): ?>
                    <div class="schedule-item">
                        <span class="day"><?= htmlspecialchars($day) ?></span>
                        <span class="time"><?= htmlspecialchars($time) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="current-status">
                Приют: 
                <span class="<?= $isOpen ? 'status-open' : 'status-closed' ?>">
                    <?= $isOpen ? 'ОТКРЫТ' : 'ЗАКРЫТ' ?>
                </span>
            </div>
        </div>
    </section>


</div>

<?php
require_once 'includes/footer.php';
?>