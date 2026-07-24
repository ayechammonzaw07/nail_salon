<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Home';

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

$reviews = $pdo->prepare("
    SELECT r.*, s.name as service_name, st.name as staff_name, u.full_name as customer_name
    FROM reviews r
    JOIN services s ON r.service_id = s.id
    JOIN staff st ON r.staff_id = st.id
    JOIN users u ON r.customer_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 6
");
$reviews->execute();
$reviews = $reviews->fetchAll();

$services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name")->fetchAll();

$staff = $pdo->query("SELECT * FROM staff WHERE status='available' ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>
</main>

<section class="hero" style="min-height:auto;padding:5rem 2rem 2rem;">
    <div class="container">
        <div class="hero-content">
            <h1>Welcome back, <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span></h1>
            <p>Your next fresh look is just a click away. Browse our services or check your upcoming appointments.</p>
            <div class="hero-buttons">
                <a href="booking.php" class="btn-primary">
                    <i class="fas fa-calendar-check"></i> Book Your Session
                </a>
                <a href="appointments.php" class="btn-outline">
                    <i class="fas fa-clock"></i> My Appointments
                </a>
            </div>
        </div>
        <div class="hero-image">
            <div class="model-frame" style="overflow:hidden;">
                <span class="model-text">✦ Welcome</span>
                <img src="/nail/assets/uploads/services/home.jpg" alt="Nail Salon" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
            </div>
            <div class="floating-card">
                <i class="fas fa-star"></i>
                <div>
                    <span>Premium Quality</span>
                    <small>Top-rated studio</small>
                </div>
            </div>
            <div class="floating-card">
                <i class="fas fa-leaf"></i>
                <div>
                    <span>Natural Care</span>
                    <small>Eco-friendly products</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats-strip">
    <div class="container">
        <div class="stat-item">
            <h3>500+</h3>
            <p>Happy Clients</p>
        </div>
        <div class="stat-item">
            <h3>50+</h3>
            <p>Nail Designs</p>
        </div>
        <div class="stat-item">
            <h3>4.9★</h3>
            <p>Average Rating</p>
        </div>
        <div class="stat-item">
            <h3>5</h3>
            <p>Years Experience</p>
        </div>
    </div>
</section>

<?php if (!empty($reviews)): ?>
<section class="section" style="padding:3rem 2rem;background:var(--avocado-50);">
    <div class="container">
        <div class="section-header">
            <span class="tag">Reviews</span>
            <h2>What Our <span>Clients</span> Say</h2>
            <p>Real feedback from our happy customers.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;max-width:1000px;margin:0 auto;">
            <?php foreach ($reviews as $rev): ?>
            <div style="background:white;border-radius:16px;padding:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,0.04);border:1px solid rgba(124,179,66,0.08);">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.8rem;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color:<?php echo $i <= $rev['rating'] ? '#f59e0b' : '#d1d5db'; ?>;font-size:0.85rem;"></i>
                    <?php endfor; ?>
                    <span style="font-size:0.8rem;color:var(--text-light);margin-left:0.3rem;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                </div>
                <?php if (!empty($rev['comment'])): ?>
                <p style="font-size:0.9rem;color:var(--dark);line-height:1.6;margin:0 0 1rem;"><?php echo htmlspecialchars($rev['comment']); ?></p>
                <?php endif; ?>
                <div style="display:flex;align-items:center;gap:0.8rem;padding-top:0.8rem;border-top:1px solid var(--avocado-100);">
                    <div style="width:36px;height:36px;background:var(--avocado-100);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user" style="color:var(--avocado-600);font-size:0.8rem;"></i>
                    </div>
                    <div>
                        <p style="font-weight:600;color:var(--dark);margin:0;font-size:0.85rem;"><?php echo htmlspecialchars($rev['customer_name']); ?></p>
                        <p style="font-size:0.75rem;color:var(--text-light);margin:0;"><?php echo htmlspecialchars($rev['service_name']); ?> with <?php echo htmlspecialchars($rev['staff_name']); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
.svc-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(61,79,42,.06);border:1px solid rgba(124,179,66,.08);transition:all .4s ease;cursor:pointer;position:relative}
.svc-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(61,79,42,.12);border-color:var(--avocado-300)}
.svc-card-img{position:relative;height:200px;overflow:hidden}
.svc-card-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease}
.svc-card:hover .svc-card-img img{transform:scale(1.08)}
.svc-card-img .svc-placeholder{width:100%;height:100%;background:linear-gradient(135deg,var(--avocado-100),var(--avocado-50));display:flex;align-items:center;justify-content:center}
.svc-card-img .svc-placeholder i{font-size:3rem;color:var(--avocado-300)}
.svc-card-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);color:var(--avocado-700);font-size:.7rem;font-weight:600;padding:.3rem .8rem;border-radius:50px;border:1px solid var(--avocado-200)}
.svc-card-price-tag{position:absolute;top:.75rem;right:.75rem;background:var(--avocado-600);color:white;font-size:.85rem;font-weight:700;padding:.35rem .9rem;border-radius:12px;box-shadow:0 4px 12px rgba(93,132,51,.3)}
.svc-card-body{padding:1.2rem 1.4rem 1.4rem}
.svc-card-body h3{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--avocado-900);margin:0 0 .3rem;line-height:1.3}
.svc-card-body .svc-desc{font-size:.85rem;color:var(--text-light);line-height:1.6;margin:0 0 1rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.svc-card-meta{display:flex;align-items:center;gap:1rem;padding-top:.9rem;border-top:1px solid #f3f4f6}
.svc-card-meta .svc-meta-item{display:flex;align-items:center;gap:.35rem;font-size:.8rem;color:var(--text-light)}
.svc-card-meta .svc-meta-item i{color:var(--avocado-500);font-size:.85rem}
.svc-card-footer{display:flex;align-items:center;justify-content:space-between;padding:0 1.4rem 1.2rem}
.svc-card-footer .svc-book-btn{background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;border:none;padding:.55rem 1.2rem;border-radius:12px;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .3s;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none}
.svc-card-footer .svc-book-btn:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px);box-shadow:0 6px 16px rgba(93,132,51,.25)}
.svc-card-footer .svc-view-detail{font-size:.78rem;color:var(--avocado-600);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem;transition:color .2s;cursor:pointer}
.svc-card-footer .svc-view-detail:hover{color:var(--avocado-800)}
@media(max-width:640px){.svc-grid{grid-template-columns:1fr}}
</style>

<section class="section" id="services">
    <div class="container">
        <div class="section-header">
            <span class="tag">Our Services</span>
            <h2>Fresh <span>Avocado</span> Treatments</h2>
            <p>From classic elegance to bold model-inspired designs — every service is crafted with care using natural, high-quality products.</p>
        </div>
        <div class="svc-grid">
            <?php foreach ($services as $svc): ?>
            <div class="svc-card">
                <div class="svc-card-img">
                    <?php if ($svc['image']): ?>
                        <img src="/nail/assets/uploads/<?php echo $svc['image']; ?>" alt="<?php echo htmlspecialchars($svc['name']); ?>">
                    <?php else: ?>
                        <div class="svc-placeholder"><i class="fas fa-leaf"></i></div>
                    <?php endif; ?>
                    <span class="svc-card-badge"><?php echo htmlspecialchars($svc['category_name']); ?></span>
                    <span class="svc-card-price-tag">MMK<?php echo number_format($svc['price'], 2); ?></span>
                </div>
                <div class="svc-card-body">
                    <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
                    <p class="svc-desc"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                    <div class="svc-card-meta">
                        <span class="svc-meta-item"><i class="fas fa-clock"></i> <?php echo $svc['duration']; ?> min</span>
                        <span class="svc-meta-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($svc['category_name']); ?></span>
                    </div>
                </div>
                <div class="svc-card-footer">
                    <span class="svc-view-detail">Details <i class="fas fa-arrow-right"></i></span>
                    <a href="booking.php?service=<?php echo $svc['id']; ?>" class="svc-book-btn" onclick="event.stopPropagation()"><i class="fas fa-calendar-plus"></i> Book</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($staff)): ?>
<section class="section staff-section" id="staff">
    <div class="container">
        <div class="section-header">
            <span class="tag">Our Team</span>
            <h2>Meet Our <span>Artists</span></h2>
            <p>Talented nail artists dedicated to bringing your vision to life with precision and creativity.</p>
        </div>
        <div class="staff-grid">
            <?php foreach ($staff as $s): ?>
            <div class="staff-card">
                <div class="staff-photo">
                    <?php if ($s['photo']): ?>
                    <img src="/nail/assets/uploads/<?php echo htmlspecialchars($s['photo']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>">
                    <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($s['name']); ?></h3>
                <span class="staff-specialization"><?php echo htmlspecialchars($s['specialization'] ?? 'Nail Artist'); ?></span>
                <a href="booking.php?staff=<?php echo $s['id']; ?>" class="btn-sm">Book with Me</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section about-section" id="about">
    <div class="container">
        <div class="about-grid">
            <div class="about-image">
                <div class="model-frame-2" style="overflow:hidden;">
                    <img src="/nail/assets/uploads/services/decor.jpg" alt="Salon Interior" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
                </div>
                <div class="experience-badge">
                    <h3>5+</h3>
                    <p>Years of Excellence</p>
                </div>
            </div>
            <div class="about-content">
                <div class="section-header" style="text-align:left;margin-bottom:1rem;">
                    <span class="tag">About Us</span>
                </div>
                <h2>Where <span>Avocado</span> Freshness<br>Meets <span>Model</span> Elegance</h2>
                <p>At Avocado Nail & Model Studio, we believe your nails are your ultimate accessory. Our studio combines the natural, nourishing power of avocado-based treatments with cutting-edge nail artistry inspired by the latest fashion trends.</p>
                <p>Every session is designed to be a moment of self-care — whether you're preparing for a special event or treating yourself to a well-deserved break.</p>
                <div class="about-features">
                    <div class="about-feature"><i class="fas fa-check-circle"></i><span>100% Natural Products</span></div>
                    <div class="about-feature"><i class="fas fa-check-circle"></i><span>Certified Nail Artists</span></div>
                    <div class="about-feature"><i class="fas fa-check-circle"></i><span>Hygienic & Sterile</span></div>
                    <div class="about-feature"><i class="fas fa-check-circle"></i><span>Fashion-Forward Designs</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <h2>Ready for Your Next Look?</h2>
        <p>Book a session today and let our artists create something beautiful for you.</p>
        <a href="booking.php" class="btn-primary">
            <i class="fas fa-calendar-check"></i> Book Your Appointment
        </a>
    </div>
</section>

<main class="max-w-7xl mx-auto px-4 py-6">
<?php require_once '../includes/footer.php'; ?>
