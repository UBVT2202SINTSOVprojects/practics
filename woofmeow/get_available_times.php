<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['animal_id']) || !isset($_GET['date']) || !isset($_GET['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$animalId = (int)$_GET['animal_id'];
$date = $_GET['date'];
$type = $_GET['type'];
$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 1;

// Проверяем формат даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

$times = getAvailableTimeSlots($pdo, $animalId, $date, $type, $duration);

echo json_encode([
    'success' => true,
    'times' => $times
]);
?>