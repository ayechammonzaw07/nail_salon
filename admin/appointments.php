<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Appointments';
$message = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE appointments SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);

        $apt = $pdo->prepare("SELECT a.*, u.full_name as customer_name FROM appointments a JOIN users u ON a.customer_id = u.id WHERE a.id = ?");
        $apt->execute([$id]);
        $apt_row = $apt->fetch();
        if ($apt_row) {
            require_once '../includes/notifications.php';
            $status_label = ucfirst(str_replace('_', ' ', $status));
            createNotification($pdo, $apt_row['customer_id'], 'status_change', 'Appointment ' . $status_label, 'Your appointment (#' . $id . ') has been ' . $status_label . '.', $id);
        }

        $message = 'Appointment status updated.';
    } elseif ($action === 'assign_staff') {
        $id = $_POST['id'];
        $staff_id = $_POST['staff_id'];
        $stmt = $pdo->prepare("UPDATE appointments SET staff_id=? WHERE id=?");
        $stmt->execute([$staff_id, $id]);
        $message = 'Staff assigned.';
    }
}

// Filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

$sql = "SELECT a.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
        s.name as service_name, s.price as service_price, st.name as staff_name
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN services s ON a.service_id = s.id
        JOIN staff st ON a.staff_id = st.id
        WHERE 1=1";

$params = [];
if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}
if ($date_filter) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$staff_members = $pdo->query("SELECT * FROM staff WHERE status != 'off'")->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Appointment Management</h1>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border p-4 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="<?php echo $date_filter; ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
        </div>
        <div>
            <a href="appointments.php" class="inline-block px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200">Clear Filters</a>
        </div>
    </form>

    <!-- Appointments Table -->
    <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Customer</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Service</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Staff</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Time</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Amount</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $apt): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3"><?php echo $apt['id']; ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium"><?php echo htmlspecialchars($apt['customer_name']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($apt['customer_email']); ?></div>
                    </td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($apt['service_name']); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($apt['staff_name']); ?></td>
                    <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                    <td class="px-4 py-3"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?> - <?php echo date('h:i A', strtotime($apt['end_time'])); ?></td>
                    <td class="px-4 py-3 text-green-600 font-medium">MMK<?php echo number_format($apt['service_price'], 2); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full 
                            <?php echo match($apt['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'in_progress' => 'bg-purple-100 text-purple-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700'
                            }; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-col space-y-1">
                            <form method="POST" class="flex space-x-1">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                <select name="status" class="text-xs border border-gray-300 rounded px-1 py-0.5" onchange="this.form.submit()">
                                    <option value="">Change</option>
                                    <option value="confirmed">Confirm</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Complete</option>
                                    <option value="cancelled">Cancel</option>
                                </select>
                            </form>
                            <form method="POST" class="flex space-x-1">
                                <input type="hidden" name="action" value="assign_staff">
                                <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                <select name="staff_id" class="text-xs border border-gray-300 rounded px-1 py-0.5" onchange="this.form.submit()">
                                    <option value="">Assign staff</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>" <?php echo $staff['id'] == $apt['staff_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($staff['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
