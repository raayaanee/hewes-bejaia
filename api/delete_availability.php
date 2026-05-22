<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Méthode non autorisée']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['id'])) { echo json_encode(['success'=>false,'error'=>'ID requis']); exit; }

try {
    $database = new Database();
    $db = $database->getConnection();

    $check = $db->prepare("SELECT current_reservations FROM availability WHERE id=:id");
    $check->execute([':id'=>(int)$input['id']]);
    $result = $check->fetch();

    if (!$result) { echo json_encode(['success'=>false,'error'=>'Créneau non trouvé']); exit; }
    if ($result['current_reservations'] > 0) {
        echo json_encode(['success'=>false,'error'=>"Impossible: {$result['current_reservations']} réservation(s) en cours"]); exit;
    }

    $db->prepare("DELETE FROM availability WHERE id=:id")->execute([':id'=>(int)$input['id']]);
    echo json_encode(['success'=>true,'message'=>'Créneau supprimé avec succès']);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>'Erreur lors de la suppression']);
}