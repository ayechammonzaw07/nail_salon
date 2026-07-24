<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireCustomer();

$title = 'Services';

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();

$selected_category = $_GET['category'] ?? null;

if ($selected_category) {
    $stmt = $pdo->prepare("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.category_id = ? AND s.status='active' ORDER BY s.name");
    $stmt->execute([$selected_category]);
    $services = $stmt->fetchAll();
} else {
    $services = $pdo->query("SELECT s.*, c.name as category_name FROM services s JOIN categories c ON s.category_id = c.id WHERE s.status='active' ORDER BY c.name, s.name")->fetchAll();
}

require_once '../includes/header.php';
?>
<style>
.svc-section{padding:2rem 0 4rem}
.svc-hero{text-align:center;padding:2rem 0 1.5rem}
.svc-hero h1{font-family:'Playfair Display',serif;font-size:2rem;color:var(--avocado-900);margin:0 0 .5rem}
.svc-hero h1 span{color:var(--avocado-600)}
.svc-hero p{color:var(--text-light);font-size:.95rem;max-width:500px;margin:0 auto}

.svc-filters{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-bottom:2rem}
.svc-filter-btn{padding:.55rem 1.3rem;border-radius:50px;font-size:.85rem;font-weight:600;text-decoration:none;border:2px solid var(--avocado-200);color:var(--avocado-700);background:white;transition:all .3s;cursor:pointer}
.svc-filter-btn:hover{border-color:var(--avocado-400);background:var(--avocado-50)}
.svc-filter-btn.active{background:var(--avocado-600);color:white;border-color:var(--avocado-600)}

.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;padding:0 .5rem}

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
.svc-card-footer .svc-book-btn{background:linear-gradient(135deg,var(--avocado-500),var(--avocado-600));color:white;border:none;padding:.55rem 1.2rem;border-radius:12px;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .3s;display:inline-flex;align-items:center;gap:.4rem}
.svc-card-footer .svc-book-btn:hover{background:linear-gradient(135deg,var(--avocado-600),var(--avocado-700));transform:translateY(-2px);box-shadow:0 6px 16px rgba(93,132,51,.25)}
.svc-card-footer .svc-view-detail{font-size:.78rem;color:var(--avocado-600);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem;transition:color .2s}
.svc-card-footer .svc-view-detail:hover{color:var(--avocado-800)}

.svc-empty{grid-column:1/-1;text-align:center;padding:4rem 1rem;color:var(--text-light)}
.svc-empty i{font-size:3rem;color:var(--avocado-200);margin-bottom:1rem;display:block}
.svc-empty p{font-size:1rem}

@media(max-width:640px){
    .svc-grid{grid-template-columns:1fr}
}
</style>

<div class="svc-section">
    <div class="svc-hero">
        <h1>Our <span>Services</span></h1>
        <p>Discover premium nail treatments crafted with care using natural, high-quality products.</p>
    </div>

    <div class="svc-filters">
        <a href="services.php" class="svc-filter-btn <?php echo !$selected_category ? 'active' : ''; ?>">All Services</a>
        <?php foreach ($categories as $cat): ?>
        <a href="?category=<?php echo $cat['id']; ?>" class="svc-filter-btn <?php echo $selected_category == $cat['id'] ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </a>
        <?php endforeach; ?>
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
                <a href="javascript:void(0)" class="svc-view-detail">Details <i class="fas fa-arrow-right"></i></a>
                <a href="booking.php?service=<?php echo $svc['id']; ?>" class="svc-book-btn" onclick="event.stopPropagation()"><i class="fas fa-calendar-plus"></i> Book</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
            <div class="svc-empty">
                <i class="fas fa-spa"></i>
                <p>No services available in this category yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
