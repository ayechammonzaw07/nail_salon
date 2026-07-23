<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['error' => 'Unauthorized', 'appointments' => []]);
    exit;
}

$now = new DateTime();
$window = clone $now;
$window->modify('+30 minutes');

$stmt = $pdo->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.end_time, a.status, a.notes,
           u.full_name as customer_name, u.phone as customer_phone,
           s.name as service_name, s.duration,
           st.name as staff_name,
           st2.label as seat_label
    FROM appointments a
    JOIN users u ON a.customer_id = u.id
    JOIN services s ON a.service_id = s.id
    JOIN staff st ON a.staff_id = st.id
    LEFT JOIN seats st2 ON a.seat_id = st2.id
    WHERE a.appointment_date = ?
      AND a.appointment_time >= ?
      AND a.appointment_time <= ?
      AND a.status IN ('confirmed', 'pending')
    ORDER BY a.appointment_time ASC
");

$stmt->execute([
    $now->format('Y-m-d'),
    $now->format('H:i:s'),
    $window->format('H:i:s')
]);

$appointments = $stmt->fetchAll();
$result = [];

foreach ($appointments as $apt) {
    $apt_time = new DateTime($apt['appointment_time']);
    $minutes_until = ($apt_time->getTimestamp() - $now->getTimestamp()) / 60;

    $result[] = [
        'id' => $apt['id'],
        'customer_name' => $apt['customer_name'],
        'customer_phone' => $apt['customer_phone'],
        'service_name' => $apt['service_name'],
        'staff_name' => $apt['staff_name'],
        'seat_label' => $apt['seat_label'],
        'time' => date('h:i A', strtotime($apt['appointment_time'])),
        'end_time' => date('h:i A', strtotime($apt['end_time'])),
        'duration' => $apt['duration'],
        'status' => $apt['status'],
        'notes' => $apt['notes'],
        'minutes_until' => round($minutes_until),
        'urgency' => $minutes_until <= 10 ? 'critical' : ($minutes_until <= 20 ? 'warning' : 'info')
    ];
}

echo json_encode(['appointments' => $result]);
