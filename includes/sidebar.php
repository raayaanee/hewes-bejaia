<?php
/**
 * includes/sidebar.php
 * $active_page requis avant include
 */
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_init = strtoupper(substr($admin_name, 0, 1));

if (!isset($pending_count) && isset($db)) {
    try {
        $pending_count = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn();
    } catch(Exception $e) { $pending_count = 0; }
}

$nav = [
    'dashboard'      => ['icon'=>'fa-chart-bar',      'label'=>'Tableau de bord', 'href'=>'index.php'],
    'reservations'   => ['icon'=>'fa-calendar-check', 'label'=>'Réservations',    'href'=>'reservations.php'],
    'activities'     => ['icon'=>'fa-water',           'label'=>'Activités',       'href'=>'activities.php'],
    'accommodations' => ['icon'=>'fa-house',           'label'=>'Hébergements',    'href'=>'accommodations.php'],
    'availability'   => ['icon'=>'fa-clock',           'label'=>'Disponibilités',  'href'=>'availability.php'],
    'clients'        => ['icon'=>'fa-users',           'label'=>'Clients',         'href'=>'clients.php'],
    'gallery'        => ['icon'=>'fa-images',          'label'=>'Galerie',         'href'=>'gallery.php'],
    'settings'       => ['icon'=>'fa-cog',             'label'=>'Paramètres',      'href'=>'settings.php'],
];
?>
<div class="mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark"><i class="fas fa-water"></i></div>
    <div class="logo-text">
      <h2>Hawas Bjaya</h2>
      <span>Administration</span>
    </div>
    <button class="collapse-btn" onclick="toggleSidebar()" id="colBtn">
      <i class="fas fa-chevron-left"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group-label">Navigation</div>
    <?php foreach($nav as $key => $item):
      $active = ($active_page === $key); ?>
    <a href="<?php echo $item['href']; ?>"
       class="nav-item<?php echo $active ? ' active' : ''; ?>"
       data-label="<?php echo $item['label']; ?>">
      <span class="nav-icon"><i class="fas <?php echo $item['icon']; ?>"></i></span>
      <span class="nav-label"><?php echo $item['label']; ?></span>
      <?php if($key === 'reservations' && !empty($pending_count) && $pending_count > 0): ?>
      <span class="nav-badge"><?php echo $pending_count; ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <button class="theme-toggle-btn" onclick="toggleTheme()" id="themeBtn" title="Changer le thème">
      <span class="t-icon"><i class="fas fa-sun" id="themeIcon"></i></span>
      <span id="themeLabel">Mode clair</span>
    </button>

    <div class="admin-card" onclick="location.href='settings.php'">
      <div class="avatar"><?php echo $admin_init; ?></div>
      <div class="admin-info">
        <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="role">Administrateur</div>
      </div>
    </div>

    <button class="logout-btn" onclick="if(confirm('Se déconnecter ?')) location.href='logout.php'">
      <span class="logout-icon"><i class="fas fa-sign-out-alt"></i></span>
      <span class="logout-text">Déconnexion</span>
    </button>
  </div>
</aside>

<script>
const sb = document.getElementById('sidebar');
if (localStorage.getItem('sb_col') === '1' && window.innerWidth > 992) {
  sb.classList.add('collapsed');
}
function toggleSidebar() {
  sb.classList.toggle('collapsed');
  localStorage.setItem('sb_col', sb.classList.contains('collapsed') ? '1' : '0');
}
function toggleMobileSidebar() {
  sb.classList.toggle('mob-open');
  document.getElementById('mobOverlay').classList.toggle('show');
}
function closeSidebar() {
  sb.classList.remove('mob-open');
  document.getElementById('mobOverlay').classList.remove('show');
}
window.addEventListener('resize', () => {
  if (window.innerWidth <= 992) { sb.classList.remove('collapsed'); closeSidebar(); }
});
const html = document.documentElement;
const themeIcon = document.getElementById('themeIcon');
const themeLbl  = document.getElementById('themeLabel');
const themeBtn  = document.getElementById('themeBtn');
function applyTheme(theme) {
  if (theme === 'light') {
    html.setAttribute('data-theme', 'light');
    themeIcon.className = 'fas fa-moon';
    themeLbl.textContent = 'Mode sombre';
    themeBtn.title = 'Passer en mode sombre';
  } else {
    html.removeAttribute('data-theme');
    themeIcon.className = 'fas fa-sun';
    themeLbl.textContent = 'Mode clair';
    themeBtn.title = 'Passer en mode clair';
  }
}
function toggleTheme() {
  const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
  localStorage.setItem('admin_theme', next);
  applyTheme(next);
}
(function() { applyTheme(localStorage.getItem('admin_theme') || 'dark'); })();
</script>