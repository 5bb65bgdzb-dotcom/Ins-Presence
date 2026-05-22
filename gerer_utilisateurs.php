<?php
/**
 * Gestion des utilisateurs
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
$users = [];

// Récupérer les utilisateurs
$result = $conn->query("SELECT id_user AS id, username, roles AS role, statut AS status FROM utilisateurs ORDER BY id_user DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Traiter l'ajout d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        
        if (empty($username) || empty($password)) {
            $error = 'Tous les champs sont requis.';
        } else {
            $result = $auth->createUser([
                'username' => $username,
                'password' => $password,
                'role' => $role
            ]);
            
            if ($result['success']) {
                $success = $result['message'];
                // Rafraîchir la liste
                $result = $conn->query("SELECT id_user AS id, username, roles AS role, statut AS status FROM utilisateurs ORDER BY id_user DESC");
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Traiter la modification du statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $user_id = intval($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? 'actif';
        
        $sql = "UPDATE utilisateurs SET statut = ? WHERE id_user = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $status, $user_id);
            if ($stmt->execute()) {
                $success = 'Statut mise à jour avec succès.';
                // Rafraîchir
                $result = $conn->query("SELECT id_user AS id, username, roles AS role, statut AS status FROM utilisateurs ORDER BY id_user DESC");
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            } else {
                $error = MESSAGES['error_occurred'];
            }
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
    <title>Gérer Utilisateurs - <?php echo SITE_NAME; ?></title>
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
            grid-template-columns: repeat(2, 1fr);
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
        input[type="password"],
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
        }
        
        .status-actif {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactif {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-suspendu {
            background-color: #fff3cd;
            color: #856404;
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
            <h1>Créer un Nouvel Utilisateur</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" required>
                            <option value="employee">Employé</option>
                            <option value="manager">Gestionnaire</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nom_complet">Nom Complet</label>
                    <input type="text" id="nom_complet" name="nom_complet">
                </div>
                
                <button type="submit">Créer Utilisateur</button>
            </form>
        </div>
        
        <div class="card">
            <h1>Liste des Utilisateurs</h1>
            
            <table>
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo ucfirst($u['role']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $u['status']; ?>">
                                    <?php echo ucfirst($u['status']); ?>
                                </span>
                            </td>
                            <td>--</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
