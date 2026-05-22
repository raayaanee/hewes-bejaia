<?php
/**
 * api/accommodations/save.php
 * Créer ou modifier un hébergement (admin seulement)
 * POST: id(optionnel), name, type, description, capacity, price_per_night,
 *       images(JSON), amenities(JSON), address, min_nights, is_active
 */

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']); exit;
}
require_once '../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$required = ['name', 'type', 'capacity', 'price_per_night'];
foreach ($required as $f) {
    if (empty($input[$f])) {
        echo json_encode(['success' => false, 'error' => "Champ requis: $f"]); exit;
    }
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    $id          = isset($input['id']) ? (int)$input['id'] : 0;
    $images      = is_array($input['images'] ?? null)
                     ? json_encode($input['images'])
                     : ($input['images'] ?? '[]');
    $amenities   = is_array($input['amenities'] ?? null)
                     ? json_encode($input['amenities'])
                     : ($input['amenities'] ?? '[]');

    $data = [
        ':name'     => trim($input['name']),
        ':type'     => $input['type'],
        ':desc'     => $input['description'] ?? '',
        ':cap'      => (int)$input['capacity'],
        ':price'    => (float)$input['price_per_night'],
        ':images'   => $images,
        ':amenities'=> $amenities,
        ':address'  => $input['address'] ?? '',
        ':min'      => (int)($input['min_nights'] ?? 1),
        ':sort'     => (int)($input['sort_order'] ?? 0),
        ':active'   => isset($input['is_active']) ? (int)$input['is_active'] : 1,
    ];

    if ($id > 0) {
        // UPDATE
        $data[':id'] = $id;
        $db->prepare("
            UPDATE accommodations SET
              name = :name, type = :type, description = :desc,
              capacity = :cap, price_per_night = :price,
              images = :images, amenities = :amenities,
              address = :address, min_nights = :min,
              sort_order = :sort, is_active = :active
            WHERE id = :id
        ")->execute($data);
        echo json_encode(['success' => true, 'id' => $id, 'action' => 'updated']);
    } else {
        // INSERT
        $db->prepare("
            INSERT INTO accommodations
              (name, type, description, capacity, price_per_night,
               images, amenities, address, min_nights, sort_order, is_active)
            VALUES
              (:name, :type, :desc, :cap, :price,
               :images, :amenities, :address, :min, :sort, :active)
        ")->execute($data);
        $newId = $db->lastInsertId();
        echo json_encode(['success' => true, 'id' => (int)$newId, 'action' => 'created']);
    }

} catch (Exception $e) {
    error_log('accommodations/save error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
