// IIFE pour encapsuler la logique du quiz runner
(function() {
    // S'exécute seulement sur la page du quiz runner
    const quizRunnerEl = document.getElementById('quiz-runner');
    if (!quizRunnerEl) {
        return;
    }

    // --- Éléments du DOM ---
    const questionContainer = document.getElementById('question-container');
    const feedbackContainer = document.getElementById('feedback-container');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const validateBtn = document.getElementById('validate-btn');
    const finishBtn = document.getElementById('finish-btn');
    const resultsModal = document.getElementById('results-modal');
    const resultsSummary = document.getElementById('results-summary');
    const exportJsonBtn = document.getElementById('export-json-btn');
    const exportCsvBtn = document.getElementById('export-csv-btn');

    // --- Données du Quiz ---
    const quizId = quizRunnerEl.dataset.quizId;
    const questions = JSON.parse(document.getElementById('quiz-data').textContent);
    const totalQuestions = questions.length;

    // --- État du Quiz ---
    let state = {
        currentQuestionIndex: 0,
        attemptId: null,
        answers: {}, // Stocke les sélections et l'état de validation de chaque question
        finalResults: null
    };

    /**
     * Initialise le quiz : crée une tentative et affiche la première question.
     */
    async function initializeQuiz() {
        try {
            const response = await fetch('api/attempts_create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ quiz_id: quizId })
            });

            if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);

            const data = await response.json();
            if (data.status !== 'success') throw new Error(data.message);

            state.attemptId = data.attempt_id;
            renderCurrentQuestion();
            updateNavigation();

        } catch (error) {
            console.error("Erreur d'initialisation du quiz:", error);
            questionContainer.innerHTML = `<div class="text-center text-red-500">Impossible de démarrer le quiz. Veuillez rafraîchir la page.</div>`;
        }
    }

    /**
     * Affiche la question actuelle dans le DOM.
     */
    function renderCurrentQuestion() {
        const question = questions[state.currentQuestionIndex];
        if (!question) return;

        // Effacer le contenu précédent
        questionContainer.innerHTML = '';

        // Titre de la question
        const questionTitle = document.createElement('h2');
        questionTitle.className = 'text-2xl font-semibold mb-6 text-gray-800';
        questionTitle.textContent = question.question;
        questionContainer.appendChild(questionTitle);

        // Conteneur pour les options
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'space-y-4';
        questionContainer.appendChild(optionsContainer);

        // Rendu spécifique au type de question
        switch (question.selectionType) {
            case 'single':
            case 'select':
                renderRadioOptions(optionsContainer, question);
                break;
            case 'multi':
            case 'multiselect':
                renderCheckboxOptions(optionsContainer, question);
                break;
            case 'toggle':
                renderToggleOption(optionsContainer, question);
                break;
            case 'range':
                renderRangeSlider(optionsContainer, question);
                break;
            case 'ranking':
                renderRankingList(optionsContainer, question);
                break;
            case 'image':
                 renderImageOptions(optionsContainer, question);
                 break;
            default:
                questionContainer.innerHTML = `<p>Type de question non supporté: ${question.selectionType}</p>`;
        }

        restoreAnswerState();
    }

    // --- Fonctions de rendu des types de questions ---

    function renderRadioOptions(container, question) {
        question.options.forEach(option => {
            const div = document.createElement('div');
            div.className = 'flex items-center p-3 rounded-lg border border-gray-200 transition-all';
            div.innerHTML = `
                <input id="${option.id}" name="q_option" type="radio" value="${option.id}" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 cursor-pointer">
                <label for="${option.id}" class="ml-3 block text-md font-medium text-gray-700 w-full cursor-pointer">${escapeHTML(option.label)}</label>
            `;
            container.appendChild(div);
        });
    }

    function renderCheckboxOptions(container, question) {
        question.options.forEach(option => {
            const div = document.createElement('div');
            div.className = 'flex items-center p-3 rounded-lg border border-gray-200 transition-all';
            div.innerHTML = `
                <input id="${option.id}" name="q_option" type="checkbox" value="${option.id}" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer">
                <label for="${option.id}" class="ml-3 block text-md font-medium text-gray-700 w-full cursor-pointer">${escapeHTML(option.label)}</label>
            `;
            container.appendChild(div);
        });
    }

    function renderToggleOption(container, question) {
        const option = question.options[0]; // Toggle a généralement une seule "option" binaire
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-4 rounded-lg border border-gray-200';
        div.innerHTML = `
            <span class="text-md font-medium text-gray-700">${escapeHTML(question.question)}</span>
            <label for="${option.id}" class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" value="${option.id}" id="${option.id}" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        `;
        // On remplace la question principale par le label du toggle
        questionContainer.querySelector('h2').textContent = option.label;
        container.appendChild(div);
    }

    function renderRangeSlider(container, question) {
        const config = question.rangeConfig;
        const div = document.createElement('div');
        div.className = 'p-4';
        div.innerHTML = `
            <input type="range" id="range-slider" min="${config.min}" max="${config.max}" step="${config.step || 1}" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
            <div class="flex justify-between text-xs text-gray-500 mt-2">
                <span>${config.minLabel || config.min}</span>
                <span id="range-value" class="font-bold text-blue-600 text-lg">${config.default || config.min}</span>
                <span>${config.maxLabel || config.max}</span>
            </div>
        `;
        container.appendChild(div);
        const slider = document.getElementById('range-slider');
        const valueDisplay = document.getElementById('range-value');
        slider.oninput = () => { valueDisplay.textContent = slider.value; };
        // Set default value
        slider.value = config.default || config.min;
        valueDisplay.textContent = slider.value;
    }

    function renderRankingList(container, question) {
        container.innerHTML = `<p class="text-sm text-gray-500 mb-4">Glissez-déposez les items pour les classer dans le bon ordre.</p>`;
        const list = document.createElement('ul');
        list.id = 'ranking-list';
        list.className = 'space-y-2 border rounded-lg p-2';
        question.options.forEach(option => {
            const item = document.createElement('li');
            item.dataset.id = option.id;
            item.draggable = true;
            item.className = 'p-3 bg-gray-100 rounded-md cursor-grab border flex items-center';
            item.innerHTML = `<svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg> ${escapeHTML(option.label)}`;
            list.appendChild(item);
        });
        container.appendChild(list);

        // Drag and drop logic
        let draggedItem = null;
        list.addEventListener('dragstart', e => {
            draggedItem = e.target;
            setTimeout(() => e.target.style.opacity = '0.5', 0);
        });
        list.addEventListener('dragend', e => {
            setTimeout(() => e.target.style.opacity = '1', 0);
            draggedItem = null;
        });
        list.addEventListener('dragover', e => {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        });
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function renderImageOptions(container, question) {
        container.className = 'grid grid-cols-2 md:grid-cols-3 gap-4';
        question.options.forEach(option => {
            const card = document.createElement('div');
            card.className = 'border rounded-lg p-3 text-center cursor-pointer transition-all hover:shadow-lg hover:border-blue-500';
            card.dataset.id = option.id;
            card.innerHTML = `
                <div class="text-5xl mb-2">${option.emoji || '🖼️'}</div>
                <p class="font-medium text-gray-700">${escapeHTML(option.label)}</p>
                <input type="radio" name="q_option" value="${option.id}" class="hidden">
            `;
            container.appendChild(card);
            card.addEventListener('click', () => {
                container.querySelectorAll('div').forEach(c => c.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500'));
                card.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
                card.querySelector('input').checked = true;
            });
        });
    }

    /**
     * Récupère la sélection de l'utilisateur depuis les inputs du DOM.
     */
    function getSelectionFromDOM() {
        const question = questions[state.currentQuestionIndex];
        switch (question.selectionType) {
            case 'single':
            case 'select':
            case 'image':
            case 'toggle':
                const checkedRadio = questionContainer.querySelector('input:checked');
                return checkedRadio ? checkedRadio.value : null;
            case 'multi':
            case 'multiselect':
                const checkedBoxes = [...questionContainer.querySelectorAll('input:checked')];
                return checkedBoxes.map(cb => cb.value);
            case 'range':
                return document.getElementById('range-slider').value;
            case 'ranking':
                const items = [...document.querySelectorAll('#ranking-list li')];
                return items.map(item => item.dataset.id);
            default:
                return null;
        }
    }

    /**
     * Restaure l'état de la question (sélection et verrouillage) si elle a déjà été répondue.
     */
    function restoreAnswerState() {
        const answerData = state.answers[state.currentQuestionIndex];
        if (!answerData) return;

        const { selection, validationResult } = answerData;
        const question = questions[state.currentQuestionIndex];

        // Restaurer la sélection
        switch (question.selectionType) {
            case 'single':
            case 'select':
            case 'toggle':
                const radioToSelect = document.querySelector(`input[value="${selection}"]`);
                if (radioToSelect) radioToSelect.checked = true;
                break;
            case 'multi':
            case 'multiselect':
                selection.forEach(selId => {
                    const checkToSelect = document.querySelector(`input[value="${selId}"]`);
                    if (checkToSelect) checkToSelect.checked = true;
                });
                break;
            case 'range':
                const slider = document.getElementById('range-slider');
                if(slider) {
                    slider.value = selection;
                    document.getElementById('range-value').textContent = selection;
                }
                break;
            case 'ranking':
                 // Non implémenté pour la simplicité, car l'ordre initial est perdu.
                 // Une vraie app stockerait l'ordre initial pour pouvoir le restaurer.
                break;
            case 'image':
                 const cardToSelect = document.querySelector(`div[data-id="${selection}"]`);
                 if(cardToSelect) {
                    cardToSelect.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
                    cardToSelect.querySelector('input').checked = true;
                 }
                 break;
        }

        // Si la question a été validée, on verrouille tout et on affiche le feedback
        if (validationResult) {
            lockInputs();
            displayFeedback(validationResult);
            highlightAnswers(validationResult.ratio);
        }
    }

    /**
     * Met à jour l'état des boutons de navigation (précédent, suivant, valider...).
     */
    function updateNavigation() {
        // Bouton Précédent
        prevBtn.disabled = state.currentQuestionIndex === 0;

        const isLastQuestion = state.currentQuestionIndex === totalQuestions - 1;
        const isValidated = state.answers[state.currentQuestionIndex]?.validationResult;

        validateBtn.classList.toggle('hidden', !!isValidated);
        nextBtn.classList.toggle('hidden', !isValidated || isLastQuestion);
        finishBtn.classList.toggle('hidden', !isValidated || !isLastQuestion);

        // Mise à jour de la barre de progression
        const progressPercent = ((state.currentQuestionIndex + 1) / totalQuestions) * 100;
        progressBar.style.width = `${progressPercent}%`;
        progressText.textContent = `Question ${state.currentQuestionIndex + 1} / ${totalQuestions}`;
    }

    /**
     * Gère le clic sur le bouton "Valider".
     */
    async function handleValidate() {
        const selection = getSelectionFromDOM();

        if (selection === null || (Array.isArray(selection) && selection.length === 0)) {
            questionContainer.classList.add('ring-2', 'ring-red-500');
            setTimeout(() => questionContainer.classList.remove('ring-2', 'ring-red-500'), 1500);
            return;
        }

        // Sauvegarder la sélection de l'utilisateur
        if (!state.answers[state.currentQuestionIndex]) {
            state.answers[state.currentQuestionIndex] = {};
        }
        state.answers[state.currentQuestionIndex].selection = selection;

        validateBtn.disabled = true;
        validateBtn.textContent = 'Validation...';

        try {
            const response = await fetch('api/answer_validate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: state.attemptId,
                    question_index: state.currentQuestionIndex,
                    selection: selection
                })
            });

            if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);

            const result = await response.json();
            if (result.status !== 'success') throw new Error(result.message);

            // Stocker le résultat de la validation
            state.answers[state.currentQuestionIndex].validationResult = result;

            lockInputs();
            displayFeedback(result);
            highlightAnswers(result.ratio);
            updateNavigation();

        } catch (error) {
            console.error('Erreur de validation:', error);
            feedbackContainer.textContent = `Erreur: ${error.message}`;
            feedbackContainer.className = 'mt-6 p-4 rounded-md text-center bg-red-100 text-red-700 opacity-100';
            validateBtn.disabled = false;
            validateBtn.textContent = 'Valider';
        }
    }

    /**
     * Affiche le feedback (correct, incorrect, partiel) après validation.
     */
    function displayFeedback(result) {
        feedbackContainer.innerHTML = `
            <span class="font-bold">Points: ${result.points_earned} / ${result.max_question_points}</span>
            <p>${escapeHTML(result.feedback_text)}</p>
        `;
        let feedbackClass = 'bg-feedback-incorrect text-red-800';
        if (result.ratio >= 1.0) {
            feedbackClass = 'bg-feedback-correct text-green-800';
        } else if (result.ratio > 0) {
            feedbackClass = 'bg-feedback-partial text-yellow-800';
        }
        feedbackContainer.className = `mt-6 p-4 rounded-md text-center transition-opacity duration-300 opacity-100 ${feedbackClass}`;
    }

    /**
     * Désactive tous les inputs de la question.
     */
    function lockInputs() {
        questionContainer.querySelectorAll('input, button, select, textarea, li').forEach(el => {
            el.disabled = true;
            el.classList.add('disabled:opacity-70', 'disabled:cursor-not-allowed');
            if(el.tagName === 'LI') el.draggable = false;
        });
    }

    /**
     * Met en surbrillance les bonnes et mauvaises réponses.
     */
    function highlightAnswers(ratio) {
        const question = questions[state.currentQuestionIndex];
        const inputs = questionContainer.querySelectorAll('input[name="q_option"]');

        inputs.forEach(input => {
            const option = question.options.find(opt => opt.id == input.value);
            if (!option) return;

            const parentDiv = input.closest('div'); // La div qui entoure l'input et le label
            if (!parentDiv) return;

            if (option.points > 0) { // C'est une bonne réponse
                parentDiv.classList.add('bg-green-100', 'border-green-300');
            } else if (input.checked) { // C'est une mauvaise réponse qui a été cochée
                parentDiv.classList.add('bg-red-100', 'border-red-300');
            }
        });

        // Pour les images, le `div` est le parent direct
        if (question.selectionType === 'image') {
             const cards = questionContainer.querySelectorAll('div[data-id]');
             cards.forEach(card => {
                 const option = question.options.find(opt => opt.id == card.dataset.id);
                 if (!option) return;
                 const isChecked = card.querySelector('input').checked;

                 if (option.points > 0) {
                    card.classList.add('bg-green-100', 'border-green-400');
                 } else if(isChecked) {
                    card.classList.add('bg-red-100', 'border-red-400');
                 }
             });
        }
    }

    /**
     * Navigue vers la question suivante.
     */
    function handleNext() {
        if (state.currentQuestionIndex < totalQuestions - 1) {
            state.currentQuestionIndex++;
            feedbackContainer.className = 'mt-6 p-4 rounded-md text-center transition-opacity duration-300 opacity-0';
            renderCurrentQuestion();
            updateNavigation();
        }
    }

    /**
     * Navigue vers la question précédente.
     */
    function handlePrev() {
        if (state.currentQuestionIndex > 0) {
            state.currentQuestionIndex--;
            feedbackContainer.className = 'mt-6 p-4 rounded-md text-center transition-opacity duration-300 opacity-0';
            renderCurrentQuestion();
            updateNavigation();
        }
    }

    /**
     * Termine le quiz et affiche la modale de résultats.
     */
    async function handleFinish() {
        finishBtn.disabled = true;
        finishBtn.textContent = 'Chargement...';

         try {
            const response = await fetch('api/attempt_finish.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attempt_id: state.attemptId })
            });

            if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);

            const results = await response.json();
            if (results.status !== 'success') throw new Error(results.message);

            state.finalResults = results;
            displayFinalResults();

        } catch (error) {
            console.error('Erreur de finalisation:', error);
            resultsSummary.innerHTML = `<p class="text-red-500">Impossible de charger les résultats. ${error.message}</p>`;
        } finally {
            resultsModal.classList.remove('hidden');
        }
    }

    /**
     * Affiche les résultats finaux dans la modale.
     */
    function displayFinalResults() {
        const { summary, detailed_results } = state.finalResults;
        const percentage = summary.percentage;
        let colorClass = 'text-red-600';
        if (percentage >= 80) colorClass = 'text-green-600';
        else if (percentage >= 50) colorClass = 'text-yellow-600';

        resultsSummary.innerHTML = `
            <div class="text-center mb-6">
                <p class="text-lg text-gray-600">Votre score final</p>
                <p class="text-6xl font-extrabold ${colorClass}">${summary.score} / ${summary.total_max}</p>
                <p class="text-2xl font-bold ${colorClass}">(${percentage}%)</p>
            </div>
            <h4 class="text-lg font-semibold mb-2">Récapitulatif des réponses :</h4>
            <ul class="space-y-2 max-h-60 overflow-y-auto pr-2">
                ${detailed_results.map(res => `
                    <li class="flex items-center justify-between text-sm p-2 rounded ${res.is_correct ? 'bg-green-50' : 'bg-red-50'}">
                        <span class="truncate pr-4">${escapeHTML(res.question_text)}</span>
                        <span class="font-bold flex-shrink-0">${res.points_earned} / ${res.max_points} pts</span>
                    </li>
                `).join('')}
            </ul>
        `;
    }

    /**
     * Gère l'export des résultats en JSON.
     */
    function handleExportJson() {
        if (!state.finalResults) return;
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(state.finalResults, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", `quiz_results_${quizId}_${state.attemptId}.json`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    /**
     * Gère l'export des résultats en CSV.
     */
    function handleExportCsv() {
        if (!state.finalResults) return;
        const { detailed_results } = state.finalResults;
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Question,Question Text,Points Earned,Max Points,Correct\r\n";

        detailed_results.forEach(res => {
            const row = [
                res.question_index + 1,
                `"${res.question_text.replace(/"/g, '""')}"`,
                res.points_earned,
                res.max_points,
                res.is_correct ? 'Yes' : 'No'
            ].join(",");
            csvContent += row + "\r\n";
        });

        const encodedUri = encodeURI(csvContent);
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", encodedUri);
        downloadAnchorNode.setAttribute("download", `quiz_results_${quizId}_${state.attemptId}.csv`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    function escapeHTML(str) {
        const p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }

    // --- Ajout des écouteurs d'événements ---
    prevBtn.addEventListener('click', handlePrev);
    nextBtn.addEventListener('click', handleNext);
    validateBtn.addEventListener('click', handleValidate);
    finishBtn.addEventListener('click', handleFinish);
    exportJsonBtn.addEventListener('click', handleExportJson);
    exportCsvBtn.addEventListener('click', handleExportCsv);

    // Gestion du clavier
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' && !nextBtn.hidden) {
            handleNext();
        } else if (e.key === 'ArrowLeft' && !prevBtn.disabled) {
            handlePrev();
        } else if (e.key.toLowerCase() === 'v' && !validateBtn.hidden) {
            e.preventDefault(); // Empêcher d'écrire 'v' dans un input
            handleValidate();
        }
    });

    // --- Démarrage ---
    initializeQuiz();
})();