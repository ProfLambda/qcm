#!/usr/bin/env php
<?php
// Script en ligne de commande (CLI) pour importer les quiz.
// Usage: php scripts/import_from_github.php

// Définir un flag pour indiquer que nous sommes en mode CLI
define('APP_RUNNING_IN_CLI', true);

// Inclure les fichiers nécessaires. On remonte dans l'arborescence.
require_once __DIR__ . '/../public/inc/db.php';
require_once __DIR__ . '/../public/inc/scoring.php';

function run_import() {
    echo "=============================================\n";
    echo " Démarrage de l'importation des questionnaires \n";
    echo "=============================================\n";

    try {
        // 1. Initialiser la connexion à la base de données
        echo "1. Initialisation de la base de données...\n";
        initialize_database();
        $pdo = get_db_connection();
        echo "   Base de données prête.\n\n";

        // 3. Lire les données depuis le fichier JSON
        echo "3. Lecture du fichier JSON consolidé...\n";
        $json_path = __DIR__ . '/../public/data/quizzes.json';
        if (!file_exists($json_path)) {
            throw new Exception("Fichier quizzes.json introuvable. La construction a peut-être échoué.");
        }
        $json_content = file_get_contents($json_path);
        $quizzes_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de décodage du fichier quizzes.json : " . json_last_error_msg());
        }
        echo "   " . count($quizzes_data) . " quiz trouvé(s) dans le fichier JSON.\n\n";

        // 4. Traiter chaque quiz du JSON (logique "upsert")
        $pdo->beginTransaction();
        echo "4. Synchronisation des quiz avec la base de données...\n";

        foreach ($quizzes_data as $quiz) {
            $title = $quiz['title'] ?? 'Titre inconnu';
            $source_file = $quiz['source_file'] ?? 'N/A';
            $questions = $quiz['questions'] ?? [];

            if (empty($questions)) {
                echo "   - [SKIP] Quiz '$title' ignoré (aucune question).\n";
                continue;
            }

            echo "   - Traitement du quiz : '$title'...\n";

            // Calculer les métadonnées
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

            // "Upsert" du quiz
            $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE slug = ?");
            $stmt->execute([$slug]);
            $quiz_id = $stmt->fetchColumn();

            if ($quiz_id) {
                // UPDATE
                echo "     Mise à jour du quiz existant (ID: $quiz_id).\n";
                $update_quiz_stmt = $pdo->prepare(
                    "UPDATE quizzes SET title = ?, level = ?, themes = ?, question_count = ?, total_max_points = ?, source_url = ? WHERE id = ?"
                );
                $update_quiz_stmt->execute([$title, $level, json_encode($themes), $question_count, $total_max_points, $source_file, $quiz_id]);

                // Vider les anciennes questions
                $delete_questions_stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
                $delete_questions_stmt->execute([$quiz_id]);
            } else {
                // INSERT
                echo "     Création d'un nouveau quiz.\n";
                $insert_quiz_stmt = $pdo->prepare(
                    "INSERT INTO quizzes (slug, title, level, themes, question_count, total_max_points, source_url) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insert_quiz_stmt->execute([$slug, $title, $level, json_encode($themes), $question_count, $total_max_points, $source_file]);
                $quiz_id = $pdo->lastInsertId();
            }

            // Insertion des nouvelles questions
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


// Exécuter la fonction principale si le script est appelé directement
if (php_sapi_name() === 'cli' || defined('APP_RUNNING_IN_CLI')) {
    run_import();
}
?>