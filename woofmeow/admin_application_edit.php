<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check permissions
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}

$isEditMode = isset($_GET['id']);
$applicationId = $isEditMode ? (int)$_GET['id'] : 0;

// Get application data (if editing)
if ($isEditMode) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               u.username as user_name, 
               u.first_name as user_first_name, 
               u.last_name as user_last_name,
               u.email as user_email,
               u.phone as user_phone,
               an.name as animal_name,
               an.image_path as animal_image,
               an.type as animal_type
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN animals an ON a.animal_id = an.id
        WHERE a.id = ?
    ");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    
    if (!$application) {
        $_SESSION['error_message'] = "Заявка не найдена";
        redirect('admin_applications.php');
    }
}

// Get lists for dropdowns
$users = $pdo->query("SELECT id, username, first_name, last_name, email, phone FROM users ORDER BY first_name, last_name")->fetchAll();
$animals = $pdo->query("SELECT id, name, type FROM animals ORDER BY name")->fetchAll();

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = $_POST['user_type']; // 'existing' or 'new'
    $animalId = (int)$_POST['animal_id'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    $date = $_POST['date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $phone = trim($_POST['phone'] ?? '');
    $userComment = trim($_POST['user_comment'] ?? '');
    $staffComment = trim($_POST['staff_comment'] ?? '');
    
    $startDateTime = $date . ' ' . $startTime . ':00';
    $endDateTime = $date . ' ' . $endTime . ':00';
    
    $errors = [];
    
    if (empty($animalId)) $errors[] = "Не выбрано животное";
    if (empty($date)) $errors[] = "Не указана дата";
    if (empty($startTime) || empty($endTime)) $errors[] = "Не указано время";
    if (strtotime($startTime) >= strtotime($endTime)) $errors[] = "Время окончания должно быть позже времени начала";
    if ($status === 'canceled' && empty($staffComment)) $errors[] = "При отмене заявки необходимо указать причину";
    
    if ($userType === 'existing') {
        $userId = (int)$_POST['user_id'];
        if (empty($userId)) $errors[] = "Не выбран пользователь";
    } else {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($firstName) || empty($lastName)) $errors[] = "Не указано имя или фамилия пользователя";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Неверный формат email";
        if (empty($username)) $errors[] = "Не указан логин пользователя";
        if (empty($password) || strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = "Пароль должен содержать минимум 10 символов, включая хотя бы одну букву и одну цифру";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create new user if needed
            if ($userType === 'new') {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch();
                
                if ($existingUser) {
                    $userId = $existingUser['id'];
                } else {
                    // Create new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users 
                        (username, first_name, last_name, email, password, phone, user_type, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'user', NOW())
                    ");
                    $stmt->execute([
                        $username,
                        $firstName,
                        $lastName,
                        $email,
                        $hashedPassword,
                        $phone
                    ]);
                    $userId = $pdo->lastInsertId();
                }
            } else {
                // Update phone for existing user
                $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$phone, $userId]);
            }
            
            if ($isEditMode) {
                // Update existing application
                $stmt = $pdo->prepare("
                    UPDATE applications 
                    SET user_id = ?,
                        animal_id = ?,
                        type = ?,
                        status = ?,
                        start_time = ?,
                        end_time = ?,
                        phone = ?,
                        comment = ?,
                        visit_comments = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $userId,
                    $animalId,
                    $type,
                    $status,
                    $startDateTime,
                    $endDateTime,
                    $phone,
                    $userComment,
                    $staffComment,
                    $applicationId
                ]);
                
                // If status changed to canceled or completed - add to history
                if (($status === 'canceled' || $status === 'completed') && $status !== $application['status']) {
                    $action = $type === 'reservation' ? 'visit' : $type;
                    $details = ($type === 'adoption' ? 'Усыновление' : 'Резервирование');
                    
                    if ($status === 'canceled') {
                        $details .= " Отменено";
                        $details .= " " . date('d.m.Y H:i', strtotime($startDateTime));
                        $details .= " - " . date('H:i', strtotime($endDateTime));
                        $details .= " (Отменено сотрудником)";
                    } else {
                        $details .= " Завершено";
                        $details .= " " . date('d.m.Y H:i', strtotime($startDateTime));
                        $details .= " - " . date('H:i', strtotime($endDateTime));
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO history 
                        (user_id, animal_id, application_id, action, status, date, details, visit_comments) 
                        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $animalId,
                        $applicationId,
                        $action,
                        $status,
                        $details,
                        $staffComment
                    ]);
                    
                    // If adoption was canceled - update animal status
                    if ($type === 'adoption' && $status === 'canceled') {
                        $stmt = $pdo->prepare("
                            UPDATE animals 
                            SET status = 'available' 
                            WHERE id = ?
                        ");
                        $stmt->execute([$animalId]);
                    }
                }
                
                $_SESSION['success_message'] = "Заявка успешно обновлена";
            } else {
                // Create new application
                $stmt = $pdo->prepare("
                    INSERT INTO applications 
                    (user_id, animal_id, type, status, start_time, end_time, phone, comment, visit_comments, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    $animalId,
                    $type,
                    $status,
                    $startDateTime,
                    $endDateTime,
                    $phone,
                    $userComment,
                    $staffComment
                ]);
                $applicationId = $pdo->lastInsertId();
                
                $_SESSION['success_message'] = "Заявка успешно создана";
            }
            
            $pdo->commit();
            redirect('admin_applications.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Ошибка при сохранении заявки: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

$pageTitle = ($isEditMode ? "Редактирование" : "Создание") . " заявки | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<style>
    .hide {
        display: none;
    }
    .show {
        display: block;
    }
</style>
<div class="admin-container admin-application-edit">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <h1><?= $isEditMode ? 'Редактирование заявки' : 'Создание новой заявки' ?></h1>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <form method="post" id="applicationForm">
                <!-- User type selector (only for new applications) -->
                <?php if (!$isEditMode): ?>
                <div class="user-type-selector">
                    <label>
                        <input type="radio" name="user_type" value="existing" <?= (!isset($_POST['user_type']) || $_POST['user_type'] === 'existing') ? 'checked' : '' ?> onchange="toggleUserFields()">
                        Существующий пользователь
                    </label>
                    <label>
                        <input type="radio" name="user_type" value="new" <?= isset($_POST['user_type']) && $_POST['user_type'] === 'new' ? 'checked' : '' ?> onchange="toggleUserFields()">
                        Новый пользователь
                    </label>
                </div>
                <?php else: ?>
                    <input type="hidden" name="user_type" value="existing">
                <?php endif; ?>
                
                <!-- Existing user fields -->
                <div id="existingUserFields" class="<?= ($isEditMode || !isset($_POST['user_type']) || $_POST['user_type'] === 'existing') ? 'show' : 'hide' ?>">
                    <div class="form-group">
                        <label for="user_id">Пользователь:</label>
                        <select name="user_id" id="user_id" class="form-control" onchange="fillUserData()" <?= $isEditMode ? 'disabled' : '' ?>>
                            <option value="">Выберите пользователя</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                    data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                                    data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                    data-phone="<?= htmlspecialchars($user['phone']) ?>"
                                    <?= ($isEditMode && $application['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="user_id" value="<?= $application['user_id'] ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Имя:</label>
                            <input type="text" class="form-control" id="existing_first_name" value="<?= $isEditMode ? htmlspecialchars($application['user_first_name']) : '' ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Фамилия:</label>
                            <input type="text" class="form-control" id="existing_last_name" value="<?= $isEditMode ? htmlspecialchars($application['user_last_name']) : '' ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="text" class="form-control" id="existing_email" value="<?= $isEditMode ? htmlspecialchars($application['user_email']) : '' ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- New user fields (only for new applications) -->
                <?php if (!$isEditMode): ?>
                <div id="newUserFields" class="<?= isset($_POST['user_type']) && $_POST['user_type'] === 'new' ? 'show' : 'hide' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Имя:</label>
                            <input type="text" name="first_name" id="first_name" class="form-control"
                                   value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Фамилия:</label>
                            <input type="text" name="last_name" id="last_name" class="form-control"
                                   value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Логин:</label>
                            <div class="input-group">
                                <input type="text" name="username" id="username" class="form-control"
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                                <button type="button" class="btn btn-outline" onclick="generateUsername()">Сгенерировать</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Пароль:</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control"
                                       value="<?= isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '' ?>">
                                <button type="button" class="btn btn-outline" onclick="generatePassword()">Сгенерировать</button>
                                <button type="button" class="btn btn-outline" onclick="togglePasswordVisibility()">Показать</button>
                            </div>
                            <small class="text-muted">Минимум 10 символов, включая буквы и цифры</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="phone">Телефон:</label>
                    <input type="tel" name="phone" id="phone" class="form-control"
                           value="<?= $isEditMode ? htmlspecialchars($application['phone'] ?? $application['user_phone']) : (isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="animal_id">Животное:</label>
                        <select name="animal_id" id="animal_id" class="form-control" required>
                            <option value="">Выберите животное</option>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?= $animal['id'] ?>" 
                                    <?= ($isEditMode && $application['animal_id'] == $animal['id']) || (isset($_POST['animal_id']) && $_POST['animal_id'] == $animal['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($animal['name'] . ' (' . getAnimalType($animal['type']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Тип заявки:</label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="reservation" <?= ($isEditMode && $application['type'] == 'reservation') || (isset($_POST['type']) && $_POST['type'] == 'reservation') ? 'selected' : '' ?>>Резервирование</option>
                            <option value="adoption" <?= ($isEditMode && $application['type'] == 'adoption') || (isset($_POST['type']) && $_POST['type'] == 'adoption') ? 'selected' : '' ?>>Усыновление</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Статус:</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="created" <?= ($isEditMode && $application['status'] == 'created') || (isset($_POST['status']) && $_POST['status'] == 'created') ? 'selected' : '' ?>>Создана</option>
                            <option value="active" <?= ($isEditMode && $application['status'] == 'active') || (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>Активна</option>
                            <option value="completed" <?= ($isEditMode && $application['status'] == 'completed') || (isset($_POST['status']) && $_POST['status'] == 'completed') ? 'selected' : '' ?>>Завершена</option>
                            <option value="canceled" <?= ($isEditMode && $application['status'] == 'canceled') || (isset($_POST['status']) && $_POST['status'] == 'canceled') ? 'selected' : '' ?>>Отменена</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Дата:</label>
                        <input type="date" name="date" id="date" class="form-control" required
                               value="<?= $isEditMode ? date('Y-m-d', strtotime($application['start_time'])) : (isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d')) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Время начала:</label>
                        <input type="time" name="start_time" id="start_time" class="form-control" required
                               value="<?= $isEditMode ? date('H:i', strtotime($application['start_time'])) : (isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : '10:00') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">Время окончания:</label>
                        <input type="time" name="end_time" id="end_time" class="form-control" required
                               value="<?= $isEditMode ? date('H:i', strtotime($application['end_time'])) : (isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : '11:00') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="user_comment">Комментарий пользователя:</label>
                    <textarea name="user_comment" id="user_comment" class="form-control" rows="3"><?= $isEditMode ? htmlspecialchars($application['comment']) : (isset($_POST['user_comment']) ? htmlspecialchars($_POST['user_comment']) : '' )?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="staff_comment">Комментарий сотрудника:</label>
                    <textarea name="staff_comment" id="staff_comment" class="form-control" rows="3"><?= $isEditMode ? htmlspecialchars($application['visit_comments']) : (isset($_POST['staff_comment']) ? htmlspecialchars($_POST['staff_comment']) : '') ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="admin_applications.php" class="btn btn-outline">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleUserFields() {
    const userType = document.querySelector('input[name="user_type"]:checked').value;
    
    if (userType === 'existing') {
        document.getElementById('existingUserFields').classList.remove('hide');
        document.getElementById('existingUserFields').classList.add('show');
        document.getElementById('newUserFields').classList.remove('show');
        document.getElementById('newUserFields').classList.add('hide');
    } else {
        document.getElementById('existingUserFields').classList.remove('show');
        document.getElementById('existingUserFields').classList.add('hide');
        document.getElementById('newUserFields').classList.remove('hide');
        document.getElementById('newUserFields').classList.add('show');
    }
    
    // Clear fields when switching
    if (userType === 'existing') {
        document.getElementById('first_name').value = '';
        document.getElementById('last_name').value = '';
        document.getElementById('email').value = '';
        document.getElementById('username').value = '';
        document.getElementById('password').value = '';
    } else {
        document.getElementById('user_id').value = '';
        fillUserData();
    }
}

function fillUserData() {
    const select = document.getElementById('user_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        document.getElementById('existing_first_name').value = selectedOption.getAttribute('data-first-name') || '';
        document.getElementById('existing_last_name').value = selectedOption.getAttribute('data-last-name') || '';
        document.getElementById('existing_email').value = selectedOption.getAttribute('data-email') || '';
        document.getElementById('phone').value = selectedOption.getAttribute('data-phone') || '';
    } else {
        document.getElementById('existing_first_name').value = '';
        document.getElementById('existing_last_name').value = '';
        document.getElementById('existing_email').value = '';
        document.getElementById('phone').value = '';
    }
}

function generateUsername() {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    
    if (!firstName && !lastName) {
        alert('Пожалуйста, укажите имя и фамилию пользователя');
        return;
    }
    
    // Generate base username
    let baseUsername = (firstName.charAt(0) + lastName).toLowerCase();
    baseUsername = baseUsername.replace(/[^a-z0-9]/g, '');
    
    if (baseUsername.length === 0) {
        baseUsername = 'user' + Math.floor(1000 + Math.random() * 9000);
    }
    
    // Check if username exists and add random numbers if needed
    checkUsernameExists(baseUsername).then(exists => {
        let username = baseUsername;
        if (exists) {
            username = baseUsername + Math.floor(100 + Math.random() * 900);
            // Check again if the new username exists
            checkUsernameExists(username).then(newExists => {
                if (newExists) {
                    username = baseUsername + Math.floor(1000 + Math.random() * 9000);
                }
                document.getElementById('username').value = username;
            });
        } else {
            document.getElementById('username').value = username;
        }
    });
}

function checkUsernameExists(username) {
    return fetch('check_username.php?username=' + encodeURIComponent(username))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => data.exists)
        .catch(error => {
            console.error('Error checking username:', error);
            return false;
        });
}

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    let password = '';
    
    // Ensure at least one letter and one number
    password += chars.charAt(Math.floor(Math.random() * 52)); // letter
    password += chars.charAt(52 + Math.floor(Math.random() * 10)); // number
    
    // Add remaining characters
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    // Shuffle the password
    password = password.split('').sort(() => 0.5 - Math.random()).join('');
    
    document.getElementById('password').value = password;
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
    } else {
        passwordInput.type = 'password';
    }
}

// Form validation
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    const status = document.getElementById('status').value;
    const staffComment = document.getElementById('staff_comment').value;
    
    if (status === 'canceled' && staffComment.trim() === '') {
        e.preventDefault();
        alert('Пожалуйста, укажите причину отмены заявки');
    }
    
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    
    if (startTime >= endTime) {
        e.preventDefault();
        alert('Время окончания должно быть позже времени начала');
    }
    
    const userType = document.querySelector('input[name="user_type"]:checked')?.value || 'existing';
    
    if (userType === 'new') {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        if (!firstName || !lastName) {
            e.preventDefault();
            alert('Пожалуйста, укажите имя и фамилию пользователя');
            return;
        }
        
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            e.preventDefault();
            alert('Пожалуйста, укажите корректный email');
            return;
        }
        
        if (!username) {
            e.preventDefault();
            alert('Пожалуйста, укажите логин пользователя');
            return;
        }
        
        if (!password || password.length < 10 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
            e.preventDefault();
            alert('Пароль должен содержать минимум 10 символов, включая хотя бы одну букву и одну цифру');
        }
    } else {
        const userId = document.getElementById('user_id').value;
        if (!userId) {
            e.preventDefault();
            alert('Пожалуйста, выберите пользователя');
        }
    }
});

// Initialize fields for edit mode
<?php if ($isEditMode): ?>
document.addEventListener('DOMContentLoaded', function() {
    fillUserData();
});
<?php endif; ?>
</script>

<?php
require_once 'includes/footer.php';
?>