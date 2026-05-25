<?php
/**
 * Gestion des divisions
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

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
$divisions = [];
$directions = [];

// Récupérer les directions disponibles
$result = $conn->query("SELECT id, nom FROM directions ORDER BY nom");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $directions[] = $row;
    }
}

// Récupérer la liste des divisions
$result = $conn->query("SELECT d.id, d.nom, dir.nom AS direction_nom FROM divisions d LEFT JOIN directions dir ON d.direction_id = dir.id ORDER BY d.nom ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_division') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $direction_id = intval($_POST['direction_id'] ?? 0);

        if (empty($nom) || $direction_id <= 0) {
            $error = 'Le nom de la division et la direction sont requis.';
        } else {
            $stmt = $conn->prepare("INSERT INTO divisions (nom, direction_id) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('si', $nom, $direction_id);
                if ($stmt->execute()) {
                    $success = 'Division créée avec succès.';
                    $divisions = [];
                    $result = $conn->query("SELECT d.id, d.nom, dir.nom AS direction_nom FROM divisions d LEFT JOIN directions dir ON d.direction_id = dir.id ORDER BY d.nom ASC");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $divisions[] = $row;
                        }
                    }
                } else {
                    $error = MESSAGES['error_occurred'];
                }
                $stmt->close();
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
    <title>Gérer Divisions - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; height: 70px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .navbar a { color: white; text-decoration: none; font-size: 14px; background-color: rgba(255, 255, 255, 0.2); padding: 8px 16px; border-radius: 5px; }
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 30px; }
        h1 { margin-bottom: 30px; color: #333; }
        .alert { padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        .alert-danger { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #f8f9fa; border-bottom: 2px solid #ddd; }
        tr:hover { background-color: #f9f9f9; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
        <a href="dashboard.php">← Retour</a>
    </nav>
    <div class="container">
        <div class="card">
            <h1>Créer une Nouvelle Division</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_division">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom">Nom de la division *</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="direction_id">Direction associée *</label>
                        <select id="direction_id" name="direction_id" required>
                            <option value="">-- Choisir une direction --</option>
                            <?php foreach ($directions as $direction): ?>
                                <option value="<?php echo intval($direction['id']); ?>"><?php echo htmlspecialchars($direction['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit">Créer Division</button>
            </form>
        </div>

        <div class="card">
            <h1>Liste des Divisions</h1>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Direction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($divisions as $division): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($division['nom']); ?></td>
                            <td><?php echo htmlspecialchars($division['direction_nom'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
