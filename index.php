<?php

session_start();

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    // Créer les repositories
    $userRepository = new UserRepository();
    $bookRepository = new BookRepository();
    $categoryRepository = new CategoryRepository();
    $commentRepository = new CommentRepository();
    
    // Récupérer les statistiques globales (simples compteurs)
    $stats = [
        'total_books' => $bookRepository->countAll(),
        'total_users' => $userRepository->countAll(),
        'total_categories' => $categoryRepository->countAll(),
        'total_views' => $bookRepository->countTotalViews()
    ];
    
    // RÉCUPÉRER LES LIVRES EN VEDETTE (déjà des objets Book!)
    $featuredBooks = $bookRepository->findFeatured(4);
    
    // ÉCUPÉRER LES DERNIERS LIVRES (déjà des objets Book!)
    $recentBooks = $bookRepository->findRecent(8);
    
    // RÉCUPÉRER LES CATÉGORIES (déjà des objets Category!)
    $categories = $categoryRepository->findWithBookCount(6);
    
    // RÉCUPÉRER LES TÉMOIGNAGES (déjà des objets Comment!)
    $testimonials = $commentRepository->findTestimonials(3);
    
} catch (Exception $e) {
    // Gestion d'erreur avec objets vides
    if (config('app.debug', false)) {
        die("Erreur lors du chargement de la page d'accueil : " . $e->getMessage());
    } else {
        error_log("Index page error: " . $e->getMessage());
        $stats = ['total_books' => 0, 'total_users' => 0, 'total_categories' => 0, 'total_views' => 0];
        $featuredBooks = [];
        $recentBooks = [];
        $categories = [];
        $testimonials = [];
    }
}


include "includes/header.php";

$page_title = "Accueil";

?>
<div class="min-h-screen">
    <link rel="stylesheet" href="includes/style/index.css">
    <!-- Hero Section Moderne -->
    <section class="hero-gradient min-h-screen flex items-center relative">
        <div class="container mx-auto px-4 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Contenu texte -->
                <div class="text-white">
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6 leading-tight">
                        <span class="block">Bibliothèque</span>
                    </h1>
                    <p class="text-xl lg:text-2xl mb-8 opacity-90 leading-relaxed">
                        Découvrez une collection exceptionnelle de livres pour nourrir votre connaissance.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="library.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold text-lg inline-flex items-center justify-center">
                            <i class="fas fa-book-open mr-3"></i> Explorer la bibliothèque
                        </a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-8 py-4 rounded-2xl font-bold text-lg inline-flex items-center justify-center transition-all duration-300 border border-white/30">
                                <i class="fas fa-user-plus mr-3"></i> Rejoindre gratuitement
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistiques flottantes -->
                <div class="lg:block hidden">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_books']; ?>"><?php echo $stats['total_books']; ?>+</div>
                            <div class="text-white/80 relative z-10">Livres disponibles</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -2s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_users']; ?>"><?php echo $stats['total_users']; ?>+</div>
                            <div class="text-white/80 relative z-10">Lecteurs inscrits</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -4s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_categories']; ?>"><?php echo $stats['total_categories']; ?>+</div>
                            <div class="text-white/80 relative z-10">Catégories</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -6s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_views']; ?>"><?php echo number_format($stats['total_views']); ?>+</div>
                            <div class="text-white/80 relative z-10">Lectures totales</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 text-white animate-bounce">
            <i class="fas fa-chevron-down text-2xl opacity-60"></i>
        </div>
    </section>

    <!-- Livres en vedette -->
    <section class="py-20 bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
                
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Découvrez notre sélection de livres soigneusement choisis pour enrichir votre parcours 
                </p>
            </div>
            
            <?php if (!empty($featuredBooks)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($featuredBooks as $index => $book): ?>
                        <div class="glass-card rounded-3xl overflow-hidden modern-card slide-up-enter" style="transition-delay: <?php echo $index * 0.1; ?>s">
                            <a href="book.php?id=<?php echo $book->getId(); ?>" class="block">
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
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <span class="bg-blue-500/80 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm">
                                                <?php echo htmlspecialchars($book->getCategoryName() ?? 'Non catégorisé'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-6">
                                    <h3 class="text-xl font-bold mb-3 text-gray-800 dark:text-white line-clamp-2">
                                        <?php echo htmlspecialchars($book->getTitle()); ?>
                                    </h3>
                                    <?php if (!empty($book->getAuthor())): ?>
                                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                                            par <span class="font-medium"><?php echo htmlspecialchars($book->getAuthor()); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">En savoir plus</span>
                                        <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 transform transition-transform group-hover:translate-x-2"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-book text-6xl text-gray-400 mb-4"></i>
                    <p class="text-xl text-gray-500 dark:text-gray-400">Aucun livre en vedette pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Catégories modernes -->
    <section class="py-20 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gray-800 dark:text-white">
                    Explorez par <span class="bg-gradient-to-r from-green-500 to-blue-500 bg-clip-text text-transparent">catégorie</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Trouvez facilement les livres qui correspondent à vos centres d'intérêt spirituels
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($categories as $index => $category): ?>
                    <a href="category.php?id=<?php echo $category->getId(); ?>" 
                       class="category-card rounded-2xl p-8 text-center hover:scale-105 transition-all duration-300 slide-up-enter" 
                       style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-6 text-white">
                            <i class="fas fa-tag text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-3 text-gray-800 dark:text-white">
                            <?php echo htmlspecialchars($category->getName()); ?>
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php echo $category->getFormattedBookCount(); ?> disponible<?php echo ($category->getBookCount() ?? 0) > 1 ? 's' : ''; ?>
                        </p>
                        <div class="inline-flex items-center text-blue-600 dark:text-blue-400 font-medium">
                            Explorer <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="library.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold inline-flex items-center">
                    <i class="fas fa-th-large mr-3"></i> Voir toutes les catégories
                </a>
            </div>
        </div>
    </section>

    <!-- Témoignages modernes -->
    <?php if (!empty($testimonials)): ?>
    <section class="py-20 bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gray-800 dark:text-white">
                    Ce que disent nos <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">lecteurs</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Découvrez les témoignages authentiques de notre communauté de lecteurs passionnés
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                    <div class="testimonial-modern rounded-2xl p-8 hover:scale-105 transition-all duration-300 slide-up-enter" 
                         style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <!-- Étoiles -->
                        <div class="flex justify-center mb-6">
                            <div class="text-yellow-400 text-xl">
                                <?php foreach ($testimonial->getStarsArray() as $isFilled): ?>
                                    <i class="fas fa-star<?php echo $isFilled ? '' : ' far'; ?>"></i>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Citation -->
                        <div class="relative mb-8">
                            <i class="fas fa-quote-left text-4xl text-blue-500/20 absolute -top-2 -left-2"></i>
                            <p class="text-gray-700 dark:text-gray-300 text-lg leading-relaxed italic pl-8">
                                <?php echo htmlspecialchars($testimonial->getCommentText()); ?>
                            </p>
                        </div>
                        
                        <!-- Auteur -->
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-gray-800 dark:text-white text-lg">
                                    <?php echo htmlspecialchars($testimonial->getUsername()); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    À propos de "<?php echo htmlspecialchars($testimonial->getBookTitle()); ?>"
                                </div>
                            </div>
                            <a href="book.php?id=<?php echo $testimonial->getBookId(); ?>" 
                               class="w-12 h-12 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center transition-colors">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="testimonials.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold inline-flex items-center">
                    <i class="fas fa-comments mr-3"></i> Voir tous les témoignages
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Final -->
    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="py-20 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="max-w-4xl mx-auto slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    Prêt à commencer votre <span class="text-yellow-400">voyage spirituel</span> ?
                </h2>
                <p class="text-xl lg:text-2xl mb-12 opacity-90 leading-relaxed">
                    Rejoignez des milliers de lecteurs qui ont déjà transformé leur vie grâce à notre bibliothèque spirituelle
                </p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <a href="register.php" class="bg-white hover:bg-gray-100 text-blue-600 px-10 py-5 rounded-2xl font-bold text-xl transition-all duration-300 hover:scale-105 pulse-glow">
                        <i class="fas fa-rocket mr-3"></i> Commencer gratuitement
                    </a>
                    <a href="library.php" class="border-2 border-white/30 hover:border-white text-white px-10 py-5 rounded-2xl font-bold text-xl transition-all duration-300 hover:bg-white/10">
                        <i class="fas fa-eye mr-3"></i> Parcourir d'abord
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php include "includes/footer.php"; ?>
<script src="includes/js/index.js"></script>
