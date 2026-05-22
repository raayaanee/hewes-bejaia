<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/email.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$required_fields = ['name', 'email', 'phone', 'password'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'error' => "Le champ $field est requis"]);
        exit;
    }
}

$name = sanitize($input['name']);
$email = sanitize($input['email']);
$phone = sanitize($input['phone']);
$password = $input['password'];

// Validation format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Format d\'email invalide']);
    exit;
}

// Validation mot de passe
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier si l'email existe déjà
    $check_query = "SELECT id FROM clients WHERE email = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'Un compte existe déjà avec cet email'
        ]);
        exit;
    }
    
    // Générer le token de vérification
    $verification_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Créer le nouveau client
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $insert_query = "
        INSERT INTO clients (
            full_name, 
            email, 
            phone, 
            password, 
            email_verified, 
            verification_token, 
            verification_token_expires,
            created_at
        ) VALUES (?, ?, ?, ?, 0, ?, ?, NOW())
    ";
    
    $stmt = $db->prepare($insert_query);
    $stmt->execute([
        $name, 
        $email, 
        $phone, 
        $hashed_password, 
        $verification_token, 
        $token_expires
    ]);
    
    $client_id = $db->lastInsertId();
    
    // Envoyer l'email de vérification
    try {
        $emailService = new EmailService();
        $email_sent = $emailService->sendVerificationEmail($email, $name, $verification_token);
        
        if ($email_sent) {
            echo json_encode([
                'success' => true,
                'message' => 'Inscription réussie ! Veuillez vérifier votre email pour activer votre compte.',
                'client_id' => $client_id,
                'email_sent' => true
            ]);
        } else {
            // Compte créé mais email non envoyé
            echo json_encode([
                'success' => true,
                'message' => 'Compte créé mais erreur d\'envoi de l\'email. Contactez le support.',
                'client_id' => $client_id,
                'email_sent' => false
            ]);
        }
    } catch (Exception $e) {
        error_log("Erreur envoi email vérification: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'message' => 'Compte créé. L\'email de vérification n\'a pas pu être envoyé.',
            'client_id' => $client_id,
            'email_sent' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur d'inscription client: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de l\'inscription'
    ]);
}
?>