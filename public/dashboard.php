<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/db.php';
$pdo = get_db_connection();
$user = current_user();

$page_title = "Tableau de Bord";
require_once __DIR__ . '/partials/header.php';

// --- Récupération des données pour les KPIs ---
// 1. Nombre total de tentatives
$stmt_kpi = $pdo->prepare("
    SELECT
        COUNT(id) as total_attempts,
        AVG(CAST(score AS REAL) / total_max) * 100 as average_score_percentage,
        SUM(CASE WHEN finished_at IS NOT NULL THEN strftime('%s', finished_at) - strftime('%s', started_at) ELSE 0 END) as total_time_seconds
    FROM attempts
    WHERE user_id = ?
");
$stmt_kpi->execute([$user['id']]);
$kpis = $stmt_kpi->fetch();

// 2. Derniers quiz tentés (5 derniers)
$stmt_latest = $pdo->prepare("
    SELECT q.title, q.slug, a.finished_at
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.id
    WHERE a.user_id = ? AND a.status = 'finished'
    ORDER BY a.finished_at DESC
    LIMIT 5
");
$stmt_latest->execute([$user['id']]);
$latest_quizzes = $stmt_latest->fetchAll();

// --- Récupération des données pour les graphiques ---
// 1. Répartition des points par thème
// Cette requête est complexe. Elle va chercher chaque réponse, la lie à sa question,
// décode le thème, et somme les points.
$stmt_themes = $pdo->prepare("
    SELECT
        json_extract(q.payload, '$.theme') as theme,
        SUM(aa.points_earned) as total_points
    FROM attempt_answers aa
    JOIN attempts a ON aa.attempt_id = a.id
    JOIN questions q ON a.quiz_id = q.quiz_id AND aa.question_index = q.index_in_quiz
    WHERE a.user_id = ?
    GROUP BY theme
    HAVING total_points > 0
    ORDER BY total_points DESC
");
$stmt_themes->execute([$user['id']]);
$theme_data = $stmt_themes->fetchAll(PDO::FETCH_ASSOC);

// --- Récupération de l'historique des tentatives ---
$stmt_history = $pdo->prepare("
    SELECT
        q.title,
        a.id as attempt_id,
        a.started_at,
        a.finished_at,
        a.score,
        a.total_max,
        a.status
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.id
    WHERE a.user_id = ?
    ORDER BY a.started_at DESC
");
$stmt_history->execute([$user['id']]);
$history = $stmt_history->fetchAll();

function format_duration($seconds) {
    if ($seconds < 60) return $seconds . "s";
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return "{$m}m {$s}s";
}
?>

<div class="container mx-auto px-6 py-12">
    <h1 class="text-4xl font-bold mb-8">Tableau de Bord de <?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></h1>

    <!-- Section KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="bg-white p-6 rounded-lg shadow-lg text-center">
            <div class="text-5xl font-extrabold text-blue-600"><?= $kpis['total_attempts'] ?? 0 ?></div>
            <div class="text-gray-500 mt-2">Tentatives de Quiz</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg text-center">
            <div class="text-5xl font-extrabold text-green-600"><?= round($kpis['average_score_percentage'] ?? 0, 1) ?>%</div>
            <div class="text-gray-500 mt-2">Score Moyen</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg text-center">
            <div class="text-5xl font-extrabold text-purple-600"><?= round(($kpis['total_time_seconds'] ?? 0) / 3600, 1) ?>h</div>
            <div class="text-gray-500 mt-2">Temps total passé</div>
        </div>
    </div>

    <!-- Section Graphiques et Derniers Quiz -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-xl font-bold mb-4">Répartition des points par Thème</h3>
            <?php if (!empty($theme_data)): ?>
                <canvas id="theme-chart"></canvas>
            <?php else: ?>
                <p class="text-gray-500">Aucune donnée disponible. Participez à des quiz pour voir vos statistiques ici.</p>
            <?php endif; ?>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-xl font-bold mb-4">Derniers Quiz Terminés</h3>
            <?php if (!empty($latest_quizzes)): ?>
                <ul class="space-y-3">
                    <?php foreach ($latest_quizzes as $quiz): ?>
                        <li class="flex justify-between items-center text-sm">
                            <a href="quiz.php?slug=<?= htmlspecialchars($quiz['slug']) ?>" class="text-blue-600 hover:underline truncate pr-4"><?= htmlspecialchars($quiz['title']) ?></a>
                            <span class="text-gray-500 flex-shrink-0"><?= date('d/m/Y', strtotime($quiz['finished_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500">Aucun quiz terminé pour le moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section Historique des tentatives -->
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h3 class="text-xl font-bold mb-4">Historique des Tentatives</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durée</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $attempt): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($attempt['title']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($attempt['started_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($attempt['status'] == 'finished'): ?>
                                        <span class="font-bold"><?= $attempt['score'] ?></span> / <?= $attempt['total_max'] ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($attempt['finished_at']): ?>
                                        <?= format_duration(strtotime($attempt['finished_at']) - strtotime($attempt['started_at'])) ?>
                                    <?php else: ?>
                                        En cours
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($attempt['status'] == 'finished'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Terminé</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">En cours</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">Vous n'avez encore commencé aucun quiz.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeData = <?= json_encode($theme_data) ?>;

    if (themeData && themeData.length > 0) {
        const ctx = document.getElementById('theme-chart').getContext('2d');

        const labels = themeData.map(d => d.theme);
        const data = themeData.map(d => d.total_points);

        new Chart(ctx, {
            type: 'doughnut', // ou 'pie', 'bar'
            data: {
                labels: labels,
                datasets: [{
                    label: 'Points par Thème',
                    data: data,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(236, 72, 153, 0.7)'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: false,
                        text: 'Répartition des points par Thème'
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>