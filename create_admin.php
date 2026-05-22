<?php
require_once 'config/database.php';

// Mot de passe en clair
$password = 'admin123';

// Générer le hash
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Création d'un administrateur</h2>";
echo "<p><strong>Mot de passe en clair :</strong> $password</p>";
echo "<p><strong>Hash généré :</strong> $hashedPassword</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
   
    
    
    // Insérer le nouvel admin
    $insertQuery = "INSERT INTO admins (username, email, password, full_name, is_active) 
                    VALUES (:username, :email, :password, :full_name, TRUE)";
    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@hawasbejaia.com',
        ':password' => $hashedPassword,
        ':full_name' => 'Administrateur Hawas Bjaya'
    ]);
    
    echo "<p style='color: green; font-size: 20px;'>✓ ✓ ✓ ADMIN CRÉÉ AVEC SUCCÈS !</p>";
    echo "<hr>";
    echo "<h3>Identifiants de connexion :</h3>";
    echo "<ul>";
    echo "<li><strong>Username :</strong> admin</li>";
    echo "<li><strong>Email :</strong> admin@hawasbejaia.com</li>";
    echo "<li><strong>Password :</strong> admin123</li>";
    echo "</ul>";
    
    // Vérifier que ça marche
    $checkQuery = "SELECT * FROM admins WHERE username = 'admin'";
    $checkStmt = $db->query($checkQuery);
    $admin = $checkStmt->fetch();
    
    if ($admin) {
        echo "<hr>";
        echo "<h3>Vérification :</h3>";
        echo "<p>✓ Admin trouvé dans la base de données</p>";
        echo "<p>✓ ID: " . $admin['id'] . "</p>";
        echo "<p>✓ Username: " . $admin['username'] . "</p>";
        echo "<p>✓ Email: " . $admin['email'] . "</p>";
        
        // Tester le mot de passe
        if (password_verify('admin123', $admin['password'])) {
            echo "<p style='color: green; font-weight: bold;'>✓ ✓ ✓ Le mot de passe 'admin123' fonctionne parfaitement !</p>";
        } else {
            echo "<p style='color: red;'>✗ Erreur : Le mot de passe ne correspond pas !</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Prochaine étape :</h3>";
    echo "<p>1. Allez sur <a href='index.php'>index.php</a></p>";
    echo "<p>2. Cliquez sur 'Se connecter'</p>";
    echo "<p>3. Sélectionnez 'Admin'</p>";
    echo "<p>4. Utilisez : <strong>admin</strong> / <strong>admin123</strong></p>";
    
    echo "<hr>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité !</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Erreur : " . $e->getMessage() . "</p>";
}
?>