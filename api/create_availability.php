<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Méthode non autorisée']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
foreach (['activity_id','date','time_slot_id','max_participants'] as $f) {
    if (empty($input[$f])) { echo json_encode(['success'=>false,'error'=>"Le champ $f est requis"]); exit; }
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $check = $db->prepare("SELECT id FROM availability WHERE activity_id=:a AND date=:d AND time_slot_id=:t");
    $check->execute([':a'=>(int)$input['activity_id'],':d'=>$input['date'],':t'=>(int)$input['time_slot_id']]);
    if ($check->fetch()) { echo json_encode(['success'=>false,'error'=>'Ce créneau existe déjà']); exit; }

    $db->prepare("INSERT INTO availability (activity_id,date,time_slot_id,max_participants,current_reservations,price_override,is_available) VALUES (:a,:d,:t,:m,0,:p,TRUE)")
       ->execute([':a'=>(int)$input['activity_id'],':d'=>$input['date'],':t'=>(int)$input['time_slot_id'],':m'=>(int)$input['max_participants'],':p'=>!empty($input['price_override'])?floatval($input['price_override']):null]);

    echo json_encode(['success'=>true,'message'=>'Créneau créé avec succès','id'=>$db->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur lors de la création']);
}