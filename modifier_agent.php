<?php
/**
 * Modifier un agent
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
$isAdmin = Auth::hasRole('admin');
$error = '';
$success = '';
$agent = null;

$agent_id = intval($_GET['id'] ?? 0);
if ($agent_id <= 0) {
    die('Agent invalide.');
}

$sql = "SELECT a.*, u.username AS owner_username FROM agents a LEFT JOIN utilisateurs u ON a.user_id = u.id_user WHERE a.user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $agent_id);
$stmt->execute();
$result = $stmt->get_result();
$agent = $result->fetch_assoc();
$stmt->close();

if (!$agent) {
    die('Agent introuvable.');
}

if (!$isAdmin && intval($agent['user_id']) !== $user['id']) {
    Auth::accessDenied();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_agent') {
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
        $statut = $_POST['statut'] ?? 'actif';

        if (empty($numero_agent) || empty($nom) || empty($prenom)) {
            $error = 'Les champs requis doivent être remplis.';
        } else {
            $checkSql = "SELECT id FROM agents WHERE numero_agent = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('si', $numero_agent, $agent_id);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $error = 'Un agent avec ce numéro existe déjà.';
            } else {
                $updateSql = "UPDATE agents SET numero_agent = ?, nom = ?, prenom = ?, email = ?, telephone = ?, departement = ?, poste = ?, date_embauche = ?, statut = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('sssssssssi', $numero_agent, $nom, $prenom, $email, $telephone, $departement, $poste, $date_embauche, $statut, $agent_id);
                    if ($updateStmt->execute()) {
                        $success = 'Agent mis à jour avec succès.';
                        // Rafraîchir les données de l'agent
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('i', $agent_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $agent = $result->fetch_assoc();
                        $stmt->close();
                    } else {
                        $error = MESSAGES['error_occurred'];
                    }
                    $updateStmt->close();
                }
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
    <title>Modifier Agent - <?php echo SITE_NAME; ?></title>
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
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"],
        input[type="email"],
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
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
        <a href="gerer_agents.php">← Retour aux Agents</a>
    </nav>
    <div class="container">
        <div class="card">
            <h1>Modifier l'agent</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="update_agent">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="numero_agent">Numéro Agent *</label>
                        <input type="text" id="numero_agent" name="numero_agent" required value="<?php echo htmlspecialchars($agent['numero_agent']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($agent['nom']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($agent['prenom']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($agent['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="text" id="telephone" name="telephone" value="<?php echo htmlspecialchars($agent['telephone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="departement">Département</label>
                        <input type="text" id="departement" name="departement" value="<?php echo htmlspecialchars($agent['departement']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="poste">Poste</label>
                        <input type="text" id="poste" name="poste" value="<?php echo htmlspecialchars($agent['poste']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_embauche">Date d'embauche</label>
                        <input type="date" id="date_embauche" name="date_embauche" value="<?php echo htmlspecialchars($agent['date_embauche']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut">
                            <option value="actif" <?php echo ($agent['statut'] === 'actif' ? 'selected' : ''); ?>>Actif</option>
                            <option value="inactif" <?php echo ($agent['statut'] === 'inactif' ? 'selected' : ''); ?>>Inactif</option>
                            <option value="conge" <?php echo ($agent['statut'] === 'conge' ? 'selected' : ''); ?>>Congé</option>
                        </select>
                    </div>
                </div>
                <button type="submit">Enregistrer les modifications</button>
            </form>
        </div>
    </div>
</body>
</html>
