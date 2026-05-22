<?php
/**
 * Page de modification de présence
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

// Récupérer les presences non modifiées
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

// Traiter la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $presence_id = intval($_POST['presence_id'] ?? 0);
        $heure_entree = $_POST['heure_entree'] ?? null;
        $heure_sortie = $_POST['heure_sortie'] ?? null;
        $statut = $_POST['statut'] ?? 'present';
        $observation = $_POST['observation'] ?? '';
        
        if ($presence_id <= 0) {
            $error = 'Présence invalide.';
        } else {
            // Récupérer l'ancienne valeur pour l'audit
            $oldSql = "SELECT * FROM presences WHERE id = ?";
            $oldStmt = $conn->prepare($oldSql);
            $oldStmt->bind_param('i', $presence_id);
            $oldStmt->execute();
            $oldData = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            
            if (!$oldData) {
                $error = 'Présence introuvable.';
            } else {
                $sql = "UPDATE presences 
                        SET heure_entree = ?, heure_sortie = ?, statut = ?, observation = ? 
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('ssssi', $heure_entree, $heure_sortie, $statut, $observation, $presence_id);
                    
                    if ($stmt->execute()) {
                        $success = MESSAGES['record_updated'];
                        
                        // Enregistrer l'audit si la table existe
                        $hasAuditTable = false;
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            $hasAuditTable = true;
                        }
                        if ($hasAuditTable) {
                            $auditSql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) 
                                        VALUES (?, 'UPDATE', 'presences', ?, ?, ?)";
                            $auditStmt = $conn->prepare($auditSql);
                            if ($auditStmt) {
                                $oldJson = json_encode($oldData);
                                $newJson = json_encode(['heure_entree' => $heure_entree, 'heure_sortie' => $heure_sortie, 'statut' => $statut, 'observation' => $observation]);
                                $auditStmt->bind_param('iiss', $user['id'], $presence_id, $oldJson, $newJson);
                                $auditStmt->execute();
                            }
                        }
                        
                        // Rafraîchir la liste
                        $result = $conn->query($sql = "SELECT p.*, a.matricule AS numero_agent, a.nom, a.prenom 
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
    <title>Modifier Présence - <?php echo SITE_NAME; ?></title>
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
        
        .edit-btn {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .edit-btn:hover {
            background-color: #764ba2;
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
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 500px;
            max-width: 90%;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }
        
        button {
            background-color: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #764ba2;
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
            <h1>Modifier une Présence</h1>
            
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
                        <th>Arrivée</th>
                        <th>Départ</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presences as $presence): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($presence['numero_agent'] . ' - ' . $presence['nom'] . ' ' . $presence['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($presence['date_presence']); ?></td>
                            <td><?php echo htmlspecialchars($presence['heure_entree'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($presence['heure_sortie'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $presence['statut']))); ?></td>
                            <td>
                                <button class="edit-btn" onclick="openModal(<?php echo $presence['id']; ?>, '<?php echo htmlspecialchars($presence['heure_entree']); ?>', '<?php echo htmlspecialchars($presence['heure_sortie']); ?>', '<?php echo htmlspecialchars($presence['statut']); ?>', '<?php echo htmlspecialchars($presence['observation']); ?>')">Modifier</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Modifier Présence</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="presence_id" name="presence_id">
                
                <div class="form-group">
                    <label for="heure_entree">Heure d'entrée</label>
                    <input type="time" id="heure_entree" name="heure_entree">
                </div>
                
                <div class="form-group">
                    <label for="heure_sortie">Heure de sortie</label>
                    <input type="time" id="heure_sortie" name="heure_sortie">
                </div>
                
                <div class="form-group">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut">
                        <option value="present">Présent</option>
                        <option value="absent">Absent</option>
                        <option value="conge">Congé</option>
                        <option value="maladie">Maladie</option>
                        <option value="retard">Retard</option>
                        <option value="demi_jour">Demi-journée</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="observation">Observation</label>
                    <textarea id="observation" name="observation"></textarea>
                </div>
                
                <button type="submit">Enregistrer</button>
                <button type="button" onclick="closeModal()" style="background-color: #6c757d;">Annuler</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(id, arrivee, depart, statut, observation) {
            document.getElementById('presence_id').value = id;
            document.getElementById('heure_entree').value = arrivee;
            document.getElementById('heure_sortie').value = depart;
            document.getElementById('statut').value = statut;
            document.getElementById('observation').value = observation;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
