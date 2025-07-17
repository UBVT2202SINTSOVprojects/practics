<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка авторизации
if (!is_logged_in()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('login.php');
}

// Проверка наличия ID животного
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('catalog.php');
}

$animalId = (int)$_GET['id'];

// Получаем данные животного и проверяем его доступность
$stmt = $pdo->prepare("SELECT a.*, 
    (SELECT COUNT(*) FROM applications 
     WHERE animal_id = a.id AND type = 'adoption' 
     AND status IN ('created', 'active')) as active_applications
    FROM animals a WHERE id = ?");
$stmt->execute([$animalId]);
$animal = $stmt->fetch();

// Если животное не найдено или есть активные заявки - редирект
if (!$animal || $animal['active_applications'] > 0) {
    $_SESSION['message'] = [
        'type' => 'warning',
        'text' => $animal 
            ? 'На этого питомца уже есть активная заявка на усыновление' 
            : 'Питомец не найден',
        'link' => ['url' => 'catalog.php', 'text' => 'Выбрать другого питомца']
    ];
    redirect('catalog.php');
}

// Получаем данные пользователя
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Обработка формы
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $timeSlot = trim($_POST['time_slot'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    // Валидация
    if (!empty($phone) && !preg_match('/^\+?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $errors['phone'] = "Неверный формат телефона";
    }
    
    if (empty($date)) {
        $errors['date'] = "Выберите дату посещения";
    }
    
    if (empty($timeSlot)) {
        $errors['time_slot'] = "Выберите временной слот";
    }
    
    if (empty($errors)) {
        // Для усыновления берем слот 1 час
        $startTime = $timeSlot . ':00';
        $endTime = date('H:i:s', strtotime($timeSlot . ' +1 hour'));
        $startDateTime = $date . ' ' . $startTime;
        $endDateTime = $date . ' ' . $endTime;
        
        // Проверяем доступность слота
        if (!isTimeSlotAvailableForAdoption($pdo, $startDateTime, $endDateTime) || 
            !isTimeSlotAvailableForAnimal($pdo, $animalId, $startDateTime, $endDateTime)) {
            $errors[] = "Выбранное время больше недоступно";
        } else {
            // Создаем заявку
            $stmt = $pdo->prepare("
                INSERT INTO applications 
                (user_id, animal_id, type, start_time, end_time, phone, comment, status, created_at) 
                VALUES (?, ?, 'adoption', ?, ?, ?, ?, 'created', NOW())
            ");
            $result = $stmt->execute([
                $userId, 
                $animalId, 
                $startDateTime, 
                $endDateTime,
                $phone,
                $comment
            ]);
            
            if ($result) {
                $success = true;
            } else {
                $errors[] = "Ошибка при создании заявки";
            }
        }
    }
}

// Генерируем список рабочих дат на 2 недели вперед
$today = new DateTime();
$today->setTime(0, 0, 0);
$endDate = clone $today;
$endDate->add(new DateInterval('P14D'));

$stmt = $pdo->query("SELECT day_of_week FROM shelter_schedule WHERE is_working_day = 1");
$workingDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

$availableDates = [];
$interval = new DateInterval('P1D');
$period = new DatePeriod($today, $interval, $endDate);

foreach ($period as $date) {
    $dayOfWeek = $date->format('N');
    if (in_array($dayOfWeek, $workingDays)) {
        $availableDates[] = $date->format('Y-m-d');
    }
}

$pageTitle = "Усыновление {$animal['name']} | " . SITE_NAME;
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/adoption.css">
<div class="adoption-container">
    <div class="adoption-card">
        <div class="adoption-header">
            <h1>Заявка на усыновление</h1>
            <p>Заполните форму для подачи заявки на усыновление <?php echo htmlspecialchars($animal['name']); ?></p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success">
                <p>Ваша заявка на усыновление успешно создана!</p>
                <p>Мы свяжемся с вами для уточнения деталей. Вы можете просмотреть статус заявки или отменить ее в <a href="dashboard.php">личном кабинете</a>.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" id="adoptionForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Имя</label>
                        <input type="text" id="first_name" name="first_name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                               readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Фамилия</label>
                        <input type="text" id="last_name" name="last_name" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                               readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           readonly>
                </div>
                
                <div class="form-group">
    <label for="phone">Телефон (необязательно)</label>
    <input type="tel" id="phone" name="phone" 
           class="form-control" 
           placeholder="+7 (999) 123-45-67"
           value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
    <small class="form-hint">Мы свяжемся с вами для уточнения деталей</small>
    <?php if (isset($errors['phone'])): ?>
        <div class="error-message"><?php echo $errors['phone']; ?></div>
    <?php endif; ?>
</div>
                
                <div class="form-group">
                    <label for="date">Дата посещения</label>
                    <select id="date" name="date" class="form-control" required>
                        <option value="">Выберите дату</option>
                        <?php foreach ($availableDates as $date): 
                            $dateObj = new DateTime($date);
                            $displayDate = $dateObj->format('d.m.Y') . ' (' . getRussianWeekDay($dateObj->format('N')) . ')';
                            $selected = ($_POST['date'] ?? '') === $date ? 'selected' : '';
                        ?>
                            <option value="<?php echo $date; ?>" <?php echo $selected; ?>><?php echo $displayDate; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['date'])): ?>
                        <div class="error-message"><?php echo $errors['date']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="time_slot">Временной слот (1 час)</label>
                    <select id="time_slot" name="time_slot" class="form-control" required>
                        <option value="">Сначала выберите дату</option>
                    </select>
                    <?php if (isset($errors['time_slot'])): ?>
                        <div class="error-message"><?php echo $errors['time_slot']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="comment">Комментарий (необязательно)</label>
                    <textarea id="comment" name="comment" class="form-control" rows="4" 
                              placeholder="Расскажите о себе, условиях проживания животного и т.д."><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-note">
                    <p><i class="fas fa-info-circle"></i> Максимальное количество активных заявок на усыновление - 2.</p>
                    <p><i class="fas fa-info-circle"></i> Время доступно с учетом временных интервалов и нагрузки на наших сотрудников.</p>
		    <p><i class="fas fa-info-circle"></i> Опоздание больше чем на 15 минут может привести к отмене заявки.</p>
                    <p><i class="fas fa-info-circle"></i> При себе необходимо иметь паспорт для оформления договора.</p>
                    <p><i class="fas fa-info-circle"></i> Вы можете отменить заявку в <a href="dashboard.php">личном кабинете</a>.</p>
                </div>
                
                <button type="submit" class="btn btn-primary">Подать заявку на усыновление</button>
            </form>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const dateInput = document.getElementById('date');
                const timeSelect = document.getElementById('time_slot');
                
                dateInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    const animalId = <?php echo $animalId; ?>;
                    
                    if (!selectedDate) {
                        timeSelect.innerHTML = '<option value="">Сначала выберите дату</option>';
                        return;
                    }
                    
                    timeSelect.innerHTML = '<option value="">Загрузка доступных слотов...</option>';
                    
                    fetch(`get_available_times.php?animal_id=${animalId}&date=${selectedDate}&type=adoption`)
                        .then(response => response.json())
                        .then(data => {
                            timeSelect.innerHTML = '<option value="">Выберите время</option>';
                            
                            if (data.success && data.times.length > 0) {
                                data.times.forEach(time => {
                                    const option = document.createElement('option');
                                    option.value = time;
                                    option.textContent = time;
                                    timeSelect.appendChild(option);
                                });
                            } else {
                                const option = document.createElement('option');
                                option.textContent = 'Нет доступных временных слотов';
                                option.disabled = true;
                                timeSelect.appendChild(option);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            timeSelect.innerHTML = '<option value="">Ошибка загрузки слотов</option>';
                        });
                });
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>