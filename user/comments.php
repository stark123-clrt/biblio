<?php
// comments.php - Version 100% POO avec interface moderne et dark mode
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../classes/Core.php";
require_once "../classes/Models.php";
require_once "../classes/Repositories.php";

try {
    // ✅ INITIALISATION DES REPOSITORIES
    $commentRepository = new CommentRepository();
    $bookRepository = new BookRepository();
    
    $user_id = $_SESSION['user_id'];
    
    // ✅ VARIABLES POUR LA VUE (SÉPARATION MVC)
    $success_message = null;
    $error_message = null;
    
    // ================================
    // 🗑️ SUPPRESSION D'UN COMMENTAIRE
    // ================================
    
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $comment_id = intval($_GET['delete']);
        
        try {
            if ($commentRepository->delete($comment_id, $user_id)) {
                $success_message = "Le commentaire a été supprimé avec succès.";
            } else {
                $error_message = "Vous n'êtes pas autorisé à supprimer ce commentaire.";
            }
        } catch (Exception $e) {
            if (config('app.debug', false)) {
                $error_message = "Erreur de suppression : " . $e->getMessage();
            } else {
                error_log("Comment deletion error: " . $e->getMessage());
                $error_message = "Une erreur est survenue lors de la suppression du commentaire.";
            }
        }
    }
    
    // ✅ RÉCUPÉRER LES COMMENTAIRES (RETOURNE DES OBJETS Comment)
    $comments = $commentRepository->findByUser($user_id);
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur d'initialisation : " . $e->getMessage());
    } else {
        error_log("Comments page initialization error: " . $e->getMessage());
        $comments = [];
        $error_message = "Une erreur est survenue lors du chargement des commentaires.";
    }
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Mes Commentaires";
include "../includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <link rel="stylesheet" href="../includes/style/comments.css">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Hero Section moderne -->
        <div class="text-center mb-12">
            <div class="relative inline-block">
                <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                    <i class="fas fa-comments mr-4 text-blue-600 dark:text-blue-400"></i>
                    Mes Commentaires
                </h1>
                <div class="absolute -inset-2 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-lg -z-10"></div>
            </div>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Gérez vos avis et évaluations sur les livres de la bibliothèque
            </p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="glass-card rounded-2xl border-l-4 border-green-500 bg-green-50/80 dark:bg-green-900/20 text-green-700 dark:text-green-300 px-6 py-4 mb-6 backdrop-blur-lg">
                <div class="flex items-center">
                    <div class="bg-green-500 w-8 h-8 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-check text-white text-sm"></i>
                    </div>
                    <span class="font-medium"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
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
        
        <!-- Liste des commentaires avec design moderne -->
        <?php if (!empty($comments)): ?>
            <div class="space-y-8">
                <?php foreach ($comments as $index => $comment): ?>
                    <div class="glass-card rounded-3xl overflow-hidden hover:scale-[1.01] transition-all duration-500 group shadow-xl border border-white/20 dark:border-gray-700/20 slide-up" style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <div class="lg:flex">
                            <!-- Section livre avec image -->
                            <div class="lg:w-1/3 bg-gradient-to-br from-gray-50/80 to-white/80 dark:from-gray-800/80 dark:to-gray-900/80 p-6 backdrop-blur-sm">
                                <div class="flex items-start space-x-4">
                                    <!-- Image du livre -->
                                    <div class="flex-shrink-0">
                                        <?php if ($comment->getCoverImage()): ?>
                                            <?php
                                            $image_path = $comment->getCoverImage();
                                            if (strpos($image_path, '../') === 0) {
                                                $image_path = substr($image_path, 3);
                                            }
                                            $image_path = '../' . $image_path;
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                 alt="<?php echo htmlspecialchars($comment->getBookTitle()); ?>" 
                                                 class="w-20 h-28 object-cover rounded-xl shadow-lg hover:scale-105 transition-transform duration-300"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="w-20 h-28 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center rounded-xl shadow-lg" style="display:none;">
                                                <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-20 h-28 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center rounded-xl shadow-lg">
                                                <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Infos livre -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-bold mb-3 line-clamp-2">
                                            <a href="../book.php?id=<?php echo $comment->getBookId(); ?>" 
                                               class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors hover:underline">
                                                <?php echo htmlspecialchars($comment->getBookTitle()); ?>
                                            </a>
                                        </h3>
                                        
                                        <!-- Note avec étoiles -->
                                        <div class="flex items-center mb-3">
                                            <div class="text-yellow-400 text-lg mr-2">
                                                <?php foreach ($comment->getStarsArray() as $isFilled): ?>
                                                    <i class="<?php echo $isFilled ? 'fas' : 'far'; ?> fa-star"></i>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                <?php echo $comment->getRating(); ?>/5
                                            </span>
                                        </div>
                                        
                                        <!-- Date et status -->
                                        <div class="flex flex-wrap gap-2 items-center text-sm">
                                            <span class="inline-flex items-center bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo $comment->getFormattedDate(); ?>
                                            </span>
                                            
                                            <?php if ($comment->getCreatedAt() != $comment->getUpdatedAt()): ?>
                                                <span class="inline-flex items-center bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 px-3 py-1 rounded-full">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    modifié
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section commentaire -->
                            <div class="lg:w-2/3 p-6">
                                <!-- En-tête avec statut -->
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center">
                                        <i class="fas fa-quote-left mr-2 text-purple-600 dark:text-purple-400"></i>
                                        Mon avis
                                    </h4>
                                    
                                    <div class="flex space-x-2">
                                        <?php if ($comment->isValidated()): ?>
                                            <span class="inline-flex items-center bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-3 py-1 rounded-full text-sm font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>Validé
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 px-3 py-1 rounded-full text-sm font-medium">
                                                <i class="fas fa-clock mr-1"></i>En attente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Contenu du commentaire -->
                                <div class="bg-gray-50/50 dark:bg-gray-800/50 rounded-2xl p-4 mb-6 backdrop-blur-sm">
                                    <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                        <?php 
                                        $comment_text = $comment->getCommentText();
                                        $is_long = strlen($comment_text) > 200;
                                        ?>
                                        
                                        <?php if ($is_long): ?>
                                            <div class="comment-content">
                                                <div class="comment-preview">
                                                    <?php echo nl2br(htmlspecialchars(substr($comment_text, 0, 200))); ?>...
                                                </div>
                                                <div class="comment-full hidden">
                                                    <?php echo nl2br(htmlspecialchars($comment_text)); ?>
                                                </div>
                                                <button class="toggle-comment mt-3 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center transition-colors" onclick="toggleCommentExpansion(this)">
                                                    <i class="fas fa-chevron-down mr-2 transition-transform"></i> 
                                                    <span>Voir plus</span>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <?php echo nl2br(htmlspecialchars($comment_text)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex justify-end space-x-3">
                                    <button class="edit-comment px-4 py-2 bg-purple-500/10 hover:bg-purple-500/20 text-purple-600 dark:text-purple-400 rounded-xl transition-all duration-300 hover:scale-105 font-medium flex items-center"
                                           data-id="<?php echo $comment->getId(); ?>"
                                           data-book-id="<?php echo $comment->getBookId(); ?>"
                                           data-rating="<?php echo $comment->getRating(); ?>"
                                           data-text="<?php echo htmlspecialchars($comment->getCommentText()); ?>">
                                        <i class="fas fa-edit mr-2"></i>Modifier
                                    </button>
                                    <a href="comments.php?delete=<?php echo $comment->getId(); ?>" 
                                       class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-xl transition-all duration-300 hover:scale-105 font-medium flex items-center"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                        <i class="fas fa-trash mr-2"></i>Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- État vide moderne -->
            <div class="text-center py-20">
                <div class="glass-card rounded-3xl p-12 max-w-lg mx-auto backdrop-blur-lg shadow-2xl">
                    <div class="bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/20 dark:to-purple-900/20 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-comments text-4xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Aucun commentaire</h2>
                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                        Vous n'avez pas encore posté d'avis sur les livres de la bibliothèque.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="../library.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                            <i class="fas fa-book-open mr-2"></i>Parcourir la bibliothèque
                        </a>
                        <a href="../user/my-library.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                            <i class="fas fa-heart mr-2"></i>Ma bibliothèque
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de modification ultra-moderne -->
<div id="editCommentModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
    <div class="glass-card rounded-3xl shadow-2xl p-8 w-full max-w-2xl border border-white/20 dark:border-gray-700/20 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <!-- En-tête du modal -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <div class="bg-gradient-to-r from-purple-500 to-indigo-600 w-10 h-10 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-edit text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Modifier le commentaire</h3>
            </div>
            <button id="closeModal" class="w-10 h-10 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-xl transition-all duration-300 hover:scale-110">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editCommentForm" action="../ajax/update_comment.php" method="POST">
            <input type="hidden" id="comment_id" name="comment_id" value="">
            <input type="hidden" id="book_id" name="book_id" value="">
            
            <!-- Sélection de la note -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    <i class="fas fa-star mr-2 text-yellow-500"></i>Votre note
                </label>
                <div class="flex items-center space-x-2 rating-selector bg-gray-50/50 dark:bg-gray-800/50 rounded-2xl p-4">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" id="rating<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden">
                        <label for="rating<?php echo $i; ?>" class="text-4xl text-gray-300 cursor-pointer rating-star hover:scale-110 transition-all duration-200">
                            ★
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Commentaire -->
            <div class="mb-6">
                <label for="comment_text" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    <i class="fas fa-comment mr-2 text-purple-600 dark:text-purple-400"></i>Votre commentaire
                </label>
                <textarea id="comment_text" name="comment_text" rows="8" 
                          class="w-full p-4 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-2xl focus:outline-none focus:ring-4 focus:ring-purple-500/20 focus:border-purple-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 resize-none"
                          placeholder="Partagez votre opinion sur ce livre..."
                          required></textarea>
                <div class="flex justify-between items-center mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>Décrivez ce que vous avez pensé de ce livre</span>
                    <span id="charCount">0 caractères</span>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" id="cancelEdit" class="px-6 py-3 bg-gray-200/80 dark:bg-gray-700/80 hover:bg-gray-300/80 dark:hover:bg-gray-600/80 text-gray-800 dark:text-gray-300 rounded-xl transition-all duration-300 hover:scale-105 backdrop-blur-sm font-medium">
                    <i class="fas fa-times mr-2"></i>Annuler
                </button>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                    <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>

<script src="../includes/js/comments.js"></script>