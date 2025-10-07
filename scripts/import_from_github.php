#!/usr/bin/env php
<?php
// Script en ligne de commande (CLI) pour importer les quiz.
// Usage: php scripts/import_from_github.php

// Définir un flag pour indiquer que nous sommes en mode CLI
define('APP_RUNNING_IN_CLI', true);

// DEBUG: Point de contrôle 1
echo "Script démarré.\n";

// Inclure les fichiers nécessaires. On remonte dans l'arborescence.
require_once __DIR__ . '/../public/inc/db.php';
// DEBUG: Point de contrôle 2
echo "Fichier db.php inclus.\n";

require_once __DIR__ . '/../public/inc/github.php';
// DEBUG: Point de contrôle 3
echo "Fichier github.php inclus.\n";

require_once __DIR__ . '/../public/inc/scoring.php';
// DEBUG: Point de contrôle 4
echo "Fichier scoring.php inclus.\n";


function run_import() {
    echo "=============================================\n";
    echo " Démarrage de l'importation des questionnaires \n";
    echo "=============================================\n";

    try {
        // 1. Initialiser la connexion à la base de données et les tables
        echo "1. Initialisation de la base de données...\n";
        initialize_database();
        $pdo = get_db_connection();
        echo "   Base de données prête.\n\n";

        // 2. Récupérer la liste des fichiers de quiz
        echo "2. Récupération de la liste des fichiers de quiz...\n";
        $quiz_files = fetch_quiz_files_list_from_source();
        if (empty($quiz_files)) {
            echo "   Aucun fichier de quiz trouvé dans le dossier 'oldfiles'.\n";
            echo "   Assurez-vous d'avoir placé vos fichiers .json ou .html dans ce dossier.\n";
            // Création de fichiers d'exemple si le dossier est vide
            create_dummy_quiz_files();
            $quiz_files = fetch_quiz_files_list_from_source();
            echo "   Fichiers d'exemples créés. Relancez le script.\n";
            exit;
        }
        echo "   " . count($quiz_files) . " fichier(s) trouvé(s).\n\n";

        // 3. Traiter chaque fichier
        $pdo->beginTransaction();
        echo "3. Traitement de chaque fichier...\n";

        foreach ($quiz_files as $filename) {
            echo "   - Traitement de '$filename'...\n";
            $content = fetch_raw_quiz_content($filename);
            if (!$content) {
                echo "     [ERREUR] Impossible de lire le contenu du fichier.\n";
                continue;
            }

            $questions = [];
            $title = pathinfo($filename, PATHINFO_FILENAME);

            try {
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'json') {
                    $data = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("JSON invalide.");
                    }
                    // On suppose que le JSON peut contenir le titre et les questions
                    $title = $data['title'] ?? $title;
                    $questions = $data['questions'] ?? ($data['QUESTIONS'] ?? []);
                } elseif (pathinfo($filename, PATHINFO_EXTENSION) === 'html') {
                    $questions = extract_questions_from_html($content);
                }
            } catch (Exception $e) {
                echo "     [ERREUR] Impossible d'extraire les questions : " . $e->getMessage() . "\n";
                continue;
            }

            if (empty($questions)) {
                echo "     [AVERTISSEMENT] Aucune question trouvée dans le fichier.\n";
                continue;
            }

            // 4. Calculer les métadonnées du quiz
            $question_count = count($questions);
            $total_max_points = 0;
            $themes = [];
            foreach ($questions as $q) {
                $total_max_points += calculate_max_points_for_question($q);
                if (isset($q['theme']) && !in_array($q['theme'], $themes)) {
                    $themes[] = $q['theme'];
                }
            }

            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
            $level = extract_level_from_title($title);

            // 5. "Upsert" du quiz dans la base de données
            $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE slug = ?");
            $stmt->execute([$slug]);
            $quiz_id = $stmt->fetchColumn();

            if ($quiz_id) {
                // UPDATE
                echo "     Mise à jour du quiz existant (ID: $quiz_id).\n";
                $update_quiz_stmt = $pdo->prepare(
                    "UPDATE quizzes SET title = ?, level = ?, themes = ?, question_count = ?, total_max_points = ?, source_url = ? WHERE id = ?"
                );
                $update_quiz_stmt->execute([$title, $level, json_encode($themes), $question_count, $total_max_points, $filename, $quiz_id]);

                // Supprimer les anciennes questions pour les remplacer
                $delete_questions_stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
                $delete_questions_stmt->execute([$quiz_id]);
            } else {
                // INSERT
                echo "     Création d'un nouveau quiz.\n";
                $insert_quiz_stmt = $pdo->prepare(
                    "INSERT INTO quizzes (slug, title, level, themes, question_count, total_max_points, source_url) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insert_quiz_stmt->execute([$slug, $title, $level, json_encode($themes), $question_count, $total_max_points, $filename]);
                $quiz_id = $pdo->lastInsertId();
            }

            // 6. Insertion des questions
            $insert_question_stmt = $pdo->prepare(
                "INSERT INTO questions (quiz_id, index_in_quiz, payload) VALUES (?, ?, ?)"
            );
            foreach ($questions as $index => $question) {
                $insert_question_stmt->execute([$quiz_id, $index, json_encode($question)]);
            }
            echo "     $question_count questions importées pour le quiz '$title'.\n";
        }

        $pdo->commit();
        echo "\n=============================================\n";
        echo " Importation terminée avec succès. \n";
        echo "=============================================\n";

    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "\n[ERREUR FATALE] Une erreur est survenue durant l'importation : " . $e->getMessage() . "\n";
        exit(1);
    }
}

function extract_level_from_title($title) {
    $title_lower = strtolower($title);
    if (str_contains($title_lower, 'débutant') || str_contains($title_lower, 'beginner')) return 'Débutant';
    if (str_contains($title_lower, 'intermédiaire') || str_contains($title_lower, 'intermediate') || str_contains($title_lower, 'modeste')) return 'Intermédiaire';
    if (str_contains($title_lower, 'avancé') || str_contains($title_lower, 'advanced')) return 'Avancé';
    if (str_contains($title_lower, 'expert')) return 'Expert';
    return 'Général';
}

function create_dummy_quiz_files() {
    $oldfilesDir = __DIR__ . '/../oldfiles';

    // Création du fichier index.html déplacé
    $indexHtmlContent = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Anciens QCM (Archive)</title>
    <style>body { font-family: sans-serif; padding: 2em; } a { display: block; margin-bottom: 1em; }</style>
</head>
<body>
    <h1>Archive des anciens QCM</h1>
    <p>Cette page est une archive. Les nouveaux quiz sont disponibles sur la page d'accueil.</p>
    <a href="qcm-ia-beginner.json">QCM IA Débutant (source JSON)</a>
    <p><em>Note: Le contenu fonctionnel a été migré vers la nouvelle application.</em></p>
</body>
</html>
HTML;
    file_put_contents($oldfilesDir . '/index.html', $indexHtmlContent);


    // Création d'un fichier JSON d'exemple
    $jsonContent = <<<JSON
{
  "title": "QCM sur l'IA - Débutant",
  "level": "Débutant",
  "QUESTIONS": [
    {
      "id": "q1",
      "theme": "Concepts de base",
      "question": "Qu'est-ce que l'apprentissage supervisé ?",
      "selectionType": "single",
      "options": [
        { "id": "q1o1", "label": "Un apprentissage avec des données non étiquetées", "points": 0 },
        { "id": "q1o2", "label": "Un apprentissage basé sur des données étiquetées (entrée-sortie)", "points": 1, "emoji": "✅" },
        { "id": "q1o3", "label": "Un apprentissage par essai-erreur", "points": 0 }
      ],
      "feedback_correct": "Exact ! L'apprentissage supervisé utilise un jeu de données où les bonnes réponses sont connues.",
      "feedback_incorrect": "Incorrect. C'est la définition de l'apprentissage non-supervisé.",
      "relevance": 0.9
    },
    {
      "id": "q2",
      "theme": "Concepts de base",
      "question": "Quels sont des exemples d'IA ?",
      "selectionType": "multi",
      "options": [
        { "id": "q2o1", "label": "Reconnaissance faciale", "points": 1 },
        { "id": "q2o2", "label": "Une calculatrice", "points": 0 },
        { "id": "q2o3", "label": "Les assistants vocaux (Siri, Alexa)", "points": 1 },
        { "id": "q2o4", "label": "Un tableur Excel", "points": 0 }
      ],
      "feedback_correct": "Très bien, vous avez identifié les bonnes applications.",
      "feedback_partial": "Vous en avez quelques-uns. Les calculatrices et tableurs sont des programmes informatiques, mais pas considérés comme de l'IA.",
      "feedback_incorrect": "Ces exemples ne sont pas corrects.",
      "relevance": 0.8
    }
  ]
}
JSON;
    // La convention de nommage du fichier a été changée pour correspondre à la logique d'extraction du titre.
    file_put_contents($oldfilesDir . '/qcm-ia-beginner.json', $jsonContent);
}


// Exécuter la fonction principale si le script est appelé directement
if (php_sapi_name() === 'cli' || defined('APP_RUNNING_IN_CLI')) {
    run_import();
}
?>