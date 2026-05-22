<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

require_once '../config/database.php';
require_once '../config/email.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || empty($input['email'])) {
    echo json_encode(['success' => false, 'error' => 'Email requis']);
    exit;
}

$email = sanitize($input['email']);

// Validation format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Format d\'email invalide']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier si l'email existe
    $query = "SELECT id, full_name, email FROM clients WHERE email = ? AND is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    
    if (!$client) {
        // ⚠️ SÉCURITÉ : Ne pas révéler si l'email existe ou non
        echo json_encode([
            'success' => true,
            'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'
        ]);
        exit;
    }
    
    // Générer un token unique sécurisé
    $token = bin2hex(random_bytes(32)); // 64 caractères
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expire dans 1 heure
    
    // Supprimer les anciens tokens de cet email
    $delete_query = "DELETE FROM password_resets WHERE email = ?";
    $stmt = $db->prepare($delete_query);
    $stmt->execute([$email]);
    
    // Insérer le nouveau token
    $insert_query = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $db->prepare($insert_query);
    $stmt->execute([$email, $token, $expires_at]);
    
    // Envoyer l'email
    $emailService = new EmailService();
    $email_sent = $emailService->sendPasswordResetEmail(
        $client['email'],
        $client['full_name'],
        $token
    );
    
    if ($email_sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Un email de réinitialisation a été envoyé à votre adresse.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur forgot_password: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors du traitement de votre demande.'
    ]);
}
?>