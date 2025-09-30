<?php
// testimonials.php - Version 100% POO avec séparation MVC complète
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    // ✅ INITIALISATION DES REPOSITORIES
    $commentRepository = new CommentRepository();
    $bookRepository = new BookRepository();
    
    // ✅ RÉCUPÉRER LES PARAMÈTRES DE FILTRAGE ET PAGINATION
    $book_id = intval($_GET['book_id'] ?? 0);
    $rating = intval($_GET['rating'] ?? 0);
    $sort = $_GET['sort'] ?? 'recent';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 9;
    $offset = ($page - 1) * $per_page;
    
    // ✅ RÉCUPÉRER LES TÉMOIGNAGES AVEC OBJETS (SELON LES FILTRES)
    if ($book_id > 0) {
        // Témoignages pour un livre spécifique avec filtres
        $testimonials = $commentRepository->findTestimonialsWithFilters($book_id, $rating, $sort, $per_page, $offset);
        $total_testimonials = $commentRepository->countTestimonialsWithFilters($book_id, $rating);
    } else {
        // Tous les témoignages avec filtres
        $testimonials = $commentRepository->findTestimonialsWithFilters(0, $rating, $sort, $per_page, $offset);
        $total_testimonials = $commentRepository->countTestimonialsWithFilters(0, $rating);
    }
    
    // ✅ CALCULER LA PAGINATION
    $total_pages = ceil($total_testimonials / $per_page);
    
    // ✅ RÉCUPÉRER LES LIVRES AVEC COMMENTAIRES (RETOURNE DES OBJETS Book)
    $books = $bookRepository->findBooksWithComments();
    
    // ✅ STATISTIQUES GLOBALES (MÉTHODES DE REPOSITORY)
    $all_testimonials = $commentRepository->findTestimonials(1000);
    $total_ratings = count($all_testimonials);
    
    if ($total_ratings > 0) {
        $sum_ratings = 0;
        foreach ($all_testimonials as $testimonial) {
            $sum_ratings += $testimonial->getRating();
        }
        $avg_rating = round($sum_ratings / $total_ratings, 1);
    } else {
        $avg_rating = 0;
    }
    
    // ✅ RÉPARTITION DES NOTES AVEC OBJETS
    $ratings_distribution = [];
    for ($i = 5; $i >= 1; $i--) {
        $count = 0;
        foreach ($all_testimonials as $testimonial) {
            if ($testimonial->getRating() == $i) {
                $count++;
            }
        }
        $percentage = $total_ratings > 0 ? round(($count / $total_ratings) * 100) : 0;
        $ratings_distribution[$i] = [
            'count' => $count,
            'percentage' => $percentage
        ];
    }
    
    // ✅ PRÉPARER LES VARIABLES POUR LA VUE
    $stats = [
        'total_ratings' => $total_ratings,
        'avg_rating' => $avg_rating
    ];
    
    $selected_book_title = '';
    if ($book_id > 0) {
        foreach ($books as $book) {
            if ($book->getId() == $book_id) {
                $selected_book_title = $book->getTitle();
                break;
            }
        }
    }
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur lors du chargement des témoignages : " . $e->getMessage());
    } else {
        error_log("Testimonials error: " . $e->getMessage());
        $testimonials = [];
        $books = [];
        $total_testimonials = 0;
        $total_pages = 0;
        $stats = ['total_ratings' => 0, 'avg_rating' => 0];
        $ratings_distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratings_distribution[$i] = ['count' => 0, 'percentage' => 0];
        }
        $selected_book_title = '';
    }
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Recommandations";
include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        <!-- En-tête moderne avec gradient -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                Recommandations des Lecteurs
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Découvrez les avis authentiques et les recommandations de notre communauté de lecteurs passionnés
            </p>
        </div>
        
        <!-- Statistiques globales avec design moderne -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-2xl border border-white/20 dark:border-gray-700/20 p-8 mb-12">
            <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-8 lg:space-y-0 lg:space-x-12">
                <!-- Note moyenne avec design moderne -->
                <div class="flex-shrink-0 text-center">
                    <div class="relative inline-block">
                        <div class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-2xl">
                            <div class="text-center text-white">
                                <div class="text-3xl font-extrabold"><?php echo $avg_rating; ?></div>
                                <div class="text-sm font-medium opacity-90">/5</div>
                            </div>
                        </div>
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-yellow-500 rounded-full border-4 border-white dark:border-gray-800 shadow-lg flex items-center justify-center">
                            <i class="fas fa-star text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-yellow-400 text-xl mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $avg_rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div class="text-gray-600 dark:text-gray-400 font-medium">
                            <?php echo number_format($stats['total_ratings']); ?> recommandations
                        </div>
                    </div>
                </div>
                
                <!-- Répartition des notes avec design moderne -->
                <div class="flex-1 w-full">
                    <h3 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white"></h3>
                    <div class="space-y-3">
                        <?php foreach ($ratings_distribution as $rating_val => $data): ?>
                            <div class="flex items-center group">
                                <div class="text-sm w-16 text-gray-600 dark:text-gray-400 font-medium">
                                    <?php echo $rating_val; ?> étoile<?php echo $rating_val > 1 ? 's' : ''; ?>
                                </div>
                                <div class="flex-1 mx-4">
                                    <div class="bg-gray-200 rounded-full h-3 dark:bg-gray-700 overflow-hidden">
                                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-1000 ease-out group-hover:from-purple-500 group-hover:to-blue-600" 
                                             style="width: <?php echo $data['percentage']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-sm w-20 text-gray-600 dark:text-gray-400 font-medium text-right">
                                    <?php echo $data['count']; ?> (<?php echo $data['percentage']; ?>%)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Résultats de filtrage -->
        <?php if ($book_id > 0 || $rating > 0): ?>
            <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/20 p-6 mb-8">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                    <h2 class="text-xl font-bold dark:text-white flex items-center mb-2 sm:mb-0">
                        <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-500"></i>
                        <?php echo $total_testimonials; ?> recommandation<?php echo $total_testimonials != 1 ? 's' : ''; ?> trouvée<?php echo $total_testimonials != 1 ? 's' : ''; ?>
                        <?php if ($book_id > 0 && !empty($selected_book_title)): ?>
                            pour "<span class="text-blue-600 dark:text-blue-400 font-semibold"><?php echo htmlspecialchars($selected_book_title); ?></span>"
                        <?php endif; ?>
                        <?php if ($rating > 0): ?>
                            avec <?php echo $rating; ?> étoile<?php echo $rating > 1 ? 's' : ''; ?>
                        <?php endif; ?>
                    </h2>
                    
                    <a href="testimonials.php" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 mt-2 sm:mt-0 inline-flex items-center font-medium transition-all duration-300 hover:scale-105">
                        <i class="fas fa-times-circle mr-2"></i> Effacer les filtres
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Filtres avec design moderne -->
        <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-xl border border-white/20 dark:border-gray-700/20 p-6 mb-8">
            <form action="testimonials.php" method="GET" class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Livre -->
                    <div>
                        <label for="book_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-book mr-1 text-blue-600 dark:text-blue-400"></i> Livre
                        </label>
                        <select id="book_id" name="book_id" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="0">Tous les livres</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo $book->getId(); ?>" <?php echo $book_id == $book->getId() ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($book->getTitle()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Note -->
                    <div>
                        <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-star mr-1 text-yellow-500"></i> Note
                        </label>
                        <select id="rating" name="rating" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="0">Toutes les notes</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $rating == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- Tri -->
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-sort mr-1 text-purple-600 dark:text-purple-400"></i> Trier par
                        </label>
                        <select id="sort" name="sort" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                            <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Mieux notés</option>
                            <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Moins bien notés</option>
                        </select>
                    </div>
                    
                    <!-- Bouton -->
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg">
                            <i class="fas fa-search mr-2"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Grille des recommandations -->
        <?php if (!empty($testimonials)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="group bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 overflow-hidden transition-all duration-500 hover:scale-105 hover:shadow-2xl">
                        <!-- En-tête de la carte -->
                        <div class="flex items-center p-6 bg-gradient-to-r from-gray-50 to-blue-50 dark:from-gray-800 dark:to-gray-700 border-b border-gray-100 dark:border-gray-600">
                            <div class="flex-shrink-0">
                                <?php if ($testimonial->getCoverImage()): ?>
                                    <?php
                                    // Nettoyer le chemin de l'image comme dans l'ancien code
                                    $testimonial_image_path = $testimonial->getCoverImage();
                                    if (strpos($testimonial_image_path, '../') === 0) {
                                        $testimonial_image_path = substr($testimonial_image_path, 3);
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($testimonial_image_path); ?>" 
                                         alt="<?php echo htmlspecialchars($testimonial->getBookTitle()); ?>" 
                                         class="w-16 h-16 object-cover rounded-xl shadow-lg transition-transform duration-300 group-hover:scale-110"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-16 h-16 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-600 dark:to-gray-700 flex items-center justify-center rounded-xl shadow-lg" style="display:none;">
                                        <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-16 h-16 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-600 dark:to-gray-700 flex items-center justify-center rounded-xl shadow-lg">
                                        <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="font-bold text-lg">
                                    <a href="book.php?id=<?php echo $testimonial->getBookId(); ?>" 
                                       class="text-blue-600 hover:text-blue-800 transition-colors dark:text-blue-400 dark:hover:text-blue-300">
                                        <?php echo htmlspecialchars($testimonial->getBookTitle()); ?>
                                    </a>
                                </h3>
                                <div class="text-yellow-400 text-lg mt-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $testimonial->getRating()): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contenu de la recommandation -->
                        <div class="p-6">
                            <p class="text-gray-700 dark:text-gray-300 mb-6 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($testimonial->getCommentText())); ?>
                            </p>
                            
                            <!-- Informations utilisateur -->
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-gray-600 dark:text-gray-400">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-white text-xs"></i>
                                    </div>
                                    <span class="font-semibold"><?php echo htmlspecialchars($testimonial->getUsername()); ?></span>
                                </div>
                                <div class="flex items-center text-gray-500 dark:text-gray-400">
                                    <i class="far fa-calendar-alt mr-2"></i>
                                    <?php echo $testimonial->getFormattedDate(); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Badge recommandation populaire -->
                        <?php if ($testimonial->getRating() >= 5): ?>
                            <div class="bg-gradient-to-r from-yellow-400/20 to-orange-400/20 backdrop-blur-sm px-6 py-3 border-t border-yellow-200/20 dark:border-yellow-800/20">
                                <span class="text-yellow-700 dark:text-yellow-300 text-sm font-semibold flex items-center">
                                    <i class="fas fa-award mr-2"></i> Recommandation exceptionnelle
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination moderne -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center">
                    <div class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300 flex items-center">
                                <i class="fas fa-chevron-left mr-1"></i> Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="bg-blue-500 text-white px-4 py-2 rounded-lg font-medium">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300 flex items-center">
                                Suivant <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- État vide moderne -->
            <div class="text-center py-20">
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 p-12">
                    <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Aucune recommandation trouvée</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                        <?php if ($book_id > 0 || $rating > 0): ?>
                            Aucune recommandation ne correspond à vos critères de recherche.
                        <?php else: ?>
                            Il n'y a pas encore de recommandations validées sur notre site.
                        <?php endif; ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if ($book_id > 0 || $rating > 0): ?>
                            <a href="testimonials.php" 
                               class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                                <i class="fas fa-undo-alt mr-2"></i>Toutes les recommandations
                            </a>
                        <?php endif; ?>
                        <a href="library.php" 
                           class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                            <i class="fas fa-book-open mr-2"></i>Parcourir la bibliothèque
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Section d'appel à l'action moderne -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="bg-gradient-to-r from-blue-500/10 to-purple-500/10 backdrop-blur-lg rounded-3xl border border-blue-200/20 dark:border-blue-800/20 p-8 mt-16">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-heart mr-2 text-red-500"></i>
                        Partagez vos recommandations
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-2xl mx-auto">
                        Avez-vous lu un livre dans notre bibliothèque ? Partagez votre recommandation et aidez les autres lecteurs à découvrir de nouveaux ouvrages exceptionnels.
                    </p>
                    <div class="flex justify-center">
                        <a href="library.php" 
                           class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                            <i class="fas fa-pen mr-2"></i>Laisser une recommandation
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>
<script src="includes/js/testimonials.js"></script>