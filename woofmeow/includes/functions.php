<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function validate_password($password) {
    if (strlen($password) < 10) {
        return "Пароль должен содержать не менее 10 символов";
    }
    if (!preg_match('/[A-Za-zА-Яа-я]/u', $password)) {
        return "Пароль должен содержать хотя бы одну букву";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Пароль должен содержать хотя бы одну цифру";
    }
    return true;
}

function register_user($username, $first_name, $last_name, $email, $password, $user_type, $pdo, $phone = null) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, password, user_type, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $first_name, $last_name, $email, $password_hash, $user_type, $phone]);
    } catch(PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return false;
    }
}

function display_errors($errors) {
    if (!empty($errors)) {
        echo '<div class="alert error">';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}


// Проверка часов работы приюта
function isShelterOpen($pdo) {
    $currentDay = date('N'); // 1-пн, 7-вс
    $currentTime = date('H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT * FROM shelter_schedule 
        WHERE day_of_week = ? 
        AND is_working_day = 1
        AND open_time <= ? 
        AND close_time > ?
    ");
    $stmt->execute([$currentDay, $currentTime, $currentTime]);
    
    return $stmt->fetch() !== false;
}



function createApplication($pdo, $userId, $animalId, $type, $startTime = null, $endTime = null, $phone = null, $comment = null) {
    try {
        $pdo->beginTransaction();
        
        // Проверяем доступность животного
        $stmt = $pdo->prepare("SELECT status FROM animals WHERE id = ?");
        $stmt->execute([$animalId]);
        $animalStatus = $stmt->fetchColumn();
        
        if ($animalStatus !== 'available') {
            return "Это животное уже не доступно";
        }
        
        // Для резервирования проверяем доступность временного слота
        if ($type === 'reservation') {
            if (!isTimeSlotAvailable($pdo, $animalId, $startTime, $endTime)) {
                return "Это время уже занято для данного животного";
            }
            
            if (getUserActiveReservationsCount($pdo, $userId) >= 2) {
                return "У вас уже максимальное количество резервирований (2)";
            }
        }
        
        // Для усыновления проверяем, что нет активных заявок
        if ($type === 'adoption') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM applications 
                WHERE animal_id = ? 
                AND type = 'adoption' 
                AND status IN ('created', 'active')
            ");
            $stmt->execute([$animalId]);
            if ($stmt->fetchColumn() > 0) {
                return "На это животное уже подана заявка на усыновление";
            }
        }
        
        // Создаем заявку
               // Создаем заявку
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (user_id, animal_id, type, start_time, end_time, phone, comment, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'created', NOW())
        ");
        $stmt->execute([
            $userId, 
            $animalId, 
            'adoption', 
            $startDateTime, 
            $endDateTime,
            $phone,
            $comment
        ]); 
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Application creation error: " . $e->getMessage());
        return "Ошибка при создании заявки: " . $e->getMessage();
    }
}


function getAnimalStatus($status) {
    switch ($status) {
        case 'available': return 'Доступен';
        case 'reserved': return 'Зарезервирован';
        case 'adopted': return 'Усыновлен';
        case 'sick': return 'Болеет';
        default: return $status;
    }
}

function getShelterSchedule($pdo) {
    $stmt = $pdo->query("SELECT * FROM shelter_schedule ORDER BY day_of_week");
    $schedule = [];
    
    // Группируем рабочие дни с одинаковым временем
    $weekdays = [];
    $weekend = [];
    
    while ($row = $stmt->fetch()) {
        if ($row['is_working_day']) {
            $time = date('H:i', strtotime($row['open_time'])) . ' - ' . 
                   date('H:i', strtotime($row['close_time']));
            
            if ($row['day_of_week'] >= 1 && $row['day_of_week'] <= 5) {
                $weekdays[$time] = true;
            } elseif ($row['day_of_week'] == 6) {
                $weekend['saturday'] = $time;
            } elseif ($row['day_of_week'] == 7) {
                $weekend['sunday'] = $time;
            }
        } else {
            if ($row['day_of_week'] == 6) {
                $weekend['saturday'] = 'выходной';
            } elseif ($row['day_of_week'] == 7) {
                $weekend['sunday'] = 'выходной';
            }
        }
    }
    
    // Формируем итоговое расписание
    if (!empty($weekdays)) {
        $weekdayTime = current(array_keys($weekdays));
        $schedule['Пн-Пт'] = $weekdayTime;
    }
    
    if (isset($weekend['saturday'])) {
        $schedule['Суббота'] = $weekend['saturday'];
    }
    
    if (isset($weekend['sunday'])) {
        $schedule['Воскресенье'] = $weekend['sunday'];
    }
    
    return $schedule;
}

function getStatusText($status) {
    switch ($status) {
        case 'created': return 'Создана';
        case 'active': return 'Активна';
        case 'completed': return 'Завершена';
        case 'canceled': return 'Отменена';
        default: return $status;
    }
}

function getActionText($action) {
    switch ($action) {
        case 'adoption': return 'Усыновление';
        case 'reservation': return 'Резервирование';
        case 'visit': return 'Посещение';
        default: return $action;
    }
}

function getAnimalType($type) {
    switch ($type) {
        case 'cat': return 'Кот';
        case 'dog': return 'Собака';
        case 'kitten': return 'Котенок';
        case 'puppy': return 'Щенок';
        default: return $type;
    }
}


function formatAge($months) {
    if ($months < 12) {
        return $months . ' ' . pluralForm($months, ['месяц', 'месяца', 'месяцев']);
    } else {
        $years = floor($months / 12);
        return $years . ' ' . pluralForm($years, ['год', 'года', 'лет']);
    }
}

function pluralForm($n, $forms) {
    return $n % 10 == 1 && $n % 100 != 11 ? $forms[0] :
        ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? $forms[1] : $forms[2]);
}

function isTimeSlotAvailable($pdo, $animalId, $startTime, $endTime) {
    // Проверяем что время попадает в часы работы приюта
    $startDay = date('N', strtotime($startTime));
    $startTimeFormatted = date('H:i:s', strtotime($startTime));
    $endTimeFormatted = date('H:i:s', strtotime($endTime));
    
    $stmt = $pdo->prepare("
        SELECT * FROM shelter_schedule 
        WHERE day_of_week = ? 
        AND is_working_day = 1
        AND open_time <= ? 
        AND close_time >= ?
    ");
    $stmt->execute([$startDay, $startTimeFormatted, $endTimeFormatted]);
    
    if (!$stmt->fetch()) {
        return false;
    }
    
    // Проверяем что слот не занят
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE animal_id = ? 
        AND type = 'reservation' 
        AND status IN ('created', 'active')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([$animalId, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
    
    return $stmt->fetchColumn() == 0;
}

function getUserActiveReservationsCount($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE user_id = ? 
        AND type = 'reservation' 
        AND status IN ('created', 'active')
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}


function updateApplicationStatuses($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Обновляем резервирования: created -> active
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET status = 'active' 
            WHERE type = 'reservation' 
            AND status = 'created' 
            AND start_time <= NOW() 
            AND end_time > NOW()
        ");
        $stmt->execute();
        
        // Помечаем пропущенные резервирования как missed
        $stmt = $pdo->prepare("
            SELECT * 
            FROM applications 
            WHERE type = 'reservation' 
            AND status = 'created' 
            AND end_time <= NOW()
        ");
        $stmt->execute();
        $missedReservations = $stmt->fetchAll();
        
        foreach ($missedReservations as $reservation) {
            // Добавляем запись в историю
            $stmt = $pdo->prepare("
                INSERT INTO history 
                (user_id, animal_id, application_id, action, status, date, details) 
                VALUES (?, ?, ?, 'visit', 'missed', NOW(), ?)
            ");
            $details = "Посещение не состоялось: " . date('d.m.Y H:i', strtotime($reservation['start_time'])) . 
                      " - " . date('H:i', strtotime($reservation['end_time']));
            $stmt->execute([
                $reservation['user_id'],
                $reservation['animal_id'],
                $reservation['id'],
                $details . " (автоматическая отмена из-за пропуска)"
            ]);
            
            // Обновляем статус заявки на 'missed'
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'missed' 
                WHERE id = ?
            ");
            $stmt->execute([$reservation['id']]);
        }
        
        // Переносим завершенные активные резервирования в историю
        $stmt = $pdo->prepare("
            SELECT * 
            FROM applications 
            WHERE type = 'reservation' 
            AND status = 'active' 
            AND end_time <= NOW()
        ");
        $stmt->execute();
        $completedReservations = $stmt->fetchAll();
        
        foreach ($completedReservations as $reservation) {
            // Добавляем запись в историю
            $stmt = $pdo->prepare("
                INSERT INTO history 
                (user_id, animal_id, application_id, action, status, date, details) 
                VALUES (?, ?, ?, 'visit', 'completed', ?, ?)
            ");
            $details = "Посещение животного " . date('d.m.Y H:i', strtotime($reservation['start_time'])) . 
                      " - " . date('H:i', strtotime($reservation['end_time']));
            $stmt->execute([
                $reservation['user_id'],
                $reservation['animal_id'],
                $reservation['id'],
                $reservation['end_time'], // Используем end_time как дату события
                $details
            ]);
            
            // Обновляем статус заявки на 'completed'
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET status = 'completed' 
                WHERE id = ?
            ");
            $stmt->execute([$reservation['id']]);
            
            // Обновляем статус животного, если нет других заявок
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM applications 
                WHERE animal_id = ? 
                AND status IN ('created', 'active')
            ");
            $stmt->execute([$reservation['animal_id']]);
            
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("
                    UPDATE animals 
                    SET status = 'available' 
                    WHERE id = ?
                ");
                $stmt->execute([$reservation['animal_id']]);
            }
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Status update error: " . $e->getMessage());
    }
}

function createAdoptionApplication($pdo, $userId, $animalId, $date, $timeSlot, $phone = null, $comment = null) {
    // Проверяем количество активных заявок пользователя
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE user_id = ? 
        AND type = 'adoption' 
        AND status IN ('created', 'active')
    ");
    $stmt->execute([$userId]);
    
    if ($stmt->fetchColumn() >= 2) {
        return ['success' => false, 'message' => 'У вас уже есть 2 активные заявки на усыновление'];
    }

    // Проверяем, что дата не раньше завтрашнего дня
    $tomorrow = new DateTime('tomorrow');
    $selectedDate = new DateTime($date);
    
    if ($selectedDate < $tomorrow) {
        return ['success' => false, 'message' => 'Дата должна быть не раньше завтрашнего дня'];
    }

    // Проверяем временной слот
    if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $timeSlot, $matches)) {
        return ['success' => false, 'message' => 'Неверный формат временного слота'];
    }

    $startTime = $matches[1];
    $endTime = $matches[2];
    
    // Проверяем, что временной слот валидный (не более 2 часов)
    $start = DateTime::createFromFormat('H:i', $startTime);
    $end = DateTime::createFromFormat('H:i', $endTime);
    $interval = $start->diff($end);
    
    if ($interval->h > 2 || ($interval->h == 2 && $interval->i > 0)) {
        return ['success' => false, 'message' => 'Максимальный промежуток времени - 2 часа'];
    }

    // Проверяем, что время в рамках работы приюта
    $dayOfWeek = $selectedDate->format('N'); // 1-пн, 7-вс
    $stmt = $pdo->prepare("
        SELECT open_time, close_time 
        FROM shelter_schedule 
        WHERE day_of_week = ? 
        AND is_working_day = 1
    ");
    $stmt->execute([$dayOfWeek]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        return ['success' => false, 'message' => 'Приют не работает в выбранную дату'];
    }

    $openTime = new DateTime($schedule['open_time']);
    $closeTime = new DateTime($schedule['close_time']);
    
    if ($start < $openTime || $end > $closeTime) {
        return ['success' => false, 'message' => 'Выбранное время должно быть в рамках работы приюта'];
    }

    // Проверяем доступность временного слота
    $startDateTime = "$date $startTime:00";
    $endDateTime = "$date $endTime:00";
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE animal_id = ? 
        AND type = 'adoption' 
        AND status IN ('created', 'active')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([$animalId, $endDateTime, $startDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime]);
    
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Выбранное время уже занято. Пожалуйста, выберите другое время.'];
    }

    try {
        $pdo->beginTransaction();
        
        // Создаем заявку
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (user_id, animal_id, type, start_time, end_time, phone, comment, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'created', NOW())
        ");
        $stmt->execute([
            $userId, 
            $animalId, 
            'adoption', 
            $startDateTime, 
            $endDateTime,
            $phone,
            $comment
        ]);
          
        $pdo->commit();
        return ['success' => true, 'message' => 'Заявка на усыновление успешно создана'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Ошибка при создании заявки: ' . $e->getMessage()];
    }
}

function isTimeSlotAvailableForAnimal($pdo, $animalId, $startTime, $endTime) {
    // Проверяем, есть ли активные заявки на усыновление для этого животного
    $stmt = $pdo->prepare("
        SELECT start_time 
        FROM applications 
        WHERE animal_id = ? 
        AND type = 'adoption'
        AND status IN ('created', 'active')
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$animalId]);
    $earliestAdoption = $stmt->fetchColumn();
    
    // Если есть заявка на усыновление и наш слот начинается после нее - блокируем
    if ($earliestAdoption && strtotime($startTime) >= strtotime($earliestAdoption)) {
        return false;
    }

    // Проверяем пересечение с другими резервированиями
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE animal_id = ? 
        AND type = 'reservation'
        AND status IN ('created', 'active')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([$animalId, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
    
    return $stmt->fetchColumn() == 0;
}

function isTimeSlotAvailableForAdoption($pdo, $startTime, $endTime) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE type = 'adoption'
        AND status IN ('created', 'active')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([$endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
    
    return $stmt->fetchColumn() < 2;
}

function isTimeSlotAvailableForReservation($pdo, $startTime, $endTime) {
    // Для reservation проверяем что не больше 5 заявок
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM applications 
        WHERE type = 'reservation'
        AND status IN ('created', 'active')
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([$endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
    
    return $stmt->fetchColumn() < 5;
}
function getAvailableTimeSlots($pdo, $animalId, $date, $type, $duration = 1) {
    // Проверяем, есть ли активные заявки на усыновление для этого животного
    $stmt = $pdo->prepare("
        SELECT start_time 
        FROM applications 
        WHERE animal_id = ? 
        AND type = 'adoption'
        AND status IN ('created', 'active')
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$animalId]);
    $earliestAdoption = $stmt->fetchColumn();
    
    // Если есть заявка на усыновление и выбранная дата после нее - слотов нет
    if ($earliestAdoption && strtotime($date) >= strtotime($earliestAdoption)) {
        return [];
    }

    // Получаем часы работы для этого дня
    $dayOfWeek = date('N', strtotime($date));
    $stmt = $pdo->prepare("SELECT * FROM shelter_schedule WHERE day_of_week = ? AND is_working_day = 1");
    $stmt->execute([$dayOfWeek]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        return [];
    }

    $openTime = new DateTime($schedule['open_time']);
    $closeTime = new DateTime($schedule['close_time']);
    $currentTime = clone $openTime;
    $availableTimes = [];

    while ($currentTime < $closeTime) {
        $timeStr = $currentTime->format('H:i:s');
        $endTime = (clone $currentTime)->add(new DateInterval('PT' . $duration . 'H'));
        $endTimeStr = $endTime->format('H:i:s');
        
        // Проверяем что не выходим за время закрытия
        if ($endTime > $closeTime) {
            $currentTime->add(new DateInterval('PT1H'));
            continue;
        }
        
        $startDateTime = $date . ' ' . $timeStr;
        $endDateTime = $date . ' ' . $endTimeStr;
        
        if ($type === 'adoption') {
            $slotAvailable = isTimeSlotAvailableForAdoption($pdo, $startDateTime, $endDateTime);
        } else {
            $slotAvailable = isTimeSlotAvailableForReservation($pdo, $startDateTime, $endDateTime);
        }
        
        $animalAvailable = isTimeSlotAvailableForAnimal($pdo, $animalId, $startDateTime, $endDateTime);
        
        if ($slotAvailable && $animalAvailable) {
            $availableTimes[] = $currentTime->format('H:i');
        }
        
        $currentTime->add(new DateInterval('PT1H'));
    }

    return $availableTimes;
}
function getRussianWeekDay($dayNumber) {
    $days = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
        7 => 'Воскресенье'
    ];
    return $days[$dayNumber] ?? '';
}

function getUserTypeText($type) {
    switch ($type) {
        case 'admin': return 'Администратор';
        case 'staff': return 'Сотрудник';
        case 'user': return 'Пользователь';
        default: return $type;
    }
}
function shortenDescription($text, $maxLength = 100) {
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    
    // Обрезаем текст до последнего пробела перед maxLength
    $shortened = mb_substr($text, 0, $maxLength);
    $lastSpace = mb_strrpos($shortened, ' ');
    
    if ($lastSpace !== false) {
        $shortened = mb_substr($shortened, 0, $lastSpace);
    }
    
    return $shortened . '...';
}
?>