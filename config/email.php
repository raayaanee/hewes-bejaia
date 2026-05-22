<?php
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/dotenv.php'; // Charger DotEnv si pas encore fait

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {

    private PHPMailer $mail;

    // ─── Paramètres SMTP lus depuis .env ────────────────────────────────────
    private string $smtp_host;
    private int    $smtp_port;
    private string $smtp_username;
    private string $smtp_password;
    private string $from_email;
    private string $from_name;

    // ─── Paramètres applicatifs ──────────────────────────────────────────────
    private int    $verification_expiry; // heures
    private int    $reset_expiry;        // heures

    // ─── Variables de construction des liens ────────────────────────────────
    private string $app_url;

    // ────────────────────────────────────────────────────────────────────────

    public function __construct() {
        // Vérifier que les variables critiques sont présentes
        DotEnv::required([
            'SMTP_HOST',
            'SMTP_PORT',
            'SMTP_USERNAME',
            'SMTP_PASSWORD',
            'SMTP_FROM_EMAIL',
            'SMTP_FROM_NAME',
            'APP_URL',
        ]);

        $this->smtp_host           = DotEnv::get('SMTP_HOST');
        $this->smtp_port           = DotEnv::getInt('SMTP_PORT', 587);
        $this->smtp_username       = DotEnv::get('SMTP_USERNAME');
        $this->smtp_password       = DotEnv::get('SMTP_PASSWORD');
        $this->from_email          = DotEnv::get('SMTP_FROM_EMAIL');
        $this->from_name           = DotEnv::get('SMTP_FROM_NAME', 'HEWES BEJAIA');
        $this->app_url             = rtrim(DotEnv::get('APP_URL'), '/');
        $this->verification_expiry = DotEnv::getInt('EMAIL_VERIFICATION_EXPIRY', 24);
        $this->reset_expiry        = DotEnv::getInt('PASSWORD_RESET_EXPIRY', 1);

        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }

    // ─── Configuration SMTP ──────────────────────────────────────────────────

    private function setupSMTP(): void {
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = $this->smtp_host;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $this->smtp_username;
            $this->mail->Password   = $this->smtp_password;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = $this->smtp_port;
            $this->mail->CharSet    = 'UTF-8';

            // Activer le debug uniquement en mode développement
            if (DotEnv::getBool('APP_DEBUG', false)) {
                $this->mail->SMTPDebug = 2; // messages client + serveur
            }

            $this->mail->setFrom($this->from_email, $this->from_name);

        } catch (Exception $e) {
            error_log('[EmailService] Erreur configuration SMTP : ' . $e->getMessage());
            throw new \RuntimeException('Impossible de configurer le service email.');
        }
    }

    // ─── Construction des URLs ───────────────────────────────────────────────

    /**
     * Construit l'URL de base (https ou http) depuis APP_URL ou le serveur courant.
     */
    private function buildBaseUrl(): string {
        // Priorité : APP_URL défini dans .env
        if (!empty($this->app_url)) {
            // Ajouter le schéma s'il est absent
            if (!preg_match('#^https?://#', $this->app_url)) {
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                    ? 'https'
                    : 'http';
                return $scheme . '://' . $this->app_url;
            }
            return $this->app_url;
        }

        // Fallback : déduire depuis $_SERVER
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    // ─── Envoi emails ────────────────────────────────────────────────────────

    /**
     * Envoyer un email de vérification de compte.
     *
     * @param string $to_email          Adresse du destinataire
     * @param string $to_name           Nom du destinataire
     * @param string $verification_token Token unique de vérification
     * @return bool
     */
    public function sendVerificationEmail(
        string $to_email,
        string $to_name,
        string $verification_token
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to_email, $to_name);

            $link = $this->buildBaseUrl()
                . '/api/verify_email.php?token='
                . urlencode($verification_token);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Vérifiez votre adresse email - HEWES BEJAIA';
            $this->mail->Body    = $this->getVerificationEmailTemplate(
                $to_name,
                $link,
                $this->verification_expiry
            );
            $this->mail->AltBody = $this->getVerificationEmailText(
                $to_name,
                $link,
                $this->verification_expiry
            );

            $result = $this->mail->send();
            if ($result) {
                error_log("[EmailService] Email de vérification envoyé à : $to_email");
            }
            return $result;

        } catch (Exception $e) {
            error_log('[EmailService] Erreur envoi vérification : ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Envoyer un email de réinitialisation de mot de passe.
     *
     * @param string $to_email    Adresse du destinataire
     * @param string $to_name     Nom du destinataire
     * @param string $reset_token Token unique de réinitialisation
     * @return bool
     */
    public function sendPasswordResetEmail(
        string $to_email,
        string $to_name,
        string $reset_token
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to_email, $to_name);

            $link = $this->buildBaseUrl()
                . '/reset-password.php?token='
                . urlencode($reset_token);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Réinitialisation de votre mot de passe - HEWES BEJAIA';
            $this->mail->Body    = $this->getPasswordResetEmailTemplate(
                $to_name,
                $link,
                $this->reset_expiry
            );
            $this->mail->AltBody = $this->getPasswordResetEmailText(
                $to_name,
                $link,
                $this->reset_expiry
            );

            $result = $this->mail->send();
            if ($result) {
                error_log("[EmailService] Email de reset envoyé à : $to_email");
            }
            return $result;

        } catch (Exception $e) {
            error_log('[EmailService] Erreur envoi reset : ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    // ─── Templates HTML ──────────────────────────────────────────────────────

    private function getVerificationEmailTemplate(
        string $name,
        string $link,
        int    $expiry
    ): string {
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vérification email</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px;
                             overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                          color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 15px 40px;
                          text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .note { color: #666; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Bienvenue sur HEWES BEJAIA !</h1>
                </div>
                <div class="content">
                    <h2>Bonjour {$escapedName},</h2>
                    <p>Merci de vous être inscrit sur notre plateforme de réservation !</p>
                    <p>Pour activer votre compte, veuillez cliquer sur le bouton ci-dessous :</p>
                    <center>
                        <a href="{$escapedLink}" class="button">Vérifier mon email</a>
                    </center>
                    <p class="note">Ce lien expire dans <strong>{$expiry} heures</strong>.</p>
                    <p class="note">Si vous n'avez pas créé de compte, ignorez cet email.</p>
                </div>
                <div class="footer">
                    <p>HEWES BEJAIA – Découvrez la perle de la Méditerranée</p>
                    <p>+213 775 654 995 | Béjaïa, Algérie</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function getPasswordResetEmailTemplate(
        string $name,
        string $link,
        int    $expiry
    ): string {
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Réinitialisation mot de passe</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px;
                             overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                          color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 15px 40px;
                          text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .note { color: #666; font-size: 14px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Réinitialisation de mot de passe</h1>
                </div>
                <div class="content">
                    <h2>Bonjour {$escapedName},</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                    <p>Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>
                    <center>
                        <a href="{$escapedLink}" class="button">Réinitialiser mon mot de passe</a>
                    </center>
                    <div class="warning">
                        <strong>⚠️ Important :</strong> Ce lien expire dans <strong>{$expiry} heure(s)</strong>.
                    </div>
                    <p class="note">
                        Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.
                        Votre mot de passe actuel restera inchangé.
                    </p>
                </div>
                <div class="footer">
                    <p>HEWES BEJAIA – Découvrez la perle de la Méditerranée</p>
                    <p>+213 775 654 995 | Béjaïa, Algérie</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    // ─── Templates texte brut (fallback) ────────────────────────────────────

    private function getVerificationEmailText(string $name, string $link, int $expiry): string {
        return "Bonjour $name,\n\n"
            . "Merci de votre inscription sur HEWES BEJAIA !\n\n"
            . "Cliquez sur ce lien pour vérifier votre email :\n$link\n\n"
            . "Ce lien expire dans $expiry heures.\n\n"
            . "Si vous n'avez pas créé de compte, ignorez cet email.\n\n"
            . "— HEWES BEJAIA | Béjaïa, Algérie";
    }

    private function getPasswordResetEmailText(string $name, string $link, int $expiry): string {
        return "Bonjour $name,\n\n"
            . "Vous avez demandé la réinitialisation de votre mot de passe.\n\n"
            . "Cliquez sur ce lien pour créer un nouveau mot de passe :\n$link\n\n"
            . "Ce lien expire dans $expiry heure(s).\n\n"
            . "Si vous n'avez pas fait cette demande, ignorez cet email.\n\n"
            . "— HEWES BEJAIA | Béjaïa, Algérie";
    }
}