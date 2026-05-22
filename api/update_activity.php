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
if (!isset($input['activity_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID de l\'activité requis']);
    exit;
}

$booking_type = $input['booking_type'] ?? 'shared';
if (!in_array($booking_type, ['private', 'shared'])) {
    echo json_encode(['success' => false, 'error' => 'Type de réservation invalide']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        UPDATE activities SET
            name = :name,
            description = :description,
            type_id = :type_id,
            price = :price,
            duration_minutes = :duration_minutes,
            max_participants = :max_participants,
            image_url = :image_url,
            requirements = :requirements,
            is_active = :is_active,
            booking_type = :booking_type,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'               => (int)$input['activity_id'],
        ':name'             => sanitize($input['name']),
        ':description'      => sanitize($input['description']),
        ':type_id'          => (int)$input['type_id'],
        ':price'            => floatval($input['price'] ?? 0),
        ':duration_minutes' => (int)$input['duration_minutes'],
        ':max_participants' => (int)$input['max_participants'],
        ':image_url'        => $input['image_url'] ?? null,
        ':requirements'     => $input['requirements'] ?? null,
        ':is_active'        => (int)($input['is_active'] ?? 1),
        ':booking_type'     => $booking_type
    ]);

    echo json_encode(['success' => true, 'message' => 'Activité modifiée avec succès']);

} catch (PDOException $e) {
    error_log("Erreur modification activité: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la modification']);
}