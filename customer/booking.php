<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Book Appointment';
$error = '';
$success = '';

$stmt = $pdo->query("SHOW TABLES LIKE 'seats'");
if (!$stmt->fetch()) {
    $pdo->exec("CREATE TABLE seats (id INT AUTO_INCREMENT PRIMARY KEY, seat_number INT NOT NULL UNIQUE, label VARCHAR(50) DEFAULT NULL, status ENUM('active','maintenance') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    for ($i = 1; $i <= 5; $i++) $pdo->exec("INSERT INTO seats (seat_number, label) VALUES ($i, 'Seat $i')");
}
$stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
if ($stmt->fetch()) {
    $cols = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'seat_id'");
    if (!$cols->fetch()) $pdo->exec("ALTER TABLE appointments ADD COLUMN seat_id INT DEFAULT NULL AFTER staff_id");
}

$all_seats = $pdo->query("SELECT * FROM seats WHERE status='active' ORDER BY seat_number ASC")->fetchAll();
$total_seats = count($all_seats);

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name, s.name")->fetchAll();
$staff_members = $pdo->query("SELECT * FROM staff WHERE status='available' ORDER BY name")->fetchAll();

$selected_service = $_GET['service'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($service_id) || empty($staff_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Appointment date cannot be in the past.';
    } elseif (strtotime($appointment_date) === strtotime(date('Y-m-d')) && strtotime($appointment_time) <= strtotime('+30 minutes', strtotime(date('H:i:s')))) {
        $error = 'Please select a time at least 30 minutes from now.';
    } else {
        $svc_stmt = $pdo->prepare("SELECT duration, price FROM services WHERE id = ?");
        $svc_stmt->execute([$service_id]);
        $svc = $svc_stmt->fetch();

        if (!$svc) {
            $error = 'Invalid service selected.';
        } else {
            $start_time = $appointment_time;
            $duration_minutes = $svc['duration'];
            $end_time = date('H:i:s', strtotime($start_time) + $duration_minutes * 60);

            $staff_stmt = $pdo->prepare("SELECT working_hours_start, working_hours_end FROM staff WHERE id = ?");
            $staff_stmt->execute([$staff_id]);
            $staff_info = $staff_stmt->fetch();

            if ($staff_info) {
                if ($start_time < $staff_info['working_hours_start']) {
                    $error = 'Staff working hours start at ' . date('h:i A', strtotime($staff_info['working_hours_start'])) . '. Please select a later time.';
                } elseif ($end_time > $staff_info['working_hours_end']) {
                    $error = 'This service ends at ' . date('h:i A', strtotime($end_time)) . ', which is beyond staff working hours (' . date('h:i A', strtotime($staff_info['working_hours_end'])) . '). Please select an earlier time.';
                }
            }

            $duplicate = $pdo->prepare("SELECT id FROM appointments WHERE customer_id = ? AND service_id = ? AND appointment_date = ? AND status NOT IN ('cancelled')");
            $duplicate->execute([$_SESSION['user_id'], $service_id, $appointment_date]);
            if ($duplicate->fetch()) {
                $error = 'You already have an appointment for this service on the selected date.';
            }

            if (!$error) {
                $staff_conflict = $pdo->prepare("SELECT id FROM appointments WHERE staff_id = ? AND appointment_date = ? AND ((appointment_time <= ? AND end_time > ?) OR (appointment_time < ? AND end_time >= ?)) AND status NOT IN ('cancelled')");
                $staff_conflict->execute([$staff_id, $appointment_date, $start_time, $start_time, $end_time, $end_time]);
                if ($staff_conflict->fetch()) {
                    $error = 'This staff member is already booked for the selected time slot.';
                }
            }

            if (!$error) {
                $seat_id = null;
                $seat_conflict_stmt = $pdo->prepare("SELECT seat_id FROM appointments WHERE appointment_date = ? AND status NOT IN ('cancelled') AND ((appointment_time < ? AND end_time > ?) OR (appointment_time < ? AND end_time > ?) OR (appointment_time >= ? AND end_time <= ?))");
                $seat_conflict_stmt->execute([$appointment_date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time]);
                $occupied_seat_ids = array_column($seat_conflict_stmt->fetchAll(), 'seat_id');
                $occupied_seat_ids = array_filter($occupied_seat_ids);

                if (!empty($occupied_seat_ids)) {
                    $avail_seat = $pdo->prepare("SELECT id FROM seats WHERE status='active' AND id NOT IN (" . implode(',', array_fill(0, count($occupied_seat_ids), '?')) . ") ORDER BY seat_number ASC LIMIT 1");
                    $avail_seat->execute($occupied_seat_ids);
                } else {
                    $avail_seat = $pdo->prepare("SELECT id FROM seats WHERE status='active' ORDER BY seat_number ASC LIMIT 1");
                    $avail_seat->execute();
                }
                $seat_row = $avail_seat->fetch();

                if (!$seat_row) {
                    $error = 'Sorry, all seats are currently occupied for this time slot. Please choose a different time.';
                } else {
                    $seat_id = $seat_row['id'];

                    $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, service_id, staff_id, seat_id, appointment_date, appointment_time, end_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$_SESSION['user_id'], $service_id, $staff_id, $seat_id, $appointment_date, $start_time, $end_time, $notes]);
                    $appointment_id = $pdo->lastInsertId();
                    require_once '../includes/notifications.php';
                    notifyAdmins($pdo, 'new_booking', 'New Booking', $_SESSION['full_name'] . ' booked a new appointment.', $appointment_id);
                    $success = 'Appointment booked successfully! We will confirm your booking shortly.';
                }
            }
        }
    }
}

if (isset($_GET['check_availability'])) {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? '';
    $time = $_GET['time'] ?? '';
    $dur = intval($_GET['duration'] ?? 30);
    $stf = intval($_GET['staff_id'] ?? 0);

    if ($date && $time) {
        $et = date('H:i:s', strtotime($time) + $dur * 60);

        $occupied_q = $pdo->prepare("SELECT seat_id FROM appointments WHERE appointment_date = ? AND status NOT IN ('cancelled') AND ((appointment_time < ? AND end_time > ?) OR (appointment_time < ? AND end_time > ?) OR (appointment_time >= ? AND end_time <= ?))");
        $occupied_q->execute([$date, $et, $time, $time, $et, $time, $et]);
        $occupied_ids = array_column($occupied_q->fetchAll(), 'seat_id');

        $avail_q = $pdo->prepare("SELECT id, seat_number, label FROM seats WHERE status='active' AND id NOT IN (" . implode(',', array_fill(0, max(1, count($occupied_ids)), '?')) . ") ORDER BY seat_number ASC");
        $avail_q->execute($occupied_ids ?: [0]);
        $available_seats = $avail_q->fetchAll();

        $stf_ok = true;
        if ($stf) {
            $si = $pdo->prepare("SELECT working_hours_start, working_hours_end FROM staff WHERE id = ?");
            $si->execute([$stf]);
            $si = $si->fetch();
            if ($si && ($time < $si['working_hours_start'] || $et > $si['working_hours_end'])) {
                $stf_ok = false;
            }
        }

        echo json_encode([
            'seats_available' => count($available_seats),
            'seats_total' => $total_seats,
            'seats_occupied' => count($occupied_ids),
            'available_seats' => $available_seats,
            'staff_available' => $stf_ok,
            'end_time' => date('h:i A', strtotime($et))
        ]);
    }
    exit;
}

require_once '../includes/header.php';
?>
</main>

<style>
.bk{background:linear-gradient(180deg,var(--avocado-50) 0%,#f9fafb 100%);min-height:100vh;display:flex;flex-direction:column}

/* Big Header */
.bk-head{text-align:center;padding:1.8rem 1rem 0}
.bk-head h1{font-family:'Playfair Display',serif;font-size:2.2rem;color:var(--avocado-900);margin:0 0 .3rem}
.bk-head h1 span{color:var(--avocado-600)}
.bk-head p{color:var(--text-light);font-size:1rem;margin:0}

/* Big Progress Steps */
.bk-progress{display:flex;align-items:center;justify-content:center;gap:0;padding:1.5rem 1rem 0;max-width:600px;margin:0 auto}
.bk-prog-step{display:flex;align-items:center;gap:.6rem}
.bk-prog-circle{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;border:3px solid var(--avocado-200);background:white;color:var(--avocado-400);transition:all .4s ease;flex-shrink:0}
.bk-prog-step.active .bk-prog-circle{background:var(--avocado-600);border-color:var(--avocado-600);color:white;box-shadow:0 6px 20px rgba(93,132,51,.35);transform:scale(1.08)}
.bk-prog-step.done .bk-prog-circle{background:var(--avocado-100);border-color:var(--avocado-500);color:var(--avocado-600)}
.bk-prog-label{font-size:.9rem;font-weight:700;color:var(--avocado-400);transition:color .3s}
.bk-prog-step.active .bk-prog-label{color:var(--avocado-800)}
.bk-prog-step.done .bk-prog-label{color:var(--avocado-600)}
.bk-prog-line{width:80px;height:3px;background:var(--avocado-200);margin:0 .4rem;border-radius:3px;transition:background .4s}
.bk-prog-line.done{background:var(--avocado-500)}

/* Alert */
.bk-alert{border-radius:14px;padding:.8rem 1.1rem;margin:0 2rem 1rem;font-size:.9rem;display:flex;align-items:center;gap:.5rem}
.bk-alert i{font-size:1.1rem;flex-shrink:0}
.bk-alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
.bk-alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a}

/* Panels */
.bk-panel{display:none;animation:bkFadeIn .4s ease}
.bk-panel.active{display:flex;flex-direction:column;flex:1}
@keyframes bkFadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

.bk-inner{max-width:1100px;width:100%;margin:0 auto;padding:0 2rem;flex:1;display:flex;flex-direction:column}

/* Step 1: full height service table */
#step1{flex:1;display:flex;flex-direction:column}
#step1 .bk-svc-wrap{flex:1;display:flex;flex-direction:column}

/* Category pills */
.bk-cats{display:flex;flex-wrap:wrap;gap:.5rem;margin:1.2rem 0 1rem}
.bk-cat{padding:.5rem 1.2rem;border-radius:50px;border:2px solid var(--avocado-200);background:white;color:var(--avocado-700);font-size:.85rem;font-weight:600;cursor:pointer;transition:all .25s}
.bk-cat:hover{border-color:var(--avocado-400);background:var(--avocado-50)}
.bk-cat.on{background:var(--avocado-600);color:white;border-color:var(--avocado-600)}

/* Service table */
.bk-svc-table{width:100%;flex:1;display:flex;flex-direction:column;border:2px solid var(--avocado-100);border-radius:16px;overflow:hidden;background:white}
.bk-svc-thead{display:grid;grid-template-columns:44px 1fr 100px 100px 110px;padding:.7rem 1rem;background:var(--avocado-50);border-bottom:2px solid var(--avocado-100)}
.bk-svc-thead span{font-size:.72rem;font-weight:700;color:var(--avocado-700);text-transform:uppercase;letter-spacing:.5px}
.bk-svc-tbody{flex:1;overflow-y:auto;max-height:calc(100vh - 340px)}
.bk-svc-row{display:grid;grid-template-columns:44px 1fr 100px 100px 110px;padding:.6rem 1rem;align-items:center;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:all .2s}
.bk-svc-row:last-child{border-bottom:none}
.bk-svc-row:hover{background:var(--avocado-50)}
.bk-svc-row.on{background:var(--avocado-50);border-color:var(--avocado-200)}
.bk-svc-radio{width:22px;height:22px;border-radius:50%;border:2px solid var(--avocado-300);display:flex;align-items:center;justify-content:center;transition:all .2s}
.bk-svc-row.on .bk-svc-radio{border-color:var(--avocado-600);background:var(--avocado-600)}
.bk-svc-row.on .bk-svc-radio::after{content:'\f00c';font-family:'Font Awesome 6 Free';font-weight:900;font-size:.6rem;color:white}
.bk-svc-name{font-weight:600;color:var(--dark);font-size:.9rem}
.bk-svc-name small{display:block;font-weight:400;color:var(--text-light);font-size:.72rem;margin-top:.1rem}
.bk-svc-price{font-weight:700;color:var(--avocado-600);font-size:.9rem}
.bk-svc-dur{font-size:.8rem;color:var(--text-light);display:flex;align-items:center;gap:.3rem}
.bk-svc-dur i{color:var(--avocado-500)}
.bk-svc-select{background:var(--avocado-600);color:white;border:none;padding:.45rem 1rem;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .2s;text-align:center}
.bk-svc-select:hover{background:var(--avocado-700)}
.bk-svc-row.on .bk-svc-select{background:var(--avocado-100);color:var(--avocado-700)}

.bk-actions{display:flex;justify-content:space-between;align-items:center;padding:1rem 0}
.bk-btn{padding:.7rem 1.8rem;border-radius:12px;font-weight:700;font-size:.95rem;cursor:pointer;transition:all .3s;border:none;display:inline-flex;align-items:center;gap:.5rem;font-family:'Inter',sans-serif}
.bk-btn-next{background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;box-shadow:0 4px 16px rgba(93,132,51,.25)}
.bk-btn-next:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px);box-shadow:0 8px 24px rgba(93,132,51,.3)}
.bk-btn-back{background:white;color:var(--avocado-700);border:2px solid var(--avocado-200)}
.bk-btn-back:hover{background:var(--avocado-50);border-color:var(--avocado-400)}
.bk-btn-confirm{background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;font-size:1rem;padding:.8rem 2.5rem;box-shadow:0 4px 16px rgba(93,132,51,.25)}
.bk-btn-confirm:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px);box-shadow:0 8px 24px rgba(93,132,51,.3)}

/* Step 2 */
.bk-card2{background:white;border-radius:20px;padding:2rem;box-shadow:0 2px 20px rgba(0,0,0,.04);margin-top:1rem}
.bk-card2-title{margin-bottom:1.5rem}
.bk-card2-title h2{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--avocado-900);margin:0 0 .25rem}
.bk-card2-title p{color:var(--text-light);font-size:.9rem;margin:0}

.bk-field-row{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
.bk-field label{display:block;font-weight:700;color:var(--dark);margin-bottom:.4rem;font-size:.9rem}
.bk-field label .bk-opt{font-weight:400;color:var(--text-light);font-size:.8rem}
.bk-input{width:100%;padding:.75rem 1rem;border:2px solid var(--avocado-100);border-radius:12px;font-size:.9rem;outline:none;transition:border-color .3s;font-family:'Inter',sans-serif;background:white}
.bk-input:focus{border-color:var(--avocado-400)}
textarea.bk-input{resize:vertical;min-height:70px}

.bk-avail{padding:.8rem 1.2rem;border-radius:12px;font-size:.88rem;margin-bottom:1.2rem;display:flex;align-items:center;gap:.6rem}
.bk-avail i{font-size:1.1rem;flex-shrink:0}
.bk-avail-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.bk-avail-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.bk-avail-bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}

.bk-staff-label{font-weight:700;color:var(--dark);font-size:.9rem;margin-bottom:.7rem}
.bk-staff-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.9rem;margin-bottom:1.2rem}
.bk-staff{border:2px solid var(--avocado-100);border-radius:16px;cursor:pointer;transition:all .3s;text-align:center;position:relative;overflow:hidden}
.bk-staff:hover{border-color:var(--avocado-300);box-shadow:0 4px 14px rgba(61,79,42,.07)}
.bk-staff.on{border-color:var(--avocado-500);background:var(--avocado-50);box-shadow:0 0 0 3px rgba(124,179,66,.15)}
.bk-staff.on::after{content:'\f00c';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;top:.5rem;right:.5rem;width:24px;height:24px;background:var(--avocado-600);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;z-index:1}
.bk-staff-inner{padding:1.2rem .8rem}
.bk-staff-avatar{width:60px;height:60px;border-radius:50%;background:var(--avocado-100);margin:0 auto .6rem;display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid var(--avocado-200);transition:border-color .3s}
.bk-staff.on .bk-staff-avatar{border-color:var(--avocado-500)}
.bk-staff-avatar img{width:100%;height:100%;object-fit:cover}
.bk-staff-avatar i{color:var(--avocado-500);font-size:1.3rem}
.bk-staff h4{font-weight:700;color:var(--dark);margin:0 0 .15rem;font-size:.9rem}
.bk-staff p{font-size:.75rem;color:var(--text-light);margin:0 0 .35rem}
.bk-staff-hours{font-size:.7rem;color:var(--avocado-500);display:flex;align-items:center;justify-content:center;gap:.25rem}

/* Step 3 */
.bk-confirm{background:linear-gradient(135deg,var(--avocado-50),#f4fae6);border-radius:18px;padding:1.8rem;margin:1rem 0;border:1px solid var(--avocado-200)}
.bk-confirm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem}
.bk-confirm-item{background:white;border-radius:14px;padding:1rem 1.2rem}
.bk-confirm-label{font-size:.72rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:.25rem}
.bk-confirm-val{font-weight:700;color:var(--dark);font-size:1rem}
.bk-confirm-val.bk-price{color:var(--avocado-600);font-size:1.2rem;font-weight:800}
.bk-confirm-note{background:white;border:1px solid #fde68a;border-radius:12px;padding:.8rem 1.2rem;font-size:.88rem;color:#92400e;display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem}
.bk-confirm-note i{flex-shrink:0}

@media(max-width:768px){
    .bk-head h1{font-size:1.6rem}
    .bk-inner{padding:0 1rem}
    .bk-prog-circle{width:40px;height:40px;font-size:.9rem}
    .bk-prog-label{font-size:.78rem}
    .bk-prog-line{width:40px}
    .bk-svc-thead{grid-template-columns:36px 1fr 80px 70px;display:none}
    .bk-svc-row{grid-template-columns:36px 1fr auto;gap:.5rem;padding:.8rem 1rem}
    .bk-svc-dur,.bk-svc-select{display:none}
    .bk-field-row{grid-template-columns:1fr}
    .bk-staff-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}
    .bk-confirm-grid{grid-template-columns:1fr 1fr}
}
</style>

<section class="bk">
    <div class="bk-inner">
        <div class="bk-head">
            <h1>Book Your <span>Appointment</span></h1>
            <p>Select a service, pick your preferred date and time, and we'll handle the rest.</p>
        </div>

        <div class="bk-progress">
            <div class="bk-prog-step active" id="progStep1">
                <div class="bk-prog-circle">1</div>
                <span class="bk-prog-label">Choose Service</span>
            </div>
            <div class="bk-prog-line" id="progLine1"></div>
            <div class="bk-prog-step" id="progStep2">
                <div class="bk-prog-circle">2</div>
                <span class="bk-prog-label">Schedule</span>
            </div>
            <div class="bk-prog-line" id="progLine2"></div>
            <div class="bk-prog-step" id="progStep3">
                <div class="bk-prog-circle">3</div>
                <span class="bk-prog-label">Confirm</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bk-alert bk-alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bk-alert bk-alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" id="bookingForm" style="flex:1;display:flex;flex-direction:column">

            <!-- STEP 1: Service Table -->
            <div id="step1" class="bk-panel active">
                <div class="bk-svc-wrap">
                    <div class="bk-cats">
                        <button type="button" class="bk-cat on" onclick="bkFilterCat('all',this)">All Services</button>
                        <?php foreach ($categories as $cat): ?>
                        <button type="button" class="bk-cat" onclick="bkFilterCat('<?php echo htmlspecialchars($cat['name']); ?>',this)"><?php echo htmlspecialchars($cat['name']); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="bk-svc-table">
                        <div class="bk-svc-thead">
                            <span></span>
                            <span>Service</span>
                            <span>Category</span>
                            <span>Duration</span>
                            <span style="text-align:right">Price</span>
                        </div>
                        <div id="serviceList" class="bk-svc-tbody">
                            <?php foreach ($services as $svc): ?>
                            <label class="bk-svc-row <?php echo $selected_service == $svc['id'] ? 'on' : ''; ?>" data-cat="<?php echo htmlspecialchars($svc['category_name']); ?>">
                                <input type="radio" name="service_id" value="<?php echo $svc['id']; ?>" style="display:none;" data-duration="<?php echo $svc['duration']; ?>" data-price="<?php echo $svc['price']; ?>" data-name="<?php echo htmlspecialchars($svc['name']); ?>" <?php echo $selected_service == $svc['id'] ? 'checked' : ''; ?> onchange="bkSelectSvc(this)">
                                <div class="bk-svc-radio"></div>
                                <div class="bk-svc-name"><?php echo htmlspecialchars($svc['name']); ?><?php if (!empty($svc['description'])): ?><small><?php echo htmlspecialchars(mb_strimwidth($svc['description'],0,60,'...')); ?></small><?php endif; ?></div>
                                <div style="font-size:.78rem;color:var(--text-light)"><?php echo htmlspecialchars($svc['category_name']); ?></div>
                                <div class="bk-svc-dur"><i class="fas fa-clock"></i> <?php echo $svc['duration']; ?> min</div>
                                <div class="bk-svc-price" style="text-align:right">MMK<?php echo number_format($svc['price'], 0); ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bk-actions">
                        <div></div>
                        <button type="button" class="bk-btn bk-btn-next" onclick="bkGo(2)">Continue <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Schedule -->
            <div id="step2" class="bk-panel">
                <div class="bk-card2">
                    <div class="bk-card2-title">
                        <h2>Pick Date, Time & Staff</h2>
                        <p>Choose when you'd like to come in and who you'd like to see.</p>
                    </div>

                    <div class="bk-field-row">
                        <div class="bk-field">
                            <label><i class="fas fa-calendar-alt" style="color:var(--avocado-500);margin-right:.3rem"></i> Date</label>
                            <input type="date" name="appointment_date" id="bookingDate" min="<?php echo date('Y-m-d'); ?>" required onchange="bkOnSchedule()" class="bk-input">
                        </div>
                        <div class="bk-field">
                            <label><i class="fas fa-clock" style="color:var(--avocado-500);margin-right:.3rem"></i> Time</label>
                            <select name="appointment_time" id="bookingTime" required onchange="bkOnSchedule()" class="bk-input">
                                <option value="">Select time</option>
                                <?php for ($h = 9; $h <= 17; $h++): ?>
                                    <option value="<?php echo sprintf('%02d', $h); ?>:00:00"><?php echo date('h:i A', strtotime(sprintf('%02d:00', $h))); ?></option>
                                    <?php if ($h < 17): ?>
                                    <option value="<?php echo sprintf('%02d', $h); ?>:30:00"><?php echo date('h:i A', strtotime(sprintf('%02d:30', $h))); ?></option>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div id="availNotice" class="bk-avail" style="display:none;"></div>

                    <div class="bk-staff-label"><i class="fas fa-users" style="color:var(--avocado-500);margin-right:.3rem"></i> Staff Member</div>
                    <div id="staffList" class="bk-staff-grid">
                        <?php foreach ($staff_members as $s): ?>
                        <label class="bk-staff">
                            <input type="radio" name="staff_id" value="<?php echo $s['id']; ?>" style="display:none;" data-hours-start="<?php echo $s['working_hours_start']; ?>" data-hours-end="<?php echo $s['working_hours_end']; ?>" onchange="bkSelectStaff(this)">
                            <div class="bk-staff-inner">
                                <div class="bk-staff-avatar">
                                    <?php if ($s['photo']): ?>
                                    <img src="/nail/assets/uploads/<?php echo htmlspecialchars($s['photo']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>">
                                    <?php else: ?>
                                    <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <h4><?php echo htmlspecialchars($s['name']); ?></h4>
                                <p><?php echo htmlspecialchars($s['specialization'] ?? 'Nail Artist'); ?></p>
                                <span class="bk-staff-hours"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($s['working_hours_start'])); ?> - <?php echo date('h:i A', strtotime($s['working_hours_end'])); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="bk-field">
                        <label>Notes <span class="bk-opt">(Optional)</span></label>
                        <textarea name="notes" rows="2" placeholder="Any special requests or preferences..." class="bk-input"></textarea>
                    </div>

                    <div class="bk-actions">
                        <button type="button" class="bk-btn bk-btn-back" onclick="bkGo(1)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="bk-btn bk-btn-next" onclick="bkGo(3)">Continue <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Confirm -->
            <div id="step3" class="bk-panel">
                <div class="bk-card2">
                    <div class="bk-card2-title">
                        <h2>Confirm Your Booking</h2>
                        <p>Review your details before confirming.</p>
                    </div>

                    <div class="bk-confirm">
                        <div class="bk-confirm-grid">
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-hand-sparkles" style="margin-right:.3rem"></i>Service</div>
                                <div class="bk-confirm-val" id="sumSvc"></div>
                            </div>
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-user" style="margin-right:.3rem"></i>Staff</div>
                                <div class="bk-confirm-val" id="sumStaff"></div>
                            </div>
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-calendar" style="margin-right:.3rem"></i>Date</div>
                                <div class="bk-confirm-val" id="sumDate"></div>
                            </div>
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-clock" style="margin-right:.3rem"></i>Time</div>
                                <div class="bk-confirm-val" id="sumTime"></div>
                            </div>
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-hourglass-half" style="margin-right:.3rem"></i>Duration</div>
                                <div class="bk-confirm-val" id="sumDur"></div>
                            </div>
                            <div class="bk-confirm-item">
                                <div class="bk-confirm-label"><i class="fas fa-tag" style="margin-right:.3rem"></i>Total Price</div>
                                <div class="bk-confirm-val bk-price" id="sumPrice"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bk-confirm-note">
                        <i class="fas fa-info-circle"></i> Your appointment will be submitted as <strong>pending</strong>. We will confirm it shortly. Seat will be auto-assigned for you.
                    </div>

                    <div class="bk-actions">
                        <button type="button" class="bk-btn bk-btn-back" onclick="bkGo(2)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="submit" class="bk-btn bk-btn-confirm"><i class="fas fa-calendar-check"></i> Confirm Booking</button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</section>

<script>
var bkStep = 1;

function bkGo(step) {
    if (step > bkStep) {
        if (bkStep === 1 && !document.querySelector('input[name="service_id"]:checked')) {
            Swal.fire({icon:'warning',title:'Select a Service',text:'Please choose a service to continue.',confirmButtonColor:'#7cb342'});
            return;
        }
        if (bkStep === 2) {
            var d = document.querySelector('[name="appointment_date"]').value;
            var t = document.querySelector('[name="appointment_time"]').value;
            var st = document.querySelector('input[name="staff_id"]:checked');
            if (!d || !t) { Swal.fire({icon:'warning',title:'Select Date & Time',text:'Please choose both date and time.',confirmButtonColor:'#7cb342'}); return; }
            if (!st) { Swal.fire({icon:'warning',title:'Select Staff',text:'Please choose a staff member.',confirmButtonColor:'#7cb342'}); return; }
            bkUpdateSummary();
        }
    }
    document.querySelectorAll('.bk-panel').forEach(function(p){p.classList.remove('active')});
    document.getElementById('step'+step).classList.add('active');
    for (var i = 1; i <= 3; i++) {
        var ps = document.getElementById('progStep'+i);
        ps.classList.remove('active','done');
        if (i === step) ps.classList.add('active');
        else if (i < step) ps.classList.add('done');
    }
    for (var i = 1; i <= 2; i++) {
        var ln = document.getElementById('progLine'+i);
        ln.classList.toggle('done', i < step);
    }
    bkStep = step;
    window.scrollTo({top:0,behavior:'smooth'});
}

function bkFilterCat(cat, btn) {
    document.querySelectorAll('.bk-cat').forEach(function(b){b.classList.remove('on')});
    btn.classList.add('on');
    document.querySelectorAll('.bk-svc-row').forEach(function(c){
        c.style.display = (cat==='all' || c.dataset.cat===cat) ? '' : 'none';
    });
}

function bkSelectSvc(r) {
    document.querySelectorAll('.bk-svc-row').forEach(function(c){c.classList.remove('on')});
    r.closest('.bk-svc-row').classList.add('on');
}

function bkSelectStaff(r) {
    document.querySelectorAll('.bk-staff').forEach(function(c){c.classList.remove('on')});
    r.closest('.bk-staff').classList.add('on');
}

function bkUpdateSummary() {
    var svc = document.querySelector('input[name="service_id"]:checked');
    var stf = document.querySelector('input[name="staff_id"]:checked');
    var date = document.querySelector('[name="appointment_date"]').value;
    var time = document.querySelector('[name="appointment_time"]').value;
    if (svc) {
        document.getElementById('sumSvc').textContent = svc.dataset.name;
        document.getElementById('sumPrice').textContent = 'MMK' + parseFloat(svc.dataset.price).toLocaleString('en',{minimumFractionDigits:2});
        document.getElementById('sumDur').textContent = svc.dataset.duration + ' minutes';
    }
    if (stf) {
        document.getElementById('sumStaff').textContent = stf.closest('.bk-staff').querySelector('h4').textContent;
    }
    if (date) {
        var d = new Date(date+'T00:00:00');
        document.getElementById('sumDate').textContent = d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
    }
    if (time) {
        var p = time.split(':');
        var h = parseInt(p[0]);
        document.getElementById('sumTime').textContent = (h%12||12)+':'+p[1]+' '+(h>=12?'PM':'AM');
    }
}

function bkUpdateTimeSlots() {
    var date = document.querySelector('[name="appointment_date"]').value;
    var sel = document.getElementById('bookingTime');
    var today = new Date().toISOString().split('T')[0];
    var now = new Date();
    sel.querySelectorAll('option[value]').forEach(function(o){
        if(!o.value) return;
        if(date===today){
            var p=o.value.split(':');
            var st=new Date();st.setHours(parseInt(p[0]),parseInt(p[1]),0,0);
            var mn=new Date(now.getTime()+30*60000);
            o.disabled=st<=mn; o.style.color=st<=mn?'#ccc':'';
        } else { o.disabled=false; o.style.color=''; }
    });
    if(date===today && sel.value){
        var p=sel.value.split(':');var st=new Date();st.setHours(parseInt(p[0]),parseInt(p[1]),0,0);
        if(st<=new Date(now.getTime()+30*60000)) sel.value='';
    }
}

function bkOnSchedule() {
    bkUpdateTimeSlots();
    var date = document.querySelector('[name="appointment_date"]').value;
    var time = document.querySelector('[name="appointment_time"]').value;
    var svc = document.querySelector('input[name="service_id"]:checked');
    var stf = document.querySelector('input[name="staff_id"]:checked');
    var notice = document.getElementById('availNotice');
    if (!date||!time||!svc) { notice.style.display='none'; return; }
    var dur = parseInt(svc.dataset.duration)||30;
    var sid = stf?stf.value:'';
    fetch('booking.php?check_availability=1&date='+date+'&time='+time+'&duration='+dur+'&staff_id='+sid)
    .then(function(r){return r.json();}).then(function(data){
        notice.style.display='flex';
        var sa=data.seats_available, st=data.seats_total;
        if(sa<=0){notice.className='bk-avail bk-avail-bad';notice.innerHTML='<i class="fas fa-times-circle"></i><span><strong>No seats available</strong> &mdash; All '+st+' seats are occupied for this time slot.</span>';}
        else if(sa<=2){notice.className='bk-avail bk-avail-warn';notice.innerHTML='<i class="fas fa-exclamation-triangle"></i><span><strong>'+sa+' seat'+(sa>1?'s':'')+' left</strong> &mdash; Limited availability. Book soon!</span>';}
        else{notice.className='bk-avail bk-avail-ok';notice.innerHTML='<i class="fas fa-check-circle"></i><span><strong>'+sa+' seats available</strong> &mdash; Good to go until '+data.end_time+'.</span>';}
        if(!data.staff_available&&sid) notice.innerHTML+='<br><small style="color:#b45309;">This staff member may not be available during this time.</small>';
    }).catch(function(){notice.style.display='none';});
}
</script>

<main class="max-w-7xl mx-auto px-4 py-6">
<?php require_once '../includes/footer.php'; ?>
