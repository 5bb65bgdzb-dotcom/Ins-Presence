<?php
/**
 * Profil utilisateur
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

// Vérifier l'authentification
if (!Auth::isAuthenticated() || !Auth::checkSessionTimeout()) {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

$user = Auth::getCurrentUser();
$error = '';
$success = '';

// Traiter le changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs sont requis.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            // Vérifier le mot de passe actuel
            $sql = "SELECT password_user FROM utilisateurs WHERE id_user = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!password_verify($current_password, $result['password_user'])) {
                $error = 'Le mot de passe actuel est incorrect.';
            } else {
                // Mettre à jour le mot de passe
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                $updateSql = "UPDATE utilisateurs SET password_user = ? WHERE id_user = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('si', $new_hash, $user['id']);
                
                if ($updateStmt->execute()) {
                    $success = 'Mot de passe changé avec succès.';
                } else {
                    $error = MESSAGES['error_occurred'];
                }
                $updateStmt->close();
            }
            $stmt->close();
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les informations d'agent si l'utilisateur est un agent
$agent_info = null;
if ($user['role'] === 'employee') {
    $sql = "SELECT * FROM agents WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $agent_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Récupérer les presences récentes
$recent_presences = [];
if ($agent_info) {
    $sql = "SELECT * FROM presences WHERE agent_id = ? ORDER BY date_presence DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $agent_info['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_presences[] = $row;
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?php echo SITE_NAME; ?></title>
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
        
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 5px;
        }
        
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        h1 {
            margin-bottom: 30px;
            color: #333;
        }
        
        h2 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 20px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-item label {
            font-weight: 600;
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item p {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-present {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-conge {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-maladie {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-retard {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-demi_jour {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
        <a href="dashboard.php">← Retour</a>
    </nav>
    
    <div class="container">
        <div class="card">
            <h1>Mon Profil</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <h2>Informations Personnelles</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <label>Nom d'utilisateur</label>
                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($user['email'] ?? '-'); ?></p>
                </div>
                <div class="info-item">
                    <label>Nom Complet</label>
                    <p><?php echo htmlspecialchars($user['nom_complet'] ?: 'Non défini'); ?></p>
                </div>
                <div class="info-item">
                    <label>Rôle</label>
                    <p><?php echo ucfirst(ROLES[$user['role']]['nom']); ?></p>
                </div>
            </div>
            
            <?php if ($agent_info): ?>
                <h2>Informations d'Agent</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Numéro d'Agent</label>
                        <p><?php echo htmlspecialchars($agent_info['matricule']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Département</label>
                        <p><?php echo htmlspecialchars($agent_info['direction_id'] ?: '-'); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Poste</label>
                        <p><?php echo htmlspecialchars($agent_info['division_id'] ?: '-'); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Date d'embauche</label>
                        <p><?php echo $agent_info['date_embauche'] ? date('d/m/Y', strtotime($agent_info['date_embauche'])) : '-'; ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Changer le Mot de Passe</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <button type="submit">Changer le Mot de Passe</button>
            </form>
        </div>
        
        <?php if ($agent_info && !empty($recent_presences)): ?>
            <div class="card">
                <h2>Mes Dernières Presences</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_presences as $presence): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?></td>
                                <td><?php echo htmlspecialchars($presence['heure_entree'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($presence['heure_sortie'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $presence['statut']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $presence['statut'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
