<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['username'])) {
    echo json_encode(['exists' => false]);
    exit;
}

$username = trim($_GET['username']);
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

echo json_encode(['exists' => $user !== false]);
?>