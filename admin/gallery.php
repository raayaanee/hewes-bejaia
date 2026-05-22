<?php
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();

$msg='';$msg_type='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='add'){
    $title=trim($_POST['title']??'');$url=trim($_POST['image_path']??'');$ord=(int)($_POST['display_order']??0);$active=(int)($_POST['is_active']??1);
    if($title&&$url){
      $db->prepare("INSERT INTO gallery(title,image_path,display_order,is_active)VALUES(:t,:u,:o,:a)")->execute([':t'=>$title,':u'=>$url,':o'=>$ord,':a'=>$active]);
      $msg='Image ajoutée';$msg_type='success';
    }else{$msg='Titre et URL requis';$msg_type='error';}
  }
  if($action==='edit'){
    $id=(int)$_POST['gal_id'];
    $db->prepare("UPDATE gallery SET title=:t,image_path=:u,display_order=:o,is_active=:a WHERE id=:id")
       ->execute([':t'=>$_POST['title'],':u'=>$_POST['image_path'],':o'=>(int)$_POST['display_order'],':a'=>(int)$_POST['is_active'],':id'=>$id]);
    $msg='Image modifiée';$msg_type='success';
  }
  if($action==='delete'){$db->prepare("DELETE FROM gallery WHERE id=:id")->execute([':id'=>(int)$_POST['gal_id']]);$msg='Image supprimée';$msg_type='success';}
  if($action==='toggle'){$db->prepare("UPDATE gallery SET is_active=:v WHERE id=:id")->execute([':v'=>(int)$_POST['is_active'],':id'=>(int)$_POST['gal_id']]);$msg='Statut modifié';$msg_type='success';}
}

$filter=$_GET['filter']??'all';
try {
  $q="SELECT * FROM gallery";
  if($filter==='active')$q.=" WHERE is_active=1";
  if($filter==='inactive')$q.=" WHERE is_active=0";
  $q.=" ORDER BY display_order ASC,created_at DESC";
  $gallery=$db->query($q)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  $gallery=[];
  $msg="Erreur: ".$e->getMessage();$msg_type='error';
}
$active_page='gallery';$pending_count=0;
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Galerie — Hawas Bjaya</title>
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
      <div><div class="page-title">Galerie</div><div class="page-sub"><?php echo count($gallery); ?> image(s)</div></div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-plus"></i>Ajouter une image</button>
    </div>
  </header>
  <div class="page-body">
    <?php if($msg): ?><div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>"></i><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="card"><div class="card-body" style="padding:1rem">
      <div class="filter-bar">
        <div class="ftabs">
          <?php foreach(['all'=>'Toutes','active'=>'Actives','inactive'=>'Inactives'] as $k=>$v): ?>
          <a href="?filter=<?php echo $k; ?>" class="ftab<?php echo $filter===$k?' active':''; ?>"><?php echo $v; ?></a>
          <?php endforeach; ?>
        </div>
        <span style="font-size:.8rem;color:var(--text-muted)"><?php echo count($gallery); ?> résultat(s)</span>
      </div>
    </div></div>

    <?php if(!empty($gallery)): ?>
    <div class="gallery-grid">
      <?php foreach($gallery as $g): ?>
      <div class="gal-item" style="<?php echo !$g['is_active']?'opacity:.5':''; ?>">
        <img src="<?php echo htmlspecialchars($g['image_path']); ?>" 
             alt="<?php echo htmlspecialchars($g['title']); ?>"
             loading="lazy"
             onerror="imgError(this)">
        <div class="gal-overlay">
          <div class="gal-title"><?php echo htmlspecialchars($g['title']); ?></div>
          <div class="gal-actions">
            <button class="btn btn-sm btn-icon" style="background:rgba(255,255,255,.15);color:white;border-color:transparent" onclick='openEdit(<?php echo htmlspecialchars(json_encode($g), ENT_QUOTES); ?>)' title="Modifier"><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="gal_id" value="<?php echo $g['id']; ?>">
              <button type="submit" class="btn btn-sm btn-icon" style="background:rgba(251,113,133,.3);color:var(--danger);border-color:transparent"><i class="fas fa-trash"></i></button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="gal_id" value="<?php echo $g['id']; ?>">
              <input type="hidden" name="is_active" value="<?php echo $g['is_active']?0:1; ?>">
              <button type="submit" class="btn btn-sm btn-icon" style="background:rgba(255,255,255,.15);color:white;border-color:transparent"><i class="fas fa-<?php echo $g['is_active']?'eye-slash':'eye'; ?>"></i></button>
            </form>
          </div>
        </div>
        <?php if(!$g['is_active']): ?>
        <div style="position:absolute;top:.5rem;right:.5rem;z-index:1"><span class="badge b-inactive" style="font-size:.62rem">Masqué</span></div>
        <?php else: ?>
        <div style="position:absolute;top:.5rem;right:.5rem;z-index:1"><span class="badge b-active" style="font-size:.62rem">Visible</span></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card"><div class="empty-box"><div class="empty-ico"><i class="fas fa-images"></i></div><h3>Galerie vide</h3><p>Ajoutez des images via l'URL</p></div></div>
    <?php endif; ?>
  </div>
</div></div>

<!-- Modal Ajouter -->
<div class="modal-wrap" id="addModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-ttl">Ajouter une image</div><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Titre *</label><input type="text" name="title" class="form-control" required placeholder="Ex: Jet Ski Béjaïa"></div>
        <div class="form-group"><label class="form-label">URL de l'image *</label><input type="url" name="image_path" id="addUrl" class="form-control" required placeholder="https://…" oninput="previewImg(this,'addPrev')"></div>
        <div id="addPrev" style="height:120px;border-radius:var(--radius);overflow:hidden;background:var(--surface-2);display:none;border:1px solid var(--border)"><img id="addPrevImg" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        <div class="form-row grid-2">
          <div class="form-group"><label class="form-label">Ordre d'affichage</label><input type="number" name="display_order" class="form-control" value="0" min="0"></div>
          <div class="form-group"><label class="form-label">Visibilité</label><select name="is_active" class="form-control"><option value="1">Visible</option><option value="0">Masqué</option></select></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('show')">Annuler</button><button type="submit" class="btn btn-accent"><i class="fas fa-plus"></i>Ajouter</button></div>
    </form>
  </div>
</div>

<!-- Modal Modifier -->
<div class="modal-wrap" id="editModal">
  <div class="modal">
    <div class="modal-head"><div class="modal-ttl">Modifier l'image</div><button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="gal_id" id="eId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Titre</label><input type="text" name="title" id="eTtl" class="form-control" required></div>
        <div class="form-group"><label class="form-label">URL de l'image</label><input type="url" name="image_path" id="eUrl" class="form-control" required oninput="previewImg(this,'editPrev')"></div>
        <div id="editPrev" style="height:120px;border-radius:var(--radius);overflow:hidden;background:var(--surface-2);border:1px solid var(--border)"><img id="editPrevImg" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        <div class="form-row grid-2">
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="display_order" id="eOrd" class="form-control" value="0"></div>
          <div class="form-group"><label class="form-label">Visibilité</label><select name="is_active" id="eActive" class="form-control"><option value="1">Visible</option><option value="0">Masqué</option></select></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').classList.remove('show')">Annuler</button><button type="submit" class="btn btn-accent"><i class="fas fa-check"></i>Enregistrer</button></div>
    </form>
  </div>
</div>
<script>
function imgError(img) {
  img.onerror = null;
  img.style.display = 'none';
  var ph = document.createElement('div');
  ph.style.cssText = 'position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#3d5070;background:#131820';
  ph.innerHTML = '<i class="fas fa-image" style="font-size:1.5rem"></i><span style="font-size:.75rem">Image non disponible</span>';
  img.parentNode.appendChild(ph);
}
function previewImg(inp,prevId){
  const prev=document.getElementById(prevId);const img=document.getElementById(prevId+'Img');
  if(inp.value){prev.style.display='block';img.src=inp.value;img.onerror=()=>prev.style.display='none';}else prev.style.display='none';
}
function openEdit(g){
  document.getElementById('eId').value=g.id;document.getElementById('eTtl').value=g.title;
  document.getElementById('eUrl').value=g.image_path;document.getElementById('eOrd').value=g.display_order;
  document.getElementById('eActive').value=g.is_active;
  const pi=document.getElementById('editPrevImg');pi.src=g.image_path;
  document.getElementById('editModal').classList.add('show');
}
document.querySelectorAll('.modal-wrap').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('show')}));
</script>
</body></html>