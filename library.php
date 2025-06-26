<?php
// library.php - Page de la bibliothèque principale MODERNE
require_once "config/database.php";

// Désactiver temporairement le mode strict pour GROUP BY
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Initialisation des variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Construction de la requête SQL de base avec statistiques
$sql = "SELECT b.*, c.name as category_name,
               COALESCE(AVG(comm.rating), 0) as avg_rating,
               COUNT(comm.id) as total_reviews,
               COALESCE(b.views_count, 0) as views_count
        FROM books b 
        LEFT JOIN book_categories bc ON b.id = bc.book_id 
        LEFT JOIN categories c ON bc.category_id = c.id
        LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1";

// Conditions de recherche et filtrage
$params = [];
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE :search OR b.description LIKE :search OR b.author LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($category_id > 0) {
    $where_conditions[] = "bc.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Groupement et tri
$sql .= " GROUP BY b.id";

switch ($sort) {
    case 'title_asc':
        $sql .= " ORDER BY b.title ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY b.title DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    case 'popular':
        $sql .= " ORDER BY views_count DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC, total_reviews DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

// Exécution de la requête
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les catégories pour le filtre
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques globales
$stmt = $conn->prepare("SELECT 
    COUNT(DISTINCT b.id) as total_books,
    COUNT(DISTINCT c.id) as total_categories,
    COALESCE(SUM(b.views_count), 0) as total_views,
    COUNT(comm.id) as total_comments
    FROM books b 
    LEFT JOIN book_categories bc ON b.id = bc.book_id
    LEFT JOIN categories c ON bc.category_id = c.id
    LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1");
$stmt->execute();
$global_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Bibliothèque";
include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        <!-- En-tête moderne avec gradient -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                Bibliothèque Numérique
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Découvrez notre collection de livres spirituels et ressources pour votre croissance personnelle
            </p>
        </div>

        <!-- Statistiques globales avec design moderne -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-2xl border border-white/20 dark:border-gray-700/20 p-8 mb-12">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total des livres -->
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="flex items-center justify-center mb-3">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <i class="fas fa-book text-2xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?php echo number_format($global_stats['total_books']); ?></div>
                        <div class="text-blue-100 text-sm font-medium">Livres disponibles</div>
                    </div>
                </div>

                <!-- Catégories -->
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-green-500 to-green-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="flex items-center justify-center mb-3">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <i class="fas fa-tags text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?php echo number_format($global_stats['total_categories']); ?></div>
                        <div class="text-green-100 text-sm font-medium">Catégories</div>
                    </div>
                </div>

                <!-- Lectures totales -->
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="flex items-center justify-center mb-3">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <i class="fas fa-eye text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?php echo number_format($global_stats['total_views']); ?></div>
                        <div class="text-purple-100 text-sm font-medium">Lectures totales</div>
                    </div>
                </div>

                <!-- Commentaires -->
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-yellow-500 to-orange-500 p-6 rounded-2xl text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <div class="flex items-center justify-center mb-3">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <i class="fas fa-comments text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold mb-1"><?php echo number_format($global_stats['total_comments']); ?></div>
                        <div class="text-yellow-100 text-sm font-medium">Avis de lecteurs</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Barre de recherche et filtres avec design moderne -->
        <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-xl border border-white/20 dark:border-gray-700/20 p-6 mb-8">
            <form action="library.php" method="GET" class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    <i class="fas fa-search mr-2 text-blue-600 dark:text-blue-400"></i>
                    Rechercher et filtrer
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Recherche -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-search mr-1 text-blue-600 dark:text-blue-400"></i> Rechercher
                        </label>
                        <div class="relative">
                            <input type="text" id="search" name="search" 
                                   class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Titre, auteur ou description...">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Catégorie -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-tag mr-1 text-green-600 dark:text-green-400"></i> Catégorie
                        </label>
                        <select id="category" name="category" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="0">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Tri -->
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <i class="fas fa-sort mr-1 text-purple-600 dark:text-purple-400"></i> Trier par
                        </label>
                        <select id="sort" name="sort" 
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 text-gray-900 dark:text-white">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                            <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                            <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Plus populaires</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Mieux notés</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg">
                        <i class="fas fa-search mr-2"></i> Rechercher
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Résultats de la recherche -->
        <div class="mb-8">
            <?php if (!empty($search) || $category_id > 0): ?>
                <div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/20 p-6 mb-8">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">
                            <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-500"></i>
                            <?php echo count($books); ?> résultat<?php echo count($books) > 1 ? 's' : ''; ?> trouvé<?php echo count($books) > 1 ? 's' : ''; ?>
                            <?php if (!empty($search)): ?>
                                pour "<span class="text-blue-600 dark:text-blue-400 font-semibold"><?php echo htmlspecialchars($search); ?></span>"
                            <?php endif; ?>
                            <?php if ($category_id > 0): 
                                $category_name = '';
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category_id) {
                                        $category_name = $cat['name'];
                                        break;
                                    }
                                }
                            ?>
                                dans la catégorie "<span class="text-green-600 dark:text-green-400 font-semibold"><?php echo htmlspecialchars($category_name); ?></span>"
                            <?php endif; ?>
                        </h2>
                        
                        <a href="library.php" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 mt-2 sm:mt-0 inline-flex items-center font-medium transition-all duration-300 hover:scale-105">
                            <i class="fas fa-times-circle mr-2"></i> Effacer les filtres
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($books)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                    <?php foreach ($books as $index => $book): ?>
                        <div class="group bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 overflow-hidden transition-all duration-500 hover:scale-105 hover:shadow-2xl">
                            <a href="book.php?id=<?php echo $book['id']; ?>" class="block h-full">
                                <!-- Image avec overlay moderne -->
                                <div class="relative overflow-hidden">
                                    <?php if (!empty($book['cover_image'])): ?>
                                        <?php
                                        $image_path = $book['cover_image'];
                                        if (strpos($image_path, '../') === 0) {
                                            $image_path = substr($image_path, 3);
                                        }
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
                                    
                                    <!-- Badges -->
                                    <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                                        <!-- Badge catégorie -->
                                        <div class="bg-gradient-to-r from-blue-500/90 to-blue-600/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm">
                                            <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                                        </div>
                                        
                                        <!-- Badge note si disponible -->
                                        <?php if ($book['avg_rating'] > 0): ?>
                                            <div class="bg-gradient-to-r from-yellow-500/90 to-orange-500/90 text-white text-xs font-bold px-3 py-2 rounded-full shadow-lg backdrop-blur-sm flex items-center">
                                                <i class="fas fa-star mr-1"></i>
                                                <?php echo round($book['avg_rating'], 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Contenu de la carte -->
                                <div class="p-6">
                                    <h3 class="font-bold text-lg line-clamp-2 text-gray-800 dark:text-white mb-2">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                    </h3>
                                    
                                    <?php if (!empty($book['author'])): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-3">
                                            par <span class="italic font-medium"><?php echo htmlspecialchars($book['author']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Statistiques -->
                                    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-eye mr-1"></i>
                                            <?php echo number_format($book['views_count']); ?> vues
                                        </div>
                                        <?php if ($book['total_reviews'] > 0): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-comments mr-1"></i>
                                                <?php echo $book['total_reviews']; ?> avis
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Note avec étoiles -->
                                    <?php if ($book['avg_rating'] > 0): ?>
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="flex items-center text-yellow-400">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $book['avg_rating']): ?>
                                                        <i class="fas fa-star text-sm"></i>
                                                    <?php elseif ($i - 0.5 <= $book['avg_rating']): ?>
                                                        <i class="fas fa-star-half-alt text-sm"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-sm"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                <?php echo round($book['avg_rating'], 1); ?>/5
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Bouton d'action -->
                                    <div class="flex justify-center">
                                        <span class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium px-6 py-2 rounded-full transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                                            <i class="fas fa-book-open mr-2"></i>
                                            Découvrir
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- État vide moderne -->
                <div class="text-center py-20">
                    <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-3xl shadow-xl border border-white/20 dark:border-gray-700/20 p-12">
                        <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Aucun livre trouvé</h2>
                        <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            <?php if (!empty($search) || $category_id > 0): ?>
                                Essayez de modifier vos critères de recherche pour découvrir plus de livres.
                            <?php else: ?>
                                Aucun livre n'est disponible dans la bibliothèque pour le moment.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || $category_id > 0): ?>
                            <a href="library.php" 
                               class="inline-block bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-full transition-all duration-300 hover:scale-105 shadow-lg">
                                <i class="fas fa-undo-alt mr-2"></i>Afficher tous les livres
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section d'appel à l'action pour les utilisateurs connectés -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="bg-gradient-to-r from-blue-500/10 to-purple-500/10 backdrop-blur-lg rounded-3xl border border-blue-200/20 dark:border-blue-800/20 p-8 mt-16">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-bookmark mr-2 text-blue-500"></i>
                        Créez votre bibliothèque personnelle
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-2xl mx-auto">
                        Ajoutez vos livres favoris à votre bibliothèque personnelle pour les retrouver facilement et suivre votre progression de lecture.
                    </p>
                    <div class="flex justify-center">
                        <a href="user/my-library.php" 
                           class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-full transition-all duration-300 hover:scale-105 shadow-lg inline-flex items-center">
                            <i class="fas fa-user-plus mr-2"></i>Ma Bibliothèque
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-soumission du formulaire lors des changements
    const selects = document.querySelectorAll('select[name="category"], select[name="sort"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    // Animation des cartes au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observer toutes les cartes
    document.querySelectorAll('.group').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });

    // Effet de recherche en temps réel (optionnel)
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Auto-submit après 500ms d'inactivité (optionnel)
            // this.closest('form').submit();
        }, 500);
    });
});


document.addEventListener('DOMContentLoaded', function() {

    
    const html = document.documentElement;
      
    // Votre code existant pour les selects (gardez-le)
    const selects = document.querySelectorAll('select[name="category"], select[name="sort"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    // Votre code existant pour les animations au scroll (gardez-le)
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observer toutes les cartes
    document.querySelectorAll('.group').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });

    // Effet de recherche en temps réel (optionnel)
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Auto-submit après 500ms d'inactivité (optionnel)
                // this.closest('form').submit();
            }, 500);
        });
    }
});


</script>

<?php include "includes/footer.php"; ?>