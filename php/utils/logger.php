<?php
// php/utils/logger.php

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Nécessaire pour récupérer l'ID de l'admin connecté
}

/**
 * Enregistre une action dans l'historique de l'application.
 *
 * @param PDO $pdo L'instance de connexion PDO.
 * @param string $type_action Le type d'action (ex: 'CONNEXION_ADMIN').
 * @param string $description_action Une description de l'action.
 * @param int|null $id_element_concerne L'ID de l'élément affecté (optionnel).
 * @param string|null $type_element_concerne Le type de l'élément affecté (optionnel).
 * @param array|null $details_supplementaires Données additionnelles à stocker en JSON (optionnel).
 * @param int|null $id_admin_actionneur ID de l'admin effectuant l'action (si null, essaie de le prendre de la session).
 * @return bool True si succès, false sinon.
 */
function log_action_application(PDO $pdo, string $type_action, string $description_action, ?int $id_element_concerne = null, ?string $type_element_concerne = null, ?array $details_supplementaires = null, ?int $id_admin_actionneur = null): bool {
    try {
        // S'assurer que la table existe avant d'insérer
        if ($pdo->query("SHOW TABLES LIKE 'historique_actions'")->rowCount() == 0) {
            error_log("La table 'historique_actions' est manquante. Impossible de logger l'action: $type_action");
            return false; // Ne pas logger si la table n'existe pas pour éviter une erreur fatale.
        }

        $id_utilisateur_pour_log = $id_admin_actionneur ?? ($_SESSION['admin_id'] ?? null);
        $type_utilisateur_pour_log = ($id_utilisateur_pour_log !== null) ? 'admin' : 'systeme'; // Si pas d'ID admin, c'est une action système

        $sql = "INSERT INTO historique_actions 
                    (type_action, description_action, id_utilisateur_action, type_utilisateur_action, id_element_concerne, type_element_concerne, details_supplementaires) 
                VALUES 
                    (:type_action, :description_action, :id_utilisateur_action, :type_utilisateur_action, :id_element_concerne, :type_element_concerne, :details_supplementaires)";
        
        $stmt = $pdo->prepare($sql);
        
        $params_log = [
            ':type_action' => $type_action,
            ':description_action' => $description_action,
            ':id_utilisateur_action' => $id_utilisateur_pour_log,
            ':type_utilisateur_action' => $type_utilisateur_pour_log,
            ':id_element_concerne' => $id_element_concerne,
            ':type_element_concerne' => $type_element_concerne,
            ':details_supplementaires' => $details_supplementaires ? json_encode($details_supplementaires) : null
        ];
        
        return $stmt->execute($params_log);

    } catch (PDOException $e) {
        error_log("Erreur de journalisation d'action ($type_action): " . $e->getMessage());
        return false;
    }
}
?>