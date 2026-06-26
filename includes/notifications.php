<?php
function createNotification($pdo, $user_id, $type, $title, $message, $appointment_id = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, appointment_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $title, $message, $appointment_id]);
}

function notifyAdmins($pdo, $type, $title, $message, $appointment_id = null) {
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        createNotification($pdo, $admin['id'], $type, $title, $message, $appointment_id);
    }
}

function getUnreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getRecentNotifications($pdo, $user_id, $limit = 5) {
    $limit = (int) $limit;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function markAsRead($pdo, $notification_id, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
}

function markAllAsRead($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
}
