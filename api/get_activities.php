<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, name FROM activities WHERE is_active = TRUE ORDER BY name";
    $result = $db->query($query)->fetchAll();
    
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>