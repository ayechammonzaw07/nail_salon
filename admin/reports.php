<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Reports';
$report_type = $_GET['type'] ?? 'daily';

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reports</h1>

    <div class="flex flex-wrap gap-2">
        <a href="?type=daily" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $report_type === 'daily' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-calendar-day mr-2"></i>Daily Report
        </a>
        <a href="?type=monthly" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $report_type === 'monthly' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-calendar-alt mr-2"></i>Monthly Report
        </a>
        <a href="?type=appointments" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $report_type === 'appointments' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-clock mr-2"></i>Appointment Report
        </a>
        <a href="?type=popular" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $report_type === 'popular' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-star mr-2"></i>Popular Services
        </a>
    </div>

    <?php if ($report_type === 'daily'): ?>
        <?php
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name as customer_name, s.name as service_name, s.price as service_price, st.name as staff_name
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE a.appointment_date = ? AND a.status = 'completed'
            ORDER BY a.appointment_time
        ");
        $stmt->execute([$date]);
        $daily = $stmt->fetchAll();
        $daily_total = array_sum(array_column($daily, 'service_price'));
        ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-calendar-day text-emerald-500 mr-2"></i>Daily Revenue Report
                </h3>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="hidden" name="type" value="daily">
                    <input type="date" name="date" value="<?php echo $date; ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                </form>
            </div>
            <div class="text-center mb-6">
                <p class="text-sm text-gray-500">Total Revenue for <?php echo date('F d, Y', strtotime($date)); ?></p>
                <p class="text-4xl font-bold text-green-600">MMK<?php echo number_format($daily_total, 2); ?></p>
                <p class="text-sm text-gray-400"><?php echo count($daily); ?> completed appointment(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">Customer</th>
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">Staff</th>
                            <th class="px-4 py-2 text-left">Time</th>
                            <th class="px-4 py-2 text-left">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily as $d): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($d['customer_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($d['service_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($d['staff_name']); ?></td>
                            <td class="px-4 py-2"><?php echo date('h:i A', strtotime($d['appointment_time'])); ?></td>
                            <td class="px-4 py-2 text-green-600">MMK<?php echo number_format($d['service_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="font-bold">
                            <td colspan="4" class="px-4 py-2 text-right">Total:</td>
                            <td class="px-4 py-2 text-green-600">MMK<?php echo number_format($daily_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($report_type === 'monthly'): ?>
        <?php
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name as customer_name, s.name as service_name, s.price as service_price, st.name as staff_name,
                   DAY(a.appointment_date) as day
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ? AND a.status = 'completed'
            ORDER BY a.appointment_date
        ");
        $stmt->execute([$month]);
        $monthly = $stmt->fetchAll();
        $monthly_total = array_sum(array_column($monthly, 'service_price'));
        ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-calendar-alt text-emerald-500 mr-2"></i>Monthly Revenue Report
                </h3>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="hidden" name="type" value="monthly">
                    <input type="month" name="month" value="<?php echo $month; ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                </form>
            </div>
            <div class="text-center mb-6">
                <p class="text-sm text-gray-500">Total Revenue for <?php echo date('F Y', strtotime($month . '-01')); ?></p>
                <p class="text-4xl font-bold text-green-600">MMK<?php echo number_format($monthly_total, 2); ?></p>
                <p class="text-sm text-gray-400"><?php echo count($monthly); ?> completed appointment(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Customer</th>
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">Staff</th>
                            <th class="px-4 py-2 text-left">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly as $m): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo date('M d', strtotime($m['appointment_date'])); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($m['customer_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($m['service_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($m['staff_name']); ?></td>
                            <td class="px-4 py-2 text-green-600">MMK<?php echo number_format($m['service_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="font-bold">
                            <td colspan="4" class="px-4 py-2 text-right">Total:</td>
                            <td class="px-4 py-2 text-green-600">MMK<?php echo number_format($monthly_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($report_type === 'appointments'): ?>
        <?php
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count, COALESCE(SUM(s.price), 0) as total
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            GROUP BY status
        ");
        $appt_stats = $stmt->fetchAll();
        $total_appts = array_sum(array_column($appt_stats, 'count'));
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold mb-4">Appointment Status Breakdown</h3>
                <?php foreach ($appt_stats as $stat): ?>
                <div class="flex justify-between items-center py-2 border-b">
                    <span class="px-2 py-1 text-xs rounded-full 
                        <?php echo match($stat['status']) {
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'confirmed' => 'bg-blue-100 text-blue-700',
                            'in_progress' => 'bg-purple-100 text-purple-700',
                            'completed' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                            default => 'bg-gray-100 text-gray-700'
                        }; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>
                    </span>
                    <span class="font-medium"><?php echo $stat['count']; ?> (<?php echo $total_appts > 0 ? round($stat['count'] / $total_appts * 100, 1) : 0; ?>%)</span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between items-center py-2 font-bold">
                    <span>Total</span>
                    <span><?php echo $total_appts; ?></span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold mb-4">Revenue by Status</h3>
                <?php foreach ($appt_stats as $stat): ?>
                <div class="flex justify-between items-center py-2 border-b">
                    <span class="px-2 py-1 text-xs rounded-full 
                        <?php echo match($stat['status']) {
                            'completed' => 'bg-green-100 text-green-700',
                            default => 'bg-gray-100 text-gray-700'
                        }; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>
                    </span>
                    <span class="font-medium text-green-600">MMK<?php echo number_format($stat['total'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($report_type === 'popular'): ?>
        <?php
        $stmt = $pdo->query("
            SELECT s.name, c.name as category_name, COUNT(a.id) as booking_count, s.price,
                   COALESCE(SUM(s.price), 0) as total_revenue
            FROM services s
            LEFT JOIN appointments a ON s.id = a.service_id AND a.status = 'completed'
            JOIN categories c ON s.category_id = c.id
            GROUP BY s.id
            ORDER BY booking_count DESC
        ");
        $popular = $stmt->fetchAll();
        $max_bookings = !empty($popular) ? max(array_column($popular, 'booking_count')) : 1;
        ?>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold mb-4"><i class="fas fa-star text-emerald-500 mr-2"></i>Popular Services Report</h3>
            <div class="space-y-4">
                <?php foreach ($popular as $p): ?>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <div>
                            <span class="font-medium"><?php echo htmlspecialchars($p['name']); ?></span>
                            <span class="text-xs text-gray-400 ml-2">(<?php echo htmlspecialchars($p['category_name']); ?>)</span>
                        </div>
                        <div class="text-sm">
                            <span class="text-green-600 font-medium">MMK<?php echo number_format($p['total_revenue'], 2); ?></span>
                            <span class="text-gray-400 ml-2"><?php echo $p['booking_count']; ?> bookings</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="bg-emerald-500 h-2.5 rounded-full" style="width: <?php echo ($max_bookings > 0 ? ($p['booking_count'] / $max_bookings) * 100 : 0); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
