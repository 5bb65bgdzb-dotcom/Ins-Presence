<?php
/**
 * Page d'ajout de présence
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
    die('Accès refusé. Vous n\'avez pas les permissions nécessaires.');
}

$user = Auth::getCurrentUser();
$error = '';
$success = '';

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité.';
    } else {
        $agent_id = intval($_POST['agent_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $heure_entree = $_POST['heure_entree'] ?? null;
        $heure_sortie = $_POST['heure_sortie'] ?? null;
        $statut = $_POST['statut'] ?? 'present';
        $observation = $_POST['observation'] ?? '';
        
        if ($agent_id <= 0 || empty($date)) {
            $error = 'Veuillez remplir tous les champs requis.';
        } else {
            // Vérifier si l'enregistrement existe déjà
            $checkSql = "SELECT id FROM presences WHERE agent_id = ? AND date_presence = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('is', $agent_id, $date);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                $error = 'Une présence est déjà enregistrée pour ce jour.';
            } else {
                // Insérer la présence
                $sql = "INSERT INTO presences (agent_id, date_presence, heure_entree, heure_sortie, statut, observation) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('isssss', $agent_id, $date, $heure_entree, $heure_sortie, $statut, $observation);
                    
                    if ($stmt->execute()) {
                        $success = MESSAGES['record_added'];
                        // Enregistrer dans les logs d'audit si la table existe
                        $hasAuditTable = false;
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            $hasAuditTable = true;
                        }
                        if ($hasAuditTable) {
                            $auditSql = "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, 'CREATE', 'presences', ?)";
                            $auditStmt = $conn->prepare($auditSql);
                            if ($auditStmt) {
                                $recordId = $conn->insert_id;
                                $auditStmt->bind_param('ii', $user['id'], $recordId);
                                $auditStmt->execute();
                            }
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

// Générer CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer la liste des agents
$agentsResult = $conn->query("SELECT id, matricule AS numero_agent, nom, prenom FROM agents WHERE statut = 'actif' ORDER BY nom, prenom");
$agents = [];
if ($agentsResult) {
    while ($row = $agentsResult->fetch_assoc()) {
        $agents[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Présence - <?php echo SITE_NAME; ?></title>
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
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .form-card {
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
            font-size: 14px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input[type="text"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-back {
            background: #6c757d;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
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
        <div class="form-card">
            <h1>Ajouter une Présence</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="agent_id">Agent *</label>
                    <select id="agent_id" name="agent_id" required>
                        <option value="">-- Sélectionner un agent --</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>">
                                <?php echo htmlspecialchars($agent['numero_agent'] . ' - ' . $agent['nom'] . ' ' . $agent['prenom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="heure_entree">Heure d'entrée</label>
                        <input type="time" id="heure_entree" name="heure_entree">
                    </div>
                    <div class="form-group">
                        <label for="heure_sortie">Heure de sortie</label>
                        <input type="time" id="heure_sortie" name="heure_sortie">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="statut">Statut *</label>
                    <select id="statut" name="statut" required>
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
                    <textarea id="observation" name="observation" rows="4"></textarea>
                </div>
                
                <button type="submit">Ajouter</button>
                <a href="dashboard.php" class="btn-back" style="padding: 12px 30px; display: inline-block; margin-left: 10px;">Annuler</a>
            </form>
        </div>
    </div>
</body>
</html>
