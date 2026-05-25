<?php
/**
 * Supprimer un agent
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

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
$agent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agent) {
    die('Agent introuvable.');
}

if (!$isAdmin && intval($agent['user_id']) !== $user['id']) {
    Auth::accessDenied();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_agent') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $deleteSql = "DELETE FROM agents WHERE user_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $agent_id);
            if ($deleteStmt->execute()) {
                header('Location: gerer_agents.php?success=agent_deleted');
                exit;
            } else {
                $error = MESSAGES['error_occurred'];
            }
            $deleteStmt->close();
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
    <title>Supprimer Agent - <?php echo SITE_NAME; ?></title>
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
            margin-bottom: 20px;
            color: #333;
        }
        p {
            margin-bottom: 20px;
            line-height: 1.6;
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
        button,
        .cancel-link {
            display: inline-block;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 5px;
        }
        button {
            background: #e74c3c;
            color: white;
            border: none;
            cursor: pointer;
        }
        .cancel-link {
            background: #bdc3c7;
            color: #2c3e50;
            margin-left: 10px;
        }
        button:hover {
            opacity: 0.95;
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
            <h1>Supprimer l'agent</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <p>Vous êtes sur le point de supprimer l'agent <strong><?php echo htmlspecialchars($agent['numero_agent'] . ' - ' . $agent['nom'] . ' ' . $agent['prenom']); ?></strong>.</p>
            <p>Cette action est irréversible et supprimera également toutes les données de présence liées à cet agent.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="delete_agent">
                <button type="submit">Supprimer définitivement</button>
                <a href="gerer_agents.php" class="cancel-link">Annuler</a>
            </form>
        </div>
    </div>
</body>
</html>
