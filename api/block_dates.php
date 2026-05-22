<?php
/**
 * api/block_dates.php
 * Bloquer / débloquer des dates pour une activité
 * POST { activity_id (nullable), dates: [...], reason }
 * POST { action:'delete', id }
 */
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }
require_once '../config/database.php';
$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'block';
try {
    $db = (new Database())->getConnection();
    if ($action === 'delete') {
        $db->prepare("DELETE FROM activity_blocked_dates WHERE id=:id")->execute([':id'=>(int)$input['id']]);
        echo json_encode(['success'=>true]); exit;
    }
    $actId  = !empty($input['activity_id']) ? (int)$input['activity_id'] : null;
    $dates  = $input['dates'] ?? [];
    $reason = $input['reason'] ?? 'Bloqué par admin';
    $ins    = $db->prepare("INSERT IGNORE INTO activity_blocked_dates (activity_id, blocked_date, reason) VALUES (:a,:d,:r)");
    $count  = 0;
    foreach ($dates as $d) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) {
            $ins->execute([':a'=>$actId,':d'=>$d,':r'=>$reason]); $count++;
        }
    }
    echo json_encode(['success'=>true,'blocked_count'=>$count]);
} catch (Exception $e) { echo json_encode(['success'=>false,'error'=>'Erreur serveur']); }
?>
