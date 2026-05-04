<?php
// admin/deconnexion_admin.php

session_start();

// Vérifier si une session admin existe réellement.
if (isset($_SESSION['admin_id'])) { 
    // Vider toutes les variables de la session actuelle.
    $_SESSION = array();

    // Supprimer le cookie de session côté client.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }

    // Détruire la session sur le serveur.
    session_destroy();

    // Préparer un message flash pour la page de connexion admin.
    session_start(); // Redémarrer une session minimale pour le message.
    $_SESSION['flash_message_admin_login'] = "Vous avez été déconnecté(e) avec succès de l'espace administration.";
    $_SESSION['flash_type_admin_login'] = "info"; 

} else {
    // Si aucune session admin n'était active, s'assurer qu'une session est démarrée
    // si on voulait y mettre un message flash (non fait ici).
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Optionnellement, on pourrait mettre un message différent si l'utilisateur tente de se déconnecter sans être connecté.
    // $_SESSION['flash_message_admin_login'] = "Aucune session active à déconnecter.";
    // $_SESSION['flash_type_admin_login'] = "info";
}

// Rediriger l'utilisateur vers la page de connexion administrateur.
// Ce chemin est correct si deconnexion_admin.php est dans admin/ et admin-login.php dans pages/.
$admin_login_url = '../pages/admin-login.php'; 
header("Location: " . $admin_login_url);
exit; 
?>