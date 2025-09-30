<?php

/**
 * =================================================================
 * 📧 EMAIL SERVICE - GESTION D'ENVOI D'EMAILS AVEC PHPMAILER
 * =================================================================
 *
 * Service pour l'envoi d'emails de vérification et récupération
 * Compatible avec votre configuration SMTP Gmail
 */

// ✅ CHARGEMENT DE L'AUTOLOADER COMPOSER
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
class EmailService
{
    private $config;
    public function __construct()
    {
        $this->config = Config::getInstance();
    }

    // ========================================
    // 📧 MÉTHODES PRINCIPALES D'ENVOI
    // ========================================

    /**
     * Envoyer un email de vérification d'inscription
     */
    public function sendVerificationEmail(User $user, string $verificationToken): bool
    {
        try {
            $verificationUrl = $this->generateVerificationUrl($verificationToken);
            $subject = "✅ Vérifiez votre adresse email - Bibliothèque Chrétienne";
            $htmlBody = $this->getVerificationEmailTemplate($user, $verificationUrl);
            $textBody = $this->getVerificationEmailTextTemplate($user, $verificationUrl);
            return $this->sendEmail($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName(), $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("Erreur envoi email vérification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un email de récupération de mot de passe
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): bool
    {
        try {
            $resetUrl = $this->generatePasswordResetUrl($resetToken);
            $subject = "🔐 Réinitialisation de votre mot de passe - Bibliothèque Chrétienne";
            $htmlBody = $this->getPasswordResetEmailTemplate($user, $resetUrl);
            $textBody = $this->getPasswordResetEmailTextTemplate($user, $resetUrl);
            return $this->sendEmail($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName(), $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("Erreur envoi email récupération mot de passe: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // ⚙️ MÉTHODE CORE D'ENVOI AVEC PHPMAILER
    // ========================================

    /**
     * Envoyer un email avec PHPMailer
     */
    private function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        try {
// Créer une instance de PHPMailer
            $mail = new PHPMailer(true);
// Configuration SMTP depuis votre .env
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
// Configuration de l'encodage
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
// Expéditeur
            $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost';
            $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Bibliothèque Numérique';
            $mail->setFrom($fromAddress, $fromName);
            $mail->addReplyTo($fromAddress, $fromName);
// Destinataire
            $mail->addAddress($toEmail, $toName);
// Contenu de l'email
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
// Version texte alternative (optionnelle)
            if (!empty($textBody)) {
                $mail->AltBody = $textBody;
            }

            // Options supplémentaires pour Gmail
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
// Envoyer l'email
            $result = $mail->send();
            if ($result) {
                error_log("Email envoyé avec succès à: " . $toEmail);
                return true;
            } else {
                error_log("Échec envoi email à: " . $toEmail);
                return false;
            }
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // 🔗 GÉNÉRATEURS D'URL
    // ========================================

    /**
     * Générer l'URL de vérification d'email
     */
    private function generateVerificationUrl(string $token): string
    {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . "/verify-email.php?token=" . urlencode($token);
    }

    /**
     * Générer l'URL de réinitialisation de mot de passe
     */
    private function generatePasswordResetUrl(string $token): string
    {
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . "/reset-password.php?token=" . urlencode($token);
    }

    /**
     * Obtenir l'URL de base du site
     */
    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return $protocol . '://' . $host . rtrim($path, '/');
    }

    // ========================================
    // 📧 TEMPLATES HTML - VÉRIFICATION EMAIL
    // ========================================

    /**
     * Template HTML pour email de vérification d'inscription
     */
    private function getVerificationEmailTemplate(User $user, string $verificationUrl): string
    {
        $firstName = htmlspecialchars($user->getFirstName() ?? $user->getUsername());
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Vérification d'email</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f8fafc; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    text-decoration: none; 
                    padding: 16px 32px; 
                    border-radius: 8px; 
                    font-weight: 600; 
                    text-align: center; 
                    margin: 20px 0; 
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    color: #6b7280; 
                    font-size: 14px; 
                    border-top: 1px solid #e5e7eb; 
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'>📚 Bibliothèque</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Vérification de votre compte</p>
                </div>
                
                <div class='content'>
                    <h2 style='color: #333; margin-bottom: 20px;'>Bonjour {$firstName} ! 👋</h2>
                    
                    <p>Bienvenue dans notre bibliothèque ! Pour finaliser votre inscription et accéder à tous nos livres spirituels, veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous :</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verificationUrl}' class='button'>✅ Vérifier mon email</a>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px;'>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
                    <p style='word-break: break-all; color: #667eea; font-size: 14px;'>{$verificationUrl}</p>
                    
                    <p style='margin-top: 30px;'>Ce lien expire dans 24 heures pour votre sécurité.</p>
                </div>
                
                <div class='footer'>
                    <p>© 2025 Bibliothèque. Tous droits réservés.</p>
                    <p>Si vous n'avez pas créé de compte, ignorez simplement cet email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Template texte pour email de vérification (version alternative)
     */
    private function getVerificationEmailTextTemplate(User $user, string $verificationUrl): string
    {
        $firstName = $user->getFirstName() ?? $user->getUsername();
        return "
        Bibliothèque - Vérification d'email
        
        Bonjour {$firstName} !
        
        Bienvenue dans notre bibliothèque ! Pour finaliser votre inscription et accéder à tous nos livres, veuillez vérifier votre adresse email en cliquant sur le lien ci-dessous :
        
        {$verificationUrl}
        
        Ce lien expire dans 24 heures pour votre sécurité.
        
        Si vous n'avez pas créé de compte, ignorez simplement cet email.
        
        © 2025 Bibliothèque. Tous droits réservés.
        ";
    }

    // ========================================
    // 🔐 TEMPLATES HTML - RÉCUPÉRATION MOT DE PASSE
    // ========================================

    /**
     * Template HTML pour email de récupération de mot de passe
     */
    private function getPasswordResetEmailTemplate(User $user, string $resetUrl): string
    {
        $firstName = htmlspecialchars($user->getFirstName() ?? $user->getUsername());
        $timeRemaining = $user->getPasswordResetTimeRemaining();
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Réinitialisation de mot de passe</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f8fafc; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .content { 
                    padding: 40px 30px; 
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); 
                    color: white; 
                    text-decoration: none; 
                    padding: 16px 32px; 
                    border-radius: 8px; 
                    font-weight: 600; 
                    text-align: center; 
                    margin: 20px 0; 
                }
                .warning-box {
                    background: #fef3c7;
                    border: 2px solid #f59e0b;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    color: #6b7280; 
                    font-size: 14px; 
                    border-top: 1px solid #e5e7eb; 
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 24px;'> Bibliothèque </h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Réinitialisation de mot de passe</p>
                </div>
                
                <div class='content'>
                    <h2 style='color: #333; margin-bottom: 20px;'>Bonjour {$firstName} ! 👋</h2>
                    
                    <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte sur notre bibliothèque.</p>
                    
                    <div class='warning-box'>
                        <p style='margin: 0; color: #92400e; font-weight: 600;'>⚠️ Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email. Votre mot de passe restera inchangé.</p>
                    </div>
                    
                    <p>Pour créer un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetUrl}' class='button'>🔐 Réinitialiser mon mot de passe</a>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px;'>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
                    <p style='word-break: break-all; color: #dc2626; font-size: 14px;'>{$resetUrl}</p>
                    
                    <div style='background: #fee2e2; border-radius: 8px; padding: 16px; margin: 30px 0;'>
                        <p style='margin: 0; color: #991b1b; font-weight: 600;'>🕒 Important :</p>
                        <p style='margin: 8px 0 0 0; color: #991b1b;'>Ce lien expire dans {$timeRemaining} minutes pour votre sécurité.</p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>© 2025 Bibliothèque. Tous droits réservés.</p>
                    <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Template texte pour email de récupération de mot de passe (version alternative)
     */
    private function getPasswordResetEmailTextTemplate(User $user, string $resetUrl): string
    {
        $firstName = $user->getFirstName() ?? $user->getUsername();
        $timeRemaining = $user->getPasswordResetTimeRemaining();
        return "
        Bibliothèque - Réinitialisation de mot de passe
        
        Bonjour {$firstName} !
        
        Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.
        
        Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.
        
        Pour créer un nouveau mot de passe, cliquez sur le lien ci-dessous :
        
        {$resetUrl}
        
        ⚠️ IMPORTANT : Ce lien expire dans {$timeRemaining} minutes pour votre sécurité.
        
        Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.
        
        © 2025 Bibliothèque . Tous droits réservés.
        ";
    }
}
