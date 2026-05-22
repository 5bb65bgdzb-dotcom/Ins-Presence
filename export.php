<?php
/**
 * Page d'exportation des données
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

// Vérifier l'authentification
if (!Auth::isAuthenticated() || !Auth::checkSessionTimeout()) {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

// Vérifier la permission de vue des rapports
if (!Auth::hasPermission('view_reports')) {
    Auth::accessDenied();
}

$user = Auth::getCurrentUser();
$rapportType = $_GET['type'] ?? 'daily';
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');
$agentId = $_GET['agent_id'] ?? null;
$exportFormat = $_GET['format'] ?? 'csv';

$rapportData = [];
$title = '';
$filename = '';

// Générer le rapport selon le type
if ($rapportType === 'daily') {
    $title = 'Rapport Quotidien';
    $filename = 'rapport_quotidien_' . $dateDebut . '_' . $dateFin;
    
    $sql = "SELECT 
                p.id, 
                p.date_presence, 
                a.matricule, 
                a.nom, 
                a.prenom, 
                p.heure_entree, 
                p.heure_sortie, 
                p.statut, 
                p.observation
            FROM presences p
            JOIN agents a ON p.agent_id = a.id
            WHERE p.date_presence BETWEEN ? AND ?";
    
    $params = [$dateDebut, $dateFin];
    
    if ($agentId) {
        $sql .= " AND a.id = ?";
        $params[] = $agentId;
    }
    
    $sql .= " ORDER BY p.date_presence DESC, a.nom, a.prenom";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rapportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($rapportType === 'absences') {
    $title = 'Rapport des Absences';
    $filename = 'rapport_absences_' . $dateDebut . '_' . $dateFin;
    
    $sql = "SELECT 
                p.id, 
                p.date_presence, 
                a.matricule, 
                a.nom, 
                a.prenom, 
                p.statut, 
                p.observation
            FROM presences p
            JOIN agents a ON p.agent_id = a.id
            WHERE p.date_presence BETWEEN ? AND ? AND p.statut = 'absence'";
    
    $params = [$dateDebut, $dateFin];
    
    if ($agentId) {
        $sql .= " AND a.id = ?";
        $params[] = $agentId;
    }
    
    $sql .= " ORDER BY p.date_presence DESC, a.nom, a.prenom";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rapportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($rapportType === 'retards') {
    $title = 'Rapport des Retards';
    $filename = 'rapport_retards_' . $dateDebut . '_' . $dateFin;
    
    $sql = "SELECT 
                p.id, 
                p.date_presence, 
                a.matricule, 
                a.nom, 
                a.prenom, 
                p.heure_entree, 
                p.retard_minutes, 
                p.observation
            FROM presences p
            JOIN agents a ON p.agent_id = a.id
            WHERE p.date_presence BETWEEN ? AND ? AND p.statut = 'retard'";
    
    $params = [$dateDebut, $dateFin];
    
    if ($agentId) {
        $sql .= " AND a.id = ?";
        $params[] = $agentId;
    }
    
    $sql .= " ORDER BY p.date_presence DESC, a.nom, a.prenom";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rapportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} elseif ($rapportType === 'statistiques') {
    $title = 'Statistiques des Présences';
    $filename = 'statistiques_presences_' . $dateDebut . '_' . $dateFin;
    
    $sql = "SELECT 
                a.id,
                a.matricule, 
                a.nom, 
                a.prenom,
                COUNT(*) as total_jours,
                SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presences,
                SUM(CASE WHEN p.statut = 'absence' THEN 1 ELSE 0 END) as absences,
                SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as retards,
                ROUND(SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as taux_presence
            FROM agents a
            LEFT JOIN presences p ON a.id = p.agent_id AND p.date_presence BETWEEN ? AND ?
            WHERE a.statut = 'actif'";
    
    $params = [$dateDebut, $dateFin];
    
    if ($agentId) {
        $sql .= " AND a.id = ?";
        $params[] = $agentId;
    }
    
    $sql .= " GROUP BY a.id, a.matricule, a.nom, a.prenom ORDER BY a.nom, a.prenom";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rapportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fonction pour exporter en CSV
function exportCSV($data, $filename, $rapportType) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    if (empty($data)) {
        fputcsv($output, ['Aucune donnée']);
        fclose($output);
        return;
    }
    
    // En-têtes
    if ($rapportType === 'statistiques') {
        $headers = ['Agent', 'Matricule', 'Jours Travaillés', 'Présences', 'Absences', 'Retards', 'Taux de Présence (%)'];
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['nom'] . ' ' . $row['prenom'],
                $row['matricule'],
                $row['total_jours'],
                $row['presences'] ?? 0,
                $row['absences'] ?? 0,
                $row['retards'] ?? 0,
                $row['taux_presence'] ?? 0
            ]);
        }
    } elseif ($rapportType === 'daily') {
        $headers = ['Date', 'Agent', 'Matricule', 'Heure Entrée', 'Heure Sortie', 'Statut', 'Observation'];
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['date_presence'],
                $row['nom'] . ' ' . $row['prenom'],
                $row['matricule'],
                $row['heure_entree'] ?? '-',
                $row['heure_sortie'] ?? '-',
                $row['statut'],
                $row['observation'] ?? '-'
            ]);
        }
    } elseif ($rapportType === 'absences') {
        $headers = ['Date', 'Agent', 'Matricule', 'Observation'];
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['date_presence'],
                $row['nom'] . ' ' . $row['prenom'],
                $row['matricule'],
                $row['observation'] ?? '-'
            ]);
        }
    } elseif ($rapportType === 'retards') {
        $headers = ['Date', 'Agent', 'Matricule', 'Heure Entrée', 'Retard (minutes)', 'Observation'];
        fputcsv($output, $headers);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['date_presence'],
                $row['nom'] . ' ' . $row['prenom'],
                $row['matricule'],
                $row['heure_entree'] ?? '-',
                $row['retard_minutes'] ?? 0,
                $row['observation'] ?? '-'
            ]);
        }
    }
    
    fclose($output);
}

// Vérifier le format d'export
if ($exportFormat === 'csv') {
    exportCSV($rapportData, $filename, $rapportType);
} else {
    // Par défaut, rediriger vers rapports.php
    header('Location: rapports.php?type=' . $rapportType . '&date_debut=' . $dateDebut . '&date_fin=' . $dateFin . '&agent_id=' . $agentId);
}
exit;
?>
