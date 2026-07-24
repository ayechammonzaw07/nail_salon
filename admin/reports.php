<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Reports';
$report_type = $_GET['type'] ?? 'daily';

require_once '../includes/header.php';
?>
<style>
    @media print {
        body * {
            visibility: hidden;
        }

        .print-area,
        .print-area * {
            visibility: visible;
        }

        .print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
        }

        .no-print {
            display: none !important;
        }

        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }

        .print-header h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .print-header p {
            font-size: 12px;
            color: #666;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        table th,
        table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
    }
</style>
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
        <div class="print-area">
            <div class="print-header" style="display:none">
                <h1>Nail Salon - Daily Revenue Report</h1>
                <p><?php echo date('F d, Y', strtotime($date)); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6 no-print">
                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-calendar-day text-emerald-500 mr-2"></i>Daily Revenue Report
                    </h3>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <a href="export_report.php?type=daily&date=<?php echo $date; ?>" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 inline-flex items-center">
                            <i class="fas fa-file-excel mr-2"></i>Export Excel
                        </a>
                        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 inline-flex items-center">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                        <form method="GET" class="flex items-center space-x-2 no-print">
                            <input type="hidden" name="type" value="daily">
                            <input type="date" name="date" value="<?php echo $date; ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                        </form>
                    </div>
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
        <div class="print-area">
            <div class="print-header" style="display:none">
                <h1>Nail Salon - Monthly Revenue Report</h1>
                <p><?php echo date('F Y', strtotime($month . '-01')); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6 no-print">
                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-calendar-alt text-emerald-500 mr-2"></i>Monthly Revenue Report
                    </h3>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <a href="export_report.php?type=monthly&month=<?php echo $month; ?>" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 inline-flex items-center">
                            <i class="fas fa-file-excel mr-2"></i>Export Excel
                        </a>
                        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 inline-flex items-center">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                        <form method="GET" class="flex items-center space-x-2 no-print">
                            <input type="hidden" name="type" value="monthly">
                            <input type="month" name="month" value="<?php echo $month; ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                        </form>
                    </div>
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
        </div>
    <?php elseif ($report_type === 'appointments'): ?>
        <?php
        $date_from = $_GET['date_from'] ?? date('Y-m-01');
        $date_to = $_GET['date_to'] ?? date('Y-m-d');
        $status_filter = $_GET['status'] ?? '';

        $where = "WHERE a.appointment_date BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        if ($status_filter !== '') {
            $where .= " AND a.status = ?";
            $params[] = $status_filter;
        }

        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name AS customer_name, u.phone AS customer_phone,
                   s.name AS service_name, s.price AS service_price,
                   st.name AS staff_name
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            $where
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute($params);
        $filtered = $stmt->fetchAll();
        $total_count = count($filtered);

        $stats_stmt = $pdo->query("
            SELECT a.status, COUNT(*) AS count, COALESCE(SUM(s.price), 0) AS total
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            GROUP BY a.status
        ");
        $appt_stats = $stats_stmt->fetchAll();
        $total_all = array_sum(array_column($appt_stats, 'count'));

        $statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        $status_colors = [
            'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
            'confirmed' => 'bg-blue-100 text-blue-700 border-blue-300',
            'in_progress' => 'bg-purple-100 text-purple-700 border-purple-300',
            'completed' => 'bg-green-100 text-green-700 border-green-300',
            'cancelled' => 'bg-red-100 text-red-700 border-red-300',
        ];
        $status_icons = [
            'pending' => 'fa-clock',
            'confirmed' => 'fa-check-circle',
            'in_progress' => 'fa-spinner',
            'completed' => 'fa-check-double',
            'cancelled' => 'fa-times-circle',
        ];
        $stat_map = [];
        foreach ($appt_stats as $s) {
            $stat_map[$s['status']] = $s;
        }
        ?>
        <div class="print-area">
            <div class="print-header" style="display:none">
                <h1>Nail Salon - Appointment List</h1>
                <p><?php echo date('F d, Y', strtotime($date_from)) . ' - ' . date('F d, Y', strtotime($date_to)); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-6 mb-6 no-print">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h3 class="text-lg font-semibold"><i class="fas fa-calendar-alt text-emerald-500 mr-2"></i>Appointment Report</h3>
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="type" value="appointments">
                        <label class="text-sm text-gray-500">From:</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <label class="text-sm text-gray-500">To:</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <select name="status" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $status_filter === $st ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="?type=appointments" class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200"><i class="fas fa-undo mr-1"></i>Reset</a>
                        <a href="export_report.php?type=appointments&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                            <i class="fas fa-file-excel mr-1"></i>Export
                        </a>
                        <button onclick="window.print()" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                            <i class="fas fa-print mr-1"></i>Print
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6 no-print">
                <?php foreach ($statuses as $st): ?>
                    <?php
                    $st_data = $stat_map[$st] ?? ['count' => 0, 'total' => 0];
                    $pct = $total_all > 0 ? round($st_data['count'] / $total_all * 100, 1) : 0;
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                        <div class="flex items-center justify-center space-x-2 mb-2">
                            <i class="fas <?php echo $status_icons[$st]; ?> text-lg <?php echo explode(' ', $status_colors[$st])[1]; ?>"></i>
                            <span class="text-sm font-semibold text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $st)); ?></span>
                        </div>
                        <p class="text-2xl font-bold"><?php echo $st_data['count']; ?></p>
                        <p class="text-xs text-gray-400"><?php echo $pct; ?>% of total</p>
                        <p class="text-xs text-green-600 font-medium">MMK<?php echo number_format($st_data['total'], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h4 class="font-semibold text-gray-700">
                        <i class="fas fa-list mr-2 text-emerald-500"></i>Appointment List
                        <span class="text-sm font-normal text-gray-400 ml-2">(<?php echo $total_count; ?> appointment<?php echo $total_count !== 1 ? 's' : ''; ?>)</span>
                    </h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="px-4 py-3 text-left">Date / Time</th>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Service</th>
                                <th class="px-4 py-3 text-left">Staff</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filtered)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No appointments found for the selected filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filtered as $a): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <?php echo date('M d, Y', strtotime($a['appointment_date'])); ?>
                                            <br><span class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($a['appointment_time'])); ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium"><?php echo htmlspecialchars($a['customer_name']); ?></span>
                                            <?php if ($a['customer_phone']): ?>
                                                <br><span class="text-xs text-gray-400"><?php echo htmlspecialchars($a['customer_phone']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($a['service_name']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($a['staff_name']); ?></td>
                                        <td class="px-4 py-3 text-green-600 font-medium">MMK<?php echo number_format($a['service_price'], 2); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full border font-medium
                                                <?php echo $status_colors[$a['status']] ?? 'bg-gray-100 text-gray-700 border-gray-300'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $a['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
        <div class="print-area">
            <div class="print-header" style="display:none">
                <h1>Nail Salon - Popular Services Report</h1>
                <p><?php echo date('F d, Y'); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 no-print">
                    <h3 class="text-lg font-semibold"><i class="fas fa-star text-emerald-500 mr-2"></i>Popular Services Report</h3>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <a href="export_report.php?type=popular" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 inline-flex items-center">
                            <i class="fas fa-file-excel mr-2"></i>Export Excel
                        </a>
                        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 inline-flex items-center">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                    </div>
                </div>
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
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>