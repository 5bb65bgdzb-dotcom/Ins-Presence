# Système de Gestion de Présence - Documentation

## Vue d'ensemble

Cette application web est un système complet de gestion de présence des agents avec un système de sécurité robuste et un contrôle d'accès basé sur les rôles (RBAC).

## Fonctionnalités Principales

### 1. **Sécurité**
- ✅ **Authentification sécurisée** : Login/Mot de passe avec hash BCRYPT
- ✅ **Gestion des sessions** : Timeout automatique après inactivité
- ✅ **CSRF Protection** : Tokens CSRF sur tous les formulaires
- ✅ **Prepared Statements** : Protection contre les injections SQL
- ✅ **Password Hashing** : BCRYPT avec coût 12
- ✅ **Audit Logs** : Enregistrement de toutes les modifications
- ✅ **Failed Login Tracking** : Enregistrement des tentatives échouées

### 2. **Gestion des Rôles et Permissions**

#### **Admin (Administrateur)**
- Voir le tableau de bord complet
- Gérer les utilisateurs (créer, modifier, supprimer)
- Gérer les agents
- Gérer les présences (ajouter, modifier, supprimer)
- Voir les rapports
- Exporter les données
- Accès à l'audit

#### **Manager (Gestionnaire)**
- Voir le tableau de bord avec statistiques
- Gérer les presences de son équipe
- Voir les rapports
- Exporter les données

#### **Employee (Employé)**
- Voir sa propre présence
- Accéder à son profil

### 3. **Modules Principaux**

#### Tableau de Bord (Dashboard)
- Statistiques en temps réel
- Nombre d'agents actifs
- Presences du jour
- Interface adaptée par rôle

#### Gestion des Presences
- **Ajouter** : Enregistrer une nouvelle présence
- **Modifier** : Mettre à jour une présence existante
- **Supprimer** : Retirer une présence de la base de données
- Statuts : Présent, Absent, Congé, Maladie, Retard, Demi-journée

#### Gestion des Utilisateurs (Admin)
- Créer de nouveaux utilisateurs
- Assigner des rôles
- Gérer le statut (actif, inactif, suspendu)
- Voir l'historique de connexion

#### Gestion des Agents
- Créer des fiches d'agent
- Associer à des utilisateurs
- Gérer les informations (département, poste, etc.)

#### Profil Utilisateur
- Voir les informations personnelles
- Changer le mot de passe
- Voir l'historique des presences (pour les agents)

## Installation et Configuration

### Prérequis
- PHP 7.4+
- MySQL 5.7+
- XAMPP ou équivalent

### Étapes d'installation

1. **Créer la base de données**
   - Ouvrir phpMyAdmin (http://localhost/phpmyadmin)
   - Importer le fichier `schema.sql`
   - Ou copier-coller le contenu dans l'onglet SQL

2. **Configurer les accès**
   - Éditer [db.php](db.php)
   - Vérifier les paramètres de connexion MySQL :
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'inspresence');
     ```

3. **Accéder à l'application**
   - URL : `http://localhost/insPresenceStage/`
   - Redirection automatique vers la page de connexion

### Données d'accès par défaut

| Username | Password | Rôle |
|----------|----------|------|
| admin | Demander à l'admin | Admin |

> **Note** : Le mot de passe par défaut est hasher dans la BD. À modifier en production.

## Structure de la Base de Données

### Tables Principales

#### `utilisateurs`
```sql
- id : INT PRIMARY KEY
- username : VARCHAR(50) UNIQUE
- email : VARCHAR(100) UNIQUE
- password_hash : VARCHAR(255)
- nom_complet : VARCHAR(100)
- role : ENUM('admin', 'manager', 'employee')
- status : ENUM('actif', 'inactif', 'suspendu')
- created_at : TIMESTAMP
- last_login : TIMESTAMP
```

#### `agents`
```sql
- id : INT PRIMARY KEY
- user_id : INT (Foreign Key)
- numero_agent : VARCHAR(20) UNIQUE
- nom : VARCHAR(100)
- prenom : VARCHAR(100)
- email : VARCHAR(100)
- telephone : VARCHAR(20)
- departement : VARCHAR(100)
- poste : VARCHAR(100)
- date_embauche : DATE
- status : ENUM('actif', 'inactif', 'conge')
```

#### `presences`
```sql
- id : INT PRIMARY KEY
- agent_id : INT (Foreign Key)
- date_presence : DATE
- heure_arrivee : TIME
- heure_depart : TIME
- statut : ENUM('present', 'absent', 'conge', 'maladie', 'retard', 'demi_jour')
- observations : VARCHAR(255)
- modifie_par : INT (Foreign Key - User ID)
- created_at : TIMESTAMP
- updated_at : TIMESTAMP
```

#### `audit_logs`
```sql
- id : INT PRIMARY KEY
- user_id : INT
- action : VARCHAR(100) (CREATE, UPDATE, DELETE)
- table_name : VARCHAR(50)
- record_id : INT
- old_value : LONGTEXT (JSON)
- new_value : LONGTEXT (JSON)
- ip_address : VARCHAR(45)
- user_agent : VARCHAR(255)
- created_at : TIMESTAMP
```

#### `login_logs`
```sql
- id : INT PRIMARY KEY
- user_id : INT
- ip_address : VARCHAR(45)
- status : ENUM('success', 'failed')
- logged_at : TIMESTAMP
```

#### `failed_login_attempts`
```sql
- id : INT PRIMARY KEY
- username : VARCHAR(50)
- ip_address : VARCHAR(45)
- attempted_at : TIMESTAMP
```

## Structure des Fichiers

```
insPresenceStage/
├── index.php                 # Page d'accueil (redirection)
├── db.php                    # Connexion à la BD
├── config.php                # Configuration globale & rôles
├── auth.php                  # Système d'authentification
├── schema.sql                # Schéma de la BD
├── pages/
│   ├── connexion.php         # Page de login
│   ├── dashboard.php         # Tableau de bord
│   ├── ajouter.php          # Ajouter présence
│   ├── modifier.php         # Modifier présence
│   ├── supprimer.php        # Supprimer présence
│   ├── gerer_utilisateurs.php   # Gestion users
│   ├── gerer_agents.php     # Gestion agents
│   └── mon_profil.php       # Profil utilisateur
└── README.md                # Cette documentation
```

## Flux d'Authentification

```
Utilisateur
    ↓
[Saisir login/password]
    ↓
[Vérifier CSRF Token]
    ↓
[Chercher l'utilisateur en BD]
    ↓
[Vérifier le hash du password]
    ↓
[Vérifier le statut du compte]
    ↓
[Régénérer l'ID de session]
    ↓
[Stocker en session: user_id, role, permissions]
    ↓
[Mettre à jour last_login]
    ↓
[Enregistrer dans login_logs]
    ↓
[Rediriger vers dashboard]
```

## Fonctionnement du Contrôle d'Accès (RBAC)

### Vérifcation des Permissions

```php
// Vérifier si l'utilisateur est authentifié
if (!Auth::isAuthenticated()) {
    // Rediriger vers la connexion
}

// Vérifier une permission spécifique
if (!Auth::hasPermission('manage_attendance')) {
    // Afficher un message d'erreur
}

// Vérifier un rôle
if (!Auth::hasRole('admin')) {
    // Accès refusé
}
```

### Architecture des Permissions

```php
ROLES = [
    'admin' => [
        'permissions' => [
            'view_dashboard',
            'manage_users',
            'manage_attendance',
            'view_reports',
            'manage_roles',
            'export_data'
        ]
    ],
    'manager' => [
        'permissions' => [
            'view_dashboard',
            'manage_attendance',
            'view_reports',
            'export_data'
        ]
    ],
    'employee' => [
        'permissions' => [
            'view_own_attendance'
        ]
    ]
]
```

## Bonnes Pratiques de Sécurité Implémentées

### 1. **Protection contre les Injections SQL**
```php
// ✅ Utilisation de Prepared Statements
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// ❌ À éviter
$result = $conn->query("SELECT * FROM users WHERE id = " . $_GET['id']);
```

### 2. **Protection contre les Attaques XSS**
```php
// ✅ Échappement de la sortie
echo htmlspecialchars($user_input);

// ❌ À éviter
echo $user_input;
```

### 3. **Protection contre les Attaques CSRF**
```php
// ✅ Vérification du token CSRF
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Erreur de sécurité');
}

// Tous les formulaires incluent :
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
```

### 4. **Hachage Sécurisé des Mots de Passe**
```php
// ✅ BCRYPT avec coût 12
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ✅ Vérification
if (password_verify($password, $hash)) {
    // Mot de passe correct
}
```

### 5. **Configuration des Cookies de Session**
```php
ini_set('session.cookie_httponly', 1);    // Pas d'accès JavaScript
ini_set('session.cookie_secure', 0);      // À mettre à 1 en HTTPS
ini_set('session.cookie_samesite', 'Strict'); // Protection CSRF
```

### 6. **Régénération de l'ID de Session**
```php
// ✅ Après login réussi
session_regenerate_id(true);
```

## Utilisation de l'Application

### Pour un Admin

1. **Accéder au tableau de bord**
   - Voir les statistiques globales

2. **Gérer les utilisateurs**
   - Créer des nouveaux comptes
   - Assigner des rôles

3. **Gérer les agents**
   - Créer des fiches d'agent
   - Associer des utilisateurs

4. **Gérer les presences**
   - Ajouter/Modifier/Supprimer

### Pour un Manager

1. **Voir le tableau de bord**
   - Statistiques de son équipe

2. **Gérer les presences**
   - Ajouter/Modifier presences de l'équipe

### Pour un Employé

1. **Accéder à son profil**
   - Voir ses informations
   - Voir son historique de presence
   - Changer son mot de passe

## Support des Navigateurs

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Maintenance et Monitoring

### Vérifier les Logs d'Audit
```sql
SELECT * FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Vérifier les Tentatives de Login Échouées
```sql
SELECT * FROM failed_login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### Réinitialiser un Mot de Passe

```php
// Dans phpMyAdmin ou un script sécurisé
$new_hash = password_hash('nouveau_password', PASSWORD_BCRYPT, ['cost' => 12]);
UPDATE utilisateurs SET password_hash = '$new_hash' WHERE id = 1;
```

## Dépannage

### L'app ne se charge pas
- Vérifier la connexion MySQL
- Vérifier les credentials dans `db.php`
- Vérifier que la BD `inspresence` existe

### Erreur d'authentification
- Vérifier le schéma de la BD
- Vérifier que l'admin user existe
- Vérifier les logs MySQL

### Les sessions expirent trop vite
- Modifier `SESSION_TIMEOUT` dans `config.php`
- Valeur en secondes (3600 = 1 heure)

## Améliorations Futures

- [ ] Implémentation de 2FA
- [ ] API REST
- [ ] Export en PDF/Excel
- [ ] Graphiques avancés
- [ ] Notifications par email
- [ ] Mobile responsive amélioré
- [ ] Backup automatique

## Licence

Propriétaire - Tous droits réservés

## Support

Pour toute question ou bug, contacter l'administrateur système.

---

**Dernière mise à jour :** 22 Mai 2026  
**Version :** 1.0.0
