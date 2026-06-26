<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Customers';

$customers = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = u.id) as total_appointments,
           (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = u.id AND a.status = 'completed') as completed_appointments,
           (SELECT COALESCE(SUM(s.price), 0) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.customer_id = u.id AND a.status = 'completed') as total_spent
    FROM users u 
    WHERE u.role = 'customer' 
    ORDER BY u.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Customer Management</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Total Customers</p>
            <p class="text-2xl font-bold"><?php echo count($customers); ?></p>
        </div>
        <?php
        $total_rev = 0;
        foreach ($customers as $c) $total_rev += $c['total_spent'];
        ?>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Total Revenue from Customers</p>
            <p class="text-2xl font-bold text-green-600">MMK<?php echo number_format($total_rev, 2); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Name</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Email</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Phone</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Appointments</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Completed</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Total Spent</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Joined</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($c['full_name']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($c['email']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($c['phone'] ?? 'N/A'); ?></td>
                    <td class="px-6 py-4"><?php echo $c['total_appointments']; ?></td>
                    <td class="px-6 py-4"><?php echo $c['completed_appointments']; ?></td>
                    <td class="px-6 py-4 text-green-600 font-medium">MMK<?php echo number_format($c['total_spent'], 2); ?></td>
                    <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                    <td class="px-6 py-4">
                        <a href="?view=<?php echo $c['id']; ?>" class="text-emerald-600 hover:text-emerald-700">
                            <i class="fas fa-eye"></i> View History
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($_GET['view'])): 
        $customer_id = $_GET['view'];
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as service_name, st.name as staff_name 
            FROM appointments a 
            JOIN services s ON a.service_id = s.id 
            JOIN staff st ON a.staff_id = st.id 
            WHERE a.customer_id = ? 
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$customer_id]);
        $history = $stmt->fetchAll();
        $cust_info = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $cust_info->execute([$customer_id]);
        $cust = $cust_info->fetch();
    ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-history text-emerald-500 mr-2"></i>
                Appointment History - <?php echo htmlspecialchars($cust['full_name']); ?>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">Staff</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Time</th>
                            <th class="px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($h['service_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($h['staff_name']); ?></td>
                            <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($h['appointment_date'])); ?></td>
                            <td class="px-4 py-2"><?php echo date('h:i A', strtotime($h['appointment_time'])); ?></td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo match($h['status']) {
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'confirmed' => 'bg-blue-100 text-blue-700',
                                        'in_progress' => 'bg-purple-100 text-purple-700',
                                        'completed' => 'bg-green-100 text-green-700',
                                        'cancelled' => 'bg-red-100 text-red-700',
                                        default => 'bg-gray-100 text-gray-700'
                                    }; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $h['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="customers.php" class="mt-4 inline-block text-emerald-600 hover:text-emerald-700 text-sm">&larr; Back to customers</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
