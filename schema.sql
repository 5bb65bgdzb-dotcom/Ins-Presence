-- Schéma de la base de données inspresence
-- Créer la base de données
CREATE DATABASE IF NOT EXISTS inspresence;
USE inspresence;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nom_complet VARCHAR(100) NOT NULL,
  role ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
  status ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_role (role)
);

-- Table des agents (employés)
CREATE TABLE IF NOT EXISTS agents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  numero_agent VARCHAR(20) UNIQUE NOT NULL,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  telephone VARCHAR(20),
  departement VARCHAR(100),
  poste VARCHAR(100),
  date_embauche DATE,
  status ENUM('actif', 'inactif', 'conge') DEFAULT 'actif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
  INDEX idx_numero (numero_agent),
  INDEX idx_status (status),
  INDEX idx_departement (departement)
);

-- Table de présence
CREATE TABLE IF NOT EXISTS presences (
  id INT PRIMARY KEY AUTO_INCREMENT,
  agent_id INT NOT NULL,
  date_presence DATE NOT NULL,
  heure_arrivee TIME,
  heure_depart TIME,
  statut ENUM('present', 'absent', 'conge', 'maladie', 'retard', 'demi_jour') DEFAULT 'present',
  observations VARCHAR(255),
  modifie_par INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  FOREIGN KEY (modifie_par) REFERENCES utilisateurs(id),
  UNIQUE KEY unique_presence (agent_id, date_presence),
  INDEX idx_date (date_presence),
  INDEX idx_statut (statut)
);

-- Table pour enregistrer les actions d'audit
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  table_name VARCHAR(50),
  record_id INT,
  old_value LONGTEXT,
  new_value LONGTEXT,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id),
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
);

-- Table pour enregistrer les tentatives de connexion échouées
CREATE TABLE IF NOT EXISTS failed_login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50),
  ip_address VARCHAR(45),
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_ip (ip_address),
  INDEX idx_time (attempted_at)
);

-- Table pour enregistrer les connexions
CREATE TABLE IF NOT EXISTS login_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  ip_address VARCHAR(45),
  status ENUM('success', 'failed') DEFAULT 'success',
  logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_logged (logged_at)
);

-- Table des congés
CREATE TABLE IF NOT EXISTS conges (
  id INT PRIMARY KEY AUTO_INCREMENT,
  agent_id INT NOT NULL,
  type_conge ENUM('annuel', 'maladie', 'familial', 'non_paye') DEFAULT 'annuel',
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  motif VARCHAR(255),
  statut ENUM('en_attente', 'approuve', 'refuse') DEFAULT 'en_attente',
  approuve_par INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
  FOREIGN KEY (approuve_par) REFERENCES utilisateurs(id),
  INDEX idx_agent (agent_id),
  INDEX idx_statut (statut)
);

-- Table des rapports de présence
CREATE TABLE IF NOT EXISTS rapports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  titre VARCHAR(255) NOT NULL,
  type_rapport VARCHAR(50),
  date_debut DATE,
  date_fin DATE,
  genere_par INT NOT NULL,
  filtres LONGTEXT,
  donnees LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (genere_par) REFERENCES utilisateurs(id),
  INDEX idx_type (type_rapport),
  INDEX idx_date (created_at)
);

-- Insérer un administrateur par défaut
INSERT INTO utilisateurs (username, email, password_hash, nom_complet, role, status) 
VALUES ('admin', 'admin@inspresence.com', '$2y$12$OIx8jULpz3EH4pP0kE1v/.3bQp1ZCKrYm8m5LcX5X1e4Z7X8X2m6W', 'Administrateur', 'admin', 'actif')
ON DUPLICATE KEY UPDATE username=username;

-- Créer des index pour améliorer les performances
CREATE INDEX idx_presence_agent_date ON presences(agent_id, date_presence);
CREATE INDEX idx_conge_agent_dates ON conges(agent_id, date_debut, date_fin);
