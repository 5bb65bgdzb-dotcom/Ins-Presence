<?php
/**
 * Page d'accueil (redirection)
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Si authentifié, aller au dashboard
if (Auth::isAuthenticated()) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
}
exit;

?>
