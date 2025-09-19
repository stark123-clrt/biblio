<?php
// book.php - Version 100% POO avec séparation MVC complète
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'ID du livre est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: library.php");
    exit();
}
require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    
    $bookRepository = new BookRepository();
    $commentRepository = new CommentRepository();
    $libraryRepository = new LibraryRepository();
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur d'initialisation : " . $e->getMessage());
    } else {
        error_log("Book page initialization error: " . $e->getMessage());
        header("Location: library.php");
        exit();
    }
}

$book_id = intval($_GET['id']);

try {
 
    $book = $bookRepository->findById($book_id);
    
    if (!$book) {
        header("Location: library.php");
        exit();
    }
    
    $success_message = "";
    $error_message = "";
    $comment_success = "";
    $comment_error = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // Action: Ajouter à la bibliothèque
        if (isset($_POST['action']) && $_POST['action'] == 'add_to_library' && isset($_POST['book_id'])) {
            $book_id_to_add = (int)$_POST['book_id'];
            
            try {
                if ($libraryRepository->addToLibrary($user_id, $book_id_to_add)) {
                    $success_message = "Le livre a été ajouté à votre bibliothèque avec succès.";
                } else {
                    $error_message = "Vous devez d'abord commencer à lire le livre avant de commenté.";
                }
            } catch (Exception $e) {
                $error_message = "Une erreur est survenue lors de l'ajout du livre à votre bibliothèque.";
            }
        }
        // Action: Toggle favori
        if (isset($_POST['action']) && $_POST['action'] == 'toggle_favorite' && isset($_POST['book_id'])) {
            $book_id_to_toggle = (int)$_POST['book_id'];
            
            try {
                if ($libraryRepository->toggleFavorite($user_id, $book_id_to_toggle)) {
                    $success_message = "Le statut favori a été mis à jour.";
                } else {
                    $error_message = "Vous devez d'abord commencer à lire le livre avant de commenté.";
                }
            } catch (Exception $e) {
                $error_message = "Une erreur est survenue.";
            }
        }
    }
   
    // TRAITEMENT DES COMMENTAIRES AVEC VÉRIFICATION DE LECTURE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        $comment_error = "Vous devez être connecté pour laisser un commentaire.";
    } else {
        $comment_text = trim($_POST['comment_text']);
        $rating = intval($_POST['rating']);
        $user_id = $_SESSION['user_id'];
        
        if (empty($comment_text)) {
            $comment_error = "Le commentaire ne peut pas être vide.";
        } elseif ($rating < 1 || $rating > 5) {
            $comment_error = "La note doit être comprise entre 1 et 5.";
        } else {
            try {
                //  LE COMMENTAIRE (avec vérification de lecture)
                $result = $commentRepository->createOrUpdate($user_id, $book_id, $comment_text, $rating);
                
                if ($result['success']) {
                    $comment_success = $result['message'];
                } else {
                    $comment_error = $result['message'];
                }
                
            } catch (Exception $e) {
                if (config('app.debug', false)) {
                    $comment_error = "Erreur technique : " . $e->getMessage();
                } else {
                    error_log("Comment processing error: " . $e->getMessage());
                    $comment_error = "Une erreur technique est survenue.";
                }
            }
        }
    }
}
    

    // RÉCUPÉRER LES COMMENTAIRES AVEC REPOSITORY (retourne des objets Comment)
    $comments = $commentRepository->findByBook($book_id, true); 
    // INCRÉMENTER LES VUES AVEC REPOSITORY
    $bookRepository->incrementViews($book_id);
    // CALCULER LES STATISTIQUES
    $average_rating = $commentRepository->getAverageRating($book_id);
    $total_reviews = $commentRepository->countByBook($book_id, true);
    // PRÉPARER TOUTES LES VARIABLES POUR LA VUE (SÉPARATION MVC)
    $user_comment = null;
    $has_rated = false;
    $user_rating = 0;
    $in_library = false;
    $is_favorite = false; 
    // LOGIQUE UTILISATEUR AVEC REPOSITORIES (VERSION FINALE)
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // VÉRIFIER LES COMMENTAIRES DE L'UTILISATEUR (avec objets)
        foreach ($comments as $comment) {
            if ($comment->getUserId() == $user_id) {
                $user_comment = $comment;
                $has_rated = true;
                $user_rating = $comment->getRating();
                break;
            }
        }    
        // VÉRIFIER SI LE LIVRE EST DANS LA BIBLIOTHÈQUE
        $in_library = $libraryRepository->isInLibrary($user_id, $book_id);  
        // VÉRIFIER LE STATUT FAVORI (MÉTHODE PROPRE)
        if ($in_library) {
            $is_favorite = $libraryRepository->isFavorite($user_id, $book_id);
        }
    }
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur lors du chargement du livre : " . $e->getMessage());
    } else {
        error_log("Book page error: " . $e->getMessage());
        header("Location: library.php");
        exit();
    }
}
$page_title = htmlspecialchars($book->getTitle());
require_once "includes/header.php";
?>
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <link rel="stylesheet" href="includes/style/book.css">
    <!-- Hero Section avec détails du livre -->
    <section class="hero-book py-12 lg:py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <!-- Breadcrumb moderne responsive -->
            <nav class="breadcrumb-modern text-white/90 mb-6 lg:mb-8 max-w-full overflow-hidden">
                <div class="flex items-center text-xs lg:text-sm">
                    <a href="index.php" class="hover:text-white transition-colors flex items-center flex-shrink-0">
                        <i class="fas fa-home mr-1 lg:mr-2"></i>
                        <span class="hidden sm:inline">Accueil</span>
                    </a>
                    <i class="fas fa-chevron-right mx-2 lg:mx-3 text-white/60 flex-shrink-0"></i>
                    <a href="library.php" class="hover:text-white transition-colors flex-shrink-0">
                        <span class="hidden sm:inline">Bibliothèque</span>
                        <span class="sm:hidden">Biblio.</span>
                    </a>
                    <i class="fas fa-chevron-right mx-2 lg:mx-3 text-white/60 flex-shrink-0"></i>
                    <span class="text-white font-medium truncate min-w-0">
                        <?php echo htmlspecialchars($book->getTitle()); ?>
                    </span>
                </div>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-center">
                <!-- Image et actions -->
                <div class="text-center order-2 lg:order-1">
                    <div class="book-cover relative inline-block mb-6 lg:mb-8">
                        <?php if ($book->hasCover()): ?>
                            <img src="<?php echo htmlspecialchars($book->getCoverImagePath()); ?>" 
                                 alt="<?php echo htmlspecialchars($book->getTitle()); ?>" 
                                 class="w-full max-w-sm mx-auto object-cover"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-full max-w-sm h-80 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center mx-auto" style="display:none;">
                                <i class="fas fa-book text-6xl lg:text-8xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php else: ?>
                            <div class="w-full max-w-sm h-80 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center mx-auto">
                                <i class="fas fa-book text-6xl lg:text-8xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge de note flottant -->
                        <?php if ($average_rating > 0): ?>
                            <div class="floating-badge">
                                <div class="text-sm lg:text-lg font-bold"><?php echo round($average_rating, 1); ?></div>
                                <div class="text-xs">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="stats-grid mb-6 lg:mb-8">
                        <div class="stat-item">
                            <div class="text-xl lg:text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="text-xs lg:text-sm text-white/80">
                                <?php echo number_format($book->getViewsCount() ?? 0); ?> vues
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="text-xl lg:text-2xl font-bold text-green-600 dark:text-green-400 mb-2">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="text-xs lg:text-sm text-white/80">
                                <?php echo $total_reviews; ?> avis
                            </div>
                        </div>
                        <?php if ($book->getPagesCount()): ?>
                            <div class="stat-item lg:col-span-2">
                                <div class="text-xl lg:text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="text-xs lg:text-sm text-white/80">
                                    <?php echo $book->getPagesCount(); ?> pages
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations du livre -->
                <div class="text-white text-center lg:text-left order-1 lg:order-2">
                    <h1 class="text-3xl lg:text-4xl xl:text-6xl font-bold mb-4 lg:mb-6 leading-tight">
                        <?php echo htmlspecialchars($book->getTitle()); ?>
                    </h1>
                    
                    <?php if ($book->getAuthor()): ?>
                        <p class="text-lg lg:text-xl xl:text-2xl mb-4 lg:mb-6 opacity-90">
                            par <span class="font-semibold text-yellow-300"><?php echo htmlspecialchars($book->getAuthor()); ?></span>
                        </p>
                    <?php endif; ?>

                    <!-- Badges -->
                    <div class="flex flex-wrap justify-center lg:justify-start gap-2 lg:gap-3 mb-6 lg:mb-8">
                        <span class="bg-white/20 backdrop-blur-sm rounded-full px-3 lg:px-4 py-1 lg:py-2 text-xs lg:text-sm font-semibold">
                            <i class="fas fa-tag mr-1 lg:mr-2"></i>
                            <?php echo htmlspecialchars($book->getCategoryName() ?? 'Non catégorisé'); ?>
                        </span>
                        <?php if ($book->getPublicationYear()): ?>
                            <span class="bg-white/20 backdrop-blur-sm rounded-full px-3 lg:px-4 py-1 lg:py-2 text-xs lg:text-sm font-semibold">
                                <i class="fas fa-calendar-alt mr-1 lg:mr-2"></i>
                                <?php echo $book->getPublicationYear(); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Note avec étoiles -->
                    <?php if ($average_rating > 0): ?>
                        <div class="flex items-center justify-center lg:justify-start mb-6 lg:mb-8">
                            <div class="star-rating mr-3 lg:mr-4">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $average_rating): ?>
                                        <i class="fas fa-star text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php elseif ($i - 0.5 <= $average_rating): ?>
                                        <i class="fas fa-star-half-alt text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="text-lg lg:text-xl font-semibold">
                                <?php echo round($average_rating, 1); ?>/5 (<?php echo $total_reviews; ?> avis)
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Boutons d'action -->
                    <div class="space-y-3 lg:space-y-4">
                        <a href="read.php?id=<?php echo $book->getId(); ?>" class="btn-modern">
                            <i class="fas fa-book-reader mr-2 lg:mr-3 text-lg lg:text-xl"></i>
                            Lire maintenant
                        </a>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>                            
                            <div class="space-y-3 lg:space-y-0 lg:space-x-4 lg:flex lg:flex-col xl:flex-row">
                                <?php if (!$in_library): ?>
                                    <form method="POST" action="book.php?id=<?php echo $book->getId(); ?>" class="w-full">
                                        <input type="hidden" name="action" value="add_to_library">
                                        <input type="hidden" name="book_id" value="<?php echo $book->getId(); ?>">
                                        <button type="submit" class="btn-modern btn-success">
                                            <i class="fas fa-plus mr-2 lg:mr-3"></i>
                                            Ajouter à ma bibliothèque
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="book.php?id=<?php echo $book->getId(); ?>" class="w-full">
                                        <input type="hidden" name="action" value="toggle_favorite">
                                        <input type="hidden" name="book_id" value="<?php echo $book->getId(); ?>">
                                        <button type="submit" class="btn-modern <?php echo $is_favorite ? 'btn-warning' : 'btn-success'; ?>">
                                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart mr-2 lg:mr-3"></i>
                                            <?php echo $is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

 <div class="container mx-auto px-4 py-12">
        <!-- Messages d'alerte -->
        <?php if (!empty($success_message)): ?>
            <div class="glass-card rounded-2xl p-6 mb-8 border-l-4 border-green-500 slide-up">
                <div class="flex items-center text-green-700 dark:text-green-300">
                    <i class="fas fa-check-circle text-2xl mr-4"></i>
                    <p class="font-semibold text-lg"><?php echo $success_message; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="glass-card rounded-2xl p-6 mb-8 border-l-4 border-red-500 slide-up">
                <div class="flex items-center text-red-700 dark:text-red-300">
                    <i class="fas fa-exclamation-circle text-2xl mr-4"></i>
                    <p class="font-semibold text-lg"><?php echo $error_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section principale du contenu -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <!-- Contenu principal -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Description du livre -->
                <?php if ($book->getDescription()): ?>
                    <div class="glass-card rounded-3xl p-8 slide-up">
                        <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                            <i class="fas fa-align-left text-blue-600 dark:text-blue-400 mr-3"></i>
                            À propos de ce livre
                        </h2>
                        <div class="prose prose-lg max-w-none text-gray-700 dark:text-gray-300 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($book->getDescription())); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section Commentaires -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-8 flex items-center">
                        <i class="fas fa-comments text-blue-600 dark:text-blue-400 mr-3"></i>
                        Avis des lecteurs
                        <?php if (count($comments) > 0): ?>
                            <span class="ml-3 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-lg px-3 py-1 rounded-full">
                                <?php echo count($comments); ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <!-- Messages pour les commentaires -->
                    <?php if (!empty($comment_success)): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 text-green-700 dark:text-green-300 px-6 py-4 rounded-r-xl mb-6 flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <?php echo $comment_success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($comment_error)): ?>
                        <div class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 border-l-4 border-red-500 text-red-700 dark:text-red-300 px-6 py-4 rounded-r-xl mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <?php echo $comment_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Affichage des commentaires -->
                    <?php if (!empty($comments)): ?>
                        <div class="mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                                    Derniers avis (<?php echo count($comments); ?>)
                                </h3>
                                <?php if (count($comments) > 3): ?>
                                    <div class="hidden lg:flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-hand-point-right mr-2"></i>
                                        Faites défiler pour voir plus
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Version mobile : scroll horizontal -->
                            <div class="lg:hidden">
                                <div class="flex space-x-4 overflow-x-auto pb-4 px-2" style="scrollbar-width: thin;">
                                    <?php foreach ($comments as $index => $comment): ?>
                                        <div class="comment-card flex-shrink-0 w-80 max-w-[90vw]" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                            <div class="flex items-start space-x-3">
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                                        <span class="text-white font-bold text-sm">
                                                            <?php echo strtoupper(substr($comment->getUsername(), 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate">
                                                            <?php echo htmlspecialchars($comment->getUsername()); ?>
                                                        </h4>
                                                        <div class="flex items-center bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/30 rounded-full px-2 py-1 ml-2">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="<?php echo ($i <= $comment->getRating()) ? 'fas' : 'far'; ?> fa-star text-yellow-400 text-xs"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center">
                                                        <i class="far fa-clock mr-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($comment->getCreatedAt())); ?>
                                                        <?php if ($comment->getCreatedAt() != $comment->getUpdatedAt()): ?>
                                                            <span class="ml-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs px-1 py-0.5 rounded">
                                                                modifié
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed line-clamp-3">
                                                        <?php echo nl2br(htmlspecialchars($comment->getCommentText())); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Indicateur de scroll -->
                                <?php if (count($comments) > 1): ?>
                                    <div class="flex justify-center mt-4">
                                        <div class="flex items-center space-x-2 text-gray-400 dark:text-gray-500">
                                            <i class="fas fa-chevron-left text-xs"></i>
                                            <span class="text-xs">Glissez pour voir plus d'avis</span>
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Version desktop : grille verticale -->
                            <div class="hidden lg:block space-y-6">
                                <?php foreach ($comments as $index => $comment): ?>
                                    <div class="comment-card" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                                    <span class="text-white font-bold text-lg">
                                                        <?php echo strtoupper(substr($comment->getUsername(), 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div>
                                                        <h4 class="font-bold text-gray-800 dark:text-gray-200 text-lg">
                                                            <?php echo htmlspecialchars($comment->getUsername()); ?>
                                                        </h4>
                                                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                            <i class="far fa-clock mr-1"></i>
                                                            <?php echo date('d/m/Y à H:i', strtotime($comment->getCreatedAt())); ?>
                                                            <?php if ($comment->getCreatedAt() != $comment->getUpdatedAt()): ?>
                                                                <span class="ml-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                                                    modifié
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/30 rounded-full px-3 py-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="<?php echo ($i <= $comment->getRating()) ? 'fas' : 'far'; ?> fa-star text-yellow-400 text-sm"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                    <?php echo nl2br(htmlspecialchars($comment->getCommentText())); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 mb-8">
                            <div class="bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-3xl p-12">
                                <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                                    <i class="far fa-comment-dots"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-600 dark:text-gray-400 mb-4">
                                    Aucun avis pour le moment
                                </h3>
                                <p class="text-gray-500 dark:text-gray-500 text-lg">
                                    Soyez le premier à partager votre opinion sur ce livre !
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulaire de commentaire -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="form-modern">
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                                <i class="fas fa-pen mr-3 text-blue-600 dark:text-blue-400"></i>
                                <?php echo $user_comment ? 'Modifier votre avis' : 'Partager votre avis'; ?>
                            </h3>
                            
                            <form method="POST" action="book.php?id=<?php echo $book_id; ?>" id="comment-form" class="space-y-6">
                                <!-- Système de notation par étoiles -->
                                <div>
                                    <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-4 text-lg">
                                        Votre note
                                    </label>
                                    <div class="flex items-center space-x-2" id="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" id="rating_<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden" <?php echo ($user_comment && $user_comment->getRating() == $i) ? 'checked' : ''; ?> required>
                                            <label for="rating_<?php echo $i; ?>" class="star-interactive" data-rating="<?php echo $i; ?>">
                                                <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Cliquez sur les étoiles pour noter ce livre
                                    </p>
                                </div>
                                
                                <!-- Zone de commentaire -->
                                <div>
                                    <label for="comment_text" class="block text-gray-700 dark:text-gray-300 font-semibold mb-4 text-lg">
                                        Votre commentaire
                                    </label>
                                    <textarea id="comment_text" name="comment_text" rows="6" 
                                            class="textarea-modern w-full" 
                                            placeholder="Partagez votre expérience de lecture, ce que vous avez aimé ou moins aimé..."
                                            required><?php echo $user_comment ? htmlspecialchars($user_comment->getCommentText()) : ''; ?></textarea>
                                    <div class="flex justify-between items-center mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-lightbulb mr-1"></i>
                                            Minimum 10 caractères
                                        </p>
                                        <div id="char-counter" class="text-sm text-gray-500 dark:text-gray-400"></div>
                                    </div>
                                </div>
                                
                                <!-- Bouton de soumission -->
                                <div class="flex justify-end pt-4">
                                    <button type="submit" name="submit_comment" class="btn-modern">
                                        <i class="fas fa-paper-plane mr-3"></i>
                                        <?php echo $user_comment ? 'Mettre à jour mon avis' : 'Publier mon avis'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="form-modern text-center">
                            <div class="text-blue-500 text-8xl mb-6">
                                <i class="fas fa-user-lock"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                                Connectez-vous pour partager votre avis
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg">
                                Rejoignez notre communauté de lecteurs et partagez vos impressions sur ce livre.
                            </p>
                            <div class="flex flex-col sm:flex-row justify-center gap-4">
                                <a href="login.php" class="btn-modern">
                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                    Se connecter
                                </a>
                                <a href="register.php" class="btn-modern btn-success">
                                    <i class="fas fa-user-plus mr-2"></i>
                                    S'inscrire
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar avec informations complémentaires -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Informations détaillées -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-3"></i>
                        Détails du livre
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if ($book->getAuthor()): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-user-edit text-blue-600 dark:text-blue-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Auteur</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($book->getAuthor()); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($book->getPublisher()): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-building text-green-600 dark:text-green-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Éditeur</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($book->getPublisher()); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($book->getPublicationYear()): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-purple-600 dark:text-purple-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Année de publication</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo $book->getPublicationYear(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($book->getPagesCount()): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-file-alt text-orange-600 dark:text-orange-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Nombre de pages</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo number_format($book->getPagesCount()); ?> pages
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-badge">
                            <div class="flex items-center">
                                <i class="fas fa-plus text-blue-600 dark:text-blue-400 mr-3 text-xl"></i>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Ajouté le</div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">
                                        <?php echo date('d/m/Y', strtotime($book->getCreatedAt())); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques de lecture -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-chart-bar text-green-600 dark:text-green-400 mr-3"></i>
                        Statistiques
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                <?php echo number_format($book->getViewsCount() ?? 0); ?>
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Lectures totales</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-4xl font-bold text-green-600 dark:text-green-400 mb-2">
                                <?php echo $total_reviews; ?>
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Avis de lecteurs</div>
                        </div>
                        
                        <?php if ($average_rating > 0): ?>
                            <div class="text-center">
                                <div class="text-4xl font-bold text-yellow-500 mb-2">
                                    <?php echo round($average_rating, 1); ?>/5
                                </div>
                                <div class="text-gray-600 dark:text-gray-400">Note moyenne</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-bolt text-yellow-600 dark:text-yellow-400 mr-3"></i>
                        Actions rapides
                    </h3>
                    
                    <div class="space-y-4">
                        <a href="read.php?id=<?php echo $book->getId(); ?>" 
                           class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-book-reader mr-2"></i>
                            Commencer la lecture
                        </a>
                        
                        <button onclick="window.print()" 
                                class="w-full bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-print mr-2"></i>
                            Imprimer cette page
                        </a>
                        
                        <button onclick="shareBook()" 
                                class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-share-alt mr-2"></i>
                            Partager ce livre
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>
<script src="includes/js/book.js"></script>