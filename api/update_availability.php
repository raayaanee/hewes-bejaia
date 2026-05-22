<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Méthode non autorisée']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['availability_id'])) { echo json_encode(['success'=>false,'error'=>'ID requis']); exit; }

try {
    $database = new Database();
    $db = $database->getConnection();

    $check = $db->prepare("SELECT current_reservations FROM availability WHERE id=:id");
    $check->execute([':id'=>(int)$input['availability_id']]);
    $current = $check->fetch();

    if (!$current) { echo json_encode(['success'=>false,'error'=>'Créneau non trouvé']); exit; }

    $newMax = (int)$input['max_participants'];
    if ($newMax < $current['current_reservations']) {
        echo json_encode(['success'=>false,'error'=>"Impossible: {$current['current_reservations']} réservation(s) déjà effectuée(s)"]); exit;
    }

    $db->prepare("UPDATE availability SET activity_id=:a, date=:d, time_slot_id=:t, max_participants=:m, price_override=:p, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
       ->execute([':a'=>(int)$input['activity_id'],':d'=>$input['date'],':t'=>(int)$input['time_slot_id'],':m'=>$newMax,':p'=>!empty($input['price_override'])?floatval($input['price_override']):null,':id'=>(int)$input['availability_id']]);

    echo json_encode(['success'=>true,'message'=>'Créneau modifié avec succès']);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur lors de la modification']);
}