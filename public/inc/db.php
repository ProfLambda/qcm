<?php
// Fichier de connexion à la base de données via PDO

require_once __DIR__ . '/config.php';

// Variable globale pour la connexion PDO, initialisée à null.
$pdo = null;

/**
 * Retourne une instance de connexion PDO.
 * Utilise un singleton pattern pour éviter des connexions multiples.
 *
 * @return PDO L'instance de PDO.
 * @throws PDOException Si la connexion échoue.
 */
function get_db_connection(): PDO
{
    global $pdo;

    // Si la connexion n'a pas encore été établie, on la crée.
    if ($pdo === null) {
        try {
            if (DB_DRIVER === 'sqlite') {
                $dbPath = DB_PATH;
                $dbDir = dirname($dbPath);

                // Crée le dossier /data s'il n'existe pas
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $pdo = new PDO('sqlite:' . $dbPath);
                // Activer les contraintes de clé étrangère pour SQLite
                $pdo->exec('PRAGMA foreign_keys = ON;');

            } elseif (DB_DRIVER === 'mysql') {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $options = [
                    PDO::ATTR_EMULATE_PREPARES   => false, // Désactive l'émulation des requêtes préparées
                    PDO::ATTR_STRINGIFY_FETCHES  => false, // Ne convertit pas les nombres en chaînes
                ];
                $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);

            } else {
                throw new Exception("Driver de base de données non supporté : " . DB_DRIVER);
            }

            // Configuration commune pour les deux drivers
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Lève des exceptions en cas d'erreur
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Récupère les résultats en tableau associatif

        } catch (PDOException $e) {
            // En cas d'échec de la connexion, on arrête tout et on affiche un message d'erreur.
            // En production, il faudrait logger cette erreur plutôt que de l'afficher.
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Erreur de connexion à la base de données.',
                'details' => $e->getMessage() // Uniquement pour le développement
            ]);
            exit; // Arrête l'exécution du script
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    return $pdo;
}

/**
 * Fonction utilitaire pour initialiser la base de données avec le schéma approprié.
 * Elle ne fait rien si les tables existent déjà.
 */
function initialize_database() {
    $pdo = get_db_connection();
    $schema_path = __DIR__ . '/../../schema/schema_' . DB_DRIVER . '.sql';

    if (!file_exists($schema_path)) {
        throw new Exception("Fichier de schéma non trouvé pour le driver " . DB_DRIVER);
    }

    try {
        // Vérifie si la table 'users' existe pour éviter de ré-exécuter le schéma
        $table_check = (DB_DRIVER === 'sqlite')
            ? "SELECT name FROM sqlite_master WHERE type='table' AND name='users'"
            : "SHOW TABLES LIKE 'users'";

        $stmt = $pdo->query($table_check);
        if ($stmt->fetch() === false) {
            // La table n'existe pas, on exécute le script de création
            $schema = file_get_contents($schema_path);
            $pdo->exec($schema);

            // Une fois le schéma créé, on peuple la base si elle est vide
            populate_database_from_json_if_empty();
        }
    } catch (PDOException $e) {
        // Gérer l'erreur d'initialisation
        throw new Exception("Erreur lors de l'initialisation de la base de données : " . $e->getMessage());
    }
}

/**
 * Peuple la base de données à partir du fichier quizzes.json si la table quizzes est vide.
 */
function populate_database_from_json_if_empty() {
    require_once __DIR__ . '/scoring.php';
    $pdo = get_db_connection();

    // Vérifier si la table des quiz est vide
    $stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
    if ($stmt->fetchColumn() > 0) {
        return; // La table n'est pas vide, on ne fait rien.
    }

    $json_path = __DIR__ . '/../data/quizzes.json';
    if (!file_exists($json_path)) {
        // Si le fichier n'existe pas, on tente de le construire
        echo "Fichier quizzes.json non trouvé. Tentative de construction...\n";
        require_once __DIR__ . '/../scripts/build_quizzes_json.php';
        build_json();
        if (!file_exists($json_path)) {
            throw new Exception("La construction du fichier quizzes.json a échoué.");
        }
    }

    $json_content = file_get_contents($json_path);
    $quizzes_data = json_decode($json_content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur de décodage du fichier quizzes.json: " . json_last_error_msg());
    }

    $pdo->beginTransaction();
    try {
        $insert_quiz_stmt = $pdo->prepare(
            "INSERT INTO quizzes (slug, title, level, themes, question_count, total_max_points, source_url) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $insert_question_stmt = $pdo->prepare(
            "INSERT INTO questions (quiz_id, index_in_quiz, payload) VALUES (?, ?, ?)"
        );

        foreach ($quizzes_data as $quiz) {
            if (empty($quiz['questions']) || !is_array($quiz['questions'])) continue;

            $questions = $quiz['questions'];
            $title = $quiz['title'] ?? 'Titre inconnu';
            $source_file = $quiz['source_file'] ?? 'N/A';

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

            $insert_quiz_stmt->execute([$slug, $title, $level, json_encode($themes), $question_count, $total_max_points, $source_file]);
            $quiz_id = $pdo->lastInsertId();

            foreach ($questions as $index => $question) {
                $insert_question_stmt->execute([$quiz_id, $index, json_encode($question)]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>