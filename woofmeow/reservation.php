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

// Получаем данные животного
$stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
$stmt->execute([$animalId]);
$animal = $stmt->fetch();

if (!$animal) {
    redirect('catalog.php');
}

// Получаем данные пользователя
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Проверяем количество активных резервирований пользователя
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications 
    WHERE user_id = ? 
    AND type = 'reservation' 
    AND status IN ('created', 'active')
");
$stmt->execute([$userId]);
$activeReservations = $stmt->fetchColumn();
$maxReservationsReached = $activeReservations >= 2;

// Обработка формы
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$maxReservationsReached) {
    $phone = trim($_POST['phone'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $timeSlot = trim($_POST['time_slot'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    // Валидация
    if (empty($date)) {
        $errors['date'] = "Выберите дату посещения";
    }
    
    if (empty($duration)) {
        $errors['duration'] = "Выберите продолжительность визита";
    } elseif ($duration < 1 || $duration > 3) {
        $errors['duration'] = "Продолжительность должна быть от 1 до 3 часов";
    }
    
    if (empty($timeSlot)) {
        $errors['time_slot'] = "Выберите время начала";
    }
    
    if (!empty($phone) && !preg_match('/^\+?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $errors['phone'] = "Неверный формат телефона";
    }
    
    if (empty($errors)) {
        $startTime = $timeSlot . ':00';
        $endTime = date('H:i:s', strtotime($timeSlot . ' +' . $duration . ' hour'));
        $startDateTime = $date . ' ' . $startTime;
        $endDateTime = $date . ' ' . $endTime;
        
        // Проверяем доступность слота
        if (!isTimeSlotAvailableForAnimal($pdo, $animalId, $startDateTime, $endDateTime) ||
            !isTimeSlotAvailableForReservation($pdo, $startDateTime, $endDateTime)) {
            $errors[] = "Выбранное время больше недоступно";
        } else {
            // Создаем заявку
            $stmt = $pdo->prepare("
                INSERT INTO applications 
                (user_id, animal_id, type, start_time, end_time, phone, comment, status, created_at) 
                VALUES (?, ?, 'reservation', ?, ?, ?, ?, 'created', NOW())
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

$pageTitle = "Резервирование {$animal['name']} | " . SITE_NAME;
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/reservation.css">
<div class="reservation-container">
    <div class="reservation-card">
        <div class="reservation-header">
            <h1>Бронирование времени для знакомства</h1>
            <p>Выберите удобное время для посещения приюта</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success">
                <p>Ваша заявка на посещение успешно создана!</p>
                <p>Вы можете просмотреть свои заявки в <a href="dashboard.php">личном кабинете</a>.</p>
            </div>
        <?php elseif ($maxReservationsReached): ?>
            <div class="alert warning">
                <p>У вас уже есть 2 активные заявки на посещение. Вы не можете создать новую заявку, пока одна из текущих не будет завершена.</p>
                <p>Вы можете отменить текущие заявки в <a href="dashboard.php">личном кабинете</a>.</p>
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
            
            <form method="post" id="reservationForm">
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
    <small class="form-hint">Мы свяжемся с вами, если потребуется</small>
    <?php if (isset($errors['phone'])): ?>
        <div class="error-message"><?php echo $errors['phone']; ?></div>
    <?php endif; ?>
</div>
                
                <div class="form-group">
                    <label for="date">Дата посещения</label>
                    <select id="date" name="date" class="form-control" required>
                        <option value="">Выберите дату</option>
                        <?php
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);
                        $endDate = clone $today;
                        $endDate->add(new DateInterval('P14D')); // 2 недели вперед
                        
                        $stmt = $pdo->query("SELECT day_of_week FROM shelter_schedule WHERE is_working_day = 1");
                        $workingDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $interval = new DateInterval('P1D');
                        $period = new DatePeriod($today, $interval, $endDate);
                        
                        foreach ($period as $date) {
                            $dayOfWeek = $date->format('N');
                            if (in_array($dayOfWeek, $workingDays)) {
                                $dateStr = $date->format('Y-m-d');
                                $displayDate = $date->format('d.m.Y') . ' (' . getRussianWeekDay($dayOfWeek) . ')';
                                $selected = ($_POST['date'] ?? '') === $dateStr ? 'selected' : '';
                                echo "<option value='$dateStr' $selected>$displayDate</option>";
                            }
                        }
                        ?>
                    </select>
                    <?php if (isset($errors['date'])): ?>
                        <div class="error-message"><?php echo $errors['date']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="duration">Продолжительность визита</label>
                    <select id="duration" name="duration" class="form-control" required>
                        <option value="">Выберите продолжительность</option>
                        <option value="1" <?= ($_POST['duration'] ?? '') == '1' ? 'selected' : '' ?>>1 час</option>
                        <option value="2" <?= ($_POST['duration'] ?? '') == '2' ? 'selected' : '' ?>>2 часа</option>
                        <option value="3" <?= ($_POST['duration'] ?? '') == '3' ? 'selected' : '' ?>>3 часа</option>
                    </select>
                    <?php if (isset($errors['duration'])): ?>
                        <div class="error-message"><?php echo $errors['duration']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group time-slot-group" style="display: none;">
                    <label for="time_slot">Выберите удобное время</label>
                    <select id="time_slot" name="time_slot" class="form-control" required>
                        <option value="">Сначала выберите дату и продолжительность</option>
                    </select>
                    <?php if (isset($errors['time_slot'])): ?>
                        <div class="error-message"><?php echo $errors['time_slot']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="comment">Комментарий (необязательно)</label>
                    <textarea id="comment" name="comment" class="form-control" rows="3" 
                              placeholder="Укажите дополнительную информацию, если необходимо"><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-note">
                    <p><i class="fas fa-info-circle"></i> Максимальное количество активных заявок на посещение - 2.</p>
                    <p><i class="fas fa-info-circle"></i> Время доступно с учетом временных интервалов и нагрузки на наших сотрудников.</p>
		    <p><i class="fas fa-info-circle"></i> Опоздание больше чем на 15 минут может привести к отмене заявки.</p>
                    <p><i class="fas fa-info-circle"></i> Вы можете отменить заявку в <a href="dashboard.php">личном кабинете</a>.</p>
                </div>
                
                <button type="submit" class="btn btn-primary">Забронировать время</button>
            </form>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const dateInput = document.getElementById('date');
                const durationInput = document.getElementById('duration');
                const timeSlotGroup = document.querySelector('.time-slot-group');
                const timeSelect = document.getElementById('time_slot');
                
                function updateTimeSlots() {
                    const selectedDate = dateInput.value;
                    const selectedDuration = durationInput.value;
                    const animalId = <?php echo $animalId; ?>;
                    
                    if (!selectedDate || !selectedDuration) {
                        timeSlotGroup.style.display = 'none';
                        return;
                    }
                    
                    timeSlotGroup.style.display = 'block';
                    timeSelect.innerHTML = '<option value="">Загрузка доступных слотов...</option>';
                    
                    fetch(`get_available_times.php?animal_id=${animalId}&date=${selectedDate}&type=reservation&duration=${selectedDuration}`)
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
                }
                
                dateInput.addEventListener('change', updateTimeSlots);
                durationInput.addEventListener('change', updateTimeSlots);
                
                // Инициализация при загрузке, если уже есть выбранные значения
                if (dateInput.value && durationInput.value) {
                    updateTimeSlots();
                }
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>