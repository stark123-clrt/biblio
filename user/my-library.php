<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../classes/Core.php";
require_once "../classes/Models.php";
require_once "../classes/Repositories.php";

try {
   
    $userRepository = new UserRepository();
    $libraryRepository = new LibraryRepository();
    $noteRepository = new NoteRepository();
    $readingHistoryRepository = new ReadingHistoryRepository();
    
    $user_id = $_SESSION['user_id'];
    
    // ✅ RÉCUPÉRER L'UTILISATEUR (retourne encore un tableau dans l'implémentation réelle)
    $user = $userRepository->findById($user_id);
    
    if (!$user) {
        header("Location: ../login.php");
        exit();
    }
    
    // ✅ RÉCUPÉRER LES LIVRES DE LA BIBLIOTHÈQUE (RETOURNE DES OBJETS Book)
    $library_books = $libraryRepository->getUserLibrary($user_id);
    
    // ✅ CALCULER LES STATISTIQUES AVEC LES OBJETS
    $stats = $libraryRepository->getUserStats($user_id);
    $notes_count = $noteRepository->countByUser($user_id);
    
    // ✅ CALCULS AVANCÉS AVEC LES OBJETS Book
    $finished_books = 0;
    foreach ($library_books as $book) {
        if ($book->getLastPageRead() > 1 && $book->getPagesCount() > 0) {
            $progress = ($book->getLastPageRead() / $book->getPagesCount()) * 100;
            if ($progress >= 99.5) {
                $finished_books++;
            }
        }
    }
    
    // ✅ RÉCUPÉRER L'ACTIVITÉ RÉCENTE (RETOURNE DES OBJETS ReadingHistory)
    $recent_activity = $readingHistoryRepository->findRecentByUser($user_id, 5);
    
    // ✅ VARIABLES POUR LA VUE (SÉPARATION MVC)
    $success_message = null;
    $error_message = null;
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur d'initialisation : " . $e->getMessage());
    } else {
        error_log("My Library page initialization error: " . $e->getMessage());
        $library_books = [];
        $stats = ['total_books' => 0, 'started_books' => 0, 'favorite_books' => 0];
        $notes_count = 0;
        $finished_books = 0;
        $recent_activity = []; // Tableau vide d'objets ReadingHistory
        // Utilisateur par défaut en tant que tableau
        $user = ['username' => 'Utilisateur', 'first_name' => '', 'last_name' => '', 'profile_picture' => ''];
        $error_message = "Une erreur est survenue lors du chargement de votre bibliothèque.";
    }
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Ma Bibliothèque";
include "../includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <link rel="stylesheet" href="../includes/style/my-library.css">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Hero Section moderne -->
        <div class="text-center mb-12">
            <div class="relative inline-block">
                <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                    
                    Mon Espace Lecture
                </h1>
                <div class="absolute -inset-2 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-lg -z-10"></div>
            </div>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Découvrez votre univers littéraire personnel et suivez votre progression
            </p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="glass-card rounded-2xl border-l-4 border-red-500 bg-red-50/80 dark:bg-red-900/20 text-red-700 dark:text-red-300 px-6 py-4 mb-6 backdrop-blur-lg">
                <div class="flex items-center">
                    <div class="bg-red-500 w-8 h-8 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation text-white text-sm"></i>
                    </div>
                    <span class="font-medium"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Profil et statistiques ultra-modernes -->
        <div class="glass-card rounded-3xl p-8 mb-12 backdrop-blur-lg shadow-2xl border border-white/20 dark:border-gray-700/20">
            <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-8 lg:space-y-0 lg:space-x-12">
                <!-- Profil utilisateur modernisé -->
                <div class="flex-shrink-0 text-center">
                    <div class="relative inline-block">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Photo de profil" 
                                 class="w-32 h-32 rounded-full object-cover shadow-2xl ring-4 ring-white/50 dark:ring-gray-700/50">
                        <?php else: ?>
                            <div class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-2xl ring-4 ring-white/50 dark:ring-gray-700/50">
                                <?php
                                // Calculer les initiales manuellement
                                $firstName = $user['first_name'] ?? '';
                                $lastName = $user['last_name'] ?? '';
                                $username = $user['username'] ?? '';
                                
                                $firstInitial = $firstName ? strtoupper($firstName[0]) : '';
                                $lastInitial = $lastName ? strtoupper($lastName[0]) : '';
                                
                                if ($firstInitial && $lastInitial) {
                                    $initials = $firstInitial . $lastInitial;
                                } else {
                                    $initials = strtoupper(substr($username, 0, 2));
                                }
                                ?>
                                <span class="text-4xl font-bold text-white"><?php echo htmlspecialchars($initials); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-green-500 rounded-full border-4 border-white dark:border-gray-800 shadow-lg flex items-center justify-center">
                            <i class="fas fa-check text-white text-xs"></i>
                        </div>
                    </div>
                    <?php
                    // Calculer le nom complet manuellement
                    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $displayName = $fullName ?: ($user['username'] ?? 'Utilisateur');
                    ?>
                    <h2 class="mt-4 text-2xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($displayName); ?></h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-2">@<?php echo htmlspecialchars($user['username'] ?? 'user'); ?></p>
                    <span class="inline-flex items-center bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full text-sm font-medium">
                        <i class="fas fa-book-reader mr-1"></i>Lecteur passionné
                    </span>
                </div>
                
                <!-- Statistiques avec cartes carrées centrées -->
                <div class="flex-1 w-full">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 justify-items-center max-w-4xl mx-auto mt-8">
                        <!-- Total des livres - CARRÉ BLEU -->
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 group w-40 h-40 flex flex-col items-center justify-center text-center">
                            <div class="p-3 bg-white/20 rounded-xl mb-3 group-hover:bg-white/30 transition-colors">
                                <i class="fas fa-book text-2xl"></i>
                            </div>
                            <div class="text-3xl font-bold mb-1"><?php echo $stats['total_books']; ?></div>
                            <div class="text-blue-100 text-sm font-medium">Livres total</div>
                        </div>
                        
                        <!-- Livres commencés - CARRÉ VERT -->
                        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 group w-40 h-40 flex flex-col items-center justify-center text-center">
                            <div class="p-3 bg-white/20 rounded-xl mb-3 group-hover:bg-white/30 transition-colors">
                                <i class="fas fa-play text-2xl"></i>
                            </div>
                            <div class="text-3xl font-bold mb-1"><?php echo $stats['started_books']; ?></div>
                            <div class="text-green-100 text-sm font-medium">Commencés</div>
                        </div>
                        
                        <!-- Livres terminés - CARRÉ VIOLET -->
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 group w-40 h-40 flex flex-col items-center justify-center text-center">
                            <div class="p-3 bg-white/20 rounded-xl mb-3 group-hover:bg-white/30 transition-colors">
                                <i class="fas fa-check-circle text-2xl"></i>
                            </div>
                            <div class="text-3xl font-bold mb-1"><?php echo $finished_books; ?></div>
                            <div class="text-purple-100 text-sm font-medium">Terminés</div>
                        </div>
                        
                        <!-- Favoris - CARRÉ ROUGE/ROSE -->
                        <div class="bg-gradient-to-br from-red-500 to-pink-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 group w-40 h-40 flex flex-col items-center justify-center text-center">
                            <div class="p-3 bg-white/20 rounded-xl mb-3 group-hover:bg-white/30 transition-colors">
                                <i class="fas fa-heart text-2xl"></i>
                            </div>
                            <div class="text-3xl font-bold mb-1"><?php echo $stats['favorite_books']; ?></div>
                            <div class="text-red-100 text-sm font-medium">Favoris</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtres et recherche ultra-modernes -->
        <div class="glass-card rounded-3xl p-8 mb-8 backdrop-blur-lg shadow-xl border border-white/20 dark:border-gray-700/20">
            <div class="flex items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white"></h3>
            </div>
            
            <!-- Boutons de filtre modernisés -->
            <div class="flex flex-wrap gap-3 mb-6">
                <button class="filter-btn px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 active" data-filter="all">
                    <i class="fas fa-th-large mr-2"></i>Tous les livres
                </button>
                <button class="filter-btn px-6 py-3 bg-white/80 dark:bg-gray-700/80 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200/50 dark:border-gray-600/50 backdrop-blur-sm" data-filter="reading">
                    <i class="fas fa-book-reader mr-2"></i>En cours de lecture
                </button>
                <button class="filter-btn px-6 py-3 bg-white/80 dark:bg-gray-700/80 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200/50 dark:border-gray-600/50 backdrop-blur-sm" data-filter="favorites">
                    <i class="fas fa-heart mr-2"></i>Mes favoris
                </button>
                <button class="filter-btn px-6 py-3 bg-white/80 dark:bg-gray-700/80 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200/50 dark:border-gray-600/50 backdrop-blur-sm" data-filter="not-started">
                    <i class="fas fa-hourglass-start mr-2"></i>Non commencés
                </button>
                <button class="filter-btn px-6 py-3 bg-white/80 dark:bg-gray-700/80 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200/50 dark:border-gray-600/50 backdrop-blur-sm" data-filter="finished">
                    <i class="fas fa-check-circle mr-2"></i>Terminés
                </button>
            </div>
            
            <!-- Barre de recherche ultra-moderne -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="searchLibrary" 
                       placeholder="Rechercher dans votre bibliothèque..." 
                       class="w-full pl-12 pr-16 py-4 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-2xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                    <button type="button" id="clearSearch" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors hidden">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Grille des livres ultra-moderne -->
        <div id="libraryBooks" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (!empty($library_books)): ?>
                <?php foreach ($library_books as $index => $book): ?>
                    <?php
                        // ✅ UTILISER LES MÉTHODES D'OBJETS
                        $book_status = '';
                        $progress = 0;
                        $isCompleted = false;
                        
                        if ($book->getPagesCount() > 0) {
                            $progress = min(100, ($book->getLastPageRead() / $book->getPagesCount()) * 100);
                            $isCompleted = ($progress >= 99.5);
                        }
                        
                        if ($book->getLastPageRead() > 1 && !$isCompleted) {
                            $book_status = 'reading';
                        } elseif ($book->getLastPageRead() <= 1) {
                            $book_status = 'not-started';
                        }
                        
                        if ($isCompleted) {
                            $book_status = 'finished';
                        }
                        
                        if ($book->getIsFavoriteInLibrary()) {
                            $book_status .= ' favorites';
                        }
                    ?>

                    <div class="library-book glass-card rounded-3xl overflow-hidden hover:scale-[1.02] transition-all duration-500 group shadow-xl border border-white/20 dark:border-gray-700/20 slide-up <?php echo $book_status; ?>" 
                         style="transition-delay: <?php echo $index * 0.1; ?>s"
                         data-title="<?php echo htmlspecialchars(strtolower($book->getTitle())); ?>">
                        
                        <!-- Image avec overlay moderne -->
                        <div class="relative overflow-hidden">
                            <?php if ($book->hasCover()): ?>
                                <?php
                                // Corriger le chemin de l'image comme dans l'ancien code
                                $image_path = $book->getCoverImage();
                                if (strpos($image_path, '../') === 0) {
                                    $image_path = substr($image_path, 3);
                                }
                                $image_path = '../' . $image_path;
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
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
                            
                            <!-- Overlay gradient -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            
                            <!-- Badges avec design ultra-moderne -->
                            <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                                <!-- Badge de statut -->
                                <?php if ($book->getLastPageRead() > 1 && !$isCompleted): ?>
                                    <div class="bg-gradient-to-r from-blue-500/90 to-blue-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                        <i class="fas fa-play mr-1"></i>En cours
                                    </div>
                                <?php elseif ($isCompleted): ?>
                                    <div class="bg-gradient-to-r from-green-500/90 to-green-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                        <i class="fas fa-check mr-1"></i>Terminé
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gradient-to-r from-gray-500/90 to-gray-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                        <i class="fas fa-clock mr-1"></i>À lire
                                    </div>
                                <?php endif; ?>

                                <button class="favorite-btn w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center text-yellow-400 hover:bg-white/30 transition-all duration-300 shadow-lg hover:scale-110" 
                                        data-id="<?php echo $book->getId(); ?>" 
                                        data-favorite="<?php echo $book->getIsFavoriteInLibrary() ? '1' : '0'; ?>">
                                    <i class="<?php echo $book->getIsFavoriteInLibrary() ? 'fas' : 'far'; ?> fa-heart text-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="mb-4">
                                <h3 class="font-bold text-lg line-clamp-2 text-gray-800 dark:text-white mb-2">
                                    <?php echo htmlspecialchars($book->getTitle()); ?>
                                </h3>
                                <?php if ($book->getAuthor()): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        par <span class="font-medium"><?php echo htmlspecialchars($book->getAuthor()); ?></span>
                                    </p>
                                <?php endif; ?>
                                <div class="inline-flex items-center bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-3 py-1 rounded-full text-sm">
                                    <i class="fas fa-tag mr-1"></i>
                                    <?php echo htmlspecialchars($book->getCategoryName() ?? 'Non catégorisé'); ?>
                                </div>
                            </div>
                            
                            <!-- Barre de progression ultra-moderne -->
                            <?php if ($book->getLastPageRead() > 1): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progression</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo round($progress); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="<?php echo $isCompleted ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-blue-500 to-blue-600'; ?> h-3 rounded-full transition-all duration-500 ease-out" 
                                             style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        Page <?php echo $book->getLastPageRead(); ?> 
                                        <?php if ($book->getPagesCount() > 0): ?>
                                            sur <?php echo $book->getPagesCount(); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions avec boutons ultra-modernes -->
                            <div class="flex flex-col space-y-3">
                                <div class="flex justify-between items-center">
                                    <a href="../book.php?id=<?php echo $book->getId(); ?>" 
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium text-sm transition-colors duration-300 flex items-center">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                    
                                    <a href="../read.php?id=<?php echo $book->getId(); ?>" 
                                       class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 hover:scale-105 shadow-lg flex items-center">
                                        <i class="fas fa-<?php echo $book->getLastPageRead() > 1 ? 'play' : 'book-open'; ?> mr-1"></i>
                                        <?php echo $book->getLastPageRead() > 1 ? 'Continuer' : 'Commencer'; ?>
                                    </a>
                                </div>
                                
                                <!-- Bouton de suppression discret -->
                                <button class="remove-book-btn text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium transition-colors duration-300 text-center py-2 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl" 
                                        data-id="<?php echo $book->getId(); ?>" 
                                        data-title="<?php echo htmlspecialchars($book->getTitle()); ?>">
                                    <i class="fas fa-trash-alt mr-1"></i>Retirer de ma bibliothèque
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- État vide ultra-moderne -->
                <div class="col-span-full text-center py-20">
                    <div class="glass-card rounded-3xl p-12 max-w-lg mx-auto backdrop-blur-lg shadow-2xl">
                        <div class="bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/20 dark:to-purple-900/20 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-book-open text-4xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Votre bibliothèque est vide</h2>
                        <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                            Découvrez des livres passionnants et commencez votre aventure littéraire !
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="../library.php" 
                               class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                                <i class="fas fa-search mr-2"></i>Parcourir la bibliothèque
                            </a>
                            <a href="../category.php" 
                               class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                                <i class="fas fa-tags mr-2"></i>Explorer les catégories
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Activité récente avec design ultra-moderne -->
        <?php if (!empty($recent_activity)): ?>
            <div class="mt-16">
             
                
                <div class="glass-card rounded-3xl overflow-hidden backdrop-blur-lg shadow-2xl border border-white/20 dark:border-gray-700/20">
                    <div class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="p-6 hover:bg-gray-50/30 dark:hover:bg-gray-700/30 transition-all duration-300 group cursor-pointer" onclick="window.location.href='../read.php?id=<?php echo $activity->getBookId(); ?>'">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br <?php echo $activity->getActionColor(); ?> rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
                                            <i class="<?php echo $activity->getActionIcon(); ?> text-white"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-800 dark:text-white mb-1">
                                            <?php echo $activity->getActionLabel(); ?>
                                            <span class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-300">
                                                <?php echo htmlspecialchars($activity->getBookTitle()); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center flex-wrap gap-2">
                                            <?php if ($activity->getPageNumber() && $activity->getPageNumber() > 0): ?>
                                                <span class="inline-flex items-center bg-blue-100/80 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-2 py-1 rounded-full text-xs font-medium">
                                                    Page <?php echo $activity->getPageNumber(); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="flex items-center">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo $activity->getFormattedDate(); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Icône flèche subtile -->
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-chevron-right text-gray-400 dark:text-gray-500 text-sm group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Actions rapides -->
        <?php if (!empty($library_books)): ?>
            <div class="mt-16 glass-card rounded-3xl p-8 backdrop-blur-lg shadow-xl border border-white/20 dark:border-gray-700/20">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="notes.php" class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300 group">
                        <div class="bg-gradient-to-br from-green-500 to-green-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                            <i class="fas fa-sticky-note text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2">Mes Notes</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Consultez vos <?php echo $notes_count; ?> note<?php echo $notes_count > 1 ? 's' : ''; ?></p>
                    </a>
                    
                    <a href="comments.php" class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300 group">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                            <i class="fas fa-comments text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2">Mes Avis</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Gérez vos commentaires</p>
                    </a>
                    
                    <a href="../library.php" class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300 group">
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg group-hover:shadow-xl transition-shadow">
                            <i class="fas fa-plus text-xl"></i>
                        </div>
                        <h4 class="font-bold text-gray-800 dark:text-white mb-2">Ajouter des livres</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Découvrez de nouveaux livres</p>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
<script src="../includes/js/my-library.js"></script>