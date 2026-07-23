<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Seat Management';
$message = '';

$stmt = $pdo->query("SHOW TABLES LIKE 'seats'");
if (!$stmt->fetch()) {
    $pdo->exec("CREATE TABLE seats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seat_number INT NOT NULL UNIQUE,
        label VARCHAR(50) DEFAULT NULL,
        status ENUM('active', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    for ($i = 1; $i <= 5; $i++) {
        $pdo->exec("INSERT INTO seats (seat_number, label) VALUES ($i, 'Seat $i')");
    }
}

$stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
if ($stmt->fetch()) {
    $cols = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'seat_id'");
    if (!$cols->fetch()) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN seat_id INT DEFAULT NULL AFTER staff_id");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $seat_number = intval($_POST['seat_number']);
        $label = trim($_POST['label']);
        if ($seat_number < 1) {
            $error = 'Seat number must be at least 1.';
        } else {
            $check = $pdo->prepare("SELECT id FROM seats WHERE seat_number = ?");
            $check->execute([$seat_number]);
            if ($check->fetch()) {
                $error = 'Seat number ' . $seat_number . ' already exists.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO seats (seat_number, label) VALUES (?, ?)");
                $stmt->execute([$seat_number, $label ?: 'Seat ' . $seat_number]);
                $message = 'Seat added successfully.';
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $label = trim($_POST['label']);
        $status = $_POST['status'] ?? 'active';
        $stmt = $pdo->prepare("UPDATE seats SET label=?, status=? WHERE id=?");
        $stmt->execute([$label, $status, $id]);
        $message = 'Seat updated successfully.';
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM seats WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Seat deleted successfully.';
    } elseif ($action === 'bulk_add') {
        $count = intval($_POST['count'] ?? 0);
        $max_num = $pdo->query("SELECT COALESCE(MAX(seat_number),0) FROM seats")->fetchColumn();
        for ($i = 1; $i <= $count; $i++) {
            $num = $max_num + $i;
            $pdo->prepare("INSERT IGNORE INTO seats (seat_number, label) VALUES (?, ?)")->execute([$num, 'Seat ' . $num]);
        }
        $message = $count . ' seat(s) added successfully.';
    }
}

$seats = $pdo->query("SELECT * FROM seats ORDER BY seat_number ASC")->fetchAll();

$today = date('Y-m-d');
$seat_usage = $pdo->prepare("
    SELECT s.id, s.seat_number, s.label, s.status,
           a.id as apt_id, a.appointment_time, a.end_time, a.status as apt_status,
           u.full_name as customer_name, sv.name as service_name
    FROM seats s
    LEFT JOIN appointments a ON a.seat_id = s.id AND a.appointment_date = ? AND a.status NOT IN ('cancelled')
    LEFT JOIN users u ON a.customer_id = u.id
    LEFT JOIN services sv ON a.service_id = sv.id
    ORDER BY s.seat_number ASC
");
$seat_usage->execute([$today]);
$seat_map = $seat_usage->fetchAll();

$total_seats = count($seats);
$active_seats = count(array_filter($seats, fn($s) => $s['status'] === 'active'));
$occupied = count(array_filter($seat_map, fn($s) => !empty($s['apt_id'])));

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Seat Management</h1>
        <div class="flex gap-2 w-full sm:w-auto">
            <button onclick="openModal('bulkModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm flex-1 sm:flex-none text-center">
                <i class="fas fa-layer-group mr-1"></i>Bulk Add
            </button>
            <button onclick="openModal('addModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm flex-1 sm:flex-none text-center">
                <i class="fas fa-plus mr-1"></i>Add Seat
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center"><i class="fas fa-chair text-emerald-600"></i></div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $total_seats; ?></p>
                    <p class="text-xs text-gray-500">Total Seats</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-blue-600"></i></div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $active_seats; ?></p>
                    <p class="text-xs text-gray-500">Active Seats</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center"><i class="fas fa-users text-amber-600"></i></div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $occupied; ?> <span class="text-sm text-gray-400">/ <?php echo $active_seats; ?></span></p>
                    <p class="text-xs text-gray-500">Occupied Today</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Seat Grid -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Today's Seat Map — <?php echo date('l, M d, Y', strtotime($today)); ?></h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            <?php foreach ($seat_map as $sm): ?>
            <div style="border:2px solid <?php echo $sm['status'] === 'maintenance' ? '#fca5a5' : (!empty($sm['apt_id']) ? '#fbbf24' : '#86efac'); ?>;border-radius:14px;padding:1rem;text-align:center;transition:all 0.3s;background:<?php echo $sm['status'] === 'maintenance' ? '#fef2f2' : (!empty($sm['apt_id']) ? '#fffbeb' : '#f0fdf4'); ?>;">
                <div style="width:44px;height:44px;border-radius:50%;margin:0 auto 0.5rem;display:flex;align-items:center;justify-content:center;background:<?php echo $sm['status'] === 'maintenance' ? '#fee2e2' : (!empty($sm['apt_id']) ? '#fef3c7' : '#dcfce7'); ?>;">
                    <i class="fas fa-<?php echo $sm['status'] === 'maintenance' ? 'wrench' : (!empty($sm['apt_id']) ? 'user' : 'chair'); ?>" style="color:<?php echo $sm['status'] === 'maintenance' ? '#dc2626' : (!empty($sm['apt_id']) ? '#d97706' : '#16a34a'); ?>;font-size:1.1rem;"></i>
                </div>
                <p style="font-weight:700;color:var(--dark);margin:0;font-size:0.9rem;"><?php echo htmlspecialchars($sm['label'] ?: 'Seat ' . $sm['seat_number']); ?></p>
                <?php if ($sm['status'] === 'maintenance'): ?>
                    <span style="font-size:0.7rem;color:#dc2626;font-weight:600;">Maintenance</span>
                <?php elseif (!empty($sm['apt_id'])): ?>
                    <p style="font-size:0.7rem;color:#92400e;margin:0.2rem 0 0;font-weight:500;"><?php echo htmlspecialchars($sm['customer_name']); ?></p>
                    <p style="font-size:0.65rem;color:#a16207;margin:0;"><?php echo htmlspecialchars($sm['service_name']); ?></p>
                    <p style="font-size:0.65rem;color:#b45309;margin:0;"><?php echo date('h:i A', strtotime($sm['appointment_time'])); ?> - <?php echo date('h:i A', strtotime($sm['end_time'])); ?></p>
                <?php else: ?>
                    <span style="font-size:0.7rem;color:#16a34a;font-weight:600;">Available</span>
                <?php endif; ?>
                <div style="margin-top:0.6rem;display:flex;justify-content:center;gap:0.3rem;">
                    <button onclick='editSeat(<?php echo json_encode(["id"=>$sm["id"],"seat_number"=>$sm["seat_number"],"label"=>$sm["label"],"status"=>$sm["status"]]); ?>)' title="Edit" style="width:28px;height:28px;border-radius:6px;border:none;background:var(--avocado-50);color:var(--avocado-600);cursor:pointer;font-size:0.7rem;"><i class="fas fa-edit"></i></button>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this seat?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $sm['id']; ?>">
                        <button type="submit" title="Delete" style="width:28px;height:28px;border-radius:6px;border:none;background:#fef2f2;color:#dc2626;cursor:pointer;font-size:0.7rem;"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Add Seat</h3>
            <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Seat Number</label>
                    <input type="number" name="seat_number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label (optional)</label>
                    <input type="text" name="label" placeholder="e.g. VIP Seat" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg">Add Seat</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="bulkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Bulk Add Seats</h3>
            <button onclick="closeModal('bulkModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_add">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">How many seats to add?</label>
                    <input type="number" name="count" min="1" max="50" value="5" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Seats will be numbered sequentially after the last existing seat.</p>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Add Seats</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Edit Seat</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Seat Number</label>
                    <input type="text" id="edit_seat_number" disabled class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <input type="text" name="label" id="edit_label" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
function editSeat(seat) {
    document.getElementById('edit_id').value = seat.id;
    document.getElementById('edit_seat_number').value = 'Seat ' + seat.seat_number;
    document.getElementById('edit_label').value = seat.label || '';
    document.getElementById('edit_status').value = seat.status;
    openModal('editModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
