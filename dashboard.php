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

$user = Auth::getCurrentUser();
$role = $user['role'];

// Récupérer les statistiques
$stats = [];

if ($role === 'admin') {
    // Statistiques pour l'admin
    $result = $conn->query("SELECT COUNT(*) as total FROM agents WHERE statut = 'actif'");
    $stats['agents_actifs'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE()");
    $stats['presences_today'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND statut = 'present'");
    $stats['presents_today'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND statut = 'absence'");
    $stats['absents_today'] = $result->fetch_assoc()['total'];
} elseif ($role === 'manager') {
    // Statistiques pour le manager
    $result = $conn->query("SELECT COUNT(*) as total FROM agents");
    $stats['team_members'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM presences WHERE date_presence = CURDATE() AND statut = 'present'");
    $stats['presents_today'] = $result->fetch_assoc()['total'];
} else {
    // Pour les employés, afficher leur propre présence
    $agent_id = null;
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
                <a href="gerer_utilisateurs.php">👥 Gérer Utilisateurs</a>
                <a href="gerer_agents.php">👤 Gérer Agents</a>
            <?php endif; ?>
            
            <?php if (Auth::hasPermission('view_reports')): ?>
                <p class="sidebar-title">Rapports</p>
                <a href="rapports.php">📈 Rapports</a>
                <a href="export.php">💾 Exporter Données</a>
            <?php endif; ?>
            
            <p class="sidebar-title">Profil</p>
            <a href="mon_profil.php">⚙️ Mon Profil</a>
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
                        <h3>Presences Aujourd'hui</h3>
                        <div class="number"><?php echo $stats['presences_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card present">
                        <h3>Présents</h3>
                        <div class="number"><?php echo $stats['presents_today'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card absent">
                        <h3>Absents</h3>
                        <div class="number"><?php echo $stats['absents_today'] ?? 0; ?></div>
                    </div>
                </div>
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
            <?php else: ?>
                <div class="welcome-message">
                    <p>Consultez votre présence en cliquant sur "Mon Profil".</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Traiter la déconnexion
    if (isset($_GET['logout'])) {
        Auth::logout();
        header('Location: connexion.php');
        exit;
    }
    ?>
</body>
</html>
