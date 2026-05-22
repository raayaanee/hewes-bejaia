<?php

require_once __DIR__ . '/../config/database.php';

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

try {
    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);

    error_log("Données reçues: " . $jsonInput);

    if (!$input) {
        throw new Exception('Aucune donnée reçue ou JSON invalide');
    }

    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Nom d\'utilisateur et mot de passe requis');
    }

    $username = sanitize($input['username']);
    $password = $input['password'];

    if (empty($username) || empty($password)) {
        throw new Exception('Les champs ne peuvent pas être vides');
    }

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM admins WHERE (username = :username OR email = :email) AND is_active = TRUE";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $username, PDO::PARAM_STR);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé ou compte désactivé']);
        exit;
    }

    if (password_verify($password, $admin['password'])) {
        // Régénérer l'ID de session pour sécurité
        session_regenerate_id(true);

        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_name']     = $admin['full_name'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email']    = $admin['email'];
        $_SESSION['user_type']      = 'admin';

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Connexion admin réussie',
            'admin'   => [
                'id'       => $admin['id'],
                'name'     => $admin['full_name'],
                'username' => $admin['username'],
                'email'    => $admin['email']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Mot de passe incorrect']);
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans admin_login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
} catch (Exception $e) {
    error_log("Erreur dans admin_login.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>