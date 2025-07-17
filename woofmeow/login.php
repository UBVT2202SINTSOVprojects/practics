<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        redirect('dashboard.php');
    } else {
        $errors[] = "Неверное имя пользователя или пароль";
    }
}

$pageTitle = "Вход | " . SITE_NAME;
require_once 'includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <h2>Вход в аккаунт</h2>
        <p>Пожалуйста, авторизуйтесь</p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success">
            <?php echo $_SESSION['success_message']; ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="login.php">
        <div class="form-group">
            <label for="username" class="form-label">Имя пользователя</label>
            <input type="text" id="username" name="username" 
                   class="form-control" 
                   placeholder="Введите ваш логин">
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" 
                       class="form-control" 
                       placeholder="Введите ваш пароль">
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', this)">👁️</button>
            </div>
        </div>
        
        <button type="submit" class="btn btn-block">Войти</button>
    </form>
    
    <div class="auth-footer">
        Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>