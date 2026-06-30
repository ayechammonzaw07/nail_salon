<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Home';

$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name, s.price, s.duration, st.name as staff_name, st.photo as staff_photo,
           c.name as category_name
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN staff st ON a.staff_id = st.id
    JOIN categories c ON s.category_id = c.id
    WHERE a.customer_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('completed', 'cancelled')
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming = $stmt->fetchAll();

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
                <img src="/nail_salon/assets/uploads/services/home.jpg" alt="Nail Salon" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
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

<?php if (!empty($upcoming)): ?>
<section class="section" style="padding:3rem 2rem;">
    <div class="container">
        <div class="section-header">
            <span class="tag">Upcoming</span>
            <h2>Your <span>Appointments</span></h2>
            <p>Here are your scheduled sessions. Can't make it? Let us know in advance.</p>
        </div>
        <div style="max-width:800px;margin:0 auto;">
            <?php foreach ($upcoming as $apt): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;background:white;padding:1.2rem 1.5rem;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,0.04);border:1px solid rgba(124,179,66,0.1);margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <div style="background:var(--avocado-100);border-radius:12px;padding:0.7rem 1rem;text-align:center;min-width:60px;">
                        <p style="font-size:1.3rem;font-weight:700;color:var(--avocado-700);margin:0;"><?php echo date('d', strtotime($apt['appointment_date'])); ?></p>
                        <p style="font-size:0.7rem;color:var(--avocado-500);text-transform:uppercase;font-weight:600;margin:0;"><?php echo date('M', strtotime($apt['appointment_date'])); ?></p>
                    </div>
                    <div>
                        <p style="font-weight:600;color:var(--dark);margin:0;"><?php echo htmlspecialchars($apt['service_name']); ?></p>
                        <p style="font-size:0.85rem;color:var(--text-light);margin:0.2rem 0 0;">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($apt['staff_name']); ?>
                            &nbsp;&nbsp;<i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>
                        </p>
                    </div>
                </div>
                <span style="padding:0.3rem 0.8rem;border-radius:50px;font-size:0.75rem;font-weight:600;
                    <?php echo match($apt['status']) {
                        'pending' => 'background:#fef3c7;color:#b45309;',
                        'confirmed' => 'background:#dbeafe;color:#1d4ed8;',
                        'in_progress' => 'background:#ede9fe;color:#7c3aed;',
                        default => 'background:#f3f4f6;color:#6b7280;'
                    }; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:1.5rem;">
            <a href="appointments.php" class="btn-outline" style="font-size:0.9rem;">
                <i class="fas fa-list"></i> View All Appointments
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="services">
    <div class="container">
        <div class="section-header">
            <span class="tag">Our Services</span>
            <h2>Fresh <span>Avocado</span> Treatments</h2>
            <p>From classic elegance to bold model-inspired designs — every service is crafted with care using natural, high-quality products.</p>
        </div>
        <div class="services-grid">
            <?php foreach ($services as $svc): ?>
            <a href="booking.php?service=<?php echo $svc['id']; ?>" class="service-card" style="text-decoration:none;display:block;">
                <div class="icon"><i class="fas fa-hand-sparkles"></i></div>
                <h3><?php echo htmlspecialchars($svc['name']); ?></h3>
                <p><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--avocado-100);">
                    <span style="font-weight:700;color:var(--avocado-600);font-size:1.1rem;">MMK<?php echo number_format($svc['price'], 2); ?></span>
                    <span style="font-size:0.8rem;color:var(--text-light);"><?php echo $svc['duration']; ?> min</span>
                </div>
            </a>
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
                    <img src="/nail_salon/assets/uploads/<?php echo htmlspecialchars($s['photo']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>">
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
                    <img src="/nail_salon/assets/uploads/services/decor.jpg" alt="Salon Interior" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
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

<!-- Contact -->
<section class="section" id="contact" style="background:white;">
    <div class="container">
        <div class="section-header">
            <span class="tag">Get in Touch</span>
            <h2><span>Contact</span> Us</h2>
            <p>We'd love to hear from you. Reach out for bookings, inquiries, or just to say hello.</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:start;max-width:1000px;margin:0 auto;">
            <div>
                <div style="display:flex;flex-direction:column;gap:1.5rem;">
                    <div style="display:flex;align-items:flex-start;gap:1rem;">
                        <div style="width:50px;height:50px;background:var(--avocado-50);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-map-marker-alt" style="color:var(--avocado-600);font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--avocado-900);margin:0 0 0.3rem;">Visit Us</h4>
                            <p style="color:var(--text-light);font-size:0.9rem;line-height:1.6;margin:0;">123 Avocado Lane, Quezon City<br>Metro Manila, Philippines</p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:1rem;">
                        <div style="width:50px;height:50px;background:var(--avocado-50);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-phone-alt" style="color:var(--avocado-600);font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--avocado-900);margin:0 0 0.3rem;">Call Us</h4>
                            <p style="color:var(--text-light);font-size:0.9rem;line-height:1.6;margin:0;">+63 912 345 6789<br>+63 998 765 4321</p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:1rem;">
                        <div style="width:50px;height:50px;background:var(--avocado-50);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-envelope" style="color:var(--avocado-600);font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--avocado-900);margin:0 0 0.3rem;">Email Us</h4>
                            <p style="color:var(--text-light);font-size:0.9rem;line-height:1.6;margin:0;">hello@avocadonail.com<br>bookings@avocadonail.com</p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:1rem;">
                        <div style="width:50px;height:50px;background:var(--avocado-50);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-clock" style="color:var(--avocado-600);font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--avocado-900);margin:0 0 0.3rem;">Opening Hours</h4>
                            <p style="color:var(--text-light);font-size:0.9rem;line-height:1.6;margin:0;">Monday — Saturday: 9:00 AM — 7:00 PM<br>Sunday: 10:00 AM — 5:00 PM</p>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:0.8rem;margin-top:2rem;padding-top:2rem;border-top:1px solid var(--avocado-100);">
                    <a href="#" style="width:44px;height:44px;border-radius:50%;background:var(--avocado-50);display:flex;align-items:center;justify-content:center;color:var(--avocado-600);transition:all 0.3s;text-decoration:none;" onmouseover="this.style.background='var(--avocado-600)';this.style.color='white';" onmouseout="this.style.background='var(--avocado-50)';this.style.color='var(--avocado-600)';"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" style="width:44px;height:44px;border-radius:50%;background:var(--avocado-50);display:flex;align-items:center;justify-content:center;color:var(--avocado-600);transition:all 0.3s;text-decoration:none;" onmouseover="this.style.background='var(--avocado-600)';this.style.color='white';" onmouseout="this.style.background='var(--avocado-50)';this.style.color='var(--avocado-600)';"><i class="fab fa-instagram"></i></a>
                    <a href="#" style="width:44px;height:44px;border-radius:50%;background:var(--avocado-50);display:flex;align-items:center;justify-content:center;color:var(--avocado-600);transition:all 0.3s;text-decoration:none;" onmouseover="this.style.background='var(--avocado-600)';this.style.color='white';" onmouseout="this.style.background='var(--avocado-50)';this.style.color='var(--avocado-600)';"><i class="fab fa-tiktok"></i></a>
                    <a href="#" style="width:44px;height:44px;border-radius:50%;background:var(--avocado-50);display:flex;align-items:center;justify-content:center;color:var(--avocado-600);transition:all 0.3s;text-decoration:none;" onmouseover="this.style.background='var(--avocado-600)';this.style.color='white';" onmouseout="this.style.background='var(--avocado-50)';this.style.color='var(--avocado-600)';"><i class="fab fa-pinterest-p"></i></a>
                </div>
            </div>
            <div style="background:var(--avocado-50);border-radius:20px;padding:2.5rem;">
                <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--avocado-900);margin:0 0 0.5rem;">Send a Message</h3>
                <p style="color:var(--text-light);font-size:0.9rem;margin:0 0 1.5rem;">We'll get back to you within 24 hours.</p>
                <form method="POST" action="" style="display:flex;flex-direction:column;gap:1rem;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <input type="text" placeholder="Your Name" required style="width:100%;padding:0.9rem 1rem;border:2px solid transparent;border-radius:12px;font-size:0.9rem;background:white;outline:none;transition:all 0.3s;" onfocus="this.style.borderColor='var(--avocado-400)';" onblur="this.style.borderColor='transparent';">
                        <input type="email" placeholder="Your Email" required style="width:100%;padding:0.9rem 1rem;border:2px solid transparent;border-radius:12px;font-size:0.9rem;background:white;outline:none;transition:all 0.3s;" onfocus="this.style.borderColor='var(--avocado-400)';" onblur="this.style.borderColor='transparent';">
                    </div>
                    <input type="text" placeholder="Subject" style="width:100%;padding:0.9rem 1rem;border:2px solid transparent;border-radius:12px;font-size:0.9rem;background:white;outline:none;transition:all 0.3s;" onfocus="this.style.borderColor='var(--avocado-400)';" onblur="this.style.borderColor='transparent';">
                    <textarea rows="4" placeholder="Your Message" required style="width:100%;padding:0.9rem 1rem;border:2px solid transparent;border-radius:12px;font-size:0.9rem;background:white;outline:none;resize:vertical;transition:all 0.3s;" onfocus="this.style.borderColor='var(--avocado-400)';" onblur="this.style.borderColor='transparent';"></textarea>
                    <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:1rem;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
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
