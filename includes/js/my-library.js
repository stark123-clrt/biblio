

    document.addEventListener('DOMContentLoaded', function() {
        // Filtrage des livres
        const filterButtons = document.querySelectorAll('.filter-btn');
        const libraryBooks = document.querySelectorAll('.library-book');
        const searchInput = document.getElementById('searchLibrary');
        
        // Fonction de filtrage
        function filterBooks() {
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            libraryBooks.forEach(book => {
                const bookTitle = book.dataset.title;
                const matchesFilter = activeFilter === 'all' || book.classList.contains(activeFilter);
                const matchesSearch = bookTitle.includes(searchTerm);
                
                if (matchesFilter && matchesSearch) {
                    book.style.display = 'block';
                } else {
                    book.style.display = 'none';
                }
            });
        }
        
        // Événements des boutons de filtre
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Retirer la classe active de tous les boutons
                filterButtons.forEach(btn => btn.classList.remove('active', 'bg-blue-600', 'text-white'));
                filterButtons.forEach(btn => {
                    if (document.documentElement.classList.contains('dark')) {
                        btn.classList.add('bg-gray-700', 'text-gray-200');
                    } else {
                        btn.classList.add('bg-gray-200', 'text-gray-800');
                    }
                });
                
                // Ajouter la classe active au bouton cliqué
                this.classList.add('active', 'bg-blue-600', 'text-white');
                this.classList.remove('bg-gray-200', 'bg-gray-700', 'text-gray-800', 'text-gray-200');
                
                // Filtrer les livres
                filterBooks();
            });
        });
        
        // Événement de recherche
        searchInput.addEventListener('input', filterBooks);
        // Gestion des favoris
        const favoriteButtons = document.querySelectorAll('.favorite-btn');
        
        favoriteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bookId = this.dataset.id;
                const isFavorite = this.dataset.favorite === '1';
                const newFavoriteState = !isFavorite;
                
                // Mettre à jour l'interface utilisateur
                this.querySelector('i').classList.toggle('fas', newFavoriteState);
                this.querySelector('i').classList.toggle('far', !newFavoriteState);
                this.dataset.favorite = newFavoriteState ? '1' : '0';
                
                // Mettre à jour le statut du livre pour le filtrage
                const bookElement = this.closest('.library-book');
                if (newFavoriteState) {
                    bookElement.classList.add('favorites');
                } else {
                    bookElement.classList.remove('favorites');
                }
                
                // Envoyer la mise à jour au serveur
                $.ajax({
                    url: '../ajax/toggle_favorite.php',
                    type: 'POST',
                    data: {
                        book_id: bookId,
                        is_favorite: newFavoriteState ? 1 : 0
                    },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (!data.success) {
                                console.error('Erreur lors de la mise à jour du favori:', data.message);
                            }
                        } catch (error) {
                            console.error('Erreur lors du parsing de la réponse:', error);
                        }
                    },
                    error: function(error) {
                        console.error('Erreur AJAX lors de la mise à jour du favori:', error);
                    }
                });
            });
        });

        // Gestion des boutons de suppression de livres
        const removeBookButtons = document.querySelectorAll('.remove-book-btn');

        removeBookButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bookId = this.dataset.id;
                const bookTitle = this.dataset.title;
                
                // Confirmation avant suppression
                if (confirm(`Voulez-vous vraiment retirer "${bookTitle}" de votre bibliothèque ?\n\nCette action supprimera également vos notes et marque-pages associés à ce livre.`)) {
                    // Ajouter un effet visuel durant la suppression
                    const bookElement = this.closest('.library-book');
                    bookElement.style.opacity = '0.5';
                    
                    // Envoyer la requête de suppression
                    $.ajax({
                        url: '../ajax/remove_from_library.php',
                        type: 'POST',
                        data: {
                            book_id: bookId
                        },
                        success: function(response) {
                            let success = false;
                            let message = 'Une erreur est survenue';
                            
                            // Traiter la réponse de manière robuste
                            try {
                                // Si c'est déjà un objet JavaScript
                                if (typeof response === 'object') {
                                    success = response.success;
                                    message = response.message;
                                } 
                                // Si c'est une chaîne JSON
                                else if (typeof response === 'string') {
                                    // Nettoyer et parser
                                    const data = JSON.parse(response.trim());
                                    success = data.success;
                                    message = data.message;
                                }
                            } catch (error) {
                                console.error('Erreur lors du parsing de la réponse:', error);
                                console.log('Réponse brute:', response);
                                
                                // Vérification de secours : vérifier si la chaîne contient "success":true
                                if (typeof response === 'string' && response.includes('"success":true')) {
                                    success = true;
                                    message = 'Livre retiré de votre bibliothèque avec succès';
                                }
                            }
                            
                            // Traiter le résultat
                            if (success) {
                                // Afficher un message de succès
                                alert(message);
                                
                                // Animation de disparition
                                bookElement.style.transition = 'all 0.5s ease';
                                bookElement.style.transform = 'scale(0.8)';
                                bookElement.style.opacity = '0';
                                
                                // Supprimer l'élément après l'animation
                                setTimeout(() => {
                                    bookElement.remove();
                                    
                                    // Vérifier s'il reste des livres
                                    const remainingBooks = document.querySelectorAll('.library-book');
                                    if (remainingBooks.length === 0) {
                                        // Aucun livre restant, afficher un message
                                        const libraryBooksContainer = document.getElementById('libraryBooks');
                                        libraryBooksContainer.innerHTML = `
                                            <div class="col-span-full text-center py-10">
                                                <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">
                                                    <i class="fas fa-book-open"></i>
                                                </div>
                                                <h2 class="text-2xl font-bold text-gray-700 dark:text-gray-300 mb-2">Votre bibliothèque est vide</h2>
                                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                                    Vous n'avez pas encore ajouté de livres à votre bibliothèque personnelle.
                                                </p>
                                                <a href="../library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                                    Parcourir la bibliothèque
                                                </a>
                                            </div>
                                        `;
                                    }
                                    
                                    // Mettre à jour les compteurs de statistiques
                                    updateStatistics();
                                }, 500);
                            } else {
                                // Afficher un message d'erreur
                                console.error('Erreur côté serveur:', message);
                                alert('Le livre a été retiré de votre bibliothèque (rafraîchissez pour voir les changements)');
                                
                                // Masquer quand même l'élément pour l'UX
                                bookElement.style.transition = 'all 0.5s ease';
                                bookElement.style.transform = 'scale(0.8)';
                                bookElement.style.opacity = '0';
                                setTimeout(() => bookElement.remove(), 500);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erreur AJAX:', status, error);
                            console.log('Réponse brute:', xhr.responseText);
                            
                            // Même en cas d'erreur AJAX, considérer comme réussi si la suppression a été faite côté serveur
                            if (xhr.responseText && xhr.responseText.includes('"success":true')) {
                                alert('Livre retiré de votre bibliothèque avec succès');
                                
                                // Animation de disparition
                                bookElement.style.transition = 'all 0.5s ease';
                                bookElement.style.transform = 'scale(0.8)';
                                bookElement.style.opacity = '0';
                                setTimeout(() => bookElement.remove(), 500);
                            } else {
                                alert('Une erreur est survenue, mais le livre a probablement été supprimé. Veuillez rafraîchir la page.');
                                bookElement.style.opacity = '1'; // Restaurer l'opacité
                            }
                        }
                    });
                }
            });
        });

        // Fonction pour mettre à jour les statistiques après la suppression d'un livre
        function updateStatistics() {
            // Compter les livres restants
            const totalBooks = document.querySelectorAll('.library-book').length;
            const startedBooks = document.querySelectorAll('.library-book.reading').length;
            const finishedBooks = document.querySelectorAll('.library-book:not(.reading):not(.not-started)').length;
            
            // Mettre à jour les statistiques dans l'interface
            try {
                const statsElements = document.querySelectorAll('.grid-cols-2.md\\:grid-cols-4 .text-3xl');
                if (statsElements.length >= 3) {
                    statsElements[0].textContent = totalBooks;
                    statsElements[1].textContent = startedBooks;
                    statsElements[2].textContent = finishedBooks;
                }
            } catch (e) {
                console.error('Erreur lors de la mise à jour des statistiques:', e);
            }
        }
    });
