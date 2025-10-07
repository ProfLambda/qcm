<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

// Si l'utilisateur est déjà connecté, on le redirige vers le tableau de bord.
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error_message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'L\'adresse email n\'est pas valide.';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Le mot de passe est correct.

            // Régénérer l'ID de session pour prévenir la fixation de session.
            session_regenerate_id(true);

            // Stocker les informations de l'utilisateur dans la session.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
            ];

            // Rediriger vers la page demandée avant la connexion, ou le tableau de bord.
            $redirect_to = $_SESSION['redirect_after_login'] ?? BASE_URL . 'dashboard.php';
            unset($_SESSION['redirect_after_login']); // Nettoyer la variable de session

            header('Location: ' . $redirect_to);
            exit;
        } else {
            // Identifiants incorrects.
            $error_message = 'Email ou mot de passe incorrect.';
        }
    }
}

$page_title = "Connexion";
require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-center min-h-screen bg-gray-100 -mt-24">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-3xl font-bold text-center text-gray-900">Connectez-vous</h2>

        <?php if ($error_message): ?>
            <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                Inscription réussie ! Vous pouvez maintenant vous connecter.
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Adresse Email</label>
                <div class="mt-1">
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="<?= htmlspecialchars($email) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Se connecter
                </button>
            </div>
        </form>

        <p class="text-sm text-center text-gray-600">
            Pas encore de compte ?
            <a href="signup.php" class="font-medium text-blue-600 hover:text-blue-500">
                Inscrivez-vous ici
            </a>
        </p>
    </div>
</div>

<?php
// On ne veut pas du footer standard sur cette page
// require_once __DIR__ . '/partials/footer.php';
?>
</body>
</html>