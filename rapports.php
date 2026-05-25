<?php
/**
 * Page de génération de rapports
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

$rapportData = [];
$title = '';

// Récupérer la liste des agents pour le filtre
$agentsList = [];
$result = $conn->query("SELECT id, matricule, nom, prenom FROM agents WHERE statut = 'actif' ORDER BY nom, prenom");
while ($row = $result->fetch_assoc()) {
    $agentsList[] = $row;
}

// Générer le rapport selon le type
if ($rapportType === 'daily') {
    $title = 'Rapport Quotidien';
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
    
    $sql .= " GROUP BY a.id, a.numero_agent, a.nom, a.prenom ORDER BY a.nom, a.prenom";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $rapportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar h2 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid white;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .page-title {
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5568d3;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .rapport-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .rapport-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table thead {
            background-color: #f8f9fa;
        }
        
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #ddd;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-present {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-absence {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-retard {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-mission {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-conge {
            background-color: #e7d4f5;
            color: #5a2d82;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-box .label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .stat-box .value {
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
        <div class="user-info">
            <div>
                <p><strong><?php echo htmlspecialchars($user['nom_complet'] ?: $user['username']); ?></strong></p>
            </div>
            <a href="?logout=1" class="logout-btn">Déconnexion</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-title">
            <h1>📈 Rapports et Statistiques</h1>
            <p>Générez des rapports détaillés sur les présences et absences</p>
        </div>
        
        <!-- Section de filtres -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="type">Type de Rapport</label>
                        <select name="type" id="type" onchange="this.form.submit()">
                            <option value="daily" <?php echo ($rapportType === 'daily') ? 'selected' : ''; ?>>Rapport Quotidien</option>
                            <option value="absences" <?php echo ($rapportType === 'absences') ? 'selected' : ''; ?>>Absences</option>
                            <option value="retards" <?php echo ($rapportType === 'retards') ? 'selected' : ''; ?>>Retards</option>
                            <option value="statistiques" <?php echo ($rapportType === 'statistiques') ? 'selected' : ''; ?>>Statistiques</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_debut">Date Début</label>
                        <input type="date" name="date_debut" id="date_debut" value="<?php echo $dateDebut; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_fin">Date Fin</label>
                        <input type="date" name="date_fin" id="date_fin" value="<?php echo $dateFin; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="agent_id">Agent (Optionnel)</label>
                        <select name="agent_id" id="agent_id">
                            <option value="">Tous les agents</option>
                            <?php foreach ($agentsList as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>" <?php echo ($agentId == $agent['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($agent['nom'] . ' ' . $agent['prenom'] . ' (' . $agent['matricule'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">🔍 Générer Rapport</button>
                    <a href="export.php?type=<?php echo $rapportType; ?>&date_debut=<?php echo $dateDebut; ?>&date_fin=<?php echo $dateFin; ?>&agent_id=<?php echo $agentId; ?>" class="btn btn-secondary">💾 Exporter</a>
                </div>
            </form>
        </div>
        
        <!-- Section du rapport -->
        <?php if (!empty($rapportData)): ?>
            <div class="rapport-section">
                <h2><?php echo $title; ?></h2>
                
                <?php if ($rapportType === 'statistiques'): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Matricule</th>
                                <th>Jours Travaillés</th>
                                <th>Présences</th>
                                <th>Absences</th>
                                <th>Retards</th>
                                <th>Taux de Présence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rapportData as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($row['matricule']); ?></td>
                                    <td><?php echo $row['total_jours']; ?></td>
                                    <td><span class="status-badge status-present"><?php echo $row['presences'] ?? 0; ?></span></td>
                                    <td><span class="status-badge status-absence"><?php echo $row['absences'] ?? 0; ?></span></td>
                                    <td><span class="status-badge status-retard"><?php echo $row['retards'] ?? 0; ?></span></td>
                                    <td><strong><?php echo $row['taux_presence'] ?? 0; ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Matricule</th>
                                <?php if ($rapportType === 'daily'): ?>
                                    <th>Heure Entrée</th>
                                    <th>Heure Sortie</th>
                                    <th>Statut</th>
                                    <th>Observation</th>
                                <?php elseif ($rapportType === 'absences'): ?>
                                    <th>Statut</th>
                                    <th>Observation</th>
                                <?php elseif ($rapportType === 'retards'): ?>
                                    <th>Heure Entrée</th>
                                    <th>Retard (min)</th>
                                    <th>Observation</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rapportData as $row): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($row['date_presence'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($row['matricule']); ?></td>
                                    <?php if ($rapportType === 'daily'): ?>
                                        <td><?php echo $row['heure_entree'] ?? '-'; ?></td>
                                        <td><?php echo $row['heure_sortie'] ?? '-'; ?></td>
                                        <td><span class="status-badge status-<?php echo $row['statut']; ?>"><?php echo ucfirst($row['statut']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['observation'] ?? '-'); ?></td>
                                    <?php elseif ($rapportType === 'absences'): ?>
                                        <td><span class="status-badge status-absence">Absence</span></td>
                                        <td><?php echo htmlspecialchars($row['observation'] ?? '-'); ?></td>
                                    <?php elseif ($rapportType === 'retards'): ?>
                                        <td><?php echo $row['heure_entree'] ?? '-'; ?></td>
                                        <td><?php echo $row['retard_sortie'] ?? 0; ?> min</td>
                                        <td><?php echo htmlspecialchars($row['observation'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rapport-section">
                <div class="empty-message">
                    <p>Aucune donnée trouvée pour les critères sélectionnés.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">← Retour au Tableau de Bord</a>
        </div>
    </div>
</body>
</html>
