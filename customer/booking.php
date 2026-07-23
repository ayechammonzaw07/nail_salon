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

<section class="booking-hero">
    <div class="container" style="max-width:860px;margin:0 auto;">
        <div class="booking-hero-text">
            <h1>Book Your Appointment</h1>
            <p>Select a service, pick your preferred date and time, and we'll handle the rest.</p>
        </div>

        <div class="booking-steps-indicator">
            <div id="step1Dot" class="bstep active">
                <span class="bstep-num">1</span>
                <span class="bstep-label">Service</span>
            </div>
            <div class="bstep-arrow"><i class="fas fa-chevron-right"></i></div>
            <div id="step2Dot" class="bstep">
                <span class="bstep-num">2</span>
                <span class="bstep-label">Schedule</span>
            </div>
            <div class="bstep-arrow"><i class="fas fa-chevron-right"></i></div>
            <div id="step3Dot" class="bstep">
                <span class="bstep-num">3</span>
                <span class="bstep-label">Confirm</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="booking-alert booking-alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="booking-alert booking-alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="bookingForm">

            <!-- STEP 1: Service -->
            <div id="step1" class="booking-panel active">
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h2>Choose a Service</h2>
                        <p>What treatment would you like today?</p>
                    </div>

                    <div class="booking-categories">
                        <button type="button" class="cat-pill active" onclick="filterCategory('all', this)">All</button>
                        <?php foreach ($categories as $cat): ?>
                        <button type="button" class="cat-pill" onclick="filterCategory('<?php echo htmlspecialchars($cat['name']); ?>', this)"><?php echo htmlspecialchars($cat['name']); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <div id="serviceList" class="booking-service-grid">
                        <?php foreach ($services as $svc): ?>
                        <label class="service-card-select <?php echo $selected_service == $svc['id'] ? 'selected' : ''; ?>" data-category="<?php echo htmlspecialchars($svc['category_name']); ?>">
                            <input type="radio" name="service_id" value="<?php echo $svc['id']; ?>" style="display:none;" data-duration="<?php echo $svc['duration']; ?>" data-price="<?php echo $svc['price']; ?>" <?php echo $selected_service == $svc['id'] ? 'checked' : ''; ?> onchange="selectService(this)">
                            <div class="service-card-select-inner">
                                <div class="service-card-select-top">
                                    <div class="service-card-select-icon">
                                        <i class="fas fa-hand-sparkles"></i>
                                    </div>
                                    <span class="service-card-select-badge"><?php echo htmlspecialchars($svc['category_name']); ?></span>
                                </div>
                                <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
                                <p class="service-card-select-desc"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                                <div class="service-card-select-footer">
                                    <span class="service-card-select-price">MMK<?php echo number_format($svc['price'], 2); ?></span>
                                    <span class="service-card-select-dur"><i class="fas fa-clock"></i> <?php echo $svc['duration']; ?> min</span>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="booking-card-actions">
                        <div></div>
                        <button type="button" class="bbtn bbtn-next" onclick="goToStep(2)">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Schedule -->
            <div id="step2" class="booking-panel">
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h2>Pick Date, Time & Staff</h2>
                        <p>Choose when you'd like to come in and who you'd like to see.</p>
                    </div>

                    <div class="schedule-row">
                        <div class="schedule-field">
                            <label>Date</label>
                            <input type="date" name="appointment_date" id="bookingDate" min="<?php echo date('Y-m-d'); ?>" required onchange="onScheduleChange()" class="binput">
                        </div>
                        <div class="schedule-field">
                            <label>Time</label>
                            <select name="appointment_time" id="bookingTime" required onchange="onScheduleChange()" class="binput">
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

                    <div id="availabilityNotice" class="availability-notice" style="display:none;"></div>

                    <div class="schedule-staff-section">
                        <label class="schedule-staff-label">Staff Member</label>
                        <div id="staffList" class="booking-staff-grid">
                            <?php foreach ($staff_members as $s): ?>
                            <label class="staff-card-select">
                                <input type="radio" name="staff_id" value="<?php echo $s['id']; ?>" style="display:none;" data-hours-start="<?php echo $s['working_hours_start']; ?>" data-hours-end="<?php echo $s['working_hours_end']; ?>" onchange="selectStaff(this)">
                                <div class="staff-card-select-inner">
                                    <div class="staff-card-select-avatar">
                                        <?php if ($s['photo']): ?>
                                        <img src="/nail/assets/uploads/<?php echo htmlspecialchars($s['photo']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>">
                                        <?php else: ?>
                                        <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h4><?php echo htmlspecialchars($s['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($s['specialization'] ?? 'Nail Artist'); ?></p>
                                    <span class="staff-card-select-hours"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($s['working_hours_start'])); ?> - <?php echo date('h:i A', strtotime($s['working_hours_end'])); ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="booking-field">
                        <label>Notes <span class="optional">(Optional)</span></label>
                        <textarea name="notes" rows="2" placeholder="Any special requests or preferences..." class="binput btextarea"></textarea>
                    </div>

                    <div class="booking-card-actions">
                        <button type="button" class="bbtn bbtn-back" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="button" class="bbtn bbtn-next" onclick="goToStep(3)">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Confirm -->
            <div id="step3" class="booking-panel">
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h2>Confirm Your Booking</h2>
                        <p>Review your details before confirming.</p>
                    </div>

                    <div class="confirm-summary">
                        <div class="confirm-row">
                            <div class="confirm-item">
                                <span class="confirm-label">Service</span>
                                <span class="confirm-value" id="summaryService"></span>
                            </div>
                            <div class="confirm-item">
                                <span class="confirm-label">Staff</span>
                                <span class="confirm-value" id="summaryStaff"></span>
                            </div>
                            <div class="confirm-item">
                                <span class="confirm-label">Date</span>
                                <span class="confirm-value" id="summaryDate"></span>
                            </div>
                            <div class="confirm-item">
                                <span class="confirm-label">Time</span>
                                <span class="confirm-value" id="summaryTime"></span>
                            </div>
                            <div class="confirm-item">
                                <span class="confirm-label">Duration</span>
                                <span class="confirm-value" id="summaryDuration"></span>
                            </div>
                            <div class="confirm-item">
                                <span class="confirm-label">Price</span>
                                <span class="confirm-value confirm-price" id="summaryPrice"></span>
                            </div>
                        </div>
                    </div>

                    <div class="confirm-info">
                        <i class="fas fa-info-circle"></i> Your appointment will be submitted as <strong>pending</strong>. We will confirm it shortly. Seat will be auto-assigned for you.
                    </div>

                    <div class="booking-card-actions">
                        <button type="button" class="bbtn bbtn-back" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="submit" class="bbtn bbtn-confirm"><i class="fas fa-calendar-check"></i> Confirm Booking</button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</section>

<style>
.booking-hero {
    padding: 1.5rem 1.5rem 3rem;
    background: linear-gradient(135deg, var(--avocado-50), white);
}
.booking-hero-text { text-align: center; margin-bottom: 1.8rem; }
.booking-hero-text h1 { font-family:'Playfair Display',serif; font-size:1.9rem; color:var(--avocado-900); margin:0 0 0.4rem; }
.booking-hero-text p { color:var(--text-light); font-size:0.9rem; margin:0; }

/* Step indicator */
.booking-steps-indicator { display:flex; justify-content:center; align-items:center; gap:0.4rem; margin-bottom:1.8rem; }
.bstep { display:flex; align-items:center; gap:0.35rem; padding:0.35rem 0.75rem; border-radius:20px; background:white; border:2px solid var(--avocado-100); transition:all 0.3s; cursor:default; }
.bstep.active { background:var(--avocado-600); border-color:var(--avocado-600); }
.bstep.done { background:var(--avocado-100); border-color:var(--avocado-200); color:var(--avocado-700); }
.bstep-num { width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; background:var(--avocado-200); color:var(--avocado-800); }
.bstep.active .bstep-num { background:rgba(255,255,255,0.3); color:white; }
.bstep.done .bstep-num { background:var(--avocado-600); color:white; }
.bstep-label { font-size:0.75rem; font-weight:500; }
.bstep.active .bstep-label { color:white; }
.bstep-arrow { color:var(--avocado-300); font-size:0.7rem; }

/* Panels */
.booking-panel { display:none; }
.booking-panel.active { display:block; }

/* Card */
.booking-card { background:white; border-radius:20px; padding:1.8rem; box-shadow:0 2px 20px rgba(0,0,0,0.05); }
.booking-card-header { margin-bottom:1.5rem; }
.booking-card-header h2 { font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--avocado-900); margin:0 0 0.25rem; }
.booking-card-header p { color:var(--text-light); font-size:0.82rem; margin:0; }

/* Alerts */
.booking-alert { border-radius:12px; padding:0.75rem 1rem; margin-bottom:1.5rem; font-size:0.85rem; }
.booking-alert i { margin-right:0.4rem; }
.booking-alert-error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
.booking-alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }

/* Category pills */
.booking-categories { display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:1.2rem; }
.cat-pill { padding:0.35rem 0.9rem; border-radius:20px; border:1px solid var(--avocado-200); background:white; color:var(--avocado-700); font-size:0.78rem; font-weight:500; cursor:pointer; transition:all 0.2s; }
.cat-pill:hover { background:var(--avocado-50); }
.cat-pill.active { background:var(--avocado-600); color:white; border-color:var(--avocado-600); }

/* Service cards */
.booking-service-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:0.9rem; }
.service-card-select { border:2px solid var(--avocado-100); border-radius:14px; cursor:pointer; transition:all 0.3s; display:block; }
.service-card-select:hover { border-color:var(--avocado-300); box-shadow:0 2px 12px rgba(0,0,0,0.06); }
.service-card-select.selected { border-color:var(--avocado-500); background:var(--avocado-50); box-shadow:0 0 0 3px rgba(124,179,66,0.15); }
.service-card-select-inner { padding:1rem; }
.service-card-select-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem; }
.service-card-select-icon { width:36px; height:36px; background:var(--avocado-100); border-radius:10px; display:flex; align-items:center; justify-content:center; }
.service-card-select-icon i { color:var(--avocado-600); font-size:0.85rem; }
.service-card-select-badge { font-size:0.65rem; padding:0.15rem 0.5rem; background:var(--avocado-50); color:var(--avocado-600); border-radius:20px; font-weight:500; }
.service-card-select h3 { font-weight:600; color:var(--dark); margin:0.3rem 0 0.2rem; font-size:0.9rem; }
.service-card-select-desc { font-size:0.75rem; color:var(--text-light); margin:0 0 0.7rem; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.service-card-select-footer { display:flex; justify-content:space-between; align-items:center; padding-top:0.7rem; border-top:1px solid var(--avocado-100); }
.service-card-select-price { font-weight:700; color:var(--avocado-600); font-size:0.85rem; }
.service-card-select-dur { font-size:0.72rem; color:var(--text-light); }
.service-card-select-dur i { margin-right:0.2rem; }

/* Schedule */
.schedule-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.2rem; }
.schedule-field label, .booking-field label, .schedule-staff-label { display:block; font-weight:600; color:var(--dark); margin-bottom:0.4rem; font-size:0.85rem; }
.schedule-staff-label { margin-bottom:0.6rem; }

.binput { width:100%; padding:0.7rem 0.9rem; border:2px solid var(--avocado-100); border-radius:10px; font-size:0.85rem; outline:none; transition:border-color 0.3s; font-family:'Inter',sans-serif; background:white; }
.binput:focus { border-color:var(--avocado-400); }
.btextarea { resize:vertical; min-height:60px; }
.booking-field { margin-bottom:0.5rem; }
.optional { font-weight:400; color:var(--text-light); font-size:0.78rem; }

/* Staff grid */
.booking-staff-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:0.8rem; }
.staff-card-select { border:2px solid var(--avocado-100); border-radius:14px; cursor:pointer; transition:all 0.3s; display:block; text-align:center; }
.staff-card-select:hover { border-color:var(--avocado-300); box-shadow:0 2px 10px rgba(0,0,0,0.05); }
.staff-card-select.selected { border-color:var(--avocado-500); background:var(--avocado-50); box-shadow:0 0 0 3px rgba(124,179,66,0.15); }
.staff-card-select-inner { padding:1rem 0.8rem; }
.staff-card-select-avatar { width:52px; height:52px; border-radius:50%; background:var(--avocado-100); margin:0 auto 0.6rem; display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px solid var(--avocado-200); }
.staff-card-select-avatar img { width:100%; height:100%; object-fit:cover; }
.staff-card-select-avatar i { color:var(--avocado-500); font-size:1.1rem; }
.staff-card-select h4 { font-weight:600; color:var(--dark); margin:0 0 0.15rem; font-size:0.82rem; }
.staff-card-select p { font-size:0.7rem; color:var(--text-light); margin:0 0 0.4rem; }
.staff-card-select-hours { font-size:0.65rem; color:var(--avocado-500); }
.staff-card-select-hours i { margin-right:0.2rem; }

/* Availability notice */
.availability-notice { padding:0.7rem 1rem; border-radius:10px; font-size:0.82rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.6rem; }
.availability-notice i { font-size:1rem; flex-shrink:0; }
.avail-ok { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
.avail-warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.avail-bad { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }

/* Confirm summary */
.confirm-summary { background:var(--avocado-50); border-radius:14px; padding:1.2rem; margin-bottom:1rem; }
.confirm-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:1rem; }
.confirm-label { display:block; font-size:0.65rem; color:var(--text-light); text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-bottom:0.15rem; }
.confirm-value { display:block; font-weight:600; color:var(--dark); font-size:0.88rem; }
.confirm-price { color:var(--avocado-600) !important; font-size:1rem !important; font-weight:700 !important; }
.confirm-info { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:0.7rem 1rem; font-size:0.82rem; color:#92400e; margin-bottom:0.5rem; }
.confirm-info i { margin-right:0.3rem; }

/* Buttons */
.booking-card-actions { display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f3f4f6; }
.bbtn { padding:0.6rem 1.3rem; border-radius:10px; font-weight:600; font-size:0.85rem; cursor:pointer; transition:all 0.3s; border:none; display:inline-flex; align-items:center; gap:0.4rem; font-family:'Inter',sans-serif; }
.bbtn-next { background:var(--avocado-600); color:white; }
.bbtn-next:hover { background:var(--avocado-700); }
.bbtn-back { background:white; color:var(--avocado-700); border:2px solid var(--avocado-200); }
.bbtn-back:hover { background:var(--avocado-50); }
.bbtn-confirm { background:var(--avocado-600); color:white; font-size:0.92rem; padding:0.7rem 1.8rem; }
.bbtn-confirm:hover { background:var(--avocado-700); }

/* Responsive */
@media (max-width:640px) {
    .booking-hero { padding:1rem 1rem 2rem; }
    .booking-hero-text h1 { font-size:1.4rem; }
    .booking-card { padding:1.2rem; }
    .booking-service-grid { grid-template-columns:1fr; }
    .schedule-row { grid-template-columns:1fr; }
    .booking-staff-grid { grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); }
    .confirm-row { grid-template-columns:1fr 1fr; }
    .bstep-label { display:none; }
}
</style>

<script>
var currentStep = 1;
var totalSteps = 3;

function goToStep(step) {
    if (step > currentStep) {
        if (currentStep === 1 && !document.querySelector('input[name="service_id"]:checked')) {
            Swal.fire({icon:'warning',title:'Select a Service',text:'Please choose a service to continue.',confirmButtonColor:'#7cb342'});
            return;
        }
        if (currentStep === 2) {
            var date = document.querySelector('[name="appointment_date"]').value;
            var time = document.querySelector('[name="appointment_time"]').value;
            var staff = document.querySelector('input[name="staff_id"]:checked');
            if (!date || !time) {
                Swal.fire({icon:'warning',title:'Select Date & Time',text:'Please choose both date and time to continue.',confirmButtonColor:'#7cb342'});
                return;
            }
            if (!staff) {
                Swal.fire({icon:'warning',title:'Select Staff',text:'Please choose a staff member to continue.',confirmButtonColor:'#7cb342'});
                return;
            }
            updateSummary();
        }
    }
    document.querySelectorAll('.booking-panel').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('step' + step).classList.add('active');
    for (var i = 1; i <= totalSteps; i++) {
        var dot = document.getElementById('step' + i + 'Dot');
        dot.classList.remove('active', 'done');
        if (i === step) dot.classList.add('active');
        else if (i < step) dot.classList.add('done');
    }
    currentStep = step;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function filterCategory(cat, btn) {
    document.querySelectorAll('.cat-pill').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.service-card-select').forEach(function(opt) {
        opt.style.display = (cat === 'all' || opt.dataset.category === cat) ? '' : 'none';
    });
}

function selectService(radio) {
    document.querySelectorAll('.service-card-select').forEach(function(o) { o.classList.remove('selected'); });
    radio.closest('.service-card-select').classList.add('selected');
}

function selectStaff(radio) {
    document.querySelectorAll('.staff-card-select').forEach(function(o) { o.classList.remove('selected'); });
    radio.closest('.staff-card-select').classList.add('selected');
}

function updateSummary() {
    var svc = document.querySelector('input[name="service_id"]:checked');
    var stf = document.querySelector('input[name="staff_id"]:checked');
    var date = document.querySelector('[name="appointment_date"]').value;
    var time = document.querySelector('[name="appointment_time"]').value;

    if (svc) {
        var card = svc.closest('.service-card-select');
        document.getElementById('summaryService').textContent = card.querySelector('h3').textContent;
        document.getElementById('summaryPrice').textContent = 'MMK' + parseFloat(svc.dataset.price).toLocaleString('en',{minimumFractionDigits:2});
        document.getElementById('summaryDuration').textContent = svc.dataset.duration + ' minutes';
    }
    if (stf) {
        var card = stf.closest('.staff-card-select');
        document.getElementById('summaryStaff').textContent = card.querySelector('h4').textContent;
    }
    if (date) {
        var d = new Date(date + 'T00:00:00');
        document.getElementById('summaryDate').textContent = d.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric', year:'numeric'});
    }
    if (time) {
        var parts = time.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1];
        var ampm = h >= 12 ? 'PM' : 'AM';
        document.getElementById('summaryTime').textContent = (h % 12 || 12) + ':' + m + ' ' + ampm;
    }
}

function updateTimeSlots() {
    var date = document.querySelector('[name="appointment_date"]').value;
    var timeSelect = document.getElementById('bookingTime');
    var today = new Date().toISOString().split('T')[0];
    var now = new Date();
    var bufferMinutes = 30;

    var options = timeSelect.querySelectorAll('option[value]');
    options.forEach(function(opt) {
        if (!opt.value) return;
        if (date === today) {
            var parts = opt.value.split(':');
            var slotTime = new Date();
            slotTime.setHours(parseInt(parts[0]), parseInt(parts[1]), 0, 0);
            var minAllowed = new Date(now.getTime() + bufferMinutes * 60000);
            if (slotTime <= minAllowed) {
                opt.disabled = true;
                opt.style.color = '#ccc';
            } else {
                opt.disabled = false;
                opt.style.color = '';
            }
        } else {
            opt.disabled = false;
            opt.style.color = '';
        }
    });

    if (date === today) {
        var currentVal = timeSelect.value;
        if (currentVal) {
            var parts = currentVal.split(':');
            var slotTime = new Date();
            slotTime.setHours(parseInt(parts[0]), parseInt(parts[1]), 0, 0);
            var minAllowed = new Date(now.getTime() + bufferMinutes * 60000);
            if (slotTime <= minAllowed) {
                timeSelect.value = '';
            }
        }
    }
}

function onScheduleChange() {
    updateTimeSlots();
    var date = document.querySelector('[name="appointment_date"]').value;
    var time = document.querySelector('[name="appointment_time"]').value;
    var svc = document.querySelector('input[name="service_id"]:checked');
    var stf = document.querySelector('input[name="staff_id"]:checked');

    var notice = document.getElementById('availabilityNotice');
    if (!date || !time || !svc) { notice.style.display = 'none'; return; }

    var duration = parseInt(svc.dataset.duration) || 30;
    var staffId = stf ? stf.value : '';
    var url = 'booking.php?check_availability=1&date=' + date + '&time=' + time + '&duration=' + duration + '&staff_id=' + staffId;

    fetch(url).then(function(r){return r.json();}).then(function(data) {
        notice.style.display = 'flex';
        var seatsAvail = data.seats_available;
        var seatsTotal = data.seats_total;

        if (seatsAvail <= 0) {
            notice.className = 'availability-notice avail-bad';
            notice.innerHTML = '<i class="fas fa-times-circle"></i><span><strong>No seats available</strong> &mdash; All ' + seatsTotal + ' seats are occupied for this time slot.</span>';
        } else if (seatsAvail <= 2) {
            notice.className = 'availability-notice avail-warn';
            notice.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span><strong>' + seatsAvail + ' seat' + (seatsAvail > 1 ? 's' : '') + ' left</strong> &mdash; Limited availability. Book soon!</span>';
        } else {
            notice.className = 'availability-notice avail-ok';
            notice.innerHTML = '<i class="fas fa-check-circle"></i><span><strong>' + seatsAvail + ' seats available</strong> &mdash; Good to go for ' + data.end_time + '.</span>';
        }

        if (!data.staff_available && staffId) {
            notice.innerHTML += '<br><small style="color:#b45309;">This staff member may not be available during this time.</small>';
        }
    }).catch(function(){ notice.style.display = 'none'; });
}
</script>

<main class="max-w-7xl mx-auto px-4 py-6">
<?php require_once '../includes/footer.php'; ?>
