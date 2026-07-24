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
                    <a href="javascript:void(0)" class="svc-book-btn" onclick="event.stopPropagation();handleBook()"><i class="fas fa-calendar-plus"></i> Book</a>
                </div>
            </div>
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

<!-- about -->
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
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="/nail/auth/login.php">Sign In</a></li>
                    <li><a href="/nail/auth/register.php">Create Account</a></li>
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
