<?php
require_once __DIR__ . '/notifications.php';

$stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
if (!$stmt->fetch()) {
    $pdo->exec("CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        appointment_id INT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id, is_read)");
}

$unread_count = isLoggedIn() ? getUnreadCount($pdo, $_SESSION['user_id']) : 0;
$recent_notifs = isLoggedIn() ? getRecentNotifications($pdo, $_SESSION['user_id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avocado Nail - <?php echo $title ?? 'Dashboard'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/nail/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function toggleNotifDropdown(id) {
        var dd = document.getElementById(id);
        dd.classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        document.querySelectorAll('[id$="NotifDropdown"]').forEach(function(dd) {
            if (!dd.classList.contains('hidden') && !dd.contains(e.target) && !e.target.closest('[onclick*="' + dd.id + '"]') && !e.target.closest('[data-notif-bell]')) {
                dd.classList.add('hidden');
            }
        });
    });
    var _lastUnreadCount = <?php echo $unread_count; ?>;
    function pollNotifications() {
        fetch('/nail/includes/notifications_api.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) return;
                var badges = document.querySelectorAll('.notif-badge-count');
                badges.forEach(function(b) {
                    if (data.unread_count > 0) {
                        b.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        b.style.display = 'flex';
                    } else {
                        b.style.display = 'none';
                    }
                });
                if (data.unread_count > _lastUnreadCount && _lastUnreadCount >= 0) {
                    var latest = data.notifications[0];
                    if (latest && !latest.is_read) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: latest.title,
                            text: latest.message,
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }
                }
                _lastUnreadCount = data.unread_count;
                var bodies = document.querySelectorAll('.notif-dropdown-body');
                bodies.forEach(function(body) {
                    if (data.notifications.length === 0) {
                        body.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">No notifications yet</p>';
                        return;
                    }
                    var isAdmin = !!document.getElementById('adminSidebar');
                var role = isAdmin ? 'admin' : 'customer';
                    var html = '';
                    data.notifications.forEach(function(n) {
                        html += '<a href="/nail/' + role + '/notifications.php' + (n.appointment_id ? '?appointment_id=' + n.appointment_id : '') + '" class="notif-item ' + (n.is_read ? '' : 'notif-unread') + '">';
                        html += '<p class="notif-title">' + n.title + '</p>';
                        html += '<p class="notif-msg">' + n.message + '</p>';
                        html += '<p class="notif-time">' + n.created_at + '</p>';
                        html += '</a>';
                    });
                    body.innerHTML = html;
                });
            })
            .catch(function() {});
    }
    setInterval(pollNotifications, 30000);
    </script>
</head>
<body>

<?php if (isAdmin()): ?>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <a href="/nail/admin/dashboard.php">
                <i class="fas fa-leaf" style="color:#7cb342;font-size:1.3rem"></i>
                <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:1.15rem;color:#4d6a35">Avocado Nail</span>
            </a>
            <button id="sidebarClose" class="admin-sidebar-close"><i class="fas fa-times"></i></button>
        </div>
        <nav class="admin-sidebar-nav">
            <a href="/nail/admin/dashboard.php" class="admin-sidebar-link <?php echo $currentPage==='dashboard.php'?'active':''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="/nail/admin/categories.php" class="admin-sidebar-link <?php echo $currentPage==='categories.php'?'active':''; ?>"><i class="fas fa-tags"></i><span>Categories</span></a>
            <a href="/nail/admin/services.php" class="admin-sidebar-link <?php echo $currentPage==='services.php'?'active':''; ?>"><i class="fas fa-hand-sparkles"></i><span>Services</span></a>
            <a href="/nail/admin/staff.php" class="admin-sidebar-link <?php echo $currentPage==='staff.php'?'active':''; ?>"><i class="fas fa-users"></i><span>Staff</span></a>
            <a href="/nail/admin/appointments.php" class="admin-sidebar-link <?php echo $currentPage==='appointments.php'?'active':''; ?>"><i class="fas fa-calendar-check"></i><span>Appointments</span></a>
            <a href="/nail/admin/customers.php" class="admin-sidebar-link <?php echo $currentPage==='customers.php'?'active':''; ?>"><i class="fas fa-user-friends"></i><span>Customers</span></a>
            <a href="/nail/admin/reports.php" class="admin-sidebar-link <?php echo $currentPage==='reports.php'?'active':''; ?>"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
            <a href="/nail/admin/seats.php" class="admin-sidebar-link <?php echo $currentPage==='seats.php'?'active':''; ?>"><i class="fas fa-chair"></i><span>Seats</span></a>
            <a href="javascript:void(0)" onclick="toggleNotifDropdown('adminNotifDropdown')" class="admin-sidebar-link <?php echo $currentPage==='notifications.php'?'active':''; ?>" data-notif-bell><i class="fas fa-bell"></i><span>Notifications</span><span class="admin-sidebar-badge notif-badge-count" style="<?php echo $unread_count<=0?'display:none;':''; ?>"><?php echo $unread_count>9?'9+':$unread_count; ?></span></a>
            <div class="admin-notif-dropdown hidden" id="adminNotifDropdown">
                <div class="notif-dropdown-header">
                    <span>Notifications</span>
                    <?php if ($unread_count > 0): ?>
                        <a href="/nail/admin/notifications.php?mark_read=all" class="text-xs text-emerald-600">Mark all read</a>
                    <?php endif; ?>
                </div>
                <div class="notif-dropdown-body">
                    <?php if (empty($recent_notifs)): ?>
                        <p class="text-xs text-gray-400 text-center py-6">No notifications yet</p>
                    <?php else: ?>
                        <?php foreach ($recent_notifs as $n): ?>
                            <a href="/nail/admin/notifications.php<?php echo $n['appointment_id'] ? '?appointment_id=' . $n['appointment_id'] : ''; ?>" class="notif-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>">
                                <p class="notif-title"><?php echo htmlspecialchars($n['title']); ?></p>
                                <p class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></p>
                                <p class="notif-time"><?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="/nail/admin/notifications.php" class="notif-view-all">View All</a>
            </div>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-user">
                <?php if(!empty($_SESSION['image'])): ?>
                    <img src="/nail/assets/images/<?php echo htmlspecialchars($_SESSION['image']); ?>" class="admin-sidebar-avatar">
                <?php else: ?>
                    <i class="fas fa-user-circle admin-sidebar-avatar-icon"></i>
                <?php endif; ?>
                <span class="admin-sidebar-username"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
            </div>
            <a href="/nail/auth/logout.php" class="admin-sidebar-link admin-sidebar-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    <div class="admin-overlay" id="adminOverlay">    </div>
    <button id="adminMobileToggle" class="admin-mobile-toggle"><i class="fas fa-bars"></i></button>
    <script>
    document.getElementById('adminOverlay')?.addEventListener('click',function(){
        document.getElementById('adminSidebar').classList.remove('open');
        document.getElementById('adminOverlay').classList.remove('show');
    });
    document.getElementById('adminMobileToggle')?.addEventListener('click',function(){
        document.getElementById('adminSidebar').classList.add('open');
        document.getElementById('adminOverlay').classList.add('show');
    });
    document.getElementById('sidebarClose')?.addEventListener('click',function(){
        document.getElementById('adminSidebar').classList.remove('open');
        document.getElementById('adminOverlay').classList.remove('show');
    });
    </script>
    <main class="admin-content">
<?php else: ?>
<nav class="navbar" id="navbar">
    <div class="container">
        <a href="/nail/customer/dashboard.php" class="logo">
            <i class="fas fa-leaf"></i> Avocado Nail
        </a>
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="nav-links" id="navLinks">
            <li><a href="/nail/customer/dashboard.php">Home</a></li>
            <li><a href="/nail/customer/services.php">Services</a></li>
            <li><a href="/nail/customer/staff.php">Our Team</a></li>
            <li><a href="/nail/customer/booking.php" class="btn-nav">Book Now</a></li>
            <li><a href="/nail/customer/appointments.php">My Appointments</a></li>
            <li><a href="/nail/customer/review.php">Reviews</a></li>
            <li class="nav-notif-item">
                <a href="javascript:void(0)" onclick="toggleNotifDropdown('customerNotifDropdown')" class="nav-notif-btn" data-notif-bell>
                    <i class="fas fa-bell"></i>
                    <span class="nav-notif-badge notif-badge-count" style="<?php echo $unread_count<=0?'display:none;':''; ?>"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                </a>
                <div class="notif-dropdown hidden" id="customerNotifDropdown">
                    <div class="notif-dropdown-header">
                        <span>Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <a href="/nail/customer/notifications.php?mark_read=all" class="text-xs text-emerald-600">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <div class="notif-dropdown-body">
                        <?php if (empty($recent_notifs)): ?>
                            <p class="text-xs text-gray-400 text-center py-6">No notifications yet</p>
                        <?php else: ?>
                            <?php foreach ($recent_notifs as $n): ?>
                                <a href="/nail/customer/notifications.php<?php echo $n['appointment_id'] ? '?appointment_id=' . $n['appointment_id'] : ''; ?>" class="notif-item <?php echo $n['is_read'] ? '' : 'notif-unread'; ?>">
                                    <p class="notif-title"><?php echo htmlspecialchars($n['title']); ?></p>
                                    <p class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <p class="notif-time"><?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></p>
                                </a>
                            <?php endforeach; ?> 
                        <?php endif; ?>
                    </div>
                    <a href="/nail/customer/notifications.php" class="notif-view-all">View All</a>
                </div>
            </li>
            <li class="nav-user-item">
                <a href="javascript:void(0)" onclick="toggleUserDropdown()" class="nav-user-btn">
                    <?php if (!empty($_SESSION['image'])): ?>
                        <img src="/nail/assets/images/<?php echo htmlspecialchars($_SESSION['image']); ?>" class="w-7 h-7 rounded-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'User')[0]); ?></span>
                    <i class="fas fa-chevron-down" style="font-size:0.7rem;margin-left:0.3rem;"></i>
                </a>
                <div class="user-dropdown" id="userDropdown">
                    <a href="/nail/customer/profile.php"><i class="fas fa-user-edit"></i> Profile</a>
                    <a href="/nail/auth/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                    <hr>
                    <a href="/nail/auth/logout.php" style="color:#dc2626;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>
<div class="nav-js">
<script>
function toggleUserDropdown() {
    const dd = document.getElementById('userDropdown');
    dd.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const btn = document.querySelector('.nav-user-btn');
    const dd = document.getElementById('userDropdown');
    if (dd && btn && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.classList.remove('show');
    }
});

const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    });
}

navLinks.querySelectorAll('a:not(.nav-user-btn)').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('active');
        menuToggle.querySelector('i')?.classList.add('fa-bars');
        menuToggle.querySelector('i')?.classList.remove('fa-times');
    });
});
</script>
</div>
<main class="max-w-7xl mx-auto px-4 py-6 pt-20">
<?php endif; ?>
