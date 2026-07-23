<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireAdmin();

$title = 'Salon Settings';
$message = '';

$stmt = $pdo->query("SHOW TABLES LIKE 'salon_settings'");
if (!$stmt->fetch()) {
    $pdo->exec("CREATE TABLE salon_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->exec("INSERT INTO salon_settings (setting_key, setting_value) VALUES ('total_seats', '5'), ('salon_name', 'Avocado Nail Studio'), ('currency', 'MMK')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $total_seats = intval($_POST['total_seats'] ?? 5);
    $salon_name = trim($_POST['salon_name'] ?? 'Avocado Nail Studio');
    $currency = trim($_POST['currency'] ?? 'MMK');

    if ($total_seats < 1) $total_seats = 1;

    $settings = ['total_seats' => $total_seats, 'salon_name' => $salon_name, 'currency' => $currency];
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO salon_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    $message = 'Settings updated successfully.';
}

$stmt = $pdo->query("SELECT setting_key, setting_value FROM salon_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$total_seats = intval($settings['total_seats'] ?? 5);
$salon_name = $settings['salon_name'] ?? 'Avocado Nail Studio';
$currency = $settings['currency'] ?? 'MMK';

$today = date('Y-m-d');
$seat_query = $pdo->prepare("
    SELECT COUNT(*) as booked
    FROM appointments
    WHERE appointment_date = ? AND status NOT IN ('cancelled')
    AND appointment_time <= '23:59:59' AND end_time > '00:00:00'
");
$seat_query->execute([$today]);
$seats_used_today = $seat_query->fetch()['booked'];

require_once '../includes/header.php';
?>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Salon Settings</h1>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chair text-emerald-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Total Seats</h3>
            </div>
            <p class="text-3xl font-bold text-emerald-600"><?php echo $total_seats; ?></p>
            <p class="text-sm text-gray-500 mt-1">Maximum concurrent appointments</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-blue-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Today's Usage</h3>
            </div>
            <p class="text-3xl font-bold text-blue-600"><?php echo $seats_used_today; ?> <span class="text-lg text-gray-400">/ <?php echo $total_seats; ?></span></p>
            <p class="text-sm text-gray-500 mt-1">Seats booked today</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-coins text-amber-600"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Currency</h3>
            </div>
            <p class="text-3xl font-bold text-amber-600"><?php echo htmlspecialchars($currency); ?></p>
            <p class="text-sm text-gray-500 mt-1">Used across the system</p>
        </div>
    </div>

    <form method="POST" class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
        <h2 class="text-lg font-semibold text-gray-800 border-b pb-3">General Settings</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Salon Name</label>
                <input type="text" name="salon_name" value="<?php echo htmlspecialchars($salon_name); ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                <input type="text" name="currency" value="<?php echo htmlspecialchars($currency); ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Total Nail Seats</label>
            <input type="number" name="total_seats" min="1" max="100" value="<?php echo $total_seats; ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
            <p class="text-xs text-gray-400 mt-1">Maximum number of customers that can be served at the same time.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>
<?php require_once '../includes/footer.php'; ?>
