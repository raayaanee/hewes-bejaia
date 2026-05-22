<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_GET['activity_id']) || !isset($_GET['date'])) {
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
    exit;
}

$activityId = (int)$_GET['activity_id'];
$date = $_GET['date'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "
        SELECT 
            av.id,
            av.activity_id,
            av.date,
            av.time_slot_id,
            av.max_participants,
            av.current_reservations,
            av.price_override,
            av.is_available,
            ts.name as slot_name,
            ts.start_time,
            ts.end_time,
            a.price as activity_price,
            a.booking_type,
            COALESCE(av.price_override, a.price) as final_price
        FROM availability av
        JOIN time_slots ts ON av.time_slot_id = ts.id
        JOIN activities a ON av.activity_id = a.id
        WHERE av.activity_id = :activity_id
        AND av.date = :date
        AND av.is_available = TRUE
        AND ts.is_active = TRUE
        ORDER BY ts.start_time
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':activity_id' => $activityId,
        ':date' => $date
    ]);
    
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'count' => count($slots)
    ]);
    
} catch (Exception $e) {
    error_log("Erreur get_availability_for_date: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>