<?php
// Endpoint API: GET /api/quizzes.php
// Retourne la liste des quiz actifs.

header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

// On n'a pas besoin d'être authentifié pour voir la liste des quiz.

try {
    $pdo = get_db_connection();

    // Sélectionne les quiz qui sont marqués comme actifs.
    $stmt = $pdo->query(
        "SELECT id, slug, title, level, themes, question_count, total_max_points
         FROM quizzes
         WHERE is_active = 1
         ORDER BY title ASC"
    );

    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'quizzes' => $quizzes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur interne du serveur.',
        'details' => $e->getMessage() // Pour le débogage
    ]);
}
?>