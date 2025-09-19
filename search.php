<?php
// search.php - Version 100% POO avec séparation MVC complète
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    //INITIALISATION DES REPOSITORIES
    $bookRepository = new BookRepository();
    $categoryRepository = new CategoryRepository();
    
    //RÉCUPÉRER LES PARAMÈTRES DE RECHERCHE
    $search_query = trim($_GET['q'] ?? '');
    $category_id = intval($_GET['category'] ?? 0);
    $sort = $_GET['sort'] ?? 'relevance';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 16;
    $offset = ($page - 1) * $per_page;
    
    //RÉCUPÉRER LES LIVRES SELON LES CRITÈRES (RETOURNE DES OBJETS Book)
    if (!empty($search_query)) {
        // Recherche par terme avec tri et pagination
        $books = $bookRepository->searchWithSort($search_query, $sort, $per_page, $offset);
        $total_books = $bookRepository->countSearch($search_query);
    } elseif ($category_id > 0) {
        // Filtrage par catégorie avec tri et pagination
        $books = $bookRepository->findByCategoryWithSort($category_id, $sort, $per_page, $offset);
        $total_books = $bookRepository->countByCategory($category_id);
    } else {
        // Tous les livres avec tri et pagination
        $books = $bookRepository->findAllWithSort($sort, $per_page, $offset);
        $total_books = $bookRepository->countAll();
    }
    
    // CALCULER LA PAGINATION
    $total_pages = ceil($total_books / $per_page);
    

    // RÉCUPÉRER LES CATÉGORIES (RETOURNE DES OBJETS Category)
    $categories = $categoryRepository->findWithBookCount();
    

    // PRÉPARER LES VARIABLES POUR LA VUE
    $selected_category_name = '';
    if ($category_id > 0) {
        foreach ($categories as $cat) {
            if ($cat->getId() == $category_id) {
                $selected_category_name = $cat->getName();
                break;
            }
        }
    }
    


} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur lors de la recherche : " . $e->getMessage());
    } else {
        error_log("Search error: " . $e->getMessage());
        $books = [];
        $categories = [];
        $total_books = 0;
        $total_pages = 0;
        $selected_category_name = '';
    }
} 


// FONCTION HELPER POUR SURLIGNER LES TERMES DE RECHERCHE
function highlightSearchTerms($text, $searchQuery) {
    if (empty($searchQuery) || empty($text)) {
        return htmlspecialchars($text);
    }
    
    $escapedText = htmlspecialchars($text);
    $pattern = '/' . preg_quote($searchQuery, '/') . '/i';
    return preg_replace($pattern, '<span class="search-highlight">$0</span>', $escapedText);
}

//PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = !empty($search_query) 
    ? "Recherche: " . htmlspecialchars($search_query)
    : "Recherche";

include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        <link rel="stylesheet" href="includes/style/search.css">
        
        <h1 class="text-3xl lg:text-4xl font-bold mb-6 dark:text-white flex items-center animate-fadeInUp">
            <i class="fas fa-search text-blue-600 dark:text-blue-500 mr-3"></i>
            <?php if (!empty($search_query)): ?>
                Résultats de recherche pour "<span class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($search_query); ?></span>"
            <?php else: ?>
                Recherche avancée
            <?php endif; ?>
        </h1>
        
        <!-- Filtres et options de recherche -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl shadow-xl border border-white/20 dark:border-gray-700/20 p-6 mb-8 animate-fadeInUp">
            <form action="search.php" method="GET" class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-400"></i>
                    Filtres de recherche
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="md:col-span-2">
                        <label for="q" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-search mr-1 text-blue-600 dark:text-blue-400"></i> Rechercher
                        </label>
                        <div class="relative">
                            <input type="text" id="q" name="q" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                   placeholder="Titre, auteur ou description...">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-tag mr-1 text-green-600 dark:text-green-400"></i> Catégorie
                        </label>
                        <select id="category" name="category" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="0">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category->getId(); ?>" 
                                        <?php echo $category_id == $category->getId() ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category->getName()); ?> 
                                    (<?php echo $category->getBookCount() ?? 0; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-sort mr-1 text-purple-600 dark:text-purple-400"></i> Trier par
                        </label>
                        <select id="sort" name="sort" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="relevance" <?php echo $sort == 'relevance' ? 'selected' : ''; ?>>Pertinence</option>
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                            <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                            <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Popularité</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Mieux notés</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg">
                        <i class="fas fa-search mr-2"></i> Rechercher
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Résultats de recherche -->
        <div class="mb-6 animate-fadeInUp delay-100">
            <?php if (!empty($search_query) || $category_id > 0): ?>
                <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/20 p-6 mb-8">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                        <h2 class="text-xl font-bold dark:text-white flex items-center mb-2 sm:mb-0">
                            <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-500"></i>
                            <?php echo $total_books; ?> résultat<?php echo $total_books != 1 ? 's' : ''; ?> trouvé<?php echo $total_books != 1 ? 's' : ''; ?>
                            <?php if (!empty($search_query)): ?>
                                pour "<span class="text-blue-600 dark:text-blue-400 font-semibold"><?php echo htmlspecialchars($search_query); ?></span>"
                            <?php endif; ?>
                            <?php if ($category_id > 0 && !empty($selected_category_name)): ?>
                                dans "<span class="text-green-600 dark:text-green-400 font-semibold"><?php echo htmlspecialchars($selected_category_name); ?></span>"
                            <?php endif; ?>
                        </h2>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="text-gray-600 dark:text-gray-400 flex items-center">
                                <i class="fas fa-file-alt mr-2"></i> Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($books)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                    <?php foreach ($books as $index => $book): ?>
                        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 overflow-hidden transition-all duration-500 hover:scale-105 hover:shadow-2xl group animate-fadeInUp" style="animation-delay: <?php echo ($index % 4 + 1) * 100; ?>ms">
                            <a href="book.php?id=<?php echo $book->getId(); ?>" class="block h-full">
                                <div class="relative overflow-hidden">
                                    <?php if ($book->hasCover()): ?>
                                        <img src="<?php echo htmlspecialchars($book->getCoverImagePath()); ?>" 
                                             alt="<?php echo htmlspecialchars($book->getTitle()); ?>" 
                                             class="w-full h-64 object-cover transition-transform duration-500 group-hover:scale-110"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-full h-64 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center" style="display:none;">
                                            <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-64 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                                            <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Badges -->
                                    <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                                        <!-- Badge catégorie -->
                                        <div class="bg-gradient-to-r from-blue-500/90 to-blue-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                            <?php echo htmlspecialchars($book->getCategoryName() ?? 'Non catégorisé'); ?>
                                        </div>
                                        
                                        <!-- Badge popularité -->
                                        <?php if ($book->getViewsCount() > 50): ?>
                                            <div class="bg-gradient-to-r from-orange-500/90 to-red-500/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm flex items-center">
                                                <i class="fas fa-fire mr-1"></i> Populaire
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="p-6">
                                    <h3 class="text-xl font-bold mb-2 dark:text-white line-clamp-2">
                                        <?php echo highlightSearchTerms($book->getTitle(), $search_query); ?>
                                    </h3>
                                    
                                    <?php if ($book->getAuthor()): ?>
                                        <p class="text-gray-600 dark:text-gray-400 mb-3">
                                            par <?php echo highlightSearchTerms($book->getAuthor(), $search_query); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($book->getDescription()): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3">
                                            <?php echo highlightSearchTerms($book->getShortDescription(120), $search_query); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Statistiques -->
                                    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-eye mr-1"></i>
                                            <?php echo $book->getFormattedViews(); ?> vues
                                        </div>
                                        <?php if ($book->getTotalReviews() > 0): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-comments mr-1"></i>
                                                <?php echo $book->getTotalReviews(); ?> avis
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Note avec étoiles -->
                                    <?php if ($book->getAvgRating() > 0): ?>
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center text-yellow-400">
                                                <?php foreach ($book->getStarsArray() as $isFilled): ?>
                                                    <i class="<?php echo $isFilled ? 'fas' : 'far'; ?> fa-star text-sm"></i>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                <?php echo round($book->getAvgRating(), 1); ?>/5
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex justify-center">
                                        <span class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium px-6 py-2 rounded-full transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                                            <i class="fas fa-book-open mr-2"></i>
                                            Découvrir
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination moderne -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-12 animate-fadeInUp delay-300">
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300 flex items-center">
                                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            ?>
                            
                            <?php if ($start_page > 1): ?>
                                <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=1" 
                                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300">
                                    1
                                </a>
                                <?php if ($start_page > 2): ?>
                                    <span class="bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="bg-blue-500 text-white px-4 py-2 rounded-lg font-medium">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                                       class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </span>
                                <?php endif; ?>
                                <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $total_pages; ?>" 
                                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2 transition-all duration-300">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
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
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Aucun résultat trouvé</h3>
                        <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            <?php if (!empty($search_query)): ?>
                                Aucun livre ne correspond à votre recherche "<span class="font-medium text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($search_query); ?></span>".
                            <?php else: ?>
                                Veuillez effectuer une recherche pour voir les résultats.
                            <?php endif; ?>
                        </p>
                        
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-6 mb-8 max-w-md mx-auto">
                            <p class="text-gray-600 dark:text-gray-400 mb-4 font-medium">Suggestions :</p>
                            <ul class="text-gray-600 dark:text-gray-400 text-left space-y-2">
                                <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Vérifiez l'orthographe des termes</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Essayez des termes plus généraux</li>
                                <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Utilisez des synonymes</li>
                            </ul>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <?php if (!empty($search_query)): ?>
                                <a href="search.php" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                                    <i class="fas fa-undo-alt mr-2"></i> Nouvelle recherche
                                </a>
                            <?php endif; ?>
                            <a href="library.php" class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                                <i class="fas fa-books mr-2"></i> Parcourir la bibliothèque
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>
<script src="includes/js/search.js"></script>