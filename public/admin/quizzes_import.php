<?php
require_once __DIR__ . '/../inc/auth.php';

// Seuls les administrateurs peuvent accéder à cette page.
require_role('admin');

$page_title = "Administration - Import des Quiz";
require_once __DIR__ . '/../partials/header.php';

$import_output = '';
$import_successful = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Démarrer la mise en mémoire tampon de la sortie
    ob_start();

    try {
        // Inclure et exécuter la logique du script d'importation.
        // Le script d'importation est conçu pour être appelé via CLI ou inclus.
        require_once __DIR__ . '/../../scripts/import_from_github.php';

        // La fonction run_import() est définie dans le script ci-dessus.
        // Elle gère l'ensemble du processus et affiche sa progression.
        // run_import(); // Cette ligne est commentée car le script s'auto-exécute

        $import_successful = true;

    } catch (Exception $e) {
        // Capturer les exceptions qui pourraient survenir pendant l'importation
        echo "\n[ERREUR FATALE CAPTURÉE] " . $e->getMessage();
        $import_successful = false;
    }

    // Récupérer le contenu du tampon et le nettoyer
    $import_output = ob_get_clean();
}
?>

<div class="container mx-auto px-6 py-12">
    <h1 class="text-3xl font-bold mb-6">Importation des Questionnaires</h1>

    <div class="bg-white p-8 rounded-lg shadow-lg">
        <p class="mb-4 text-gray-700">
            Cette page permet d'importer ou de mettre à jour les questionnaires depuis la source de données configurée (actuellement simulée par les fichiers dans le dossier `/oldfiles`).
        </p>
        <p class="mb-6 text-sm text-gray-500">
            Le script va lire les fichiers, les parser, calculer les métadonnées et les insérer ou mettre à jour dans la base de données.
        </p>

        <form method="POST" action="">
            <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-md hover:bg-blue-700 transition-transform transform hover:scale-105">
                Lancer l'Importation / Mise à jour
            </button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Résultat de l'importation :</h2>
                <?php if ($import_successful): ?>
                    <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg">
                        L'importation s'est terminée. Vérifiez les logs ci-dessous pour les détails.
                    </div>
                <?php else: ?>
                     <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg">
                        Une erreur est survenue pendant l'importation.
                    </div>
                <?php endif; ?>

                <pre class="mt-4 bg-gray-900 text-white text-sm rounded-md p-6 overflow-x-auto max-h-96"><?= htmlspecialchars($import_output) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>