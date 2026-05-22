<?php
require_once '../config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: ../index.php'); exit; }
$database = new Database();
$db       = $database->getConnection();

$msg = ''; $msg_type = '';

// ════════════════════════════════════════════════════════════
// ACTIONS POST
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CRÉER ──────────────────────────────────────────────
    if ($action === 'create') {
        try {
            $dates   = [];
            $mode    = $_POST['date_mode'] ?? 'single';
            $ts_raw  = json_decode($_POST['ts_json'] ?? '[]', true) ?: [];
            $excl    = array_map('strval', json_decode($_POST['excl_json'] ?? '[]', true) ?: []);
            $act_id  = (int)$_POST['activity_id'];
            $max     = max(1, (int)$_POST['max_participants']);
            $pr      = trim($_POST['price_override'] ?? '');
            $price   = ($pr !== '' && is_numeric($pr)) ? (float)$pr : null;

            if (!$act_id) throw new Exception('Sélectionnez une activité');
            if (empty($ts_raw)) throw new Exception('Sélectionnez au moins un créneau');

            // ── Construire les dates ──────────────────────────────
            if ($mode === 'single' && !empty($_POST['date_single'])) {
                $dates[] = $_POST['date_single'];
            } elseif ($mode === 'range' && !empty($_POST['date_from']) && !empty($_POST['date_to'])) {
                $cur = strtotime($_POST['date_from']);
                $end = strtotime($_POST['date_to']);
                if (($end - $cur) / 86400 > 365) throw new Exception('Plage max 365 jours');
                while ($cur <= $end) {
                    if (!in_array(date('N', $cur), $excl)) $dates[] = date('Y-m-d', $cur);
                    $cur += 86400;
                }
            }
            if (empty($dates)) throw new Exception('Aucune date générée');

            $db->beginTransaction();

            // ── Résoudre les time_slot IDs ──────────────────────────
            // ts_raw peut être :
            //   - [10]                          (mode daily : id direct)
            //   - [{start:"HH:MM:SS",end:"..."}] (mode timeslot : paires)
            $ts_ids = [];
            $findTs = $db->prepare("SELECT id FROM time_slots WHERE start_time=:s AND end_time=:e LIMIT 1");
            $insTs  = $db->prepare("INSERT INTO time_slots(name,start_time,end_time,is_active) VALUES(:n,:s,:e,1)");

            foreach ($ts_raw as $item) {
                if (is_int($item) || (is_numeric($item) && !is_array($item))) {
                    // ID direct (mode daily : 10)
                    $ts_ids[] = (int)$item;
                } elseif (is_array($item) && isset($item['start'], $item['end'])) {
                    // Paire start/end (mode timeslot) → trouver ou créer
                    $s = $item['start']; // "08:00:00"
                    $e = $item['end'];   // "09:30:00"
                    // Normaliser
                    if (strlen($s) === 5) $s .= ':00';
                    if (strlen($e) === 5) $e .= ':00';
                    $findTs->execute([':s' => $s, ':e' => $e]);
                    $row = $findTs->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $ts_ids[] = (int)$row['id'];
                    } else {
                        $label = substr($s,0,5) . ' – ' . substr($e,0,5);
                        $insTs->execute([':n' => $label, ':s' => $s, ':e' => $e]);
                        $ts_ids[] = (int)$db->lastInsertId();
                    }
                }
            }

            if (empty($ts_ids)) throw new Exception('Impossible de résoudre les créneaux');

            $ok = 0; $skip = 0;
            $chk = $db->prepare("SELECT id FROM availability WHERE activity_id=:a AND date=:d AND time_slot_id=:t");
            $ins = $db->prepare("INSERT INTO availability(activity_id,date,time_slot_id,max_participants,price_override) VALUES(:a,:d,:t,:m,:p)");

            foreach ($dates as $date) {
                foreach ($ts_ids as $ts_id) {
                    $chk->execute([':a'=>$act_id,':d'=>$date,':t'=>$ts_id]);
                    if ($chk->fetch()) { $skip++; continue; }
                    $ins->execute([':a'=>$act_id,':d'=>$date,':t'=>$ts_id,':m'=>$max,':p'=>$price]);
                    $ok++;
                }
            }

            $db->commit();
            $msg = "$ok créneau(x) créé(s)" . ($skip ? " ($skip existant(s) ignoré(s))" : '');
            $msg_type = 'success';

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $msg = 'Erreur : ' . $e->getMessage();
            $msg_type = 'error';
        }
    }

    // ── MODIFIER ───────────────────────────────────────────
    if ($action === 'update') {
        $id    = (int)$_POST['avail_id'];
        $max   = max(1, (int)$_POST['max_participants']);
        $pr    = trim($_POST['price_override'] ?? '');
        $price = ($pr !== '' && is_numeric($pr)) ? (float)$pr : null;
        $db->prepare("UPDATE availability SET max_participants=:m, price_override=:p WHERE id=:id")
           ->execute([':m'=>$max,':p'=>$price,':id'=>$id]);
        $msg = 'Créneau modifié'; $msg_type = 'success';
    }

    // ── SUPPRIMER ──────────────────────────────────────────
    if ($action === 'delete') {
        $id  = (int)$_POST['avail_id'];
        $chk = $db->prepare("SELECT COUNT(*) FROM reservations WHERE availability_id=:id AND status IN('pending','confirmed')");
        $chk->execute([':id'=>$id]);
        if ($chk->fetchColumn() > 0) {
            $msg = 'Impossible : des réservations actives sont liées'; $msg_type = 'error';
        } else {
            $db->prepare("DELETE FROM availability WHERE id=:id")->execute([':id'=>$id]);
            $msg = 'Créneau supprimé'; $msg_type = 'success';
        }
    }
}

// ════════════════════════════════════════════════════════════
// DONNÉES
// ════════════════════════════════════════════════════════════
$date_f = $_GET['date']     ?? date('Y-m-d');
$act_f  = $_GET['activity'] ?? 'all';
$w = "WHERE av.date >= :today"; $p = [':today' => $date_f];
if ($act_f !== 'all') { $w .= " AND av.activity_id=:ac"; $p[':ac'] = $act_f; }

$avail = $db->prepare("
    SELECT av.*, a.name act_name, a.booking_type,
           ts.start_time, ts.end_time,
           (av.max_participants - av.current_reservations) available
    FROM availability av
    JOIN activities a  ON av.activity_id  = a.id
    JOIN time_slots ts ON av.time_slot_id = ts.id
    $w ORDER BY av.date ASC, ts.start_time ASC
");
$avail->execute($p);
$rows = $avail->fetchAll(PDO::FETCH_ASSOC);

$activities = $db->query("SELECT id, name, duration_minutes, max_participants, booking_mode FROM activities WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$slots      = $db->query("SELECT * FROM time_slots WHERE is_active=1 ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);

$active_page   = 'availability';
$pending_count = 0;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Disponibilités — Hawas Bjaya</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" onclick="toggleMobileSidebar()"><i class="fas fa-bars"></i></button>
      <div>
        <div class="page-title">Disponibilités</div>
        <div class="page-sub"><?php echo count($rows); ?> créneau(x) à venir</div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-ghost" onclick="openPlanning()">
        <i class="fas fa-sliders-h"></i>Plannings
      </button>
      <button class="btn btn-accent" onclick="openCreate()">
        <i class="fas fa-plus"></i>Nouveau créneau
      </button>
    </div>
  </header>

  <div class="page-body">

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>">
      <i class="fas fa-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
      <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- ── Filtres ──────────────────────────────────────── -->
    <div class="card">
      <div class="card-body" style="padding:1rem">
        <form method="GET" class="filter-bar">
          <div class="search-wrap" style="max-width:220px">
            <i class="fas fa-calendar"></i>
            <input type="date" name="date" value="<?php echo $date_f; ?>" onchange="this.form.submit()">
          </div>
          <select name="activity" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="all">Toutes activités</option>
            <?php foreach ($activities as $a): ?>
            <option value="<?php echo $a['id']; ?>"<?php echo $act_f == $a['id'] ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($a['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <a href="availability.php" class="btn btn-ghost btn-sm">
            <i class="fas fa-sync"></i>Réinitialiser
          </a>
        </form>
      </div>
    </div>

    <!-- ── Tableau ──────────────────────────────────────── -->
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th><th>Activité</th><th>Créneau</th>
              <th>Capacité</th><th>Réservées</th><th>Disponibles</th>
              <th>Prix override</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
            $pct = $r['max_participants'] > 0 ? round($r['current_reservations'] / $r['max_participants'] * 100) : 0;
            $col = $pct >= 100 ? 'var(--danger)' : ($pct >= 70 ? 'var(--warning)' : 'var(--success)');
          ?>
          <tr>
            <td>
              <strong><?php echo date('d/m/Y', strtotime($r['date'])); ?></strong>
              <div style="font-size:.72rem;color:var(--text-muted)"><?php echo date('l', strtotime($r['date'])); ?></div>
            </td>
            <td>
              <?php echo htmlspecialchars($r['act_name']); ?>
              <div style="margin-top:.25rem">
                <span class="badge <?php echo $r['booking_type']==='private'?'b-private':'b-shared'; ?>">
                  <?php echo $r['booking_type']==='private'?'Privatif':'Partagé'; ?>
                </span>
              </div>
            </td>
            <td>
              <strong><?php echo substr($r['start_time'],0,5); ?></strong>
              <span style="color:var(--text-muted)"> – <?php echo substr($r['end_time'],0,5); ?></span>
            </td>
            <td><strong><?php echo $r['max_participants']; ?></strong></td>
            <td><?php echo $r['current_reservations']; ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <strong style="color:<?php echo $col; ?>"><?php echo $r['available']; ?></strong>
                <div style="flex:1;min-width:50px">
                  <div class="pbar">
                    <div class="pfill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>"></div>
                  </div>
                </div>
              </div>
            </td>
            <td><?php echo $r['price_override']!==null ? number_format($r['price_override'],0,',',' ').' DA' : '—'; ?></td>
            <td>
              <div style="display:flex;gap:.35rem">
                <button class="btn btn-ghost btn-sm btn-icon" title="Modifier"
                  onclick='openEdit(<?php echo json_encode([
                    "id"              => $r["id"],
                    "max_participants" => $r["max_participants"],
                    "price_override"  => $r["price_override"],
                    "act_name"        => $r["act_name"],
                    "date"            => date("d/m/Y", strtotime($r["date"])),
                    "start_time"      => substr($r["start_time"],0,5),
                    "end_time"        => substr($r["end_time"],0,5)
                  ]); ?>)'>
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce créneau ?')">
                  <input type="hidden" name="action"   value="delete">
                  <input type="hidden" name="avail_id" value="<?php echo $r['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Supprimer">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="8">
            <div class="empty-box">
              <div class="empty-ico"><i class="fas fa-clock"></i></div>
              <h3>Aucun créneau</h3>
              <p>Créez un nouveau créneau ou modifiez le filtre</p>
            </div>
          </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /admin-layout -->

<!-- ══════════════════════════════════════════════════════════
     MODAL : CRÉER UN CRÉNEAU (inchangé)
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="createModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-head">
      <div class="modal-ttl">
        <i class="fas fa-plus" style="color:var(--accent);margin-right:.5rem"></i>Nouveau créneau
      </div>
      <button class="modal-close" onclick="closeModal('createModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="createForm" onsubmit="return prepareForm()">
      <input type="hidden" name="action"    value="create">
      <input type="hidden" name="date_mode" id="dateMode" value="single">
      <input type="hidden" name="ts_json"   id="tsJson"   value="[]">
      <input type="hidden" name="excl_json" id="exclJson" value="[]">
      <div class="modal-body">

        <div class="form-group">
          <label class="form-label">Activité *</label>
          <select name="activity_id" id="createActSel" class="form-control" required
                  onchange="onActivityChange()">
            <option value="">Sélectionner une activité</option>
            <?php foreach ($activities as $a): ?>
            <option value="<?php echo $a['id']; ?>"
                    data-dur="<?php echo (int)($a['duration_minutes'] ?? 60); ?>"
                    data-mode="<?php echo htmlspecialchars($a['booking_mode'] ?? 'timeslot'); ?>"
                    data-max="<?php echo (int)($a['max_participants'] ?? 10); ?>">
              <?php echo htmlspecialchars($a['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Tabs date -->
        <div>
          <div class="ftabs" style="margin-bottom:.875rem">
            <button type="button" class="ftab active" onclick="switchTab('single',this)">Date unique</button>
            <button type="button" class="ftab"        onclick="switchTab('range',this)">Plage de dates</button>
          </div>
          <div id="tab-single">
            <div class="form-group">
              <label class="form-label">Date *</label>
              <input type="date" name="date_single" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          <div id="tab-range" style="display:none">
            <div class="form-row grid-2">
              <div class="form-group"><label class="form-label">Du *</label><input type="date" name="date_from" id="dateFrom" class="form-control"></div>
              <div class="form-group"><label class="form-label">Au *</label><input type="date" name="date_to"   id="dateTo"   class="form-control"></div>
            </div>
            <div class="form-group" style="margin-top:.875rem">
              <label class="form-label">Exclure les jours <span style="font-size:.72rem;color:var(--text-muted)">(cliquez pour exclure)</span></label>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.375rem">
                <?php foreach(['1'=>'Lun','2'=>'Mar','3'=>'Mer','4'=>'Jeu','5'=>'Ven','6'=>'Sam','7'=>'Dim'] as $v=>$l): ?>
                <button type="button" class="day-btn" data-dow="<?php echo $v; ?>" onclick="toggleDay(this)"
                  style="padding:.4rem .8rem;border-radius:8px;font-size:.82rem;border:1px solid var(--border);
                         background:var(--surface-2);color:var(--text-soft);cursor:pointer;transition:all .18s">
                  <?php echo $l; ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Heure ouverture/fermeture -->
        <div id="hoursRow" style="display:none">
          <div class="form-row grid-2" style="margin-bottom:.5rem">
            <div class="form-group">
              <label class="form-label">Heure ouverture <span style="font-size:.7rem;color:var(--text-muted)">— depuis le planning ou manuel</span></label>
              <input type="time" id="cOpenTime" class="form-control" value="08:00" onchange="generateSlotButtons()">
            </div>
            <div class="form-group">
              <label class="form-label">Heure fermeture</label>
              <input type="time" id="cCloseTime" class="form-control" value="18:00" onchange="generateSlotButtons()">
            </div>
          </div>
        </div>

        <!-- Créneaux — générés dynamiquement selon l'activité -->
        <div class="form-group" id="slotGroup" style="display:none">
          <label class="form-label">
            Créneaux <span id="slotGroupLabel" style="color:var(--text-muted);font-weight:400"></span>
            <span style="font-size:.72rem;color:var(--text-muted)">(cliquez pour sélectionner)</span>
          </label>
          <div id="slotContainer" style="display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem"></div>
        </div>

        <!-- Message mode daily ou nightly -->
        <div id="modeInfo" style="display:none;padding:.65rem .875rem;border-radius:var(--radius);
             font-size:.83rem;margin-bottom:.5rem"></div>

        <div class="form-row grid-2">
          <div class="form-group">
            <label class="form-label">Capacité max *</label>
            <input type="number" name="max_participants" class="form-control" min="1" value="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prix override (DA) <span style="font-size:.7rem;color:var(--text-muted)">optionnel</span></label>
            <input type="number" name="price_override" class="form-control" min="0" placeholder="Par défaut de l'activité">
          </div>
        </div>

        <div id="summary" style="display:none;padding:.75rem 1rem;background:var(--accent-dim);
             border:1px solid rgba(56,189,248,.25);border-radius:var(--radius);font-size:.82rem;color:var(--accent)">
          <i class="fas fa-info-circle"></i> <span id="summaryText"></span>
        </div>

      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createModal')">Annuler</button>
        <button type="submit" class="btn btn-accent"><i class="fas fa-check"></i>Créer les créneaux</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL : MODIFIER UN CRÉNEAU (inchangé)
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-ttl">
        <i class="fas fa-edit" style="color:var(--gold);margin-right:.5rem"></i>Modifier le créneau
      </div>
      <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"   value="update">
      <input type="hidden" name="avail_id" id="editId">
      <div class="modal-body">
        <div style="padding:.875rem;background:var(--surface-2);border-radius:var(--radius);border:1px solid var(--border)">
          <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:.4rem">Créneau</div>
          <div id="editInfo" style="font-size:.9rem;font-weight:600;color:var(--text)"></div>
        </div>
        <div class="form-row grid-2">
          <div class="form-group">
            <label class="form-label">Capacité max *</label>
            <input type="number" name="max_participants" id="editMax" class="form-control" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prix override (DA)</label>
            <input type="number" name="price_override" id="editPrice" class="form-control" min="0" placeholder="—">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Annuler</button>
        <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i>Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL : PLANNINGS + AUTO-GÉNÉRER + BLOQUER
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="planningModal">
  <div class="modal" style="max-width:700px">
    <div class="modal-head">
      <div class="modal-ttl">
        <i class="fas fa-sliders-h" style="color:var(--accent);margin-right:.5rem"></i>
        Plannings &amp; Génération automatique
      </div>
      <button class="modal-close" onclick="closeModal('planningModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">

      <div style="background:var(--accent-dim);border:1px solid rgba(56,189,248,.2);border-radius:var(--radius);
                  padding:.65rem 1rem;font-size:.82rem;color:var(--accent);margin-bottom:1rem">
        <i class="fas fa-info-circle" style="margin-right:.35rem"></i>
        Définissez les heures d'ouverture par activité. Les créneaux sont calculés automatiquement
        selon la durée. Vous pouvez toujours créer/modifier des créneaux individuels via le bouton
        <strong>Nouveau créneau</strong>.
      </div>

      <!-- Tabs -->
      <div class="ftabs" style="margin-bottom:1rem">
        <button type="button" class="ftab active" onclick="switchPTab('schedules',this)">
          <i class="fas fa-clock" style="margin-right:.3rem"></i>Plannings
        </button>
        <button type="button" class="ftab" onclick="switchPTab('generate',this)">
          <i class="fas fa-magic" style="margin-right:.3rem"></i>Auto-générer
        </button>
        <button type="button" class="ftab" onclick="switchPTab('block',this)">
          <i class="fas fa-ban" style="margin-right:.3rem"></i>Bloquer des dates
        </button>
      </div>

      <!-- ─────────────────────────────────────────────────
           TAB PLANNINGS
      ──────────────────────────────────────────────────── -->
      <div id="ptab-schedules">
        <div id="pSchedList" style="margin-bottom:1rem"></div>

        <!-- Formulaire ajout/modif -->
        <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:1rem">
          <div style="font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:.75rem">
            <i class="fas fa-plus" style="color:var(--accent);margin-right:.35rem"></i>
            Ajouter / modifier un planning
          </div>
          <input type="hidden" id="pSchedId">
          <div class="form-row grid-2">
            <div class="form-group">
              <label class="form-label">Activité *</label>
              <select id="pActivity" class="form-control" onchange="updateSchedPreview()">
                <option value="">— Choisir —</option>
                <?php foreach ($activities as $a): ?>
                <option value="<?php echo $a['id']; ?>"
                        data-dur="<?php echo (int)($a['duration_minutes'] ?? 60); ?>"
                        data-max="<?php echo (int)($a['max_participants'] ?? 10); ?>">
                  <?php echo htmlspecialchars($a['name']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Jours</label>
              <select id="pDay" class="form-control">
                <option value="0">Tous les jours</option>
                <option value="1">Lundi</option><option value="2">Mardi</option>
                <option value="3">Mercredi</option><option value="4">Jeudi</option>
                <option value="5">Vendredi</option><option value="6">Samedi</option>
                <option value="7">Dimanche</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Heure ouverture</label>
              <input type="time" id="pOpen" class="form-control" value="08:00" onchange="updateSchedPreview()">
            </div>
            <div class="form-group">
              <label class="form-label">Heure fermeture</label>
              <input type="time" id="pClose" class="form-control" value="18:00" onchange="updateSchedPreview()">
            </div>
            <div class="form-group">
              <label class="form-label">Capacité / créneau</label>
              <input type="number" id="pCap" class="form-control" value="10" min="1">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end">
              <button type="button" class="btn btn-accent" style="width:100%" onclick="saveSched()">
                <i class="fas fa-check"></i>Enregistrer le planning
              </button>
            </div>
          </div>
          <div id="pSchedPreview" style="display:none;margin-top:.5rem;padding:.6rem .875rem;
               background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);
               font-size:.79rem;color:var(--text-soft)">
          </div>
        </div>
      </div>

      <!-- ─────────────────────────────────────────────────
           TAB AUTO-GÉNÉRER
      ──────────────────────────────────────────────────── -->
      <div id="ptab-generate" style="display:none">
        <div class="form-row grid-2">
          <div class="form-group">
            <label class="form-label">Activité *</label>
            <select id="gActivity" class="form-control" onchange="updateGenPreview()">
              <option value="">— Choisir —</option>
              <?php foreach ($activities as $a): ?>
              <option value="<?php echo $a['id']; ?>"
                      data-dur="<?php echo (int)($a['duration_minutes'] ?? 60); ?>"
                      data-max="<?php echo (int)($a['max_participants'] ?? 10); ?>">
                <?php echo htmlspecialchars($a['name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div id="gInfo" style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Capacité max</label>
            <input type="number" id="gCap" class="form-control" value="10" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Du *</label>
            <input type="date" id="gStart" class="form-control" value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Au *</label>
            <input type="date" id="gEnd" class="form-control"
                   value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Heure ouverture</label>
            <input type="time" id="gOpen" class="form-control" value="08:00" onchange="updateGenPreview()">
          </div>
          <div class="form-group">
            <label class="form-label">Heure fermeture</label>
            <input type="time" id="gClose" class="form-control" value="18:00" onchange="updateGenPreview()">
          </div>
          <div class="form-group span-2">
            <div id="gPreview" style="display:none;padding:.65rem .875rem;background:var(--accent-dim);
                 border:1px solid rgba(56,189,248,.2);border-radius:var(--radius);font-size:.79rem;color:var(--accent)">
            </div>
          </div>
          <div class="form-group span-2" style="display:flex;align-items:center;gap:.75rem">
            <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer;margin:0">
              <input type="checkbox" id="gOverwrite"> Écraser si déjà existant
            </label>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;margin-top:.25rem">
          <button type="button" class="btn btn-accent" onclick="doGenerate()">
            <i class="fas fa-magic"></i>Générer les créneaux
          </button>
          <span id="gResult" style="font-size:.82rem;color:var(--text-soft)"></span>
        </div>
      </div>

      <!-- ─────────────────────────────────────────────────
           TAB BLOQUER DES DATES
      ──────────────────────────────────────────────────── -->
      <div id="ptab-block" style="display:none">
        <p style="font-size:.82rem;color:var(--text-soft);margin-bottom:.875rem">
          Bloquer une ou toutes les activités sur une période (météo, maintenance, fermeture...).
        </p>
        <div class="form-row grid-2">
          <div class="form-group">
            <label class="form-label">Activité</label>
            <select id="bActivity" class="form-control">
              <option value="">— Toutes les activités —</option>
              <?php foreach ($activities as $a): ?>
              <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Raison</label>
            <input type="text" id="bReason" class="form-control" placeholder="Météo, maintenance...">
          </div>
          <div class="form-group">
            <label class="form-label">Du *</label>
            <input type="date" id="bStart" class="form-control" value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Au (inclus) *</label>
            <input type="date" id="bEnd" class="form-control" value="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;margin-top:.25rem">
          <button type="button" class="btn btn-danger" onclick="doBlock()">
            <i class="fas fa-ban"></i>Bloquer ces dates
          </button>
          <span id="bResult" style="font-size:.82rem;color:var(--text-soft)"></span>
        </div>

        <div style="margin-top:1.5rem">
          <div style="font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:.5rem">
            Dates bloquées à venir
          </div>
          <div id="bList"></div>
        </div>
      </div>

    </div><!-- /modal-body -->
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('planningModal')">Fermer</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->
<script>
// ── Modals ───────────────────────────────────────────────────
function openCreate() { document.getElementById('createModal').classList.add('show'); }
function openPlanning() {
  document.getElementById('planningModal').classList.add('show');
  loadSchedList();
}
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-wrap').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);

// ── Tabs modal créer ─────────────────────────────────────────
function switchTab(t, btn) {
  document.getElementById('tab-single').style.display = t === 'single' ? 'block' : 'none';
  document.getElementById('tab-range').style.display  = t === 'range'  ? 'block' : 'none';
  document.getElementById('dateMode').value = t;
  document.querySelectorAll('#createModal .ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  updateSummary();
}

// ── Activités data (pour génération dynamique) ───────────────
const ACTIVITIES_DATA = <?php echo json_encode(
  array_column(
    array_map(fn($a) => [
      'id'                => (int)$a['id'],
      'duration_minutes'  => (int)($a['duration_minutes'] ?? 60),
      'max_participants'  => (int)($a['max_participants']  ?? 10),
      'booking_mode'      => $a['booking_mode'] ?? 'timeslot',
    ], $activities),
    null
  )
); ?>;

// Plannings depuis la DB pour pré-remplir les heures
let schedulesCache = null;

async function getSchedules() {
  if (schedulesCache) return schedulesCache;
  const r = await fetch('../api/get_schedules.php');
  const d = await r.json();
  schedulesCache = d.schedules || [];
  return schedulesCache;
}

// ── Changement d'activité → générer les bons créneaux ────────
async function onActivityChange() {
  const sel     = document.getElementById('createActSel');
  const opt     = sel.options[sel.selectedIndex];
  const actId   = parseInt(sel.value);
  const dur     = parseInt(opt.dataset.dur  || 60);
  const mode    = opt.dataset.mode || 'timeslot';
  const maxDef  = parseInt(opt.dataset.max  || 10);
  const hoursRow= document.getElementById('hoursRow');
  const slotGrp = document.getElementById('slotGroup');
  const modeInfo= document.getElementById('modeInfo');

  // Reset
  document.getElementById('slotContainer').innerHTML = '';
  document.getElementById('tsJson').value = '[]';
  hoursRow.style.display = 'none';
  slotGrp.style.display  = 'none';
  modeInfo.style.display = 'none';
  updateSummary();

  if (!actId) return;

  // Pré-remplir capacité
  document.querySelector('[name="max_participants"]').value = maxDef;

  if (mode === 'nightly') {
    modeInfo.style.cssText = 'display:block;padding:.65rem .875rem;border-radius:var(--radius);font-size:.83rem;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:var(--gold)';
    modeInfo.innerHTML = '<i class="fas fa-info-circle" style="margin-right:.4rem"></i>Mode <strong>Hébergement</strong> — gérez les disponibilités depuis la page Hébergements.';
    return;
  }

  if (mode === 'daily') {
    modeInfo.style.cssText = 'display:block;padding:.65rem .875rem;border-radius:var(--radius);font-size:.83rem;background:var(--accent-dim);border:1px solid rgba(56,189,248,.2);color:var(--accent)';
    modeInfo.innerHTML = '<i class="fas fa-check-circle" style="margin-right:.4rem"></i>Mode <strong>Journée complète</strong> — un seul créneau "Journée" sera créé pour la/les date(s) sélectionnée(s).';
    // On crée automatiquement le slot journée (id=10) dans tsJson
    document.getElementById('tsJson').value = JSON.stringify([10]);
    updateSummary();
    return;
  }

  // Mode timeslot → afficher les heures + générer les boutons
  hoursRow.style.display = 'block';
  slotGrp.style.display  = 'block';
  document.getElementById('slotGroupLabel').textContent = `(${dur} min par créneau)`;

  // Essayer de récupérer les heures depuis le planning de cette activité
  const scheds = await getSchedules();
  const sched  = scheds.find(s => s.activity_id == actId);
  if (sched) {
    document.getElementById('cOpenTime').value  = sched.open_time.slice(0,5);
    document.getElementById('cCloseTime').value = sched.close_time.slice(0,5);
  } else {
    document.getElementById('cOpenTime').value  = '08:00';
    document.getElementById('cCloseTime').value = '18:00';
  }

  generateSlotButtons();
}

// ── Générer les boutons de créneaux selon durée + heures ─────
function generateSlotButtons() {
  const sel    = document.getElementById('createActSel');
  if (!sel.value) return;

  const opt    = sel.options[sel.selectedIndex];
  const openT  = document.getElementById('cOpenTime').value;
  const closeT = document.getElementById('cCloseTime').value;
  const cont   = document.getElementById('slotContainer');

  // ── Sécurité : durée ──────────────────────────────────────────
  let dur = parseInt(opt.dataset.dur);
  if (!dur || dur <= 0) {
    cont.innerHTML = '<div style="color:var(--danger);font-size:.82rem;padding:.5rem">'
      + '<i class="fas fa-exclamation-triangle" style="margin-right:.35rem"></i>'
      + 'Durée non définie pour cette activité. Modifiez-la dans la page Activités.</div>';
    return;
  }

  // ── Sécurité : heures valides ──────────────────────────────────
  if (!openT || !closeT || openT >= closeT) {
    cont.innerHTML = '<div style="color:var(--danger);font-size:.82rem;padding:.5rem">'
      + '<i class="fas fa-exclamation-triangle" style="margin-right:.35rem"></i>'
      + 'Héures invalides (ouverture doit être avant fermeture).</div>';
    return;
  }

  // ── Calcul des paires horaires (100% JS, sans appel API) ──
  // On stocke start/end en string HH:MM:SS directement dans les boutons
  // Le PHP créera/trouvera les time_slots au moment du submit
  const oMin  = toMinutes(openT);
  const cMin  = toMinutes(closeT);
  const pairs = [];
  let curMin  = oMin;

  while (curMin + dur <= cMin) {
    const endMin = curMin + dur;
    pairs.push({
      start: toHHMMSS(curMin),
      end:   toHHMMSS(endMin),
      label: toHHMM(curMin) + ' – ' + toHHMM(endMin)
    });
    curMin = endMin;
    if (pairs.length >= 48) break; // max 48 créneaux par sécurité
  }

  if (!pairs.length) {
    cont.innerHTML = '<div style="color:var(--danger);font-size:.82rem;padding:.5rem">'
      + '<i class="fas fa-exclamation-triangle" style="margin-right:.35rem"></i>'
      + 'Aucun créneau possible — la durée ('+ dur +'min) dépasse la plage horaire.</div>';
    return;
  }

  // ── Générer les boutons (data-start + data-end, pas d'ID) ───────
  cont.innerHTML = pairs.map(p => `
    <button type="button" class="slot-btn"
      data-start="${p.start}" data-end="${p.end}"
      onclick="toggleSlot(this)"
      style="padding:.65rem .875rem;border-radius:var(--radius);font-size:.84rem;
             border:1px solid var(--border);background:var(--surface-2);
             color:var(--text-soft);cursor:pointer;transition:all .18s;text-align:left">
      <i class="fas fa-clock" style="margin-right:.4rem;font-size:.75rem;color:var(--accent)"></i>
      ${p.label}
    </button>`).join('');

  updateSummary();
}

// ── Helpers conversion temps ──────────────────────────────────
function toMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + (m || 0);
}
function toHHMMSS(min) {
  const h = Math.floor(min / 60), m = min % 60;
  return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':00';
}
function toHHMM(min) {
  const h = Math.floor(min / 60), m = min % 60;
  return String(h).padStart(2,'0') + 'h' + String(m).padStart(2,'0');
}

// ── Sélection d'un créneau ───────────────────────────────────
function toggleSlot(btn) {
  btn.classList.toggle('slot-sel');
  const on = btn.classList.contains('slot-sel');
  btn.style.background  = on ? 'var(--accent-dim)' : 'var(--surface-2)';
  btn.style.borderColor = on ? 'var(--accent)'     : 'var(--border)';
  btn.style.color       = on ? 'var(--accent)'     : 'var(--text-soft)';
  btn.style.fontWeight  = on ? '600'               : '400';
  updateSummary();
}

function toggleDay(btn) {
  btn.classList.toggle('excl');
  const on = btn.classList.contains('excl');
  btn.style.background  = on ? 'rgba(239,68,68,.12)' : 'var(--surface-2)';
  btn.style.borderColor = on ? 'var(--danger)'       : 'var(--border)';
  btn.style.color       = on ? 'var(--danger)'       : 'var(--text-soft)';
  updateSummary();
}

function updateSummary() {
  const sel   = [...document.querySelectorAll('.slot-btn.slot-sel')];
  const excl  = [...document.querySelectorAll('.day-btn.excl')].map(b => b.dataset.dow);
  const mode  = document.getElementById('dateMode').value;
  const sum   = document.getElementById('summary');
  const txt   = document.getElementById('summaryText');
  if (!sel.length) { sum.style.display = 'none'; return; }

  let dateInfo = '';
  if (mode === 'range') {
    const df = document.getElementById('dateFrom').value;
    const dt = document.getElementById('dateTo').value;
    if (df && dt) {
      const days = Math.round((new Date(dt) - new Date(df)) / 86400000) + 1;
      dateInfo = `${days} jour(s)`;
      if (excl.length) dateInfo += ` (${excl.length} jour(s)/sem. exclus)`;
    }
  } else {
    dateInfo = '1 date';
  }
  txt.textContent = `${sel.length} créneau(x) × ${dateInfo || '…'}`;
  sum.style.display = 'block';
}

function prepareForm() {
  if (!document.getElementById('createActSel').value) {
    alert('Sélectionnez une activité'); return false;
  }

  const excl = [...document.querySelectorAll('.day-btn.excl')].map(b => b.dataset.dow);
  document.getElementById('exclJson').value = JSON.stringify(excl);

  const modeInfo = document.getElementById('modeInfo');
  const isDaily  = modeInfo.style.display !== 'none' &&
                   modeInfo.innerHTML.includes('Journée');
  const isNightly= modeInfo.style.display !== 'none' &&
                   modeInfo.innerHTML.includes('Hébergement');

  if (isNightly) {
    alert('Les hébergements se gèrent depuis la page Hébergements.');
    return false;
  }

  if (isDaily) {
    // tsJson = [10] déjà set dans onActivityChange
    return true;
  }

  // Mode timeslot : récupérer les paires {start, end} des boutons sélectionnés
  const selected = [...document.querySelectorAll('.slot-btn.slot-sel')];
  if (!selected.length) { alert('Sélectionnez au moins un créneau'); return false; }

  // Stocker les paires start/end — le PHP créera/trouvera les time_slots
  const pairs = selected.map(b => ({ start: b.dataset.start, end: b.dataset.end }));
  document.getElementById('tsJson').value = JSON.stringify(pairs);
  return true;
}

// ── Modal modifier ───────────────────────────────────────────
function openEdit(r) {
  document.getElementById('editId').value    = r.id;
  document.getElementById('editMax').value   = r.max_participants;
  document.getElementById('editPrice').value = r.price_override ?? '';
  document.getElementById('editInfo').textContent =
    `${r.act_name} — ${r.date} — ${r.start_time} – ${r.end_time}`;
  document.getElementById('editModal').classList.add('show');
}

// ════════════════════════════════════════════════════════════
// MODAL PLANNINGS
// ════════════════════════════════════════════════════════════
const dayNames = ['Tous les jours','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

function switchPTab(tab, btn) {
  ['schedules','generate','block'].forEach(t =>
    document.getElementById('ptab-'+t).style.display = t===tab ? 'block' : 'none'
  );
  document.querySelectorAll('#planningModal .ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (tab === 'block') loadBlockList();
}

// ── Plannings ────────────────────────────────────────────────
async function loadSchedList() {
  const r    = await fetch('../api/get_schedules.php');
  const data = await r.json();
  const list = document.getElementById('pSchedList');
  const scheds = data.schedules || [];

  if (!scheds.length) {
    list.innerHTML = '<p style="font-size:.82rem;color:var(--text-muted)">Aucun planning défini.</p>';
    return;
  }
  list.innerHTML = scheds.map(s => `
    <div style="display:flex;justify-content:space-between;align-items:center;
                padding:.6rem .875rem;border:1px solid var(--border);border-radius:var(--radius);
                background:var(--card-bg);margin-bottom:.4rem">
      <div>
        <strong style="font-size:.85rem;color:var(--text)">${s.activity_name}</strong>
        <span style="margin-left:.5rem;font-size:.72rem;color:var(--text-muted)">${dayNames[s.day_of_week]||'Tous les jours'}</span>
        <div style="font-size:.75rem;color:var(--text-soft);margin-top:.1rem">
          <i class="fas fa-clock" style="margin-right:.3rem"></i>${s.open_time.slice(0,5)} – ${s.close_time.slice(0,5)}
          &nbsp;·&nbsp;
          <i class="fas fa-users" style="margin-right:.3rem"></i>${s.max_capacity} places
          ${s.duration_minutes ? `&nbsp;·&nbsp;<i class="fas fa-stopwatch" style="margin-right:.3rem"></i>${s.duration_minutes} min` : ''}
        </div>
      </div>
      <div style="display:flex;gap:.35rem">
        <button class="btn btn-ghost btn-sm btn-icon" onclick='editSched(${JSON.stringify(s).replace(/"/g,"&quot;")})'><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteSched(${s.id})"><i class="fas fa-trash"></i></button>
      </div>
    </div>
  `).join('');
}

function editSched(s) {
  document.getElementById('pSchedId').value  = s.id;
  document.getElementById('pActivity').value = s.activity_id;
  document.getElementById('pDay').value      = s.day_of_week;
  document.getElementById('pOpen').value     = s.open_time.slice(0,5);
  document.getElementById('pClose').value    = s.close_time.slice(0,5);
  document.getElementById('pCap').value      = s.max_capacity;
  updateSchedPreview();
}

function updateSchedPreview() {
  const sel  = document.getElementById('pActivity');
  const opt  = sel.options[sel.selectedIndex];
  const dur  = parseInt(opt?.dataset?.dur ?? 60);
  const oT   = document.getElementById('pOpen').value;
  const cT   = document.getElementById('pClose').value;
  const prev = document.getElementById('pSchedPreview');
  if (!sel.value || !oT || !cT) { prev.style.display='none'; return; }

  const oTS  = new Date('2000-01-01T'+oT);
  const cTS  = new Date('2000-01-01T'+cT);
  const slots = [];
  let cur = new Date(oTS);
  while (cur.getTime() + dur*60000 <= cTS.getTime() && slots.length < 20) {
    const end = new Date(cur.getTime()+dur*60000);
    slots.push(`${cur.toTimeString().slice(0,5)} – ${end.toTimeString().slice(0,5)}`);
    cur = end;
  }
  prev.style.display = 'block';
  prev.innerHTML = `<i class="fas fa-check-circle" style="color:var(--success);margin-right:.3rem"></i>
    <strong>${slots.length} créneau(x)</strong> de ${dur} min :<br>
    <span style="display:block;margin-top:.3rem">
      ${slots.map(s=>`<span style="display:inline-block;background:var(--card-bg);border:1px solid var(--border);
        border-radius:4px;padding:.1rem .4rem;margin:.15rem;font-size:.73rem">${s}</span>`).join('')}
    </span>`;
}

async function saveSched() {
  const id  = document.getElementById('pSchedId').value;
  const act = document.getElementById('pActivity').value;
  if (!act) { alert('Choisir une activité'); return; }
  const r = await fetch('../api/save_schedule.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      id:           id ? parseInt(id) : undefined,
      activity_id:  parseInt(act),
      day_of_week:  parseInt(document.getElementById('pDay').value),
      open_time:    document.getElementById('pOpen').value+':00',
      close_time:   document.getElementById('pClose').value+':00',
      max_capacity: parseInt(document.getElementById('pCap').value),
      is_active:    1
    })
  });
  const d = await r.json();
  if (d.success) { document.getElementById('pSchedId').value=''; loadSchedList(); }
  else alert('Erreur : '+d.error);
}

async function deleteSched(id) {
  if (!confirm('Supprimer ce planning ?')) return;
  const r = await fetch('../api/save_schedule.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id})
  });
  const d = await r.json();
  if (d.success) loadSchedList();
}

// ── Auto-générer ─────────────────────────────────────────────
function updateGenPreview() {
  const sel  = document.getElementById('gActivity');
  const opt  = sel.options[sel.selectedIndex];
  const dur  = parseInt(opt?.dataset?.dur ?? 60);
  const max  = opt?.dataset?.max;
  const oT   = document.getElementById('gOpen').value;
  const cT   = document.getElementById('gClose').value;
  const prev = document.getElementById('gPreview');
  const info = document.getElementById('gInfo');

  if (max) { info.textContent=`Durée : ${dur} min · Capacité défaut : ${max}`; document.getElementById('gCap').value=max; }

  if (!sel.value || !oT || !cT) { prev.style.display='none'; return; }
  const oTS = new Date('2000-01-01T'+oT), cTS = new Date('2000-01-01T'+cT);
  const slots = [];
  let cur = new Date(oTS);
  while (cur.getTime()+dur*60000 <= cTS.getTime() && slots.length<20) {
    const end=new Date(cur.getTime()+dur*60000);
    slots.push(`${cur.toTimeString().slice(0,5)} – ${end.toTimeString().slice(0,5)}`);
    cur=end;
  }
  prev.style.display='block';
  prev.innerHTML=`<i class="fas fa-info-circle" style="margin-right:.3rem"></i>
    <strong>${slots.length} créneau(x)/jour</strong> :
    ${slots.map(s=>`<span style="display:inline-block;background:rgba(255,255,255,.4);border-radius:4px;
      padding:.1rem .35rem;margin:.1rem;font-size:.73rem">${s}</span>`).join('')}`;
}

async function doGenerate() {
  const actId = document.getElementById('gActivity').value;
  const start = document.getElementById('gStart').value;
  const end   = document.getElementById('gEnd').value;
  const res   = document.getElementById('gResult');
  if (!actId||!start||!end) { alert('Remplir tous les champs'); return; }
  res.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Génération…';
  const r=await fetch('../api/generate_availability.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({
      activity_id:parseInt(actId),start_date:start,end_date:end,
      max_capacity:parseInt(document.getElementById('gCap').value),
      open_time:document.getElementById('gOpen').value+':00',
      close_time:document.getElementById('gClose').value+':00',
      overwrite:document.getElementById('gOverwrite').checked
    })
  });
  const d=await r.json();
  res.innerHTML=d.success
    ?`<span style="color:var(--success)"><i class="fas fa-check-circle"></i> ${d.created} créé(s)${d.skipped?` · ${d.skipped} ignoré(s)`:''}</span>`
    :`<span style="color:var(--danger)"><i class="fas fa-exclamation-circle"></i> ${d.error}</span>`;
}

// ── Bloquer des dates ─────────────────────────────────────────
async function doBlock() {
  const actId  = document.getElementById('bActivity').value || null;
  const start  = document.getElementById('bStart').value;
  const end    = document.getElementById('bEnd').value;
  const reason = document.getElementById('bReason').value || 'Bloqué par admin';
  const res    = document.getElementById('bResult');
  if (!start||!end) { alert('Choisir les dates'); return; }
  if (start>end)    { alert('Date fin doit être après début'); return; }
  const days=Math.round((new Date(end)-new Date(start))/86400000)+1;
  if (!confirm(`Bloquer ${days} jour(s) ?`)) return;
  const dates=[];
  let c=new Date(start);const e=new Date(end);
  while(c<=e){dates.push(c.toISOString().slice(0,10));c.setDate(c.getDate()+1);}
  const r=await fetch('../api/block_dates.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({activity_id:actId?parseInt(actId):null,dates,reason})
  });
  const d=await r.json();
  res.innerHTML=d.success
    ?`<span style="color:var(--success)"><i class="fas fa-check-circle"></i> ${d.blocked_count} date(s) bloquée(s)</span>`
    :`<span style="color:var(--danger)"><i class="fas fa-exclamation-circle"></i> ${d.error}</span>`;
  loadBlockList();
}

async function loadBlockList() {
  const r=await fetch('../api/get_blocked_dates.php?limit=20');
  const data=await r.json();
  const list=document.getElementById('bList');
  const rows=data.dates||[];
  if(!rows.length){list.innerHTML='<span style="font-size:.82rem;color:var(--text-muted)">Aucune date bloquée à venir</span>';return;}
  list.innerHTML=`<table style="width:100%;border-collapse:collapse;font-size:.8rem">
    <thead><tr style="color:var(--text-muted)">
      <th style="text-align:left;padding:.3rem .5rem">Date</th>
      <th style="text-align:left;padding:.3rem .5rem">Activité</th>
      <th style="text-align:left;padding:.3rem .5rem">Raison</th>
      <th></th>
    </tr></thead>
    <tbody>${rows.map(r=>`
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:.35rem .5rem;font-weight:600">${r.blocked_date}</td>
        <td style="padding:.35rem .5rem;color:var(--text-soft)">${r.activity_name||'<em>Toutes</em>'}</td>
        <td style="padding:.35rem .5rem;color:var(--text-muted)">${r.reason||'—'}</td>
        <td style="padding:.35rem .5rem">
          <button class="btn btn-danger btn-sm btn-icon" onclick="unblockRow(${r.id})">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>`).join('')}
    </tbody></table>`;
}

async function unblockRow(id) {
  const r=await fetch('../api/block_dates.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'delete',id})
  });
  const d=await r.json();
  if(d.success) loadBlockList();
}
</script>
</body>
</html>
