# Projet QCM - Génération Lead Dev

Ce projet est une application web complète de questionnaires QCM, générée pour être "clé en main". Elle est construite en PHP natif et JavaScript vanilla, sans aucun framework, conformément aux exigences.

[test](https://proflambda.github.io/qcm/public/oldfiles/)

## Stack Technique

-  **Backend**: PHP 8+ (natif)
-  **Frontend**: JavaScript (vanilla, ES6 modules), Tailwind CSS (via CDN), Chart.js (via CDN)
-  **Base de données**: SQLite (par défaut) ou MySQL. Le code est compatible avec les deux via PDO.

## Installation

1. **Déploiement**: Copiez l'ensemble des fichiers sur un serveur web compatible PHP 8+. Le `DocumentRoot` de votre hébergeur (Apache, Nginx) doit pointer vers le dossier `/public`.
2. **Permissions**: Assurez-vous que le serveur web a les droits d'écriture sur le dossier `/public/data`. Le fichier de base de données SQLite (`app.db`) y sera créé automatiquement.
3. **Configuration**:
   -  Le fichier de configuration principal est `/public/inc/config.php`.
   -  Par défaut, le driver est `sqlite`.
   -  Pour passer en **MySQL**, modifiez `config.php` :
      ```php
      define('DB_DRIVER', 'mysql'); // 'sqlite' ou 'mysql'
      define('DB_HOST', 'votre_host');
      define('DB_NAME', 'votre_db');
      define('DB_USER', 'votre_user');
      define('DB_PASSWORD', 'votre_pass');
      ```
   -  Ensuite, importez le schéma correspondant : `schema/schema_mysql.sql`.

## Initialisation des Données

Les questionnaires sont importés depuis une source externe (simulée ici).

### 1. Via Script en Ligne de Commande (CLI)

C'est la méthode recommandée pour la première initialisation ou les mises à jour.

```bash
# Placez-vous à la racine du projet
php scripts/import_from_github.php
```

Ce script va :

-  Lire les sources de quiz (simulées dans `public/inc/github.php`).
-  Créer la base de données et les tables si elles n'existent pas.
-  Peupler les tables `quizzes` et `questions`.

### 2. Via l'Interface Web d'Administration

Une fois que vous avez créé un utilisateur et lui avez assigné le rôle `admin` manuellement dans la base de données :

1. Connectez-vous avec votre compte admin.
2. Accédez à l'URL `/admin/quizzes_import.php`.
3. Cliquez sur le bouton "Importer / Mettre à jour les Quiz".

## Fonctionnalités

-  **Authentification**: Inscription, connexion, déconnexion via sessions PHP natives.
-  **Catalogue de Quiz**: Page d'accueil listant les quiz disponibles.
-  **Runner de Quiz**: Interface de passage de quiz avec gestion de la progression, validation des réponses et feedback instantané.
-  **Types de questions supportés**: `single`, `multi`, `select`, `multiselect`, `toggle`, `range`, `ranking`.
-  **Dashboard Utilisateur**: Statistiques personnelles, historique des tentatives et graphiques de performance.
-  **Sécurité**: Requêtes préparées (PDO), hachage de mots de passe, protection contre les injections XSS (`htmlspecialchars`), contrôle d'accès basé sur la session.
-  **Export**: Les résultats finaux d'une tentative peuvent être exportés en JSON ou CSV.

## Accès aux anciens fichiers

Le dossier `oldfiles` contient les anciens QCM. Un lien "Anciens QCM" dans la barre de navigation pointe vers `oldfiles/index.html` pour maintenir l'accès.
