<?php
/**
 * api/accommodations/list.php
 * Retourne la liste des hébergements actifs avec leurs photos et disponibilités
 * 
 * GET params (optionnels):
 *   check_in    → YYYY-MM-DD
 *   check_out   → YYYY-MM-DD
 *   capacity    → nombre de personnes minimum
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $database = new Database();
    $db       = $database->getConnection();

    $checkIn   = $_GET['check_in']  ?? null;
    $checkOut  = $_GET['check_out'] ?? null;
    $capacity  = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 1;

    // ── Récupérer tous les hébergements actifs ──────────────
    $sql = "SELECT * FROM accommodations WHERE is_active = 1";
    $params = [];

    if ($capacity > 1) {
        $sql .= " AND capacity >= :cap";
        $params[':cap'] = $capacity;
    }

    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $accommodations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Si dates fournies : vérifier la disponibilité ───────
    $blockedPerAcc = [];

    if ($checkIn && $checkOut && $checkIn < $checkOut) {
        $nights = (int)round((strtotime($checkOut) - strtotime($checkIn)) / 86400);

        // Récupérer les dates bloquées pour les hébergements dans la période
        $blkStmt = $db->prepare("
            SELECT accommodation_id, blocked_date
            FROM accommodation_blocked_dates
            WHERE blocked_date >= :cin AND blocked_date < :cout
        ");
        $blkStmt->execute([':cin' => $checkIn, ':cout' => $checkOut]);
        foreach ($blkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $blockedPerAcc[$row['accommodation_id']][] = $row['blocked_date'];
        }
    }

    // ── Construire la réponse ────────────────────────────────
    $result = [];
    foreach ($accommodations as $acc) {
        $id = (int)$acc['id'];

        // Décoder les JSONs
        $images    = json_decode($acc['images']    ?? '[]', true) ?: [];
        $amenities = json_decode($acc['amenities'] ?? '[]', true) ?: [];

        $isAvailable = true;
        $blockedDates = [];

        if ($checkIn && $checkOut) {
            $blockedDates = $blockedPerAcc[$id] ?? [];
            $isAvailable  = empty($blockedDates);
        }

        $result[] = [
            'id'              => $id,
            'name'            => $acc['name'],
            'type'            => $acc['type'],
            'type_label'      => typeLabel($acc['type']),
            'description'     => $acc['description'],
            'capacity'        => (int)$acc['capacity'],
            'price_per_night' => (float)$acc['price_per_night'],
            'images'          => $images,
            'thumbnail'       => $images[0] ?? null,
            'amenities'       => $amenities,
            'address'         => $acc['address'],
            'min_nights'      => (int)$acc['min_nights'],
            'is_available'    => $isAvailable,
            'blocked_dates'   => $blockedDates
        ];
    }

    echo json_encode([
        'success'        => true,
        'accommodations' => $result,
        'total'          => count($result),
        'filters'        => [
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'capacity'  => $capacity
        ]
    ]);

} catch (Exception $e) {
    error_log('accommodations/list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}

function typeLabel(string $type): string {
    return match($type) {
        'apartment' => 'Appartement',
        'villa'     => 'Villa',
        'room'      => 'Chambre / Studio',
        'chalet'    => 'Chalet',
        default     => 'Hébergement'
    };
}
?>
