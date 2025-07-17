<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = "Контакты | " . SITE_NAME;
require_once 'includes/header.php';

// Получаем расписание из базы данных
$schedule = getShelterSchedule($pdo);
$isOpen = isShelterOpen($pdo);
?>

<div class="contacts-container">
    <h1>Контакты приюта WoofMeow</h1>
    
    <div class="contacts-grid">
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h2>Адрес</h2>
            <p>г. Москва, ул. Добрых животных, д. 15</p>
            <div class="map-container">
                <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3A1a2b3c4d5e6f7g8h9i0j&amp;source=constructor" 
                        width="100%" height="300" frameborder="0"></iframe>
            </div>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h2>Часы работы</h2>
            <div class="schedule">
                <?php foreach ($schedule as $day => $time): ?>
                    <div class="schedule-item">
                        <span class="day"><?php echo htmlspecialchars($day); ?></span>
                        <span class="time"><?php echo htmlspecialchars($time); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="current-status">
                Сейчас приют: 
                <span class="<?php echo $isOpen ? 'status-open' : 'status-closed'; ?>">
                    <?php echo $isOpen ? 'ОТКРЫТ' : 'ЗАКРЫТ'; ?>
                </span>
            </div>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h2>Контакты</h2>
            <ul class="contact-list">
                <li><i class="fas fa-phone"></i> <a href="tel:+74951234567">+7 (495) 123-45-67</a></li>
                <li><i class="fas fa-envelope"></i> <a href="mailto:info@woofmeow.ru">info@woofmeow.ru</a></li>
                <li><i class="fab fa-vk"></i> <a href="https://vk.com/woofmeow" target="_blank">Группа ВКонтакте</a></li>
                <li><i class="fab fa-telegram"></i> <a href="https://t.me/woofmeow" target="_blank">Телеграм-канал</a></li>
            </ul>
        </div>
    </div>
    
    <div class="volunteer-section">
        <h2>Хотите помочь приюту?</h2>
        <p>Мы всегда рады волонтерам и любой помощи. Вы можете:</p>
        <ul class="volunteer-options">
            <li><i class="fas fa-hand-holding-heart"></i> Стать волонтером</li>
            <li><i class="fas fa-donate"></i> Сделать пожертвование</li>
            <li><i class="fas fa-bone"></i> Принести корм или лекарства</li>
            <li><i class="fas fa-home"></i> Стать временным приютом</li>
        </ul>
        <p>Подробная информация по ссылке на наш телеграм канал</p>
        <a href="https://t.me/woofmeow" target="_blank" class="btn btn-primary">Написать в телеграмм</a>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>