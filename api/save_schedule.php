<?php
/**
 * api/save_schedule.php
 * Créer, modifier ou supprimer un planning d'activité (admin)
 */
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
require_once '../config/database.php';

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'save';

try {
    $database = new Database();
    $db       = $database->getConnection();

    if ($action === 'delete') {
        $db->prepare("DELETE FROM activity_schedule WHERE id = :id")->execute([':id' => (int)$input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $data = [
        ':activity_id'  => (int)$input['activity_id'],
        ':day_of_week'  => (int)($input['day_of_week'] ?? 0),
        ':open_time'    => $input['open_time']   ?? '08:00:00',
        ':close_time'   => $input['close_time']  ?? '18:00:00',
        ':max_capacity' => (int)($input['max_capacity'] ?? 10),
        ':is_active'    => (int)($input['is_active'] ?? 1),
    ];

    if ($id > 0) {
        $data[':id'] = $id;
        $db->prepare("UPDATE activity_schedule SET activity_id=:activity_id, day_of_week=:day_of_week,
            open_time=:open_time, close_time=:close_time, max_capacity=:max_capacity,
            is_active=:is_active WHERE id=:id")->execute($data);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        $db->prepare("INSERT INTO activity_schedule (activity_id,day_of_week,open_time,close_time,max_capacity,is_active)
            VALUES (:activity_id,:day_of_week,:open_time,:close_time,:max_capacity,:is_active)")->execute($data);
        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }
} catch (Exception $e) {
    error_log('save_schedule: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
