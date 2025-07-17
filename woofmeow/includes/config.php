<?php
session_start();

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'animalshelter');

// Настройки сайта
define('SITE_NAME', 'WoofMeow');
define('SITE_URL', 'http://woofmeow');

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET time_zone = '+03:00'"); // Установите ваш часовой пояс
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

require_once 'functions.php';

// Обновляем статусы заявок при каждом обращении к сайту
if (!defined('NO_STATUS_UPDATE')) {
    updateApplicationStatuses($pdo);
}