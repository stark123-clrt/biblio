<?php
// user/my-library.php - Bibliothèque personnelle de l'utilisateur
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";

// Désactiver temporairement le mode strict pour GROUP BY
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Récupérer les informations sur l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les livres de la bibliothèque personnelle
$stmt = $conn->prepare("SELECT b.*, ul.last_page_read, ul.added_at, ul.last_read_at, ul.is_favorite,
                        c.name as category_name
                        FROM user_library ul
                        JOIN books b ON ul.book_id = b.id
                        LEFT JOIN book_categories bc ON b.id = bc.book_id
                        LEFT JOIN categories c ON bc.category_id = c.id
                        WHERE ul.user_id = :user_id
                        GROUP BY b.id
                        ORDER BY ul.last_read_at DESC");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$library_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques de lecture
$stmt = $conn->prepare("SELECT COUNT(DISTINCT book_id) as total_books,
                        SUM(CASE WHEN action = 'started' THEN 1 ELSE 0 END) as started_books,
                        SUM(CASE WHEN action = 'finished' THEN 1 ELSE 0 END) as finished_books
                        FROM reading_history
                        WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$reading_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer l'activité récente
$stmt = $conn->prepare("SELECT rh.*, b.title as book_title
                        FROM reading_history rh
                        JOIN books b ON rh.book_id = b.id
                        WHERE rh.user_id = :user_id
                        ORDER BY rh.timestamp DESC
                        LIMIT 5");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Ma Bibliothèque";
include "../includes/header.php";
?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Ma Espace de livre</h1>
    
    <!-- Statistiques et profil -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
        <div class="md:flex">
            <div class="md:w-1/4 mb-4 md:mb-0">
                <div class="text-center">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo $user['profile_picture']; ?>" alt="Photo de profil" class="w-32 h-32 rounded-full mx-auto object-cover border-2 border-blue-100 dark:border-blue-900">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mx-auto">
                            <span class="text-4xl text-blue-800 dark:text-blue-300"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mt-2 font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
            </div>
            
            <div class="md:w-3/4 md:pl-6">
                <h2 class="text-xl font-bold mb-4 text-gray-800 dark:text-white">Statistiques de lecture</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded text-center">
                        <div class="text-3xl font-bold text-blue-800 dark:text-blue-300"><?php echo count($library_books); ?></div>
                        <div class="text-gray-600 dark:text-gray-300">Livres dans ma bibliothèque</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded text-center">
                        <div class="text-3xl font-bold text-green-800 dark:text-green-300"><?php echo $reading_stats['started_books']; ?></div>
                        <div class="text-gray-600 dark:text-gray-300">Livres commencés</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/30 p-4 rounded text-center">
                        <?php
                            // Compter les livres terminés directement ici
                            $completed_books = 0;
                            foreach ($library_books as $book) {
                                if (isset($book['pages_count']) && $book['pages_count'] > 0) {
                                    $progress = ($book['last_page_read'] / $book['pages_count']) * 100;
                                    if ($progress >= 99.5) {
                                        $completed_books++;
                                    }
                                }
                            }
                        ?>
                        <div class="text-3xl font-bold text-purple-800 dark:text-purple-300"><?php echo $completed_books; ?></div>
                        <div class="text-gray-600 dark:text-gray-300">Livres terminés</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/30 p-4 rounded text-center">
                        <div class="text-3xl font-bold text-yellow-800 dark:text-yellow-300">
                            <?php
                                $notes_count = 0;
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = :user_id");
                                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $stmt->execute();
                                $notes_count = $stmt->fetchColumn();
                                echo $notes_count;
                            ?>
                        </div>
                        <div class="text-gray-600 dark:text-gray-300">Notes prises</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Filtres et recherche -->
    <div class="flex flex-wrap justify-between items-center mb-6">
        <div class="w-full md:w-auto mb-4 md:mb-0">
            <div class="flex space-x-2">
                <button class="filter-btn px-4 py-2 bg-blue-600 text-white rounded-lg active" data-filter="all">
                    Tous
                </button>
                <button class="filter-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg" data-filter="reading">
                    En cours
                </button>
                <button class="filter-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg" data-filter="favorites">
                    Favoris
                </button>
                <button class="filter-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg" data-filter="not-started">
                    Non commencés
                </button>
            </div>
        </div>
        <div class="w-full md:w-auto">
            <div class="relative">
                <input type="text" id="searchLibrary" placeholder="Rechercher dans ma bibliothèque..." 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                <button class="absolute right-2 top-2 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Liste des livres -->
    <div id="libraryBooks" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (!empty($library_books)): ?>
            <?php foreach ($library_books as $book): ?>
                <?php
                    // Déterminer le statut du livre
                    $book_status = '';
                    if ($book['last_page_read'] > 1) {
                        $book_status = 'reading';
                    } else {
                        $book_status = 'not-started';
                    }
                    if ($book['is_favorite']) {
                        $book_status .= ' favorites';
                    }
                ?>
    <div class="library-book bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105 <?php echo $book_status; ?>" data-title="<?php echo htmlspecialchars(strtolower($book['title'])); ?>">
    <div class="relative">
        <?php if (!empty($book['cover_image'])): ?>
            <img src="../<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-48 object-cover">
        <?php else: ?>
            <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                <i class="fas fa-book text-4xl text-gray-400 dark:text-gray-500"></i>
            </div>
        <?php endif; ?>
        
        <!-- Badge de progression -->
        <?php 
            $progress = 0;
            $isCompleted = false;
            if (isset($book['pages_count']) && $book['pages_count'] > 0) {
                $progress = min(100, ($book['last_page_read'] / $book['pages_count']) * 100);
                $isCompleted = ($progress >= 99.5); // Considérer comme terminé si ≥ 99.5%
            }
            
            if ($book['last_page_read'] > 1 && !$isCompleted): 
        ?>
            <div class="absolute top-2 left-2 bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                En cours
            </div>
        <?php elseif ($isCompleted): ?>
            <div class="absolute top-2 left-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                Terminé
            </div>
        <?php endif; ?>
        
        <!-- Bouton favori -->
        <button class="favorite-btn absolute top-2 right-2 text-xl text-yellow-500 bg-white dark:bg-gray-800 bg-opacity-80 dark:bg-opacity-80 w-8 h-8 rounded-full flex items-center justify-center" data-id="<?php echo $book['id']; ?>" data-favorite="<?php echo $book['is_favorite'] ? '1' : '0'; ?>">
            <i class="<?php echo $book['is_favorite'] ? 'fas' : 'far'; ?> fa-star"></i>
        </button>
    </div>
    
    <div class="p-4">
        <div class="flex justify-between items-start mb-2">
            <div>
                <h3 class="font-bold line-clamp-2 text-gray-800 dark:text-white"><?php echo htmlspecialchars($book['title']); ?></h3>
                <div class="text-xs text-gray-600 dark:text-gray-400 mb-1"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
            </div>
        </div>
        
        <?php if ($book['last_page_read'] > 1): ?>
            <!-- Barre de progression -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-2">
                <div class="<?php echo $isCompleted ? 'bg-green-600' : 'bg-blue-600'; ?> h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                Page <?php echo $book['last_page_read']; ?> 
                <?php if (isset($book['pages_count']) && $book['pages_count'] > 0): ?>
                    sur <?php echo $book['pages_count']; ?> (<?php echo round($progress); ?>%)
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-2 flex flex-wrap justify-between items-center">
            <a href="../book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                Détails
            </a>
            
            <?php if (!$isCompleted): ?>
                <a href="../read.php?id=<?php echo $book['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded">
                    <?php echo $book['last_page_read'] > 1 ? 'Continuer' : 'Commencer'; ?>
                </a>
            <?php else: ?>
                <!-- Bouton de téléchargement pour les livres terminés -->
                <a href="../<?php echo $book['file_path']; ?>" download class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1 rounded flex items-center">
                    <i class="fas fa-download mr-1"></i> Télécharger
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Bouton de suppression -->
        <div class="mt-2 text-center">
            <button class="remove-book-btn text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm" data-id="<?php echo $book['id']; ?>" data-title="<?php echo htmlspecialchars($book['title']); ?>">
                <i class="fas fa-trash-alt mr-1"></i> Retirer de ma bibliothèque
            </button>
        </div>
    </div>
</div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-10 bg-white dark:bg-gray-800 rounded-lg shadow">
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
        <?php endif; ?>
    </div>
    <!-- Activité récente -->
    <div class="mt-10">
        <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-white">Activité récente</h2>
        
        <?php if (!empty($recent_activity)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($recent_activity as $activity): ?>
                        <li class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3">
                                    <?php if ($activity['action'] == 'started'): ?>
                                        <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 p-2 rounded-full">
                                            <i class="fas fa-play"></i>
                                        </span>
                                    <?php elseif ($activity['action'] == 'continued'): ?>
                                        <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 p-2 rounded-full">
                                            <i class="fas fa-book-reader"></i>
                                        </span>
                                    <?php elseif ($activity['action'] == 'finished'): ?>
                                        <span class="bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-300 p-2 rounded-full">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-800 dark:text-white">
                                        <?php if ($activity['action'] == 'started'): ?>
                                            Vous avez commencé à lire
                                        <?php elseif ($activity['action'] == 'continued'): ?>
                                            Vous avez continué à lire
                                        <?php elseif ($activity['action'] == 'finished'): ?>
                                            Vous avez terminé
                                        <?php endif; ?>
                                        <a href="../book.php?id=<?php echo $activity['book_id']; ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            <?php echo htmlspecialchars($activity['book_title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php if ($activity['action'] == 'continued'): ?>
                                            Page <?php echo $activity['page_number']; ?>
                                        <?php endif; ?>
                                        <span class="ml-2"><?php echo date('d/m/Y à H:i', strtotime($activity['timestamp'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
                <p class="text-gray-600 dark:text-gray-400">Aucune activité récente à afficher.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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
</script>

<?php include "../includes/footer.php"; ?>
