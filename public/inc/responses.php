<?php
// Fichier contenant les fonctions d'aide pour les réponses API.

if (!function_exists('json_response')) {
    /**
     * Envoie une réponse JSON standardisée avec un code de statut HTTP.
     *
     * @param int $status_code Le code de statut HTTP (ex: 200, 404, 500).
     * @param array $data Le tableau de données à encoder en JSON.
     */
    function json_response(int $status_code, array $data)
    {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
?>