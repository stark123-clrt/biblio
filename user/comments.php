<?php
// user/comments.php - Gestion des commentaires de l'utilisateur
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Supprimer un commentaire si demandé
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $comment_id = intval($_GET['delete']);
    
    // Vérifier que le commentaire appartient bien à l'utilisateur courant
    $check = $conn->prepare("SELECT id FROM comments WHERE id = :comment_id AND user_id = :user_id");
    $check->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
    $check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check->execute();
    
    if ($check->rowCount() > 0) {
        $delete = $conn->prepare("DELETE FROM comments WHERE id = :comment_id");
        $delete->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        if ($delete->execute()) {
            $success_message = "Le commentaire a été supprimé avec succès.";
        } else {
            $error_message = "Une erreur est survenue lors de la suppression du commentaire.";
        }
    } else {
        $error_message = "Vous n'êtes pas autorisé à supprimer ce commentaire.";
    }
}

// Récupérer les commentaires de l'utilisateur
$stmt = $conn->prepare("SELECT c.*, b.title as book_title, b.cover_image 
                        FROM comments c 
                        JOIN books b ON c.book_id = b.id 
                        WHERE c.user_id = :user_id 
                        ORDER BY c.created_at DESC");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Mes Commentaires";
include "../includes/header.php";
?>

<style>
/* Styles pour le mode clair/sombre */
:root {
  --primary-light: #2563eb;
  --primary-dark: #1d4ed8;
  --bg-light: #f9fafb;
  --bg-dark: #111827;
  --card-light: #ffffff;
  --card-dark: #1f2937;
  --card-alt-light: #f9fafb;
  --card-alt-dark: #374151;
  --text-light: #374151;
  --text-dark: #e5e7eb;
  --border-light: #e5e7eb;
  --border-dark: #374151;
  --hover-light: #f3f4f6;
  --hover-dark: #374151;
  --success-light: #d1fae5;
  --success-dark: #064e3b;
  --success-text-light: #065f46;
  --success-text-dark: #10b981;
  --error-light: #fee2e2;
  --error-dark: #7f1d1d;
  --error-text-light: #b91c1c;
  --error-text-dark: #ef4444;
  --yellow-light: #fef3c7;
  --yellow-dark: #78350f;
  --yellow-text-light: #92400e;
  --yellow-text-dark: #fbbf24;
  --blue-light: #dbeafe;
  --blue-dark: #1e3a8a;
  --blue-text-light: #1d4ed8;
  --blue-text-dark: #3b82f6;
  --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.18);
  --shadow-hover-light: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-hover-dark: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

/* Transitions globales */
body, .bg-white, .bg-gray-50, input, select, textarea, button, a {
  transition: all 0.3s ease;
}

/* Adaptations du mode sombre */
.dark .bg-white {
  background-color: var(--card-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark .bg-gray-50 {
  background-color: var(--card-alt-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

/* Message de succès */
.bg-green-100 {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

.dark .bg-green-100 {
  background-color: var(--success-dark);
  border-color: var(--success-text-dark);
  color: var(--success-text-dark);
}

/* Message d'erreur */
.bg-red-100 {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

.dark .bg-red-100 {
  background-color: var(--error-dark);
  border-color: var(--error-text-dark);
  color: var(--error-text-dark);
}

/* Badges de statut */
.dark .bg-green-100.text-green-800 {
  background-color: var(--success-dark);
  color: var(--success-text-dark);
}

.dark .bg-yellow-100.text-yellow-800 {
  background-color: var(--yellow-dark);
  color: var(--yellow-text-dark);
}

.dark .bg-blue-100.text-blue-800 {
  background-color: var(--blue-dark);
  color: var(--blue-text-dark);
}

/* Style amélioré des commentaires */
.comment-card {
  transition: transform 0.2s ease, box-shadow 0.3s ease, background-color 0.3s ease;
  overflow: hidden;
}

.comment-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-hover-light);
}

.dark .comment-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Boutons d'action */
.action-button {
  transition: all 0.3s ease;
  overflow: hidden;
  position: relative;
}

.action-button:after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 5px;
  height: 5px;
  background: rgba(255, 255, 255, 0.3);
  opacity: 0;
  border-radius: 100%;
  transform: scale(1, 1) translate(-50%);
  transform-origin: 50% 50%;
}

.action-button:focus:not(:active)::after {
  animation: ripple 1s ease-out;
}

@keyframes ripple {
  0% {
    transform: scale(0, 0);
    opacity: 0.5;
  }
  20% {
    transform: scale(25, 25);
    opacity: 0.3;
  }
  100% {
    opacity: 0;
    transform: scale(40, 40);
  }
}

/* Modal animation */
#modalContent {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

/* Toast notification */
.toast-notification {
  transition: all 0.3s ease;
}

/* Text colors for dark mode */
.dark .text-gray-400 {
  color: #6b7280;
}

.dark .text-gray-600 {
  color: #9ca3af;
}

.dark .text-gray-700 {
  color: #d1d5db;
}

.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .text-blue-800 {
  color: #3b82f6;
}

.dark .text-indigo-600 {
  color: #818cf8;
}

.dark .text-indigo-800 {
  color: #6366f1;
}

.dark .text-red-600 {
  color: #f87171;
}

.dark .text-red-800 {
  color: #ef4444;
}

/* Style pour les étoiles */
.rating-star {
  transition: color 0.2s ease;
}

.dark .text-gray-300 {
  color: #4b5563;
}

.dark .text-yellow-400 {
  color: #fbbf24;
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white flex items-center">
        <i class="fas fa-comments mr-3 text-blue-600 dark:text-blue-500"></i>
        Mes Commentaires
    </h1>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-4 dark:bg-green-900 dark:border-green-500 dark:text-green-200">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $success_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-500 dark:text-red-200">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($comments)): ?>
        <div class="space-y-6">
            <?php foreach ($comments as $comment): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow comment-card">
                    <div class="md:flex">
                        <div class="md:w-1/4 bg-gray-50 p-4 flex items-center dark:bg-gray-800 dark:border-r dark:border-gray-700">
                            <div class="w-full">
                                <div class="flex items-center mb-3">
                                    <?php if (!empty($comment['cover_image'])): ?>
                                        <img src="../<?php echo $comment['cover_image']; ?>" alt="<?php echo htmlspecialchars($comment['book_title']); ?>" class="w-16 h-16 object-cover rounded shadow-sm">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-gray-200 flex items-center justify-center rounded shadow-sm dark:bg-gray-700">
                                            <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ml-3">
                                        <h3 class="font-bold line-clamp-2">
                                            <a href="../book.php?id=<?php echo $comment['book_id']; ?>" class="text-blue-600 hover:text-blue-800 transition-colors dark:text-blue-400 dark:hover:text-blue-300">
                                                <?php echo htmlspecialchars($comment['book_title']); ?>
                                            </a>
                                        </h3>
                                    </div>
                                </div>
                                
                                <div class="text-yellow-400 text-lg mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $comment['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo date('d/m/Y à H:i', strtotime($comment['created_at'])); ?>
                                    <?php if ($comment['created_at'] != $comment['updated_at']): ?>
                                        <span class="italic ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full dark:bg-blue-900 dark:text-blue-300">
                                            modifié
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="md:w-3/4 p-6 dark:text-gray-300">
                            <div class="flex justify-between items-start mb-4">
                                <div class="font-semibold">Mon commentaire</div>
                                <div>
                                    <?php if ($comment['is_validated']): ?>
                                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-2 py-1 rounded-full dark:bg-green-900 dark:text-green-400">
                                            <i class="fas fa-check-circle mr-1"></i>Validé
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded-full dark:bg-yellow-900 dark:text-yellow-400">
                                            <i class="fas fa-clock mr-1"></i>En attente
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($comment['is_featured']): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded-full ml-1 dark:bg-blue-900 dark:text-blue-400">
                                            <i class="fas fa-star mr-1"></i>En vedette
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-gray-700 mb-4 dark:text-gray-300">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <a href="#" class="edit-comment text-indigo-600 hover:text-indigo-800 px-3 py-1 border border-indigo-600 rounded hover:bg-indigo-50 transition-colors action-button dark:text-indigo-400 dark:border-indigo-400 dark:hover:bg-indigo-900" 
                                   data-id="<?php echo $comment['id']; ?>"
                                   data-book-id="<?php echo $comment['book_id']; ?>"
                                   data-rating="<?php echo $comment['rating']; ?>"
                                   data-text="<?php echo htmlspecialchars($comment['comment_text']); ?>">
                                    <i class="fas fa-edit mr-1"></i> Modifier
                                </a>
                                <a href="comments.php?delete=<?php echo $comment['id']; ?>" 
                                   class="text-red-600 hover:text-red-800 px-3 py-1 border border-red-600 rounded hover:bg-red-50 transition-colors action-button dark:text-red-400 dark:border-red-400 dark:hover:bg-red-900"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                    <i class="fas fa-trash mr-1"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center bg-white rounded-lg shadow-md p-8 dark:bg-gray-800">
            <div class="text-gray-400 text-6xl mb-4 dark:text-gray-600">
                <i class="fas fa-comments"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2 dark:text-gray-300">Aucun commentaire</h2>
            <p class="text-gray-600 mb-6 dark:text-gray-400">
                Vous n'avez pas encore posté de commentaires sur les livres.
            </p>
            <a href="../library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-all action-button">
                <i class="fas fa-book-open mr-2"></i> Parcourir la bibliothèque
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de modification de commentaire -->
<div id="editCommentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 backdrop-blur-sm">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg dark:bg-gray-800 dark:border dark:border-gray-700 transform transition-transform scale-95 opacity-0" id="modalContent">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold dark:text-gray-200">Modifier le commentaire</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors dark:text-gray-400 dark:hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editCommentForm" action="../ajax/update_comment.php" method="POST">
            <input type="hidden" id="comment_id" name="comment_id" value="">
            <input type="hidden" id="book_id" name="book_id" value="">
            
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2 dark:text-gray-300">Note</label>
                <div class="flex space-x-1 rating-selector">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" id="rating<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden">
                        <label for="rating<?php echo $i; ?>" class="text-3xl text-gray-300 cursor-pointer rating-star">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="comment_text" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">Commentaire</label>
                <textarea id="comment_text" name="comment_text" rows="6" 
                          class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                          required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 action-button">
                    <i class="fas fa-times mr-2"></i>Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors action-button">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal de modification de commentaire
    const modal = document.getElementById('editCommentModal');
    const modalContent = document.getElementById('modalContent');
    const closeModal = document.getElementById('closeModal');
    const cancelEdit = document.getElementById('cancelEdit');
    const editCommentForm = document.getElementById('editCommentForm');
    const commentIdInput = document.getElementById('comment_id');
    const bookIdInput = document.getElementById('book_id');
    const commentTextInput = document.getElementById('comment_text');
    const editCommentButtons = document.querySelectorAll('.edit-comment');
    const ratingStars = document.querySelectorAll('.rating-star');
    
    // Fonction pour initialiser les étoiles
    function setRating(rating) {
        document.getElementById('rating' + rating).checked = true;
        ratingStars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('text-yellow-400');
                star.classList.remove('text-gray-300');
            } else {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            }
        });
    }
    
    // Événements pour les étoiles
    ratingStars.forEach((star, index) => {
        const rating = index + 1;
        
        // Survol
        star.addEventListener('mouseenter', function() {
            ratingStars.forEach((s, i) => {
                if (i < rating) {
                    s.classList.add('text-yellow-400');
                    s.classList.remove('text-gray-300');
                } else {
                    s.classList.remove('text-yellow-400');
                    s.classList.add('text-gray-300');
                }
            });
        });
        
        // Clic
        star.addEventListener('click', function() {
            document.getElementById('rating' + rating).checked = true;
        });
    });
    
    // Réinitialiser les étoiles quand on quitte la zone
    const ratingSelector = document.querySelector('.rating-selector');
    ratingSelector.addEventListener('mouseleave', function() {
        for (let i = 1; i <= 5; i++) {
            if (document.getElementById('rating' + i).checked) {
                setRating(i);
                break;
            }
        }
    });
    
    // Fonction pour ouvrir le modal avec animation
    function openModal(id, bookId, rating, text) {
        commentIdInput.value = id;
        bookIdInput.value = bookId;
        commentTextInput.value = text;
        setRating(rating);
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    // Fonction pour fermer le modal avec animation
    function closeModalFunction() {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Événements pour ouvrir le modal
    editCommentButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const bookId = this.dataset.bookId;
            const rating = this.dataset.rating;
            const text = this.dataset.text;
            openModal(id, bookId, rating, text);
        });
    });
    
    // Événements pour fermer le modal
    closeModal.addEventListener('click', closeModalFunction);
    cancelEdit.addEventListener('click', closeModalFunction);
    
    // Fermer le modal en cliquant à l'extérieur
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModalFunction();
        }
    });
    
    // Fermer le modal avec échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModalFunction();
        }
    });
    
    // Soumission du formulaire via AJAX
    editCommentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const commentId = commentIdInput.value;
        const bookId = bookIdInput.value;
        const commentText = commentTextInput.value.trim();
        let rating = 1; // Valeur par défaut
        
        // Récupérer la note sélectionnée
        for (let i = 1; i <= 5; i++) {
            if (document.getElementById('rating' + i).checked) {
                rating = i;
                break;
            }
        }
        
        if (!commentText) {
            alert('Veuillez entrer un commentaire.');
            return;
        }
        
        // Bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
        submitBtn.disabled = true;
        
        // Envoi des données via AJAX
        $.ajax({
            url: '../ajax/update_comment.php',
            type: 'POST',
            data: {
                comment_id: commentId,
                book_id: bookId,
                rating: rating,
                comment_text: commentText
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // Fermer le modal
                        closeModalFunction();
                        
                        // Ajouter une notification de réussite temporaire
                        const successMessage = document.createElement('div');
                        successMessage.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 shadow-lg rounded dark:bg-green-900 dark:text-green-300 dark:border-green-600 transform translate-y-10 opacity-0 transition-all toast-notification';
                        successMessage.innerHTML = '<div class="flex"><i class="fas fa-check-circle mr-2 mt-1"></i><div>Commentaire mis à jour avec succès !</div></div>';
                        document.body.appendChild(successMessage);
                        
                        setTimeout(() => {
                            successMessage.classList.remove('translate-y-10', 'opacity-0');
                        }, 100);
                        
                        setTimeout(() => {
                            successMessage.classList.add('translate-y-10', 'opacity-0');
                            setTimeout(() => {
                                document.body.removeChild(successMessage);
                                // Recharger la page pour afficher les modifications
                                location.reload();
                            }, 300);
                        }, 2000);
                    } else {
                        alert('Erreur lors de la mise à jour du commentaire: ' + data.message);
                        // Restaurer le bouton
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Erreur lors du parsing de la réponse:', error);
                    alert('Une erreur est survenue lors de la mise à jour du commentaire.');
                    // Restaurer le bouton
                    submitBtn.innerHTML = originalContent;
                    submitBtn.disabled = false;
                }
            },
            error: function(error) {
                console.error('Erreur AJAX lors de la mise à jour du commentaire:', error);
                alert('Une erreur est survenue lors de la communication avec le serveur.');
                // Restaurer le bouton
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }
        });
    });
});
</script>

<?php include "../includes/footer.php"; ?>