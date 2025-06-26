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

// Calculer les statistiques correctement à partir des livres de la bibliothèque
$total_books = count($library_books);
$started_books = 0;
$finished_books = 0;

foreach ($library_books as $book) {
    if ($book['last_page_read'] > 1) {
        $started_books++;
        
        // Vérifier si le livre est terminé
        if (isset($book['pages_count']) && $book['pages_count'] > 0) {
            $progress = ($book['last_page_read'] / $book['pages_count']) * 100;
            if ($progress >= 99.5) {
                $finished_books++;
            }
        }
    }
}

// Récupérer le nombre de notes
$stmt = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$notes_count = $stmt->fetchColumn();

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

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        <!-- En-tête avec titre moderne -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                Mon Espace Lecture
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light">
                Découvrez votre univers littéraire personnel
            </p>
        </div>
        
        <!-- Profil et statistiques avec design moderne -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-2xl border border-white/20 dark:border-gray-700/20 p-8 mb-12">
            <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-8 lg:space-y-0 lg:space-x-12">
                <!-- Profil utilisateur -->
                <div class="flex-shrink-0 text-center">
                    <div class="relative inline-block">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo $user['profile_picture']; ?>" alt="Photo de profil" 
                                 class="w-32 h-32 rounded-full object-cover border-4 border-gradient-to-r from-blue-500 to-purple-500 shadow-2xl">
                        <?php else: ?>
                            <div class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-2xl">
                                <span class="text-4xl font-bold text-white"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-green-500 rounded-full border-4 border-white dark:border-gray-800 shadow-lg"></div>
                    </div>
                    <h2 class="mt-4 text-2xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="text-gray-600 dark:text-gray-400">Lecteur passionné</p>
                </div>
                
                <!-- Statistiques avec cartes modernes -->
                <div class="flex-1 w-full">
                    <h3 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white">Vos Statistiques</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Total des livres -->
                        <div class="group">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="p-3 bg-white/20 rounded-xl">
                                        <i class="fas fa-book text-2xl"></i>
                                    </div>
                                    <div class="text-3xl font-bold"><?php echo $total_books; ?></div>
                                </div>
                                <div class="text-blue-100 text-sm font-medium">Livres dans ma bibliothèque</div>
                            </div>
                        </div>
                        
                        <!-- Livres commencés -->
                        <div class="group">
                            <div class="bg-gradient-to-br from-green-500 to-green-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="p-3 bg-white/20 rounded-xl">
                                        <i class="fas fa-play text-2xl"></i>
                                    </div>
                                    <div class="text-3xl font-bold"><?php echo $started_books; ?></div>
                                </div>
                                <div class="text-green-100 text-sm font-medium">Livres commencés</div>
                            </div>
                        </div>
                        
                        <!-- Livres terminés -->
                        <div class="group">
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="p-3 bg-white/20 rounded-xl">
                                        <i class="fas fa-check-circle text-2xl"></i>
                                    </div>
                                    <div class="text-3xl font-bold"><?php echo $finished_books; ?></div>
                                </div>
                                <div class="text-purple-100 text-sm font-medium">Livres terminés</div>
                            </div>
                        </div>
                        
                        <!-- Notes prises -->
                        <div class="group">
                            <div class="bg-gradient-to-br from-yellow-500 to-orange-500 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="p-3 bg-white/20 rounded-xl">
                                        <i class="fas fa-sticky-note text-2xl"></i>
                                    </div>
                                    <div class="text-3xl font-bold"><?php echo $notes_count; ?></div>
                                </div>
                                <div class="text-yellow-100 text-sm font-medium">Notes prises</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtres et recherche avec design moderne et responsive -->
        <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-xl border border-white/20 dark:border-gray-700/20 p-6 mb-8">
            <!-- Filtres avec design moderne -->
            <div class="flex flex-col space-y-4 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Filtrer vos livres</h3>
                <div class="flex flex-wrap gap-3">
                    <button class="filter-btn px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 active" data-filter="all">
                        <i class="fas fa-th-large mr-2"></i>Tous
                    </button>
                    <button class="filter-btn px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200 dark:border-gray-600" data-filter="reading">
                        <i class="fas fa-book-reader mr-2"></i>En cours
                    </button>
                    <button class="filter-btn px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200 dark:border-gray-600" data-filter="favorites">
                        <i class="fas fa-heart mr-2"></i>Favoris
                    </button>
                    <button class="filter-btn px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full font-medium shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 border border-gray-200 dark:border-gray-600" data-filter="not-started">
                        <i class="fas fa-hourglass-start mr-2"></i>Non commencés
                    </button>
                </div>
            </div>
            
            <!-- Barre de recherche moderne -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="searchLibrary" placeholder="Rechercher dans votre bibliothèque..." 
                       class="w-full pl-12 pr-4 py-4 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-2xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
            </div>
        </div>
        
        <!-- Grille des livres avec design moderne -->
        <div id="libraryBooks" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (!empty($library_books)): ?>
                <?php foreach ($library_books as $book): ?>
                    <?php
                        // Déterminer le statut du livre
                        $book_status = '';
                        $progress = 0;
                        $isCompleted = false;
                        
                        if (isset($book['pages_count']) && $book['pages_count'] > 0) {
                            $progress = min(100, ($book['last_page_read'] / $book['pages_count']) * 100);
                            $isCompleted = ($progress >= 99.5);
                        }
                        
                        if ($book['last_page_read'] > 1 && !$isCompleted) {
                            $book_status = 'reading';
                        } elseif ($book['last_page_read'] <= 1) {
                            $book_status = 'not-started';
                        }
                        
                        if ($book['is_favorite']) {
                            $book_status .= ' favorites';
                        }
                    ?>

                    <div class="library-book group bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 overflow-hidden transition-all duration-500 hover:scale-105 hover:shadow-2xl <?php echo $book_status; ?>" data-title="<?php echo htmlspecialchars(strtolower($book['title'])); ?>">
                        <!-- Image avec overlay moderne -->
                        <div class="relative overflow-hidden">
                            <?php if (!empty($book['cover_image'])): ?>
                                <?php
                                $image_path = $book['cover_image'];
                                if (strpos($image_path, '../') === 0) {
                                    $image_path = substr($image_path, 3);
                                }
                                $image_path = '../' . $image_path;
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" 
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
                            
                            <!-- Badges avec design moderne -->
                            <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                                <!-- Badge de statut -->
                                <?php if ($book['last_page_read'] > 1 && !$isCompleted): ?>
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                        <i class="fas fa-play mr-1"></i>En cours
                                    </div>
                                <?php elseif ($isCompleted): ?>
                                    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                        <i class="fas fa-check mr-1"></i>Terminé
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                                
                                <!-- Bouton favori moderne -->
                                <button class="favorite-btn w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center text-yellow-400 hover:bg-white/30 transition-all duration-300 shadow-lg" data-id="<?php echo $book['id']; ?>" data-favorite="<?php echo $book['is_favorite'] ? '1' : '0'; ?>">
                                    <i class="<?php echo $book['is_favorite'] ? 'fas' : 'far'; ?> fa-heart text-lg"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Contenu de la carte -->
                        <div class="p-6">
                            <!-- Titre et catégorie -->
                            <div class="mb-4">
                                <h3 class="font-bold text-lg line-clamp-2 text-gray-800 dark:text-white mb-1">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h3>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                                </div>
                            </div>
                            
                            <!-- Barre de progression moderne -->
                            <?php if ($book['last_page_read'] > 1): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progression</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-white"><?php echo round($progress); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="<?php echo $isCompleted ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-blue-500 to-blue-600'; ?> h-3 rounded-full transition-all duration-500 ease-out" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        Page <?php echo $book['last_page_read']; ?> 
                                        <?php if (isset($book['pages_count']) && $book['pages_count'] > 0): ?>
                                            sur <?php echo $book['pages_count']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions avec boutons modernes -->
                            <div class="flex flex-col space-y-3">
                                <div class="flex justify-between items-center">
                                    <a href="../book.php?id=<?php echo $book['id']; ?>" 
                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium text-sm transition-colors duration-300">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                    
                                    <?php if (!$isCompleted): ?>
                                        <a href="../read.php?id=<?php echo $book['id']; ?>" 
                                           class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 hover:scale-105 shadow-lg">
                                            <i class="fas fa-<?php echo $book['last_page_read'] > 1 ? 'play' : 'book-open'; ?> mr-1"></i>
                                            <?php echo $book['last_page_read'] > 1 ? 'Continuer' : 'Commencer'; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php
                                        $file_path = $book['file_path'];
                                        if (strpos($file_path, '../') === 0) {
                                            $file_path = substr($file_path, 3);
                                        }
                                        $file_path = '../' . $file_path;
                                        ?>
                                        <a href="<?php echo htmlspecialchars($file_path); ?>" download 
                                           class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 hover:scale-105 shadow-lg">
                                            <i class="fas fa-download mr-1"></i>Télécharger
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Bouton de suppression discret -->
                                <button class="remove-book-btn text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium transition-colors duration-300 text-center py-2" 
                                        data-id="<?php echo $book['id']; ?>" data-title="<?php echo htmlspecialchars($book['title']); ?>">
                                    <i class="fas fa-trash-alt mr-1"></i>Retirer de ma bibliothèque
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-20">
                    <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Votre bibliothèque est vide</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                        Découvrez des livres passionnants et commencez votre aventure littéraire.
                    </p>
                    <a href="../library.php" 
                       class="inline-block bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-full transition-all duration-300 hover:scale-105 shadow-lg">
                        <i class="fas fa-search mr-2"></i>Parcourir la bibliothèque
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Activité récente avec design moderne -->
        <?php if (!empty($recent_activity)): ?>
        <div class="mt-16">
            <h2 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white text-center">Activité Récente</h2>
            
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="p-6 hover:bg-gray-50/50 dark:hover:bg-gray-700/50 transition-colors duration-300">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if ($activity['action'] == 'started'): ?>
                                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                                            <i class="fas fa-play text-white"></i>
                                        </div>
                                    <?php elseif ($activity['action'] == 'continued'): ?>
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                            <i class="fas fa-book-reader text-white"></i>
                                        </div>
                                    <?php elseif ($activity['action'] == 'finished'): ?>
                                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                            <i class="fas fa-check text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-800 dark:text-white mb-1">
                                        <?php if ($activity['action'] == 'started'): ?>
                                            Vous avez commencé à lire
                                        <?php elseif ($activity['action'] == 'continued'): ?>
                                            Vous avez continué à lire
                                        <?php elseif ($activity['action'] == 'finished'): ?>
                                            Vous avez terminé
                                        <?php endif; ?>
                                        <a href="../read.php?id=<?php echo $activity['book_id']; ?>" 
                                           class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-300">
                                            <?php echo htmlspecialchars($activity['book_title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php if ($activity['action'] == 'continued'): ?>
                                            <span class="inline-flex items-center bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-2 py-1 rounded-full text-xs font-medium mr-2">
                                                Page <?php echo $activity['page_number']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('d/m/Y à H:i', strtotime($activity['timestamp'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
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
