<?php
// notes.php - Version 100% POO avec interface moderne et dark mode
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
    $noteRepository = new NoteRepository();
    $bookRepository = new BookRepository();
    
    $user_id = $_SESSION['user_id'];
    
    // ✅ PARAMÈTRES DE FILTRAGE ET TRI
    $book_id = intval($_GET['book_id'] ?? 0);
    $sort = $_GET['sort'] ?? 'newest';
    $search = trim($_GET['search'] ?? '');
    
    // ✅ VARIABLES POUR LA VUE (SÉPARATION MVC)
    $success_message = null;
    $error_message = null;
    
    // ================================
    // 🗑️ SUPPRESSION D'UNE NOTE
    // ================================
    
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $note_id = intval($_GET['delete']);
        
        try {
            if ($noteRepository->delete($note_id, $user_id)) {
                $success_message = "La note a été supprimée avec succès.";
            } else {
                $error_message = "Vous n'êtes pas autorisé à supprimer cette note.";
            }
        } catch (Exception $e) {
            if (config('app.debug', false)) {
                $error_message = "Erreur de suppression : " . $e->getMessage();
            } else {
                error_log("Note deletion error: " . $e->getMessage());
                $error_message = "Une erreur est survenue lors de la suppression de la note.";
            }
        }
    }
    
    // ✅ RÉCUPÉRER LES NOTES (RETOURNE DES OBJETS Note)
    $notes = $noteRepository->findByUserWithFilters($user_id, $book_id, $search, $sort);
    
    // ✅ RÉCUPÉRER LES LIVRES DE L'UTILISATEUR QUI ONT DES NOTES (RETOURNE DES OBJETS Book)
    $user_books = $noteRepository->getUserBooksWithNotes($user_id);
    
    // ✅ STATISTIQUES DES NOTES
    $total_notes = $noteRepository->countByUser($user_id);
    $notes_displayed = count($notes);
    $average_length = 0;
    if (!empty($notes)) {
        $total_length = array_sum(array_map(function($note) { 
            return strlen($note->getNoteText()); 
        }, $notes));
        $average_length = round($total_length / count($notes));
    }
    
} catch (Exception $e) {
    if (config('app.debug', false)) {
        die("Erreur d'initialisation : " . $e->getMessage());
    } else {
        error_log("Notes page initialization error: " . $e->getMessage());
        $notes = [];
        $user_books = [];
        $error_message = "Une erreur est survenue lors du chargement des notes.";
        $total_notes = 0;
        $notes_displayed = 0;
        $average_length = 0;
    }
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Mes Notes";
include "../includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <link rel="stylesheet" href="../includes/style/notes.css">
    
    <div class="container mx-auto px-4 py-8">
        <!-- Hero Section moderne -->
        <div class="text-center mb-12">
            <div class="relative inline-block">
                <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                    <i class="fas fa-sticky-note mr-4 text-blue-600 dark:text-blue-400"></i>
                    Mes Notes
                </h1>
                <div class="absolute -inset-2 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-lg -z-10"></div>
            </div>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Organisez et retrouvez facilement toutes vos annotations personnelles
            </p>
        </div>

        <!-- Statistiques en cards modernes -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg">
                    <i class="fas fa-sticky-note text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 dark:text-white mb-1"><?php echo $notes_displayed; ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Note<?php echo $notes_displayed > 1 ? 's' : ''; ?> affichée<?php echo $notes_displayed > 1 ? 's' : ''; ?></div>
            </div>
            
            <div class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300">
                <div class="bg-gradient-to-br from-green-500 to-green-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg">
                    <i class="fas fa-book text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 dark:text-white mb-1"><?php echo count($user_books); ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Livre<?php echo count($user_books) > 1 ? 's' : ''; ?> annoté<?php echo count($user_books) > 1 ? 's' : ''; ?></div>
            </div>
            
            <div class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300">
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 dark:text-white mb-1"><?php echo $total_notes; ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total note<?php echo $total_notes > 1 ? 's' : ''; ?></div>
            </div>
            
            <div class="glass-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300">
                <div class="bg-gradient-to-br from-orange-500 to-red-500 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 text-white shadow-lg">
                    <i class="fas fa-text-width text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800 dark:text-white mb-1"><?php echo $average_length; ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Caractères (moyenne)</div>
            </div>
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
        
        <!-- Filtres et recherche avec design moderne -->
        <div class="glass-card rounded-3xl p-8 mb-8 backdrop-blur-lg shadow-2xl border border-white/20 dark:border-gray-700/20">
            <div class="flex items-center mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-purple-500 w-10 h-10 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-filter text-white"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Filtres et recherche</h3>
            </div>
            
            <form action="notes.php" method="GET" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="book_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            <i class="fas fa-book mr-2 text-blue-600 dark:text-blue-400"></i>Livre
                        </label>
                        <select id="book_id" name="book_id" 
                                class="w-full px-4 py-3 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="0">Tous les livres</option>
                            <?php foreach ($user_books as $book): ?>
                                <option value="<?php echo $book->getId(); ?>" <?php echo $book_id == $book->getId() ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($book->getTitle()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sort" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            <i class="fas fa-sort mr-2 text-green-600 dark:text-green-400"></i>Trier par
                        </label>
                        <select id="sort" name="sort" 
                                class="w-full px-4 py-3 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récentes</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciennes</option>
                            <option value="book" <?php echo $sort == 'book' ? 'selected' : ''; ?>>Titre du livre</option>
                            <option value="page" <?php echo $sort == 'page' ? 'selected' : ''; ?>>Numéro de page</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            <i class="fas fa-search mr-2 text-purple-600 dark:text-purple-400"></i>Recherche
                        </label>
                        <div class="relative">
                            <input type="text" id="search" name="search" placeholder="Rechercher dans vos notes..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full px-4 py-3 pl-12 pr-16 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <button type="submit" class="absolute right-2 top-2 bottom-2 px-4 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg transition-all duration-300 hover:scale-105">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-4">
                    <?php if ($book_id > 0 || !empty($search) || $sort != 'newest'): ?>
                        <a href="notes.php" class="px-6 py-3 bg-gray-200/80 dark:bg-gray-700/80 hover:bg-gray-300/80 dark:hover:bg-gray-600/80 text-gray-800 dark:text-gray-300 rounded-xl transition-all duration-300 hover:scale-105 backdrop-blur-sm flex items-center font-medium">
                            <i class="fas fa-times mr-2"></i> Réinitialiser
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center font-medium">
                        <i class="fas fa-filter mr-2"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Liste des notes avec design moderne -->
        <?php if (!empty($notes)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php foreach ($notes as $index => $note): ?>
                    <div class="glass-card rounded-3xl overflow-hidden hover:scale-[1.02] transition-all duration-500 group shadow-xl border border-white/20 dark:border-gray-700/20 slide-up" style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <!-- En-tête de la note -->
                        <div class="p-6 border-b border-gray-200/50 dark:border-gray-700/50 bg-gradient-to-r from-gray-50/50 to-white/50 dark:from-gray-800/50 dark:to-gray-900/50 backdrop-blur-sm">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold mb-2">
                                        <a href="../book.php?id=<?php echo $note->getBookId(); ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors hover:underline">
                                            <i class="fas fa-book mr-2"></i><?php echo htmlspecialchars($note->getBookTitle()); ?>
                                        </a>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span class="inline-flex items-center bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full">
                                            <i class="fas fa-file-alt mr-1"></i> Page <?php echo $note->getPageNumber(); ?>
                                        </span>
                                        <span class="inline-flex items-center bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 px-3 py-1 rounded-full">
                                            <i class="far fa-clock mr-1"></i> <?php echo $note->getFormattedDate(); ?>
                                        </span>
                                        <?php if ($note->getCreatedAt() != $note->getUpdatedAt()): ?>
                                            <span class="inline-flex items-center bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 px-3 py-1 rounded-full">
                                                <i class="fas fa-edit mr-1"></i> modifiée
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex space-x-2 ml-4">
                                    <a href="../read.php?id=<?php echo $note->getBookId(); ?>&page=<?php echo $note->getPageNumber(); ?>" 
                                       class="w-10 h-10 bg-blue-500/10 hover:bg-blue-500/20 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110" 
                                       title="Voir dans le livre">
                                        <i class="fas fa-book-open text-sm"></i>
                                    </a>
                                    <button class="edit-note w-10 h-10 bg-purple-500/10 hover:bg-purple-500/20 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110" 
                                       title="Modifier" data-id="<?php echo $note->getId(); ?>"
                                       data-text="<?php echo htmlspecialchars($note->getNoteText()); ?>">
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <a href="notes.php?delete=<?php echo $note->getId(); ?>" 
                                       class="w-10 h-10 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110" 
                                       title="Supprimer"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette note ?');">
                                        <i class="fas fa-trash text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contenu de la note -->
                        <div class="p-6">
                            <div class="note-text text-gray-700 dark:text-gray-300 leading-relaxed">
                                <?php 
                                $note_text = $note->getNoteText();
                                $is_long = strlen($note_text) > 300;
                                ?>
                                
                                <?php if ($is_long): ?>
                                    <div class="note-content">
                                        <div class="note-preview">
                                            <?php echo nl2br(htmlspecialchars(substr($note_text, 0, 300))); ?>...
                                        </div>
                                        <div class="note-full hidden">
                                            <?php echo nl2br(htmlspecialchars($note_text)); ?>
                                        </div>
                                        <button class="toggle-note mt-4 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center transition-colors" onclick="toggleNoteExpansion(this)">
                                            <i class="fas fa-chevron-down mr-2 transition-transform"></i> 
                                            <span>Voir plus</span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars($note_text)); ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Badge de longueur -->
                            <div class="mt-4 pt-4 border-t border-gray-200/50 dark:border-gray-700/50">
                                <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                    <span class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-full">
                                        <?php echo strlen($note_text); ?> caractères
                                    </span>
                                    <span class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-full">
                                        <?php echo str_word_count($note_text); ?> mots
                                    </span>
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
                        <i class="fas fa-sticky-note text-4xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Aucune note trouvée</h2>
                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                        <?php if (!empty($search) || $book_id > 0): ?>
                            Essayez de modifier vos critères de recherche pour découvrir vos notes.
                        <?php else: ?>
                            Commencez à prendre des notes en lisant vos livres favoris !
                        <?php endif; ?>
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if (!empty($search) || $book_id > 0): ?>
                            <a href="notes.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                                <i class="fas fa-undo-alt mr-2"></i>Voir toutes mes notes
                            </a>
                        <?php endif; ?>
                        <a href="../library.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-xl transition-all duration-300 hover:scale-105 shadow-lg font-medium">
                            <i class="fas fa-book-open mr-2"></i>Parcourir la bibliothèque
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de modification de note ultra-moderne -->
<div id="editNoteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
    <div class="glass-card rounded-3xl shadow-2xl p-8 w-full max-w-3xl border border-white/20 dark:border-gray-700/20 transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <!-- En-tête du modal -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <div class="bg-gradient-to-r from-purple-500 to-indigo-600 w-10 h-10 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-edit text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white">Modifier la note</h3>
            </div>
            <button id="closeModal" class="w-10 h-10 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-xl transition-all duration-300 hover:scale-110">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editNoteForm" action="../ajax/update_note.php" method="POST">
            <input type="hidden" id="note_id" name="note_id" value="">
            
            <div class="mb-6">
                <label for="note_text" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    <i class="fas fa-sticky-note mr-2 text-purple-600 dark:text-purple-400"></i>Contenu de la note
                </label>
                <textarea id="note_text" name="note_text" rows="10" 
                          class="w-full p-4 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm border border-gray-200 dark:border-gray-600 rounded-2xl focus:outline-none focus:ring-4 focus:ring-purple-500/20 focus:border-purple-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 resize-none"
                          placeholder="Tapez votre note ici..."
                          required></textarea>
                <div class="flex justify-between items-center mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>Utilisez Ctrl+Enter pour sauvegarder rapidement</span>
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

<style>
/* Styles pour l'animation des notes */
.slide-up {
    opacity: 0;
    transform: translateY(20px);
    animation: slideUpFade 0.6s ease-out forwards;
}

@keyframes slideUpFade {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Glass effect pour les cards */
.glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dark .glass-card {
    background: rgba(17, 24, 39, 0.7);
    border: 1px solid rgba(75, 85, 99, 0.2);
}

/* Animation pour l'expansion des notes */
.note-full {
    animation: expandNote 0.3s ease-out;
}

@keyframes expandNote {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 1000px;
    }
}

/* Animation du modal */
#editNoteModal.show #modalContent {
    opacity: 1;
    transform: scale(1);
}

/* Compteur de caractères */
#charCount {
    transition: color 0.3s ease;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .glass-card {
        margin: 0 0.5rem;
    }
    
    .grid.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===============================
    // 🎨 ANIMATIONS D'ENTRÉE DES CARDS
    // ===============================
    const cards = document.querySelectorAll('.slide-up');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.animationDelay = `${index * 0.1}s`;
        }, index * 100);
    });

    // ===============================
    // 📝 GESTION DU MODAL MODERNE
    // ===============================
    const modal = document.getElementById('editNoteModal');
    const modalContent = document.getElementById('modalContent');
    const closeModal = document.getElementById('closeModal');
    const cancelEdit = document.getElementById('cancelEdit');
    const editNoteForm = document.getElementById('editNoteForm');
    const noteIdInput = document.getElementById('note_id');
    const noteTextInput = document.getElementById('note_text');
    const editNoteButtons = document.querySelectorAll('.edit-note');
    const charCount = document.getElementById('charCount');
    
    // ===============================
    // 🔢 COMPTEUR DE CARACTÈRES
    // ===============================
    if (noteTextInput && charCount) {
        noteTextInput.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = `${count} caractères`;
            
            // Code couleur selon la longueur
            if (count > 1000) {
                charCount.style.color = '#ef4444'; // Rouge
                charCount.classList.add('font-bold');
            } else if (count > 500) {
                charCount.style.color = '#f59e0b'; // Orange
                charCount.classList.remove('font-bold');
            } else {
                charCount.style.color = '';
                charCount.classList.remove('font-bold');
            }
        });
        
        // ⚡ RACCOURCI CTRL+ENTER POUR SAUVEGARDER
        noteTextInput.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                editNoteForm.dispatchEvent(new Event('submit'));
            }
        });
    }
    
    // ===============================
    // 🪟 FONCTIONS DU MODAL
    // ===============================
    
    // Fonction pour ouvrir le modal avec animation fluide
    function openModal(id, text) {
        noteIdInput.value = id;
        noteTextInput.value = text;
        
        // Déclencher l'event pour le compteur de caractères
        if (charCount) {
            const event = new Event('input');
            noteTextInput.dispatchEvent(event);
        }
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.add('show');
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        // Focus sur le textarea
        setTimeout(() => {
            noteTextInput.focus();
            noteTextInput.setSelectionRange(noteTextInput.value.length, noteTextInput.value.length);
        }, 100);
    }
    
    // Fonction pour fermer le modal avec animation fluide
    function closeModalFunction() {
        modal.classList.remove('show');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // ===============================
    // 🎯 ÉVÉNEMENTS DU MODAL
    // ===============================
    
    // Événements pour ouvrir le modal
    editNoteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const text = this.dataset.text;
            openModal(id, text);
        });
    });
    
    // Événements pour fermer le modal
    if (closeModal) closeModal.addEventListener('click', closeModalFunction);
    if (cancelEdit) cancelEdit.addEventListener('click', closeModalFunction);
    
    // Fermer le modal en cliquant à l'extérieur
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalFunction();
            }
        });
    }
    
    // Fermer le modal avec échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModalFunction();
        }
    });
    
    // ===============================
    // 📤 SOUMISSION AJAX MODERNISÉE
    // ===============================
    
    if (editNoteForm) {
        editNoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const noteId = noteIdInput.value;
            const noteText = noteTextInput.value.trim();
            
            if (!noteText) {
                showToast('Veuillez entrer une note.', 'error');
                return;
            }
            
            // Bouton de soumission avec animation
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75');
            
            // Envoi des données via AJAX (jQuery ou Fetch)
            if (typeof $ !== 'undefined') {
                // Version jQuery si disponible
                $.ajax({
                    url: '../ajax/update_note.php',
                    type: 'POST',
                    data: {
                        note_id: noteId,
                        note_text: noteText
                    },
                    success: function(response) {
                        handleAjaxSuccess(response, noteId, noteText, submitBtn, originalContent);
                    },
                    error: function(error) {
                        handleAjaxError(error, submitBtn, originalContent);
                    }
                });
            } else {
                // Version Fetch native
                const formData = new FormData();
                formData.append('note_id', noteId);
                formData.append('note_text', noteText);
                
                fetch('../ajax/update_note.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(response => {
                    handleAjaxSuccess(response, noteId, noteText, submitBtn, originalContent);
                })
                .catch(error => {
                    handleAjaxError(error, submitBtn, originalContent);
                });
            }
        });
    }
    
    // ===============================
    // 🎯 FONCTIONS DE GESTION AJAX
    // ===============================
    
    function handleAjaxSuccess(response, noteId, noteText, submitBtn, originalContent) {
        try {
            const data = JSON.parse(response);
            if (data.success) {
                // Mettre à jour l'interface utilisateur
                updateNoteInDOM(noteId, noteText);
                
                // Fermer le modal
                closeModalFunction();
                
                // Toast de succès
                showToast('Note mise à jour avec succès !', 'success');
                
            } else {
                showToast('Erreur lors de la mise à jour: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Erreur lors du parsing de la réponse:', error);
            showToast('Une erreur est survenue lors de la mise à jour de la note.', 'error');
        }
        
        // Restaurer le bouton
        restoreButton(submitBtn, originalContent);
    }
    
    function handleAjaxError(error, submitBtn, originalContent) {
        console.error('Erreur AJAX lors de la mise à jour de la note:', error);
        showToast('Une erreur est survenue lors de la communication avec le serveur.', 'error');
        
        // Restaurer le bouton
        restoreButton(submitBtn, originalContent);
    }
    
    function restoreButton(submitBtn, originalContent) {
        submitBtn.innerHTML = originalContent;
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-75');
    }
    
    function updateNoteInDOM(noteId, noteText) {
        // Trouver l'élément note dans la nouvelle structure
        const editButton = document.querySelector(`.edit-note[data-id="${noteId}"]`);
        if (editButton) {
            const noteCard = editButton.closest('.glass-card');
            if (noteCard) {
                const noteTextElement = noteCard.querySelector('.note-text');
                if (noteTextElement) {
                    // Mettre à jour le contenu avec gestion des longues notes
                    const formattedText = noteText.replace(/\n/g, '<br>');
                    
                    if (noteText.length > 300) {
                        // Recréer la structure pour les longues notes
                        const shortText = noteText.substring(0, 300);
                        noteTextElement.innerHTML = `
                            <div class="note-content">
                                <div class="note-preview">
                                    ${shortText.replace(/\n/g, '<br>')}...
                                </div>
                                <div class="note-full hidden">
                                    ${formattedText}
                                </div>
                                <button class="toggle-note mt-4 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center transition-colors" onclick="toggleNoteExpansion(this)">
                                    <i class="fas fa-chevron-down mr-2 transition-transform"></i> 
                                    <span>Voir plus</span>
                                </button>
                            </div>
                        `;
                    } else {
                        noteTextElement.innerHTML = formattedText;
                    }
                    
                    // Mettre à jour le data-text du bouton edit
                    editButton.dataset.text = noteText;
                    
                    // Mettre à jour les statistiques de caractères
                    updateCharacterStats(noteCard, noteText);
                }
            }
        }
    }
    
    function updateCharacterStats(noteCard, noteText) {
        const statsSection = noteCard.querySelector('.border-t');
        if (statsSection) {
            const charElement = statsSection.querySelector('span:first-child');
            const wordElement = statsSection.querySelector('span:last-child');
            
            if (charElement) charElement.textContent = `${noteText.length} caractères`;
            if (wordElement) wordElement.textContent = `${countWords(noteText)} mots`;
        }
    }
    
    function countWords(text) {
        return text.trim().split(/\s+/).filter(word => word.length > 0).length;
    }
});

// ===============================
// 📝 FONCTION TOGGLE NOTE EXPANSION (Globale)
// ===============================
function toggleNoteExpansion(button) {
    const noteContent = button.closest('.note-content');
    const preview = noteContent.querySelector('.note-preview');
    const full = noteContent.querySelector('.note-full');
    const icon = button.querySelector('i');
    const text = button.querySelector('span');
    
    if (full.classList.contains('hidden')) {
        // Afficher le contenu complet
        preview.style.display = 'none';
        full.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
        text.textContent = 'Voir moins';
        
        // Animation d'expansion
        full.style.animation = 'expandNote 0.3s ease-out';
    } else {
        // Afficher le contenu réduit
        preview.style.display = 'block';
        full.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
        text.textContent = 'Voir plus';
    }
}

// ===============================
// 🎨 SYSTÈME DE TOAST NOTIFICATIONS MODERNE
// ===============================
function showToast(message, type = 'info', duration = 4000) {
    // Supprimer les anciens toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Créer le nouveau toast
    const toast = document.createElement('div');
    toast.className = 'toast-notification fixed bottom-6 right-6 max-w-sm p-4 rounded-2xl shadow-2xl transform translate-y-10 opacity-0 transition-all duration-300 z-50';
    
    // Styles selon le type
    const styles = {
        success: {
            bg: 'bg-gradient-to-r from-green-500 to-emerald-500',
            icon: 'fas fa-check-circle',
            textColor: 'text-white'
        },
        error: {
            bg: 'bg-gradient-to-r from-red-500 to-pink-500',
            icon: 'fas fa-exclamation-circle',
            textColor: 'text-white'
        },
        info: {
            bg: 'bg-gradient-to-r from-blue-500 to-indigo-500',
            icon: 'fas fa-info-circle',
            textColor: 'text-white'
        },
        warning: {
            bg: 'bg-gradient-to-r from-yellow-500 to-orange-500',
            icon: 'fas fa-exclamation-triangle',
            textColor: 'text-white'
        }
    };
    
    const style = styles[type] || styles.info;
    toast.className += ` ${style.bg} ${style.textColor}`;
    
    // Contenu du toast
    toast.innerHTML = `
        <div class="flex items-center">
            <div class="bg-white/20 w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                <i class="${style.icon} text-sm"></i>
            </div>
            <div class="flex-1">
                <p class="font-medium leading-tight">${message}</p>
            </div>
            <button class="ml-3 bg-white/20 hover:bg-white/30 w-6 h-6 rounded-full flex items-center justify-center transition-colors" onclick="this.closest('.toast-notification').remove()">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    }, 100);
    
    // Animation de sortie automatique
    setTimeout(() => {
        toast.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, duration);
}

// ===============================
// 🎯 AMÉLIORATION DES FILTRES (OPTIONNEL)
// ===============================
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit des filtres avec délai
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Optionnel: soumission automatique après 1 seconde d'inactivité
                // this.closest('form').submit();
            }, 1000);
        });
    }
    
    // Animation des selects
    const selects = document.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.classList.add('ring-2', 'ring-blue-500/50');
            setTimeout(() => {
                this.classList.remove('ring-2', 'ring-blue-500/50');
            }, 300);
        });
    });
});
</script>