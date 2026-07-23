<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'My Appointments';

$stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
if (!$stmt->fetch()) {
    $pdo->exec("CREATE TABLE reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        service_id INT NOT NULL,
        staff_id INT NOT NULL,
        appointment_id INT NOT NULL UNIQUE,
        rating TINYINT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
    )");
}

$filter = $_GET['filter'] ?? 'upcoming';

$sql = "SELECT a.*, s.name as service_name, s.price as service_price, s.duration, st.name as staff_name, st.photo as staff_photo, c.name as category_name,
        r.id as review_id
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN staff st ON a.staff_id = st.id
        JOIN categories c ON s.category_id = c.id
        LEFT JOIN reviews r ON r.appointment_id = a.id
        WHERE a.customer_id = ?";

if ($filter === 'upcoming') {
    $sql .= " AND a.appointment_date >= CURDATE() AND a.status NOT IN ('completed', 'cancelled')";
} elseif ($filter === 'past') {
    $sql .= " AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll();

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h1 class="text-2xl font-bold text-emerald-900">My Appointments</h1>
        <a href="booking.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <i class="fas fa-plus mr-2"></i>New Booking
        </a>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="?filter=upcoming" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'upcoming' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-clock mr-1"></i>Upcoming
        </a>
        <a href="?filter=past" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'past' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'; ?>">
            <i class="fas fa-history mr-1"></i>Past
        </a>
        <a href="?" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'upcoming' && !isset($_GET['filter']) ? '' : (!$filter ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'); ?>" style="<?php echo !isset($_GET['filter']) || $filter === '' ? 'display:none;' : ''; ?>">
            <i class="fas fa-list mr-1"></i>All
        </a>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
            <i class="fas fa-calendar-alt text-emerald-300 text-5xl mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">No appointments found</h3>
            <p class="text-gray-400 mb-6">Ready to book your next session?</p>
            <a href="booking.php" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg transition font-medium">
                <i class="fas fa-calendar-plus mr-2"></i>Book Now
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($appointments as $apt): ?>
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="bg-emerald-100 rounded-xl p-3 text-center min-w-[70px]">
                            <p class="text-xl font-bold text-emerald-700"><?php echo date('d', strtotime($apt['appointment_date'])); ?></p>
                            <p class="text-xs text-emerald-500 uppercase font-medium"><?php echo date('M', strtotime($apt['appointment_date'])); ?></p>
                            <p class="text-[10px] text-emerald-400"><?php echo date('Y', strtotime($apt['appointment_date'])); ?></p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($apt['service_name']); ?></h3>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                                <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($apt['staff_name']); ?></span>
                                <span><i class="fas fa-clock mr-1"></i><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?> — <?php echo date('h:i A', strtotime($apt['end_time'])); ?></span>
                                <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($apt['category_name']); ?></span>
                            </div>
                            <?php if ($apt['notes']): ?>
                                <p class="text-sm text-gray-400 mt-2"><i class="fas fa-comment mr-1"></i><?php echo htmlspecialchars($apt['notes']); ?></p>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-emerald-600 font-bold text-sm">MMK<?php echo number_format($apt['service_price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="px-3 py-1 text-xs rounded-full font-medium
                            <?php echo match($apt['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'in_progress' => 'bg-purple-100 text-purple-700',
                                'completed' => 'bg-emerald-100 text-emerald-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-700'
                            }; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                        </span>
                        <?php if ($apt['status'] === 'completed' && empty($apt['review_id'])): ?>
                        <a href="review.php?id=<?php echo $apt['id']; ?>" class="inline-flex items-center gap-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-lg text-xs font-medium transition">
                            <i class="fas fa-star"></i> Rate
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
