<?php
require_once __DIR__ . '/inc/auth.php';

// Appelle la fonction de déconnexion qui détruit la session.
logout();

// Redirige l'utilisateur vers la page d'accueil.
header('Location: ' . BASE_URL);
exit;
?>