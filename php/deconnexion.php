<?php
// php/deconnexion.php

session_start();

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

// Préparer un message flash pour la page d'accueil.
session_start(); // Redémarrer une session minimale pour le message flash.
$_SESSION['flash_message'] = "Vous avez été déconnecté(e) avec succès.";
$_SESSION['flash_type'] = "info";

// Rediriger l'utilisateur vers la page d'accueil.
// Si deconnexion.php est dans php/ et index.php est à la racine du projet :
$accueil_url = '../index.php'; 
header("Location: " . $accueil_url);
exit; 
?>