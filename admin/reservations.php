<?php
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();
// Auto-cancel géré par cron uniquement

$stats=$db->query("SELECT
  COUNT(*) total,
  SUM(status='pending') pending,
  SUM(status='confirmed') confirmed,
  SUM(status='completed') completed,
  SUM(status='cancelled') cancelled,
  COALESCE(SUM(CASE WHEN status='completed' THEN total_price END),0) revenue
FROM reservations")->fetch(PDO::FETCH_ASSOC);

$search=$_GET['search']??'';$sf=$_GET['status']??'all';$af=$_GET['activity']??'all';$df=$_GET['date']??'';
$page=max(1,(int)($_GET['page']??1));$limit=15;
$w=[];$p=[];
if($search){$w[]="(r.client_name LIKE :s OR r.client_email LIKE :s OR r.confirmation_code LIKE :s OR r.client_phone LIKE :s)";$p[':s']="%$search%";}
if($sf!=='all'){$w[]="r.status=:st";$p[':st']=$sf;}
if($af!=='all'){$w[]="r.activity_id=:ac";$p[':ac']=$af;}
if($df){$w[]="r.reservation_date=:dt";$p[':dt']=$df;}
$wc=$w?'WHERE '.implode(' AND ',$w):'';

$cnt=$db->prepare("SELECT COUNT(*) FROM reservations r $wc");$cnt->execute($p);
$total_rows=(int)$cnt->fetchColumn();$total_pages=ceil($total_rows/$limit);$offset=($page-1)*$limit;

$stmt=$db->prepare("SELECT r.*,a.name act_name,a.booking_type,ts.start_time,ts.end_time
  FROM reservations r JOIN activities a ON r.activity_id=a.id JOIN time_slots ts ON r.time_slot_id=ts.id
  $wc ORDER BY r.created_at DESC LIMIT :lim OFFSET :off");
foreach($p as $k=>$v)$stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT);$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

$activities=$db->query("SELECT id,name FROM activities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$active_page='reservations';$pending_count=$stats['pending'];
$sl=['pending'=>'En attente','confirmed'=>'Confirmée','completed'=>'Terminée','cancelled'=>'Annulée'];
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Réservations — Hawas Bjaya</title>
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
      <div><div class="page-title">Réservations</div><div class="page-sub"><?php echo $total_rows; ?> réservation(s)</div></div>
    </div>
    <div class="topbar-right">
      <a href="../api/export_reservations.php<?php echo $_SERVER['QUERY_STRING']?'?'.$_SERVER['QUERY_STRING']:''; ?>" class="btn btn-ghost"><i class="fas fa-download"></i>CSV</a>
    </div>
  </header>
  <div class="page-body">
    <div class="stats-row stagger">
      <div class="stat-card c-warn"><div class="card-icon-wrap"><i class="fas fa-clock"></i></div><div class="card-value"><?php echo $stats['pending']; ?></div><div class="card-label">En attente</div><i class="fas fa-clock card-bg"></i></div>
      <div class="stat-card c-blue"><div class="card-icon-wrap"><i class="fas fa-check-circle"></i></div><div class="card-value"><?php echo $stats['confirmed']; ?></div><div class="card-label">Confirmées</div><i class="fas fa-check card-bg"></i></div>
      <div class="stat-card c-green"><div class="card-icon-wrap"><i class="fas fa-flag-checkered"></i></div><div class="card-value"><?php echo $stats['completed']; ?></div><div class="card-label">Terminées</div><i class="fas fa-trophy card-bg"></i></div>
      <div class="stat-card c-gold"><div class="card-icon-wrap"><i class="fas fa-coins"></i></div><div class="card-value"><?php echo number_format($stats['revenue']/1000,1); ?>K</div><div class="card-label">Revenus DA</div><i class="fas fa-coins card-bg"></i></div>
    </div>
    <!-- Filtres -->
    <div class="card"><div class="card-body" style="padding:1rem">
      <form method="GET" id="ff">
        <div class="filter-bar">
          <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="search" placeholder="Nom, email, code, téléphone…" value="<?php echo htmlspecialchars($search); ?>"></div>
          <div class="ftabs">
            <?php foreach(['all'=>'Tous','pending'=>'En attente','confirmed'=>'Confirmées','completed'=>'Terminées','cancelled'=>'Annulées'] as $k=>$v): ?>
            <button type="button" class="ftab<?php echo $sf===$k?' active':''; ?>" onclick="setF('status','<?php echo $k; ?>')"><?php echo $v; ?></button>
            <?php endforeach; ?>
          </div>
          <select name="activity" class="form-control" style="width:auto" onchange="document.getElementById('ff').submit()">
            <option value="all">Toutes activités</option>
            <?php foreach($activities as $a): ?><option value="<?php echo $a['id']; ?>"<?php echo $af==$a['id']?' selected':''; ?>><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?>
          </select>
          <input type="date" name="date" class="form-control" style="width:auto" value="<?php echo $df; ?>" onchange="document.getElementById('ff').submit()">
          <input type="hidden" name="status" id="si" value="<?php echo $sf; ?>">
          <?php if($search||$sf!=='all'||$af!=='all'||$df): ?><a href="reservations.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i>Reset</a><?php endif; ?>
        </div>
      </form>
    </div></div>
    <!-- Table -->
    <div class="card"><div class="table-wrap">
      <table class="data-table">
        <thead><tr><th class="hide-mobile">Code</th><th>Client</th><th>Activité</th><th>Date &amp; Horaire</th><th>Pers.</th><th>Total</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r):
          $df2=date('d/m/Y',strtotime($r['reservation_date']));
          $tf=substr($r['start_time'],0,5).' - '.substr($r['end_time'],0,5); ?>
        <tr>
          <td><code style="font-size:.72rem;color:var(--text-muted)" class="hide-mobile"><?php echo $r['confirmation_code']; ?></code></td>
          <td><div><strong><?php echo htmlspecialchars($r['client_name']); ?></strong><div style="font-size:.73rem;color:var(--text-muted)"><?php echo htmlspecialchars($r['client_phone']); ?></div></div></td>
          <td><div><?php echo htmlspecialchars($r['act_name']); ?><div style="margin-top:.2rem"><span class="badge <?php echo $r['booking_type']==='private'?'b-private':'b-shared'; ?>"><?php echo $r['booking_type']==='private'?'Privatif':'Partagé'; ?></span></div></div></td>
          <td><div><strong><?php echo $df2; ?></strong><div style="font-size:.73rem;color:var(--text-muted)"><i class="fas fa-clock"></i> <?php echo $tf; ?></div></div></td>
          <td><strong><?php echo $r['participants']; ?></strong></td>
          <td><strong style="color:var(--success)"><?php echo number_format($r['total_price'],0,',',' '); ?> DA</strong></td>
          <td><span class="badge b-<?php echo $r['status']; ?>"><?php echo $sl[$r['status']]??$r['status']; ?></span></td>
          <td>
            <div style="display:flex;gap:.35rem">
              <?php if($r['status']==='pending'): ?>
              <button class="btn btn-success btn-sm btn-icon" title="Confirmer" onclick="upd(<?php echo $r['id']; ?>,'confirmed')"><i class="fas fa-check"></i></button>
              <button class="btn btn-danger btn-sm btn-icon" title="Annuler" onclick="upd(<?php echo $r['id']; ?>,'cancelled')"><i class="fas fa-times"></i></button>
              <?php elseif($r['status']==='confirmed'): ?>
              <button class="btn btn-success btn-sm btn-icon" title="Terminée" onclick="upd(<?php echo $r['id']; ?>,'completed')"><i class="fas fa-flag-checkered"></i></button>
              <button class="btn btn-danger btn-sm btn-icon" title="Annuler" onclick="upd(<?php echo $r['id']; ?>,'cancelled')"><i class="fas fa-times"></i></button>
              <?php endif; ?>
              <button class="btn btn-whatsapp btn-sm btn-icon" title="WhatsApp" onclick='wa(<?php echo json_encode(["name"=>$r['client_name'],"phone"=>$r['client_phone'],"code"=>$r['confirmation_code'],"act"=>$r['act_name'],"date"=>$df2,"time"=>$tf,"pers"=>$r['participants'],"price"=>$r['total_price'],"status"=>$r['status']]); ?>)'><i class="fab fa-whatsapp"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="8"><div class="empty-box"><div class="empty-ico"><i class="fas fa-calendar-times"></i></div><h3>Aucune réservation</h3><p>Modifiez les filtres</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($total_pages>1): ?>
    <div class="pager">
      <button class="pager-btn"<?php echo $page<=1?' disabled':''; ?> onclick="gp(<?php echo $page-1; ?>)"><i class="fas fa-chevron-left"></i></button>
      <?php for($i=1;$i<=min($total_pages,7);$i++): ?><button class="pager-btn<?php echo $i==$page?' active':''; ?>" onclick="gp(<?php echo $i; ?>)"><?php echo $i; ?></button><?php endfor; ?>
      <?php if($total_pages>7): ?><span style="color:var(--text-muted);padding:0 .3rem">…</span><button class="pager-btn" onclick="gp(<?php echo $total_pages; ?>)"><?php echo $total_pages; ?></button><?php endif; ?>
      <button class="pager-btn"<?php echo $page>=$total_pages?' disabled':''; ?> onclick="gp(<?php echo $page+1; ?>)"><i class="fas fa-chevron-right"></i></button>
    </div>
    <?php endif; ?>
    </div>
  </div>
</div></div>

<div class="toast" id="toast"><div class="toast-inner"><span id="tIcon"></span><span class="toast-msg" id="tMsg"></span></div></div>

<script>
function setF(n,v){document.getElementById('si').value=v;document.getElementById('ff').submit()}
function gp(p){const u=new URL(location);u.searchParams.set('page',p);location=u}
function showToast(msg,ok=true){
  const t=document.getElementById('toast');
  document.getElementById('tMsg').textContent=msg;
  document.getElementById('tIcon').innerHTML=ok?'<i class="fas fa-check-circle" style="color:var(--success)"></i>':'<i class="fas fa-times-circle" style="color:var(--danger)"></i>';
  t.style.display='block';setTimeout(()=>t.style.display='none',3500);
}
async function upd(id,status){
  const lb={confirmed:'Confirmer',completed:'Marquer terminée',cancelled:'Annuler'};
  if(!confirm(lb[status]+' cette réservation ?'))return;
  try{
    const r=await fetch('../api/update_reservation_status.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({reservation_id:id,status})});
    const d=await r.json();
    if(d.success){showToast('Statut mis à jour');if(d.whatsapp_url)setTimeout(()=>{if(confirm('Notifier le client par WhatsApp ?'))window.open(d.whatsapp_url,'_blank');location.reload()},500);else setTimeout(()=>location.reload(),800);}
    else showToast(d.error||'Erreur',false);
  }catch(e){showToast('Erreur connexion',false)}
}
function wa(r){
  const msgs={pending:`Bonjour ${r.name},\n\nVotre réservation est en attente.\n📋 ${r.code}\n🎯 ${r.act}\n📅 ${r.date} ${r.time}\n👥 ${r.pers} pers.\n💰 ${Number(r.price).toLocaleString()} DA\n\nHawas Bjaya`,confirmed:`✅ Bonjour ${r.name},\nVotre réservation est confirmée !\n📋 ${r.code}\n🎯 ${r.act}\n📅 ${r.date} ${r.time}\n👥 ${r.pers} pers.\n💰 ${Number(r.price).toLocaleString()} DA\n📍 Béjaïa | 📞 +213 775 654 995`,completed:`🎉 Merci ${r.name} pour votre visite !\nHawas Bjaya vous attend à nouveau.`,cancelled:`❌ Bonjour ${r.name},\nRéservation ${r.code} annulée.\n📞 +213 775 654 995`};
  window.open('https://wa.me/'+r.phone.replace(/\D/g,'')+'?text='+encodeURIComponent(msgs[r.status]||msgs.confirmed),'_blank');
}
</script>
</body></html>