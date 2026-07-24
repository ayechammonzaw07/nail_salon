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

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);
    $cstmt = $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND customer_id=? AND status IN ('pending','confirmed')");
    $cstmt->execute([$cancel_id, $_SESSION['user_id']]);
    header('Location: appointments.php?cancelled=1');
    exit;
}

$filter = $_GET['filter'] ?? 'all';

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

$sql .= " ORDER BY FIELD(a.status, 'in_progress', 'confirmed', 'pending', 'completed', 'cancelled'), a.appointment_date ASC, a.appointment_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll();

require_once '../includes/header.php';

$grouped = [
    'pending'    => [],
    'confirmed'  => [],
    'in_progress'=> [],
    'completed'  => [],
    'cancelled'  => [],
];
foreach ($appointments as $apt) {
    $grouped[$apt['status']][] = $apt;
}

$statusMeta = [
    'pending'     => ['label' => 'Pending',     'icon' => 'fa-hourglass-half', 'color' => 'var(--avocado-400)', 'bg' => '#fef9c3', 'border' => '#fde68a'],
    'confirmed'   => ['label' => 'Confirmed',   'icon' => 'fa-check-circle',   'color' => '#2563eb', 'bg' => '#dbeafe', 'border' => '#93c5fd'],
    'in_progress' => ['label' => 'In Progress', 'icon' => 'fa-spinner',        'color' => '#9333ea', 'bg' => '#f3e8ff', 'border' => '#c4b5fd'],
    'completed'   => ['label' => 'Completed',   'icon' => 'fa-circle-check',   'color' => 'var(--avocado-600)', 'bg' => 'var(--avocado-50)', 'border' => 'var(--avocado-200)'],
    'cancelled'   => ['label' => 'Cancelled',   'icon' => 'fa-circle-xmark',   'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca'],
];

$displayOrder = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$visibleGroups = array_filter($displayOrder, function($s) use ($grouped) { return !empty($grouped[$s]); });
?>

<style>
.apt-page{background:linear-gradient(180deg,var(--avocado-50) 0%,#f9fafb 100%);min-height:100vh;padding:2rem 0 3rem}

.apt-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
.apt-head h1{font-family:'Playfair Display',serif;font-size:2rem;color:var(--avocado-900);margin:0}
.apt-head h1 span{color:var(--avocado-600)}
.apt-new{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1.4rem;border-radius:12px;background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;font-weight:700;font-size:.9rem;border:none;cursor:pointer;text-decoration:none;transition:all .3s;box-shadow:0 4px 14px rgba(93,132,51,.25)}
.apt-new:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px);box-shadow:0 8px 20px rgba(93,132,51,.3)}

.apt-group{margin-bottom:2rem}
.apt-group-header{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;cursor:pointer;user-select:none;padding:.5rem 0}
.apt-group-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
.apt-group-title{font-weight:700;font-size:1rem;color:var(--avocado-800)}
.apt-group-count{font-size:.75rem;font-weight:700;padding:.15rem .55rem;border-radius:50px;background:var(--avocado-100);color:var(--avocado-700)}
.apt-group-toggle{margin-left:auto;font-size:.8rem;color:var(--avocado-400);transition:transform .3s}
.apt-group-toggle.collapsed{transform:rotate(-90deg)}

.apt-table{width:100%;border:2px solid var(--avocado-100);border-radius:14px;overflow:hidden;background:white}
.apt-thead{display:grid;grid-template-columns:40px 1.3fr .9fr .9fr .8fr .7fr 100px;padding:.6rem 1rem;background:var(--avocado-50);border-bottom:2px solid var(--avocado-100)}
.apt-thead span{font-size:.7rem;font-weight:700;color:var(--avocado-700);text-transform:uppercase;letter-spacing:.5px}
.apt-thead span:last-child{text-align:right}
.apt-tbody{}
.apt-row{display:grid;grid-template-columns:40px 1.3fr .9fr .9fr .8fr .7fr 100px;padding:.65rem 1rem;align-items:center;border-bottom:1px solid #f3f4f6;transition:background .2s}
.apt-row:last-child{border-bottom:none}
.apt-row:hover{background:var(--avocado-50)}

.apt-status-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.apt-svc{font-weight:600;color:var(--dark);font-size:.88rem}
.apt-svc small{display:block;font-weight:400;color:var(--text-light);font-size:.72rem;margin-top:.1rem}
.apt-staff{display:flex;align-items:center;gap:.5rem}
.apt-staff-avatar{width:30px;height:30px;border-radius:50%;background:var(--avocado-100);display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid var(--avocado-200);flex-shrink:0}
.apt-staff-avatar img{width:100%;height:100%;object-fit:cover}
.apt-staff-avatar i{color:var(--avocado-500);font-size:.7rem}
.apt-staff-name{font-size:.85rem;color:var(--dark);font-weight:500}
.apt-date{font-size:.85rem;color:var(--dark)}
.apt-time{font-size:.85rem;color:var(--text-light)}
.apt-price{font-weight:700;color:var(--avocado-600);font-size:.88rem;text-align:right}
.apt-actions{display:flex;justify-content:flex-end;gap:.4rem}
.apt-btn{padding:.35rem .7rem;border-radius:8px;font-size:.72rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
.apt-btn-rate{background:#fef9c3;color:#a16207;border:1px solid #fde68a}
.apt-btn-rate:hover{background:#fef08a}
.apt-btn-cancel{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.apt-btn-cancel:hover{background:#fee2e2}

.apt-pager{display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.7rem 1rem;border-top:2px solid var(--avocado-100);background:var(--avocado-50)}
.apt-page-btn{width:32px;height:32px;border-radius:8px;border:2px solid var(--avocado-200);background:white;color:var(--avocado-700);font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
.apt-page-btn:hover:not(:disabled){background:var(--avocado-100);border-color:var(--avocado-400)}
.apt-page-btn:disabled{opacity:.35;cursor:not-allowed}
.apt-page-info{font-size:.78rem;font-weight:600;color:var(--avocado-700);min-width:80px;text-align:center}

.apt-empty{background:white;border:2px solid var(--avocado-100);border-radius:14px;padding:3rem;text-align:center}
.apt-empty i{font-size:2.5rem;color:var(--avocado-300);margin-bottom:1rem;display:block}
.apt-empty h3{font-weight:700;color:var(--avocado-800);margin:0 0 .4rem;font-size:1.1rem}
.apt-empty p{color:var(--text-light);font-size:.9rem;margin:0 0 1.2rem}
.apt-empty a{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;font-weight:700;font-size:.9rem;text-decoration:none;transition:all .3s}
.apt-empty a:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px)}

@media(max-width:768px){
    .apt-thead{display:none}
    .apt-row{grid-template-columns:10px 1fr auto;gap:.6rem;padding:.8rem 1rem}
    .apt-date,.apt-time,.apt-price{display:none}
    .apt-actions{justify-content:flex-start}
}
</style>

<section class="apt-page">
    <div style="max-width:1100px;margin:0 auto;padding:0 1.5rem">
        <div class="apt-head">
            <h1>My <span>Appointments</span></h1>
            <a href="booking.php" class="apt-new"><i class="fas fa-plus"></i> New Booking</a>
        </div>

        <?php if (empty($appointments)): ?>
            <div class="apt-empty">
                <i class="fas fa-calendar-xmark"></i>
                <h3>No appointments found</h3>
                <p>Ready to book your next session?</p>
                <a href="booking.php"><i class="fas fa-calendar-plus"></i> Book Now</a>
            </div>
        <?php else: ?>
            <?php foreach ($displayOrder as $status): ?>
                <?php if (empty($grouped[$status])) continue; ?>
                <?php $meta = $statusMeta[$status]; $items = $grouped[$status]; ?>
                <div class="apt-group">
                    <div class="apt-group-header" onclick="aptToggleGroup(this)">
                        <div class="apt-group-dot" style="background:<?php echo $meta['color']; ?>"></div>
                        <span class="apt-group-title"><?php echo $meta['label']; ?></span>
                        <span class="apt-group-count"><?php echo count($items); ?></span>
                        <i class="fas fa-chevron-down apt-group-toggle"></i>
                    </div>
                    <?php
                    $hasActions = ($status === 'pending' || $status === 'completed');
                    $colsNoAction = '40px 1.3fr .9fr .9fr .8fr .7fr';
                    $colsWithAction = '40px 1.3fr .9fr .9fr .8fr .7fr 100px';
                    $colTemplate = $hasActions ? $colsWithAction : $colsNoAction;
                    $paginated = in_array($status, ['confirmed', 'completed']);
                    $perPage = 5;
                    $totalItems = count($items);
                    $totalPages = $paginated ? max(1, ceil($totalItems / $perPage)) : 1;
                    $groupId = 'grp_' . $status;
                ?>
                <div class="apt-table" data-group="<?php echo $status; ?>">
                        <div class="apt-thead" style="grid-template-columns:<?php echo $colTemplate; ?>">
                            <span></span>
                            <span>Service</span>
                            <span>Staff</span>
                            <span>Date</span>
                            <span>Time</span>
                            <span>Price</span>
                            <?php if ($hasActions): ?>
                            <span style="text-align:right">Actions</span>
                            <?php endif; ?>
                        </div>
                        <div class="apt-tbody" id="<?php echo $groupId; ?>">
                            <?php foreach ($items as $idx => $apt): ?>
                            <div class="apt-row" style="grid-template-columns:<?php echo $colTemplate; ?><?php if ($paginated && $idx >= $perPage): ?>;display:none<?php endif; ?>" data-idx="<?php echo $idx; ?>">
                                <div class="apt-status-dot" style="background:<?php echo $meta['color']; ?>" title="<?php echo $meta['label']; ?>"></div>
                                <div class="apt-svc">
                                    <?php echo htmlspecialchars($apt['service_name']); ?>
                                    <small><?php echo htmlspecialchars($apt['category_name']); ?></small>
                                </div>
                                <div class="apt-staff">
                                    <div class="apt-staff-avatar">
                                        <?php if (!empty($apt['staff_photo'])): ?>
                                            <img src="/nail/assets/uploads/<?php echo htmlspecialchars($apt['staff_photo']); ?>" alt="<?php echo htmlspecialchars($apt['staff_name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="apt-staff-name"><?php echo htmlspecialchars($apt['staff_name']); ?></span>
                                </div>
                                <div class="apt-date"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                                <div class="apt-time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?> — <?php echo date('h:i A', strtotime($apt['end_time'])); ?></div>
                                <div class="apt-price">MMK<?php echo number_format($apt['service_price'], 0); ?></div>
                                <?php if ($hasActions): ?>
                                <div class="apt-actions">
                                    <?php if ($apt['status'] === 'completed' && empty($apt['review_id'])): ?>
                                        <a href="review.php?id=<?php echo $apt['id']; ?>" class="apt-btn apt-btn-rate"><i class="fas fa-star"></i> Rate</a>
                                    <?php endif; ?>
                                    <?php if ($apt['status'] === 'pending'): ?>
                                        <a href="?cancel=<?php echo $apt['id']; ?>" class="apt-btn apt-btn-cancel" onclick="return confirm('Cancel this appointment?')"><i class="fas fa-times"></i> Cancel</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($paginated && $totalPages > 1): ?>
                        <div class="apt-pager">
                            <button class="apt-page-btn" onclick="aptPage('<?php echo $status; ?>',-1)" id="<?php echo $groupId; ?>_prev"><i class="fas fa-chevron-left"></i></button>
                            <span class="apt-page-info" id="<?php echo $groupId; ?>_info">Page 1 of <?php echo $totalPages; ?></span>
                            <button class="apt-page-btn" onclick="aptPage('<?php echo $status; ?>',1)" id="<?php echo $groupId; ?>_next"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script>
var aptPages = {};
var aptPerPage = 5;

function aptInitPages() {
    ['confirmed','completed'].forEach(function(s) {
        var tbody = document.getElementById('grp_' + s);
        if (!tbody) return;
        var rows = tbody.querySelectorAll('.apt-row');
        var total = rows.length;
        var pages = Math.max(1, Math.ceil(total / aptPerPage));
        aptPages[s] = { page: 1, total: pages, totalItems: total };
        aptShowPage(s, 1);
    });
}

function aptShowPage(status, page) {
    var info = aptPages[status];
    if (!info) return;
    info.page = page;
    var tbody = document.getElementById('grp_' + status);
    var rows = tbody.querySelectorAll('.apt-row');
    var start = (page - 1) * aptPerPage;
    var end = start + aptPerPage;
    rows.forEach(function(r, i) {
        r.style.display = (i >= start && i < end) ? '' : 'none';
    });
    var infoEl = document.getElementById('grp_' + status + '_info');
    if (infoEl) infoEl.textContent = 'Page ' + page + ' of ' + info.total;
    var prev = document.getElementById('grp_' + status + '_prev');
    var next = document.getElementById('grp_' + status + '_next');
    if (prev) prev.disabled = (page <= 1);
    if (next) next.disabled = (page >= info.total);
}

function aptPage(status, dir) {
    var info = aptPages[status];
    if (!info) return;
    var newPage = info.page + dir;
    if (newPage < 1 || newPage > info.total) return;
    aptShowPage(status, newPage);
}

function aptToggleGroup(header) {
    var table = header.nextElementSibling;
    var icon = header.querySelector('.apt-group-toggle');
    if (table.style.display === 'none') {
        table.style.display = '';
        icon.classList.remove('collapsed');
    } else {
        table.style.display = 'none';
        icon.classList.add('collapsed');
    }
}

document.addEventListener('DOMContentLoaded', aptInitPages);
</script>

<?php require_once '../includes/footer.php'; ?>
