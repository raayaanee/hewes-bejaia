<?php
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();
// Auto-cancel géré par cron uniquement
$s=$db->query("SELECT
  (SELECT COUNT(*) FROM reservations) total,
  (SELECT COUNT(*) FROM reservations WHERE status='pending') pending,
  (SELECT COUNT(*) FROM reservations WHERE status='confirmed') confirmed,
  (SELECT COUNT(*) FROM reservations WHERE status='completed') completed,
  (SELECT COUNT(*) FROM reservations WHERE status='cancelled') cancelled,
  (SELECT COUNT(*) FROM clients) clients,
  (SELECT COUNT(*) FROM activities WHERE is_active=1) activities,
  (SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE status='completed') revenue,
  (SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE status IN('pending','confirmed')) potential")->fetch(PDO::FETCH_ASSOC);
$recent=$db->query("SELECT r.*,a.name as act FROM reservations r JOIN activities a ON r.activity_id=a.id ORDER BY r.created_at DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
$popular=$db->query("SELECT a.name,COUNT(r.id) cnt,COALESCE(SUM(r.total_price),0) rev FROM activities a LEFT JOIN reservations r ON a.id=r.activity_id AND r.status!='cancelled' GROUP BY a.id ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$active_page='dashboard';$pending_count=$s['pending'];
$slabels=['pending'=>'En attente','confirmed'=>'Confirmée','completed'=>'Terminée','cancelled'=>'Annulée'];
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Dashboard — Hawas Bjaya Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css"></head>
<body>
<div class="admin-layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" onclick="toggleMobileSidebar()"><i class="fas fa-bars"></i></button>
      <div><div class="page-title">Tableau de bord</div><div class="page-sub"><?php echo date('l d F Y'); ?></div></div>
    </div>
    <div class="topbar-right">
      <div class="status-pill"><span class="dot"></span>Système actif</div>
      <a href="reservations.php" class="btn btn-accent"><i class="fas fa-plus"></i>Réservation</a>
    </div>
  </header>
  <div class="page-body">
    <!-- Stats -->
    <div class="stats-row stagger">
      <div class="stat-card c-blue">
        <div class="card-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
        <div class="card-value"><?php echo $s['total']; ?></div>
        <div class="card-label">Réservations totales</div>
        <div class="card-trend"><i class="fas fa-clock"></i><?php echo $s['pending']; ?> en attente</div>
        <i class="fas fa-calendar card-bg"></i>
      </div>
      <div class="stat-card c-green">
        <div class="card-icon-wrap"><i class="fas fa-coins"></i></div>
        <div class="card-value"><?php echo $s['revenue']>=1000?number_format($s['revenue']/1000,1).'K':number_format($s['revenue'],0); ?></div>
        <div class="card-label">Revenus confirmés (DA)</div>
        <div class="card-trend"><i class="fas fa-hourglass-half"></i><?php echo number_format($s['potential']/1000,1); ?>K DA en cours</div>
        <i class="fas fa-coins card-bg"></i>
      </div>
      <div class="stat-card c-gold">
        <div class="card-icon-wrap"><i class="fas fa-users"></i></div>
        <div class="card-value"><?php echo $s['clients']; ?></div>
        <div class="card-label">Clients inscrits</div>
        <div class="card-trend"><i class="fas fa-star"></i><?php echo $s['activities']; ?> activités actives</div>
        <i class="fas fa-users card-bg"></i>
      </div>
      <div class="stat-card c-purple">
        <div class="card-icon-wrap"><i class="fas fa-trophy"></i></div>
        <div class="card-value"><?php echo $s['completed']; ?></div>
        <div class="card-label">Séjours terminés</div>
        <div class="card-trend"><i class="fas fa-times-circle"></i><?php echo $s['cancelled']; ?> annulées</div>
        <i class="fas fa-trophy card-bg"></i>
      </div>
    </div>
    <!-- Row 2 -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start">
      <div class="card">
        <div class="card-head">
          <div class="card-ttl"><span class="ttl-ico" style="background:var(--accent-dim);color:var(--accent)"><i class="fas fa-list"></i></span>Dernières réservations</div>
          <a href="reservations.php" class="btn btn-ghost btn-sm">Tout voir <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Code</th><th>Client</th><th>Activité</th><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach($recent as $r): ?>
          <tr>
            <td><code style="font-size:.72rem;color:var(--text-muted)"><?php echo $r['confirmation_code']; ?></code></td>
            <td><strong><?php echo htmlspecialchars($r['client_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['act']); ?></td>
            <td><?php echo date('d/m/Y',strtotime($r['reservation_date'])); ?></td>
            <td><strong style="color:var(--success)"><?php echo number_format($r['total_price'],0,',',' '); ?> DA</strong></td>
            <td><span class="badge b-<?php echo $r['status']; ?>"><?php echo $slabels[$r['status']]??$r['status']; ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recent)): ?><tr><td colspan="6"><div class="empty-box"><div class="empty-ico"><i class="fas fa-calendar-times"></i></div><p>Aucune réservation</p></div></td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
      <!-- Populaires -->
      <div class="card">
        <div class="card-head">
          <div class="card-ttl"><span class="ttl-ico" style="background:var(--gold-dim);color:var(--gold)"><i class="fas fa-fire"></i></span>Activités populaires</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.875rem">
          <?php $mx=!empty($popular)?max(array_column($popular,'cnt')):1; $colors=['var(--accent)','var(--gold)','var(--success)','var(--purple)','var(--danger)'];
          foreach($popular as $i=>$p):$pct=$mx>0?round($p['cnt']/$mx*100):0; ?>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:.35rem">
              <span style="font-size:.82rem;font-weight:500"><?php echo htmlspecialchars($p['name']); ?></span>
              <span style="font-size:.73rem;color:var(--text-muted)"><?php echo $p['cnt']; ?></span>
            </div>
            <div class="pbar"><div class="pfill" style="width:<?php echo $pct; ?>%;background:<?php echo $colors[$i]; ?>"></div></div>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:.2rem"><?php echo number_format($p['rev'],0,',',' '); ?> DA</div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($popular)): ?><div class="empty-box" style="padding:1.5rem"><p>Aucune donnée</p></div><?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Status breakdown -->
    <div class="card">
      <div class="card-head">
        <div class="card-ttl"><span class="ttl-ico" style="background:var(--success-dim);color:var(--success)"><i class="fas fa-chart-pie"></i></span>Répartition des statuts</div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">
          <?php
          $sd=[['En attente',$s['pending'],'var(--warning)'],['Confirmées',$s['confirmed'],'var(--accent)'],['Terminées',$s['completed'],'var(--success)'],['Annulées',$s['cancelled'],'var(--danger)']];
          $tot=max($s['total'],1);
          foreach($sd as [$lbl,$cnt,$col]):$pct=round($cnt/$tot*100); ?>
          <div style="text-align:center;padding:1rem;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border)">
            <div style="font-family:'Sora',sans-serif;font-size:1.6rem;font-weight:800;color:<?php echo $col; ?>;margin-bottom:.2rem"><?php echo $cnt; ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.625rem"><?php echo $lbl; ?></div>
            <div class="pbar"><div class="pfill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>"></div></div>
            <div style="font-size:.68rem;color:var(--text-muted);margin-top:.25rem"><?php echo $pct; ?>%</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div></div>
</body></html>