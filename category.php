<?php
// category.php - Affichage des livres par catégorie MODERNE
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

// Désactiver temporairement le mode strict pour GROUP BY
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Vérifier si l'ID de la catégorie est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: library.php");
    exit();
}

$category_id = intval($_GET['id']);

// Récupérer les informations sur la catégorie
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = :category_id");
$stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    // Si la catégorie n'existe pas, rediriger vers la bibliothèque
    header("Location: library.php");
    exit();
}

$category = $stmt->fetch(PDO::FETCH_ASSOC);

// Paramètres de tri et pagination
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Nombre de livres par page
$offset = ($page - 1) * $per_page;

// Construction de la requête pour récupérer les livres de cette catégorie
$sql = "SELECT b.*, c.name as category_name,
               COALESCE(AVG(comm.rating), 0) as avg_rating,
               COUNT(comm.id) as total_reviews,
               COALESCE(b.views_count, 0) as views_count
        FROM books b 
        JOIN book_categories bc ON b.id = bc.book_id 
        LEFT JOIN categories c ON bc.category_id = c.id 
        LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
        WHERE bc.category_id = :category_id
        GROUP BY b.id";

// Tri
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

// Ajout de la pagination
$sql .= " LIMIT :offset, :per_page";

// Exécution de la requête avec pagination
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total de livres pour la pagination
$stmt = $conn->prepare("SELECT COUNT(DISTINCT b.id) as total 
                        FROM books b 
                        JOIN book_categories bc ON b.id = bc.book_id 
                        WHERE bc.category_id = :category_id");
$stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
$stmt->execute();
$total_books = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_books / $per_page);

// Récupérer les sous-catégories (si la catégorie a des enfants)
$stmt = $conn->prepare("SELECT * FROM categories WHERE parent_id = :category_id ORDER BY name");
$stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
$stmt->execute();
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la catégorie parente (si applicable)
$parent_category = null;
if ($category['parent_id']) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = :parent_id");
    $stmt->bindParam(':parent_id', $category['parent_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $parent_category = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$page_title = $category['name'];
include "includes/header.php";
?>

<style>
/* Styles modernes pour la page de catégorie */
.hero-category {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.hero-category::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
    background-size: cover;
}

.dark .hero-category {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark .glass-card {
    background: rgba(31, 41, 55, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e5e7eb;
}

.book-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(0);
}

.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
}

.dark .book-card:hover {
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
}

.breadcrumb-modern {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 12px 24px;
    display: inline-flex;
    align-items: center;
    margin-bottom: 2rem;
}

.dark .breadcrumb-modern {
    background: rgba(31, 41, 55, 0.3);
}

.subcategory-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dark .subcategory-card {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

.subcategory-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #3b82f6, transparent);
    transition: left 0.5s ease;
}

.subcategory-card:hover::before {
    left: 100%;
}

.pagination-modern {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 3rem;
}

.pagination-modern a,
.pagination-modern span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-modern .active {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.sort-dropdown {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.dark .sort-dropdown {
    background: rgba(31, 41, 55, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e5e7eb;
}

.stats-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.dark .stats-badge {
    background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
}

.slide-up {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-up.visible {
    opacity: 1;
    transform: translateY(0);
}
</style>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <!-- Hero Section -->
    <section class="hero-category py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <!-- Breadcrumb moderne -->
            <nav class="breadcrumb-modern text-white/90 mb-8">
                <a href="index.php" class="hover:text-white transition-colors">
                    <i class="fas fa-home mr-2"></i>Accueil
                </a>
                <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                <a href="library.php" class="hover:text-white transition-colors">Bibliothèque</a>
                <?php if ($parent_category): ?>
                    <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                    <a href="category.php?id=<?php echo $parent_category['id']; ?>" class="hover:text-white transition-colors">
                        <?php echo htmlspecialchars($parent_category['name']); ?>
                    </a>
                <?php endif; ?>
                <i class="fas fa-chevron-right mx-3 text-white/60"></i>
                <span class="text-white font-medium"><?php echo htmlspecialchars($category['name']); ?></span>
            </nav>

            <div class="text-center text-white">
                <h1 class="text-4xl lg:text-6xl font-bold mb-6">
                    <?php echo htmlspecialchars($category['name']); ?>
                </h1>
                <?php if (!empty($category['description'])): ?>
                    <p class="text-xl lg:text-2xl mb-8 opacity-90 max-w-3xl mx-auto leading-relaxed">
                        <?php echo htmlspecialchars($category['description']); ?>
                    </p>
                <?php endif; ?>
                <div class="stats-badge inline-block">
                    <?php echo $total_books; ?> livre<?php echo $total_books > 1 ? 's' : ''; ?> disponible<?php echo $total_books > 1 ? 's' : ''; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-12">
        <!-- Sous-catégories -->
        <?php if (!empty($subcategories)): ?>
            <div class="mb-16 slide-up">
                <h2 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white text-center">
                    <i class="fas fa-sitemap mr-3 text-blue-600 dark:text-blue-400"></i>
                    Sous-catégories
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($subcategories as $index => $subcat): ?>
                        <a href="category.php?id=<?php echo $subcat['id']; ?>" 
                           class="subcategory-card rounded-2xl p-6 text-center hover:scale-105 transition-all duration-300 slide-up" 
                           style="transition-delay: <?php echo $index * 0.1; ?>s">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4 text-white">
                                <i class="fas fa-folder text-xl"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white">
                                <?php echo htmlspecialchars($subcat['name']); ?>
                            </h3>
                            <?php if (!empty($subcat['description'])): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    <?php echo htmlspecialchars($subcat['description']); ?>
                                </p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Options de tri -->
        <div class="glass-card rounded-2xl p-6 mb-8 flex flex-col lg:flex-row justify-between items-center gap-4 slide-up">
            <div class="flex items-center gap-4">
                <div class="text-gray-600 dark:text-gray-300 font-medium">
                    <i class="fas fa-books mr-2 text-blue-600 dark:text-blue-400"></i>
                    Affichage de <?php echo count($books); ?> sur <?php echo $total_books; ?> livre<?php echo $total_books > 1 ? 's' : ''; ?>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <label class="text-gray-600 dark:text-gray-300 font-medium flex items-center">
                    <i class="fas fa-sort mr-2 text-purple-600 dark:text-purple-400"></i>
                    Trier par:
                </label>
                <select id="sort-select" class="sort-dropdown focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                    <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                    <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Popularité</option>
                    <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Mieux notés</option>
                </select>
            </div>
        </div>
        
        <!-- Liste des livres -->
        <?php if (!empty($books)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8" id="booksGrid">
                <?php foreach ($books as $index => $book): ?>
                    <div class="glass-card rounded-3xl overflow-hidden book-card slide-up" style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <a href="book.php?id=<?php echo $book['id']; ?>" class="block h-full">
                            <div class="relative overflow-hidden">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <?php
                                    // Corriger le chemin de l'image
                                    $image_path = $book['cover_image'];
                                    if (strpos($image_path, '../') === 0) {
                                        $image_path = substr($image_path, 3);
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         class="w-full h-72 object-cover transition-transform duration-500 hover:scale-110"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-full h-72 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center" style="display:none;">
                                        <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-72 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                                        <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Overlay avec gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 hover:opacity-100 transition-opacity duration-300">
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
                            </div>
                            
                            <div class="p-6">
                                <h3 class="text-xl font-bold mb-3 text-gray-800 dark:text-white line-clamp-2">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h3>
                                
                                <?php if (!empty($book['author'])): ?>
                                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                                        par <span class="font-medium"><?php echo htmlspecialchars($book['author']); ?></span>
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
                                <div class="flex items-center justify-between">
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">Découvrir</span>
                                    <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 transform transition-transform group-hover:translate-x-2"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination moderne -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-modern">
                    <?php if ($page > 1): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                           class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                           class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- État vide moderne -->
            <div class="text-center py-20">
                <div class="glass-card rounded-3xl p-12 max-w-md mx-auto">
                    <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-700 dark:text-gray-300 mb-4">Aucun livre trouvé</h2>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                        Il n'y a pas encore de livres disponibles dans cette catégorie.
                    </p>
                    <a href="library.php" class="inline-block bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 hover:scale-105 shadow-lg">
                        <i class="fas fa-arrow-left mr-2"></i>Retour à la bibliothèque
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target); // Ne pas re-animer
            }
        });
    }, observerOptions);

    // Observer tous les éléments avec animation
    document.querySelectorAll('.slide-up').forEach(element => {
        observer.observe(element);
    });

    // Gestion du tri
    const sortSelect = document.getElementById('sort-select');
    sortSelect.addEventListener('change', function() {
        // Animation de transition
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.style.transform = 'scale(1)';
            window.location.href = 'category.php?id=<?php echo $category_id; ?>&sort=' + this.value;
        }, 150);
    });

    // Animation des cartes au chargement
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach(element => {
            element.classList.add('visible');
        });
    }, 100);
});
</script>

<?php include "includes/footer.php"; ?>