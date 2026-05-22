<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    $result = $db->query("SELECT * FROM time_slots WHERE is_active = TRUE ORDER BY start_time")->fetchAll();
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}