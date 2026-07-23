<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Categories';
$message = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $status = $_POST['status'] ?? 'active';

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $status]);
            $message = 'Category added successfully.';
        } else {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE categories SET name=?, description=?, status=? WHERE id=?");
            $stmt->execute([$name, $description, $status, $id]);
            $message = 'Category updated successfully.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Category deleted successfully.';
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC")->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Category Management</h1>
        <button onclick="openModal('addModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm w-full sm:w-auto text-center">
            <i class="fas fa-plus mr-2"></i>Add Category
        </button>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="px-6 py-3 text-left font-medium text-gray-500 hide-mobile">ID</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Name</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500 hide-mobile">Description</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                    <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 hide-mobile"><?php echo $cat['id']; ?></td>
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td class="px-6 py-4 text-gray-500 hide-mobile"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $cat['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo ucfirst($cat['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 space-x-2">
                        <button onclick='editCategory(<?php echo json_encode($cat); ?>)' class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Add Category</h3>
            <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Edit Category</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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
function editCategory(cat) {
    document.getElementById('edit_id').value = cat.id;
    document.getElementById('edit_name').value = cat.name;
    document.getElementById('edit_description').value = cat.description || '';
    document.getElementById('edit_status').value = cat.status;
    openModal('editModal');
}
</script>
<?php require_once '../includes/footer.php'; ?>
