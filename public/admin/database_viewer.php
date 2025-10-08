<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// Seuls les administrateurs peuvent accéder à cette page
require_admin();

$page_title = 'Visionneuse de la Base de Données';
require_once __DIR__ . '/../partials/header.php';

$pdo = get_db_connection();
$selected_table = $_GET['table'] ?? null;
$tables = [];
$table_content = [];
$table_columns = [];

try {
    // Récupérer la liste de toutes les tables
    $driver = DB_DRIVER;
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    } else { // mysql
        $stmt = $pdo->query("SHOW TABLES");
    }
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Si une table est sélectionnée, récupérer son contenu
    if ($selected_table && in_array($selected_table, $tables)) {
        $stmt_content = $pdo->query("SELECT * FROM " . $selected_table . " LIMIT 100");
        $table_content = $stmt_content->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($table_content)) {
            $table_columns = array_keys($table_content[0]);
        }
    }

} catch (Exception $e) {
    $error_message = "Erreur: " . $e->getMessage();
}

?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Visionneuse de la Base de Données</h1>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Erreur !</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Colonne de la liste des tables -->
        <div class="md:col-span-1 bg-white p-4 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Tables</h2>
            <ul class="space-y-2">
                <?php foreach ($tables as $table): ?>
                    <li>
                        <a href="?table=<?= htmlspecialchars($table) ?>"
                           class="block px-3 py-2 rounded-md transition <?= $selected_table === $table ? 'bg-blue-600 text-white font-bold' : 'bg-gray-100 hover:bg-blue-100' ?>">
                            <?= htmlspecialchars($table) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                 <?php if (empty($tables)): ?>
                    <li class="text-gray-500">Aucune table trouvée.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Colonne du contenu de la table -->
        <div class="md:col-span-3 bg-white p-6 rounded-lg shadow">
            <?php if ($selected_table && in_array($selected_table, $tables)): ?>
                <h2 class="text-2xl font-semibold mb-4">Contenu de la table : <span class="font-bold text-blue-600"><?= htmlspecialchars($selected_table) ?></span></h2>

                <?php if (empty($table_content)): ?>
                    <p class="text-gray-500">La table est vide.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <?php foreach ($table_columns as $column): ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <?= htmlspecialchars($column) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($table_content as $row): ?>
                                    <tr>
                                        <?php foreach ($table_columns as $column): ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                    <?= htmlspecialchars($row[$column] ?? 'NULL') ?>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center text-gray-500 pt-16">
                    <p>Veuillez sélectionner une table sur la gauche pour afficher son contenu.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>