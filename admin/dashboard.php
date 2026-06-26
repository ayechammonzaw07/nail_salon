<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Dashboard';

// Stats
$total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$today_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$monthly_revenue = $pdo->query("SELECT COALESCE(SUM(s.price), 0) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status = 'completed' AND MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE())")->fetchColumn();
$available_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'available'")->fetchColumn();

// Recent appointments
$stmt = $pdo->query("SELECT a.*, u.full_name as customer_name, s.name as service_name, st.name as staff_name 
                      FROM appointments a 
                      JOIN users u ON a.customer_id = u.id 
                      JOIN services s ON a.service_id = s.id 
                      JOIN staff st ON a.staff_id = st.id 
                      ORDER BY a.created_at DESC LIMIT 5");
$recent_appointments = $stmt->fetchAll();

$total_revenue = $pdo->query("SELECT COALESCE(SUM(s.price), 0) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status = 'completed'")->fetchColumn();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-emerald-900">Admin Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Customers</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_customers; ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-users text-blue-500 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Appointments</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_appointments; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-calendar-check text-green-500 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Today's Appointments</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $today_appointments; ?></p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i class="fas fa-calendar-day text-yellow-500 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Available Staff</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $available_staff; ?>/5</p>
                </div>
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-user-check text-purple-500 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Revenue</h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">This Month</p>
                    <p class="text-3xl font-bold text-green-600">MMK<?php echo number_format($monthly_revenue, 2); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-coins text-green-500 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Total Revenue (All Time)</h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Earnings</p>
                    <p class="text-3xl font-bold text-green-600">MMK<?php echo number_format($total_revenue, 2); ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-chart-line text-green-500 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Appointments</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="pb-3 font-medium text-gray-500">Customer</th>
                        <th class="pb-3 font-medium text-gray-500">Service</th>
                        <th class="pb-3 font-medium text-gray-500">Staff</th>
                        <th class="pb-3 font-medium text-gray-500">Date</th>
                        <th class="pb-3 font-medium text-gray-500">Time</th>
                        <th class="pb-3 font-medium text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_appointments as $apt): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3"><?php echo htmlspecialchars($apt['customer_name']); ?></td>
                        <td class="py-3"><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td class="py-3"><?php echo htmlspecialchars($apt['staff_name']); ?></td>
                        <td class="py-3"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td class="py-3"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                        <td class="py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php echo match($apt['status']) {
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'confirmed' => 'bg-blue-100 text-blue-700',
                                    'in_progress' => 'bg-purple-100 text-purple-700',
                                    'completed' => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-700'
                                }; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_appointments)): ?>
                    <tr><td colspan="6" class="py-4 text-center text-gray-400">No appointments yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
