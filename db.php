<?php
/**
 * Fichier de connexion à la base de données
 * Utilise MySQLi avec connexion sécurisée
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inspresence');

// Créer la connexion MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Vérifier la connexion
if ($conn->connect_error) {
    // Log l'erreur en production, affiche un message sécurisé
    error_log("Erreur de connexion BD: " . $conn->connect_error);
    die("Une erreur de connexion s'est produite. Veuillez réessayer plus tard.");
}

// Définir le charset UTF-8
if (!$conn->set_charset("utf8mb4")) {
    error_log("Erreur lors du paramétrage du charset: " . $conn->error);
}

// Configuration MySQLi pour la sécurité
$conn->set_charset('utf8mb4');

// Activer les rapports d'erreur en développement (désactiver en production)
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

// Fonction pour préparer les requêtes de manière sécurisée
function prepareStatement($conn, $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erreur de préparation: " . $conn->error);
        return false;
    }
    return $stmt;
}

// Fonction pour exécuter une requête sécurisée
function executeQuery($conn, $sql, $params = [], $types = '') {
    $stmt = prepareStatement($conn, $sql);
    if (!$stmt) return false;
    
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Erreur d'exécution: " . $stmt->error);
        return false;
    }
    
    return $stmt;
}

?>
