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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Mes Commentaires</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($comments)): ?>
        <div class="space-y-6">
            <?php foreach ($comments as $comment): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="md:flex">
                        <div class="md:w-1/4 bg-gray-50 p-4 flex items-center">
                            <div class="w-full">
                                <div class="flex items-center mb-3">
                                    <?php if (!empty($comment['cover_image'])): ?>
                                        <img src="../<?php echo $comment['cover_image']; ?>" alt="<?php echo htmlspecialchars($comment['book_title']); ?>" class="w-16 h-16 object-cover rounded">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-gray-200 flex items-center justify-center rounded">
                                            <i class="fas fa-book text-2xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ml-3">
                                        <h3 class="font-bold line-clamp-2">
                                            <a href="../book.php?id=<?php echo $comment['book_id']; ?>" class="text-blue-600 hover:text-blue-800">
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
                                
                                <div class="text-sm text-gray-600">
                                    <?php echo date('d/m/Y à H:i', strtotime($comment['created_at'])); ?>
                                    <?php if ($comment['created_at'] != $comment['updated_at']): ?>
                                        <span class="italic ml-1">(modifié)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="md:w-3/4 p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="font-semibold">Mon commentaire</div>
                                <div>
                                    <?php if ($comment['is_validated']): ?>
                                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-2 py-1 rounded-full">
                                            Validé
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-1 rounded-full">
                                            En attente
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($comment['is_featured']): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded-full ml-1">
                                            En vedette
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-gray-700 mb-4">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <a href="#" class="edit-comment text-indigo-600 hover:text-indigo-800 px-3 py-1 border border-indigo-600 rounded hover:bg-indigo-50" 
                                   data-id="<?php echo $comment['id']; ?>"
                                   data-book-id="<?php echo $comment['book_id']; ?>"
                                   data-rating="<?php echo $comment['rating']; ?>"
                                   data-text="<?php echo htmlspecialchars($comment['comment_text']); ?>">
                                    <i class="fas fa-edit mr-1"></i> Modifier
                                </a>
                                <a href="comments.php?delete=<?php echo $comment['id']; ?>" 
                                   class="text-red-600 hover:text-red-800 px-3 py-1 border border-red-600 rounded hover:bg-red-50"
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
        <div class="text-center bg-white rounded-lg shadow-md p-8">
            <div class="text-gray-400 text-6xl mb-4">
                <i class="fas fa-comments"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2">Aucun commentaire</h2>
            <p class="text-gray-600 mb-4">
                Vous n'avez pas encore posté de commentaires sur les livres.
            </p>
            <a href="../library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Parcourir la bibliothèque
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de modification de commentaire -->
<div id="editCommentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Modifier le commentaire</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editCommentForm" action="../ajax/update_comment.php" method="POST">
            <input type="hidden" id="comment_id" name="comment_id" value="">
            <input type="hidden" id="book_id" name="book_id" value="">
            
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Note</label>
                <div class="flex space-x-1 rating-selector">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <input type="radio" id="rating<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden">
                        <label for="rating<?php echo $i; ?>" class="text-3xl text-gray-300 cursor-pointer rating-star">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="comment_text" class="block text-gray-700 font-bold mb-2">Commentaire</label>
                <textarea id="comment_text" name="comment_text" rows="6" 
                          class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du modal de modification de commentaire
        const modal = document.getElementById('editCommentModal');
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
        
        // Fonction pour ouvrir le modal
        function openModal(id, bookId, rating, text) {
            commentIdInput.value = id;
            bookIdInput.value = bookId;
            commentTextInput.value = text;
            setRating(rating);
            modal.classList.remove('hidden');
        }
        
        // Fonction pour fermer le modal
        function closeModalFunction() {
            modal.classList.add('hidden');
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
                            // Recharger la page pour afficher les modifications
                            location.reload();
                        } else {
                            alert('Erreur lors de la mise à jour du commentaire: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Erreur lors du parsing de la réponse:', error);
                        alert('Une erreur est survenue lors de la mise à jour du commentaire.');
                    }
                },
                error: function(error) {
                    console.error('Erreur AJAX lors de la mise à jour du commentaire:', error);
                    alert('Une erreur est survenue lors de la communication avec le serveur.');
                }
            });
        });
    });
</script>

<?php include "../includes/footer.php"; ?>