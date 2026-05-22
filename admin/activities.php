<?php
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();

$msg='';$msg_type='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if(in_array($action,['create','update'])){
    $data=[
      ':name'=>sanitize($_POST['name']??''),
      ':description'=>sanitize($_POST['description']??''),
      ':type_id'=>(int)($_POST['type_id']??0),
      ':price'=>(float)($_POST['price']??0),
      ':duration_minutes'=>(int)($_POST['duration_minutes']??60),
      ':max_participants'=>(int)($_POST['max_participants']??1),
      ':image_url'=>$_POST['image_url']??null,
      ':requirements'=>$_POST['requirements']??null,
      ':is_active'=>(int)($_POST['is_active']??1),
      ':booking_type'=>in_array($_POST['booking_type']??'shared',['private','shared'])?$_POST['booking_type']:'shared',
    ];
    if($action==='create'){
      $db->prepare("INSERT INTO activities(name,description,type_id,price,duration_minutes,max_participants,image_url,requirements,is_active,booking_type)VALUES(:name,:description,:type_id,:price,:duration_minutes,:max_participants,:image_url,:requirements,:is_active,:booking_type)")->execute($data);
      $msg='Activité créée avec succès';$msg_type='success';
    } else {
      $data[':id']=(int)$_POST['activity_id'];
      $db->prepare("UPDATE activities SET name=:name,description=:description,type_id=:type_id,price=:price,duration_minutes=:duration_minutes,max_participants=:max_participants,image_url=:image_url,requirements=:requirements,is_active=:is_active,booking_type=:booking_type,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute($data);
      $msg='Activité modifiée';$msg_type='success';
    }
  }
  if($action==='delete'){
    $id=(int)$_POST['activity_id'];
    $chk=$db->prepare("SELECT COUNT(*) FROM reservations WHERE activity_id=:id");$chk->execute([':id'=>$id]);
    if($chk->fetchColumn()>0){$msg='Impossible : des réservations existent';$msg_type='error';}
    else{$db->prepare("DELETE FROM activities WHERE id=:id")->execute([':id'=>$id]);$msg='Activité supprimée';$msg_type='success';}
  }
  if($action==='toggle'){
    $db->prepare("UPDATE activities SET is_active=:v WHERE id=:id")->execute([':v'=>(int)$_POST['is_active'],':id'=>(int)$_POST['activity_id']]);
    $msg='Statut modifié';$msg_type='success';
  }
}

$search=$_GET['search']??'';$type_f=$_GET['type']??'all';$bt_f=$_GET['bt']??'all';
$w=[];$p=[];
if($search){$w[]="(a.name LIKE :s OR a.description LIKE :s)";$p[':s']="%$search%";}
if($type_f!=='all'){$w[]="a.type_id=:t";$p[':t']=$type_f;}
if($bt_f!=='all'){$w[]="a.booking_type=:bt";$p[':bt']=$bt_f;}
$wc=$w?'WHERE '.implode(' AND ',$w):'';
$stmt=$db->prepare("SELECT a.*,at2.name type_name,at2.color type_color,(SELECT COUNT(*) FROM reservations r WHERE r.activity_id=a.id AND r.status!='cancelled') res_count FROM activities a LEFT JOIN activity_types at2 ON a.type_id=at2.id $wc ORDER BY a.name");
$stmt->execute($p);$acts=$stmt->fetchAll(PDO::FETCH_ASSOC);

$types=$db->query("SELECT * FROM activity_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$active_page='activities';$pending_count=0;
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Activités — Hawas Bjaya</title>
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
      <div><div class="page-title">Activités</div><div class="page-sub"><?php echo count($acts); ?> activité(s)</div></div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent" onclick="openCreate()"><i class="fas fa-plus"></i>Nouvelle activité</button>
    </div>
  </header>
  <div class="page-body">
    <?php if($msg): ?><div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>"></i><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <!-- Filtres -->
    <div class="card"><div class="card-body" style="padding:1rem">
      <form method="GET" class="filter-bar">
        <div class="search-wrap"><i class="fas fa-search"></i><input type="text" name="search" placeholder="Rechercher une activité…" value="<?php echo htmlspecialchars($search); ?>"></div>
        <div class="ftabs">
          <button type="button" class="ftab<?php echo $bt_f==='all'?' active':''; ?>" onclick="setFilt('bt','all')">Tous</button>
          <button type="button" class="ftab<?php echo $bt_f==='shared'?' active':''; ?>" onclick="setFilt('bt','shared')">Partagé</button>
          <button type="button" class="ftab<?php echo $bt_f==='private'?' active':''; ?>" onclick="setFilt('bt','private')">Privatif</button>
        </div>
        <select name="type" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all">Tous types</option>
          <?php foreach($types as $t): ?><option value="<?php echo $t['id']; ?>"<?php echo $type_f==$t['id']?' selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?>
        </select>
        <input type="hidden" name="bt" id="btInput" value="<?php echo $bt_f; ?>">
        <?php if($search||$type_f!=='all'||$bt_f!=='all'): ?><a href="activities.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i>Reset</a><?php endif; ?>
      </form>
    </div></div>

    <!-- Grid activités -->
    <div class="act-grid">
      <?php foreach($acts as $a): ?>
      <div class="act-card">
        <div class="act-img">
          <?php if($a['image_url']): ?>
          <img src="<?php echo htmlspecialchars($a['image_url']); ?>" alt="<?php echo htmlspecialchars($a['name']); ?>" onerror="this.parentNode.innerHTML='<div class=act-img-placeholder><i class=fas fa-water></i></div>'">
          <?php else: ?><div class="act-img-placeholder"><i class="fas fa-water"></i></div><?php endif; ?>
          <div class="act-img-top">
            <span class="badge<?php echo $a['is_active']?' b-active':' b-inactive'; ?>"><?php echo $a['is_active']?'Actif':'Inactif'; ?></span>
            <span class="badge <?php echo $a['booking_type']==='private'?'b-private':'b-shared'; ?>"><?php echo $a['booking_type']==='private'?'Privatif':'Partagé'; ?></span>
          </div>
        </div>
        <div class="act-body">
          <?php if($a['type_name']): ?>
          <span style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)"><?php echo htmlspecialchars($a['type_name']); ?></span>
          <?php endif; ?>
          <div class="act-name"><?php echo htmlspecialchars($a['name']); ?></div>
          <div class="act-meta">
            <span class="meta"><i class="fas fa-clock"></i><?php echo $a['duration_minutes']; ?> min</span>
            <span class="meta"><i class="fas fa-users"></i><?php echo $a['max_participants']; ?> max</span>
            <span class="meta"><i class="fas fa-calendar-check"></i><?php echo $a['res_count']??0; ?> rés.</span>
          </div>
          <?php if($a['requirements']): ?><p style="font-size:.76rem;color:var(--text-muted);margin-top:.25rem"><?php echo htmlspecialchars(substr($a['requirements'],0,80)).(strlen($a['requirements'])>80?'…':''); ?></p><?php endif; ?>
          <div class="act-price"><?php echo number_format($a['price'],0,',',' '); ?> DA</div>
        </div>
        <div class="act-foot">
          <button class="btn btn-ghost btn-sm" style="flex:1" onclick='openEdit(<?php echo json_encode($a); ?>)'><i class="fas fa-edit"></i>Modifier</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette activité ?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="activity_id" value="<?php echo $a['id']; ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Supprimer"><i class="fas fa-trash"></i></button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="activity_id" value="<?php echo $a['id']; ?>"><input type="hidden" name="is_active" value="<?php echo $a['is_active']?0:1; ?>">
            <button type="submit" class="btn <?php echo $a['is_active']?'btn-warning':'btn-success'; ?> btn-sm btn-icon" title="<?php echo $a['is_active']?'Désactiver':'Activer'; ?>"><i class="fas fa-<?php echo $a['is_active']?'pause':'play'; ?>"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($acts)): ?><div class="empty-box" style="grid-column:1/-1"><div class="empty-ico"><i class="fas fa-water"></i></div><h3>Aucune activité</h3><p>Créez votre première activité</p></div><?php endif; ?>
    </div>
  </div>
</div></div>

<!-- Modal Créer/Modifier -->
<div class="modal-wrap" id="actModal">
  <div class="modal" style="max-width:600px">
    <div class="modal-head">
      <div class="modal-ttl" id="modalTtl">Nouvelle activité</div>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="actForm">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="activity_id" id="formId">
      <div class="modal-body">
        <div class="form-row grid-2">
          <div class="form-group span-2"><label class="form-label">Nom *</label><input type="text" name="name" id="fName" class="form-control" required placeholder="Ex: Jet Ski"></div>
          <div class="form-group">
            <label class="form-label">Type d'activité</label>
            <select name="type_id" id="fType" class="form-control">
              <option value="">Sans type</option>
              <?php foreach($types as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Type de réservation</label>
            <select name="booking_type" id="fBt" class="form-control">
              <option value="shared">Partagé</option>
              <option value="private">Privatif</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Prix (DA) *</label><input type="number" name="price" id="fPrice" class="form-control" min="0" required placeholder="0"></div>
          <div class="form-group"><label class="form-label">Durée (min)</label><input type="number" name="duration_minutes" id="fDur" class="form-control" min="1" value="60"></div>
          <div class="form-group"><label class="form-label">Capacité max *</label><input type="number" name="max_participants" id="fMax" class="form-control" min="1" required value="1"></div>
          <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="is_active" id="fActive" class="form-control"><option value="1">Actif</option><option value="0">Inactif</option></select>
          </div>
          <div class="form-group span-2"><label class="form-label">URL Image</label><input type="url" name="image_url" id="fImg" class="form-control" placeholder="https://…"></div>
          <div class="form-group span-2"><label class="form-label">Description</label><textarea name="description" id="fDesc" class="form-control"></textarea></div>
          <div class="form-group span-2"><label class="form-label">Conditions / Équipements</label><textarea name="requirements" id="fReq" class="form-control" style="min-height:70px"></textarea></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn btn-accent" id="submitBtn"><i class="fas fa-check"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function setFilt(n,v){document.getElementById('btInput').value=v;document.querySelector('form[method=GET]').submit()}
function openCreate(){document.getElementById('modalTtl').textContent='Nouvelle activité';document.getElementById('formAction').value='create';document.getElementById('actForm').reset();document.getElementById('actModal').classList.add('show')}
function openEdit(a){
  document.getElementById('modalTtl').textContent='Modifier l\'activité';
  document.getElementById('formAction').value='update';
  document.getElementById('formId').value=a.id;
  document.getElementById('fName').value=a.name;
  document.getElementById('fDesc').value=a.description||'';
  document.getElementById('fType').value=a.type_id||'';
  document.getElementById('fBt').value=a.booking_type||'shared';
  document.getElementById('fPrice').value=a.price;
  document.getElementById('fDur').value=a.duration_minutes;
  document.getElementById('fMax').value=a.max_participants;
  document.getElementById('fImg').value=a.image_url||'';
  document.getElementById('fReq').value=a.requirements||'';
  document.getElementById('fActive').value=a.is_active;
  document.getElementById('actModal').classList.add('show');
}
function closeModal(){document.getElementById('actModal').classList.remove('show')}
document.getElementById('actModal').addEventListener('click',function(e){if(e.target===this)closeModal()})
</script>
</body></html>