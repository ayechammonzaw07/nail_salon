<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Book Appointment';
$error = '';
$success = '';

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();

$services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name, s.name")->fetchAll();

$staff_members = $pdo->query("SELECT * FROM staff WHERE status='available' ORDER BY name")->fetchAll();

$selected_service = $_GET['service'] ?? null;
$selected_staff = $_GET['staff'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($service_id) || empty($staff_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Appointment date cannot be in the past.';
    } elseif (strtotime($appointment_date) === strtotime(date('Y-m-d')) && strtotime($appointment_time) <= strtotime(date('H:i:s'))) {
        $error = 'Appointment time cannot be in the past.';
    } else {
        $svc_stmt = $pdo->prepare("SELECT duration, price FROM services WHERE id = ?");
        $svc_stmt->execute([$service_id]);
        $svc = $svc_stmt->fetch();

        if (!$svc) {
            $error = 'Invalid service selected.';
        } else {
            $start_time = $appointment_time;
            $duration_minutes = $svc['duration'];
            $end_time = date('H:i:s', strtotime($start_time) + $duration_minutes * 60);

            $duplicate = $pdo->prepare("SELECT id FROM appointments WHERE customer_id = ? AND service_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')");
            $duplicate->execute([$_SESSION['user_id'], $service_id, $appointment_date]);
            if ($duplicate->fetch()) {
                $error = 'You already have an appointment for this service on the selected date.';
            }

            if (!$error) {
                $conflict = $pdo->prepare("SELECT id FROM appointments WHERE staff_id = ? AND appointment_date = ? AND ((appointment_time <= ? AND end_time > ?) OR (appointment_time < ? AND end_time >= ?)) AND status NOT IN ('cancelled')");
                $conflict->execute([$staff_id, $appointment_date, $start_time, $start_time, $end_time, $end_time]);

                if ($conflict->fetch()) {
                    $error = 'This staff member is already booked for the selected time slot. Please choose another time or staff.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, service_id, staff_id, appointment_date, appointment_time, end_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$_SESSION['user_id'], $service_id, $staff_id, $appointment_date, $start_time, $end_time, $notes]);
                    $appointment_id = $pdo->lastInsertId();
                    require_once '../includes/notifications.php';
                    notifyAdmins($pdo, 'new_booking', 'New Booking', $_SESSION['full_name'] . ' booked a new appointment.', $appointment_id);
                    $success = 'Appointment booked successfully! We will confirm your booking shortly.';
                }
            }
    }
}
}
require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-emerald-900">Book an Appointment</h1>
    <p class="text-gray-500">Choose your service, staff, and preferred schedule.</p>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Service *</label>
                <select name="service_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                    <option value="">Select a service</option>
                    <?php foreach ($services as $svc): ?>
                        <option value="<?php echo $svc['id']; ?>" data-price="<?php echo $svc['price']; ?>" data-duration="<?php echo $svc['duration']; ?>" <?php echo $selected_service == $svc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($svc['name']); ?> — MMK<?php echo number_format($svc['price'], 2); ?> (<?php echo $svc['duration']; ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Staff *</label>
                <select name="staff_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                    <option value="">Select staff</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php echo $selected_staff == $staff['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['name']); ?> — <?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                <input type="date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Time *</label>
                <select name="appointment_time" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                    <option value="">Select time</option>
                    <?php for ($h = 9; $h <= 17; $h++): ?>
                        <option value="<?php echo sprintf('%02d', $h); ?>:00:00"><?php echo date('h:i A', strtotime(sprintf('%02d:00', $h))); ?></option>
                        <option value="<?php echo sprintf('%02d', $h); ?>:30:00"><?php echo date('h:i A', strtotime(sprintf('%02d:30', $h))); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm" placeholder="Any special requests or notes..."></textarea>
        </div>
        <div class="bg-emerald-50 rounded-lg p-4 text-sm text-emerald-800">
            <i class="fas fa-info-circle mr-2"></i>Your appointment will be submitted as <strong>pending</strong>. We will confirm it shortly.
        </div>
        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition text-sm">
            <i class="fas fa-calendar-check mr-2"></i>Confirm Booking
        </button>
    </form>
</div>
<script>
document.querySelector('[name="service_id"]')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.querySelector('.selected-summary').innerHTML =
            '<i class="fas fa-hand-sparkles mr-2"></i>' + opt.text;
    }
});

</script>
<?php require_once '../includes/footer.php'; ?>
