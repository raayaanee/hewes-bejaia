<?php
require_once '../config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: ../index.php'); exit; }
$database = new Database();
$db       = $database->getConnection();

$active_page   = 'accommodations';
$pending_count = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn();

// Stats
$totalAccos   = (int)$db->query("SELECT COUNT(*) FROM accommodations WHERE is_active=1")->fetchColumn();
$totalCap     = (int)$db->query("SELECT COALESCE(SUM(capacity),0) FROM accommodations WHERE is_active=1")->fetchColumn();
$bookedNights = (int)$db->query("SELECT COUNT(*) FROM accommodation_blocked_dates WHERE reservation_id IS NOT NULL AND blocked_date >= CURDATE()")->fetchColumn();
$monthRevenue = (float)$db->query("SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE accommodation_id IS NOT NULL AND status IN('confirmed','completed') AND MONTH(check_in_date)=MONTH(CURDATE()) AND YEAR(check_in_date)=YEAR(CURDATE())")->fetchColumn();

// Hébergements
$accos = $db->query("SELECT * FROM accommodations ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Réservations hébergement (toutes, triées par date décroissante)
$resvStmt = $db->query("
    SELECT r.*,
           a.name AS acco_name
    FROM reservations r
    LEFT JOIN accommodations a ON r.accommodation_id = a.id
    WHERE r.accommodation_id IS NOT NULL
    ORDER BY r.created_at DESC
    LIMIT 200
");
$accoReservations = $resvStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'pending'   => ['#fef3c7','#92400e','En attente'],
    'confirmed' => ['#d1fae5','#065f46','Confirmé'],
    'completed' => ['#dbeafe','#1e40af','Terminé'],
    'cancelled' => ['#fee2e2','#991b1b','Annulé'],
];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Hébergements — Hawas Bjaya</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/admin.css">
<style>
/* ── Grille hébergements ─────────────────────────────────── */
.acco-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:1rem}
.acco-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;transition:transform .2s,box-shadow .2s,border-color .2s}
.acco-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:var(--border-2)}
.acco-thumb{width:100%;height:155px;object-fit:cover;display:block;background:var(--surface-2)}
.acco-thumb-ph{width:100%;height:155px;display:flex;align-items:center;justify-content:center;background:var(--surface-2)}
.acco-body{padding:1rem}
.acco-name{font-weight:700;font-size:.93rem;color:var(--text);margin-bottom:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acco-addr{font-size:.72rem;color:var(--text-muted);margin-bottom:.7rem}
.acco-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;font-size:.83rem}
.acco-price{font-weight:700;color:var(--success);font-size:.9rem}
.acco-actions{display:flex;gap:.4rem;margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border)}
.badge-type{font-size:.63rem;padding:.2rem .55rem;border-radius:20px;font-weight:700;letter-spacing:.03em}
/* ── Calendrier ──────────────────────────────────────────── */
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.cal-dow{text-align:center;font-size:.65rem;font-weight:700;color:var(--text-muted);padding:.3rem 0;letter-spacing:.05em}
.cal-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;
         font-size:.78rem;cursor:pointer;border:1px solid transparent;transition:.15s;user-select:none}
.cal-day:hover:not(.past):not(.reserved):not(.other){border-color:var(--accent)}
.cal-day.available{background:var(--surface-2);color:var(--text-soft)}
.cal-day.blocked{background:rgba(239,68,68,.12);color:var(--danger)}
.cal-day.reserved{background:rgba(245,158,11,.12);color:var(--gold);cursor:not-allowed}
.cal-day.selected{background:var(--accent);color:#fff;border-color:var(--accent)}
.cal-day.past{opacity:.3;cursor:not-allowed}
.cal-day.other{opacity:.2;cursor:default}
/* ── Modal images ────────────────────────────────────────── */
.img-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem}
.img-row input{flex:1;font-size:.8rem}
.amenity-tag{display:inline-flex;align-items:center;gap:.3rem;background:var(--accent-dim);
             color:var(--accent);padding:.22rem .6rem;border-radius:20px;font-size:.72rem;
             font-weight:600;margin:.2rem;cursor:default}
.amenity-tag button{background:none;border:none;color:var(--danger);cursor:pointer;
                    padding:0;font-size:.8rem;line-height:1;margin-left:.15rem}
.quick-am{font-size:.7rem;padding:.2rem .55rem;border-radius:10px;border:1px solid var(--border);
          background:var(--surface-2);color:var(--text-soft);cursor:pointer;transition:.15s}
.quick-am:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-dim)}

/* ── Tableau réservations ─────────────────────────────────── */
.resv-table-wrap{overflow-x:auto;margin-top:.5rem}
.resv-table{width:100%;border-collapse:collapse;font-size:.82rem}
.resv-table th{
  background:var(--surface-2);color:var(--text-muted);
  font-weight:700;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;
  padding:.6rem .875rem;text-align:left;white-space:nowrap;
  border-bottom:1px solid var(--border);
}
.resv-table td{
  padding:.65rem .875rem;border-bottom:1px solid var(--border);
  color:var(--text-soft);vertical-align:middle;white-space:nowrap;
}
.resv-table tr:hover td{background:var(--surface-2)}
.resv-table tr:last-child td{border-bottom:none}
.badge-status{
  display:inline-block;padding:.2rem .6rem;border-radius:20px;
  font-size:.68rem;font-weight:700;letter-spacing:.03em;white-space:nowrap;
}
.resv-code{
  font-family:monospace;font-size:.78rem;font-weight:700;
  color:var(--accent);letter-spacing:.05em;
}
.section-header{
  display:flex;justify-content:space-between;align-items:center;
  margin:2rem 0 1rem;
}
.section-title{
  font-size:1rem;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:.5rem;
}

/* ── Filtres ─────────────────────────────────────────────── */
.filter-row{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
.filter-row select,.filter-row input{
  padding:.4rem .75rem;border:1px solid var(--border);border-radius:8px;
  background:var(--card-bg);color:var(--text);font-size:.82rem;outline:none;
}
.filter-row select:focus,.filter-row input:focus{border-color:var(--accent)}

/* ── Tabs ────────────────────────────────────────────────── */
.tab-bar{display:flex;gap:.25rem;border-bottom:2px solid var(--border);margin-bottom:1.5rem}
.tab-btn{
  padding:.6rem 1.25rem;border:none;background:none;
  color:var(--text-muted);font-size:.88rem;font-weight:600;
  cursor:pointer;border-bottom:2px solid transparent;
  margin-bottom:-2px;transition:.15s;
}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-btn:hover:not(.active){color:var(--text)}
.tab-panel{display:none}
.tab-panel.active{display:block}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" onclick="toggleMobileSidebar()"><i class="fas fa-bars"></i></button>
      <div>
        <div class="page-title">Hébergements</div>
        <div class="page-sub"><?php echo $totalAccos; ?> logement(s) actif(s)</div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-accent" onclick="openModal()">
        <i class="fas fa-plus"></i>Ajouter
      </button>
    </div>
  </header>

  <div class="page-body">

    <!-- ── Stats ─────────────────────────────────────────── -->
    <div class="stats-row stagger">
      <div class="stat-card c-blue">
        <div class="card-icon-wrap"><i class="fas fa-house"></i></div>
        <div class="card-value"><?php echo $totalAccos; ?></div>
        <div class="card-label">Logements actifs</div>
        <i class="fas fa-house card-bg"></i>
      </div>
      <div class="stat-card c-green">
        <div class="card-icon-wrap"><i class="fas fa-users"></i></div>
        <div class="card-value"><?php echo $totalCap; ?></div>
        <div class="card-label">Capacité totale</div>
        <i class="fas fa-users card-bg"></i>
      </div>
      <div class="stat-card c-warn">
        <div class="card-icon-wrap"><i class="fas fa-moon"></i></div>
        <div class="card-value"><?php echo $bookedNights; ?></div>
        <div class="card-label">Nuits réservées (à venir)</div>
        <i class="fas fa-moon card-bg"></i>
      </div>
      <div class="stat-card c-gold">
        <div class="card-icon-wrap"><i class="fas fa-coins"></i></div>
        <div class="card-value"><?php echo number_format($monthRevenue/1000,1); ?>K</div>
        <div class="card-label">Revenus ce mois (DA)</div>
        <i class="fas fa-coins card-bg"></i>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         ONGLETS : Logements / Réservations
    ═══════════════════════════════════════════════════════ -->
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab('logements',this)">
        <i class="fas fa-house" style="margin-right:.4rem"></i>Logements
      </button>
      <button class="tab-btn" onclick="switchTab('reservations',this)">
        <i class="fas fa-calendar-check" style="margin-right:.4rem"></i>
        Réservations hébergement
        <?php if(count($accoReservations)): ?>
        <span style="background:var(--accent);color:#fff;border-radius:20px;
                     padding:.05rem .45rem;font-size:.65rem;margin-left:.3rem">
          <?php echo count($accoReservations); ?>
        </span>
        <?php endif; ?>
      </button>
    </div>

    <!-- ── TAB : Logements ────────────────────────────────── -->
    <div id="tab-logements" class="tab-panel active">
      <div class="acco-grid">
        <?php foreach ($accos as $a):
          $images    = json_decode($a['images']    ?? '[]', true) ?: [];
          $amenities = json_decode($a['amenities'] ?? '[]', true) ?: [];
          $thumb     = $images[0] ?? '';
          $types = ['apartment'=>['Appartement','var(--accent)'],'villa'=>['Villa','var(--success)'],
                    'room'=>['Studio','var(--text-muted)'],'chalet'=>['Chalet','var(--gold)']];
          [$typeLabel,$typeColor] = $types[$a['type']] ?? ['Hébergement','var(--text-muted)'];
        ?>
        <div class="acco-card">
          <?php if($thumb): ?>
            <img class="acco-thumb" src="<?php echo htmlspecialchars($thumb); ?>"
                 alt="<?php echo htmlspecialchars($a['name']); ?>">
          <?php else: ?>
            <div class="acco-thumb-ph">
              <i class="fas fa-house" style="font-size:2.5rem;color:var(--text-muted)"></i>
            </div>
          <?php endif; ?>

          <div class="acco-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.3rem">
              <div class="acco-name"><?php echo htmlspecialchars($a['name']); ?></div>
              <span class="badge-type" style="background:<?php echo $typeColor; ?>1a;color:<?php echo $typeColor; ?>;white-space:nowrap">
                <?php echo $typeLabel; ?>
              </span>
            </div>
            <div class="acco-addr">
              <i class="fas fa-map-marker-alt" style="margin-right:.3rem"></i><?php echo htmlspecialchars($a['address'] ?: 'Béjaïa'); ?>
            </div>
            <div class="acco-row">
              <span style="color:var(--text-soft)">
                <i class="fas fa-users" style="color:var(--accent);margin-right:.3rem"></i><?php echo $a['capacity']; ?> pers.
              </span>
              <div class="acco-price">
                <?php echo number_format($a['price_per_night'],0,',',' '); ?> DA
                <span style="font-weight:400;font-size:.72rem;color:var(--text-muted)">/nuit</span>
              </div>
            </div>
            <?php if($amenities): ?>
            <div style="margin-bottom:.4rem;min-height:22px">
              <?php foreach(array_slice($amenities,0,3) as $am): ?>
              <span style="font-size:.63rem;padding:.15rem .45rem;border-radius:8px;background:var(--surface-2);
                           color:var(--text-soft);border:1px solid var(--border);margin:.1rem;display:inline-block">
                <?php echo htmlspecialchars($am); ?>
              </span>
              <?php endforeach; ?>
              <?php if(count($amenities)>3): ?>
              <span style="font-size:.63rem;color:var(--text-muted)">+<?php echo count($amenities)-3; ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if(!$a['is_active']): ?>
            <div style="font-size:.7rem;color:var(--danger);margin-bottom:.3rem">
              <i class="fas fa-eye-slash" style="margin-right:.3rem"></i>Inactif
            </div>
            <?php endif; ?>
            <div class="acco-actions">
              <button class="btn btn-ghost btn-sm" style="flex:1"
                onclick='openModal(<?php echo htmlspecialchars(json_encode($a),ENT_QUOTES); ?>)'>
                <i class="fas fa-edit"></i>Modifier
              </button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Gérer les disponibilités"
                onclick="openCalendar(<?php echo (int)$a['id']; ?>,'<?php echo htmlspecialchars(addslashes($a['name'])); ?>')">
                <i class="fas fa-calendar-alt"></i>
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($accos)): ?>
        <div style="grid-column:1/-1">
          <div class="empty-box">
            <div class="empty-ico"><i class="fas fa-house"></i></div>
            <h3>Aucun hébergement</h3>
            <p>Cliquez sur "Ajouter" pour créer votre premier logement</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div><!-- /tab-logements -->

    <!-- ── TAB : Réservations hébergement ─────────────────── -->
    <div id="tab-reservations" class="tab-panel">

      <!-- Filtres -->
      <div class="filter-row">
        <select id="filterStatus" onchange="filterReservations()">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="confirmed">Confirmé</option>
          <option value="completed">Terminé</option>
          <option value="cancelled">Annulé</option>
        </select>
        <select id="filterAcco" onchange="filterReservations()">
          <option value="">Tous les logements</option>
          <?php foreach($accos as $a): ?>
          <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" id="filterSearch" placeholder="Rechercher client, code…"
               oninput="filterReservations()" style="min-width:180px">
      </div>

      <?php if(empty($accoReservations)): ?>
      <div class="empty-box">
        <div class="empty-ico"><i class="fas fa-calendar-times"></i></div>
        <h3>Aucune réservation hébergement</h3>
        <p>Les réservations de logements apparaîtront ici</p>
      </div>
      <?php else: ?>
      <div class="resv-table-wrap">
        <table class="resv-table" id="resvTable">
          <thead>
            <tr>
              <th>Code</th>
              <th>Logement</th>
              <th>Client</th>
              <th>Téléphone</th>
              <th>Arrivée</th>
              <th>Départ</th>
              <th>Nuits</th>
              <th>Pers.</th>
              <th>Total</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="resvTbody">
            <?php foreach($accoReservations as $r):
              $sc = $statusColors[$r['status']] ?? ['#f3f4f6','#374151',$r['status']];
              $nights = $r['nights'] ?? (
                ($r['check_in_date'] && $r['check_out_date'])
                  ? (int)round((strtotime($r['check_out_date'])-strtotime($r['check_in_date']))/86400)
                  : '—'
              );
            ?>
            <tr data-status="<?php echo $r['status']; ?>"
                data-acco="<?php echo $r['accommodation_id']; ?>"
                data-search="<?php echo strtolower(htmlspecialchars($r['client_name'].' '.$r['confirmation_code'].' '.($r['acco_name']??''))); ?>">
              <td><span class="resv-code"><?php echo htmlspecialchars($r['confirmation_code']); ?></span></td>
              <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis">
                <?php echo htmlspecialchars($r['acco_name'] ?? '—'); ?>
              </td>
              <td>
                <div style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($r['client_name']); ?></div>
                <div style="font-size:.72rem;color:var(--text-muted)"><?php echo htmlspecialchars($r['client_email']); ?></div>
              </td>
              <td><?php echo htmlspecialchars($r['client_phone']); ?></td>
              <td><?php echo $r['check_in_date']  ? date('d/m/Y', strtotime($r['check_in_date']))  : '—'; ?></td>
              <td><?php echo $r['check_out_date'] ? date('d/m/Y', strtotime($r['check_out_date'])) : '—'; ?></td>
              <td style="text-align:center"><?php echo $nights; ?></td>
              <td style="text-align:center"><?php echo $r['participants']; ?></td>
              <td style="font-weight:700;color:var(--success)">
                <?php echo number_format($r['total_price'],0,',',' '); ?> DA
              </td>
              <td>
                <span class="badge-status" style="background:<?php echo $sc[0]; ?>;color:<?php echo $sc[1]; ?>">
                  <?php echo $sc[2]; ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:.3rem">
                  <!-- Changer statut -->
                  <select class="form-control" style="font-size:.72rem;padding:.25rem .4rem;min-width:110px"
                          onchange="changeStatus(<?php echo $r['id']; ?>,this.value,this)">
                    <?php foreach(['pending'=>'En attente','confirmed'=>'Confirmé','completed'=>'Terminé','cancelled'=>'Annulé'] as $sv=>$sl): ?>
                    <option value="<?php echo $sv; ?>" <?php echo $r['status']===$sv?'selected':''; ?>>
                      <?php echo $sl; ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <!-- Détail -->
                  <button class="btn btn-ghost btn-sm btn-icon" title="Voir détail"
                          onclick="showDetail(<?php echo $r['id']; ?>)">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div id="resvCount" style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">
        <?php echo count($accoReservations); ?> réservation(s)
      </div>
      <?php endif; ?>
    </div><!-- /tab-reservations -->

  </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /admin-layout -->

<!-- ══════════════════════════════════════════════════════════
     MODAL : AJOUTER / MODIFIER UN HÉBERGEMENT
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="accoModal">
  <div class="modal" style="max-width:700px">
    <div class="modal-head">
      <div class="modal-ttl" id="modalTtl">
        <i class="fas fa-plus" style="color:var(--accent);margin-right:.5rem"></i>Nouvel hébergement
      </div>
      <button class="modal-close" onclick="closeModal('accoModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="accoId">
      <div class="form-row grid-2">
        <div class="form-group span-2">
          <label class="form-label">Nom *</label>
          <input type="text" id="fName" class="form-control" placeholder="Ex: Appartement Vue Mer — Tichy">
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select id="fType" class="form-control">
            <option value="apartment">Appartement</option>
            <option value="villa">Villa</option>
            <option value="room">Studio / Chambre</option>
            <option value="chalet">Chalet</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Localisation</label>
          <input type="text" id="fAddress" class="form-control" placeholder="Tichy, Béjaïa">
        </div>
        <div class="form-group">
          <label class="form-label">Capacité (personnes) *</label>
          <input type="number" id="fCapacity" class="form-control" min="1" value="4">
        </div>
        <div class="form-group">
          <label class="form-label">Prix / nuit (DA) *</label>
          <input type="number" id="fPrice" class="form-control" min="0" step="500" value="8000">
        </div>
        <div class="form-group">
          <label class="form-label">Nuits minimum</label>
          <input type="number" id="fMinNights" class="form-control" min="1" value="1">
        </div>
        <div class="form-group">
          <label class="form-label">Ordre d'affichage <span style="font-size:.7rem;color:var(--text-muted)">(0 = premier)</span></label>
          <input type="number" id="fSort" class="form-control" value="0" min="0">
        </div>
        <div class="form-group span-2">
          <label class="form-label">Description</label>
          <textarea id="fDesc" class="form-control" rows="3" placeholder="Description détaillée..."></textarea>
        </div>
        <div class="form-group span-2">
          <label class="form-label">Photos <span style="font-size:.7rem;color:var(--text-muted);font-weight:400">— URLs des images</span></label>
          <div id="imgList"></div>
          <div style="display:flex;gap:.5rem;margin-top:.4rem">
            <input type="url" id="imgInput" class="form-control" placeholder="https://... coller l'URL">
            <button type="button" class="btn btn-ghost btn-sm" onclick="addImg()" style="white-space:nowrap">
              <i class="fas fa-plus"></i>Ajouter
            </button>
          </div>
        </div>
        <div class="form-group span-2">
          <label class="form-label">Équipements</label>
          <div id="amenityList" style="min-height:28px;margin-bottom:.5rem"></div>
          <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem">
            <?php foreach(['WiFi','Climatisation','Cuisine équipée','Parking','Piscine','Balcon',
                           'Vue mer','Jardin','BBQ','Machine à laver','TV satellite','Terrasse',
                           'Ascenseur','Chauffe-eau','Lit bébé'] as $am): ?>
            <button type="button" class="quick-am" onclick="addAmenity('<?php echo $am; ?>')">
              <?php echo $am; ?>
            </button>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:.5rem">
            <input type="text" id="amenityInput" class="form-control" placeholder="Autre équipement..."
                   onkeydown="if(event.key==='Enter'){event.preventDefault();addAmenityInput()}">
            <button type="button" class="btn btn-ghost btn-sm" onclick="addAmenityInput()">
              <i class="fas fa-plus"></i>
            </button>
          </div>
        </div>
        <div class="form-group span-2">
          <label class="form-label">Statut</label>
          <select id="fActive" class="form-control">
            <option value="1">Actif — visible aux clients</option>
            <option value="0">Inactif — masqué</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('accoModal')">Annuler</button>
      <button class="btn btn-danger" id="btnDelete" onclick="deleteAcco()" style="display:none;margin-right:auto">
        <i class="fas fa-trash"></i>Désactiver
      </button>
      <button class="btn btn-accent" onclick="saveAcco()">
        <i class="fas fa-check"></i>Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL : CALENDRIER DISPONIBILITÉS
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="calModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-head">
      <div class="modal-ttl">
        <i class="fas fa-calendar-alt" style="color:var(--accent);margin-right:.5rem"></i>
        Disponibilités — <span id="calName"></span>
      </div>
      <button class="modal-close" onclick="closeModal('calModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.875rem">
        <button class="btn btn-ghost btn-sm btn-icon" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
        <strong id="calMonthLabel" style="font-size:.95rem;color:var(--text)"></strong>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
      </div>
      <div class="cal-grid" style="margin-bottom:3px">
        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
        <div class="cal-dow"><?php echo $d; ?></div>
        <?php endforeach; ?>
      </div>
      <div class="cal-grid" id="calGrid"></div>
      <div style="display:flex;gap:.875rem;flex-wrap:wrap;margin-top:.875rem;font-size:.72rem;color:var(--text-soft)">
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--surface-2);border-radius:2px;margin-right:.3rem"></span>Disponible</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:rgba(239,68,68,.12);border-radius:2px;margin-right:.3rem"></span>Bloqué (admin)</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:rgba(245,158,11,.12);border-radius:2px;margin-right:.3rem"></span>Réservé (client)</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:var(--accent);border-radius:2px;margin-right:.3rem"></span>Sélectionné</span>
      </div>
      <div style="display:flex;gap:.5rem;margin-top:.875rem;flex-wrap:wrap">
        <button class="btn btn-danger btn-sm" onclick="blockSelected()"><i class="fas fa-lock"></i>Bloquer</button>
        <button class="btn btn-sm" style="background:var(--success);color:#fff" onclick="unblockSelected()"><i class="fas fa-unlock"></i>Débloquer</button>
        <input type="text" id="blockReason" class="form-control" placeholder="Raison (optionnel)" style="flex:1;min-width:100px;font-size:.8rem">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('calModal')">Fermer</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL : DÉTAIL RÉSERVATION
═══════════════════════════════════════════════════════════ -->
<div class="modal-wrap" id="detailModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-head">
      <div class="modal-ttl"><i class="fas fa-calendar-check" style="color:var(--accent);margin-right:.5rem"></i>Détail réservation</div>
      <button class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="detailBody" style="font-size:.88rem"></div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('detailModal')">Fermer</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->
<script>
// ── Helpers fetch avec credentials (FIX prod session) ────────
// CORRECTION PRINCIPALE : credentials:'include' envoie les cookies de session
// au serveur même en prod, ce qui évite l'erreur "non autorisé"
function apiFetch(url, options = {}) {
    return fetch(url, {
        credentials: 'include',   // ← CRITIQUE pour la session en prod
        ...options,
        headers: {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        }
    });
}

// ── Tabs ─────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Filtres réservations ─────────────────────────────────────
function filterReservations() {
    const status = document.getElementById('filterStatus')?.value || '';
    const acco   = document.getElementById('filterAcco')?.value   || '';
    const search = (document.getElementById('filterSearch')?.value || '').toLowerCase();
    let count = 0;
    document.querySelectorAll('#resvTbody tr').forEach(row => {
        const okStatus = !status || row.dataset.status === status;
        const okAcco   = !acco   || row.dataset.acco   === acco;
        const okSearch = !search || (row.dataset.search || '').includes(search);
        const show = okStatus && okAcco && okSearch;
        row.style.display = show ? '' : 'none';
        if (show) count++;
    });
    const el = document.getElementById('resvCount');
    if (el) el.textContent = count + ' réservation(s)';
}

// ── Changer statut réservation hébergement ────────────────────
// Même endpoint que reservations.php : update_reservation_status.php
// Paramètre : reservation_id (pas "id")
async function changeStatus(id, newStatus, selectEl) {
    const labels = { confirmed:'Confirmer', completed:'Marquer terminée', cancelled:'Annuler' };
    if (!confirm((labels[newStatus] || 'Changer') + ' cette réservation ?')) {
        selectEl.value = selectEl.dataset.prev || selectEl.value;
        return;
    }
    const prevValue = selectEl.dataset.prev || selectEl.value;
    try {
        const r = await apiFetch('../api/update_reservation_status.php', {
            method: 'POST',
            body: JSON.stringify({ reservation_id: id, status: newStatus })
        });
        const d = await r.json();
        if (!d.success) {
            alert('Erreur : ' + (d.error || 'Impossible de mettre à jour'));
            selectEl.value = prevValue;
        } else {
            selectEl.dataset.prev = newStatus;
            const row = selectEl.closest('tr');
            row.dataset.status = newStatus;
            const statusColors = {
                pending:   ['#fef3c7','#92400e','En attente'],
                confirmed: ['#d1fae5','#065f46','Confirmé'],
                completed: ['#dbeafe','#1e40af','Terminé'],
                cancelled: ['#fee2e2','#991b1b','Annulé'],
            };
            const sc    = statusColors[newStatus] || ['#f3f4f6','#374151', newStatus];
            const badge = row.querySelector('.badge-status');
            if (badge) {
                badge.style.background = sc[0];
                badge.style.color      = sc[1];
                badge.textContent      = sc[2];
            }
            // WhatsApp comme reservations.php
            if (d.whatsapp_url) {
                setTimeout(() => {
                    if (confirm('Notifier le client par WhatsApp ?')) {
                        window.open(d.whatsapp_url, '_blank');
                    }
                }, 300);
            }
        }
    } catch(e) {
        alert('Erreur de connexion au serveur');
        selectEl.value = prevValue;
    }
}

// ── Voir détail réservation ───────────────────────────────────
// Données PHP encodées en JSON pour lecture JS côté client
const allReservations = <?php echo json_encode(array_values($accoReservations)); ?>;

function showDetail(id) {
    const r = allReservations.find(x => x.id == id);
    if (!r) return;
    const statusLabels = {pending:'En attente',confirmed:'Confirmé',completed:'Terminé',cancelled:'Annulé'};
    document.getElementById('detailBody').innerHTML = `
        <div style="display:grid;gap:.5rem">
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Code</span>
                <strong style="font-family:monospace;color:var(--accent)">${r.confirmation_code}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Logement</span>
                <strong>${r.acco_name || '—'}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Client</span>
                <strong>${r.client_name}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Email</span>
                <span>${r.client_email}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Téléphone</span>
                <span>
                    ${r.client_phone}
                    <a href="https://wa.me/${r.client_phone.replace(/[^0-9]/g,'')}" target="_blank"
                       style="color:#25d366;margin-left:.4rem"><i class="fab fa-whatsapp"></i></a>
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Arrivée</span>
                <strong>${r.check_in_date ? new Date(r.check_in_date).toLocaleDateString('fr-FR') : '—'}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Départ</span>
                <strong>${r.check_out_date ? new Date(r.check_out_date).toLocaleDateString('fr-FR') : '—'}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Nuits</span>
                <strong>${r.nights || '—'}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Personnes</span>
                <strong>${r.participants}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text-muted)">Total</span>
                <strong style="color:var(--success);font-size:1.05rem">${Number(r.total_price).toLocaleString('fr-DZ')} DA</strong>
            </div>
            ${r.special_requests ? `
            <div style="padding:.5rem 0;border-bottom:1px solid var(--border)">
                <div style="color:var(--text-muted);margin-bottom:.25rem">Demandes spéciales</div>
                <div style="font-style:italic">${r.special_requests}</div>
            </div>` : ''}
            <div style="display:flex;justify-content:space-between;padding:.5rem 0">
                <span style="color:var(--text-muted)">Statut</span>
                <strong>${statusLabels[r.status] || r.status}</strong>
            </div>
        </div>
    `;
    document.getElementById('detailModal').classList.add('show');
}

// ── État global ──────────────────────────────────────────────
let imgs = [], amenities = [];
let calId = null;
let calYear = new Date().getFullYear(), calMonth = new Date().getMonth();
let calBlocked = [], calReserved = [], calSelected = new Set();

// ── Fermeture modals ─────────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-wrap').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); })
);

// ════════════════════════════════════════════════════════════
// MODAL HÉBERGEMENT
// ════════════════════════════════════════════════════════════
function openModal(a) {
    imgs = []; amenities = [];
    const editing = !!a;
    document.getElementById('modalTtl').innerHTML =
        `<i class="fas fa-${editing ? 'edit' : 'plus'}" style="color:var(--accent);margin-right:.5rem"></i>` +
        (editing ? 'Modifier : ' + a.name : 'Nouvel hébergement');

    document.getElementById('accoId').value      = a?.id            ?? '';
    document.getElementById('fName').value       = a?.name          ?? '';
    document.getElementById('fType').value       = a?.type          ?? 'apartment';
    document.getElementById('fAddress').value    = a?.address       ?? '';
    document.getElementById('fCapacity').value   = a?.capacity      ?? 4;
    document.getElementById('fPrice').value      = a?.price_per_night ?? 8000;
    document.getElementById('fMinNights').value  = a?.min_nights    ?? 1;
    document.getElementById('fSort').value       = a?.sort_order    ?? 0;
    document.getElementById('fDesc').value       = a?.description   ?? '';
    document.getElementById('fActive').value     = a?.is_active != null ? a.is_active : 1;
    document.getElementById('btnDelete').style.display = editing ? 'inline-flex' : 'none';

    try { imgs      = JSON.parse(a?.images    ?? '[]') || []; } catch(e) { imgs = []; }
    try { amenities = JSON.parse(a?.amenities ?? '[]') || []; } catch(e) { amenities = []; }

    renderImgs();
    renderAmenities();
    document.getElementById('accoModal').classList.add('show');
}

// ── Photos ───────────────────────────────────────────────────
function renderImgs() {
    const list = document.getElementById('imgList');
    if (!imgs.length) {
        list.innerHTML = '<p style="font-size:.78rem;color:var(--text-muted);margin:.25rem 0">Aucune photo ajoutée</p>';
        return;
    }
    list.innerHTML = imgs.map((url, i) => `
        <div class="img-row">
            <input type="url" class="form-control" value="${url.replace(/"/g,'&quot;')}"
                   oninput="imgs[${i}]=this.value" placeholder="https://...">
            <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeImg(${i})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

function addImg() {
    const v = document.getElementById('imgInput').value.trim();
    if (!v) return;
    imgs.push(v);
    document.getElementById('imgInput').value = '';
    renderImgs();
}
function removeImg(i) { imgs.splice(i, 1); renderImgs(); }

// ── Équipements ──────────────────────────────────────────────
function renderAmenities() {
    document.getElementById('amenityList').innerHTML = amenities.map((a, i) => `
        <span class="amenity-tag">${a}<button onclick="removeAm(${i})" title="Retirer">×</button></span>
    `).join('') || '<span style="font-size:.75rem;color:var(--text-muted)">Aucun équipement</span>';
}
function addAmenity(v) {
    if (amenities.includes(v)) return;
    amenities.push(v);
    renderAmenities();
}
function addAmenityInput() {
    const v = document.getElementById('amenityInput').value.trim();
    if (!v) return;
    addAmenity(v);
    document.getElementById('amenityInput').value = '';
}
function removeAm(i) { amenities.splice(i, 1); renderAmenities(); }

// ── Sauvegarder ──────────────────────────────────────────────
async function saveAcco() {
    const id   = document.getElementById('accoId').value;
    const name = document.getElementById('fName').value.trim();
    if (!name) { alert('Le nom est requis'); return; }

    document.querySelectorAll('#imgList input[type="url"]').forEach((inp, i) => {
        imgs[i] = inp.value.trim();
    });
    const cleanImgs = imgs.filter(u => u);

    const payload = {
        id:              id ? parseInt(id) : undefined,
        name,
        type:            document.getElementById('fType').value,
        description:     document.getElementById('fDesc').value,
        capacity:        parseInt(document.getElementById('fCapacity').value),
        price_per_night: parseFloat(document.getElementById('fPrice').value),
        images:          cleanImgs,
        amenities,
        address:         document.getElementById('fAddress').value,
        min_nights:      parseInt(document.getElementById('fMinNights').value),
        sort_order:      parseInt(document.getElementById('fSort').value),
        is_active:       parseInt(document.getElementById('fActive').value),
    };

    const r = await apiFetch('../api/accommodations/save.php', {
        method: 'POST',
        body:   JSON.stringify(payload)
    });
    const d = await r.json();
    if (d.success) { closeModal('accoModal'); location.reload(); }
    else alert('Erreur : ' + d.error);
}

async function deleteAcco() {
    if (!confirm('Désactiver cet hébergement ? Il ne sera plus visible aux clients.')) return;
    const id = parseInt(document.getElementById('accoId').value);
    const r  = await apiFetch('../api/accommodations/delete.php', {
        method: 'POST',
        body:   JSON.stringify({id})
    });
    const d = await r.json();
    if (d.success) { closeModal('accoModal'); location.reload(); }
    else alert('Erreur : ' + d.error);
}

// ════════════════════════════════════════════════════════════
// MODAL CALENDRIER
// ════════════════════════════════════════════════════════════
function openCalendar(id, name) {
    calId = id;
    calSelected.clear();
    document.getElementById('calName').textContent = name;
    document.getElementById('calModal').classList.add('show');
    loadCal();
}

async function loadCal() {
    const m = `${calYear}-${String(calMonth + 1).padStart(2, '0')}`;
    document.getElementById('calMonthLabel').textContent =
        new Date(calYear, calMonth, 1).toLocaleDateString('fr-FR', {month:'long', year:'numeric'});

    const r    = await apiFetch(`../api/accommodations/blocked_dates.php?accommodation_id=${calId}&month=${m}`);
    const data = await r.json();
    const rows = data.blocked_dates || [];
    calBlocked  = rows.filter(x => !x.reservation_id).map(x => x.blocked_date);
    calReserved = rows.filter(x =>  x.reservation_id).map(x => x.blocked_date);
    renderCal();
}

function renderCal() {
    const firstDay = new Date(calYear, calMonth, 1);
    const lastDay  = new Date(calYear, calMonth + 1, 0);
    const startDow = (firstDay.getDay() + 6) % 7;
    const today    = new Date().toISOString().slice(0, 10);
    let html = '';

    for (let i = 0; i < startDow; i++) html += '<div class="cal-day other"></div>';
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const ds   = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const past = ds < today;
        let cls = 'available';
        if (calReserved.includes(ds))     cls = 'reserved';
        else if (calBlocked.includes(ds)) cls = 'blocked';
        if (calSelected.has(ds))          cls = 'selected';
        html += `<div class="cal-day ${cls}${past?' past':''}" onclick="toggleDay('${ds}','${cls}',${past})">${d}</div>`;
    }
    document.getElementById('calGrid').innerHTML = html;
}

function toggleDay(date, cls, past) {
    if (past || cls === 'reserved') return;
    if (calSelected.has(date)) calSelected.delete(date);
    else                        calSelected.add(date);
    renderCal();
}

function changeMonth(dir) {
    calMonth += dir;
    if (calMonth < 0)  { calMonth = 11; calYear--; }
    if (calMonth > 11) { calMonth = 0;  calYear++; }
    calSelected.clear();
    loadCal();
}

async function blockSelected() {
    if (!calSelected.size) { alert('Sélectionnez des dates d\'abord'); return; }
    const reason = document.getElementById('blockReason').value.trim() || 'Bloqué par admin';
    const r = await apiFetch('../api/accommodations/blocked_dates.php', {
        method: 'POST',
        body:   JSON.stringify({action:'block', accommodation_id:calId, dates:[...calSelected], reason})
    });
    const d = await r.json();
    if (d.success) { calSelected.clear(); loadCal(); }
    else alert('Erreur : ' + d.error);
}

async function unblockSelected() {
    if (!calSelected.size) { alert('Sélectionnez des dates d\'abord'); return; }
    const r = await apiFetch('../api/accommodations/blocked_dates.php', {
        method: 'POST',
        body:   JSON.stringify({action:'unblock', accommodation_id:calId, dates:[...calSelected]})
    });
    const d = await r.json();
    if (d.success) { calSelected.clear(); loadCal(); }
    else alert('Erreur : ' + d.error);
}
</script>
</body>
</html>