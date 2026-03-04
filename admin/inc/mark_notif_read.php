<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = (int)($_SESSION['userdata']['user_id'] ?? 0);
if (!$userId) { echo json_encode(['success' => false]); exit; }

// Auto-add column if not exists
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_last_seen DATETIME DEFAULT NULL");

$stmt = $conn->prepare("UPDATE users SET notif_last_seen = NOW() WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);