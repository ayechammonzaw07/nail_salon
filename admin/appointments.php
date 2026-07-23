<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Appointments';
$message = '';

// Mark notifications as read when arriving from bell
if (isset($_GET['mark_read'])) {
    require_once '../includes/notifications.php';
    markAllAsRead($pdo, $_SESSION['user_id']);
    header('Location: appointments.php');
    exit;
}

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
        s.name as service_name, s.price as service_price, st.name as staff_name,
        st2.label as seat_label, st2.seat_number
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN services s ON a.service_id = s.id
        JOIN staff st ON a.staff_id = st.id
        LEFT JOIN seats st2 ON a.seat_id = st2.id
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
$sql .= " ORDER BY CASE a.status 
    WHEN 'pending' THEN 1
    WHEN 'confirmed' THEN 2
    WHEN 'in_progress' THEN 3
    WHEN 'completed' THEN 4
    WHEN 'cancelled' THEN 5
END, a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$staff_members = $pdo->query("SELECT * FROM staff WHERE status != 'off'")->fetchAll();

$stmt = $pdo->query("SHOW TABLES LIKE 'seats'");
if ($stmt->fetch()) {
    $total_seats = $pdo->query("SELECT COUNT(*) FROM seats WHERE status='active'")->fetchColumn();
} else {
    $total_seats = 5;
}

$check_date = $date_filter ?: date('Y-m-d');
$seat_q = $pdo->prepare("SELECT COUNT(*) as booked FROM appointments WHERE appointment_date = ? AND status NOT IN ('cancelled')");
$seat_q->execute([$check_date]);
$seats_booked = $seat_q->fetch()['booked'];
$seats_pct = $total_seats > 0 ? min(100, round(($seats_booked / $total_seats) * 100)) : 0;

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Appointment Management</h1>

    <!-- Upcoming Appointment Alert -->
    <div id="upcomingAlert" class="hidden">
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-amber-500 text-white px-4 py-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-bell"></i>
                    <span class="font-semibold text-sm">Upcoming Appointments (Next 30 Minutes) — Confirm or No Show</span>
                </div>
                <span id="upcomingCount" class="bg-white/20 px-2 py-0.5 rounded-full text-xs font-bold">0</span>
            </div>
            <div id="upcomingList" class="divide-y divide-amber-100"></div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border p-4 flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 items-stretch sm:items-end">
        <div class="flex-1 min-w-0">
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="flex-1 min-w-0">
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="<?php echo $date_filter; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
        </div>
        <div>
            <a href="appointments.php" class="inline-block px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 text-center w-full sm:w-auto">Clear Filters</a>
        </div>
    </form>

    <!-- Seat Usage -->
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <i class="fas fa-chair text-emerald-600"></i>
                <span class="text-sm font-medium text-gray-700">Seat Usage — <?php echo date('M d, Y', strtotime($check_date)); ?></span>
            </div>
            <span class="text-sm font-semibold <?php echo $seats_booked >= $total_seats ? 'text-red-600' : ($seats_pct >= 80 ? 'text-amber-600' : 'text-emerald-600'); ?>">
                <?php echo $seats_booked; ?> / <?php echo $total_seats; ?> seats
            </span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2.5">
            <div class="h-2.5 rounded-full transition-all duration-500 <?php echo $seats_pct >= 100 ? 'bg-red-500' : ($seats_pct >= 80 ? 'bg-amber-400' : 'bg-emerald-500'); ?>" style="width:<?php echo $seats_pct; ?>%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1"><?php echo $total_seats - $seats_booked; ?> seat<?php echo ($total_seats - $seats_booked) !== 1 ? 's' : ''; ?> remaining for this date. <a href="seats.php" class="text-emerald-600 underline">Manage seats</a></p>
    </div>

    <!-- Appointments Table -->
    <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b">
                    in <!-- <th class="px-4 py-3 text-left font-medium text-gray-500">#</th> -->
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Customer</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Service</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 hide-mobile">Staff</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 hide-mobile">Seat</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 hide-mobile">Time</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 hide-mobile">Amount</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $apt): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <!-- <td class="px-4 py-3"><?php echo $apt['id']; ?></td> -->
                        <td class="px-4 py-3">
                            <div class="font-medium"><?php echo htmlspecialchars($apt['customer_name']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($apt['customer_email']); ?></div>
                        </td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td class="px-4 py-3 hide-mobile"><?php echo htmlspecialchars($apt['staff_name']); ?></td>
                        <td class="px-4 py-3 hide-mobile">
                            <?php if ($apt['seat_label']): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-emerald-50 text-emerald-700 font-medium">
                                <i class="fas fa-chair mr-1"></i><?php echo htmlspecialchars($apt['seat_label']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                        <td class="px-4 py-3 hide-mobile"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?> - <?php echo date('h:i A', strtotime($apt['end_time'])); ?></td>
                        <td class="px-4 py-3 text-green-600 font-medium hide-mobile">MMK<?php echo number_format($apt['service_price'], 2); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                            <?php echo match ($apt['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'in_progress' => 'bg-purple-100 text-purple-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700'
                            }; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col space-y-1">
                                <form method="POST" class="flex space-x-1 status-form" data-apt-id="<?php echo $apt['id']; ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo $apt['id']; ?>">
                                    <select name="status" class="text-xs border border-gray-300 rounded px-1 py-0.5 status-select" data-current="<?php echo $apt['status']; ?>">
                                        <option value="pending" <?php echo $apt['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $apt['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="in_progress" <?php echo $apt['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $apt['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $apt['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">No appointments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('.status-select').forEach(function(sel) {
    sel.addEventListener('change', function(e) {
        var form = this.closest('.status-form');
        var aptId = form.dataset.aptId;
        var newStatus = this.value;
        var currentStatus = this.dataset.current;
        var self = this;

        if (newStatus === currentStatus) return;

        var statusColors = {
            'pending': '#f59e0b',
            'confirmed': '#3b82f6',
            'in_progress': '#8b5cf6',
            'completed': '#10b981',
            'cancelled': '#ef4444'
        };

        Swal.fire({
            title: 'Change Status?',
            html: 'Set appointment <strong>#' + aptId + '</strong> to <strong style="color:' + (statusColors[newStatus] || '#6b7280') + '">' + newStatus.replace('_', ' ').toUpperCase() + '</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Update',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            } else {
                self.value = currentStatus;
            }
        });
    });
});
</script>
<script>
function fetchUpcoming() {
    fetch('/nail/includes/upcoming_appointments_api.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error || !data.appointments.length) {
                document.getElementById('upcomingAlert').classList.add('hidden');
                return;
            }
            var apts = data.appointments;
            document.getElementById('upcomingAlert').classList.remove('hidden');
            document.getElementById('upcomingCount').textContent = apts.length;

            var html = '';
            apts.forEach(function(apt) {
                var urgencyBorder = apt.urgency === 'critical' ? 'border-l-4 border-l-red-500' :
                                   apt.urgency === 'warning' ? 'border-l-4 border-l-amber-400' :
                                   'border-l-4 border-l-blue-400';
                var timeBadge = apt.urgency === 'critical' ?
                    '<span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">' + apt.minutes_until + ' min</span>' :
                    apt.urgency === 'warning' ?
                    '<span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full text-xs font-bold">' + apt.minutes_until + ' min</span>' :
                    '<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-bold">' + apt.minutes_until + ' min</span>';

                html += '<div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-4 hover:bg-amber-50/50 transition ' + urgencyBorder + '">';
                html += '  <div class="flex items-start gap-3 mb-2 sm:mb-0">';
                html += '    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">';
                html += '      <i class="fas fa-user text-amber-600 text-sm"></i>';
                html += '    </div>';
                html += '    <div>';
                html += '      <div class="flex items-center gap-2 flex-wrap">';
                html += '        <span class="font-semibold text-gray-800 text-sm">' + escapeHtml(apt.customer_name) + '</span>';
                html += '        <span class="text-xs px-1.5 py-0.5 rounded ' + (apt.status === 'confirmed' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') + '">' + apt.status + '</span>';
                html += '        ' + timeBadge;
                html += '      </div>';
                html += '      <p class="text-xs text-gray-500 mt-0.5">' + escapeHtml(apt.service_name) + ' &mdash; ' + apt.time + ' - ' + apt.end_time + '</p>';
                html += '      <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-user-tie mr-1"></i>' + escapeHtml(apt.staff_name) + (apt.seat_label ? ' &middot; <i class="fas fa-chair mr-1"></i>' + escapeHtml(apt.seat_label) : '') + '</p>';
                if (apt.customer_phone) {
                    html += '      <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-phone mr-1"></i>' + escapeHtml(apt.customer_phone) + '</p>';
                }
                html += '    </div>';
                html += '  </div>';
                html += '  <div class="flex items-center gap-2 ml-13 sm:ml-0">';
                html += '    <button type="button" class="bg-green-500 hover:bg-green-600 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition flex items-center gap-1 confirm-btn" data-apt-id="' + apt.id + '" data-customer="' + escapeHtml(apt.customer_name) + '" data-service="' + escapeHtml(apt.service_name) + '" data-time="' + apt.time + '">';
                html += '      <i class="fas fa-check"></i> Confirm';
                html += '    </button>';
                html += '    <button type="button" class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium px-3 py-1.5 rounded-lg transition flex items-center gap-1 no-show-btn" data-apt-id="' + apt.id + '" data-customer="' + escapeHtml(apt.customer_name) + '" data-service="' + escapeHtml(apt.service_name) + '" data-time="' + apt.time + '">';
                html += '      <i class="fas fa-times"></i> No Show';
                html += '    </button>';
                html += '  </div>';
                html += '</div>';
            });
            document.getElementById('upcomingList').innerHTML = html;

            document.querySelectorAll('.confirm-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var aptId = this.dataset.aptId;
                    var customer = this.dataset.customer;
                    var service = this.dataset.service;
                    var time = this.dataset.time;
                    Swal.fire({
                        title: 'Confirm Attendance?',
                        html: 'Is <strong>' + customer + '</strong> coming for <strong>' + service + '</strong> at <strong>' + time + '</strong>?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#059669',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Yes, Confirmed',
                        cancelButtonText: 'Cancel'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '/nail/admin/appointments.php';
                            form.innerHTML = '<input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="' + aptId + '"><input type="hidden" name="status" value="confirmed">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            document.querySelectorAll('.no-show-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var aptId = this.dataset.aptId;
                    var customer = this.dataset.customer;
                    var service = this.dataset.service;
                    var time = this.dataset.time;
                    Swal.fire({
                        title: 'Mark as No Show?',
                        html: 'Mark <strong>' + customer + '</strong> as no-show for <strong>' + service + '</strong> at <strong>' + time + '</strong>? This will cancel the appointment.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Yes, Mark No Show',
                        cancelButtonText: 'Cancel'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '/nail/admin/appointments.php';
                            form.innerHTML = '<input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="' + aptId + '"><input type="hidden" name="status" value="cancelled">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
        })
        .catch(function() {});
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

fetchUpcoming();
setInterval(fetchUpcoming, 60000);
</script>
<?php require_once '../includes/footer.php'; ?>