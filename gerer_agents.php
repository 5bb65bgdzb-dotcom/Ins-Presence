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

if (!Auth::hasPermission('manage_users')) {
    Auth::accessDenied();
}

$user = Auth::getCurrentUser();
$error = '';
$success = '';
$agents = [];
$users = [];

// Récupérer les utilisateurs pour affectation des agents
$usersResult = $conn->query("SELECT id_user AS id, username FROM utilisateurs ORDER BY username");
if ($usersResult) {
    while ($row = $usersResult->fetch_assoc()) {
        $users[] = $row;
    }
}

// Récupérer les agents
$result = $conn->query("SELECT a.id, a.matricule AS numero_agent, a.nom, a.prenom, a.email, a.telephone, a.statut AS status, a.user_id, u.username AS owner_username FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user ORDER BY a.nom, a.prenom");
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
        $numero_agent = trim($_POST['numero_agent'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $departement = trim($_POST['departement'] ?? '');
        $poste = trim($_POST['poste'] ?? '');
        $date_embauche = $_POST['date_embauche'] ?? null;
        $owner_user_id = intval($_POST['user_id'] ?? 0);
        
        if (empty($numero_agent) || empty($nom) || empty($prenom)) {
            $error = 'Les champs requis doivent être remplis.';
        } else {
            // Vérifier si l'agent existe déjà
            $checkSql = "SELECT id FROM agents WHERE matricule = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('s', $numero_agent);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Cet agent existe déjà.';
            } else {
                if ($owner_user_id > 0) {
                    $sql = "INSERT INTO agents (user_id, matricule, nom, prenom, email, telephone, date_embauche, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'actif')";
                } else {
                    $sql = "INSERT INTO agents (matricule, nom, prenom, email, telephone, date_embauche, statut) 
                            VALUES (?, ?, ?, ?, ?, ?, 'actif')";
                }
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($owner_user_id > 0) {
                        $stmt->bind_param('issssss', $owner_user_id, $numero_agent, $nom, $prenom, $email, $telephone, $date_embauche);
                    } else {
                        $stmt->bind_param('ssssss', $numero_agent, $nom, $prenom, $email, $telephone, $date_embauche);
                    }
                    
                    if ($stmt->execute()) {
                        $success = 'Agent créé avec succès.';
                        // Rafraîchir
                        $result = $conn->query("SELECT a.id, a.matricule AS numero_agent, a.nom, a.prenom, a.email, a.telephone, a.statut AS status, a.user_id, u.username AS owner_username FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user ORDER BY a.nom, a.prenom");
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
                        <input type="text" id="numero_agent" name="numero_agent" required>
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
                        <label for="departement">Département</label>
                        <input type="text" id="departement" name="departement">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="poste">Poste</label>
                        <input type="text" id="poste" name="poste">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_embauche">Date d'embauche</label>
                        <input type="date" id="date_embauche" name="date_embauche">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">Utilisateur responsable</label>
                        <select id="user_id" name="user_id">
                            <option value="">Aucun</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit">Créer Agent</button>
            </form>
        </div>
        
        <div class="card">
            <h1>Liste des Agents</h1>
            
            <table>
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Département</th>
                        <th>Poste</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['numero_agent']); ?></td>
                            <td><?php echo htmlspecialchars($a['nom']); ?></td>
                            <td><?php echo htmlspecialchars($a['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($a['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['departement'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['poste'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($a['owner_username'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $a['status']; ?>">
                                    <?php echo ucfirst($a['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
