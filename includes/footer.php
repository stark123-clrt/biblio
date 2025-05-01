</main>

    <?php 
    // Déterminer le chemin de base en fonction de l'emplacement du fichier actuel
    $base_path = '';
    if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
        $base_path = '../'; // Si on est dans le dossier admin
    } elseif (strpos($_SERVER['PHP_SELF'], '/user/') !== false) {
        $base_path = '../'; // Si on est dans le dossier user
    }
    ?>

    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Bibliothèque Chrétienne</h3>
                    <p class="mb-4">Une plateforme de lecture en ligne dédiée à l'enrichissement spirituel.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Liens Rapides</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo $base_path; ?>index.php" class="hover:text-blue-300">Accueil</a></li>
                        <li><a href="<?php echo $base_path; ?>library.php" class="hover:text-blue-300">Bibliothèque</a></li>
                        <li><a href="<?php echo $base_path; ?>testimonials.php" class="hover:text-blue-300">Témoignages</a></li>
                        <li><a href="#" class="hover:text-blue-300">À Propos</a></li>
                        <li><a href="#" class="hover:text-blue-300">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Contact</h3>
                    <p class="mb-2"><i class="fas fa-envelope mr-2"></i> contact@bibliotheque-chretienne.com</p>
                    <p class="mb-2"><i class="fas fa-phone mr-2"></i> +123 456 7890</p>
                    <p><i class="fas fa-map-marker-alt mr-2"></i> 123 Rue de l'Église, 75000 Paris</p>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-6 text-center">
                <p>&copy; <?php echo date('Y'); ?> Bibliothèque Chrétienne - Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <!-- Script AJAX global -->
    <script>
        // Fonction pour sauvegarder la progression de lecture sans rafraîchir la page
        function saveReadingProgress(bookId, pageNumber) {
            $.ajax({
                url: '<?php echo $base_path; ?>ajax/save_progress.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    page_number: pageNumber
                },
                success: function(response) {
                    console.log('Progression sauvegardée');
                },
                error: function(error) {
                    console.error('Erreur lors de la sauvegarde de la progression', error);
                }
            });
        }
    </script>
</body>
</html>