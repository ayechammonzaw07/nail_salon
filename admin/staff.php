<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Staff';
$message = '';

$upload_dir = '../assets/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $working_hours_start = $_POST['working_hours_start'];
        $working_hours_end = $_POST['working_hours_end'];
        $status = $_POST['status'] ?? 'available';
        $photo = '';

        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
        }

        $incentive_rate = trim($_POST['incentive_rate'] ?? '');
        if ($incentive_rate !== '') {
            $incentive_rate = floatval($incentive_rate);
        } elseif (strtolower(trim($specialization)) === 'professional nail artist') {
            $incentive_rate = 15.00;
        } else {
            $incentive_rate = null;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO staff (name, phone, specialization, working_hours_start, working_hours_end, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $specialization, $working_hours_start, $working_hours_end, $photo, $status]);
            $staff_id = $pdo->lastInsertId();
            if ($incentive_rate !== null) {
                $pdo->prepare("INSERT INTO incentive_settings (staff_id, rate) VALUES (?, ?)")->execute([$staff_id, $incentive_rate]);
            }
            $message = 'Staff added successfully.';
        } else {
            $id = $_POST['id'];
            if ($photo) {
                $stmt = $pdo->prepare("UPDATE staff SET name=?, phone=?, specialization=?, working_hours_start=?, working_hours_end=?, photo=?, status=? WHERE id=?");
                $stmt->execute([$name, $phone, $specialization, $working_hours_start, $working_hours_end, $photo, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE staff SET name=?, phone=?, specialization=?, working_hours_start=?, working_hours_end=?, status=? WHERE id=?");
                $stmt->execute([$name, $phone, $specialization, $working_hours_start, $working_hours_end, $status, $id]);
            }
            $pdo->prepare("INSERT INTO incentive_settings (staff_id, rate) VALUES (?, ?) ON DUPLICATE KEY UPDATE rate = VALUES(rate)")
                ->execute([$id, $incentive_rate ?? 10.00]);
            $message = 'Staff updated successfully.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM staff WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Staff deleted successfully.';
    }
}

$staff_members = $pdo->query("
    SELECT st.*, inc.rate AS incentive_rate
    FROM staff st
    LEFT JOIN incentive_settings inc ON st.id = inc.staff_id
    ORDER BY st.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Staff Management</h1>
        <button onclick="openModal('addModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm w-full sm:w-auto text-center">
            <i class="fas fa-plus mr-2"></i>Add Staff
        </button>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($staff_members as $staff): ?>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="p-6">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-100">
                        <?php if ($staff['photo']): ?>
                            <img src="/nail/assets/uploads/<?php echo $staff['photo']; ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-400 text-2xl"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($staff['name']); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?></p>
                        <span class="inline-block px-2 py-1 text-xs rounded-full mt-1
                            <?php echo match($staff['status']) {
                                'available' => 'bg-green-100 text-green-700',
                                'busy' => 'bg-yellow-100 text-yellow-700',
                                'off' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700'
                            }; ?>">
                            <?php echo ucfirst($staff['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4 space-y-1 text-sm text-gray-500">
                    <p><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></p>
                    <p><i class="fas fa-clock mr-2"></i><?php echo date('h:i A', strtotime($staff['working_hours_start'])); ?> - <?php echo date('h:i A', strtotime($staff['working_hours_end'])); ?></p>
                    <p><i class="fas fa-coins mr-2"></i>Incentive Rate: <span class="font-medium text-emerald-600"><?php echo $staff['incentive_rate'] ? number_format($staff['incentive_rate'], 1) . '%' : '10.0% (default)'; ?></span></p>
                </div>
                <div class="mt-4 flex space-x-2">
                    <button onclick='editStaff(<?php echo json_encode($staff); ?>)' class="flex-1 text-center text-blue-500 hover:text-blue-700 border border-blue-200 rounded-lg py-1.5 text-sm">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                    <form method="POST" class="flex-1" onsubmit="return confirm('Delete this staff?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                        <button type="submit" class="w-full text-center text-red-500 hover:text-red-700 border border-red-200 rounded-lg py-1.5 text-sm">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Add Staff</h3>
            <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                    <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input type="text" name="specialization" oninput="autoIncentiveRate(this)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Working Hours Start</label>
                        <input type="time" name="working_hours_start" value="09:00" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Working Hours End</label>
                        <input type="time" name="working_hours_end" value="18:00" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Incentive Rate (%)</label>
                    <input type="number" name="incentive_rate" step="0.1" min="0" max="100" placeholder="Auto (10% or 15% for Nail Artist)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Leave empty for auto-rate based on specialization</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="available">Available</option>
                        <option value="busy">Busy</option>
                        <option value="off">Off</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Edit Staff</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Photo (leave empty to keep current)</label>
                    <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input type="text" name="specialization" id="edit_specialization" oninput="autoIncentiveRate(this)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Working Hours Start</label>
                        <input type="time" name="working_hours_start" id="edit_start" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Working Hours End</label>
                        <input type="time" name="working_hours_end" id="edit_end" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Incentive Rate (%)</label>
                    <input type="number" name="incentive_rate" id="edit_incentive_rate" step="0.1" min="0" max="100" placeholder="Auto (10% or 15% for Nail Artist)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Leave empty for auto-rate based on specialization</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="available">Available</option>
                        <option value="busy">Busy</option>
                        <option value="off">Off</option>
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
function editStaff(staff) {
    document.getElementById('edit_id').value = staff.id;
    document.getElementById('edit_name').value = staff.name;
    document.getElementById('edit_phone').value = staff.phone || '';
    document.getElementById('edit_specialization').value = staff.specialization || '';
    document.getElementById('edit_start').value = staff.working_hours_start;
    document.getElementById('edit_end').value = staff.working_hours_end;
    document.getElementById('edit_status').value = staff.status;
    document.getElementById('edit_incentive_rate').value = staff.incentive_rate || '';
    openModal('editModal');
}
function autoIncentiveRate(input) { 
    if (input.value.toLowerCase() === 'professional nail artist') {
        var rateField = input.closest('form').querySelector('[name="incentive_rate"]');
        if (rateField && !rateField.value) rateField.value = '15';
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>
