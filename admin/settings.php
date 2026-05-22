<?php
require_once '../config/database.php';
if(!isset($_SESSION['admin_id'])){header('Location: ../index.php');exit;}
$database=new Database();$db=$database->getConnection();

$msg='';$msg_type='';
$settings_file=__DIR__.'/../config/settings.json';
$settings=file_exists($settings_file)?json_decode(file_get_contents($settings_file),true):[];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';

  if($action==='profile'){
    $name=$_POST['admin_name']??'';$email=$_POST['admin_email']??'';
    if(!empty($_POST['new_password'])){
      if(strlen($_POST['new_password'])<6){$msg='Mot de passe min. 6 caractères';$msg_type='error';}
      elseif($_POST['new_password']!==$_POST['confirm_password']){$msg='Les mots de passe ne correspondent pas';$msg_type='error';}
      else{
        $adm=$db->prepare("SELECT password FROM admins WHERE id=:id");$adm->execute([':id'=>$_SESSION['admin_id']]);$adm=$adm->fetch();
        if(!password_verify($_POST['current_password']??'',$adm['password'])){$msg='Mot de passe actuel incorrect';$msg_type='error';}
        else{
          $db->prepare("UPDATE admins SET username=:n,email=:e,password=:p WHERE id=:id")->execute([':n'=>$name,':e'=>$email,':p'=>password_hash($_POST['new_password'],PASSWORD_DEFAULT),':id'=>$_SESSION['admin_id']]);
          $_SESSION['admin_name']=$name;$msg='Profil mis à jour avec succès';$msg_type='success';
        }
      }
    } else {
      $db->prepare("UPDATE admins SET username=:n,email=:e WHERE id=:id")->execute([':n'=>$name,':e'=>$email,':id'=>$_SESSION['admin_id']]);
      $_SESSION['admin_name']=$name;$msg='Profil mis à jour';$msg_type='success';
    }
  }

  if($action==='company'){
    $settings['company_name']=$_POST['company_name']??'';
    $settings['company_phone']=$_POST['company_phone']??'';
    $settings['company_email']=$_POST['company_email']??'';
    $settings['whatsapp_number']=$_POST['whatsapp_number']??'';
    $settings['address']=$_POST['address']??'';
    file_put_contents($settings_file,json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    $msg='Paramètres enregistrés';$msg_type='success';
  }
}

// Récupérer info admin
$admin=$db->prepare("SELECT * FROM admins WHERE id=:id");$admin->execute([':id'=>$_SESSION['admin_id']]);$admin=$admin->fetch(PDO::FETCH_ASSOC);

// Stats système
$sys=[
  'reservations'=>$db->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
  'clients'=>$db->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
  'activities'=>$db->query("SELECT COUNT(*) FROM activities")->fetchColumn(),
  'gallery'=>$db->query("SELECT COUNT(*) FROM gallery")->fetchColumn(),
];
$active_page='settings';$pending_count=0;
$tab=$_GET['tab']??'profile';
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Paramètres — Hawas Bjaya</title>
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
      <div><div class="page-title">Paramètres</div><div class="page-sub">Configuration du système</div></div>
    </div>
  </header>
  <div class="page-body">
    <?php if($msg): ?><div class="alert alert-<?php echo $msg_type==='success'?'success':'error'; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-circle'; ?>"></i><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="settings-layout">
      <!-- Nav latérale -->
      <div class="card">
        <div class="card-body">
          <div class="stab-list">
            <button class="stab<?php echo $tab==='profile'?' active':''; ?>" onclick="showTab('profile',this)"><i class="fas fa-user"></i>Mon profil</button>
            <button class="stab<?php echo $tab==='company'?' active':''; ?>" onclick="showTab('company',this)"><i class="fas fa-building"></i>Entreprise</button>
            <button class="stab<?php echo $tab==='system'?' active':''; ?>" onclick="showTab('system',this)"><i class="fas fa-chart-bar"></i>Système</button>
          </div>
          <!-- Avatar admin -->
          <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border);text-align:center">
            <div style="width:56px;height:56px;border-radius:14px;margin:0 auto .75rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;background:linear-gradient(135deg,var(--accent),var(--purple));color:#000">
              <?php echo strtoupper(substr($admin['username'],0,1)); ?>
            </div>
            <div style="font-weight:600;font-size:.9rem"><?php echo htmlspecialchars($admin['username']); ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)">Administrateur</div>
          </div>
        </div>
      </div>

      <!-- Panels -->
      <div>
        <!-- Profil -->
        <div class="spanel<?php echo $tab==='profile'?' active':''; ?>" id="panel-profile">
          <div class="card">
            <div class="card-head"><div class="card-ttl"><span class="ttl-ico" style="background:var(--accent-dim);color:var(--accent)"><i class="fas fa-user"></i></span>Informations personnelles</div></div>
            <div class="card-body">
              <form method="POST" class="form-row grid-2">
                <input type="hidden" name="action" value="profile">
                <div class="form-group"><label class="form-label">Nom d'utilisateur</label><input type="text" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin['email']??''); ?>"></div>
                <div class="form-group span-2" style="border-top:1px solid var(--border);padding-top:1.1rem;margin-top:.25rem">
                  <div style="font-size:.82rem;font-weight:600;color:var(--text-soft);margin-bottom:.875rem">Changer le mot de passe <span style="font-size:.75rem;font-weight:400;color:var(--text-muted)">(laisser vide pour ne pas modifier)</span></div>
                  <div class="form-row grid-3">
                    <div class="form-group"><label class="form-label">Actuel</label><input type="password" name="current_password" class="form-control" placeholder="••••••••"></div>
                    <div class="form-group"><label class="form-label">Nouveau</label><input type="password" name="new_password" class="form-control" placeholder="••••••••"></div>
                    <div class="form-group"><label class="form-label">Confirmer</label><input type="password" name="confirm_password" class="form-control" placeholder="••••••••"></div>
                  </div>
                </div>
                <div class="span-2"><button type="submit" class="btn btn-accent"><i class="fas fa-save"></i>Enregistrer le profil</button></div>
              </form>
            </div>
          </div>
        </div>

        <!-- Entreprise -->
        <div class="spanel<?php echo $tab==='company'?' active':''; ?>" id="panel-company">
          <div class="card">
            <div class="card-head"><div class="card-ttl"><span class="ttl-ico" style="background:var(--gold-dim);color:var(--gold)"><i class="fas fa-building"></i></span>Informations de l'entreprise</div></div>
            <div class="card-body">
              <form method="POST" class="form-row grid-2">
                <input type="hidden" name="action" value="company">
                <div class="form-group span-2"><label class="form-label">Nom de l'entreprise</label><input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_name']??'Hawas Bjaya'); ?>"></div>
                <div class="form-group"><label class="form-label">Téléphone</label><input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($settings['company_phone']??''); ?>" placeholder="+213 …"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Numéro WhatsApp</label><input type="text" name="whatsapp_number" class="form-control" value="<?php echo htmlspecialchars($settings['whatsapp_number']??'213775654995'); ?>" placeholder="213XXXXXXXXX"></div>
                <div class="form-group"><label class="form-label">Adresse</label><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($settings['address']??'Béjaïa'); ?>"></div>
                <div class="span-2"><button type="submit" class="btn btn-accent"><i class="fas fa-save"></i>Enregistrer</button></div>
              </form>
            </div>
          </div>
        </div>

        <!-- Système -->
        <div class="spanel<?php echo $tab==='system'?' active':''; ?>" id="panel-system">
          <div class="card">
            <div class="card-head"><div class="card-ttl"><span class="ttl-ico" style="background:var(--success-dim);color:var(--success)"><i class="fas fa-chart-bar"></i></span>Statistiques système</div></div>
            <div class="card-body">
              <div class="form-row grid-2">
                <?php
                $sysItems=[['Réservations','reservations','fa-calendar-check','c-blue'],['Clients','clients','fa-users','c-gold'],['Activités','activities','fa-water','c-green'],['Images galerie','gallery','fa-images','c-purple']];
                foreach($sysItems as [$lbl,$key,$ico,$col]): ?>
                <div style="padding:1rem;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border);display:flex;align-items:center;gap:.875rem">
                  <div class="card-icon-wrap <?php echo $col; ?>" style="margin:0"><i class="fas <?php echo $ico; ?>"></i></div>
                  <div>
                    <div style="font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:800"><?php echo $sys[$key]; ?></div>
                    <div style="font-size:.75rem;color:var(--text-muted)"><?php echo $lbl; ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border)">
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;font-size:.82rem">
                  <div style="color:var(--text-muted)">Version PHP</div><div style="color:var(--text-soft);text-align:right"><?php echo phpversion(); ?></div>
                  <div style="color:var(--text-muted)">Serveur</div><div style="color:var(--text-soft);text-align:right"><?php echo $_SERVER['SERVER_SOFTWARE']??'—'; ?></div>
                  <div style="color:var(--text-muted)">Timezone</div><div style="color:var(--text-soft);text-align:right"><?php echo date_default_timezone_get(); ?></div>
                  <div style="color:var(--text-muted)">Date serveur</div><div style="color:var(--text-soft);text-align:right"><?php echo date('d/m/Y H:i'); ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div></div>
<script>
function showTab(tab,btn){
  document.querySelectorAll('.spanel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.stab').forEach(b=>b.classList.remove('active'));
  document.getElementById('panel-'+tab).classList.add('active');
  btn.classList.add('active');
}
</script>
</body></html>