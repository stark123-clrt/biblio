<?php
// category.php - Version 100% POO avec séparation MVC complète
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'ID de la catégorie est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: library.php");
    exit();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    //INITIALISATION DES REPOSITORIES
    $categoryRepository = new CategoryRepository();
    $bookRepository = new BookRepository();
    
    $category_id = intval($_GET['id']);
    
    //RÉCUPÉRER LA CATÉGORIE (RETOURNE UN OBJET Category)
    $category = $categoryRepository->findById($category_id);
    
    // Si la catégorie n'existe pas, rediriger
    if (!$category) {
        header("Location: library.php");
        exit();
    }
    
    //PARAMÈTRES DE TRI ET PAGINATION
    $sort = $_GET['sort'] ?? 'newest';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 12;
    $offset = ($page - 1) * $per_page;
    
    //RÉCUPÉRER LES LIVRES DE CETTE CATÉGORIE (RETOURNE DES OBJETS Book)
    $books = $bookRepository->findByCategoryWithSort($category_id, $sort, $per_page, $offset);
    
    //COMPTER LE TOTAL POUR LA PAGINATION
    $total_books = $bookRepository->countByCategory($category_id);
    $total_pages = ceil($total_books / $per_page);
    
    //RÉCUPÉRER TOUTES LES CATÉGORIES (RETOURNE DES OBJETS Category)
    $all_categories = $categoryRepository->findAll();
    
    //LOGIQUE DES SOUS-CATÉGORIES ET PARENT (avec objets)
    $subcategories = [];
    $parent_category = null;
    
    // Note: Cette logique assume qu'on pourrait avoir un parent_id dans le futur
    // Pour l'instant on la garde simple avec les catégories existantes
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur lors du chargement de la catégorie : " . $e->getMessage());
    } else {
        error_log("Category error: " . $e->getMessage());
        header("Location: library.php");
        exit();
    }
}

//PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = $category->getName();
include "includes/header.php";
?>
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <link rel="stylesheet" href="includes/style/category.css">
    
    <!-- Hero Section -->
    <section class="hero-category py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <!-- Breadcrumb moderne -->
            <nav class="breadcrumb-modern text-white/90 mb-8">
                <a href="index.php" class="hover:text-white transition-colors">
                    <i class="fas fa-home mr-2"></i>Accueil
                </a>
                <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                <a href="library.php" class="hover:text-white transition-colors">Bibliothèque</a>
                <?php if ($parent_category): ?>
                    <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                    <a href="category.php?id=<?php echo $parent_category->getId(); ?>" class="hover:text-white transition-colors">
                        <?php echo htmlspecialchars($parent_category->getName()); ?>
                    </a>
                <?php endif; ?>
                <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                <span class="text-white font-medium"><?php echo htmlspecialchars($category->getName()); ?></span>
            </nav>

            <div class="text-center text-white">
                <h1 class="text-4xl lg:text-6xl font-bold mb-6">
                    <?php echo htmlspecialchars($category->getName()); ?>
                </h1>
                <?php if ($category->getDescription()): ?>
                    <p class="text-xl lg:text-2xl mb-8 opacity-90 max-w-3xl mx-auto leading-relaxed">
                        <?php echo htmlspecialchars($category->getDescription()); ?>
                    </p>
                <?php endif; ?>
                <div class="stats-badge inline-block">
                    <?php echo $total_books; ?> livre<?php echo $total_books > 1 ? 's' : ''; ?> disponible<?php echo $total_books > 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-12">
        <!-- Sous-catégories -->
        <?php if (!empty($subcategories)): ?>
            <div class="mb-16 slide-up">
                <h2 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white text-center">
                    <i class="fas fa-sitemap mr-3 text-blue-600 dark:text-blue-400"></i>
                    Sous-catégories
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($subcategories as $index => $subcat): ?>
                        <a href="category.php?id=<?php echo $subcat->getId(); ?>" 
                           class="subcategory-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300 slide-up" 
                           style="transition-delay: <?php echo $index * 0.1; ?>s">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4 text-white">
                                <i class="fas fa-folder text-xl"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white">
                                <?php echo htmlspecialchars($subcat->getName()); ?>
                            </h3>
                            <?php if ($subcat->getDescription()): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    <?php echo htmlspecialchars($subcat->getDescription()); ?>
                                </p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Options de tri -->
        <div class="glass-card rounded-2xl p-6 mb-8 flex flex-col lg:flex-row justify-between items-center gap-4 slide-up">
            <div class="flex items-center gap-4">
                <div class="text-gray-600 dark:text-gray-300 font-medium">
                    <i class="fas fa-books mr-2 text-blue-600 dark:text-blue-400"></i>
                    Affichage de <?php echo count($books); ?> sur <?php echo $total_books; ?> livre<?php echo $total_books > 1 ? 's' : ''; ?>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <label class="text-gray-600 dark:text-gray-300 font-medium flex items-center">
                    <i class="fas fa-sort mr-2 text-purple-600 dark:text-purple-400"></i>
                    Trier par:
                </label>
                <select id="sort-select" class="sort-dropdown focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                    <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                    <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Popularité</option>
                    <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Mieux notés</option>
                </select>
            </div>
        </div>
        
        <!-- Liste des livres -->
        <?php if (!empty($books)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8" id="booksGrid">
                <?php foreach ($books as $index => $book): ?>
                    <div class="glass-card rounded-3xl overflow-hidden book-card slide-up" style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <a href="book.php?id=<?php echo $book->getId(); ?>" class="block h-full">
                            <div class="relative overflow-hidden">
                                <?php if ($book->hasCover()): ?>
                                    <img src="<?php echo htmlspecialchars($book->getCoverImagePath()); ?>" 
                                         alt="<?php echo htmlspecialchars($book->getTitle()); ?>" 
                                         class="w-full h-72 object-cover transition-transform duration-500 hover:scale-110"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-full h-72 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center" style="display:none;">
                                        <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-72 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                                        <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Overlay avec gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 hover:opacity-100 transition-opacity duration-300">
                                    <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                                        <!-- Badge catégorie -->
                                        <div class="bg-gradient-to-r from-blue-500/90 to-blue-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                            <?php echo htmlspecialchars($book->getCategoryName() ?? 'Non catégorisé'); ?>
                                        </div>
                                        
                                        <!-- Badge note si disponible -->
                                        <?php if ($book->getAvgRating() > 0): ?>
                                            <div class="bg-gradient-to-r from-yellow-500/90 to-orange-500/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm flex items-center">
                                                <i class="fas fa-star mr-1"></i>
                                                <?php echo round($book->getAvgRating(), 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <h3 class="text-xl font-bold mb-3 text-gray-800 dark:text-white line-clamp-2">
                                    <?php echo htmlspecialchars($book->getTitle()); ?>
                                </h3>
                                
                                <?php if ($book->getAuthor()): ?>
                                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                                        par <span class="font-medium"><?php echo htmlspecialchars($book->getAuthor()); ?></span>
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
                                
                                <!-- Bouton d'action -->
                                <div class="flex items-center justify-between">
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">Découvrir</span>
                                    <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 transform transition-transform group-hover:translate-x-2"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination moderne -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-modern">
                    <?php if ($page > 1): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                           class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                           class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- État vide moderne -->
            <div class="text-center py-20">
                <div class="glass-card rounded-3xl p-12 max-w-md mx-auto">
                    <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Aucun livre trouvé</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                        Il n'y a pas encore de livres disponibles dans cette catégorie.
                    </p>
                    <a href="library.php" class="inline-block bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg">
                        <i class="fas fa-arrow-left mr-2"></i>Retour à la bibliothèque
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include "includes/footer.php"; ?>
<script src="includes/js/category.js"></script>