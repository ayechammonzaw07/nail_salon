<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();
require_once '../includes/notifications.php';

$title = 'Notifications';

if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all') {
    markAllAsRead($pdo, $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['read']) && isset($_GET['id'])) {
    markAsRead($pdo, $_GET['id'], $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-bell text-emerald-500 mr-2"></i>Notifications
        </h1>
        <div class="flex space-x-2 w-full sm:w-auto">
            <a href="?mark_read=all" class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700">
                <i class="fas fa-check-double mr-1"></i>Mark All Read
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-bell text-4xl mb-3"></i>
                <p>No notifications yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="flex items-start px-6 py-4 border-b hover:bg-gray-50 <?php echo $n['is_read'] ? '' : 'bg-emerald-50 border-l-4 border-l-emerald-500'; ?>">
                    <div class="flex-1">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($n['title']); ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($n['message']); ?></p>
                        <p class="text-xs text-gray-400 mt-2">
                            <i class="far fa-clock mr-1"></i><?php echo date('F d, Y - h:i A', strtotime($n['created_at'])); ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        <?php if (!$n['is_read']): ?>
                            <a href="?read=1&id=<?php echo $n['id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($n['appointment_id']): ?>
                            <a href="appointments.php" class="text-xs text-blue-600 hover:text-blue-700" title="View Appointment">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
