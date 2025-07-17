<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверка прав доступа - только для администраторов
if (!is_logged_in() || $_SESSION['user_type'] !== 'admin') {
    redirect('dashboard.php');
}

$isEditMode = isset($_GET['id']);
$userId = $isEditMode ? (int)$_GET['id'] : 0;

// Get user data (if editing)
if ($isEditMode) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = "Пользователь не найден";
        redirect('admin_users.php');
    }
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $userType = $_POST['user_type'];
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    $errors = [];
    
    if (empty($username)) $errors[] = "Не указан логин пользователя";
    if (empty($firstName)) $errors[] = "Не указано имя пользователя";
    if (empty($lastName)) $errors[] = "Не указана фамилия пользователя";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Неверный формат email";
    if (!in_array($userType, ['admin', 'staff', 'user'])) $errors[] = "Неверный тип пользователя";
    
    // Password validation only for new users or when changing password
    if (!$isEditMode) {
        // Для нового пользователя пароль обязателен
        if (empty($password)) {
            $errors[] = "Пароль обязателен для нового пользователя";
        } elseif (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = "Пароль должен содержать минимум 10 символов, включая хотя бы одну букву и одну цифру";
        }
        if ($password !== $confirmPassword) {
            $errors[] = "Пароли не совпадают";
        }
    } else {
        // Для редактирования - проверяем пароль только если он указан
        if (!empty($password)) {
            if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $errors[] = "Пароль должен содержать минимум 10 символов, включая хотя бы одну букву и одну цифру";
            }
            if ($password !== $confirmPassword) {
                $errors[] = "Пароли не совпадают";
            }
        }
    }
    
    // Check if username already exists (for new users or when changing username)
    if (!$isEditMode || ($isEditMode && $username !== $user['username'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким логином уже существует";
        }
    }
    
    // Check if email already exists (for new users or when changing email)
    if (!$isEditMode || ($isEditMode && $email !== $user['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким email уже существует";
        }
    }
    
    if (empty($errors)) {
        try {
            if ($isEditMode) {
                // Update existing user
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?,
                            first_name = ?,
                            last_name = ?,
                            email = ?,
                            phone = ?,
                            user_type = ?,
                            password = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $username,
                        $firstName,
                        $lastName,
                        $email,
                        $phone,
                        $userType,
                        $hashedPassword,
                        $userId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?,
                            first_name = ?,
                            last_name = ?,
                            email = ?,
                            phone = ?,
                            user_type = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $username,
                        $firstName,
                        $lastName,
                        $email,
                        $phone,
                        $userType,
                        $userId
                    ]);
                }
                
                $_SESSION['success_message'] = "Данные пользователя успешно обновлены";
            } else {
                // Create new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users 
                    (username, first_name, last_name, email, phone, user_type, password, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $username,
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $userType,
                    $hashedPassword
                ]);
                $userId = $pdo->lastInsertId();
                
                $_SESSION['success_message'] = "Пользователь успешно добавлен";
            }
            
            redirect('admin_users.php');
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Ошибка при сохранении данных: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

$pageTitle = ($isEditMode ? "Редактирование" : "Добавление") . " пользователя | " . SITE_NAME;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
<div class="admin-container admin-user-edit">
    <div class="admin-grid">
        <?php require_once 'admin_sidebar.php'; ?>
        
        <div class="admin-content">
            <h1><?= $isEditMode ? 'Редактирование пользователя' : 'Добавление нового пользователя' ?></h1>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <form method="post" id="userForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Логин:</label>
                        <input type="text" name="username" id="username" class="form-control" required
                               value="<?= $isEditMode ? htmlspecialchars($user['username']) : (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_type">Тип пользователя:</label>
                        <select name="user_type" id="user_type" class="form-control" required>
                            <option value="admin" <?= ($isEditMode && $user['user_type'] == 'admin') || (isset($_POST['user_type']) && $_POST['user_type'] == 'admin' ? 'selected' : '') ?>>Администратор</option>
                            <option value="staff" <?= ($isEditMode && $user['user_type'] == 'staff') || (isset($_POST['user_type']) && $_POST['user_type'] == 'staff' ? 'selected' : '') ?>>Сотрудник</option>
                            <option value="user" <?= ($isEditMode && $user['user_type'] == 'user') || (isset($_POST['user_type']) && $_POST['user_type'] == 'user' ? 'selected' : '') ?>>Пользователь</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Имя:</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required
                               value="<?= $isEditMode ? htmlspecialchars($user['first_name']) : (isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' )?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Фамилия:</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required
                               value="<?= $isEditMode ? htmlspecialchars($user['last_name']) : (isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' )?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" class="form-control" required
                               value="<?= $isEditMode ? htmlspecialchars($user['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' )?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Телефон:</label>
                        <input type="tel" name="phone" id="phone" class="form-control"
                               value="<?= $isEditMode ? htmlspecialchars($user['phone']) : (isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' )?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password"><?= $isEditMode ? 'Новый пароль (оставьте пустым, чтобы не изменять):' : 'Пароль:' ?></label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" <?= !$isEditMode ? 'required' : '' ?>>
                            <button type="button" class="btn btn-outline" onclick="generatePassword()">Сгенерировать</button>
                            <button type="button" class="btn btn-outline" onclick="togglePasswordVisibility()">Показать</button>
                        </div>
                        <small class="text-muted">Минимум 10 символов, включая буквы и цифры</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Подтверждение пароля:</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" <?= !$isEditMode ? 'required' : '' ?>>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="admin_users.php" class="btn btn-outline">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
    document.getElementById('confirm_password').value = password;
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        confirmInput.type = 'text';
    } else {
        passwordInput.type = 'password';
        confirmInput.type = 'password';
    }
}

// Form validation
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
    
    if (!isEditMode) {
        // For new user - password is required
        if (password.length === 0) {
            e.preventDefault();
            alert('Пароль обязателен для нового пользователя');
            return;
        }
    }
    
    // Only validate password if it's provided (for edit mode) or required (for new user)
    if ((isEditMode && password.length > 0) || !isEditMode) {
        if (password.length < 10 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
            e.preventDefault();
            alert('Пароль должен содержать минимум 10 символов, включая хотя бы одну букву и одну цифру');
            return;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Пароли не совпадают');
            return;
        }
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>