<?php
require_once 'config/database.php';
require_once 'includes/session.php';

$services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name")->fetchAll();
$staff = $pdo->query("SELECT * FROM staff WHERE status='available' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avocado Nail & Model Studio — Elegance in Every Stroke</title>
    <meta name="description" content="Premium nail artistry with a fresh, modern aesthetic. Book your session today.">
    <link rel="stylesheet" href="/nail/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<nav class="navbar" id="navbar">
    <div class="container">
        <a href="/nail/index.php" class="logo">
            <i class="fas fa-leaf"></i> Avocado Nail
        </a>
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="nav-links" id="navLinks">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#staff">Our Team</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="/nail/auth/login.php">Sign In</a></li>
            <li><a href="/nail/auth/register.php" class="btn-nav">Get Started</a></li>
        </ul>
    </div>
</nav>

<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <h1>Where <span>Fresh</span> Meets<br><span>Elegant</span> Nail Art</h1>
            <p>Experience the perfect blend of modern model aesthetics and avocado-inspired freshness. Premium nail artistry crafted for the confident, elegant you.</p>
            <div class="hero-buttons">
                <a href="javascript:void(0)" onclick="handleBook()" class="btn-primary">
                    <i class="fas fa-calendar-check"></i> Book Your Session
                </a>
                <a href="#services" class="btn-outline">
                    <i class="fas fa-hand-sparkles"></i> Our Services
                </a>
            </div>
        </div>
        <div class="hero-image">
            <div class="model-frame" style="background:linear-gradient(145deg, var(--avocado-100), var(--avocado-50));overflow:hidden;">
                <span class="model-text">✦ Model Collection</span>
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

<!-- our services -->
<section class="section" id="services">
    <div class="container">
        <div class="section-header">
            <span class="tag">Our Services</span>
            <h2>Fresh <span>Avocado</span> Treatments</h2>
            <p>From classic elegance to bold model-inspired designs — every service is crafted with care using natural, high-quality products.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($services as $svc): ?>
            <a href="javascript:void(0)" onclick="handleBook()" class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition group" style="text-decoration:none;">
                <?php if ($svc['image']): ?>
                    <img src="/nail/assets/uploads/<?php echo $svc['image']; ?>" alt="" class="w-full h-48 object-cover group-hover:scale-105 transition duration-300">
                <?php else: ?>
                    <div class="w-full h-48 bg-gradient-to-br from-emerald-100 to-emerald-50 flex items-center justify-center">
                        <i class="fas fa-leaf text-emerald-300 text-5xl"></i>
                    </div>
                <?php endif; ?>
                <div class="p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($svc['name']); ?></h3>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($svc['category_name']); ?></p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-lg font-bold text-emerald-600">MMK<?php echo number_format($svc['price'], 2); ?></span>
                            <span class="text-sm text-gray-400 ml-2"><?php echo $svc['duration']; ?> min</span>
                        </div>
                        <span class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-calendar-plus mr-1"></i>Book
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- staff -->
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
                <a href="javascript:void(0)" onclick="handleBook()" class="btn-sm">Book with Me</a>
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

<!-- book -->
<section class="cta-section">
    <div class="container">
        <h2>Ready for a Fresh Look?</h2>
        <p>Join the Avocado Nail community and experience nail artistry that combines natural care with model-worthy elegance.</p>
        <a href="javascript:void(0)" onclick="handleBook()" class="btn-primary">
            <i class="fas fa-calendar-check"></i> Book Your Appointment
        </a>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <h3><i class="fas fa-leaf"></i> Avocado Nail Studio</h3>
                <p>Where fresh meets elegant. Premium nail artistry inspired by nature and fashion.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
            <li><a href="/nail/services.php">Services</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="/nail/auth/login.php">Sign In</a></li>
                    <li><a href="/nail/auth/register.php">Create Account</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Get in Touch</h4>
                <ul>
                    <li><a href="#contact"><i class="fas fa-map-marker-alt"></i> Quezon City, PH</a></li>
                    <li><a href="#contact"><i class="fas fa-phone"></i> +63 912 345 6789</a></li>
                    <li><a href="#contact"><i class="fas fa-envelope"></i> hello@avocadonail.com</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> Avocado Nail & Model Studio. All rights reserved.</span>
            <div class="social-links">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                <a href="#" aria-label="Pinterest"><i class="fab fa-pinterest"></i></a>
            </div>
        </div>
    </div>
</footer>

<script>
const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;

function handleBook() {
    if (isLoggedIn) {
        window.location.href = '/nail/customer/booking.php';
    } else {
        Swal.fire({
            icon: 'info',
            title: 'Login Required',
            text: 'Please log in or create an account to book an appointment.',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-sign-in-alt"></i> Login',
            cancelButtonText: 'Cancel',
            showClass: { popup: 'animate__animated animate__fadeInUp' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/nail/auth/login.php';
            }
        });
    }
}

// Navbar scroll effect
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    const icon = menuToggle.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
});

// Close mobile menu on link click
navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('active');
        const icon = menuToggle.querySelector('i');
        icon.classList.add('fa-bars');
        icon.classList.remove('fa-times');
    });
});

// Smooth scroll offset for fixed navbar
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            const offset = 80;
            const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
});
</script>

</body>
</html>
