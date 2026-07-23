<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Services';

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();

$selected_category = $_GET['category'] ?? null;

if ($selected_category) {
    $stmt = $pdo->prepare("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.category_id = ? AND s.status='active' ORDER BY s.name");
    $stmt->execute([$selected_category]);
    $services = $stmt->fetchAll();
} else {
    $services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name, s.name")->fetchAll();
}

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Our Services</h1>

    <!-- Categories -->
    <div class="flex flex-wrap gap-2">
        <a href="services.php" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo !$selected_category ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            All Services
        </a>
        <?php foreach ($categories as $cat): ?>
        <a href="?category=<?php echo $cat['id']; ?>" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $selected_category == $cat['id'] ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($services as $svc): ?>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition group">
            <?php if ($svc['image']): ?>
                <img src="/nail_salon/assets/uploads/<?php echo $svc['image']; ?>" alt="" class="w-full h-48 object-cover group-hover:scale-105 transition duration-300">
            <?php else: ?>
                <div class="w-full h-48 bg-gradient-to-br from-emerald-100 to-emerald-50 flex items-center justify-center">
                    <i class="fas fa-leaf text-emerald-300 text-5xl"></i>
                </div>
            <?php endif; ?>
            <div class="p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($svc['name']); ?></h3>
                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($svc['category_name']); ?></p>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-emerald-600">MMK<?php echo number_format($svc['price'], 2); ?></span>
                        <span class="text-sm text-gray-400 ml-2"><?php echo $svc['duration']; ?> min</span>
                    </div>
                    <a href="booking.php?service=<?php echo $svc['id']; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-3 py-1.5 rounded-lg transition">
                        <i class="fas fa-calendar-plus mr-1"></i>Book
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
            <div class="col-span-full text-center py-12 text-gray-400">
                <i class="fas fa-spa text-4xl mb-3"></i>
                <p>No services available in this category yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
