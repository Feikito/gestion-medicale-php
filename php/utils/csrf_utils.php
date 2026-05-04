<?php
// php/utils/csrf_utils.php

/**
 * S'assure que la session est démarrée.
 * Doit être appelé avant toute sortie ou manipulation de $_SESSION.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

/**
 * Génère un token CSRF et le stocke en session s'il n'existe pas déjà,
 * ou retourne le token existant.
 *
 * @return string Le token CSRF.
 * @throws Exception Si random_bytes() échoue (très rare).
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("ERREUR CRITIQUE CSRF: Impossible de générer random_bytes pour le token. " . $e->getMessage());
            die("Erreur critique de sécurité du site (CSRF token generation failed). Veuillez contacter l'administrateur.");
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un token CSRF soumis par rapport à celui stocké en session.
 *
 * @param string|null $submitted_token Le token reçu du formulaire.
 * @param bool $invalidate_after_use Si true, le token sera invalidé (supprimé de la session)
 *                                   après une validation réussie, le rendant à usage unique.
 *                                   Recommandé pour les actions très sensibles.
 * @return bool True si le token est valide, false sinon.
 */
function validate_csrf_token(?string $submitted_token, bool $invalidate_after_use = false): bool {
    if (empty($submitted_token) || empty($_SESSION['csrf_token'])) {
        error_log("Validation CSRF échouée: Token soumis ou token de session manquant.");
        return false; 
    }

    $is_valid = hash_equals($_SESSION['csrf_token'], $submitted_token);

    if ($is_valid && $invalidate_after_use) {
        unset($_SESSION['csrf_token']); 
    }
    
    if (!$is_valid) {
        // Ne pas logger les tokens eux-mêmes ici pour éviter de remplir les logs avec des données sensibles
        // en cas de tentative d'attaque, mais signaler l'échec.
        error_log("Validation CSRF échouée: Incompatibilité des tokens.");
    }
    
    return $is_valid;
}

/**
 * Génère le HTML pour un champ input hidden contenant le token CSRF actuel.
 * Doit être appelée à l'intérieur d'une balise <form>.
 *
 * @return string Le HTML de l'input hidden avec le token CSRF.
 */
function csrf_input_field(): string {
    $token = generate_csrf_token(); 
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Invalide (supprime) le token CSRF actuel de la session.
 * Utile pour forcer la régénération lors de la prochaine demande de token.
 */
function invalidate_csrf_token(): void {
    unset($_SESSION['csrf_token']);
}

/**
 * Régénère le token CSRF. L'ancien est invalidé et un nouveau est créé.
 * Bonne pratique après login, logout, changement de mot de passe.
 * @return string Le nouveau token CSRF.
 */
function regenerate_csrf_token(): string {
    invalidate_csrf_token(); 
    return generate_csrf_token(); 
}
?>