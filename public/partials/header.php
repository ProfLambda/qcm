<?php
require_once __DIR__ . '/../inc/auth.php';
$current_user = current_user();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><circle cx='32' cy='32' r='30' stroke='dodgerblue' stroke-width='6' fill='white'/><text y='50%' x='50%' text-anchor='middle' dominant-baseline='middle' font-size='40' font-family='Arial' fill='dodgerblue'>?</text></svg>"
        type="image/svg+xml">

    <title><?= $page_title ?? 'QCM & IA' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'feedback-correct': 'rgba(210, 247, 226, 0.9)',
                        'feedback-partial': 'rgba(254, 243, 199, 0.9)',
                        'feedback-incorrect': 'rgba(254, 226, 226, 0.9)',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
</head>

<body class="bg-gray-50 text-gray-800 antialiased">
    <header id="main-header" class="fixed inset-x-0 top-0 z-50 bg-transparent backdrop-blur supports-[backdrop-filter]:bg-white/30 transition-all duration-300">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="<?= BASE_URL ?>" class="text-2xl font-bold text-gray-800 hover:text-blue-600 transition">QCM•IA</a>

            <!-- Menu pour écrans larges -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="<?= BASE_URL ?>#catalogue" class="text-gray-700 hover:text-blue-600">Catalogue</a>
                <a href="<?= BASE_URL ?>dashboard.php" class="text-gray-700 hover:text-blue-600">Tableau de bord</a>
                <a href="<?= BASE_URL ?>../qcm_originaux/index.html" class="text-gray-700 hover:text-blue-600" target="_blank">Anciens QCM</a>
                <span class="h-6 w-px bg-gray-300" aria-hidden="true"></span>
                <?php if ($current_user): ?>
                    <span class="text-gray-600">Bonjour, <?= htmlspecialchars($current_user['display_name'] ?: $current_user['email']) ?></span>
                    <a href="<?= BASE_URL ?>logout.php" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition">Déconnexion</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>login.php" class="text-gray-700 hover:text-blue-600">Connexion</a>
                    <a href="<?= BASE_URL ?>signup.php" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition">Inscription</a>
                <?php endif; ?>
            </div>

            <!-- Menu burger pour mobile -->
            <div class="md:hidden">
                <details class="relative">
                    <summary class="list-none cursor-pointer">
                        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                        </svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                        <a href="<?= BASE_URL ?>#catalogue" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Catalogue</a>
                        <a href="<?= BASE_URL ?>dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Tableau de bord</a>
                        <a href="<?= BASE_URL ?>../qcm_originaux/index.html" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" target="_blank">Anciens QCM</a>
                        <div class="border-t my-1"></div>
                        <?php if ($current_user): ?>
                            <div class="px-4 py-2 text-sm text-gray-500">
                                Connecté: <?= htmlspecialchars($current_user['display_name'] ?: $current_user['email']) ?>
                            </div>
                            <a href="<?= BASE_URL ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Déconnexion</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>login.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Connexion</a>
                            <a href="<?= BASE_URL ?>signup.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Inscription</a>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </nav>
    </header>
    <script>
        // Script pour la transparence de la navbar au scroll
        document.addEventListener('DOMContentLoaded', () => {
            const header = document.getElementById('main-header');
            if (header) {
                const handleScroll = () => {
                    if (window.scrollY > 8) {
                        header.classList.add('bg-white/70', 'shadow-sm');
                    } else {
                        header.classList.remove('bg-white/70', 'shadow-sm');
                    }
                };
                window.addEventListener('scroll', handleScroll, {
                    passive: true
                });
                handleScroll(); // Appliquer au chargement
            }
        });
    </script>
    <main class="pt-24"> <!-- Padding-top pour laisser de la place à la navbar fixe -->