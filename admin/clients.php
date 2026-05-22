<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();

function timeAgo($date){
  $diff=time()-strtotime($date);
  if($diff<60)return 'À l\'instant';
  if($diff<3600)return floor($diff/60).' min';
  if($diff<86400)return floor($diff/3600).' h';
  if($diff<2592000)return floor($diff/86400).' j';
  return date('d/m/Y',strtotime($date));
}

$search=$_GET['search']??'';$filter=$_GET['filter']??'all';
$page=max(1,(int)($_GET['page']??1));$limit=15;
$w=[];$p=[];
if($search){$w[]="(c.full_name LIKE :s OR c.email LIKE :s OR c.phone LIKE :s)";$p[':s']="%$search%";}
if($filter==='active')  {$w[]="c.is_active=1";}
if($filter==='inactive'){$w[]="c.is_active=0";}
if($filter==='loyal')   {$w[]="(SELECT COUNT(*) FROM reservations r WHERE r.client_id=c.id AND r.status='completed')>=3";}
$wc=$w?'WHERE '.implode(' AND ',$w):'';

$cnt=$db->prepare("SELECT COUNT(*) FROM clients c $wc");$cnt->execute($p);
$total_rows=$cnt->fetchColumn();$total_pages=ceil($total_rows/$limit);$offset=($page-1)*$limit;

$stmt=$db->prepare("
  SELECT c.*,
    COUNT(DISTINCT r.id) total_res,
    SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END) completed_res,
    COALESCE(SUM(CASE WHEN r.status='completed' THEN r.total_price END),0) total_spent,
    MAX(r.created_at) last_activity
  FROM clients c LEFT JOIN reservations r ON c.id=r.client_id
  $wc GROUP BY c.id ORDER BY MAX(r.created_at) IS NULL ASC, MAX(r.created_at) DESC LIMIT :lim OFFSET :off");
foreach($p as $k=>$v)$stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT);$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();$clients=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_total = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$stats_active = (int)$db->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
$stats_loyal = (int)$db->query("SELECT COUNT(*) FROM (SELECT client_id FROM reservations WHERE status='completed' GROUP BY client_id HAVING COUNT(*)>=3) t")->fetchColumn();
$stats_revenue = (float)$db->query("SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE status='completed'")->fetchColumn();
$stats=['total'=>$stats_total,'active'=>$stats_active,'loyal'=>$stats_loyal,'revenue'=>$stats_revenue];
$active_page='clients';$pending_count=0;
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Clients — Hawas Bjaya</title>
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
      <div><div class="page-title">Clients</div><div class="page-sub"><?php echo $total_rows; ?> client(s)</div></div>
    </div>
  </header>
  <div class="page-body">
    <div class="stats-row stagger">
      <div class="stat-card c-blue"><div class="card-icon-wrap"><i class="fas fa-users"></i></div><div class="card-value"><?php echo $stats['total']; ?></div><div class="card-label">Total clients</div><i class="fas fa-users card-bg"></i></div>
      <div class="stat-card c-green"><div class="card-icon-wrap"><i class="fas fa-user-check"></i></div><div class="card-value"><?php echo $stats['active']; ?></div><div class="card-label">Clients actifs</div><i class="fas fa-user-check card-bg"></i></div>
      <div class="stat-card c-gold"><div class="card-icon-wrap"><i class="fas fa-star"></i></div><div class="card-value"><?php echo (int)$stats['loyal']; ?></div><div class="card-label">Clients fidèles</div><div class="card-trend" style="color:var(--gold)"><i class="fas fa-tag"></i>-25% de réduction</div><i class="fas fa-crown card-bg"></i></div>
      <div class="stat-card c-purple"><div class="card-icon-wrap"><i class="fas fa-coins"></i></div><div class="card-value"><?php echo number_format($stats['revenue']/1000,1); ?>K</div><div class="card-label">CA total (DA)</div><i class="fas fa-coins card-bg"></i></div>
    </div>

    <div class="card"><div class="card-body" style="padding:1rem">
      <form method="GET" class="filter-bar">
        <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="search" placeholder="Nom, email, téléphone…" value="<?php echo htmlspecialchars($search); ?>"></div>
        <div class="ftabs">
          <?php foreach(['all'=>'Tous','active'=>'Actifs','inactive'=>'Inactifs','loyal'=>'⭐ Fidèles'] as $k=>$v): ?>
          <button type="button" class="ftab<?php echo $filter===$k?' active':''; ?>" onclick="setF('<?php echo $k; ?>')"><?php echo $v; ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="filter" id="fInp" value="<?php echo $filter; ?>">
        <?php if($search||$filter!=='all'): ?><a href="clients.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i>Reset</a><?php endif; ?>
      </form>
    </div></div>

    <div class="card"><div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Client</th><th>Contact</th><th>Réservations</th><th>Dépensé</th><th>Fidélité</th><th>Dernière activité</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($clients as $c):
          $isLoyal=$c['completed_res']>=3;
          $loyalProg=min(100,round($c['completed_res']/3*100));
          $remaining=max(0,3-$c['completed_res']);
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.625rem">
              <div style="width:34px;height:34px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;background:<?php echo $isLoyal?'linear-gradient(135deg,var(--gold),#d97706)':'var(--surface-3)'; ?>;color:<?php echo $isLoyal?'#000':'var(--text-soft)'; ?>">
                <?php echo strtoupper(substr($c['full_name'],0,1)); ?>
              </div>
              <div>
                <strong><?php echo htmlspecialchars($c['full_name']); ?></strong>
                <?php if($isLoyal): ?><div><span class="badge b-loyal" style="font-size:.65rem">⭐ Fidèle</span></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:.8rem"><?php echo htmlspecialchars($c['email']); ?></div>
            <div style="font-size:.76rem;color:var(--text-muted)"><?php echo htmlspecialchars($c['phone']??'—'); ?></div>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:.2rem">
              <span><strong><?php echo $c['total_res']; ?></strong> total</span>
              <span style="font-size:.75rem;color:var(--success)"><?php echo $c['completed_res']; ?> terminées</span>
            </div>
          </td>
          <td><strong style="color:var(--success)"><?php echo number_format($c['total_spent'],0,',',' '); ?> DA</strong></td>
          <td>
            <div style="min-width:90px">
              <?php if($isLoyal): ?>
              <div style="font-size:.75rem;color:var(--gold);margin-bottom:.25rem"><i class="fas fa-crown"></i> Remise -25%</div>
              <?php else: ?>
              <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem">Encore <?php echo $remaining; ?> / 3</div>
              <?php endif; ?>
              <div class="pbar"><div class="pfill" style="width:<?php echo $loyalProg; ?>%;background:<?php echo $isLoyal?'var(--gold)':'var(--accent)'; ?>"></div></div>
            </div>
          </td>
          <td><span style="font-size:.78rem;color:var(--text-muted)"><?php echo $c['last_activity']?timeAgo($c['last_activity']):'Jamais'; ?></span></td>
          <td><span class="badge <?php echo $c['is_active']?'b-active':'b-inactive'; ?>"><?php echo $c['is_active']?'Actif':'Inactif'; ?></span></td>
          <td>
            <div style="display:flex;gap:.35rem">
              <a href="<?php echo 'https://wa.me/'.preg_replace('/[^0-9]/','',($c['phone']??'')).'?text='.urlencode('Bonjour '.$c['full_name'].', nous vous contactons de la part de Hawas Bjaya.'); ?>" target="_blank" class="btn btn-whatsapp btn-sm btn-icon" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
              <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>" class="btn btn-ghost btn-sm btn-icon" title="Email"><i class="fas fa-envelope"></i></a>
              <a href="reservations.php?search=<?php echo urlencode($c['email']); ?>" class="btn btn-purple btn-sm btn-icon" title="Voir réservations"><i class="fas fa-calendar-alt"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($clients)): ?><tr><td colspan="8"><div class="empty-box"><div class="empty-ico"><i class="fas fa-users"></i></div><h3>Aucun client</h3><p>Aucun client ne correspond aux filtres</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($total_pages>1): ?>
    <div class="pager">
      <button class="pager-btn"<?php echo $page<=1?' disabled':''; ?> onclick="gp(<?php echo $page-1; ?>)"><i class="fas fa-chevron-left"></i></button>
      <?php for($i=1;$i<=min($total_pages,7);$i++): ?><button class="pager-btn<?php echo $i==$page?' active':''; ?>" onclick="gp(<?php echo $i; ?>)"><?php echo $i; ?></button><?php endfor; ?>
      <button class="pager-btn"<?php echo $page>=$total_pages?' disabled':''; ?> onclick="gp(<?php echo $page+1; ?>)"><i class="fas fa-chevron-right"></i></button>
    </div>
    <?php endif; ?>
    </div>
  </div>
</div></div>
<script>
function setF(v){document.getElementById('fInp').value=v;document.querySelector('form[method=GET]').submit()}
function gp(p){const u=new URL(location);u.searchParams.set('page',p);location=u}
</script>
</body></html>