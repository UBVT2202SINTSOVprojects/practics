<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.0 404 Not Found");
    die("Животное не найдено");
}

$animalId = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
$stmt->execute([$animalId]);
$animal = $stmt->fetch();

if (!$animal) {
    header("HTTP/1.0 404 Not Found");
    die("Животное не найдено");
}

$hasActiveAdoptionApplications = false;
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications 
    WHERE animal_id = ? 
    AND type = 'adoption' 
    AND status IN ('created', 'active')
");
$stmt->execute([$animalId]);
$hasActiveAdoptionApplications = $stmt->fetchColumn() > 0;

$isAvailableForAdoption = $animal['status'] === 'available' && !$hasActiveAdoptionApplications;

$pageTitle = $animal['name'] . " | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/animal.css">
<div class="animal-profile">
    <div class="animal-main">
        <div class="animal-gallery">
            <img src="<?php echo SITE_URL . '/assets/images/animals/' . htmlspecialchars($animal['image_path']); ?>" 
                 alt="<?php echo htmlspecialchars($animal['name']); ?>" class="main-image">
        </div>
        
        <div class="animal-info">
            <h1><?php echo htmlspecialchars($animal['name']); ?></h1>
            
            <div class="animal-meta">
                <span class="animal-type <?php echo htmlspecialchars($animal['type']); ?>">
                    <?php echo getAnimalType($animal['type']); ?>
                </span>
<span class="animal-gender <?= $animal['gender'] ?>">
    <?= $animal['gender'] === 'male' ? ' Мальчик' : ' Девочка' ?>
</span>
                <span class="animal-age">
                    <?= formatAge($animal['age_months']) ?>
		</span>
            </div>
                            <div class="animal-details">
                    <p><strong>Порода:</strong> <?php echo htmlspecialchars($animal['breed']); ?></p>
                    <p><strong>Окрас:</strong> <?php echo htmlspecialchars($animal['color']); ?></p>
                </div>
            <div class="animal-description">
                <h3>Описание</h3>
                <p><?php echo nl2br(htmlspecialchars($animal['description'])); ?></p>
                
                <?php if (!empty($animal['detailed_description'])): ?>
                    <h3>Подробное описание</h3>
                    <p><?php echo nl2br(htmlspecialchars($animal['detailed_description'])); ?></p>
                <?php endif; ?>
                

            </div>
        </div>
    </div>
    
    <div class="animal-sidebar">
        <div class="sidebar-card">
            <h3><i class="fas fa-paw"></i> Действия</h3>
            
            <?php if (is_logged_in()): ?>
                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'user'): ?>
                    <div class="quick-actions">
                        <a href="reservation.php?id=<?php echo $animalId; ?>" class="btn btn-primary">
                            <i class="fas fa-handshake"></i> Познакомиться
                        </a>
                        
                        <?php if ($isAvailableForAdoption): ?>
                            <a href="adoption.php?id=<?php echo $animalId; ?>" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Усыновить
                            </a>
                        <?php else: ?>
                            <div class="not-available">
                                <i class="fas fa-info-circle"></i>
                                <?php if ($hasActiveAdoptionApplications): ?>
                                    На это животное уже подана заявка на усыновление
                                <?php else: ?>
                                    Это животное уже не доступно для усыновления
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
    <div class="auth-notice">
        <i class="fas fa-exclamation-circle"></i>
        <span>Для этих действий необходимо <a href="login.php">войти</a> или <a href="register.php">зарегистрироваться</span>
    </div>

                <div class="quick-actions">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-handshake"></i> Познакомиться
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Усыновить
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-card">
            <h3><i class="fas fa-clock"></i> Часы работы</h3>
            <ul class="schedule-list">
                <?php foreach (getShelterSchedule($pdo) as $day => $time): ?>
                    <li>
                        <span class="day"><?php echo $day; ?></span>
                        <span class="time"><?php echo $time; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="current-status">
                Сейчас приют: 
                <span class="<?php echo isShelterOpen($pdo) ? 'status-open' : 'status-closed'; ?>">
                    <?php echo isShelterOpen($pdo) ? 'ОТКРЫТ' : 'ЗАКРЫТ'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>