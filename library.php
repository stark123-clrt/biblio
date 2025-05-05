<?php
// library.php - Page de la bibliothèque principale
require_once "config/database.php";

// Désactiver temporairement le mode strict pour GROUP BY
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Initialisation des variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Construction de la requête SQL de base
$sql = "SELECT b.*, c.name as category_name 
        FROM books b 
        LEFT JOIN book_categories bc ON b.id = bc.book_id 
        LEFT JOIN categories c ON bc.category_id = c.id";

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
        $sql .= " ORDER BY b.views_count DESC";
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

$page_title = "Bibliothèque";
include "includes/header.php";
?>

<style>
/* Styles pour le mode clair/sombre */
:root {
  --primary-blue: #2563eb;
  --primary-blue-dark: #1d4ed8;
  --bg-light: #ffffff;
  --bg-light-alt: #f3f4f6;
  --bg-dark: #111827;
  --bg-dark-alt: #1f2937;
  --card-light: #ffffff;
  --card-dark: #1f2937;
  --card-alt-light: #f9fafb;
  --card-alt-dark: #374151;
  --text-light: #374151;
  --text-dark: #e5e7eb;
  --text-light-secondary: #6b7280;
  --text-dark-secondary: #9ca3af;
  --border-light: #e5e7eb;
  --border-dark: #374151;
  --hover-light: #f3f4f6;
  --hover-dark: #374151;
  --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.18);
  --shadow-hover-light: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-hover-dark: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

/* Transitions globales */
body, .bg-white, .bg-gray-50, .bg-gray-100, input, select, textarea, button, a, .book-card {
  transition: all 0.3s ease;
}

/* Adaptations du mode sombre */
.dark .bg-white {
  background-color: var(--card-dark);
  color: var(--text-dark);
}

.dark .bg-gray-50,
.dark .bg-gray-100 {
  background-color: var(--bg-dark-alt);
  color: var(--text-dark);
}

.dark .bg-gray-200 {
  background-color: var(--card-alt-dark);
}

.dark .text-gray-500,
.dark .text-gray-600,
.dark .text-gray-700 {
  color: var(--text-dark-secondary);
}

.dark .border-gray-300 {
  border-color: var(--border-dark);
}

/* Formulaires */
.dark input,
.dark select,
.dark textarea {
  background-color: var(--bg-dark-alt);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark .focus\:shadow-outline:focus,
.dark .focus\:outline-none:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
  outline: none;
  border-color: #3b82f6;
}

/* Couleurs textes */
.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .hover\:text-blue-800:hover {
  color: #93c5fd;
}

/* Card styles */
.book-card {
  transition: all 0.3s ease;
  transform-origin: center;
}

.book-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: var(--shadow-hover-light);
  z-index: 10;
}

.dark .book-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeInUp {
  animation: fadeInUp 0.5s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.animate-fadeIn {
  animation: fadeIn 0.5s ease-out forwards;
}

/* Animation delay classes */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
.delay-400 { animation-delay: 0.4s; }

/* Boutons */
.btn {
  position: relative;
  overflow: hidden;
}

.btn:after {
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

.btn:focus:not(:active)::after {
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

/* Search box */
.search-box-container {
  position: relative;
}

.search-box-container .search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.search-box-container input {
  padding-left: 35px;
}

.dark .search-box-container .search-icon {
  color: #9ca3af;
}

/* Loader animation */
@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

.loading-shimmer {
  background: linear-gradient(90deg, 
    var(--bg-light-alt) 25%, 
    var(--bg-light) 50%, 
    var(--bg-light-alt) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.dark .loading-shimmer {
  background: linear-gradient(90deg, 
    var(--bg-dark-alt) 25%, 
    var(--card-dark) 50%, 
    var(--bg-dark-alt) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white flex items-center">
        <i class="fas fa-books mr-3 text-blue-600 dark:text-blue-500"></i> Bibliothèque
    </h1>
    
    <!-- Barre de recherche et filtres -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fadeInUp dark:bg-gray-800">
        <form action="library.php" method="GET" class="space-y-4">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 search-box-container">
                    <label for="search" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-search mr-2 text-blue-600 dark:text-blue-500"></i> Rechercher
                    </label>
                    <div class="relative">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="search" name="search" 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                               value="<?php echo htmlspecialchars($search); ?>" placeholder="Titre, auteur ou description...">
                    </div>
                </div>
                
                <div class="w-full md:w-1/4">
                    <label for="category" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-tag mr-2 text-blue-600 dark:text-blue-500"></i> Catégorie
                    </label>
                    <select id="category" name="category" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="w-full md:w-1/4">
                    <label for="sort" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-sort mr-2 text-blue-600 dark:text-blue-500"></i> Trier par
                    </label>
                    <select id="sort" name="sort" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                        <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                        <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Plus populaires</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline btn transition-colors">
                    <i class="fas fa-search mr-2"></i> Rechercher
                </button>
            </div>
        </form>
    </div>
    
    <!-- Résultats de la recherche -->
    <div class="animate-fadeInUp delay-100">
        <?php if (!empty($search) || $category_id > 0): ?>
            <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center bg-white p-4 rounded-lg shadow-sm dark:bg-gray-800">
                <h2 class="text-xl font-bold dark:text-white">
                    <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-500"></i>
                    <?php echo count($books); ?> résultat(s)
                    <?php if (!empty($search)): ?>
                        pour "<span class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($search); ?></span>"
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
                        dans la catégorie "<span class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($category_name); ?></span>"
                    <?php endif; ?>
                </h2>
                
                <a href="library.php" class="text-blue-600 hover:text-blue-800 mt-2 sm:mt-0 inline-flex items-center transition-colors dark:text-blue-400 dark:hover:text-blue-300">
                    <i class="fas fa-times-circle mr-2"></i> Effacer les filtres
                </a>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($books)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($books as $index => $book): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden book-card animate-fadeInUp delay-<?php echo ($index % 4 + 1) * 100; ?> dark:bg-gray-800">
                        <a href="book.php?id=<?php echo $book['id']; ?>" class="block h-full">
                            <div class="relative overflow-hidden">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                         class="w-full h-64 object-cover transform transition-transform hover:scale-110">
                                <?php else: ?>
                                    <div class="w-full h-64 bg-gray-200 flex items-center justify-center transform transition-transform hover:scale-110 dark:bg-gray-700">
                                        <i class="fas fa-book text-5xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($book['views_count']) && $book['views_count'] > 10): ?>
                                    <div class="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full">
                                        <i class="fas fa-eye mr-1"></i> <?php echo $book['views_count']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <div class="text-sm text-blue-600 mb-1 dark:text-blue-400"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                                <h3 class="text-xl font-bold mb-2 dark:text-white line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <?php if (!empty($book['author'])): ?>
                                    <p class="text-gray-600 dark:text-gray-400">par <span class="italic"><?php echo htmlspecialchars($book['author']); ?></span></p>
                                <?php endif; ?>
                                <div class="mt-4 flex justify-end">
                                    <span class="text-blue-600 hover:text-blue-800 inline-flex items-center dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                        En savoir plus <i class="fas fa-arrow-right ml-2"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-md animate-fadeIn dark:bg-gray-800">
                <div class="text-gray-400 text-6xl mb-6 dark:text-gray-500">
                    <i class="fas fa-book-open"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-700 mb-3 dark:text-white">Aucun livre trouvé</h2>
                <p class="text-gray-600 mb-6 dark:text-gray-400">
                    <?php if (!empty($search) || $category_id > 0): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucun livre n'est disponible dans la bibliothèque pour le moment.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || $category_id > 0): ?>
                    <a href="library.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded transition-colors inline-flex items-center btn">
                        <i class="fas fa-undo-alt mr-2"></i> Afficher tous les livres
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments au scroll
    const animateElements = () => {
        const elements = document.querySelectorAll('.animate-fadeInUp, .animate-fadeIn');
        elements.forEach(el => {
            const rect = el.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            if (rect.top <= windowHeight * 0.85 && !el.classList.contains('animated')) {
                el.style.opacity = '1';
                el.classList.add('animated');
            }
        });
    };
    
    // Initialiser les animations
    animateElements();
    
    // Animer au scroll
    window.addEventListener('scroll', animateElements);
    
    // Effet de hover amélioré pour les cartes de livres
    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Auto-soumission du formulaire lors des changements de tri ou catégorie
    const categorySelect = document.getElementById('category');
    const sortSelect = document.getElementById('sort');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>