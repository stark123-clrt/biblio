<?php 

$base_path = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base_path = '../'; // Si on est dans le dossier admin
} elseif (strpos($_SERVER['PHP_SELF'], '/user/') !== false) {
    $base_path = '../'; // Si on est dans le dossier user
}

// ✅ MODERNISATION POO - Optionnel : Récupérer les statistiques du site
$site_stats = null;
try {
    // Charger les classes POO si pas déjà fait et si on veut afficher des stats
    if (class_exists('BookRepository') || (file_exists($base_path . "classes/Core.php"))) {
        if (!class_exists('BookRepository')) {
            require_once $base_path . "classes/Core.php";
            require_once $base_path . "classes/Models.php"; 
            require_once $base_path . "classes/Repositories.php";
        }
        
        // Récupérer quelques statistiques pour le footer
        $bookRepository = new BookRepository();
        $userRepository = new UserRepository();
        
        $site_stats = [
            'total_books' => $bookRepository->countAll(),
            'total_users' => $userRepository->countAll(),
            'total_views' => $bookRepository->countTotalViews()
        ];
    }
} catch (Exception $e) {
    // En cas d'erreur, continuer sans statistiques
    error_log("Footer stats loading error: " . $e->getMessage());
    $site_stats = null;
}
?>

<footer class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-12">
    <div class="container mx-auto px-4 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
            <!-- Brand Section -->
            <div class="space-y-5">
                <div class="flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-blue-200">Bibliothèque Chrétienne</span>
                </div>
                <p class="text-gray-300 leading-relaxed">Une plateforme dédiée à l'enrichissement spirituel à travers la lecture et la méditation.</p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors duration-300 transform hover:-translate-y-1">
                        <i class="fab fa-facebook-f text-lg"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors duration-300 transform hover:-translate-y-1">
                        <i class="fab fa-twitter text-lg"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors duration-300 transform hover:-translate-y-1">
                        <i class="fab fa-instagram text-lg"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors duration-300 transform hover:-translate-y-1">
                        <i class="fab fa-youtube text-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-xl font-semibold mb-6 relative pb-2 text-blue-200">
                    <span class="border-b-2 border-blue-500 pb-2">Liens Rapides</span>
                </h3>
                <ul class="space-y-3">
                    <li><a href="<?php echo $base_path; ?>index.php" class="text-gray-300 hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-blue-400"></i> Accueil</a></li>
                    <li><a href="<?php echo $base_path; ?>library.php" class="text-gray-300 hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-blue-400"></i> Bibliothèque</a></li>
                    <li><a href="<?php echo $base_path; ?>testimonials.php" class="text-gray-300 hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-blue-400"></i> Témoignages</a></li>
                    <li><a href="#" class="text-gray-300 hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-blue-400"></i> À Propos</a></li>
                    <li><a href="#" class="text-gray-300 hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-blue-400"></i> Contact</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h3 class="text-xl font-semibold mb-6 relative pb-2 text-blue-200">
                    <span class="border-b-2 border-blue-500 pb-2">Contact</span>
                </h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <i class="fas fa-envelope text-blue-400 mt-1 mr-3"></i>
                        <a href="mailto:contact@bibliotheque-chretienne.com" class="text-gray-300 hover:text-blue-400 transition-colors">contact@bibliotheque-chretienne.com</a>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-phone text-blue-400 mt-1 mr-3"></i>
                        <a href="tel:+1234567890" class="text-gray-300 hover:text-blue-400 transition-colors">+123 456 7890</a>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt text-blue-400 mt-1 mr-3"></i>
                        <span class="text-gray-300">123 Rue de l'Église, 75000 Paris</span>
                    </div>
                </div>
            </div>

            <!-- Newsletter -->
            <div>
                <h3 class="text-xl font-semibold mb-6 relative pb-2 text-blue-200">
                    <span class="border-b-2 border-blue-500 pb-2">Newsletter</span>
                </h3>
                <p class="text-gray-300 mb-4">Abonnez-vous pour recevoir les dernières nouveautés et méditations.</p>
                <form class="space-y-3">
                    <input type="email" placeholder="Votre email" class="w-full px-4 py-2 rounded bg-gray-700 border border-gray-600 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-500 text-white transition">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-6 rounded transition-all duration-300 transform hover:scale-105 shadow-lg">
                        S'abonner
                    </button>
                </form>
            </div>
        </div>

        <!-- Copyright -->
        <div class="border-t border-gray-700 mt-12 pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm mb-4 md:mb-0">
                    &copy; <?php echo date('Y'); ?> Bibliothèque Chrétienne. Tous droits réservés. | Développé par <span class="text-blue-400">Christian Ondiyo</span>
                </p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-blue-400 text-sm transition-colors">Mentions légales</a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 text-sm transition-colors">Politique de confidentialité</a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 text-sm transition-colors">Conditions d'utilisation</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" class="fixed bottom-8 right-8 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg opacity-0 invisible transition-all duration-300 transform translate-y-4">
    <i class="fas fa-arrow-up"></i>
</button>

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

    // Back to Top Button
    $(document).ready(function(){
        $(window).scroll(function(){
            if ($(this).scrollTop() > 300) {
                $('#backToTop').removeClass('opacity-0 invisible translate-y-4').addClass('opacity-100 visible translate-y-0');
            } else {
                $('#backToTop').removeClass('opacity-100 visible translate-y-0').addClass('opacity-0 invisible translate-y-4');
            }
        });
        
        $('#backToTop').click(function(){
            $('html, body').animate({scrollTop : 0}, 800);
            return false;
        });
    });
</script>
</body>
</html>