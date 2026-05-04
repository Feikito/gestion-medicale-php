<?php
// php/db.php

// --- Configuration de la Connexion à la Base de Données ---

// Paramètres de connexion
// Idéalement, en production, ces informations ne devraient pas être directement dans le code
// mais plutôt dans des variables d'environnement ou un fichier de configuration sécurisé
// en dehors du webroot.
$host = 'localhost';                     // Généralement 'localhost' pour un serveur local
$dbname = 'rvd_medicale';              // Le nom exact de votre base de données
$username = 'root';                    // Votre nom d'utilisateur MySQL (souvent 'root' en local)
$password = '';                        // Votre mot de passe MySQL (souvent vide pour XAMPP/WAMP en local)
$charset = 'utf8mb4';                  // Recommandé pour une compatibilité complète avec les caractères (emojis, etc.)

// Construction du Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Options pour la connexion PDO pour améliorer la gestion des erreurs et le format des résultats
$options = [
    // Gérer les erreurs SQL en lançant des exceptions PDOException (recommandé)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // Récupérer les résultats par défaut sous forme de tableaux associatifs (noms de colonnes comme clés)
    // Cela évite de devoir spécifier PDO::FETCH_ASSOC à chaque appel de fetch() ou fetchAll().
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Désactiver l'émulation des requêtes préparées pour utiliser les "vraies" requêtes préparées
    // fournies par le SGBD (MySQL dans ce cas). C'est généralement plus sûr et peut être plus performant.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Tentative de Connexion ---
try {
    // Créer l'instance PDO (l'objet de connexion à la base de données)
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // L'objet $pdo est maintenant disponible pour être utilisé dans les autres scripts
    // qui incluront ce fichier (via require 'db.php'; ou require __DIR__ . '/db.php'; etc.)

} catch (PDOException $e) {
    // En cas d'échec de la connexion, il est crucial d'arrêter le script
    // et de gérer l'erreur de manière appropriée.

    // 1. Logguer l'erreur réelle côté serveur (très important pour le débogage)
    // Assurez-vous que votre serveur PHP est configuré pour logger les erreurs
    // dans un fichier (par exemple, via error_log() ou la configuration de php.ini).
    error_log("ERREUR CRITIQUE DE CONNEXION PDO à la base de données: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");

    // 2. Afficher un message d'erreur générique et convivial à l'utilisateur.
    // NE JAMAIS afficher $e->getMessage() directement en production car cela peut
    // révéler des informations sensibles sur votre configuration serveur/BDD.
    // Vous pouvez personnaliser ce message ou même afficher une page d'erreur dédiée.
    // http_response_code(503); // Service Unavailable (optionnel)
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Erreur de Service</title>";
    echo "<style>body{font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;} .error-container{text-align: center; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);}</style>";
    echo "</head><body>";
    echo "<div class='error-container'>";
    echo "<h1>Oops! Service Temporairement Indisponible</h1>";
    echo "<p>Nous rencontrons actuellement des difficultés techniques pour accéder à nos services de données.</p>";
    echo "<p>Notre équipe technique a été informée et travaille à résoudre le problème. Veuillez réessayer ultérieurement.</p>";
    echo "<p>Nous vous prions de nous excuser pour la gêne occasionnée.</p>";
    // Optionnel: lien de retour ou contact support
    // echo "<p><a href='/index.php'>Retour à l'accueil</a></p>";
    echo "</div>";
    echo "</body></html>";
    
    // Arrêter l'exécution de tout script PHP qui inclurait ce fichier après l'échec de connexion.
    exit; 
}

// Si vous atteignez ce point, la connexion est établie et $pdo est prêt.
?>