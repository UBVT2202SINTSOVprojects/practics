<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$errors = [];
$formData = [
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim($_POST['username']);
    $formData['first_name'] = trim($_POST['first_name']);
    $formData['last_name'] = trim($_POST['last_name']);
    $formData['email'] = trim($_POST['email']);
    $formData['phone'] = trim($_POST['phone']); // Добавляем телефон
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    // Валидация имени пользователя
    if (empty($formData['username'])) {
        $errors['username'] = "Имя пользователя обязательно для заполнения";
    } elseif (strlen($formData['username']) < 4) {
        $errors['username'] = "Имя пользователя должно содержать минимум 4 символа";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = "Это имя пользователя уже занято";
        }
    }
    
    // Валидация имени
    if (empty($formData['first_name'])) {
        $errors['first_name'] = "Имя обязательно для заполнения";
    } elseif (strlen($formData['first_name']) < 2) {
        $errors['first_name'] = "Имя должно содержать минимум 2 символа";
    }
    
    // Валидация фамилии
    if (empty($formData['last_name'])) {
        $errors['last_name'] = "Фамилия обязательна для заполнения";
    } elseif (strlen($formData['last_name']) < 2) {
        $errors['last_name'] = "Фамилия должна содержать минимум 2 символа";
    }
    
    // Валидация email
    if (empty($formData['email'])) {
        $errors['email'] = "Email обязателен для заполнения";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Некорректный формат email";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = "Этот email уже используется";
        }
    }
    
    // Валидация телефона (не обязательное поле)
    if (!empty($formData['phone'])) {
        // Удаляем все нецифровые символы
        $cleanedPhone = preg_replace('/[^0-9]/', '', $formData['phone']);
        
        // Проверяем минимальную длину (например, 10 цифр)
        if (strlen($cleanedPhone) < 10) {
            $errors['phone'] = "Номер телефона должен содержать не менее 10 цифр";
        } else {
            // Форматируем номер для хранения в базе
            $formData['phone'] = $cleanedPhone;
        }
    }
    
    // Валидация пароля
    if (empty($password)) {
        $errors['password'] = "Пароль обязателен для заполнения";
    } else {
        if (strlen($password) < 10) {
            $errors['password'] = "Пароль должен содержать не менее 10 символов";
        } elseif (!preg_match('/[A-Za-z]/', $password)) {
            $errors['password'] = "Пароль должен содержать хотя бы одну букву";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = "Пароль должен содержать хотя бы одну цифру";
        } elseif ($password !== $password_confirm) {
            $errors['password_confirm'] = "Пароли не совпадают";
        }
    }
    
    // Если ошибок нет - регистрируем пользователя
    if (empty($errors)) {
        if (register_user($formData['username'], $formData['first_name'], $formData['last_name'], $formData['email'], $password, 'user', $pdo, $formData['phone'])) {
            $_SESSION['success_message'] = "Регистрация прошла успешно! Теперь вы можете войти.";
            redirect('login.php');
        } else {
            $errors[] = "Произошла ошибка при регистрации. Пожалуйста, попробуйте позже.";
        }
    }
}

$pageTitle = "Регистрация | " . SITE_NAME;
require_once 'includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <h2>Создать аккаунт</h2>
        <p>Присоединяйтесь к нашему сообществу</p>
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
    
    <form method="post" action="register.php" id="registerForm">
        <div class="form-group">
            <label for="username" class="form-label">Имя пользователя</label>
            <input type="text" id="username" name="username" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($formData['username']); ?>"
                   placeholder="Придумайте логин">
            <?php if (isset($errors['username'])): ?>
                <div class="error-message"><?php echo $errors['username']; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="first_name" class="form-label">Имя</label>
                <input type="text" id="first_name" name="first_name" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($formData['first_name']); ?>"
                       placeholder="Ваше имя">
                <?php if (isset($errors['first_name'])): ?>
                    <div class="error-message"><?php echo $errors['first_name']; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="last_name" class="form-label">Фамилия</label>
                <input type="text" id="last_name" name="last_name" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($formData['last_name']); ?>"
                       placeholder="Ваша фамилия">
                <?php if (isset($errors['last_name'])): ?>
                    <div class="error-message"><?php echo $errors['last_name']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($formData['email']); ?>"
                   placeholder="Ваш email">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?php echo $errors['email']; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="phone" class="form-label">Телефон (необязательно)</label>
            <input type="tel" id="phone" name="phone" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($formData['phone']); ?>"
                   placeholder="+7 (XXX) XXX-XX-XX">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?php echo $errors['phone']; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" 
                       class="form-control" 
                       placeholder="Не менее 10 символов, буквы и цифры">
                <button type="button" class="toggle-password" data-target="password">👁️</button>
            </div>
            <?php if (isset($errors['password'])): ?>
                <div class="error-message"><?php echo $errors['password']; ?></div>
            <?php endif; ?>
            <div class="password-strength">
                <div class="strength-meter">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-feedback">
                    <span class="feedback-text">Надёжность пароля:</span>
                    <span class="strength-text" id="strengthText">Ненадёжный</span>
                </div>
            </div>
            <div class="requirements-list">
                <div class="requirement" id="lengthReq">
                    <span class="icon">✖</span> Не менее 10 символов
                </div>
                <div class="requirement" id="letterReq">
                    <span class="icon">✖</span> Хотя бы одна буква (англ. или рус.)
                </div>
                <div class="requirement" id="numberReq">
                    <span class="icon">✖</span> Хотя бы одна цифра
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="password_confirm" class="form-label">Подтвердите пароль</label>
            <div class="password-wrapper">
                <input type="password" id="password_confirm" name="password_confirm" 
                       class="form-control" 
                       placeholder="Повторите ваш пароль">
                <button type="button" class="toggle-password" data-target="password_confirm">👁️</button>
            </div>
            <?php if (isset($errors['password_confirm'])): ?>
                <div class="error-message"><?php echo $errors['password_confirm']; ?></div>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-block">Зарегистрироваться</button>
    </form>
    
    <div class="auth-footer">
        Уже есть аккаунт? <a href="login.php">Войти</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Показать/скрыть пароль
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? '🙈' : '👁️';
        });
    });
    
    // Проверка сложности пароля в реальном времени
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const lengthReq = document.getElementById('lengthReq');
    const letterReq = document.getElementById('letterReq');
    const numberReq = document.getElementById('numberReq');
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Проверка длины
        const hasLength = password.length >= 10;
        updateRequirement(lengthReq, hasLength);
        if (hasLength) strength += 1;
        
        // Проверка букв (теперь и русские и английские)
        const hasLetter = /[A-Za-zА-Яа-я]/.test(password);
        updateRequirement(letterReq, hasLetter);
        if (hasLetter) strength += 1;
        
        // Проверка цифр
        const hasNumber = /[0-9]/.test(password);
        updateRequirement(numberReq, hasNumber);
        if (hasNumber) strength += 1;
        
        // Обновление индикатора силы
        updateStrengthIndicator(strength);
    });
    
    // Маска для телефона
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            const x = this.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
            this.value = !x[2] ? x[1] : '+' + x[1] + ' (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? '-' + x[4] : '') + (x[5] ? '-' + x[5] : '');
        });
    }
    
    function updateRequirement(element, isValid) {
        const icon = element.querySelector('.icon');
        if (isValid) {
            element.classList.add('valid');
            icon.textContent = '✔';
            icon.style.color = '#1cc88a';
        } else {
            element.classList.remove('valid');
            icon.textContent = '✖';
            icon.style.color = '#e74a3b';
        }
    }
    
    function updateStrengthIndicator(strength) {
        let percent = 0;
        let text = '';
        let color = '';
        
        switch(strength) {
            case 0:
                percent = 0;
                text = 'Ненадёжный';
                color = '#e74a3b';
                break;
            case 1:
                percent = 33;
                text = 'Слабый';
                color = '#f6c23e';
                break;
            case 2:
                percent = 66;
                text = 'Средний';
                color = '#4e73df';
                break;
            case 3:
                percent = 100;
                text = 'Надёжный';
                color = '#1cc88a';
                break;
        }
        
        strengthBar.style.width = percent + '%';
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>