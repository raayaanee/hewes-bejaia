<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'error' => 'Token et mot de passe requis']);
    exit;
}

$token        = $input['token'];
$new_password = $input['password'];

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ? AND used = 0");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        echo json_encode(['success' => false, 'error' => 'Lien de réinitialisation invalide ou déjà utilisé.']);
        exit;
    }
    if (strtotime($reset['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'Ce lien a expiré. Veuillez en demander un nouveau.']);
        exit;
    }

    $db->prepare("UPDATE clients SET password = ? WHERE email = ?")
       ->execute([password_hash($new_password, PASSWORD_DEFAULT), $reset['email']]);

    $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
       ->execute([$token]);

    echo json_encode(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès !']);

} catch (Exception $e) {
    error_log("Erreur reset_password: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la réinitialisation.']);
}