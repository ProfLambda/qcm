<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

// Le runner de quiz nécessite d'être connecté.
require_login();

// Récupérer le slug du quiz depuis l'URL.
$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header("Location: " . BASE_URL);
    exit;
}

// Récupérer les informations du quiz depuis la base de données.
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$quiz = $stmt->fetch();

if (!$quiz) {
    $page_title = "Erreur 404";
    require_once __DIR__ . '/partials/header.php';
    echo '<div class="text-center py-20">
            <h1 class="text-3xl font-bold">Quiz non trouvé</h1>
            <p class="text-gray-600 mt-4">Le quiz que vous cherchez n\'existe pas ou n\'est plus disponible.</p>
            <a href="' . BASE_URL . '" class="mt-8 inline-block bg-blue-600 text-white font-bold py-3 px-6 rounded-md hover:bg-blue-700">Retour à l\'accueil</a>
          </div>';
    require_once __DIR__ . '/partials/footer.php';
    exit;
}

// Récupérer toutes les questions pour ce quiz
$stmt_questions = $pdo->prepare("SELECT payload FROM questions WHERE quiz_id = ? ORDER BY index_in_quiz ASC");
$stmt_questions->execute([$quiz['id']]);
$questions_payload = $stmt_questions->fetchAll(PDO::FETCH_COLUMN);
$questions = array_map(fn($p) => json_decode($p, true), $questions_payload);

$page_title = "Quiz: " . htmlspecialchars($quiz['title']);
require_once __DIR__ . '/partials/header.php';
?>

<div id="quiz-runner" class="container mx-auto px-4 py-8"
     data-quiz-slug="<?= htmlspecialchars($quiz['slug']) ?>"
     data-quiz-id="<?= $quiz['id'] ?>"
     data-user-id="<?= current_user()['id'] ?>">

    <!-- 1. Barre de progression et d'information -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-8 sticky top-24 z-10">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800 truncate pr-4" id="quiz-title"><?= htmlspecialchars($quiz['title']) ?></h1>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <div class="text-sm text-gray-500">Progression</div>
                    <div class="font-bold text-gray-800" id="progress-text">Question 1 / <?= $quiz['question_count'] ?></div>
                </div>
                <div class="w-40 bg-gray-200 rounded-full h-2.5">
                    <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: <?= 100 / $quiz['question_count'] ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Conteneur de la question -->
    <div id="question-container" class="bg-white p-8 rounded-lg shadow-lg min-h-[400px]">
        <!-- Le contenu de la question sera rendu ici par JS -->
        <div class="text-center text-gray-500">Chargement de la question...</div>
    </div>

    <!-- 3. Zone de feedback -->
    <div id="feedback-container" class="mt-6 p-4 rounded-md text-center transition-opacity duration-300 opacity-0">
        <!-- Le feedback après validation s'affichera ici -->
    </div>

    <!-- 4. Barre de navigation du quiz -->
    <div class="mt-8 flex justify-between items-center">
        <button id="prev-btn" class="px-6 py-3 bg-gray-300 text-gray-800 font-semibold rounded-md hover:bg-gray-400 disabled:bg-gray-200 disabled:cursor-not-allowed">
            &larr; Précédent
        </button>

        <div class="flex space-x-4">
            <button id="validate-btn" class="px-8 py-3 bg-green-600 text-white font-bold rounded-md hover:bg-green-700 transition-transform transform hover:scale-105">
                Valider
            </button>
            <button id="next-btn" class="px-8 py-3 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-transform transform hover:scale-105 hidden">
                Suivant &rarr;
            </button>
             <button id="finish-btn" class="px-8 py-3 bg-purple-600 text-white font-bold rounded-md hover:bg-purple-700 hidden">
                Voir l'avis final
            </button>
        </div>
    </div>

    <!-- 5. Modal de résultats finaux -->
    <div id="results-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-2xl p-8 max-w-2xl w-full animate-fade-in-up">
            <h2 class="text-3xl font-bold mb-4">Résultats du Quiz</h2>
            <div id="results-summary"></div>
            <div class="mt-6 flex justify-end space-x-4">
                <button id="export-json-btn" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">Exporter en JSON</button>
                <button id="export-csv-btn" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-800">Exporter en CSV</button>
                <a href="<?= BASE_URL ?>dashboard.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Aller au Tableau de Bord</a>
            </div>
        </div>
    </div>
</div>

<!-- On passe les données du quiz au script JS via un objet global ou des data-attributes. -->
<!-- Ici, on utilise un script tag pour injecter les données, c'est plus propre pour des objets complexes. -->
<script id="quiz-data" type="application/json">
    <?= json_encode($questions) ?>
</script>


<?php require_once __DIR__ . '/partials/footer.php'; ?>