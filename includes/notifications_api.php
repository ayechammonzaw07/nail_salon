<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated', 'unread_count' => 0, 'notifications' => []]);
    exit;
}

$unread_count = getUnreadCount($pdo, $_SESSION['user_id']);
$notifications = getRecentNotifications($pdo, $_SESSION['user_id'], 5);

$notif_data = [];
foreach ($notifications as $n) {
    $notif_data[] = [
        'id' => $n['id'],
        'type' => $n['type'],
        'title' => $n['title'],
        'message' => $n['message'],
        'appointment_id' => $n['appointment_id'],
        'is_read' => (int) $n['is_read'],
        'created_at' => date('M d, h:i A', strtotime($n['created_at'])),
        'link' => ($_SESSION['role'] === 'admin' ? '/nail/admin/' : '/nail/customer/') . 'notifications.php' . ($n['appointment_id'] ? '?appointment_id=' . $n['appointment_id'] : '')
    ];
}

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notif_data
]);
