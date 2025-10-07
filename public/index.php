<?php
$page_title = "Accueil - Catalogue des Quiz";
require_once __DIR__ . '/partials/header.php';
?>

<div class="container mx-auto px-6 py-12">
    <!-- Section Hero -->
    <div class="text-center bg-white p-12 rounded-lg shadow-lg mb-16">
        <h1 class="text-5xl font-extrabold text-gray-800 mb-4">Testez vos Connaissances</h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Explorez notre collection de quiz sur l'Intelligence Artificielle, le développement web, et bien plus. Prêt à relever le défi ?
        </p>
        <a href="#catalogue" class="mt-8 inline-block bg-blue-600 text-white font-bold py-3 px-8 rounded-full hover:bg-blue-700 transition-transform transform hover:scale-105">
            Voir les quiz
        </a>
    </div>

    <!-- Section Catalogue -->
    <section id="catalogue">
        <h2 class="text-3xl font-bold text-center mb-10">Catalogue des Quiz</h2>

        <div id="quiz-list-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Les cartes de quiz seront injectées ici par JavaScript -->
            <div class="text-center p-8 text-gray-500">Chargement des quiz...</div>
        </div>
        <div id="quiz-list-error" class="hidden text-center p-8 text-red-500 bg-red-100 rounded-lg"></div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('quiz-list-container');
    const errorContainer = document.getElementById('quiz-list-error');

    fetch('<?= BASE_URL ?>api/quizzes.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && data.quizzes.length > 0) {
                container.innerHTML = ''; // Vider le message de chargement
                data.quizzes.forEach(quiz => {
                    const card = createQuizCard(quiz);
                    container.appendChild(card);
                });
            } else if (data.quizzes.length === 0) {
                 container.innerHTML = `<div class="col-span-full text-center p-8 text-gray-500">Aucun quiz n'est disponible pour le moment. <a href="admin/quizzes_import.php" class="text-blue-600 hover:underline">Importer des quiz ?</a></div>`;
            } else {
                throw new Error(data.message || 'Erreur lors de la récupération des quiz.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            container.innerHTML = '';
            errorContainer.classList.remove('hidden');
            errorContainer.textContent = 'Impossible de charger les quiz. Veuillez réessayer plus tard.';
        });

    function createQuizCard(quiz) {
        const card = document.createElement('a');
        card.href = `<?= BASE_URL ?>quiz.php?slug=${quiz.slug}`;
        card.className = 'bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden transform hover:-translate-y-1 block';

        const themes = quiz.themes ? JSON.parse(quiz.themes).map(theme =>
            `<span class="bg-blue-100 text-blue-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded-full">${escapeHTML(theme)}</span>`
        ).join(' ') : '';

        card.innerHTML = `
            <div class="p-6">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-bold text-gray-900">${escapeHTML(quiz.title)}</h3>
                    <span class="text-sm font-medium text-white ${getLevelColor(quiz.level)} py-1 px-3 rounded-full">${escapeHTML(quiz.level)}</span>
                </div>
                <div class="flex items-center text-gray-500 text-sm space-x-4 mb-4">
                    <span>
                        <svg class="w-4 h-4 inline -mt-1 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        ${quiz.question_count} questions
                    </span>
                    <span>
                        <svg class="w-4 h-4 inline -mt-1 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        ~${Math.ceil(quiz.question_count * 1.5)} min
                    </span>
                </div>
                <div class="h-16">
                    ${themes}
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <span class="text-blue-600 font-semibold hover:text-blue-800">Commencer le quiz &rarr;</span>
            </div>
        `;
        return card;
    }

    function getLevelColor(level) {
        switch (level.toLowerCase()) {
            case 'débutant': return 'bg-green-500';
            case 'intermédiaire': return 'bg-yellow-500';
            case 'avancé': return 'bg-red-500';
            case 'expert': return 'bg-purple-600';
            default: return 'bg-gray-500';
        }
    }

    function escapeHTML(str) {
        const p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>