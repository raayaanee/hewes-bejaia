<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID requis']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Vérifier s'il y a des réservations associées
    $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE activity_id = :id");
    $checkStmt->execute([':id' => (int)$input['id']]);
    $result = $checkStmt->fetch();

    if ($result['count'] > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Impossible de supprimer : des réservations existent pour cette activité'
        ]);
        exit;
    }

    $deleteStmt = $db->prepare("DELETE FROM activities WHERE id = :id");
    $deleteStmt->execute([':id' => (int)$input['id']]);

    echo json_encode(['success' => true, 'message' => 'Activité supprimée avec succès']);

} catch (PDOException $e) {
    error_log("Erreur suppression activité: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
}