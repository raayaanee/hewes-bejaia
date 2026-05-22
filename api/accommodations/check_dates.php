<?php
/**
 * api/accommodations/check_dates.php
 * Vérifie si un hébergement est disponible pour une période donnée
 * 
 * GET: accommodation_id, check_in, check_out
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

$accoId   = isset($_GET['accommodation_id']) ? (int)$_GET['accommodation_id'] : 0;
$checkIn  = $_GET['check_in']  ?? '';
$checkOut = $_GET['check_out'] ?? '';

if (!$accoId || !$checkIn || !$checkOut) {
    echo json_encode(['success' => false, 'error' => 'Paramètres requis: accommodation_id, check_in, check_out']);
    exit;
}

if ($checkIn >= $checkOut) {
    echo json_encode(['success' => false, 'error' => 'check_out doit être après check_in']);
    exit;
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    $accStmt = $db->prepare("SELECT * FROM accommodations WHERE id = :id AND is_active = 1");
    $accStmt->execute([':id' => $accoId]);
    $acco = $accStmt->fetch(PDO::FETCH_ASSOC);

    if (!$acco) {
        echo json_encode(['success' => false, 'error' => 'Hébergement introuvable']);
        exit;
    }

    $nights = (int)round((strtotime($checkOut) - strtotime($checkIn)) / 86400);

    // Construire la liste des dates de séjour
    $cur = strtotime($checkIn);
    $end = strtotime($checkOut);
    $datesToCheck = [];
    while ($cur < $end) {
        $datesToCheck[] = date('Y-m-d', $cur);
        $cur += 86400;
    }

    // Vérifier les dates bloquées
    $placeholders = implode(',', array_fill(0, count($datesToCheck), '?'));
    $blkStmt = $db->prepare("
        SELECT blocked_date FROM accommodation_blocked_dates
        WHERE accommodation_id = ? AND blocked_date IN ($placeholders)
    ");
    $blkStmt->execute(array_merge([$accoId], $datesToCheck));
    $blockedRows = $blkStmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer les mois concernés pour afficher le calendrier de disponibilité
    $firstMonth = date('Y-m-01', strtotime($checkIn));
    $lastMonth  = date('Y-m-01', strtotime($checkOut));
    $calBlkStmt = $db->prepare("
        SELECT blocked_date FROM accommodation_blocked_dates
        WHERE accommodation_id = :id
          AND blocked_date >= :start
          AND blocked_date <= :end
    ");
    $threeMonthsBefore = date('Y-m-d', strtotime('-1 month', strtotime($firstMonth)));
    $threeMonthsAfter  = date('Y-m-d', strtotime('+3 months', strtotime($lastMonth)));
    $calBlkStmt->execute([':id' => $accoId, ':start' => $threeMonthsBefore, ':end' => $threeMonthsAfter]);
    $allBlocked = $calBlkStmt->fetchAll(PDO::FETCH_COLUMN);

    $isAvailable  = empty($blockedRows);
    $totalPrice   = $nights * (float)$acco['price_per_night'];
    $meetsMinNights = $nights >= (int)$acco['min_nights'];

    echo json_encode([
        'success'         => true,
        'accommodation'   => [
            'id'              => (int)$acco['id'],
            'name'            => $acco['name'],
            'type'            => $acco['type'],
            'capacity'        => (int)$acco['capacity'],
            'price_per_night' => (float)$acco['price_per_night'],
            'min_nights'      => (int)$acco['min_nights'],
            'images'          => json_decode($acco['images'] ?? '[]', true) ?: [],
            'amenities'       => json_decode($acco['amenities'] ?? '[]', true) ?: [],
        ],
        'check_in'        => $checkIn,
        'check_out'       => $checkOut,
        'nights'          => $nights,
        'total_price'     => $totalPrice,
        'is_available'    => $isAvailable && $meetsMinNights,
        'meets_min_nights'=> $meetsMinNights,
        'blocked_in_range'=> $blockedRows,
        'all_blocked_dates'=> $allBlocked,  // Pour le calendrier frontend
        'message'         => !$meetsMinNights
            ? "Séjour minimum : {$acco['min_nights']} nuit(s)"
            : ($isAvailable ? 'Disponible' : 'Indisponible pour ces dates')
    ]);

} catch (Exception $e) {
    error_log('check_dates error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
