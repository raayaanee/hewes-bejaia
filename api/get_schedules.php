<?php
/**
 * api/get_schedules.php
 * Liste tous les plannings d'activités
 */
header('Content-Type: application/json');
require_once '../config/database.php';
try {
    $db   = (new Database())->getConnection();
    $stmt = $db->query("
        SELECT s.*, a.name as activity_name, a.booking_mode, a.duration_minutes
        FROM activity_schedule s
        JOIN activities a ON s.activity_id = a.id
        ORDER BY a.name, s.day_of_week
    ");
    echo json_encode(['success' => true, 'schedules' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
