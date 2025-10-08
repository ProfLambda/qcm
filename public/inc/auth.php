<?php
// Fichier d'aide pour la gestion de l'authentification et des sessions

require_once __DIR__ . '/config.php';

/**
 * Vérifie si un utilisateur est actuellement connecté.
 *
 * @return bool True si l'utilisateur est connecté, false sinon.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Récupère les informations de l'utilisateur actuellement connecté.
 *
 * @return array|null Les données de l'utilisateur ou null s'il n'est pas connecté.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    // On pourrait mettre en cache les données de l'utilisateur dans la session
    // pour éviter des requêtes DB à chaque chargement de page.
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    require_once __DIR__ . '/db.php';
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, email, display_name, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user; // Mise en cache
        return $user;
    }

    // Si l'user_id en session ne correspond à aucun utilisateur, on nettoie la session.
    logout();
    return null;
}

/**
 * Exige qu'un utilisateur soit connecté pour accéder à une page.
 * Si l'utilisateur n'est pas connecté, il est redirigé vers la page de connexion.
 *
 * @param string $redirect_url L'URL vers laquelle rediriger si non connecté.
 */
function require_login(string $redirect_url = 'login.php'): void
{
    if (!is_logged_in()) {
        // Stocker l'URL demandée pour rediriger l'utilisateur après la connexion
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . $redirect_url);
        exit;
    }
}

/**
 * Exige qu'un utilisateur ait un rôle spécifique (ex: 'admin').
 *
 * @param string $role Le rôle requis.
 */
function require_role(string $role): void
{
    require_login();
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        http_response_code(403); // Forbidden
        echo "Accès refusé. Vous n'avez pas les permissions nécessaires.";
        exit;
    }
}

function require_admin(): void
{
    require_role('admin');
}


/**
 * Déconnecte l'utilisateur en détruisant la session.
 */
function logout(): void
{
    // Efface toutes les variables de session
    $_SESSION = [];

    // Si vous souhaitez détruire complètement la session, effacez également
    // le cookie de session.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finalement, on détruit la session.
    session_destroy();
}

/**
 * Fonction utilitaire pour générer une réponse JSON et terminer le script.
 *
 * @param int $statusCode Le code de statut HTTP.
 * @param array $data Les données à encoder en JSON.
 */
function json_response(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>