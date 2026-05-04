<?php
// php/utils/email_functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php'; 

// --- CONFIGURATION SMTP ---
// Utilisation de Gmail avec un Mot de Passe d'Application

define('SMTP_HOST_CONFIG', 'smtp.gmail.com');
define('SMTP_USERNAME_CONFIG', 'abdourahamanea9wal@gmail.com'); // VOTRE ADRESSE GMAIL
define('SMTP_PASSWORD_CONFIG', 'gszeyvqtnqapitbk');       // VOTRE MOT DE PASSE D'APPLICATION (sans espaces)
define('SMTP_PORT_CONFIG', 587);                         // Port pour TLS
define('SMTP_SECURE_CONFIG', PHPMailer::ENCRYPTION_STARTTLS); // Cryptage TLS
define('EMAIL_SYSTEM_FROM_ADDRESS', 'abdourahamanea9wal@gmail.com'); // Doit être la même que SMTP_USERNAME_CONFIG pour Gmail
define('EMAIL_SYSTEM_FROM_NAME', 'SANTE TV Platform');


// Destinataire pour les messages du formulaire de contact
// REMPLACEZ CECI PAR L'ADRESSE EMAIL REELLE DE VOTRE SUPPORT/CONTACT
define('CONTACT_FORM_RECIPIENT_EMAIL', 'contact@santetv.ma'); 
define('CONTACT_FORM_RECIPIENT_NAME', 'Support SANTE TV');

// Destinataire pour les notifications à l'administrateur
// REMPLACEZ CECI PAR L'ADRESSE EMAIL REELLE DE VOTRE ADMINISTRATEUR
define('ADMIN_EMAIL_NOTIFICATIONS', 'admin.santetv@estm.ac.ma'); 
define('ADMIN_NAME_NOTIFICATIONS', 'Administrateur SANTE TV');


function envoyer_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = '', ?string $replyToEmail = null, ?string $replyToName = null): bool {
    
    if (!defined('SMTP_HOST_CONFIG') || empty(SMTP_HOST_CONFIG) || 
        !defined('SMTP_USERNAME_CONFIG') || empty(SMTP_USERNAME_CONFIG) ||
        !defined('SMTP_PASSWORD_CONFIG') || empty(SMTP_PASSWORD_CONFIG) ) {
        error_log("Configuration SMTP manquante ou incomplète dans email_functions.php");
        return false;
    }

    $mail = new PHPMailer(true); 

    try {
        // Pour le débogage détaillé pendant les tests, décommentez la ligne suivante :
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
        // En production ou pour un usage normal, utilisez :
        $mail->SMTPDebug = SMTP::DEBUG_OFF;      
        
        $mail->isSMTP();                         
        $mail->Host       = SMTP_HOST_CONFIG;    
        $mail->SMTPAuth   = true;                
        $mail->Username   = SMTP_USERNAME_CONFIG;
        $mail->Password   = SMTP_PASSWORD_CONFIG;
        $mail->SMTPSecure = SMTP_SECURE_CONFIG;  
        $mail->Port       = SMTP_PORT_CONFIG;    
        $mail->CharSet    = PHPMailer::CHARSET_UTF8; 

        $mail->setFrom(EMAIL_SYSTEM_FROM_ADDRESS, EMAIL_SYSTEM_FROM_NAME);
        $mail->addAddress($toEmail, $toName);     

        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?? $toName); 
        }

        $mail->isHTML(true); 
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        
        if (empty($altBody)) {
            $altBodyContent = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
            $mail->AltBody = !empty(trim($altBodyContent)) ? $altBodyContent : 'Pour visualiser ce message, veuillez utiliser un client email compatible HTML.';
        } else {
            $mail->AltBody = $altBody;
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Erreur: Email à {$toEmail}. Sujet: {$subject}. Erreur: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false; 
    }
}
?>