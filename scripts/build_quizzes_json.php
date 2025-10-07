#!/usr/bin/env php
<?php
// Script pour agréger tous les questionnaires des fichiers sources
// en un seul fichier JSON.

require_once __DIR__ . '/../public/inc/github.php';

function build_json() {
    echo "=================================================\n";
    echo " Construction du fichier JSON des questionnaires \n";
    echo "=================================================\n";

    try {
        // 1. Récupérer la liste des fichiers de quiz
        echo "1. Récupération de la liste des fichiers sources...\n";
        $quiz_files = fetch_quiz_files_list_from_source();

        if (empty($quiz_files)) {
            echo "   Aucun fichier source trouvé dans 'public/oldfiles'.\n";
            return;
        }
        echo "   " . count($quiz_files) . " fichier(s) trouvé(s).\n\n";

        // 2. Extraire les questions de chaque fichier
        echo "2. Extraction des questions de chaque fichier...\n";
        $all_quizzes = [];

        foreach ($quiz_files as $filename) {
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'html') {
                continue; // On ne traite que les fichiers HTML pour l'instant
            }

            echo "   - Traitement de '$filename'...\n";
            $content = fetch_raw_quiz_content($filename);
            if (!$content) {
                echo "     [AVERTISSEMENT] Impossible de lire le contenu.\n";
                continue;
            }

            try {
                $questions = extract_questions_from_html($content);
                if (empty($questions)) {
                    echo "     [AVERTISSEMENT] Aucune question trouvée.\n";
                    continue;
                }

                $title = pathinfo($filename, PATHINFO_FILENAME);
                $quiz_data = [
                    'source_file' => $filename,
                    'title' => $title,
                    'questions' => $questions,
                ];
                $all_quizzes[] = $quiz_data;
                echo "     " . count($questions) . " questions extraites.\n";

            } catch (Exception $e) {
                echo "     [ERREUR] Impossible d'extraire les questions : " . $e->getMessage() . "\n";
                continue;
            }
        }

        // 3. Sauvegarder le JSON consolidé
        echo "\n3. Sauvegarde du fichier JSON consolidé...\n";
        $output_dir = __DIR__ . '/../public/data';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        $output_path = $output_dir . '/quizzes.json';

        $json_content = json_encode($all_quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($output_path, $json_content)) {
            echo "   Fichier sauvegardé avec succès dans : $output_path\n";
        } else {
            throw new Exception("Échec de la sauvegarde du fichier JSON.");
        }

        echo "\n=================================================\n";
        echo " Construction terminée avec succès. \n";
        echo "=================================================\n";

    } catch (Exception $e) {
        echo "\n[ERREUR FATALE] " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Exécuter la fonction si le script est appelé directement
if (php_sapi_name() === 'cli') {
    build_json();
}
?>