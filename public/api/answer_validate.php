<?php
// Endpoint API: POST /api/answer_validate.php
// Valide la réponse à une question, calcule les points et la stocke.

header('Content-Type: application/json');
require_once __DIR__ . '/../inc/responses.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/scoring.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['status' => 'error', 'message' => 'Méthode non autorisée.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$attempt_id = $input['attempt_id'] ?? null;
$question_index = $input['question_index'] ?? null;
$selection = $input['selection'] ?? null;

if ($attempt_id === null || $question_index === null || $selection === null) {
    json_response(400, ['status' => 'error', 'message' => 'Données manquantes (attempt_id, question_index, selection).']);
}

try {
    $pdo = get_db_connection();
    $user = current_user();

    // 1. Vérifier que la tentative appartient bien à l'utilisateur connecté
    $stmt_attempt = $pdo->prepare(
        "SELECT id, quiz_id, status FROM attempts WHERE id = ? AND user_id = ?"
    );
    $stmt_attempt->execute([$attempt_id, $user['id']]);
    $attempt = $stmt_attempt->fetch();

    if (!$attempt) {
        json_response(403, ['status' => 'error', 'message' => 'Tentative non trouvée ou accès non autorisé.']);
    }

    if ($attempt['status'] === 'finished') {
        json_response(400, ['status' => 'error', 'message' => 'Ce quiz est déjà terminé.']);
    }

    // 2. Vérifier si une réponse pour cette question n'a pas déjà été validée (règle de verrouillage)
    $stmt_check = $pdo->prepare(
        "SELECT id FROM attempt_answers WHERE attempt_id = ? AND question_index = ?"
    );
    $stmt_check->execute([$attempt_id, $question_index]);
    if ($stmt_check->fetch()) {
        json_response(409, ['status' => 'error', 'message' => 'Une réponse a déjà été validée pour cette question.']);
    }

    // 3. Récupérer la question correspondante depuis la DB pour faire le scoring côté serveur
    $stmt_question = $pdo->prepare(
        "SELECT payload FROM questions WHERE quiz_id = ? AND index_in_quiz = ?"
    );
    $stmt_question->execute([$attempt['quiz_id'], $question_index]);
    $question_payload = $stmt_question->fetchColumn();

    if (!$question_payload) {
        json_response(404, ['status' => 'error', 'message' => 'Question non trouvée.']);
    }
    $question = json_decode($question_payload, true);

    // 4. Calculer les points et le feedback
    $points_earned = calculate_points_for_selection($question, $selection);
    $max_question_points = calculate_max_points_for_question($question);
    $ratio = ($max_question_points > 0) ? ($points_earned / $max_question_points) : 0;
    $feedback_text = get_feedback_text($question, $ratio);

    // 5. Enregistrer la réponse dans la base de données
    $stmt_insert = $pdo->prepare(
        "INSERT INTO attempt_answers (attempt_id, question_index, selection, points_earned) VALUES (?, ?, ?, ?)"
    );
    // La sélection est stockée en tant que chaîne JSON
    $stmt_insert->execute([$attempt_id, $question_index, json_encode($selection), $points_earned]);

    // 6. Retourner le résultat de la validation
    json_response(200, [
        'status' => 'success',
        'points_earned' => $points_earned,
        'max_question_points' => $max_question_points,
        'feedback_text' => $feedback_text,
        'ratio' => $ratio
    ]);

} catch (PDOException $e) {
    json_response(500, [
        'status' => 'error',
        'message' => 'Erreur de base de données.',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    json_response(500, [
        'status' => 'error',
        'message' => 'Erreur interne du serveur.',
        'details' => $e->getMessage()
    ]);
}
?>