<?php
// Fichier pour simuler la récupération de données depuis GitHub.
// Dans une application réelle, ce fichier utiliserait cURL ou file_get_contents
// pour récupérer les données depuis GITHUB_RAW_URL.

require_once __DIR__ . '/config.php';

/**
 * Simule la récupération de la liste des fichiers de quiz depuis une source.
 *
 * @return array Une liste de noms de fichiers de quiz (ex: 'qcm-ia-beginner.json').
 */
function fetch_quiz_files_list_from_source(): array
{
    // Simulation : on retourne une liste de fichiers prédéfinis.
    // Dans un cas réel, on listerait les fichiers d'un dépôt.
    // Pour ce projet, on déplace les fichiers dans `oldfiles` et on les lit depuis là.

    $sourceDir = __DIR__ . '/../../oldfiles';

    if (!is_dir($sourceDir)) {
        // Crée le dossier et un fichier placeholder si le dossier n'existe pas
        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceDir . '/.gitkeep', '');
        return [];
    }

    $files = scandir($sourceDir);
    $quizFiles = [];
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json' || pathinfo($file, PATHINFO_EXTENSION) === 'html') {
            $quizFiles[] = $file;
        }
    }
    return $quizFiles;
}


/**
 * Récupère le contenu brut d'un fichier de quiz spécifique.
 *
 * @param string $filename Le nom du fichier à récupérer.
 * @return string|false Le contenu du fichier ou false en cas d'échec.
 */
function fetch_raw_quiz_content(string $filename): string|false
{
    $filePath = __DIR__ . '/../../oldfiles/' . $filename;
    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    }
    return false;
}

/**
 * Extrait le tableau de questions d'un contenu HTML.
 * Cherche une variable JavaScript `QUESTIONS = [...]`.
 *
 * @param string $htmlContent Le contenu HTML brut.
 * @return array Le tableau de questions décodé.
 * @throws Exception Si les questions ne peuvent pas être extraites.
 */
function extract_questions_from_html(string $htmlContent): array
{
    // Regex pour trouver la déclaration du tableau QUESTIONS.
    // Robuste aux espaces, sauts de ligne, et quotes simples/doubles.
    $pattern = '/const\s+QUESTIONS\s*=\s*(\[[\s\S]*?\]);/m';

    if (preg_match($pattern, $htmlContent, $matches)) {
        $json_string = $matches[1];

        // PHP ne peut pas parser directement du JS. On doit nettoyer la chaîne.
        // 1. Enlever les commentaires
        $json_string = preg_replace('/\/\/.*/', '', $json_string);
        // 2. Remplacer les quotes simples par des doubles pour les clés et les chaînes
        // Ceci est une simplification. Une solution plus robuste utiliserait un parser JS ou des regex plus complexes.
        // Pour ce cas, on suppose une structure JSON-like.
        // La regex suivante met des guillemets doubles autour des clés non quotées.
        $json_string = preg_replace('/([{,]\s*)(\w+)\s*:/', '$1"$2":', $json_string);
        // Remplace les apostrophes par des guillemets
        $json_string = str_replace("'", '"', $json_string);

        $questions = json_decode($json_string, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $questions;
        } else {
            // Tentative de nettoyage plus agressive si le premier essai échoue
            // Enlever les virgules en fin de tableau/objet
            $json_string = preg_replace('/,\s*([\}\]])/', '$1', $json_string);
            $questions = json_decode($json_string, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $questions;
            }
            throw new Exception("Erreur de parsing JSON après extraction : " . json_last_error_msg());
        }
    }

    throw new Exception("Impossible de trouver la constante 'QUESTIONS' dans le fichier HTML.");
}
?>