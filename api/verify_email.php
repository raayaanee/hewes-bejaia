<?php
session_start();
require_once '../config/database.php';

// Vérifier si le token est fourni
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: ../index.php?verification=invalid');
    exit;
}

$token = $_GET['token'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Chercher le client avec ce token
    $query = "
        SELECT id, email, full_name, verification_token_expires 
        FROM clients 
        WHERE verification_token = ? 
        AND email_verified = 0
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$token]);
    $client = $stmt->fetch();
    
    if (!$client) {
        // Token invalide ou email déjà vérifié
        header('Location: ../index.php?verification=invalid');
        exit;
    }
    
    // Vérifier l'expiration (24 heures)
    if ($client['verification_token_expires'] && strtotime($client['verification_token_expires']) < time()) {
        header('Location: ../index.php?verification=expired');
        exit;
    }
    
    // Marquer l'email comme vérifié
    $update_query = "
        UPDATE clients 
        SET email_verified = 1, 
            verification_token = NULL, 
            verification_token_expires = NULL 
        WHERE id = ?
    ";
    $stmt = $db->prepare($update_query);
    $stmt->execute([$client['id']]);
    
    // Connecter automatiquement le client
    $_SESSION['client_id'] = $client['id'];
    $_SESSION['client_name'] = $client['full_name'];
    $_SESSION['client_email'] = $client['email'];
    
    // Rediriger avec succès
    header('Location: ../index.php?verification=success');
    exit;
    
} catch (Exception $e) {
    error_log("Erreur verify_email: " . $e->getMessage());
    header('Location: ../index.php?verification=error');
    exit;
}
?>