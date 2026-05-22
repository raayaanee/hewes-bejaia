// ============================================================
// MAIN.JS — HEWES BEJAIA
// ============================================================

// ============================================================
// INITIALISATION AOS (Animate On Scroll)
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
  AOS.init({
    duration: 800,
    easing: 'ease-in-out',
    once: true,
    offset: 100
  });

  initializeApp();
});

// ============================================================
// INITIALISATION DE L'APPLICATION
// ============================================================
async function initializeApp() {
  try {
    console.log('🌊 Hawas Bjaya - Application initialisée pour WAMP/MySQL');
  } catch (error) {
    console.error('Erreur d\'initialisation:', error);
  }
}

// ============================================================
// UTILITAIRES
// ============================================================
function getActivityIcon(category) {
  const icons = {
    'nautique':    'water',
    'terrestre':   'mountain',
    'hebergement': 'bed'
  };
  return icons[category] || 'star';
}

function formatDuration(minutes) {
  if (minutes < 60)   return `${minutes}min`;
  if (minutes < 1440) return `${Math.floor(minutes / 60)}h${minutes % 60 > 0 ? ` ${minutes % 60}min` : ''}`;
  return `${Math.floor(minutes / 1440)} jour${Math.floor(minutes / 1440) > 1 ? 's' : ''}`;
}

// ============================================================
// LOADING SCREEN
// Disparaît 1,5 seconde après le chargement complet de la page
// ============================================================
window.addEventListener('load', function() {
  const loadingScreen = document.getElementById('loading-screen');
  setTimeout(() => {
    loadingScreen.classList.add('hidden');
  }, 1500);
});

// ============================================================
// NAVIGATION MOBILE (HAMBURGER)
// ============================================================
const hamburger = document.querySelector('.hamburger');
const navMenu   = document.querySelector('.nav-menu');

if (hamburger && navMenu) {
  // Ouvrir / fermer le menu au clic sur le hamburger
  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
  });

  // Fermer le menu quand on clique sur un lien
  document.querySelectorAll('.nav-link').forEach(n => n.addEventListener('click', () => {
    hamburger.classList.remove('active');
    navMenu.classList.remove('active');
  }));

  // Fermer le menu quand on clique en dehors
  document.addEventListener('click', (e) => {
    if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
      hamburger.classList.remove('active');
      navMenu.classList.remove('active');
    }
  });
}

// ============================================================
// DÉFILEMENT FLUIDE (SMOOTH SCROLL)
// Intercepte les clics sur les ancres (#section) et anime
// le défilement en tenant compte de la hauteur du header fixe
// ============================================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      const headerHeight    = document.querySelector('.header').offsetHeight;
      const targetPosition  = target.offsetTop - headerHeight;
      window.scrollTo({ top: targetPosition, behavior: 'smooth' });
    }
  });
});

// ============================================================
// HEADER : FOND AU SCROLL + MASQUAGE EN DESCENDANT
// — Fond semi-opaque ajouté après 100px de scroll
// — Header masqué quand on descend, réapparu quand on remonte
// ============================================================
const header = document.querySelector('.header');
let lastScrollY = window.scrollY;

window.addEventListener('scroll', () => {
  const currentScrollY = window.scrollY;

  // Ajouter un fond blanc quand on scrolle
  if (currentScrollY > 100) {
    header.classList.add('scrolled');
  } else {
    header.classList.remove('scrolled');
  }

  // Masquer/afficher le header selon la direction du scroll
  if (currentScrollY > lastScrollY && currentScrollY > 200) {
    header.style.transform = 'translateY(-100%)'; // on descend → cacher
  } else {
    header.style.transform = 'translateY(0)';      // on remonte → montrer
  }

  lastScrollY = currentScrollY;
});

// ============================================================
// LIEN DE NAVIGATION ACTIF AU SCROLL (SCROLL SPY)
//
// Quand l'utilisateur scrolle, on détecte quelle section
// est visible à l'écran et on met en surbrillance le lien
// correspondant dans la navbar (classe CSS .active).
//
// Exemple : scroll vers #services → le lien "Activités" s'allume
// ============================================================
(function initScrollSpy() {

  // Sélectionner tous les liens nav qui pointent vers une ancre (#...)
  const navLinks = document.querySelectorAll('.nav-link[href^="#"]');

  // Construire la liste des sections qui correspondent aux liens
  const sections = [];
  navLinks.forEach(function(link) {
    const id      = link.getAttribute('href').replace('#', ''); // ex: "services"
    const section = document.getElementById(id);               // <section id="services">
    if (section) sections.push(section);
  });

  // Fonction : retire .active de tous les liens, puis l'ajoute
  // uniquement sur celui qui correspond à l'id passé en argument
  function setActiveLink(sectionId) {
    navLinks.forEach(function(link) {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + sectionId) {
        link.classList.add('active');
      }
    });
  }

  // IntersectionObserver : se déclenche quand une section
  // occupe au moins 30% de la zone centrale de l'écran
  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        setActiveLink(entry.target.id);
      }
    });
  }, {
    root:       null,               // viewport = fenêtre du navigateur
    rootMargin: '-20% 0px -20% 0px', // ignorer les 20% haut/bas (évite les conflits de transitions)
    threshold:  0.3                 // 30% de la section doit être visible
  });

  // Observer chaque section
  sections.forEach(function(section) {
    observer.observe(section);
  });

  // Cas spécial : tout en haut de la page → activer "Accueil"
  window.addEventListener('scroll', function() {
    if (window.scrollY < 100) {
      setActiveLink('accueil');
    }
  });

  // Par défaut au chargement : activer "Accueil"
  setActiveLink('accueil');

})(); // Fonction auto-exécutée pour ne pas polluer le scope global

// ============================================================
// SLIDESHOW HERO
// Change de slide toutes les 5 secondes
// ============================================================
const heroSlides  = document.querySelectorAll('.hero-slide');
let   currentSlide = 0;

function nextSlide() {
  heroSlides[currentSlide].classList.remove('active');
  currentSlide = (currentSlide + 1) % heroSlides.length;
  heroSlides[currentSlide].classList.add('active');
}

setInterval(nextSlide, 5000);

// ============================================================
// FILTRE DES ACTIVITÉS (catégories)
// Montre/cache les cartes selon la catégorie sélectionnée
// ============================================================
const categoryBtns = document.querySelectorAll('.category-btn');
const serviceCards = document.querySelectorAll('.service-card');

categoryBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    categoryBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const category = btn.dataset.category;

    serviceCards.forEach(card => {
      if (category === 'all' || card.classList.contains(category)) {
        card.style.display = 'block';
        // Relancer l'animation AOS
        card.classList.remove('aos-animate');
        setTimeout(() => card.classList.add('aos-animate'), 100);
      } else {
        card.style.display = 'none';
      }
    });
  });
});

// ============================================================
// FILTRE DE LA GALERIE
// ============================================================
const filterBtns   = document.querySelectorAll('.filter-btn');
const galleryItems = document.querySelectorAll('.gallery-item');

filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const filter = btn.dataset.filter;

    galleryItems.forEach(item => {
      if (filter === 'all' || item.classList.contains(filter)) {
        item.style.display = 'block';
        item.classList.remove('aos-animate');
        setTimeout(() => item.classList.add('aos-animate'), 100);
      } else {
        item.style.display = 'none';
      }
    });
  });
});

// ============================================================
// MODAL IMAGE (clic sur un élément galerie)
// ============================================================
document.querySelectorAll('.gallery-item').forEach(item => {
  item.addEventListener('click', function() {
    const img         = this.querySelector('img');
    const title       = this.querySelector('h4').textContent;
    const description = this.querySelector('p').textContent;

    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.innerHTML = `
      <div class="modal-backdrop"></div>
      <div class="modal-content">
        <button class="close-modal"><i class="fas fa-times"></i></button>
        <div class="modal-image"><img src="${img.src}" alt="${title}"></div>
        <div class="modal-info">
          <h3>${title}</h3>
          <p>${description}</p>
          <button class="modal-whatsapp">
            <i class="fab fa-whatsapp"></i> Réserver cette activité
          </button>
        </div>
      </div>
    `;

    if (!document.querySelector('#modal-styles')) {
      const styleSheet = document.createElement('style');
      styleSheet.id = 'modal-styles';
      styleSheet.textContent = `
        .image-modal { position:fixed;top:0;left:0;width:100%;height:100%;z-index:2000;display:flex;align-items:center;justify-content:center;padding:2rem;opacity:0;animation:modalFadeIn .3s ease forwards; }
        .modal-backdrop { position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);backdrop-filter:blur(10px); }
        .modal-content { position:relative;background:#fff;border-radius:1.5rem;overflow:hidden;max-width:800px;max-height:90vh;width:100%;display:grid;grid-template-columns:1fr 300px;box-shadow:0 25px 50px rgba(0,0,0,.5);transform:scale(.9);animation:modalScaleIn .3s ease .1s forwards; }
        .close-modal { position:absolute;top:1rem;right:1rem;width:40px;height:40px;background:rgba(0,0,0,.5);border:none;border-radius:50%;color:#fff;font-size:1.2rem;cursor:pointer;z-index:10;transition:all .3s ease; }
        .close-modal:hover { background:rgba(0,0,0,.8);transform:scale(1.1); }
        .modal-image { position:relative; }
        .modal-image img { width:100%;height:100%;object-fit:cover; }
        .modal-info { padding:2rem;display:flex;flex-direction:column;justify-content:center; }
        .modal-info h3 { font-size:1.5rem;font-weight:700;margin-bottom:1rem;color:var(--gray-900); }
        .modal-info p { color:var(--gray-600);line-height:1.6;margin-bottom:2rem; }
        .modal-whatsapp { background:var(--success-color);color:#fff;border:none;padding:1rem 1.5rem;border-radius:.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.5rem;justify-content:center;transition:all .3s ease; }
        .modal-whatsapp:hover { background:#059669;transform:translateY(-2px); }
        @keyframes modalFadeIn { to { opacity:1; } }
        @keyframes modalScaleIn { to { transform:scale(1); } }
        @media(max-width:768px){.modal-content{grid-template-columns:1fr;max-height:80vh;}.modal-info{padding:1.5rem;}}
      `;
      document.head.appendChild(styleSheet);
    }

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    const closeModal = () => {
      modal.style.opacity = '0';
      setTimeout(() => {
        if (document.body.contains(modal)) document.body.removeChild(modal);
        document.body.style.overflow = '';
      }, 300);
    };

    modal.querySelector('.close-modal').addEventListener('click', closeModal);
    modal.querySelector('.modal-backdrop').addEventListener('click', closeModal);
    modal.querySelector('.modal-whatsapp').addEventListener('click', () => {
      const msg = `Bonjour, je souhaite réserver l'activité "${title}". Pourriez-vous me donner plus d'informations sur les disponibilités et les tarifs ?`;
      window.open(`https://wa.me/213775654995?text=${encodeURIComponent(msg)}`, '_blank');
      closeModal();
    });

    const handleEscape = (e) => {
      if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', handleEscape); }
    };
    document.addEventListener('keydown', handleEscape);
  });
});

// ============================================================
// FORMULAIRE CONTACT → WHATSAPP
// ============================================================
const whatsappForm = document.getElementById('whatsappForm');
if (whatsappForm) {
  whatsappForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const name    = document.getElementById('name').value;
    const email   = document.getElementById('email').value;
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;

    const whatsappMessage =
`🌊 *Nouvelle Demande - HEWES BEJAIA*

👤 *Nom:* ${name}
📧 *Email:* ${email}
🎯 *Sujet:* ${subject}

💬 *Message:*
${message}`;

    window.open(`https://wa.me/213775654995?text=${encodeURIComponent(whatsappMessage)}`, '_blank');
    showNotification('Votre message sera envoyé via WhatsApp !', 'success');

    whatsappForm.style.opacity = '0.5';
    setTimeout(() => { whatsappForm.reset(); whatsappForm.style.opacity = '1'; }, 500);
  });
}

// Garder compatibilité avec l'ancien id "contactForm" si présent
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const name    = contactForm.querySelector('input[type="text"]').value;
    const email   = contactForm.querySelector('input[type="email"]').value;
    const phone   = contactForm.querySelector('input[type="tel"]') ? contactForm.querySelector('input[type="tel"]').value : '';
    const service = contactForm.querySelector('select') ? contactForm.querySelector('select').value : '';
    const message = contactForm.querySelector('textarea').value;

    const msg =
`🌊 *Nouvelle Demande - Hawas Bjaya*
👤 *Nom:* ${name}
📧 *Email:* ${email}
📱 *Téléphone:* ${phone}
🎯 *Service:* ${service}
💬 *Message:*\n${message}`;

    window.open(`https://wa.me/213775654995?text=${encodeURIComponent(msg)}`, '_blank');
    showNotification('Votre demande sera traitée via WhatsApp !', 'success');
    contactForm.style.opacity = '0.5';
    setTimeout(() => { contactForm.reset(); contactForm.style.opacity = '1'; }, 500);
  });
}

// ============================================================
// SYSTÈME DE NOTIFICATION (toast en haut à droite)
// ============================================================
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;

  const icon = type === 'success' ? 'fas fa-check-circle'
             : type === 'error'   ? 'fas fa-exclamation-circle'
             :                      'fas fa-info-circle';

  notification.innerHTML = `<i class="${icon}"></i><span>${message}</span>`;

  if (!document.querySelector('#notification-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'notification-styles';
    styleSheet.textContent = `
      .notification { position:fixed;top:2rem;right:2rem;background:#fff;color:var(--gray-800);padding:1rem 1.5rem;border-radius:.75rem;box-shadow:0 10px 25px rgba(0,0,0,.15);z-index:3000;transform:translateX(110%);transition:transform .3s ease;display:flex;align-items:center;gap:.75rem;min-width:300px;border-left:4px solid; }
      .notification-success { border-left-color:var(--success-color); }
      .notification-success i { color:var(--success-color); }
      .notification-error { border-left-color:var(--error-color); }
      .notification-error i { color:var(--error-color); }
      .notification-info { border-left-color:var(--primary-color); }
      .notification-info i { color:var(--primary-color); }
    `;
    document.head.appendChild(styleSheet);
  }

  document.body.appendChild(notification);
  setTimeout(() => notification.style.transform = 'translateX(0)', 100);
  setTimeout(() => {
    notification.style.transform = 'translateX(110%)';
    setTimeout(() => { if (document.body.contains(notification)) document.body.removeChild(notification); }, 300);
  }, 4000);
}

// ============================================================
// BOUTON RETOUR EN HAUT
// ============================================================
const backToTopBtn = document.getElementById('backToTop');

if (backToTopBtn) {
  window.addEventListener('scroll', () => {
    backToTopBtn.classList.toggle('visible', window.scrollY > 500);
  });

  backToTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ============================================================
// EFFET PARALLAXE SUR LE HERO
// Le fond descend plus lentement que le contenu
// ============================================================
window.addEventListener('scroll', () => {
  const scrolled     = window.pageYOffset;
  const hero         = document.querySelector('.hero');
  const heroContent  = document.querySelector('.hero-content');

  if (hero && scrolled < hero.offsetHeight) {
    hero.style.transform        = `translateY(${scrolled * 0.5}px)`;
    heroContent.style.transform = `translateY(${scrolled * 0.3}px)`;
  }
});

// ============================================================
// CHARGEMENT DES IMAGES (fondu au chargement)
// ============================================================
document.querySelectorAll('img').forEach(img => {
  img.addEventListener('load', function() { this.style.opacity = '1'; });
  if (!img.complete) {
    img.style.opacity    = '0';
    img.style.transition = 'opacity 0.3s ease';
  }
});

// ============================================================
// ANIMATION DES COMPTEURS (hero stats)
// Les chiffres s'incrémentent quand ils entrent dans le viewport
// ============================================================
const observeCounters = () => {
  const counters = document.querySelectorAll('.stat-number');

  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const counter   = entry.target;
        const target    = parseInt(counter.textContent.replace(/\D/g, ''));
        const suffix    = counter.textContent.replace(/\d/g, '');
        let   current   = 0;
        const increment = target / 50;

        const updateCounter = () => {
          if (current < target) {
            current += increment;
            counter.textContent = Math.ceil(current) + suffix;
            requestAnimationFrame(updateCounter);
          } else {
            counter.textContent = target + suffix;
          }
        };

        updateCounter();
        counterObserver.unobserve(counter);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(counter => counterObserver.observe(counter));
};

document.addEventListener('DOMContentLoaded', observeCounters);

// ============================================================
// EFFET RIPPLE sur les boutons (cercle qui s'étend au clic)
// ============================================================
document.querySelectorAll('.btn, .submit-btn, .category-btn, .filter-btn').forEach(button => {
  button.addEventListener('click', function(e) {
    const ripple = document.createElement('span');
    const rect   = this.getBoundingClientRect();
    const size   = Math.max(rect.width, rect.height);
    const x      = e.clientX - rect.left  - size / 2;
    const y      = e.clientY - rect.top   - size / 2;

    ripple.style.cssText = `
      position:absolute;width:${size}px;height:${size}px;
      left:${x}px;top:${y}px;
      background:rgba(255,255,255,0.3);border-radius:50%;
      transform:scale(0);animation:ripple 0.6s ease-out;pointer-events:none;
    `;

    if (!document.querySelector('#ripple-styles')) {
      const styleSheet = document.createElement('style');
      styleSheet.id = 'ripple-styles';
      styleSheet.textContent = `@keyframes ripple { to { transform:scale(2);opacity:0; } }`;
      document.head.appendChild(styleSheet);
    }

    this.style.position = 'relative';
    this.style.overflow  = 'hidden';
    this.appendChild(ripple);
    setTimeout(() => { if (this.contains(ripple)) this.removeChild(ripple); }, 600);
  });
});

// ============================================================
// LAZY LOADING des images avec data-src
// ============================================================
const lazyImages    = document.querySelectorAll('img[data-src]');
const imageObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const img = entry.target;
      img.src   = img.dataset.src;
      img.classList.remove('lazy');
      imageObserver.unobserve(img);
    }
  });
});
lazyImages.forEach(img => imageObserver.observe(img));

// ============================================================
// ANIMATION D'APPARITION DES SECTIONS AU SCROLL
// ============================================================
const revealSections  = document.querySelectorAll('section');
const sectionObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) entry.target.classList.add('revealed');
  });
}, { threshold: 0.1 });

revealSections.forEach(section => {
  section.classList.add('reveal');
  sectionObserver.observe(section);
});

if (!document.querySelector('#reveal-styles')) {
  const styleSheet = document.createElement('style');
  styleSheet.id = 'reveal-styles';
  styleSheet.textContent = `
    .reveal   { opacity:0; transform:translateY(30px); transition:all .8s ease; }
    .revealed { opacity:1; transform:translateY(0); }
  `;
  document.head.appendChild(styleSheet);
}

// ============================================================
// GESTION DES ERREURS D'IMAGES (image de secours)
// ============================================================
document.querySelectorAll('img').forEach(img => {
  img.addEventListener('error', function() {
    this.src = 'https://images.pexels.com/photos/1450353/pexels-photo-1450353.jpeg?auto=compress&cs=tinysrgb&w=800';
    this.alt = 'Image de Béjaïa';
  });
});

// ============================================================
// INITIALISATION FINALE au chargement du DOM
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
  console.log('🌊 Hawas Bjaya - Site touristique Béjaïa initialisé avec succès!');

  // Décalage d'animation progressif pour les cartes services
  document.querySelectorAll('.service-card').forEach((card, index) => {
    card.style.animationDelay = `${index * 0.1}s`;
  });

  // Décalage d'animation progressif pour les éléments galerie
  document.querySelectorAll('.gallery-item').forEach((item, index) => {
    item.style.animationDelay = `${index * 0.1}s`;
  });

  // Préchargement des images critiques
  [
    'https://images.pexels.com/photos/1450353/pexels-photo-1450353.jpeg?auto=compress&cs=tinysrgb&w=1920&h=1080',
    'https://images.pexels.com/photos/1004584/pexels-photo-1004584.jpeg?auto=compress&cs=tinysrgb&w=1920&h=1080'
  ].forEach(src => { const img = new Image(); img.src = src; });
});

// ============================================================
// UTILITAIRE : calcul de prix de groupe (usage futur)
// ============================================================
function calculateGroupPrice(basePrice, groupSize) {
  if (groupSize >= 10) return basePrice * 0.90; // -10% pour 10+ personnes
  if (groupSize >= 5)  return basePrice * 0.95; // -5%  pour 5+ personnes
  return basePrice;
}