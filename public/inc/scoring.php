<?php
// Fichier contenant la logique de calcul des scores pour les questions

/**
 * Calcule le nombre maximum de points qu'il est possible d'obtenir pour une question donnée.
 *
 * @param array $question L'objet question (depuis le payload JSON).
 * @return int Le nombre maximum de points.
 */
function calculate_max_points_for_question(array $question): int
{
    if (!isset($question['selectionType'])) {
        return 0;
    }

    switch ($question['selectionType']) {
        case 'single':
        case 'select':
        case 'image':
        case 'toggle':
            // Le max est le plus grand nombre de points parmi les options.
            return array_reduce($question['options'], function ($max, $option) {
                return max($max, $option['points'] ?? 0);
            }, 0);

        case 'multi':
        case 'multiselect':
            // Le max est la somme de tous les points positifs.
            return array_reduce($question['options'], function ($sum, $option) {
                return $sum + (isset($option['points']) && $option['points'] > 0 ? $option['points'] : 0);
            }, 0);

        case 'range':
            // Le max est le plus grand nombre de points parmi les bandes de valeurs.
            return array_reduce($question['rangeConfig']['bands'], function ($max, $band) {
                return max($max, $band['points'] ?? 0);
            }, 0);

        case 'ranking':
            // Le max est le nombre d'items multiplié par les points par item correct.
            $pointsPerItem = $question['rankingConfig']['pointsPerItem'] ?? 1;
            $itemCount = count($question['options']);
            return $pointsPerItem * $itemCount;

        default:
            return 0;
    }
}

/**
 * Calcule les points obtenus par un utilisateur pour une sélection donnée sur une question.
 *
 * @param array $question L'objet question.
 * @param mixed $selection La sélection de l'utilisateur (peut être un ID, un tableau d'IDs, une valeur, un ordre...).
 * @return int Les points gagnés.
 */
function calculate_points_for_selection(array $question, $selection): int
{
    if (!isset($question['selectionType'])) {
        return 0;
    }

    switch ($question['selectionType']) {
        case 'single':
        case 'select':
        case 'image':
        case 'toggle':
            // Trouve l'option choisie et retourne ses points.
            foreach ($question['options'] as $option) {
                // La sélection est l'ID de l'option.
                if ($option['id'] == $selection) {
                    return $option['points'] ?? 0;
                }
            }
            return 0;

        case 'multi':
        case 'multiselect':
            // Somme les points des options cochées (la sélection est un tableau d'IDs).
            if (!is_array($selection)) return 0;
            $total = 0;
            $selection_ids = array_flip($selection); // Pour une recherche rapide
            foreach ($question['options'] as $option) {
                if (isset($selection_ids[$option['id']])) {
                    // On ne somme que les points positifs, pas de malus.
                    $total += max(0, $option['points'] ?? 0);
                }
            }
            return $total;

        case 'range':
            // Trouve dans quelle "bande" la valeur sélectionnée tombe.
            $value = intval($selection);
            foreach ($question['rangeConfig']['bands'] as $band) {
                if ($value >= $band['min'] && $value <= $band['max']) {
                    return $band['points'] ?? 0;
                }
            }
            return 0;

        case 'ranking':
            // Compare l'ordre de la sélection à l'ordre correct.
            if (!is_array($selection)) return 0; // la sélection est un tableau d'IDs ordonné

            $correctOrder = $question['rankingConfig']['correctOrder'] ?? [];
            $pointsPerItem = $question['rankingConfig']['pointsPerItem'] ?? 1;
            $score = 0;

            $count = min(count($selection), count($correctOrder));
            for ($i = 0; $i < $count; $i++) {
                if ($selection[$i] == $correctOrder[$i]) {
                    $score += $pointsPerItem;
                }
            }
            return $score;

        default:
            return 0;
    }
}

/**
 * Fournit le texte de feedback basé sur le ratio de points obtenus.
 *
 * @param array $question L'objet question.
 * @param float $ratio (points_obtenus / max_points_question).
 * @return string Le texte de feedback approprié.
 */
function get_feedback_text(array $question, float $ratio): string
{
    if ($ratio >= 1.0) {
        return $question['feedback_correct'] ?? "Excellente réponse !";
    } elseif ($ratio > 0) {
        return $question['feedback_partial'] ?? "C'est en partie correct. Continuez comme ça !";
    } else {
        return $question['feedback_incorrect'] ?? "Ce n'est pas tout à fait ça. Essayez encore !";
    }
}
?>