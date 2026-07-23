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

    <!-- Upcoming Appointment Alert -->
    <div id="upcomingAlert" class="hidden">
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-amber-500 text-white px-4 py-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-bell"></i>
                    <span class="font-semibold text-sm">Upcoming Appointments (Next 30 Minutes)</span>
                </div>
                <span id="upcomingCount" class="bg-white/20 px-2 py-0.5 rounded-full text-xs font-bold">0</span>
            </div>
            <div id="upcomingList" class="divide-y divide-amber-100"></div>
        </div>
    </div>

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
