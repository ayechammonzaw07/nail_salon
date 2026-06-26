<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Staff';

$staff_members = $pdo->query("SELECT * FROM staff ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Our Staff</h1>
    <p class="text-gray-500">Meet our talented team of nail care professionals.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($staff_members as $staff): ?>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition">
            <div class="p-6 text-center">
                <div class="w-24 h-24 rounded-full mx-auto overflow-hidden bg-gray-100 mb-4">
                    <?php if ($staff['photo']): ?>
                        <img src="/nail/assets/uploads/<?php echo $staff['photo']; ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-4xl">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($staff['name']); ?></h3>
                <p class="text-sm text-emerald-600 font-medium"><?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?></p>
                
                <div class="mt-4 space-y-2 text-sm text-gray-500">
                    <p><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></p>
                    <p><i class="fas fa-clock mr-2"></i><?php echo date('h:i A', strtotime($staff['working_hours_start'])); ?> - <?php echo date('h:i A', strtotime($staff['working_hours_end'])); ?></p>
                </div>

                <div class="mt-4">
                    <span class="inline-block px-3 py-1 text-xs rounded-full font-medium
                        <?php echo match($staff['status']) {
                            'available' => 'bg-green-100 text-green-700',
                            'busy' => 'bg-yellow-100 text-yellow-700',
                            'off' => 'bg-red-100 text-red-700',
                            default => 'bg-gray-100 text-gray-700'
                        }; ?>">
                        <i class="fas fa-circle mr-1 text-[8px]"></i>
                        <?php echo ucfirst($staff['status']); ?>
                    </span>
                </div>

                <?php if ($staff['status'] === 'available'): ?>
                    <a href="booking.php?staff=<?php echo $staff['id']; ?>" class="mt-4 inline-block bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition">
                        <i class="fas fa-calendar-plus mr-1"></i>Book with <?php echo explode(' ', $staff['name'])[0]; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
