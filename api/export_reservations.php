<?php
require_once '../config/database.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php'); 
    exit;
}

$database = new Database();
$db = $database->getConnection();

$filter_activity = $_GET['activity'] ?? 'all';
$filter_status   = $_GET['status'] ?? 'all';
$filter_date     = $_GET['date'] ?? '';

$where_conditions = [];
$params = [];

if ($filter_activity !== 'all') {
    $where_conditions[] = "r.activity_id = :activity_id";
    $params[':activity_id'] = $filter_activity;
}
if ($filter_status !== 'all') {
    $where_conditions[] = "r.status = :status";
    $params[':status'] = $filter_status;
}
if (!empty($filter_date)) {
    $where_conditions[] = "r.reservation_date = :date";
    $params[':date'] = $filter_date;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT
        r.confirmation_code as 'Code Confirmation',
        r.client_name as 'Nom Client',
        r.client_email as 'Email',
        r.client_phone as 'Téléphone',
        a.name as 'Activité',
        CASE WHEN a.booking_type = 'private' THEN 'Privatif' ELSE 'Partagé' END as 'Type',
        DATE_FORMAT(r.reservation_date, '%d/%m/%Y') as 'Date',
        CONCAT(TIME_FORMAT(ts.start_time, '%H:%i'), ' - ', TIME_FORMAT(ts.end_time, '%H:%i')) as 'Horaire',
        r.participants as 'Participants',
        CONCAT(FORMAT(r.total_price, 0), ' DA') as 'Prix Total',
        CASE
            WHEN r.status = 'pending'   THEN 'En Attente'
            WHEN r.status = 'confirmed' THEN 'Confirmée'
            WHEN r.status = 'cancelled' THEN 'Annulée'
            ELSE r.status
        END as 'Statut',
        r.special_requests as 'Demandes Spéciales',
        DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as 'Date Création'
    FROM reservations r
    JOIN activities a  ON r.activity_id  = a.id
    JOIN time_slots ts ON r.time_slot_id = ts.id
    $where_clause
    ORDER BY r.reservation_date DESC, ts.start_time ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'reservations_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 pour Excel

if (!empty($reservations)) {
    fputcsv($output, array_keys($reservations[0]), ';');
    foreach ($reservations as $reservation) {
        fputcsv($output, $reservation, ';');
    }
}

fclose($output);
exit;