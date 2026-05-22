<?php

class Database {
    private $host = 'localhost';
    private $db_name = 'hawas_bjaya';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            echo "Erreur de connexion: " . $exception->getMessage();
        }
        return $this->conn;
    }
}


if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if (!function_exists('generateConfirmationCode')) {
    function generateConfirmationCode() {
        return 'HB' . date('Y') . rand(1000, 9999);
    }
}

if (!function_exists('sendWhatsAppNotification')) {
    function sendWhatsAppNotification($phone, $message) {
        $whatsapp_url = "https://wa.me/" . $phone . "?text=" . urlencode($message);
        return $whatsapp_url;
    }
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>