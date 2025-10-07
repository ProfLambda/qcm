<?php
// Endpoint API: POST /api/attempt_finish.php
// Termine une tentative, calcule le score final et renvoie un récapitulatif.

header('Content-Type: application/json');
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['status' => 'error', 'message' => 'Méthode non autorisée.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$attempt_id = $input['attempt_id'] ?? null;

if ($attempt_id === null) {
    json_response(400, ['status' => 'error', 'message' => 'ID de la tentative manquant.']);
}

try {
    $pdo = get_db_connection();
    $user = current_user();

    // 1. Vérifier que la tentative appartient bien à l'utilisateur et qu'elle est en cours.
    $stmt_attempt = $pdo->prepare(
        "SELECT id, quiz_id, status, total_max FROM attempts WHERE id = ? AND user_id = ?"
    );
    $stmt_attempt->execute([$attempt_id, $user['id']]);
    $attempt = $stmt_attempt->fetch();

    if (!$attempt) {
        json_response(403, ['status' => 'error', 'message' => 'Tentative non trouvée ou accès non autorisé.']);
    }

    if ($attempt['status'] === 'finished') {
        // Si déjà terminé, on recalcule le récap et on le renvoie sans modifier la DB.
    } else {
        // 2. Calculer le score final en sommant les points de toutes les réponses enregistrées.
        $stmt_score = $pdo->prepare(
            "SELECT SUM(points_earned) FROM attempt_answers WHERE attempt_id = ?"
        );
        $stmt_score->execute([$attempt_id]);
        $final_score = (int) $stmt_score->fetchColumn();

        // 3. Mettre à jour la tentative : statut, score et date de fin.
        $stmt_finish = $pdo->prepare(
            "UPDATE attempts SET score = ?, status = 'finished', finished_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt_finish->execute([$final_score, $attempt_id]);
    }

    // 4. Préparer le récapitulatif complet à renvoyer.
    // On récupère le score final depuis la DB pour être sûr.
     $stmt_final_data = $pdo->prepare(
        "SELECT score, total_max, started_at, finished_at FROM attempts WHERE id = ?"
    );
    $stmt_final_data->execute([$attempt_id]);
    $final_data = $stmt_final_data->fetch();

    // On récupère toutes les réponses et les questions associées pour un récap détaillé.
    $stmt_details = $pdo->prepare("
        SELECT
            q.index_in_quiz,
            q.payload AS question_payload,
            aa.selection AS user_selection,
            aa.points_earned
        FROM attempt_answers aa
        JOIN questions q ON aa.question_index = q.index_in_quiz
        JOIN attempts a ON aa.attempt_id = a.id AND q.quiz_id = a.quiz_id
        WHERE aa.attempt_id = ?
        ORDER BY q.index_in_quiz ASC
    ");
    $stmt_details->execute([$attempt_id]);
    $details_raw = $stmt_details->fetchAll();

    $detailed_results = [];
    foreach ($details_raw as $row) {
        $question_data = json_decode($row['question_payload'], true);
        $user_selection = json_decode($row['user_selection'], true);

        $detailed_results[] = [
            'question_index' => $row['index_in_quiz'],
            'question_text' => $question_data['question'],
            'user_selection' => $user_selection,
            'points_earned' => $row['points_earned'],
            'max_points' => calculate_max_points_for_question($question_data),
            'is_correct' => $row['points_earned'] >= calculate_max_points_for_question($question_data)
        ];
    }

    $summary = [
        'score' => $final_data['score'],
        'total_max' => $final_data['total_max'],
        'percentage' => $final_data['total_max'] > 0 ? round(($final_data['score'] / $final_data['total_max']) * 100, 2) : 0,
        'started_at' => $final_data['started_at'],
        'finished_at' => $final_data['finished_at'],
    ];

    json_response(200, [
        'status' => 'success',
        'message' => 'Quiz terminé.',
        'summary' => $summary,
        'detailed_results' => $detailed_results
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