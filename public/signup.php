<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$errors = [];
$email = '';
$display_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Correction : Initialiser la base de données AVANT toute opération.
    // Cela garantit que les tables existent avant les vérifications.
    initialize_database();
    $pdo = get_db_connection();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $display_name = trim($_POST['display_name'] ?? '');

    // 1. Validation des entrées
    if (empty($email) || empty($password) || empty($password_confirm)) {
        $errors[] = 'Veuillez remplir tous les champs obligatoires.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // 2. Vérifier si l'email est déjà utilisé
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Cette adresse email est déjà utilisée. Veuillez en choisir une autre ou vous connecter.';
        }
    }

    // 3. Si tout est OK, créer l'utilisateur
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $pdo->prepare(
            "INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)"
        );

        try {
            $stmt->execute([$email, $password_hash, $display_name ?: null]);
            // Redirection vers la page de connexion avec un message de succès
            header('Location: ' . BASE_URL . 'login.php?success=1');
            exit;
        } catch (PDOException $e) {
            // Gérer les erreurs d'insertion (ex: contrainte unique violée, même si on a déjà vérifié)
            $errors[] = "Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.";
        }
    }
}

$page_title = "Inscription";
require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-center min-h-screen bg-gray-100 -mt-24">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-3xl font-bold text-center text-gray-900">Créez votre compte</h2>

        <?php if (!empty($errors)): ?>
            <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="signup.php" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Adresse Email <span class="text-red-500">*</span></label>
                <div class="mt-1">
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="<?= htmlspecialchars($email) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <label for="display_name" class="block text-sm font-medium text-gray-700">Pseudo (facultatif)</label>
                <div class="mt-1">
                    <input id="display_name" name="display_name" type="text" autocomplete="nickname"
                           value="<?= htmlspecialchars($display_name) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe <span class="text-red-500">*</span></label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                 <p class="mt-2 text-xs text-gray-500">Doit contenir au moins 8 caractères.</p>
            </div>

            <div>
                <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirmez le mot de passe <span class="text-red-500">*</span></label>
                <div class="mt-1">
                    <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    S'inscrire
                </button>
            </div>
        </form>

        <p class="text-sm text-center text-gray-600">
            Déjà un compte ?
            <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                Connectez-vous
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