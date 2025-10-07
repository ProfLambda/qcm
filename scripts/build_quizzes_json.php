<?php
// Standalone script to build a consolidated quizzes.json file from HTML sources.

function build_json() {
    echo "=================================================\n";
    echo " Construction du fichier JSON des questionnaires \n";
    echo "=================================================\n";

    try {
        $source_dir = __DIR__ . '/../public/oldfiles';
        $output_dir = __DIR__ . '/../public/data';
        $output_path = $output_dir . '/quizzes.json';

        // 1. Get the list of source files
        echo "1. Recherche des fichiers sources dans '$source_dir'...\n";
        if (!is_dir($source_dir)) {
            throw new Exception("Le dossier source '$source_dir' n'existe pas.");
        }

        $files = scandir($source_dir);
        $html_files = array_filter($files, function($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'html';
        });

        if (empty($html_files)) {
            echo "   Aucun fichier HTML trouvé. Le script se termine.\n";
            return;
        }
        echo "   " . count($html_files) . " fichier(s) HTML trouvé(s).\n\n";

        // 2. Extract questions from each file
        echo "2. Extraction des questions de chaque fichier...\n";
        $all_quizzes = [];

        foreach ($html_files as $filename) {
            echo "   - Traitement de '$filename'...\n";
            $filepath = $source_dir . '/' . $filename;
            $content = file_get_contents($filepath);
            if (!$content) {
                echo "     [AVERTISSEMENT] Impossible de lire le contenu du fichier.\n";
                continue;
            }

            try {
                $questions = extract_questions_from_html($content);
                if (empty($questions)) {
                    echo "     [AVERTISSEMENT] Aucune question n'a été trouvée.\n";
                    continue;
                }

                $title = pathinfo($filename, PATHINFO_FILENAME);
                $quiz_data = [
                    'source_file' => $filename,
                    'title' => $title,
                    'questions' => $questions,
                ];
                $all_quizzes[] = $quiz_data;
                echo "     " . count($questions) . " questions extraites avec succès.\n";

            } catch (Exception $e) {
                echo "     [ERREUR] Impossible d'extraire les questions : " . $e->getMessage() . "\n";
                continue;
            }
        }

        // 3. Save the consolidated JSON file
        echo "\n3. Sauvegarde du fichier JSON consolidé...\n";
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        $json_content = json_encode($all_quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($output_path, $json_content)) {
            echo "   Fichier sauvegardé avec succès : $output_path\n";
        } else {
            throw new Exception("Échec de la sauvegarde du fichier JSON.");
        }

        echo "\n=================================================\n";
        echo " Construction terminée avec succès.\n";
        echo "=================================================\n";

    } catch (Exception $e) {
        echo "\n[ERREUR FATALE] " . $e->getMessage() . "\n";
        exit(1);
    }
}

function extract_questions_from_html(string $htmlContent): array {
    $pattern = '/const\s+QUESTIONS\s*=\s*(\[[\s\S]*?\])\s*;/m';
    if (!preg_match($pattern, $htmlContent, $matches)) {
        throw new Exception("Constante 'QUESTIONS' introuvable.");
    }
    $js_string = $matches[1];

    // 1. Cleanup: remove comments and trailing commas
    $js_string = preg_replace('!/\*[\s\S]*?\*/!s', '', $js_string); // Block comments
    $js_string = preg_replace('!//.*!', '', $js_string);             // Line comments
    $js_string = preg_replace('/,\s*([}\]])/', '$1', $js_string);     // Trailing commas

    // 2. Quote all unquoted keys
    $js_string = preg_replace('/([{,]\s*)([a-zA-Z0-9_]+)\s*:/', '$1"$2":', $js_string);

    // 3. Temporarily replace all double-quoted strings with placeholders
    $placeholders = [];
    $i = 0;
    $js_string = preg_replace_callback(
        '/"((?:[^"\\\\]|\\\\.)*)"/',
        function ($matches) use (&$placeholders, &$i) {
            $placeholder = '___JSON_PLACEHOLDER_' . $i++ . '___';
            $placeholders[$placeholder] = $matches[0];
            return $placeholder;
        },
        $js_string
    );

    // 4. Now it's safe to convert all single-quoted strings to double-quoted
    $js_string = preg_replace_callback(
        "/'((?:[^'\\\\]|\\\\.)*)'/",
        function ($matches) {
            return '"' . str_replace('"', '\"', $matches[1]) . '"';
        },
        $js_string
    );

    // 5. Restore the original double-quoted strings
    if (!empty($placeholders)) {
        krsort($placeholders); // Process in reverse to avoid conflicts
        $js_string = str_replace(array_keys($placeholders), array_values($placeholders), $js_string);
    }

    // 6. Decode the final JSON string
    $questions = json_decode($js_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur de décodage JSON: " . json_last_error_msg());
    }

    return $questions;
}

// Execute the main function if the script is called directly
if (php_sapi_name() === 'cli' || defined('APP_RUNNING_IN_CLI')) {
    build_json();
}
?>