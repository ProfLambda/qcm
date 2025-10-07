<?php
// Fichier de configuration principal de l'application

// --- GESTION DES ERREURS ---
// Affiche les erreurs en développement. À mettre sur `0` en production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// --- SESSION ---
// Démarre la session si elle n'est pas déjà active.
if (session_status() === PHP_SESSION_NONE) {
    // Configure le cookie de session pour plus de sécurité
    session_set_cookie_params([
        'lifetime' => 3600, // 1 heure
        'path' => '/',
        'domain' => '', // Mettre votre domaine en production
        'secure' => isset($_SERVER['HTTPS']), // True si en HTTPS
        'httponly' => true, // Empêche l'accès au cookie via JS
        'samesite' => 'Lax' // Protection CSRF
    ]);
    session_start();
}


// --- BASE DE DONNÉES ---
// Choix du driver : 'sqlite' ou 'mysql'.
// Par défaut, on utilise SQLite pour une installation "clé en main".
define('DB_DRIVER', 'sqlite');

// --- Configuration pour SQLite ---
if (DB_DRIVER === 'sqlite') {
    // Le chemin vers le fichier de la base de données SQLite.
    // Il sera créé dans `/public/data/`
    define('DB_PATH', __DIR__ . '/../data/app.db');
}

// --- Configuration pour MySQL ---
// Remplir ces informations si vous utilisez 'mysql'
if (DB_DRIVER === 'mysql') {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'qcm_app');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_CHARSET', 'utf8mb4');
}

// --- CHEMINS DE L'APPLICATION ---
// Définit une constante pour la racine du site pour les liens.
// Assurez-vous que cela correspond à la configuration de votre serveur.
define('BASE_URL', '/'); // ex: '/' si à la racine, '/mon-projet/' si dans un sous-dossier


// --- SOURCE DES QUIZ POUR L'IMPORT ---
// URL "raw" du dépôt GitHub contenant les fichiers de quiz.
// Pour la démonstration, nous utilisons un chemin local simulé dans `github.php`
// mais dans un cas réel, ce serait une URL comme :
// define('GITHUB_RAW_URL', 'https://raw.githubusercontent.com/user/repo/main/quizzes/');
define('GITHUB_RAW_URL', 'SIMULATED');

?>