<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Méthode non autorisée']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
foreach (['activity_id','start_date','end_date','time_slots','max_participants'] as $f) {
    if (empty($input[$f])) { echo json_encode(['success'=>false,'error'=>"Le champ $f est requis"]); exit; }
}
if (!is_array($input['time_slots']) || empty($input['time_slots'])) {
    echo json_encode(['success'=>false,'error'=>'Sélectionnez au moins un créneau']); exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $activityId = (int)$input['activity_id'];
    $startDate  = new DateTime($input['start_date']);
    $endDate    = new DateTime($input['end_date']);
    $maxP       = (int)$input['max_participants'];

    if ($endDate < $startDate) { echo json_encode(['success'=>false,'error'=>'Date de fin invalide']); exit; }

    $insert = $db->prepare("INSERT INTO availability (activity_id,date,time_slot_id,max_participants,current_reservations,is_available) VALUES (:a,:d,:t,:m,0,TRUE)");
    $check  = $db->prepare("SELECT id FROM availability WHERE activity_id=:a AND date=:d AND time_slot_id=:t");

    $created = 0; $skipped = 0;
    $current = clone $startDate;
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        foreach ($input['time_slots'] as $slotId) {
            $check->execute([':a'=>$activityId,':d'=>$dateStr,':t'=>(int)$slotId]);
            if ($check->fetch()) { $skipped++; continue; }
            $insert->execute([':a'=>$activityId,':d'=>$dateStr,':t'=>(int)$slotId,':m'=>$maxP]);
            $created++;
        }
        $current->modify('+1 day');
    }

    $msg = "$created créneau(x) créé(s)";
    if ($skipped > 0) $msg .= " ($skipped ignoré(s) car déjà existant(s))";
    echo json_encode(['success'=>true,'message'=>$msg,'created'=>$created,'skipped'=>$skipped]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur: '.$e->getMessage()]);
}