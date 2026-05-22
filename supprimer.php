<?php
/**
 * Page de suppression de présence
 */

require_once '../config.php';
require_once '../db.php';
require_once '../auth.php';

// Vérifier l'authentification et la permission
if (!Auth::isAuthenticated() || !Auth::checkSessionTimeout()) {
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

if (!Auth::hasPermission('manage_attendance')) {
    Auth::accessDenied();
}

$user = Auth::getCurrentUser();
$error = '';
$success = '';
$presences = [];

// Récupérer les presences
$sql = "SELECT p.*, a.matricule AS numero_agent, a.nom, a.prenom 
        FROM presences p 
        JOIN agents a ON p.agent_id = a.id 
        ORDER BY p.date_presence DESC 
        LIMIT 50";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $presences[] = $row;
    }
}

// Traiter la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $presence_id = intval($_POST['delete_id']);
        
        // Récupérer les données avant suppression (pour l'audit)
        $oldSql = "SELECT * FROM presences WHERE id = ?";
        $oldStmt = $conn->prepare($oldSql);
        $oldStmt->bind_param('i', $presence_id);
        $oldStmt->execute();
        $oldData = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();
        
        if (!$oldData) {
            $error = 'Présence introuvable.';
        } else {
            $sql = "DELETE FROM presences WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param('i', $presence_id);
                
                if ($stmt->execute()) {
                    $success = MESSAGES['record_deleted'];
                    
                    // Enregistrer l'audit si la table existe
                    $hasAuditTable = false;
                    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
                    if ($tableCheck && $tableCheck->num_rows > 0) {
                        $hasAuditTable = true;
                    }
                    if ($hasAuditTable) {
                        $auditSql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value) 
                                    VALUES (?, 'DELETE', 'presences', ?, ?)";
                        $auditStmt = $conn->prepare($auditSql);
                        if ($auditStmt) {
                            $oldJson = json_encode($oldData);
                            $auditStmt->bind_param('iis', $user['id'], $presence_id, $oldJson);
                            $auditStmt->execute();
                        }
                    }
                    
                    // Rafraîchir la liste
                    $result = $conn->query("SELECT p.*, a.matricule AS numero_agent, a.nom, a.prenom 
                            FROM presences p 
                            JOIN agents a ON p.agent_id = a.id 
                            ORDER BY p.date_presence DESC 
                            LIMIT 50");
                    $presences = [];
                    while ($row = $result->fetch_assoc()) {
                        $presences[] = $row;
                    }
                } else {
                    $error = MESSAGES['error_occurred'];
                }
                $stmt->close();
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
    <title>Supprimer Présence - <?php echo SITE_NAME; ?></title>
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
            max-width: 1000px;
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
        
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 400px;
            max-width: 90%;
            text-align: center;
        }
        
        .modal-content h2 {
            margin-bottom: 20px;
            color: #e74c3c;
        }
        
        .modal-content p {
            margin-bottom: 20px;
            color: #666;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-confirm {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-cancel {
            background-color: #95a5a6;
            color: white;
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
            <h1>Supprimer une Présence</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presences as $presence): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($presence['numero_agent'] . ' - ' . $presence['nom'] . ' ' . $presence['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($presence['date_presence']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $presence['statut']))); ?></td>
                            <td>
                                <button class="delete-btn" onclick="openDeleteModal(<?php echo $presence['id']; ?>, '<?php echo htmlspecialchars($presence['numero_agent'] . ' - ' . $presence['nom'] . ' ' . $presence['prenom']); ?>')">Supprimer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Confirmer la Suppression</h2>
            <p id="deleteMessage"></p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="delete_id" name="delete_id">
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-confirm">Supprimer</button>
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openDeleteModal(id, agent) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteMessage').textContent = 'Êtes-vous sûr de vouloir supprimer la présence de ' + agent + ' ?';
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>
</body>
</html>
