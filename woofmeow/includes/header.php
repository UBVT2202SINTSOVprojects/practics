<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/logo.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="page-wrapper">
        <header class="main-header">
            <div class="container">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="logo"><a href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a></h1>
                        <nav class="main-nav">
                            <a href="<?php echo SITE_URL; ?>">Главная</a>
                            <a href="catalog.php">Каталог</a>
                            <a href="help.php">Помощь приюту</a>
                            <a href="contacts.php">Контакты</a>
                        </nav>
                    </div>
                    <div class="header-right">
                        <?php if (is_logged_in()): ?>
                            <?php if (in_array($_SESSION['user_type'], ['admin', 'staff'])): ?>
                                <a href="admin_dashboard.php" class="user-menu"><i class="fas fa-user-shield"></i> Админ-панель</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="user-menu"><i class="fas fa-user-circle"></i> Личный кабинет</a>
                            <?php endif; ?>
                            <a href="logout.php" class="user-menu"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                        <?php else: ?>
                            <a href="login.php" class="user-menu"><i class="fas fa-sign-in-alt"></i> Вход</a>
                            <a href="register.php" class="user-menu"><i class="fas fa-user-plus"></i> Регистрация</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        <main class="main-content">
            <div class="container">