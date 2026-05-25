<?php
/**
 * Système d'authentification et d'autorisation
 */

require_once 'config.php';
require_once 'db.php';

class Auth {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Vérifier si l'utilisateur est authentifié
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
    
    /**
     * Vérifier si l'utilisateur a une permission
     */
    public static function hasPermission($permission) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        $roles = ROLES;
        
        if (!isset($roles[$role])) {
            return false;
        }
        
        return in_array($permission, $roles[$role]['permissions']);
    }
    
    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public static function hasRole($requiredRole) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        if (is_array($requiredRole)) {
            return in_array($_SESSION['user_role'], $requiredRole);
        }
        
        return $_SESSION['user_role'] === $requiredRole;
    }
    
    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['user_role'],
            'nom_complet' => $_SESSION['nom_complet'] ?? $_SESSION['username'],
            'agent_id' => $_SESSION['agent_id'] ?? null,
            'agent_matricule' => $_SESSION['agent_matricule'] ?? null,
            'derniere_action' => $_SESSION['last_activity'] ?? null
        ];
    }
    
    /**
     * Rediriger vers la page de connexion en cas d'accès refusé
     */
    public static function accessDenied() {
        session_destroy();
        header('Location: ' . BASE_URL . 'pages/connexion.php?error=access_denied');
        exit;
    }
    
    /**
     * Authentifier un utilisateur avec login et mot de passe
     */
    public function login($username, $password) {
        // Valider les entrées
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Identifiant et mot de passe requis.'];
        }
        
        // Préparer la requête en utilisant les colonnes existantes dans la base de données
        $idColumn = $this->getUtilisateurColumnName('id_user');
        $passwordColumn = $this->getUtilisateurColumnName('password_user');
        $roleColumn = $this->getUtilisateurColumnName('roles');
        $statusColumn = $this->getUtilisateurColumnName('statut');

        $sql = "SELECT {$idColumn} AS id, username, {$passwordColumn} AS password_hash, {$roleColumn} AS roles, {$statusColumn} AS statut 
                FROM utilisateurs 
                WHERE username = ?
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Erreur de préparation: " . $this->conn->error);
            return ['success' => false, 'message' => MESSAGES['error_occurred']];
        }
        
        $stmt->bind_param('s', $username);
        
        if (!$stmt->execute()) {
            error_log("Erreur d'exécution: " . $stmt->error);
            return ['success' => false, 'message' => MESSAGES['error_occurred']];
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Enregistrer la tentative échouée
            $this->logFailedAttempt($username);
            return ['success' => false, 'message' => MESSAGES['login_failed']];
        }
        
        $user = $result->fetch_assoc();
        
        // Vérifier si l'utilisateur est actif
        if ($user['statut'] !== 'actif') {
            return ['success' => false, 'message' => 'Votre compte est désactivé.'];
        }
        
        // Vérifier le mot de passe
        $passwordHash = $user['password_hash'];
        $passwordVerified = false;

        if (password_verify($password, $passwordHash)) {
            $passwordVerified = true;
        } elseif ($password === $passwordHash) {
            $passwordVerified = true;
            // Mise à niveau du mot de passe en cas de stockage en clair
            if (password_get_info($passwordHash)['algo'] === 0) {
                $this->upgradePlainPassword($user['id'], $password);
            }
        }

        if (!$passwordVerified) {
            $this->logFailedAttempt($username);
            return ['success' => false, 'message' => MESSAGES['login_failed']];
        }
        
        // Définir un rôle par défaut si la colonne est vide
        $role = !empty($user['roles']) ? $user['roles'] : 'employee';
        
        // Récréer l'ID de session pour éviter la fixation
        session_regenerate_id(true);
        
        // Stocker les données de l'utilisateur en session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $role;
        $_SESSION['nom_complet'] = $user['username'];
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at'] = time();
        
        // Associer l'utilisateur à son agent si c'est un employé
        if ($role === 'employee') {
            $agentSql = "SELECT id, matricule, nom, prenom FROM agents WHERE user_id = ? LIMIT 1";
            $agentStmt = $this->conn->prepare($agentSql);
            if ($agentStmt) {
                $agentStmt->bind_param('i', $user['id']);
                $agentStmt->execute();
                $agent = $agentStmt->get_result()->fetch_assoc();
                if ($agent) {
                    $_SESSION['agent_id'] = $agent['id'];
                    $_SESSION['agent_matricule'] = $agent['matricule'];
                    $_SESSION['nom_complet'] = trim($agent['nom'] . ' ' . $agent['prenom']);
                }
                $agentStmt->close();
            }
        }
        
        // Mettre à jour la date de dernière connexion
        $this->updateLastLogin($user['id']);
        
        // Enregistrer la connexion réussie
        $this->logLogin($user['id'], 'success');
        
        $stmt->close();
        
        return ['success' => true, 'message' => MESSAGES['login_success']];
    }
    
    /**
     * Déconnecter l'utilisateur
     */
    public static function logout() {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(SESSION_NAME, '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Vérifier si la session a expiré
     */
    public static function checkSessionTimeout() {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Enregistrer une tentative de connexion échouée
     */
    private function logFailedAttempt($username) {
        if (!$this->tableExists('failed_login_attempts')) {
            return;
        }

        $sql = "INSERT INTO failed_login_attempts (username, ip_address, attempted_at) 
                VALUES (?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->bind_param('ss', $username, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Enregistrer une connexion
     */
    private function logLogin($userId, $status) {
        if (!$this->tableExists('login_logs')) {
            return;
        }

        $sql = "INSERT INTO login_logs (user_id, ip_address, status, logged_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->bind_param('iss', $userId, $ip, $status);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Mettre à jour la date de dernière connexion
     */
    private function updateLastLogin($userId) {
        $columns = ['last_login', 'derniere_connexion'];
        $idColumn = $this->getUtilisateurColumnName('id');

        foreach ($columns as $column) {
            if ($this->columnExists('utilisateurs', $column)) {
                $sql = "UPDATE utilisateurs SET $column = NOW() WHERE {$idColumn} = ?";
                $stmt = $this->conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $stmt->close();
                }
                return;
            }
        }
    }
    
    private function columnExists($table, $column) {
        $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function tableExists($table) {
        $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function getUtilisateurColumnName($logicalName) {
        $mapping = [
            'id' => ['id', 'id'],
            'password_user' => ['password_hash', 'password_hash'],
            'role' => ['role', 'roles'],
            'statut' => ['statut', 'statut'],
        ];

        if (!isset($mapping[$logicalName])) {
            return $logicalName;
        }

        foreach ($mapping[$logicalName] as $column) {
            if ($this->columnExists('utilisateurs', $column)) {
                return $column;
            }
        }

        return $logicalName;
    }

    private function upgradePlainPassword($userId, $password) {
        $passwordColumn = $this->getUtilisateurColumnName('password');
        $idColumn = $this->getUtilisateurColumnName('id');
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = "UPDATE utilisateurs SET {$passwordColumn} = ? WHERE {$idColumn} = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Créer un nouvel utilisateur (Admin uniquement)
     */
    public function createUser($data) {
        // Valider les données
        if (empty($data['username']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Tous les champs requis doivent être remplis.'];
        }
        
        // Vérifier si l'utilisateur existe déjà
        $idColumn = $this->getUtilisateurColumnName('id');
        $passwordColumn = $this->getUtilisateurColumnName('password');
        $roleColumn = $this->getUtilisateurColumnName('role');
        $statusColumn = $this->getUtilisateurColumnName('statut');

        $checkSql = "SELECT {$idColumn} FROM utilisateurs WHERE username = ?";
        $checkStmt = $this->conn->prepare($checkSql);
        if (!$checkStmt) {
            return ['success' => false, 'message' => MESSAGES['error_occurred']];
        }
        
        $checkStmt->bind_param('s', $data['username']);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Cet utilisateur existe déjà.'];
        }
        $checkStmt->close();
        
        // Hasher le mot de passe
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insérer le nouvel utilisateur
        $sql = "INSERT INTO utilisateurs (username, {$passwordColumn}, {$roleColumn}, {$statusColumn}) 
                VALUES (?, ?, ?, 'actif')";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'message' => MESSAGES['error_occurred']];
        }
        
        $role = $data['role'] ?? 'employee';
        
        $stmt->bind_param('sss', $data['username'], $passwordHash, $role);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Utilisateur créé avec succès.', 'user_id' => $this->conn->insert_id];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => MESSAGES['error_occurred']];
        }
    }
}

// Créer une instance globale
$auth = new Auth($conn);

?>
