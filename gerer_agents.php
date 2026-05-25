<?php
/**
 * Gestion des agents
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

// Vérifier l'authentification et la permission
if (!Auth::isAuthenticated() || !Auth::checkSessionTimeout()) {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

if (!Auth::hasPermission('manage_agents')) {
    Auth::accessDenied();
}

$user = Auth::getCurrentUser();
$error = '';
$success = '';
$agents = [];
$users = [];
$directions = [];
$divisions = [];
$isAdmin = Auth::hasRole('admin');

// Récupérer les directions
$result = $conn->query("SELECT id, nom FROM directions ORDER BY nom");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $directions[] = $row;
    }
}

// Récupérer les divisions
$result = $conn->query("SELECT id, nom FROM divisions ORDER BY nom");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
}

// Récupérer les utilisateurs pour affectation des agents
if ($isAdmin) {
    $usersResult = $conn->query("SELECT username, username FROM utilisateurs ORDER BY username");
    if ($usersResult) {
        while ($row = $usersResult->fetch_assoc()) {
            $users[] = $row;
        }
    }
} else {
    $users[] = [
        'id' => $user['id'],
        'username' => $user['username'],
    ];
}

// Récupérer les agents avec noms des directions et divisions
if ($isAdmin) {
    $result = $conn->query("SELECT a.id, a.matricule, a.nom, a.prenom, a.email, a.telephone, a.direction_id, a.division_id, a.statut, a.user_id, u.username AS owner_username, d.nom AS direction_nom, divs.nom AS division_nom FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user LEFT JOIN directions d ON a.direction_id = d.id LEFT JOIN divisions divs ON a.division_id = divs.id ORDER BY a.nom, a.prenom");
} else {
    $stmt = $conn->prepare("SELECT a.id, a.matricule, a.nom, a.prenom, a.email, a.telephone, a.direction_id, a.division_id, a.statut, a.user_id, u.username AS owner_username, d.nom AS direction_nom, divs.nom AS division_nom FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user LEFT JOIN directions d ON a.direction_id = d.id LEFT JOIN divisions divs ON a.division_id = divs.id WHERE a.user_id = ? ORDER BY a.nom, a.prenom");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $agents[] = $row;
    }
}

// Traiter l'ajout d'un agent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_agent') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $numero_agent = trim($_POST['matricule'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $direction_id = intval($_POST['direction_id'] ?? 0);
        $division_id = intval($_POST['division_id'] ?? 0);
        $date_embauche = $_POST['date_embauche'] ?? null;
        $owner_user_id = $isAdmin ? intval($_POST['user_id'] ?? 0) : $user['id'];
        
        if (empty($numero_agent) || empty($nom) || empty($prenom)) {
            $error = 'Les champs requis doivent être remplis.';
        } else {
            // Vérifier si l'agent existe déjà
            $checkSql = "SELECT user_id FROM agents WHERE matricule = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('s', $numero_agent);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Cet agent existe déjà.';
            } else {
                if ($owner_user_id > 0) {
                    $sql = "INSERT INTO agents (user_id, matricule, nom, prenom, email, telephone, direction_id, division_id, date_embauche, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')";
                } else {
                    $sql = "INSERT INTO agents (matricule, nom, prenom, email, telephone, direction_id, division_id, date_embauche, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'actif')";
                }
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($owner_user_id > 0) {
                        $stmt->bind_param('isssssiis', $owner_user_id, $numero_agent, $nom, $prenom, $email, $telephone, $direction_id, $division_id, $date_embauche);
                    } else {
                        $stmt->bind_param('sssssiis', $numero_agent, $nom, $prenom, $email, $telephone, $direction_id, $division_id, $date_embauche);
                    }
                    
                    if ($stmt->execute()) {
                        $success = 'Agent créé avec succès.';
                        if ($isAdmin) {
                            $result = $conn->query("SELECT a.id, a.matricule, a.nom, a.prenom, a.email, a.telephone, a.direction_id, a.division_id, a.statut, a.user_id, u.username AS owner_username, d.nom AS direction_nom, divs.nom AS division_nom FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user LEFT JOIN directions d ON a.direction_id = d.id LEFT JOIN divisions divs ON a.division_id = divs.id ORDER BY a.nom, a.prenom");
                        } else {
                            $refreshStmt = $conn->prepare("SELECT a.id, a.matricule, a.nom, a.prenom, a.email, a.telephone, a.direction_id, a.division_id, a.statut, a.user_id, u.username AS owner_username, d.nom AS direction_nom, divs.nom AS division_nom FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user LEFT JOIN directions d ON a.direction_id = d.id LEFT JOIN divisions divs ON a.division_id = divs.id WHERE a.user_id = ? ORDER BY a.nom, a.prenom");
                            if ($refreshStmt) {
                                $refreshStmt->bind_param('i', $user['id']);
                                $refreshStmt->execute();
                                $result = $refreshStmt->get_result();
                            } else {
                                $result = false;
                            }
                        }
                        $agents = [];
                        while ($row = $result->fetch_assoc()) {
                            $agents[] = $row;
                        }
                    } else {
                        $error = MESSAGES['error_occurred'];
                    }
                    $stmt->close();
                }
            }
            $checkStmt->close();
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer Agents - <?php echo SITE_NAME; ?></title>
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
            max-width: 1200px;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
            font-size: 13px;
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
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-actif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactif {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            color: white;
            margin-right: 6px;
        }

        .edit-btn {
            background-color: #3498db;
        }

        .delete-btn {
            background-color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .form-grid {
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
            <h1>Créer un Nouvel Agent</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_agent">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="numero_agent">Numéro Agent *</label>
                        <input type="text" id="numero_agent" name="matricule" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone">
                    </div>
                    
                    <div class="form-group">
                        <label for="direction_id">Direction</label>
                        <select id="direction_id" name="direction_id">
                            <option value="">-- Sélectionner une direction --</option>
                            <?php foreach ($directions as $direction): ?>
                                <option value="<?php echo intval($direction['id']); ?>"><?php echo htmlspecialchars($direction['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="division_id">Division</label>
                        <select id="division_id" name="division_id">
                            <option value="">-- Sélectionner une division --</option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?php echo intval($division['id']); ?>"><?php echo htmlspecialchars($division['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_embauche">Date d'embauche</label>
                        <input type="date" id="date_embauche" name="date_embauche">
                    </div>
                    
                    <?php if ($isAdmin): ?>
                    <div class="form-group">
                        <label for="user_id">Utilisateur responsable</label>
                        <select id="user_id" name="user_id">
                            <option value="">Aucun</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['username']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    
                <?php endif; ?>
                </div>
                
                <button type="submit">Créer Agent</button>
            </form>
        </div>
        
        <div class="card">
            <h1><?php echo $isAdmin ? 'Liste des Agents' : 'Mes Agents'; ?></h1>
            
            <table>
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Direction</th>
                        <th>Division</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['matricule']); ?></td>
                            <td><?php echo htmlspecialchars($a['nom']); ?></td>
                            <td><?php echo htmlspecialchars($a['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($a['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['direction_nom'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['division_nom'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['owner_username'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $a['statut']; ?>">
                                    <?php echo ucfirst($a['statut']); ?>
                                </span>
                            </td>
                            <td>
                                <a class="action-btn edit-btn" href="modifier_agent.php?id=<?php echo $a['id']; ?>">Modifier</a>
                                <a class="action-btn delete-btn" href="supprimer_agent.php?id=<?php echo $a['id']; ?>">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
