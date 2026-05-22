<?php
/**
 * api/accommodations/delete.php
 * Supprimer (soft delete) un hébergement
 * POST: { id: X }
 */

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
require_once '../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$id    = isset($input['id']) ? (int)$input['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID requis']); exit;
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    // Vérifier qu'il n'y a pas de réservations futures
    $check = $db->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE accommodation_id = :id
          AND check_in_date >= CURDATE()
          AND status NOT IN ('cancelled')
    ");
    $check->execute([':id' => $id]);
    $futureCount = (int)$check->fetchColumn();

    if ($futureCount > 0) {
        echo json_encode([
            'success' => false,
            'error'   => "$futureCount réservation(s) future(s) existent pour cet hébergement. Annulez-les d'abord."
        ]);
        exit;
    }

    // Soft delete
    $db->prepare("UPDATE accommodations SET is_active = 0 WHERE id = :id")->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Hébergement désactivé']);
} catch (Exception $e) {
    error_log('accommodations/delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
