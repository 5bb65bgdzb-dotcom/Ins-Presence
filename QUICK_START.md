-- GUIDE DE DÉMARRAGE RAPIDE

/*=================
ÉTAPE 1: CRÉER LA BD
=================*/

1. Ouvrir phpMyAdmin: http://localhost/phpmyadmin
2. Cliquer sur "Importer"
3. Sélectionner le fichier schema.sql
4. Cliquer sur "Exécuter"

OU

1. Copier tout le contenu du fichier schema.sql
2. Aller dans l'onglet "SQL"
3. Coller le contenu
4. Cliquer sur "Exécuter"

Vérifier que la BD 'inspresence' est créée avec les tables:
- utilisateurs
- agents
- presences
- audit_logs
- login_logs
- failed_login_attempts
- conges
- rapports

/*=================
ÉTAPE 2: VÉRIFIER LA CONNEXION BD
=================*/

Éditer le fichier db.php et vérifier:
- DB_HOST = localhost (ou votre serveur)
- DB_USER = root (ou votre utilisateur)
- DB_PASS = '' (ou votre mot de passe)
- DB_NAME = inspresence

/*=================
ÉTAPE 3: ACCÉDER À L'APPLICATION
=================*/

Ouvrir: http://localhost/insPresenceStage/

L'application vous redirige automatiquement vers:
http://localhost/insPresenceStage/pages/connexion.php

/*=================
ÉTAPE 4: SE CONNECTER
=================*/

Username: admin
Password: Voir avec l'administrateur

La page d'accueil affiche le mode d'emploi.

/*=================
ÉTAPE 5: NAVIGUER
=================*/

Dashboard (Tableau de Bord):
- Vue d'ensemble avec statistiques
- Accès aux différents modules selon votre rôle

Menu Gauche:
- Navigation principale
- Options selon vos permissions

/*=================
RÔLES ET PERMISSIONS
=================*/

ADMIN:
✓ Gérer tous les utilisateurs
✓ Gérer tous les agents
✓ Gérer toutes les presences
✓ Voir les rapports et audit
✓ Exporter les données

MANAGER:
✓ Gérer les presences de l'équipe
✓ Voir les rapports
✓ Exporter les données

EMPLOYEE:
✓ Voir son profil
✓ Consulter sa présence

/*=================
PRINCIPALES FONCTIONNALITÉS
=================*/

1. GESTION DES UTILISATEURS
   - Créer un nouvel utilisateur
   - Assigner un rôle
   - Activer/Désactiver un compte

2. GESTION DES AGENTS
   - Créer une fiche agent
   - Ajouter les informations (dept, poste, etc)

3. GESTION DES PRESENCES
   - Ajouter une présence
   - Modifier une présence
   - Supprimer une présence
   - Statuts: Présent, Absent, Congé, Maladie, Retard, Demi-jour

4. MON PROFIL
   - Voir mes informations
   - Changer mon mot de passe
   - Consulter mon historique (si agent)

5. SÉCURITÉ
   - Authentification sécurisée
   - Sessions avec timeout
   - Audit de toutes les actions
   - Protection CSRF
   - Hachage des mots de passe

/*=================
PROBLÈMES COURANTS
=================*/

Q: Je ne peux pas me connecter
A: Vérifier que:
   - La BD est créée correctement
   - L'utilisateur 'admin' existe en BD
   - Le mot de passe est correct

Q: Les pages ne se chargent pas
A: Vérifier:
   - Que db.php a les bons identifiants
   - Que MySQL est démarré
   - Les erreurs dans la console du navigateur

Q: J'ai une erreur "Accès refusé"
A: Vous n'avez pas les permissions nécessaires
   - Contactez un admin pour augmenter vos droits
   - Vérifiez votre rôle en BD

Q: Les images de style ne s'affichent pas bien
A: Les styles sont en CSS inline
   - Actualiser la page (Ctrl+F5)
   - Vider le cache du navigateur

/*=================
CONSEILS DE SÉCURITÉ
=================*/

1. Changer le mot de passe admin par défaut
2. Utiliser des mots de passe forts (8+ caractères)
3. Ne pas partager les credentials
4. Vérifier régulièrement les audit logs
5. Faire des sauvegardes régulières
6. En production, utiliser HTTPS
7. Configurer un firewall

/*=================
FONCTIONNALITÉS AVANCÉES
=================*/

AUDIT LOGS:
- Toutes les modifications sont enregistrées
- Voir qui a fait quoi et quand
- À utiliser pour le compliance

LOGIN LOGS:
- Historique de connexion
- Tentatives échouées enregistrées
- Utile pour la sécurité

FAILED LOGIN ATTEMPTS:
- Enregistrement des tentatives ratées
- IP et timestamp
- Pour détecter les attaques

/*=================
STATISTIQUES DISPONIBLES (ADMIN)
=================*/

- Nombre total d'agents actifs
- Nombre de presences aujourd'hui
- Nombre de présents aujourd'hui
- Nombre d'absents aujourd'hui

/*=================
SUPPORT ET AIDE
=================*/

Pour plus de détails, consulter: README.md

---

Version: 1.0.0
Date: 22 Mai 2026
