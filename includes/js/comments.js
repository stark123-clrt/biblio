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