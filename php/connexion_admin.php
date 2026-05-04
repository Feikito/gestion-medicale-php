<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php';
require_once __DIR__ . '/utils/logger.php'; // AJOUTÉ

$default_redirect_admin = '../pages/admin-login.php'; 
$form_origin_admin_login = $_POST['form_origin_admin_login'] ?? $default_redirect_admin;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $default_redirect_admin);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message_admin_login'] = "Erreur de sécurité. Veuillez réessayer de vous connecter.";
    $_SESSION['flash_type_admin_login'] = "danger";
    header("Location: " . $form_origin_admin_login);
    exit;
}

$email_admin = trim(strtolower($_POST['email'] ?? '')); 
$mot_de_passe_admin_soumis = $_POST['mot_de_passe'] ?? '';

$_SESSION['form_data_admin_login'] = ['email' => $_POST['email'] ?? '']; 
$_SESSION['form_errors_admin_login'] = []; 
$errors_admin = &$_SESSION['form_errors_admin_login']; 

if (empty($email_admin)) {
    $errors_admin['email'] = "L'adresse e-mail administrateur est requise.";
} elseif (!filter_var($email_admin, FILTER_VALIDATE_EMAIL)) {
    $errors_admin['email'] = "Le format de l'adresse e-mail est invalide.";
}
if (empty($mot_de_passe_admin_soumis)) {
    $errors_admin['mot_de_passe'] = "Le mot de passe est requis.";
}

if (!empty($errors_admin)) {
    $_SESSION['flash_message_admin_login'] = "Veuillez corriger les erreurs dans le formulaire.";
    $_SESSION['flash_type_admin_login'] = "error";
    header("Location: " . $form_origin_admin_login);
    exit;
}

try {
    if (!$pdo->query("SHOW TABLES LIKE 'admins'")->rowCount() > 0) {
        throw new PDOException("La table 'admins' n'existe pas. L'authentification admin est impossible.");
    }

    $sql_get_admin = "SELECT id, nom, mot_de_passe FROM admins WHERE LOWER(email) = LOWER(?)";
    $stmt_get_admin = $pdo->prepare($sql_get_admin);
    $stmt_get_admin->execute([$email_admin]);
    $admin_db = $stmt_get_admin->fetch(PDO::FETCH_ASSOC);

    if ($admin_db && password_verify($mot_de_passe_admin_soumis, $admin_db['mot_de_passe'])) {
        unset($_SESSION['form_data_admin_login']);
        unset($_SESSION['form_errors_admin_login']);
        unset($_SESSION['flash_message_admin_login']); 
        unset($_SESSION['flash_type_admin_login']);

        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin_db['id']; 
        $_SESSION['admin_nom'] = htmlspecialchars($admin_db['nom']);
        $_SESSION['last_activity_admin'] = time(); 

        // Journalisation de l'action
        log_action_application(
            $pdo, 
            'CONNEXION_ADMIN', 
            "L'administrateur " . htmlspecialchars($admin_db['nom']) . " (ID: " . $admin_db['id'] . ") s'est connecté.",
            $admin_db['id'],
            'admin'
        );

        $_SESSION['flash_message'] = "Connexion réussie ! Bienvenue, " . htmlspecialchars($admin_db['nom']) . ".";
        $_SESSION['flash_type'] = "success";
        
        header('Location: ../admin/dashboard_admin.php'); 
        exit;

    } else {
        $_SESSION['flash_message_admin_login'] = "L'adresse e-mail ou le mot de passe administrateur est incorrect.";
        $_SESSION['flash_type_admin_login'] = "error";
        $errors_admin['_general'] = "Identifiants incorrects.";
        $_SESSION['form_errors_admin_login'] = $errors_admin;
        header("Location: " . $form_origin_admin_login);
        exit;
    }

} catch (PDOException $e) {
    error_log("Erreur PDO connexion_admin.php (Email: $email_admin): " . $e->getMessage());
    $_SESSION['flash_message_admin_login'] = "Une erreur technique est survenue lors de la tentative de connexion. Veuillez réessayer.";
    $_SESSION['flash_type_admin_login'] = "error";
    header("Location: " . $form_origin_admin_login);
    exit;
}
?>