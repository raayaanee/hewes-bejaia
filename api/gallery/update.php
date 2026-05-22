<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
// Mise à jour...