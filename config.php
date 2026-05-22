<?php
/**
 * Fichier de configuration globale de l'application
 */

// Mode debug (à mettre à false en production)
define('DEBUG_MODE', true);

// Configuration d'URL
define('BASE_URL', 'http://localhost/insPresenceStage/');
define('SITE_NAME', 'INS');

// Configuration de session
define('SESSION_TIMEOUT', 3600); // 1 heure
define('SESSION_NAME', 'insp_session');

// Définir les rôles disponibles
define('ROLES', [
    'admin' => [
        'id' => 1,
        'nom' => 'Administrateur',
        'permissions' => ['view_dashboard', 'manage_users', 'manage_attendance', 'view_reports', 'manage_roles', 'export_data']
    ],
    'manager' => [
        'id' => 2,
        'nom' => 'Gestionnaire',
        'permissions' => ['view_dashboard', 'manage_attendance', 'view_reports', 'export_data']
    ],
    'employee' => [
        'id' => 3,
        'nom' => 'Employé',
        'permissions' => ['view_dashboard', 'view_own_attendance']
    ]
]);

// Pages accessibles sans authentification
define('PUBLIC_PAGES', [
    'connexion',
    'forgot_password'
]);

// Configuration des sessions
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // À mettre à 1 en HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    session_name(SESSION_NAME);
    session_start();
}

// Fuseau horaire
date_default_timezone_set('Africa/Casablanca');

// Les constantes de messages
define('MESSAGES', [
    'login_success' => 'Connexion réussie!',
    'login_failed' => 'Identifiant ou mot de passe incorrect.',
    'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
    'access_denied' => 'Accès refusé. Vous n\'avez pas les permissions nécessaires.',
    'record_added' => 'Enregistrement ajouté avec succès.',
    'record_updated' => 'Enregistrement modifié avec succès.',
    'record_deleted' => 'Enregistrement supprimé avec succès.',
    'error_occurred' => 'Une erreur s\'est produite. Veuillez réessayer.',
]);

?>
