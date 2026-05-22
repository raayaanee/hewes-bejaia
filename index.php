<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db       = $database->getConnection();

$activities_query = "
    SELECT a.*, at.name as type_name, at.color as type_color, at.icon as type_icon,
           COALESCE(a.booking_mode, 'timeslot') as booking_mode
    FROM activities a
    JOIN activity_types at ON a.type_id = at.id
    WHERE a.is_active = TRUE
    ORDER BY a.name
";
$activities     = $db->query($activities_query)->fetchAll();
$types_query    = "SELECT * FROM activity_types ORDER BY name";
$activity_types = $db->query($types_query)->fetchAll();
$photos_query   = "SELECT * FROM gallery ORDER BY created_at DESC LIMIT 12";
$gallery_photos = $db->query($photos_query)->fetchAll();

$isLoggedIn   = isset($_SESSION['client_id']);
$clientId     = $isLoggedIn ? (int)$_SESSION['client_id'] : 0;
$clientName   = $clientEmail = $clientPhone = $display_name = '';
$reservation_count = 0;

if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT full_name, email FROM clients WHERE id = :id");
    $stmt->execute([':id' => $clientId]);
    $client       = $stmt->fetch();
    $clientName   = $client['full_name'] ?? '';
    $clientEmail  = $client['email']     ?? '';
    $display_name = $clientName ?: $clientEmail;
    $stmt2 = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE client_id = :id");
    $stmt2->execute([':id' => $clientId]);
    $reservation_count = $stmt2->fetch()['total'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HEWES BEJAIA - Découvrez Béjaïa</title>
<link rel="website icon" type="jpg" href="images/logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
<link rel="stylesheet" href="style.css">

</head>
<body>
<div id="loading-screen" class="loading-screen">
    <div class="loading-content">
        <div class="loading-logo"><h1>HEWES BEJAIA</h1><div class="loading-wave"></div></div>
        <div class="loading-status">Chargement des activites...</div>
        <div class="loading-text">Preparez-vous pour l'aventure...</div>
    </div>
</div>
<div id="app">
<header class="header">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo"><h2>HEWES BEJAIA</h2><span class="logo-subtitle">Bejaia Adventures</span></div>
            <ul class="nav-menu">
                <li><a href="#accueil" class="nav-link">Accueil</a></li>
                <li><a href="#services" class="nav-link">Activites</a></li>
                <li><a href="#galerie"  class="nav-link">Galerie</a></li>
                <li><a href="#about"    class="nav-link">A Propos</a></li>
                <li><a href="#contact"  class="nav-link">Contact</a></li>
            </ul>
            <div class="nav-cta">
                <?php if ($isLoggedIn): ?>
                <div class="client-menu">
                    <button class="auth-btn client-name-btn" onclick="toggleClientInfo(event)">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($display_name); ?>
                        <i class="fas fa-chevron-down" style="margin-left:5px;font-size:.8em"></i>
                    </button>
                    <div class="dropdown-content" id="clientDropdown">
                        <div class="client-info-card">
                            <div class="client-info-header"><div class="client-avatar-large"><i class="fas fa-user"></i></div></div>
                            <div class="client-details">
                                <h4><?php echo htmlspecialchars($clientName ?: 'Client'); ?></h4>
                                <p class="client-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($clientEmail); ?></p>
                                <div class="client-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span><?php echo $reservation_count; ?> Reservation<?php echo $reservation_count>1?'s':''; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="client-actions-menu">
                                <a href="logout.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i> Deconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <button class="auth-btn login-btn" onclick="openAuthModal()">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
                <?php endif; ?>
            </div>
            <div class="hamburger"><span class="bar"></span><span class="bar"></span><span class="bar"></span></div>
        </div>
    </nav>
</header>

<section id="accueil" class="hero">
    <div class="hero-background">
        <div class="hero-slide active" style="background-image:url('https://media.istockphoto.com/id/1252394490/fr/photo/cape-carbon-near-bejaia-algeria.jpg?s=612x612&w=0&k=20&c=zikxqxpZY50NmNsnzkFbLGuKh0Ur8P7-nKQ1WGG38xA=')"></div>
        <div class="hero-slide" style="background-image:url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRq3AUUmiqx6lFpboXnk_EbZmVfo9sIshX-cA&s')"></div>
        <div class="hero-slide" style="background-image:url('https://media.gettyimages.com/id/164861466/fr/photo/bejaia-at-dusk.jpg?s=612x612&w=0&k=20&c=ack8iGo8KP3VgbRBvnl0FRMr3g9ZpCZIG4UQcpLyl88=')"></div>
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-badge" data-aos="fade-down" data-aos-delay="200"><i class="fas fa-star"></i><span>Reservation en Ligne</span></div>
        <h1 class="hero-title" data-aos="fade-up" data-aos-delay="400">
            Decouvrez la <span class="highlight">Perle</span> de la Mediterranee
        </h1>
        <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="600">
            Reservez vos activites en ligne et vivez des aventures inoubliables a Bejaia
        </p>
        <div class="hero-buttons" data-aos="fade-up" data-aos-delay="800">
            <a href="#services" class="btn btn-primary">
                <span>Reserver Maintenant</span><i class="fas fa-calendar-check"></i>
            </a>
            <a href="#galerie" class="btn btn-secondary">
                <i class="fas fa-play"></i><span>Voir la Galerie</span>
            </a>
        </div>
        <div class="hero-stats" data-aos="fade-up" data-aos-delay="1000">
            <div class="stat"><div class="stat-number">500+</div><div class="stat-label">Reservations</div></div>
            <div class="stat"><div class="stat-number"><?php echo count($activities); ?>+</div><div class="stat-label">Activites</div></div>
            <div class="stat"><div class="stat-number">5&#9733;</div><div class="stat-label">Note Moyenne</div></div>
        </div>
    </div>
    <div class="hero-scroll"><div class="scroll-indicator"><span>Decouvrir</span><div class="scroll-arrow"></div></div></div>
</section>

<section id="services" class="services">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-badge">Nos Services</span>
            <h2 class="section-title">Activites &amp; Reservations</h2>
            <p class="section-subtitle">Cliquez sur une activite pour reserver directement</p>
        </div>
        <div class="service-categories" data-aos="fade-up" data-aos-delay="200">
            <button class="category-btn active" data-category="all">Toutes</button>
            <?php foreach ($activity_types as $type): ?>
            <button class="category-btn" data-category="type-<?php echo $type['id']; ?>">
                <i class="<?php echo $type['icon']; ?>"></i>
                <?php echo htmlspecialchars($type['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="services-grid">
            <?php foreach ($activities as $index => $activity):
                $isNightly = ($activity['booking_mode'] === 'nightly') || ($activity['duration_minutes'] >= 1440);
                $actData   = htmlspecialchars(json_encode([
                    'id'               => (int)$activity['id'],
                    'name'             => $activity['name'],
                    'description'      => $activity['description'],
                    'image_url'        => $activity['image_url'],
                    'price'            => (float)$activity['price'],
                    'duration_minutes' => (int)$activity['duration_minutes'],
                    'max_participants' => (int)$activity['max_participants'],
                    'booking_type'     => $activity['booking_type'],
                    'booking_mode'     => $activity['booking_mode'],
                ]), ENT_QUOTES);
                $h = floor($activity['duration_minutes']/60);
                $m = $activity['duration_minutes']%60;
                $durStr = $h.'h'.($m>0?' '.$m.'min':'');
            ?>
            <div class="service-card type-<?php echo $activity['type_id']; ?>"
                 data-aos="fade-up" data-aos-delay="<?php echo $index*100; ?>">
                <?php if($activity['name']=== 'Visite Guidee Complete'): ?><div class="service-badge">Populaire</div><?php endif; ?>
                <div class="service-image">
                    <img src="<?php echo htmlspecialchars($activity['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($activity['name']); ?>">
                    <div class="service-overlay"><i class="<?php echo $activity['type_icon']; ?>"></i></div>
                </div>
                <div class="service-content">
                    <h3><?php echo htmlspecialchars($activity['name']); ?></h3>
                    <p><?php echo htmlspecialchars($activity['description']); ?></p>
                    <div class="service-features">
                        <span><i class="fas fa-clock"></i> <?php echo $isNightly?'Sejour/nuit':$durStr; ?></span>
                        <span><i class="fas fa-users"></i> Max <?php echo $activity['max_participants']; ?></span>
                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($activity['type_name']); ?></span>
                    </div>
                    <div class="service-price">
                        <span class="price">
                            <?php if($activity['price']>0): ?>
                                <?php echo number_format($activity['price'],0,',',' '); ?> DA
                            <?php else: ?>Sur demande<?php endif; ?>
                        </span>
                        <?php if($activity['price']>0): ?>
                        <span class="price-unit"><?php echo $isNightly?'/nuit':($activity['booking_type']=='shared'?'/personne':'/groupe'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if($isNightly): ?>
                    <button class="reserve-btn" onclick="openHebergementModal(<?php echo (int)$activity['id']; ?>)">
                        <i class="fas fa-house"></i> Voir les hebergements
                    </button>
                    <?php else: ?>
                    <button class="reserve-btn" onclick='openBookingModal(<?php echo $actData; ?>)'>
                        <i class="fas fa-calendar-check"></i> Reserver Maintenant
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="galerie" class="gallery-section">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-badge">Galerie</span>
            <h2 class="section-title">Decouvrez Bejaia en Images</h2>
            <p class="section-subtitle">Une selection de photos des plus beaux endroits</p>
        </div>
        <div class="photos-grid">
            <?php if(count($gallery_photos)>0): ?>
                <?php foreach($gallery_photos as $photo): ?>
                <div class="photo-card">
                    <div class="photo-container">
                        <img src="<?php echo htmlspecialchars($photo['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($photo['title']); ?>">
                        <div class="photo-overlay">
                            <div class="photo-info">
                                <p class="caption"><?php echo htmlspecialchars($photo['title']); ?></p>
                                <?php if(!empty($photo['description'])): ?>
                                <p class="description"><?php echo htmlspecialchars($photo['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content"><i class="fas fa-images"></i><p>Aucune photo disponible.</p></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="about" class="about">
    <div class="container">
        <div class="about-grid">
            <div class="about-content" data-aos="fade-right">
                <span class="section-badge">A Propos</span>
                <h2 class="section-title">Systeme de Reservation Intelligent</h2>
                <p class="about-text">Reservez vos activites en quelques clics. Creaneaux en temps reel, confirmation instantanee.</p>
                <div class="about-features">
                    <div class="feature-item"><div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="feature-content"><h4>Reservation en Temps Reel</h4><p>Creneaux disponibles et reservation instantanee</p></div></div>
                    <div class="feature-item"><div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="feature-content"><h4>Interface Mobile</h4><p>Reservez depuis votre smartphone</p></div></div>
                    <div class="feature-item"><div class="feature-icon"><i class="fab fa-whatsapp"></i></div>
                        <div class="feature-content"><h4>Confirmation WhatsApp</h4><p>Recevez votre confirmation sur WhatsApp</p></div></div>
                </div>
            </div>
            <div class="about-image" data-aos="fade-left">
                <div class="image-container">
                    <img src="https://i.postimg.cc/SQtfRFGR/IMG-9625.jpg" alt="Reservation">
                    <div class="image-overlay">
                        <a href="#services" class="play-button"><i class="fas fa-calendar-check"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="contact" class="contact">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-badge">Contact</span>
            <h2 class="section-title">Besoin d'Aide ?</h2>
        </div>
        <div class="contact-grid">
            <div class="contact-info" data-aos="fade-right">
                <div class="contact-card">
                    <h3>Contact</h3>
                    <div class="contact-item"><div class="contact-icon"><i class="fas fa-phone"></i></div><div class="contact-content"><h4>Telephone</h4><p>+213 775 654 995</p></div></div>
                    <div class="contact-item"><div class="contact-icon"><i class="fab fa-whatsapp"></i></div><div class="contact-content"><h4>WhatsApp</h4><p>Reservations &amp; Support</p></div></div>
                    <div class="contact-item"><div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div><div class="contact-content"><h4>Localisation</h4><p>Bejaia, Algerie</p></div></div>
                    <div class="contact-item"><div class="contact-icon"><i class="fas fa-clock"></i></div><div class="contact-content"><h4>Horaires</h4><p>Tous les jours 8h-18h</p></div></div>
                </div>
            </div>
            <div class="contact-form-container" data-aos="fade-left">
                <div class="contact-card">
                    <h3>Service Client</h3>
                    <form id="whatsappForm">
                        <div class="form-group"><input type="text" id="cnt-name" placeholder="Votre nom" required></div>
                        <div class="form-group"><input type="email" id="cnt-email" placeholder="Votre email" required></div>
                        <div class="form-group"><input type="text" id="cnt-subject" placeholder="Sujet" required></div>
                        <div class="form-group"><textarea id="cnt-msg" rows="4" placeholder="Votre message..." required></textarea></div>
                        <button type="submit" class="btn btn-primary"><i class="fab fa-whatsapp"></i> Envoyer sur WhatsApp</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-logo"><h3>HEWES BEJAIA</h3><p>Votre partenaire pour decouvrir Bejaia</p></div>
                <div class="footer-social">
                    <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/213775654995" class="social-link"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Services</h4>
                <ul>
                    <li><a href="#services">Activites Nautiques</a></li>
                    <li><a href="#services">Activites Terrestres</a></li>
                    <li><a href="#services">Hebergement</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Liens</h4>
                <ul>
                    <li><a href="#accueil">Accueil</a></li>
                    <li><a href="#services">Activites</a></li>
                    <li><a href="#galerie">Galerie</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact</h4>
                <div class="footer-contact">
                    <p><i class="fas fa-phone"></i> +213 775 654 995</p>
                    <p><i class="fab fa-whatsapp"></i> WhatsApp</p>
                    <p><i class="fas fa-map-marker-alt"></i> Bejaia, Algerie</p>
                </div>
            </div>
        </div>
        <div class="footer-bottom"><p>&copy; 2025 HEWES BEJAIA. Tous droits reserves.</p></div>
    </div>
</footer>

<a href="https://wa.me/213775654995" class="whatsapp-float" title="Support"><i class="fab fa-whatsapp"></i><span class="whatsapp-text">Support</span></a>
<button id="backToTop" class="back-to-top"><i class="fas fa-arrow-up"></i></button>

</div><!-- /app -->

<!-- Modal Hebergement -->
<div id="hbModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.65);z-index:9999;overflow-y:auto;padding:20px;box-sizing:border-box">
    <div style="max-width:1000px;margin:40px auto;background:#fff;border-radius:20px;padding:28px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <button onclick="closeHebergementModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#6b7280;width:36px;height:36px;border-radius:50%">x</button>
        <h2 style="font-size:1.3rem;font-weight:800;color:#111827;margin:0 0 6px">
            <i class="fas fa-house" style="color:#0066cc;margin-right:.5rem"></i>
            Hebergements disponibles
        </h2>
        <p style="font-size:.85rem;color:#6b7280;margin:0 0 20px">Selectionnez un logement et choisissez vos dates</p>
        <div id="hb-root"></div>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="main.js"></script>
<script src="js/auth-modal.js"></script>
<script src="js/booking-system.js"></script>
<script src="js/booking-hebergement.js"></script>

<script>
const isLoggedIn = <?php echo $isLoggedIn?'true':'false'; ?>;

// Transmettre l'etat auth au booking system
bookingSystem.setAuth(
    <?php echo $isLoggedIn?'true':'false'; ?>,
    <?php echo $isLoggedIn?$clientId:'null'; ?>,
    <?php echo json_encode($clientName); ?>,
    <?php echo json_encode($clientEmail); ?>,
    ""
);

// Modal hebergement
function openHebergementModal(activityId) {
    if (!isLoggedIn) {
        sessionStorage.setItem('hb_pending_id', String(activityId));
        openAuthModal();
        return;
    }
    _doOpenHbModal(activityId);
}
function _doOpenHbModal(activityId) {
    var modal = document.getElementById('hbModal');
    var root  = document.getElementById('hb-root');
    if (!modal || !root) return;
    root.innerHTML = '';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    if (typeof HebergementBooking !== 'undefined') {
        HebergementBooking.init(root, activityId);
    }
}
function closeHebergementModal() {
    document.getElementById('hbModal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('hb-root').innerHTML = '';
}
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'hbModal') closeHebergementModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeHebergementModal(); bookingSystem.closeModal(); }
});

// Menu client
function toggleClientInfo(event) {
    event.stopPropagation();
    var dd = document.getElementById('clientDropdown');
    if (dd) dd.classList.toggle('show');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('clientDropdown');
    var cm = document.querySelector('.client-menu');
    if (dd && cm && !cm.contains(e.target)) dd.classList.remove('show');
});

// Window load
window.addEventListener('load', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auth_required') === '1') openAuthModal();
    if (urlParams.toString()) window.history.replaceState({}, document.title, window.location.pathname);

    setTimeout(function() {
        var ls = document.getElementById('loading-screen');
        if (ls) { ls.style.opacity = '0'; setTimeout(function(){ ls.style.display='none'; }, 500); }

        if (isLoggedIn) {
            // Rouvrir booking apres login
            bookingSystem.checkPendingBooking();
            // Rouvrir hebergement apres login
            var hbPending = sessionStorage.getItem('hb_pending_id');
            if (hbPending) {
                sessionStorage.removeItem('hb_pending_id');
                setTimeout(function(){ _doOpenHbModal(parseInt(hbPending,10)); }, 550);
            }
        }
    }, 1000);
});

AOS.init({ duration: 800, once: true });

document.getElementById('whatsappForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var n = document.getElementById('cnt-name').value.trim();
    var s = document.getElementById('cnt-subject').value.trim();
    var m = document.getElementById('cnt-msg').value.trim();
    window.open('https://wa.me/213775654995?text='+encodeURIComponent('Bonjour, je suis '+n+'.\nSujet: '+s+'\n\n'+m), '_blank');
});
</script>
</body>
</html>