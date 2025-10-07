</main>
<footer class="bg-gray-800 text-white mt-24">
    <div class="container mx-auto px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Colonne 1: Pitch -->
            <div class="col-span-1">
                <h3 class="text-xl font-bold mb-4">QCM • IA</h3>
                <p class="text-gray-400">
                    Des questionnaires interactifs pour tester et approfondir vos connaissances en Intelligence Artificielle et autres domaines techniques.
                </p>
            </div>

            <!-- Colonne 2: Navigation -->
            <div class="col-span-1">
                <h4 class="text-lg font-semibold mb-4">Navigation</h4>
                <ul class="space-y-2">
                    <li><a href="<?= BASE_URL ?>" class="text-gray-400 hover:text-white transition">Accueil</a></li>
                    <li><a href="<?= BASE_URL ?>#catalogue" class="text-gray-400 hover:text-white transition">Catalogue des Quiz</a></li>
                    <li><a href="<?= BASE_URL ?>dashboard.php" class="text-gray-400 hover:text-white transition">Tableau de Bord</a></li>
                    <li><a href="<?= BASE_URL ?>signup.php" class="text-gray-400 hover:text-white transition">S'inscrire</a></li>
                </ul>
            </div>

            <!-- Colonne 3: Ressources -->
            <div class="col-span-1">
                <h4 class="text-lg font-semibold mb-4">Ressources</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="text-gray-400 hover:text-white transition">Blog</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition">Documentation API</a></li>
                    <li><a href="<?= BASE_URL ?>../oldfiles/index.html" class="text-gray-400 hover:text-white transition" target="_blank">Archives QCM</a></li>
                </ul>
            </div>

            <!-- Colonne 4: Contact -->
            <div class="col-span-1">
                <h4 class="text-lg font-semibold mb-4">Contact</h4>
                <ul class="space-y-2 text-gray-400">
                    <li>Email: contact@qcm-ia.example.com</li>
                    <li>Twitter: @QCM_IA</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-gray-700 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center text-sm">
            <p class="text-gray-500">
                © <?= date('Y') ?> QCM•IA. Tous droits réservés.
            </p>
            <div class="flex space-x-4 mt-4 md:mt-0">
                <a href="#" class="text-gray-500 hover:text-white transition">Conditions Générales d'Utilisation</a>
                <a href="#" class="text-gray-500 hover:text-white transition">Politique de Confidentialité</a>
                <a href="#" class="text-gray-500 hover:text-white transition">Mentions Légales</a>
            </div>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>assets/app.js" type="module"></script>
</body>
</html>