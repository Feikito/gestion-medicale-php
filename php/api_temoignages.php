<?php
// php/api_temoignages.php
header('Content-Type: application/json');
require 'db.php'; // Assurez-vous que ce chemin est correct pour accéder à db.php

$temoignages = [];
try {
    // Récupère 3 commentaires validés aléatoirement
    // Adaptez les noms de colonnes 'nom', 'contenu', 'statut' si les vôtres sont différents.
    $stmt = $pdo->query("SELECT nom, contenu FROM commentaires WHERE statut = 'validé' ORDER BY RAND() LIMIT 3");
    $temoignages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur API témoignages (api_temoignages.php): " . $e->getMessage());
    // En cas d'erreur, retourner un tableau vide ou un message d'erreur JSON
    // Pour la production, il est préférable de ne pas exposer les détails de l'erreur.
    echo json_encode(['error' => 'Impossible de charger les témoignages pour le moment.']);
    exit;
}

echo json_encode($temoignages);
?>