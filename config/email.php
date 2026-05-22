<?php
/**
 * Configuration Email avec PHPMailer - INSTALLATION MANUELLE
 * 
 * INSTRUCTIONS D'INSTALLATION :
 * 1. Téléchargez PHPMailer : https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip
 * 2. Extrayez et copiez le dossier dans votre projet comme suit :
 *    votre_projet/PHPMailer/src/
 * 3. Configurez vos identifiants Gmail ci-dessous
 */

// Inclure les fichiers PHPMailer manuellement
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;
    
    // ⚠️ CONFIGURATION À PERSONNALISER
    private $smtp_host = 'smtp.gmail.com';  // Gmail SMTP
    private $smtp_port = 587;                // 587 pour TLS, 465 pour SSL
    private $smtp_username = 'rayanmaouche275@gmail.com';  // ⚠️ CHANGEZ ICI
    private $smtp_password = 'grdo rznz kwre byiy';  // ⚠️ CHANGEZ ICI (16 caractères)
    private $from_email = 'rayanmaouche275@gmail.com';  // ⚠️ CHANGEZ ICI
    private $from_name = 'Hewes bejaia';
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Configuration serveur
            $this->mail->isSMTP();
            $this->mail->Host = $this->smtp_host;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->smtp_username;
            $this->mail->Password = $this->smtp_password;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $this->smtp_port;
            $this->mail->CharSet = 'UTF-8';
            
            // Pour déboguer les problèmes d'envoi (décommentez si besoin)
            // $this->mail->SMTPDebug = 2; // 0 = off, 1 = messages client, 2 = messages client et serveur
            
            // Configuration expéditeur
            $this->mail->setFrom($this->from_email, $this->from_name);
        } catch (Exception $e) {
            error_log("Erreur configuration SMTP: " . $e->getMessage());
        }
    }
    
    /**
     * Envoyer un email de vérification
     */
    public function sendVerificationEmail($to_email, $to_name, $verification_token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to_email, $to_name);
            
            // Construire l'URL de vérification
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $verification_link = $protocol . "://" . $host . "/api/verify_email.php?token=" . $verification_token;
            
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Vérifiez votre adresse email - HEWES BEJAIA';
            $this->mail->Body = $this->getVerificationEmailTemplate($to_name, $verification_link);
            $this->mail->AltBody = "Bonjour $to_name,\n\nMerci de votre inscription ! Cliquez sur ce lien pour vérifier votre email :\n$verification_link\n\nCe lien expire dans 24 heures.";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Erreur envoi email vérification: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Envoyer un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail($to_email, $to_name, $reset_token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to_email, $to_name);
            
            // Construire l'URL de réinitialisation
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . "://" . $host . "/reset-password.php?token=" . $reset_token;
            
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Réinitialisation de votre mot de passe - HEWES BEJAIA';
            $this->mail->Body = $this->getPasswordResetEmailTemplate($to_name, $reset_link);
            $this->mail->AltBody = "Bonjour $to_name,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nCliquez sur ce lien :\n$reset_link\n\nCe lien expire dans 1 heure.";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Erreur envoi email reset: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Template HTML pour email de vérification
     */
    private function getVerificationEmailTemplate($name, $link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Bienvenue sur HEWES BEJAIA !</h1>
                </div>
                <div class='content'>
                    <h2>Bonjour $name,</h2>
                    <p>Merci de vous être inscrit sur notre plateforme de réservation !</p>
                    <p>Pour activer votre compte et commencer à réserver vos activités préférées, veuillez cliquer sur le bouton ci-dessous :</p>
                    <center>
                        <a href='$link' class='button'>Vérifier mon email</a>
                    </center>
                    <p style='color: #666; font-size: 14px;'>Ce lien expire dans <strong>24 heures</strong>.</p>
                    <p style='color: #666; font-size: 14px;'>Si vous n'avez pas créé de compte, ignorez cet email.</p>
                </div>
                <div class='footer'>
                    <p>HEWES BEJAIA - Découvrez la perle de la Méditerranée</p>
                    <p>+213 775 654 995 | Béjaïa, Algérie</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Template HTML pour réinitialisation mot de passe
     */
    private function getPasswordResetEmailTemplate($name, $link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Réinitialisation de mot de passe</h1>
                </div>
                <div class='content'>
                    <h2>Bonjour $name,</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                    <p>Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>
                    <center>
                        <a href='$link' class='button'>Réinitialiser mon mot de passe</a>
                    </center>
                    <div class='warning'>
                        <strong>⚠️ Important :</strong> Ce lien expire dans <strong>1 heure</strong>.
                    </div>
                    <p style='color: #666; font-size: 14px;'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre mot de passe actuel restera inchangé.</p>
                </div>
                <div class='footer'>
                    <p>HEWES BEJAIA - Découvrez la perle de la Méditerranée</p>
                    <p>+213 775 654 995 | Béjaïa, Algérie</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

/**
 * ============================================
 * GUIDE DE CONFIGURATION GMAIL
 * ============================================
 * 
 * 1. Activez la validation en 2 étapes :
 *    - https://myaccount.google.com/security
 *    - Cherchez "Validation en deux étapes"
 *    - Suivez les instructions
 * 
 * 2. Générez un mot de passe d'application :
 *    - Retournez sur https://myaccount.google.com/security
 *    - Cherchez "Mots de passe d'application"
 *    - Sélectionnez "Messagerie" → "Autre (nom personnalisé)"
 *    - Tapez : "HEWES BEJAIA Site"
 *    - Cliquez "Générer"
 *    - Copiez le mot de passe de 16 caractères
 * 
 * 3. Collez-le dans $smtp_password ci-dessus
 * 
 * LIMITES GMAIL :
 * - Maximum 500 emails par jour
 * - Maximum 100 destinataires par email
 * 
 * ALTERNATIVES GRATUITES :
 * - SendGrid : 100 emails/jour gratuits
 * - Mailgun : 5000 emails/mois gratuits
 * - Amazon SES : Très économique
 */
?>