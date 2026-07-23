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
        SELECT r.*, s.name as service_name, st.name as staff_name,
               a.appointment_date, a.appointment_time
        FROM reviews r
        JOIN services s ON r.service_id = s.id
        JOIN staff st ON r.staff_id = st.id
        JOIN appointments a ON r.appointment_id = a.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myReviews = $stmt->fetchAll();

    require_once '../includes/header.php';
?>
</main>

<section class="section" style="padding:3rem 2rem;">
    <div class="container" style="max-width:800px;margin:0 auto;">
        <div class="section-header" style="margin-bottom:2rem;">
            <span class="tag">My Reviews</span>
            <h2>Your <span>Reviews</span></h2>
            <p>See all the feedback you've shared with us.</p>
        </div>

        <?php if (empty($myReviews)): ?>
        <div style="background:white;border-radius:20px;padding:3rem;text-align:center;box-shadow:0 2px 15px rgba(0,0,0,0.04);">
            <i class="fas fa-star" style="font-size:3rem;color:var(--avocado-200);margin-bottom:1rem;display:block;"></i>
            <h3 style="font-size:1.1rem;color:var(--dark);margin:0 0 0.5rem;">No reviews yet</h3>
            <p style="color:var(--text-light);font-size:0.9rem;margin:0 0 1.5rem;">Complete an appointment to leave your first review!</p>
            <a href="appointments.php?filter=past" class="btn-primary" style="display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;">
                <i class="fas fa-history"></i> View Past Appointments
            </a>
        </div>
        <?php else: ?>

        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1rem 1.5rem;background:var(--avocado-50);border-radius:14px;">
            <div style="text-align:center;">
                <p style="font-size:2rem;font-weight:700;color:var(--avocado-700);margin:0;"><?php echo number_format(array_sum(array_column($myReviews, 'rating')) / count($myReviews), 1); ?></p>
                <div style="margin:0.3rem 0;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color:<?php echo $i <= round(array_sum(array_column($myReviews, 'rating')) / count($myReviews)) ? '#f59e0b' : '#d1d5db'; ?>;font-size:0.8rem;"></i>
                    <?php endfor; ?>
                </div>
                <p style="font-size:0.75rem;color:var(--text-light);margin:0;"><?php echo count($myReviews); ?> review<?php echo count($myReviews) !== 1 ? 's' : ''; ?></p>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($myReviews as $rev): ?>
            <div style="background:white;border-radius:16px;padding:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,0.04);border:1px solid rgba(124,179,66,0.08);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.8rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.3rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color:<?php echo $i <= $rev['rating'] ? '#f59e0b' : '#d1d5db'; ?>;font-size:0.85rem;"></i>
                            <?php endfor; ?>
                            <span style="font-size:0.8rem;color:var(--text-light);margin-left:0.3rem;"><?php echo $rev['rating']; ?>/5</span>
                        </div>
                        <p style="font-weight:600;color:var(--dark);margin:0;font-size:0.95rem;"><?php echo htmlspecialchars($rev['service_name']); ?></p>
                        <p style="font-size:0.8rem;color:var(--text-light);margin:0.2rem 0 0;">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($rev['staff_name']); ?>
                            &nbsp;&nbsp;<i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($rev['appointment_date'])); ?>
                        </p>
                    </div>
                    <span style="font-size:0.75rem;color:var(--text-light);"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                </div>
                <?php if (!empty($rev['comment'])): ?>
                <p style="font-size:0.9rem;color:var(--dark);line-height:1.6;margin:0;padding-top:0.8rem;border-top:1px solid var(--avocado-100);"><?php echo htmlspecialchars($rev['comment']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php } ?>
<main class="max-w-7xl mx-auto px-4 py-6">
<?php require_once '../includes/footer.php'; ?>
