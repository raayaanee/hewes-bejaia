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
if (!isset($input['id']) || !isset($input['is_active'])) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("UPDATE activities SET is_active = :is_active WHERE id = :id");
    $stmt->execute([
        ':id' => (int)$input['id'],
        ':is_active' => $input['is_active'] ? 1 : 0
    ]);

    echo json_encode(['success' => true, 'message' => 'Statut modifié avec succès']);

} catch (PDOException $e) {
    error_log("Erreur toggle activité: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la modification']);
}