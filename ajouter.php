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
$isAdmin = Auth::hasRole('admin');
$error = '';
$success = '';

// AJAX: Récupérer la liste des agents en attente de sortie du jour
if (isset($_GET['action']) && $_GET['action'] === 'get_today_arrivals') {
    header('Content-Type: application/json');
    
    $date = date('Y-m-d');
    
    if ($isAdmin) {
        $query = "SELECT p.id, p.agent_id, p.heure_entree, a.matricule, a.nom, a.prenom 
                  FROM presences p 
                  JOIN agents a ON p.agent_id = a.id 
                  WHERE p.date_presence = ? AND p.heure_sortie IS NULL 
                  ORDER BY p.heure_entree ASC";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
        }
    } else {
        // Pour les managers, filtrer par user_id
        $query = "SELECT p.id, p.agent_id, p.heure_entree, a.matricule, a.nom, a.prenom 
                  FROM presences p 
                  JOIN agents a ON p.agent_id = a.id 
                  WHERE p.date_presence = ? AND p.heure_sortie IS NULL AND a.user_id = ? 
                  ORDER BY p.heure_entree ASC";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('si', $date, $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
        }
    }
    
    if ($result) {
        $arrivals = [];
        while ($row = $result->fetch_assoc()) {
            $arrivals[] = $row;
        }
        
        echo json_encode(['success' => true, 'arrivals' => $arrivals]);
        if ($stmt) $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

// AJAX: Récupérer les infos d'un agent par matricule
if (isset($_GET['action']) && $_GET['action'] === 'get_agent') {
    header('Content-Type: application/json');
    
    $matricule = trim($_GET['matricule'] ?? '');
    if (empty($matricule)) {
        echo json_encode(['success' => false, 'message' => 'Matricule requis']);
        exit;
    }
    
    $query = "SELECT id, matricule, nom, prenom, direction_id, division_id, user_id FROM agents WHERE matricule = ? AND statut = 'actif'";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('s', $matricule);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $agent = $result->fetch_assoc();
            
            // Vérifier les permissions
            if (!$isAdmin && $agent['user_id'] && $agent['user_id'] != $user['id']) {
                echo json_encode(['success' => false, 'message' => 'Accès refusé à cet agent']);
                $stmt->close();
                exit;
            }
            
            // Vérifier si une présence est déjà enregistrée aujourd'hui
            $date = date('Y-m-d');
            $checkSql = "SELECT id, heure_sortie FROM presences WHERE agent_id = ? AND date_presence = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param('is', $agent['id'], $date);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            $presence = null;
            $presence_status = 'new'; // 'new' ou 'existing'
            
            if ($checkResult && $checkResult->num_rows > 0) {
                $presence = $checkResult->fetch_assoc();
                $presence_status = $presence['heure_sortie'] ? 'completed' : 'in_progress';
            }
            $checkStmt->close();
            
            echo json_encode([
                'success' => true,
                'agent' => $agent,
                'presence_status' => $presence_status,
                'presence_data' => $presence,
                'current_time' => date('H:i')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Agent non trouvé']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

// AJAX: Enregistrer l'heure d'arrivée
if (isset($_POST['action']) && $_POST['action'] === 'save_arrival') {
    header('Content-Type: application/json');
    
    $agent_id = intval($_POST['agent_id'] ?? 0);
    $heure_arrivee = $_POST['heure_arrivee'] ?? date('H:i');
    $date = date('Y-m-d');
    
    if ($agent_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Agent invalide']);
        exit;
    }
    
    // Vérifier les permissions (si pas admin, l'agent doit appartenir à l'utilisateur)
    if (!$isAdmin) {
        $permCheckSql = "SELECT id FROM agents WHERE id = ? AND user_id = ?";
        $permCheckStmt = $conn->prepare($permCheckSql);
        if ($permCheckStmt) {
            $permCheckStmt->bind_param('ii', $agent_id, $user['id']);
            $permCheckStmt->execute();
            if ($permCheckStmt->get_result()->num_rows === 0) {
                $permCheckStmt->close();
                echo json_encode(['success' => false, 'message' => 'Accès refusé à cet agent']);
                exit;
            }
            $permCheckStmt->close();
        }
    }
    
    // Vérifier si une présence existe déjà pour cet agent aujourd'hui
    $checkSql = "SELECT id FROM presences WHERE agent_id = ? AND date_presence = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param('is', $agent_id, $date);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult && $checkResult->num_rows > 0) {
            $checkStmt->close();
            echo json_encode(['success' => false, 'message' => 'Une présence existe déjà pour cet agent aujourd\'hui']);
            exit;
        }
        $checkStmt->close();
    }
    
    // Insérer la présence avec l'heure d'arrivée
    $insertSql = "INSERT INTO presences (agent_id, date_presence, heure_entree, statut) VALUES (?, ?, ?, 'present')";
    $insertStmt = $conn->prepare($insertSql);
    if ($insertStmt) {
        $insertStmt->bind_param('iss', $agent_id, $date, $heure_arrivee);
        if ($insertStmt->execute()) {
            // Sauvegarder aussi dans la session
            if (!isset($_SESSION['presence_temp'])) {
                $_SESSION['presence_temp'] = [];
            }
            $_SESSION['presence_temp']['agent_id'] = $agent_id;
            $_SESSION['presence_temp']['heure_arrivee'] = $heure_arrivee;
            $_SESSION['presence_temp']['date'] = $date;
            
            echo json_encode(['success' => true, 'message' => 'Heure d\'arrivée enregistrée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
        }
        $insertStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

// AJAX: Enregistrer l'heure de sortie et finalize la présence
if (isset($_POST['action']) && $_POST['action'] === 'save_departure') {
    header('Content-Type: application/json');
    
    $agent_id = intval($_POST['agent_id'] ?? 0);
    $heure_sortie = $_POST['heure_sortie'] ?? date('H:i');
    $statut = $_POST['statut'] ?? 'present';
    $observation = $_POST['observation'] ?? '';
    
    if ($agent_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Agent invalide']);
        exit;
    }
    
    $date = date('Y-m-d');
    
    // Récupérer ou créer l'enregistrement de présence
    $checkSql = "SELECT id FROM presences WHERE agent_id = ? AND date_presence = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('is', $agent_id, $date);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    $heure_arrivee = date('H:i');
    if (isset($_SESSION['presence_temp']['heure_arrivee'])) {
        $heure_arrivee = $_SESSION['presence_temp']['heure_arrivee'];
    }
    
    if ($checkResult->num_rows > 0) {
        // Mettre à jour l'enregistrement existant
        $updateSql = "UPDATE presences SET heure_sortie = ?, statut = ?, observation = ? WHERE agent_id = ? AND date_presence = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param('sssis', $heure_sortie, $statut, $observation, $agent_id, $date);
            if ($updateStmt->execute()) {
                unset($_SESSION['presence_temp']);
                echo json_encode(['success' => true, 'message' => 'Présence enregistrée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            $updateStmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    } else {
        // Créer un nouvel enregistrement
        $insertSql = "INSERT INTO presences (agent_id, date_presence, heure_entree, heure_sortie, statut, observation) VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param('isssss', $agent_id, $date, $heure_arrivee, $heure_sortie, $statut, $observation);
            if ($insertStmt->execute()) {
                unset($_SESSION['presence_temp']);
                echo json_encode(['success' => true, 'message' => 'Présence enregistrée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'insertion']);
            }
            $insertStmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }
    exit;
}

// Générer CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        
        .info-display {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .info-display.active {
            display: block;
        }
        
        .agent-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #667eea;
        }
        
        .info-value {
            color: #333;
        }
        
        .step-indicator {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            color: #667eea;
        }
        
        .hidden {
            display: none;
        }
        
        .info-display {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .info-display.active {
            display: block;
        }
        
        .agent-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #667eea;
        }
        
        .info-value {
            color: #333;
        }
        
        .step-indicator {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            color: #667eea;
        }
        
        .hidden {
            display: none;
        }
        
        .success-message {
            color: #155724;
            font-weight: 600;
        }
        
        .arrivals-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border: 2px solid #e9ecef;
        }
        
        .arrivals-list h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
        
        .arrival-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .arrival-info {
            font-size: 13px;
        }
        
        .arrival-time {
            text-align: center;
            font-weight: 600;
            color: #667eea;
        }
        
        .btn-departure {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        
        .btn-departure:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
        }
        
        .no-arrivals {
            text-align: center;
            color: #999;
            padding: 20px;
            font-style: italic;
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
            <h1>Enregistrer une Présence</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- ÉTAPE 1 : Saisie du Matricule -->
            <div id="step1" class="step1-container">
                <div class="step-indicator">Étape 1 : Saisie du Matricule de l'Agent (Arrivée)</div>
                
                <div class="form-group">
                    <label for="matricule">Matricule de l'Agent *</label>
                    <input type="text" id="matricule" name="matricule" placeholder="Saisir le matricule" required autofocus>
                </div>
                
                <!-- Affichage des infos de l'agent -->
                <div id="agent-info-box" class="info-display">
                    <div class="agent-info" id="agent-details"></div>
                    <button type="button" id="btn-confirm-agent" class="btn-confirm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                        Enregistrer l'Arrivée
                    </button>
                </div>
            </div>
            
            <!-- LISTE DES AGENTS EN ATTENTE DE SORTIE -->
            <div class="arrivals-list" id="arrivals-container">
                <h3>📋 Agents en attente de sortie</h3>
                <div id="arrivals-list-content">
                    <div class="no-arrivals">Aucun agent enregistré pour le moment</div>
                </div>
            </div>
            
            <!-- FORMULAIRE MODAL POUR SORTIE -->
            <div id="departure-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000;">
                <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); max-width: 400px; width: 90%;">
                    <h2 style="margin-bottom: 20px;">Valider la Sortie</h2>
                    
                    <div id="departure-agent-info" style="background: #f0f4ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>
                    
                    <form id="departure-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="save_departure">
                        <input type="hidden" id="departure-agent-id" name="agent_id">
                        <input type="hidden" id="departure-arrival-time" name="arrival_time">
                        
                        <div class="form-group">
                            <label>Heure d'entrée</label>
                            <input type="time" id="display-heure-entree" disabled style="background: #f5f5f5;">
                        </div>
                        
                        <div class="form-group">
                            <label for="heure_sortie">Heure de sortie *</label>
                            <input type="time" id="heure_sortie" name="heure_sortie" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="statut-departure">Statut *</label>
                            <select id="statut-departure" name="statut" required>
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
                            <textarea id="observation" name="observation" rows="3"></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="submit" id="btn-submit-departure" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                Valider
                            </button>
                            <button type="button" id="btn-close-modal" style="background: #6c757d; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <a href="dashboard.php" class="btn-back" style="padding: 12px 30px; display: inline-block; margin-top: 20px;">← Accueil</a>
        </div>
    </div>
    
    <script>
        // Éléments DOM
        const matriculeInput = document.getElementById('matricule');
        const agentInfoBox = document.getElementById('agent-info-box');
        const agentDetails = document.getElementById('agent-details');
        const btnConfirmAgent = document.getElementById('btn-confirm-agent');
        const arrivalsListContent = document.getElementById('arrivals-list-content');
        const departureModal = document.getElementById('departure-modal');
        const departureForm = document.getElementById('departure-form');
        const btnCloseModal = document.getElementById('btn-close-modal');
        const btnSubmitDeparture = document.getElementById('btn-submit-departure');
        
        let currentAgent = null;
        
        // Charger la liste des arrivals au chargement de la page
        function loadArrivals() {
            fetch('?action=get_today_arrivals')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayArrivals(data.arrivals);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
        
        // Afficher la liste des arrivals
        function displayArrivals(arrivals) {
            if (arrivals.length === 0) {
                arrivalsListContent.innerHTML = '<div class="no-arrivals">Aucun agent enregistré pour le moment</div>';
                return;
            }
            
            let html = '';
            arrivals.forEach(arrival => {
                const arrivalTime = new Date('2000-01-01 ' + arrival.heure_entree);
                const timeStr = arrivalTime.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                html += `
                    <div class="arrival-item">
                        <div class="arrival-info">
                            <strong>${arrival.matricule}</strong><br>
                            ${arrival.nom} ${arrival.prenom}
                        </div>
                        <div class="arrival-time">
                            Arrivée:<br>${timeStr}
                        </div>
                        <div></div>
                        <button type="button" class="btn-departure" onclick="openDepartureModal(${arrival.agent_id}, '${arrival.heure_entree}', '${arrival.matricule}', '${arrival.nom}', '${arrival.prenom}')">
                            🚪 Sortie
                        </button>
                    </div>
                `;
            });
            
            arrivalsListContent.innerHTML = html;
        }
        
        // Ouvrir le modal de sortie
        function openDepartureModal(agentId, arrivalTime, matricule, nom, prenom) {
            document.getElementById('departure-agent-id').value = agentId;
            document.getElementById('departure-arrival-time').value = arrivalTime;
            document.getElementById('display-heure-entree').value = arrivalTime;
            document.getElementById('heure_sortie').value = new Date().toTimeString().slice(0, 5);
            document.getElementById('observation').value = '';
            document.getElementById('statut-departure').value = 'present';
            
            document.getElementById('departure-agent-info').innerHTML = `
                <strong>${matricule}</strong><br>
                ${nom} ${prenom}<br>
                <small>Arrivée: ${arrivalTime}</small>
            `;
            
            departureModal.style.display = 'flex';
        }
        
        // Fermer le modal
        btnCloseModal.addEventListener('click', () => {
            departureModal.style.display = 'none';
        });
        
        // Charger les infos de l'agent quand on appuie sur Entrée
        matriculeInput.addEventListener('keyup', async (e) => {
            const matricule = matriculeInput.value.trim();
            
            if (matricule.length === 0) {
                agentInfoBox.classList.remove('active');
                return;
            }
            
            try {
                const response = await fetch(`?action=get_agent&matricule=${encodeURIComponent(matricule)}`);
                const data = await response.json();
                
                if (data.success) {
                    currentAgent = data.agent;
                    displayAgentInfo(data);
                    agentInfoBox.classList.add('active');
                } else {
                    agentInfoBox.classList.remove('active');
                }
            } catch (error) {
                console.error('Erreur:', error);
                agentInfoBox.classList.remove('active');
            }
        });
        
        // Afficher les infos de l'agent
        function displayAgentInfo(data) {
            const agent = data.agent;
            agentDetails.innerHTML = `
                <div class="info-item">
                    <span class="info-label">Matricule :</span>
                    <span class="info-value">${agent.matricule}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Nom :</span>
                    <span class="info-value">${agent.nom} ${agent.prenom}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Heure Actuelle :</span>
                    <span class="info-value">${data.current_time}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Statut :</span>
                    <span class="info-value">${data.presence_status === 'new' ? 'Nouvel enregistrement' : data.presence_status === 'in_progress' ? 'Déjà enregistré' : 'Complété'}</span>
                </div>
            `;
        }
        
        // Confirmer l'arrivée
        btnConfirmAgent.addEventListener('click', async () => {
            if (!currentAgent) return;
            
            const formData = new FormData();
            formData.append('action', 'save_arrival');
            formData.append('agent_id', currentAgent.id);
            formData.append('heure_arrivee', new Date().toTimeString().slice(0, 5));
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    // Réinitialiser le formulaire
                    matriculeInput.value = '';
                    agentInfoBox.classList.remove('active');
                    matriculeInput.focus();
                    currentAgent = null;
                    
                    // Recharger la liste des arrivals
                    loadArrivals();
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'enregistrement de l\'arrivée');
            }
        });
        
        // Soumettre l'heure de sortie
        departureForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const heureEntree = document.getElementById('display-heure-entree').value;
            const heureSortie = document.getElementById('heure_sortie').value;
            
            // Valider que les heures sont différentes
            if (heureEntree === heureSortie) {
                alert('L\'heure de sortie doit être différente de l\'heure d\'arrivée !');
                return;
            }
            
            const formData = new FormData(departureForm);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Présence enregistrée avec succès !');
                    departureModal.style.display = 'none';
                    
                    // Recharger la liste
                    loadArrivals();
                } else {
                    alert('Erreur : ' + data.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'enregistrement');
            }
        });
        
        // Charger les arrivals au chargement de la page
        window.addEventListener('load', loadArrivals);
    </script>
</body>
</html>