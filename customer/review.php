<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$appointment_id = intval($_GET['id'] ?? 0);

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

if ($appointment_id) {
    $title = 'Rate Your Experience';

    $stmt = $pdo->prepare("
        SELECT a.*, s.name as service_name, s.price, st.name as staff_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN staff st ON a.staff_id = st.id
        WHERE a.id = ? AND a.customer_id = ? AND a.status = 'completed'
    ");
    $stmt->execute([$appointment_id, $_SESSION['user_id']]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        header('Location: appointments.php');
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM reviews WHERE appointment_id = ?");
    $check->execute([$appointment_id]);
    if ($check->fetch()) {
        header('Location: appointments.php?filter=past');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a rating.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO reviews (customer_id, service_id, staff_id, appointment_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $appointment['service_id'], $appointment['staff_id'], $appointment_id, $rating, $comment]);
            header('Location: review.php');
            exit;
        }
    }

    require_once '../includes/header.php';
?>
</main>

<section class="section" style="padding:3rem 2rem;">
    <div class="container" style="max-width:600px;margin:0 auto;">
        <div class="section-header" style="margin-bottom:2rem;">
            <span class="tag">Rate Your Experience</span>
            <h2>How was your <span>visit</span>?</h2>
        </div>

        <div style="background:white;border-radius:20px;padding:2rem;box-shadow:0 2px 20px rgba(0,0,0,0.05);">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--avocado-100);">
                <div style="background:var(--avocado-100);border-radius:12px;padding:0.8rem 1rem;text-align:center;min-width:60px;">
                    <p style="font-size:1.3rem;font-weight:700;color:var(--avocado-700);margin:0;"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></p>
                    <p style="font-size:0.7rem;color:var(--avocado-500);text-transform:uppercase;font-weight:600;margin:0;"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></p>
                </div>
                <div>
                    <p style="font-weight:600;color:var(--dark);margin:0;"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                    <p style="font-size:0.85rem;color:var(--text-light);margin:0.2rem 0 0;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($appointment['staff_name']); ?>
                        &nbsp;&nbsp;<i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:0.8rem 1rem;margin-bottom:1rem;color:#dc2626;font-size:0.9rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" style="display:flex;flex-direction:column;gap:1.5rem;">
                <div style="text-align:center;">
                    <p style="font-weight:600;color:var(--dark);margin-bottom:0.8rem;font-size:1rem;">Your Rating</p>
                    <div id="starRating" style="display:flex;justify-content:center;gap:0.5rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star star-btn" data-value="<?php echo $i; ?>"
                           style="font-size:2rem;cursor:pointer;color:#d1d5db;transition:color 0.2s;"
                           onmouseover="highlightStars(<?php echo $i; ?>)"
                           onmouseout="resetStars()"
                           onclick="setRating(<?php echo $i; ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0">
                    <p id="ratingText" style="font-size:0.85rem;color:var(--text-light);margin-top:0.5rem;">Click to rate</p>
                </div>

                <div>
                    <label style="font-weight:600;color:var(--dark);display:block;margin-bottom:0.5rem;font-size:0.9rem;">Your Review (optional)</label>
                    <textarea name="comment" rows="4" placeholder="Tell us about your experience..."
                        style="width:100%;padding:0.9rem 1rem;border:2px solid var(--avocado-100);border-radius:12px;font-size:0.9rem;background:white;outline:none;resize:vertical;transition:border-color 0.3s;font-family:inherit;"
                        onfocus="this.style.borderColor='var(--avocado-400)';"
                        onblur="this.style.borderColor='var(--avocado-100)';"></textarea>
                </div>

                <div style="display:flex;gap:1rem;">
                    <a href="appointments.php?filter=past" style="flex:1;text-align:center;padding:0.9rem;border:2px solid var(--avocado-200);border-radius:12px;color:var(--avocado-700);font-weight:600;text-decoration:none;transition:all 0.3s;">Cancel</a>
                    <button type="submit" class="btn-primary" style="flex:2;justify-content:center;padding:0.9rem;border:none;">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
var currentRating = 0;
var ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

function highlightStars(count) {
    var stars = document.querySelectorAll('.star-btn');
    stars.forEach(function(s, i) {
        s.style.color = i < count ? '#f59e0b' : '#d1d5db';
    });
}

function resetStars() {
    highlightStars(currentRating);
}

function setRating(value) {
    currentRating = value;
    document.getElementById('ratingInput').value = value;
    document.getElementById('ratingText').textContent = ratingLabels[value];
    highlightStars(value);
}
</script>

<?php } else {
    $title = 'My Reviews';

    $stmt = $pdo->prepare("
        SELECT r.*, s.name as service_name, s.category_id, c.name as category_name, st.name as staff_name, st.photo as staff_photo,
               a.appointment_date, a.appointment_time
        FROM reviews r
        JOIN services s ON r.service_id = s.id
        JOIN categories c ON s.category_id = c.id
        JOIN staff st ON r.staff_id = st.id
        JOIN appointments a ON r.appointment_id = a.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myReviews = $stmt->fetchAll();

    $avgRating = count($myReviews) > 0 ? round(array_sum(array_column($myReviews, 'rating')) / count($myReviews), 1) : 0;
    $starCounts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
    foreach ($myReviews as $r) { $starCounts[$r['rating']]++; }
    $maxCount = max(1, max($starCounts));

    require_once '../includes/header.php';
?>
</main>

<style>
.rev-page{background:linear-gradient(180deg,var(--avocado-50) 0%,#f9fafb 100%);min-height:100vh;padding:2rem 0 3rem}

.rev-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
.rev-head h1{font-family:'Playfair Display',serif;font-size:2rem;color:var(--avocado-900);margin:0}
.rev-head h1 span{color:var(--avocado-600)}
.rev-head-link{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.2rem;border-radius:10px;border:2px solid var(--avocado-200);background:white;color:var(--avocado-700);font-weight:600;font-size:.88rem;text-decoration:none;transition:all .3s}
.rev-head-link:hover{background:var(--avocado-50);border-color:var(--avocado-400)}

.rev-summary{display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:1rem;margin-bottom:2rem;padding:1.5rem;background:white;border:2px solid var(--avocado-100);border-radius:16px}
.rev-sum-block{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:.8rem 0}
.rev-sum-block:not(:last-child){border-right:2px solid var(--avocado-100)}
.rev-sum-big{font-size:2.4rem;font-weight:800;color:var(--avocado-700);line-height:1;margin:0}
.rev-sum-label{font-size:.75rem;color:var(--text-light);margin-top:.3rem;font-weight:600}
.rev-sum-stars{display:flex;gap:.15rem;margin:.4rem 0}
.rev-sum-stars i{font-size:.85rem;color:#f59e0b}
.rev-sum-stars i.off{color:#d1d5db}
.rev-sum-total{font-size:1.8rem;font-weight:800;color:var(--avocado-600);line-height:1;margin:0}

.rev-bars{display:flex;flex-direction:column;gap:.35rem;width:100%}
.rev-bar-row{display:flex;align-items:center;gap:.5rem}
.rev-bar-label{font-size:.72rem;font-weight:600;color:var(--text-light);width:12px;text-align:right}
.rev-bar-track{flex:1;height:8px;background:var(--avocado-50);border-radius:4px;overflow:hidden}
.rev-bar-fill{height:100%;background:var(--avocado-500);border-radius:4px;transition:width .5s ease}
.rev-bar-count{font-size:.7rem;font-weight:600;color:var(--avocado-600);width:20px}

.rev-table{width:100%;border:2px solid var(--avocado-100);border-radius:14px;overflow:hidden;background:white}
.rev-thead{display:grid;grid-template-columns:90px 1.3fr 1fr 1fr 1.5fr;padding:.6rem 1rem;background:var(--avocado-50);border-bottom:2px solid var(--avocado-100)}
.rev-thead span{font-size:.7rem;font-weight:700;color:var(--avocado-700);text-transform:uppercase;letter-spacing:.5px}
.rev-tbody{max-height:calc(100vh - 380px);overflow-y:auto}
.rev-row{display:grid;grid-template-columns:90px 1.3fr 1fr 1fr 1.5fr;padding:.7rem 1rem;align-items:center;border-bottom:1px solid #f3f4f6;transition:background .2s}
.rev-row:last-child{border-bottom:none}
.rev-row:hover{background:var(--avocado-50)}

.rev-stars{display:flex;align-items:center;gap:.1rem}
.rev-stars i{font-size:.8rem;color:#f59e0b}
.rev-stars i.off{color:#d1d5db}
.rev-stars-val{font-size:.72rem;font-weight:600;color:var(--text-light);margin-left:.3rem}
.rev-svc{font-weight:600;color:var(--dark);font-size:.88rem}
.rev-svc small{display:block;font-weight:400;color:var(--text-light);font-size:.72rem;margin-top:.1rem}
.rev-staff{display:flex;align-items:center;gap:.45rem}
.rev-staff-av{width:28px;height:28px;border-radius:50%;background:var(--avocado-100);display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid var(--avocado-200);flex-shrink:0}
.rev-staff-av img{width:100%;height:100%;object-fit:cover}
.rev-staff-av i{color:var(--avocado-500);font-size:.65rem}
.rev-staff-name{font-size:.82rem;color:var(--dark);font-weight:500}
.rev-date{font-size:.82rem;color:var(--text-light)}
.rev-comment{font-size:.82rem;color:var(--dark);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.rev-comment:empty{display:none}

.rev-empty{background:white;border:2px solid var(--avocado-100);border-radius:14px;padding:3rem;text-align:center}
.rev-empty i{font-size:2.5rem;color:var(--avocado-300);margin-bottom:1rem;display:block}
.rev-empty h3{font-weight:700;color:var(--avocado-800);margin:0 0 .4rem;font-size:1.1rem}
.rev-empty p{color:var(--text-light);font-size:.9rem;margin:0 0 1.2rem}
.rev-empty a{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.5rem;border-radius:10px;background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;font-weight:700;font-size:.9rem;text-decoration:none;transition:all .3s}
.rev-empty a:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px)}

@media(max-width:768px){
    .rev-summary{grid-template-columns:1fr;gap:.8rem}
    .rev-sum-block:not(:last-child){border-right:none;border-bottom:2px solid var(--avocado-100);padding-bottom:.8rem}
    .rev-thead{display:none}
    .rev-row{grid-template-columns:70px 1fr;gap:.5rem;padding:.8rem 1rem}
    .rev-staff,.rev-date{display:none}
    .rev-comment{-webkit-line-clamp:1}
}
</style>

<section class="rev-page">
    <div style="max-width:1100px;margin:0 auto;padding:0 1.5rem">
        <div class="rev-head">
            <h1>My <span>Reviews</span></h1>
            <a href="appointments.php" class="rev-head-link"><i class="fas fa-calendar-check"></i> View Appointments</a>
        </div>

        <?php if (empty($myReviews)): ?>
            <div class="rev-empty">
                <i class="fas fa-star"></i>
                <h3>No reviews yet</h3>
                <p>Complete an appointment to leave your first review!</p>
                <a href="appointments.php"><i class="fas fa-history"></i> View Past Appointments</a>
            </div>
        <?php else: ?>
            <div class="rev-summary">
                <div class="rev-sum-block">
                    <p class="rev-sum-big"><?php echo $avgRating; ?></p>
                    <div class="rev-sum-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?php echo $i > round($avgRating) ? ' off' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="rev-sum-label">Average Rating</p>
                </div>
                <div class="rev-sum-block">
                    <p class="rev-sum-total"><?php echo count($myReviews); ?></p>
                    <p class="rev-sum-label">Total Review<?php echo count($myReviews) !== 1 ? 's' : ''; ?></p>
                </div>
                <div class="rev-sum-block" style="padding:.6rem 1.2rem;">
                    <div class="rev-bars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="rev-bar-row">
                            <span class="rev-bar-label"><?php echo $i; ?></span>
                            <div class="rev-bar-track"><div class="rev-bar-fill" style="width:<?php echo $maxCount > 0 ? round($starCounts[$i] / $maxCount * 100) : 0; ?>%"></div></div>
                            <span class="rev-bar-count"><?php echo $starCounts[$i]; ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="rev-table">
                <div class="rev-thead">
                    <span>Rating</span>
                    <span>Service</span>
                    <span>Staff</span>
                    <span>Date</span>
                    <span>Review</span>
                </div>
                <div class="rev-tbody">
                    <?php foreach ($myReviews as $rev): ?>
                    <div class="rev-row">
                        <div class="rev-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i > $rev['rating'] ? ' off' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="rev-svc">
                            <?php echo htmlspecialchars($rev['service_name']); ?>
                            <small><?php echo htmlspecialchars($rev['category_name']); ?></small>
                        </div>
                        <div class="rev-staff">
                            <div class="rev-staff-av">
                                <?php if (!empty($rev['staff_photo'])): ?>
                                    <img src="/nail/assets/uploads/<?php echo htmlspecialchars($rev['staff_photo']); ?>" alt="<?php echo htmlspecialchars($rev['staff_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <span class="rev-staff-name"><?php echo htmlspecialchars($rev['staff_name']); ?></span>
                        </div>
                        <div class="rev-date"><?php echo date('M d, Y', strtotime($rev['appointment_date'])); ?></div>
                        <div class="rev-comment"><?php echo htmlspecialchars($rev['comment'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php } ?>
<main class="max-w-7xl mx-auto px-4 py-6">
<?php require_once '../includes/footer.php'; ?>
