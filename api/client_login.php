<?php
session_start(); 
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'error' => 'Email et mot de passe requis']);
    exit;
}

$email = sanitize($input['email']);
$password = $input['password'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM clients WHERE email = ? AND is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    
    if (!$client) {
        echo json_encode([
            'success' => false,
            'error' => 'Email non trouvé'
        ]);
        exit;
    }
    
    if (password_verify($password, $client['password'])) {
        
        // Régénérer l'ID de session pour éviter la fixation de session
        session_regenerate_id(true);
        
        $_SESSION['client_id']    = $client['id'];
        $_SESSION['client_name']  = $client['full_name'];
        $_SESSION['client_email'] = $client['email'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Connexion réussie',
            'client' => [
                'id'    => $client['id'],
                'name'  => $client['full_name'],
                'email' => $client['email']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Mot de passe incorrect'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion à la base de données'
    ]);
}
?>