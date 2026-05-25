# Guide de Déploiement - INS-Presence

## Structure de Répertoires Requise

Votre application doit être organisée comme suit sur votre serveur XAMPP :

```
C:\xampp\htdocs\insPresenceStage\
├── config.php                    ← Racine
├── db.php                        ← Racine
├── auth.php                      ← Racine
├── index.php                     ← Racine
├── connexion.php                 ← Racine
├── dashboard.php                 ← Racine
├── ajouter.php                   ← Racine
├── error_access_denied.php       ← Racine
├── schema.sql                    ← Racine
└── pages/                        ← Créer ce dossier
    ├── gerer_utilisateurs.php    ← Fichiers de gestion
    ├── gerer_agents.php
    ├── modifier_agent.php
    ├── modifier.php
    ├── supprimer.php
    ├── supprimer_agent.php
    ├── mon_profil.php
    ├── rapports.php
    └── export.php
```

## Étapes de Déploiement

1. **Créer le dossier `pages/`** dans votre répertoire racine
2. **Placer les fichiers** racine (config.php, db.php, auth.php, etc.) à la racine
3. **Placer les fichiers** de gestion dans le dossier `pages/`
4. **Vérifier les permissions** (les fichiers doivent être lisibles par le serveur)
5. **Tester** en accédant à http://localhost/insPresenceStage/connexion.php

## Chemins d'Inclusion Automatiquement Configurés

- **Fichiers racine** : utilisent `require_once 'config.php'`
- **Fichiers pages/** : utilisent `require_once '../config.php'`

Les redirections sont aussi configurées :
- De **pages/** vers **racine** : `BASE_URL . 'connexion.php'`
- De **racine** vers **pages/** : `BASE_URL . 'pages/gerer_agents.php'`

## Noms de Colonnes Base de Données

Les noms de colonnes suivants doivent correspondre au schéma.sql :

### Table `utilisateurs`
- `id` (INT, PRIMARY KEY)
- `username` (VARCHAR 50)
- `email` (VARCHAR 100)
- `password_hash` (VARCHAR 255) ← **Attention: pas `password_user`**
- `role` (ENUM: admin, manager, employee) ← **Attention: pas `roles`**
- `status` (ENUM: actif, inactif, suspendu) ← **Attention: pas `statut`**

### Table `agents`
- `id` (INT, PRIMARY KEY)
- `numero_agent` (VARCHAR 20) ← **Attention: pas `matricule`**
- `nom`, `prenom`, `email`, `telephone`, `departement`, `poste`
- `status` (ENUM) ← **Attention: pas `statut`**
- `user_id` (INT, FOREIGN KEY)

### Table `presences`
- `id` (INT, PRIMARY KEY)
- `agent_id` (INT, FOREIGN KEY)
- `date_presence` (DATE)
- `heure_arrivee` (TIME) ← **Attention: pas `heure_entree`**
- `heure_depart` (TIME) ← **Attention: pas `heure_sortie`**
- `statut` (ENUM)
- `observations` (VARCHAR) ← **Attention: pas `observation`**

## Vérification

Après le déploiement, vérifiez que :

1. La page de connexion s'ouvre : http://localhost/insPresenceStage/connexion.php
2. Aucune erreur de `require_once` n'apparaît
3. La base de données est accessible avec les bonnes colonnes
4. Les redirections fonctionnent (après connexion → dashboard.php)

## Dépannage

### Erreur: "Failed to open stream: No such file or directory"
→ Vérifiez que le fichier est dans le bon dossier (racine ou pages/)
→ Vérifiez les chemins d'inclusion avec `require_once`

### Erreur: "Column not found"
→ Vérifiez les noms de colonnes dans la base de données
→ Comparez avec le schéma.sql fourni

### Erreur de permission
→ Assurez-vous que IIS ou Apache a les permissions de lecture
→ Vérifiez les droits d'accès aux fichiers
