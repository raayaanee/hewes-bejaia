<?php

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    
    $query = "
        SELECT 
            r.id,
            r.availability_id,
            r.participants,
            r.confirmation_code,
            r.client_name,
            r.reservation_date,
            ts.end_time,
            a.booking_type
        FROM reservations r
        JOIN time_slots ts ON r.time_slot_id = ts.id
        JOIN availability av ON r.availability_id = av.id
        JOIN activities a ON av.activity_id = a.id
        WHERE r.status = 'pending'
        AND CONCAT(r.reservation_date, ' ', ts.end_time) < NOW()
    ";
    
    $stmt = $db->query($query);
    $expired_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cancelled_count = 0;
    
    foreach ($expired_reservations as $reservation) {
       
        $db->beginTransaction();
        
        try {
            // 1. Annuler la réservation
            $cancel_query = "UPDATE reservations SET status = 'cancelled' WHERE id = :id";
            $cancel_stmt = $db->prepare($cancel_query);
            $cancel_stmt->execute([':id' => $reservation['id']]);
            
            // 2. Libérer les places
            $unitsToFree = 1; // Par défaut 1 pour privatif
            if ($reservation['booking_type'] === 'shared') {
                $unitsToFree = $reservation['participants']; // N places pour partagé
            }
            
            $free_query = "
                UPDATE availability 
                SET current_reservations = current_reservations - :units 
                WHERE id = :availability_id AND current_reservations >= :units
            ";
            $free_stmt = $db->prepare($free_query);
            $free_stmt->execute([
                ':units' => $unitsToFree,
                ':availability_id' => $reservation['availability_id']
            ]);
            
            $db->commit();
            $cancelled_count++;
            
            error_log("🔄 Auto-annulation: {$reservation['confirmation_code']} - {$unitsToFree} place(s) libérée(s)");
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("❌ Erreur auto-annulation {$reservation['confirmation_code']}: " . $e->getMessage());
        }
    }
    
    if ($cancelled_count > 0) {
        error_log("✅ Auto-annulation terminée: {$cancelled_count} réservation(s) annulée(s)");
    }
    
    // Si appelé via HTTP, retourner JSON
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'cancelled_count' => $cancelled_count,
            'message' => "{$cancelled_count} réservation(s) expirée(s) annulée(s)"
        ]);
    }
    
} catch (Exception $e) {
    error_log("❌ Erreur script auto-annulation: " . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>