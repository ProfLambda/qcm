# Projet QCM - Génération Lead Dev

Ce projet est une application web complète de questionnaires QCM, générée pour être "clé en main". Elle est construite en PHP natif et JavaScript vanilla, sans aucun framework.

[test](https://proflambda.github.io/qcm/public/qcm_originaux/)

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
   -  Ensuite, l'installation importera le schéma correspondant : `schema/schema_mysql.sql` automatiquement si DB_DRIVER = 'mysql'.

## Fonctionnalités

-  **Authentification**: Inscription, connexion, déconnexion via sessions PHP natives.
-  **Catalogue de Quiz**: Page d'accueil listant les quiz disponibles.
-  **Runner de Quiz**: Interface de passage de quiz avec gestion de la progression, validation des réponses et feedback instantané.
-  **Types de questions supportés**: `single`, `multi`, `select`, `multiselect`, `toggle`, `range`, `ranking`.
-  **Dashboard Utilisateur**: Statistiques personnelles, historique des tentatives et graphiques de performance.
-  **Sécurité**: Requêtes préparées (PDO), hachage de mots de passe, protection contre les injections XSS (`htmlspecialchars`), contrôle d'accès basé sur la session.
-  **Export**: Les résultats finaux d'une tentative peuvent être exportés en JSON ou CSV.

## Accès aux anciens fichiers

Le dossier `qcm_originaux` contient les anciens QCM au format HTML. Un lien "Anciens QCM" dans la barre de navigation pointe vers `qcm_originaux/index.html` pour maintenir l'accès jusqu'à nouvel ordre.
