<?php
// admin_sidebar.php
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    redirect('dashboard.php');
}
?>

<div class="admin-sidebar">
    <div class="sidebar-header">
        <h3>Админ-панель</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Панель управления
            </a>
        </li>
        
        <?php if ($_SESSION['user_type'] == 'admin'): ?>
        <li>
            <a href="admin_users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Управление пользователями
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="admin_animals.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_animals.php' ? 'active' : '' ?>">
                <i class="fas fa-paw"></i> Управление животными
            </a>
        </li>
        
        <li>
            <a href="admin_applications.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_applications.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i> Управление заявками
            </a>
        </li>

    </ul>
</div>