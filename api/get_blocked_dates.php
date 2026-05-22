<?php
/**
 * api/get_blocked_dates.php
 * Liste les dates bloquées récentes (admin)
 */
header('Content-Type: application/json');
require_once '../config/database.php';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
try {
    $db   = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT bd.*, a.name as activity_name
        FROM activity_blocked_dates bd
        LEFT JOIN activities a ON bd.activity_id = a.id
        WHERE bd.blocked_date >= CURDATE()
        ORDER BY bd.blocked_date ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['success'=>true,'dates'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) { echo json_encode(['success'=>false,'error'=>'Erreur serveur']); }
?>
