<?php
/**
 * api/get_or_create_slots.php
 * 
 * Reçoit une liste de paires {start, end, label}
 * Retourne les IDs des time_slots correspondants,
 * en les créant dans la DB s'ils n'existent pas encore.
 *
 * POST { pairs: [ {start:"08:00:00", end:"09:30:00", label:"08:00 – 09:30"}, ... ] }
 * → { success:true, slots: { "08:00:00": {id:X, start:"08:00:00", end:"09:30:00"}, ... } }
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// L'admin doit être connecté
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pairs = $input['pairs'] ?? [];

if (empty($pairs)) {
    echo json_encode(['success' => true, 'slots' => []]);
    exit;
}

try {
    $db  = (new Database())->getConnection();
    $result = [];

    $find = $db->prepare("
        SELECT id, start_time, end_time
        FROM time_slots
        WHERE start_time = :s AND end_time = :e
        LIMIT 1
    ");

    $create = $db->prepare("
        INSERT INTO time_slots (name, start_time, end_time, is_active)
        VALUES (:n, :s, :e, 1)
    ");

    foreach ($pairs as $p) {
        $start = $p['start']; // ex: "08:00:00"
        $end   = $p['end'];   // ex: "09:30:00"

        // Normaliser au format HH:MM:SS
        if (strlen($start) === 5) $start .= ':00';
        if (strlen($end)   === 5) $end   .= ':00';

        $find->execute([':s' => $start, ':e' => $end]);
        $row = $find->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $id = (int)$row['id'];
        } else {
            // Créer le time_slot avec un nom lisible
            $label = substr($start, 0, 5) . ' – ' . substr($end, 0, 5);
            $create->execute([':n' => $label, ':s' => $start, ':e' => $end]);
            $id = (int)$db->lastInsertId();
        }

        $result[$start] = [
            'id'    => $id,
            'start' => $start,
            'end'   => $end,
            'label' => substr($start, 0, 5) . ' – ' . substr($end, 0, 5),
        ];
    }

    echo json_encode(['success' => true, 'slots' => $result]);

} catch (Exception $e) {
    error_log('get_or_create_slots: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
?>
