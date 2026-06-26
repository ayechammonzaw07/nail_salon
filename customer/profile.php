<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

// Migration: add image column if missing
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'image'");
if (!$stmt->fetch()) {
    $pdo->exec("ALTER TABLE users ADD image VARCHAR(255) DEFAULT NULL AFTER address");
}

$title = 'My Profile';
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $image = $user['image'] ?? null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp.';
            } else {
                $filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                $dest = '../assets/images/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    if ($image && file_exists('../assets/images/' . $image)) {
                        unlink('../assets/images/' . $image);
                    }
                    $image = $filename;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=?, image=? WHERE id=?");
            $stmt->execute([$full_name, $email, $phone, $address, $image, $_SESSION['user_id']]);
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['image'] = $image;
            $success = 'Profile updated successfully.';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    }
}

// Stats
$total_appointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ?");
$total_appointments->execute([$_SESSION['user_id']]);
$total_count = $total_appointments->fetchColumn();

$completed_appointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ? AND status = 'completed'");
$completed_appointments->execute([$_SESSION['user_id']]);
$completed_count = $completed_appointments->fetchColumn();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-emerald-900">My Profile</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-6">
                    <i class="fas fa-user-edit text-emerald-500 mr-2"></i>Personal Information
                </h2>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profile Image</label>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg transition text-sm">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-6 text-center">
                <div class="w-20 h-20 rounded-full overflow-hidden mx-auto mb-4 <?php echo $user['image'] ? '' : 'bg-emerald-100 flex items-center justify-center'; ?>">
                    <?php if ($user['image']): ?>
                        <img src="/nail/assets/images/<?php echo htmlspecialchars($user['image']); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user text-emerald-500 text-3xl"></i>
                    <?php endif; ?>
                </div>
                <h3 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                <p class="text-xs text-emerald-600 mt-2 font-medium">Customer</p>
                <p class="text-xs text-gray-400 mt-1">Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Activity</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Total Appointments</span>
                        <span class="font-bold text-emerald-700"><?php echo $total_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Completed</span>
                        <span class="font-bold text-emerald-600"><?php echo $completed_count; ?></span>
                    </div>
                </div>
            </div>

            <a href="/nail/auth/change-password.php" class="block bg-white rounded-xl shadow-sm border p-4 hover:bg-emerald-50 transition text-center text-sm text-emerald-600 font-medium">
                <i class="fas fa-key mr-2"></i>Change Password
            </a>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
