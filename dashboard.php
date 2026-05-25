<?php
/**
 * Tableau de bord principal
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

// Vérifier l'authentification
if (!Auth::isAuthenticated() || !Auth::checkSessionTimeout()) {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

// Vérifier la permission de vue
if (!Auth::hasPermission('view_dashboard')) {
    die('Accès refusé.');
}

// Traiter la déconnexion
if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: connexion.php');
    exit;
}

$user = Auth::getCurrentUser();
$role = $user['role'];

// Récupérer les statistiques
$stats = [];
$selectedAgentId = intval($_GET['agent_id'] ?? 0);
$agentStats = [
    'week' => ['present' => 0, 'absence' => 0, 'conge' => 0],
    'month' => ['present' => 0, 'absence' => 0, 'conge' => 0],
];
$agentsList = [];
$selectedAgentName = '';

// Liste des agents actifs pour la sélection
$agentQuery = "SELECT id, matricule, nom, prenom FROM agents WHERE IFNULL(statut, statut) = 'actif' ORDER BY nom, prenom";
$agentResult = $conn->query($agentQuery);
if ($agentResult) {
    while ($row = $agentResult->fetch_assoc()) {
        $agentsList[] = $row;
    }
}

if ($role === 'admin') {
    // Statistiques pour l'admin
    $result = $conn->query("SELECT COUNT(*) as total FROM agents WHERE IFNULL(statut, statut) = 'actif'");
    $stats['agents_actifs'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE()");
    $stats['presences_today'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND statut = 'present'");
    $stats['presents_today'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND statut = 'absence'");
    $stats['absents_today'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE MONTH(date_presence) = MONTH(CURDATE()) AND YEAR(date_presence) = YEAR(CURDATE()) AND statut = 'present'");
    $stats['presences_month'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE MONTH(date_presence) = MONTH(CURDATE()) AND YEAR(date_presence) = YEAR(CURDATE()) AND statut = 'absence'");
    $stats['absents_month'] = $result ? $result->fetch_assoc()['total'] : 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE MONTH(date_presence) = MONTH(CURDATE()) AND YEAR(date_presence) = YEAR(CURDATE()) AND statut = 'conge'");
    $stats['conges_month'] = $result ? $result->fetch_assoc()['total'] : 0;
} elseif ($role === 'manager') {
    // Statistiques pour le manager (seulement ses propres agents)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agents WHERE user_id = ? AND IFNULL(statut, statut) = 'actif'");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['team_members'] = $result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $stats['team_members'] = 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM presences p INNER JOIN agents a ON p.agent_id = a.id WHERE a.user_id = ? AND p.date_presence = CURDATE() AND p.statut = 'present'");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['presents_today'] = $result ? $result->fetch_assoc()['total'] : 0;
        $stmt->close();
    } else {
        $stats['presents_today'] = 0;
    }
} else {
    // Pour les employés, afficher leur propre présence
    $agent_id = $user['agent_id'];
    $stats['my_total_records'] = 0;
    $stats['my_present_today'] = 0;
    $stats['my_absent_today'] = 0;
    $stats['my_recent_presences'] = [];

    if ($agent_id) {
        $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE agent_id = " . intval($agent_id));
        $stats['my_total_records'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE agent_id = " . intval($agent_id) . " AND date_presence = CURDATE() AND statut = 'present'");
        $stats['my_present_today'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE agent_id = " . intval($agent_id) . " AND date_presence = CURDATE() AND statut = 'absence'");
        $stats['my_absent_today'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT * FROM presences WHERE agent_id = " . intval($agent_id) . " ORDER BY date_presence DESC LIMIT 10");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stats['my_recent_presences'][] = $row;
            }
        }
    }
}

if ($selectedAgentId > 0) {
    $selectedAgentName = '';
    foreach ($agentsList as $agentRow) {
        if (intval($agentRow['id']) === $selectedAgentId) {
            $selectedAgentName = trim(($agentRow['nom'] ?? '') . ' ' . ($agentRow['prenom'] ?? '')) ?: $agentRow['matricule'];
            break;
        }
    }

    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');

    $stmt = $conn->prepare("SELECT statut, COUNT(*) AS total FROM presences WHERE agent_id = ? AND date_presence BETWEEN ? AND ? GROUP BY statut");
    if ($stmt) {
        $stmt->bind_param('iss', $selectedAgentId, $startOfWeek, $endOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $agentStats['week'][$row['statut']] = intval($row['total']);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT statut, COUNT(*) AS total FROM presences WHERE agent_id = ? AND date_presence BETWEEN ? AND ? GROUP BY statut");
    if ($stmt) {
        $stmt->bind_param('iss', $selectedAgentId, $startOfMonth, $endOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $agentStats['month'][$row['statut']] = intval($row['total']);
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?php echo SITE_NAME; ?></title>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar h2 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info p {
            font-size: 14px;
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
            display: flex;
            height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #95a5a6;
            margin: 20px 0 10px 0;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 16px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        
        .sidebar a:hover,
        .sidebar a.active {
            background-color: #667eea;
        }
        
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }
        
        .page-title {
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .page-title p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card.present .number {
            color: #27ae60;
        }
        
        .stat-card.absent .number {
            color: #e74c3c;
        }
        
        .welcome-message {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .welcome-message h2 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .agent-filter {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-top: 15px;
        }

        .agent-filter label {
            font-size: 14px;
            color: #444;
        }

        .agent-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 220px;
            font-size: 14px;
        }

        .agent-filter button {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 18px;
            cursor: pointer;
            font-weight: 600;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                max-height: 200px;
                display: flex;
                gap: 20px;
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
        <div class="user-info">
            <div>
                <p><strong><?php echo htmlspecialchars($user['nom_complet'] ?: $user['username']); ?></strong></p>
                <p style="font-size: 12px; opacity: 0.8;"><?php echo ucfirst(ROLES[$role]['nom']); ?></p>
            </div>
            <a href="?logout=1" class="logout-btn">Déconnexion</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="sidebar">
            <p class="sidebar-title">Navigation</p>
            <a href="dashboard.php" class="active">📊 Tableau de Bord</a>
            
            <?php if (Auth::hasPermission('manage_attendance')): ?>
                <p class="sidebar-title">Gestion</p>
                <a href="ajouter.php">➕ Ajouter Présence</a>
                <a href="modifier.php">✏️ Modifier Présence</a>
                <a href="supprimer.php">🗑️ Supprimer Présence</a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('manage_users')): ?>
                <p class="sidebar-title">Administration</p>
                <a href="gerer_directions.php">🏢 Gérer Directions</a>
                <a href="gerer_divisions.php">📂 Gérer Divisions</a>
                <a href="gerer_bureaux.php">🏬 Gérer Bureaux</a>
                <a href="gerer_utilisateurs.php">👥 Gérer Utilisateurs</a>
            <?php endif; ?>
            <?php if (Auth::hasPermission('manage_agents')): ?>
                <p class="sidebar-title">Administration</p>
                <a href="gerer_agents.php">👤 Gérer Agents</a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('view_reports')): ?>
                <p class="sidebar-title">Rapports</p>
                <a href="rapports.php">📈 Rapports</a>
                <a href="export.php">💾 Exporter Données</a>
            <?php endif; ?>
            
            <p class="sidebar-title">Profil</p>
            <a href="mon_profil.php">⚙️ Mon Espace Personnel</a>
        </div>
        
        <div class="main-content">
            <div class="page-title">
                <h1>Tableau de Bord</h1>
                <p><?php echo date('l d F Y', time()); ?> à <?php echo date('H:i'); ?></p>
            </div>
            
            <div class="welcome-message">
                <h2>Bienvenue, <?php echo htmlspecialchars($user['nom_complet'] ?: $user['username']); ?>!</h2>
                <p>Vous êtes connecté en tant que <strong><?php echo ucfirst(ROLES[$role]['nom']); ?></strong></p>
            </div>
            
            <?php if ($role === 'admin'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Agents Actifs</h3>
                        <div class="number"><?php echo $stats['agents_actifs'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Présences Aujourd'hui</h3>
                        <div class="number"><?php echo $stats['presences_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card present">
                        <h3>Présents</h3>
                        <div class="number"><?php echo $stats['presents_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card absent">
                        <h3>Absents Aujourd'hui</h3>
                        <div class="number"><?php echo $stats['absents_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Présences Mensuelles</h3>
                        <div class="number"><?php echo $stats['presences_month'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card absent">
                        <h3>Absents ce Mois</h3>
                        <div class="number"><?php echo $stats['absents_month'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Congés ce Mois</h3>
                        <div class="number"><?php echo $stats['conges_month'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="welcome-message">
                    <h3>Voir les présences d'un agent</h3>
                    <form class="agent-filter" method="get">
                        <label for="agent_id">Sélectionner un agent</label>
                        <select id="agent_id" name="agent_id">
                            <option value="">-- Choisir un agent --</option>
                            <?php foreach ($agentsList as $agent): ?>
                                <option value="<?php echo intval($agent['id']); ?>" <?php echo $selectedAgentId === intval($agent['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($agent['nom'] . ' ' . $agent['prenom'])); ?> (<?php echo htmlspecialchars($agent['matricule']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Afficher</button>
                    </form>
                </div>
                <?php if ($selectedAgentId && $selectedAgentName): ?>
                    <div class="detail-grid">
                        <div class="stat-card present">
                            <h3>Présences cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['present'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card absent">
                            <h3>Absences cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['absence'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Congés cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['conge'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card present">
                            <h3>Présences ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['present'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card absent">
                            <h3>Absences ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['absence'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Congés ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['conge'] ?? 0; ?></div>
                        </div>
                    </div>
                <?php elseif ($selectedAgentId): ?>
                    <div class="welcome-message">
                        <p>Aucun agent actif trouvé pour l'ID sélectionné ou aucune présence n'a encore été enregistrée.</p>
                    </div>
                <?php endif; ?>
            <?php elseif ($role === 'manager'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Membres de l'Équipe</h3>
                        <div class="number"><?php echo $stats['team_members'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card present">
                        <h3>Présents Aujourd'hui</h3>
                        <div class="number"><?php echo $stats['presents_today'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="welcome-message">
                    <h3>Voir les présences d'un agent</h3>
                    <form class="agent-filter" method="get">
                        <label for="agent_id">Sélectionner un agent</label>
                        <select id="agent_id" name="agent_id">
                            <option value="">-- Choisir un agent --</option>
                            <?php foreach ($agentsList as $agent): ?>
                                <option value="<?php echo intval($agent['id']); ?>" <?php echo $selectedAgentId === intval($agent['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($agent['nom'] . ' ' . $agent['prenom'])); ?> (<?php echo htmlspecialchars($agent['matricule']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Afficher</button>
                    </form>
                </div>
                <?php if ($selectedAgentId && $selectedAgentName): ?>
                    <div class="detail-grid">
                        <div class="stat-card present">
                            <h3>Présences cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['present'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card absent">
                            <h3>Absences cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['absence'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Congés cette semaine</h3>
                            <div class="number"><?php echo $agentStats['week']['conge'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card present">
                            <h3>Présences ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['present'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card absent">
                            <h3>Absences ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['absence'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Congés ce mois</h3>
                            <div class="number"><?php echo $agentStats['month']['conge'] ?? 0; ?></div>
                        </div>
                    </div>
                <?php elseif ($selectedAgentId): ?>
                    <div class="welcome-message">
                        <p>Aucun agent actif trouvé pour l'ID sélectionné ou aucune présence n'a encore été enregistrée.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Mes enregistrements</h3>
                        <div class="number"><?php echo $stats['my_total_records'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card present">
                        <h3>Présent aujourd'hui</h3>
                        <div class="number"><?php echo $stats['my_present_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card absent">
                        <h3>Absent aujourd'hui</h3>
                        <div class="number"><?php echo $stats['my_absent_today'] ?? 0; ?></div>
                    </div>
                </div>

                <?php if (!empty($stats['my_recent_presences'])): ?>
                    <div class="welcome-message">
                        <h3>Mon Espace Personnel</h3>
                        <p>Voici vos dernières présences enregistrées.</p>
                    </div>
                    <div class="card">
                        <table style="width:100%; border-collapse: collapse; margin-top: 20px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; padding: 12px; border-bottom: 2px solid #ddd;">Date</th>
                                    <th style="text-align:left; padding: 12px; border-bottom: 2px solid #ddd;">Arrivée</th>
                                    <th style="text-align:left; padding: 12px; border-bottom: 2px solid #ddd;">Départ</th>
                                    <th style="text-align:left; padding: 12px; border-bottom: 2px solid #ddd;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['my_recent_presences'] as $presence): ?>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($presence['heure_arrivee'] ?? '-'); ?></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($presence['heure_depart'] ?? '-'); ?></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <?php echo ucfirst(str_replace('_', ' ', $presence['statut'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="welcome-message">
                        <p>Aucun enregistrement de présence trouvé. Vérifiez votre profil ou contactez l'administrateur.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
</body>
</html>
