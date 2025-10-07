<?php
// Endpoint API: POST /api/attempts_create.php
// Crée une nouvelle tentative de quiz pour l'utilisateur connecté.

header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth.php';

// Cette action nécessite d'être connecté.
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['status' => 'error', 'message' => 'Méthode non autorisée.']);
}

// Récupérer les données du corps de la requête
$input = json_decode(file_get_contents('php://input'), true);
$quiz_id = $input['quiz_id'] ?? null;

if (!$quiz_id) {
    json_response(400, ['status' => 'error', 'message' => 'ID du quiz manquant.']);
}

try {
    $pdo = get_db_connection();
    $user = current_user();

    // 1. Vérifier si le quiz existe et récupérer son total_max_points
    $stmt_quiz = $pdo->prepare("SELECT total_max_points FROM quizzes WHERE id = ? AND is_active = 1");
    $stmt_quiz->execute([$quiz_id]);
    $quiz_max_points = $stmt_quiz->fetchColumn();

    if ($quiz_max_points === false) {
        json_response(404, ['status' => 'error', 'message' => 'Quiz non trouvé ou inactif.']);
    }

    // 2. Vérifier s'il y a déjà une tentative "in_progress" pour ce quiz et cet utilisateur
    $stmt_check = $pdo->prepare(
        "SELECT id FROM attempts WHERE user_id = ? AND quiz_id = ? AND status = 'in_progress'"
    );
    $stmt_check->execute([$user['id'], $quiz_id]);
    $existing_attempt_id = $stmt_check->fetchColumn();

    if ($existing_attempt_id) {
        // Si une tentative existe déjà, on la retourne au lieu d'en créer une nouvelle.
        // Cela permet de reprendre un quiz non terminé.
        json_response(200, [
            'status' => 'success',
            'message' => 'Tentative existante reprise.',
            'attempt_id' => $existing_attempt_id
        ]);
        return;
    }

    // 3. Créer la nouvelle tentative
    $stmt_create = $pdo->prepare(
        "INSERT INTO attempts (user_id, quiz_id, total_max, status) VALUES (?, ?, ?, 'in_progress')"
    );
    $stmt_create->execute([$user['id'], $quiz_id, $quiz_max_points]);

    $attempt_id = $pdo->lastInsertId();

    json_response(201, [
        'status' => 'success',
        'message' => 'Tentative créée avec succès.',
        'attempt_id' => $attempt_id
    ]);

} catch (PDOException $e) {
    // Gérer les erreurs de base de données (ex: quiz_id invalide)
    json_response(500, [
        'status' => 'error',
        'message' => 'Erreur de base de données lors de la création de la tentative.',
        'details' => $e->getMessage() // Pour le débogage
    ]);
} catch (Exception $e) {
    json_response(500, [
        'status' => 'error',
        'message' => 'Erreur interne du serveur.',
        'details' => $e->getMessage() // Pour le débogage
    ]);
}
?>