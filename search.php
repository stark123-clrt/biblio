<?php
// search.php - Page de recherche
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

// Récupérer les paramètres de recherche
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

// Construire la requête SQL de base
$sql_select = "SELECT DISTINCT b.*, c.name as category_name";
$sql_from = " FROM books b
              LEFT JOIN book_categories bc ON b.id = bc.book_id
              LEFT JOIN categories c ON bc.category_id = c.id";
$sql_where = " WHERE 1=1";
$sql_order = "";
$params = [];

// Ajouter la condition de recherche si une requête est fournie
if (!empty($search_query)) {
    $sql_where .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.description LIKE :search)";
    $search_param = "%" . $search_query . "%";
    $params[':search'] = $search_param;
}

// Filtrer par catégorie si spécifiée
if ($category_id > 0) {
    $sql_where .= " AND bc.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

// Définir l'ordre de tri
switch ($sort_by) {
    case 'title_asc':
        $sql_order = " ORDER BY b.title ASC";
        break;
    case 'title_desc':
        $sql_order = " ORDER BY b.title DESC";
        break;
    case 'recent':
        $sql_order = " ORDER BY b.created_at DESC";
        break;
    case 'popularity':
        $sql_order = " ORDER BY b.views_count DESC";
        break;
    default:
        // Par défaut, trier par pertinence (si une recherche est effectuée)
        if (!empty($search_query)) {
            // Pour la pertinence, on priorise les correspondances exactes dans le titre
            $sql_order = " ORDER BY 
                          CASE WHEN b.title LIKE :exact_title THEN 1
                               WHEN b.author LIKE :exact_author THEN 2
                               ELSE 3 END,
                          b.views_count DESC";
            $exact_title = "%" . $search_query . "%";
            $params[':exact_title'] = $exact_title;
            $params[':exact_author'] = $exact_title;
        } else {
            $sql_order = " ORDER BY b.created_at DESC";
        }
        break;
}

// Exécuter la requête pour compter le nombre total de résultats
$count_sql = "SELECT COUNT(DISTINCT b.id)" . $sql_from . $sql_where;
$stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_results = $stmt->fetchColumn();

// Pagination
$results_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_results / $results_per_page);
$offset = ($current_page - 1) * $results_per_page;

// Requête finale avec pagination
$sql = $sql_select . $sql_from . $sql_where . $sql_order . " LIMIT :offset, :limit";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour le filtre
$stmt = $conn->prepare("SELECT id, name, 
                     (SELECT COUNT(*) FROM book_categories WHERE category_id = categories.id) as book_count 
                     FROM categories 
                     ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = !empty($search_query) ? "Recherche: " . htmlspecialchars($search_query) : "Recherche";
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
  --info-bg-light: #dbeafe;
  --info-bg-dark: rgba(37, 99, 235, 0.2);
  --info-text-light: #1d4ed8;
  --info-text-dark: #60a5fa;
  --highlight-bg-light: #e0f2fe;
  --highlight-bg-dark: rgba(3, 105, 161, 0.3);
  --highlight-text-light: #0369a1;
  --highlight-text-dark: #38bdf8;
  --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.18);
  --shadow-hover-light: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-hover-dark: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

/* Transitions globales */
body, .bg-white, .bg-gray-50, .bg-gray-100, .bg-blue-50, input, select, textarea, button, a, .search-result-card {
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

.dark .bg-blue-50 {
  background-color: var(--info-bg-dark);
}

.dark .text-gray-500,
.dark .text-gray-600,
.dark .text-gray-700,
.dark .text-gray-800 {
  color: var(--text-dark-secondary);
}

.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .hover\:text-blue-800:hover {
  color: #93c5fd;
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

.dark .focus\:ring-2:focus {
  --tw-ring-opacity: 0.4;
}

.dark .focus\:ring-blue-500:focus {
  --tw-ring-color: rgba(59, 130, 246, var(--tw-ring-opacity));
}

/* Cartes de résultats */
.search-result-card {
  transition: all 0.3s ease;
  transform-origin: center;
}

.search-result-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-hover-light);
  z-index: 10;
}

.dark .search-result-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Pagination */
.dark .bg-blue-50 {
  background-color: rgba(59, 130, 246, 0.2);
}

.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .hover\:bg-gray-100:hover {
  background-color: var(--card-alt-dark);
}

/* Highlight de résultats */
.search-highlight {
  background-color: rgba(37, 99, 235, 0.1);
  padding: 0 2px;
  border-radius: 2px;
}

.dark .search-highlight {
  background-color: rgba(59, 130, 246, 0.3);
}

/* État vide */
.empty-state-icon {
  transition: all 0.3s ease;
}

.dark .empty-state-icon {
  color: var(--info-text-dark);
}

/* Text limiter */
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
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
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  pointer-events: none;
}

.search-box-container input {
  padding-left: 40px;
}

.dark .search-box-container .search-icon {
  color: #9ca3af;
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white flex items-center animate-fadeInUp">
        <i class="fas fa-search text-blue-600 dark:text-blue-500 mr-3"></i>
        <?php if (!empty($search_query)): ?>
            Résultats de recherche pour "<span class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($search_query); ?></span>"
        <?php else: ?>
            Recherche avancée
        <?php endif; ?>
    </h1>
    
    <!-- Filtres et options de recherche -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fadeInUp dark:bg-gray-800">
        <form action="search.php" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-2 search-box-container">
                    <label for="q" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-search mr-2 text-blue-600 dark:text-blue-500"></i> Rechercher
                    </label>
                    <div class="relative">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                               placeholder="Titre, auteur ou description...">
                    </div>
                </div>
                
                <div>
                    <label for="category" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-tag mr-2 text-blue-600 dark:text-blue-500"></i> Catégorie
                    </label>
                    <select id="category" name="category" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?> 
                                (<?php echo $category['book_count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">
                        <i class="fas fa-sort mr-2 text-blue-600 dark:text-blue-500"></i> Trier par
                    </label>
                    <select id="sort" name="sort" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="relevance" <?php echo $sort_by == 'relevance' ? 'selected' : ''; ?>>Pertinence</option>
                        <option value="title_asc" <?php echo $sort_by == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                        <option value="title_desc" <?php echo $sort_by == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                        <option value="recent" <?php echo $sort_by == 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="popularity" <?php echo $sort_by == 'popularity' ? 'selected' : ''; ?>>Popularité</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg btn transition-colors">
                    <i class="fas fa-search mr-2"></i> Rechercher
                </button>
            </div>
        </form>
    </div>
    
    <!-- Résultats de recherche -->
    <div class="mb-6 animate-fadeInUp delay-100">
        <?php if (!empty($search_query) || $category_id > 0): ?>
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center bg-white p-4 rounded-lg shadow-sm mb-6 dark:bg-gray-800">
                <h2 class="text-xl font-bold dark:text-white flex items-center mb-2 sm:mb-0">
                    <i class="fas fa-filter mr-2 text-blue-600 dark:text-blue-500"></i>
                    <?php echo $total_results; ?> résultat<?php echo $total_results != 1 ? 's' : ''; ?> trouvé<?php echo $total_results != 1 ? 's' : ''; ?>
                </h2>
                
                <?php if ($total_pages > 1): ?>
                    <div class="text-gray-600 dark:text-gray-400 flex items-center">
                        <i class="fas fa-file-alt mr-2"></i> Page <?php echo $current_page; ?> sur <?php echo $total_pages; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($results as $index => $book): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden search-result-card animate-fadeInUp delay-<?php echo ($index % 4 + 1) * 100; ?> dark:bg-gray-800">
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
                                <?php if ($sort_by == 'popularity' || $book['views_count'] > 50): ?>
                                    <div class="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full">
                                        <i class="fas fa-eye mr-1"></i> <?php echo $book['views_count']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <div class="text-sm text-blue-600 mb-1 dark:text-blue-400"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                                <h3 class="text-xl font-bold mb-2 dark:text-white">
                                <?php 
                                    // Surligner les termes de recherche dans le titre
                                    if (!empty($search_query)) {
                                        $title = htmlspecialchars($book['title']);
                                        $pattern = '/' . preg_quote($search_query, '/') . '/i';
                                        echo preg_replace($pattern, '<span class="search-highlight">$0</span>', $title);
                                    } else {
                                        echo htmlspecialchars($book['title']);
                                    }
                                ?>
                                </h3>
                                <?php if (!empty($book['author'])): ?>
                                    <p class="text-gray-600 dark:text-gray-400">par 
                                    <?php 
                                        // Surligner les termes de recherche dans l'auteur
                                        if (!empty($search_query)) {
                                            $author = htmlspecialchars($book['author']);
                                            $pattern = '/' . preg_quote($search_query, '/') . '/i';
                                            echo preg_replace($pattern, '<span class="search-highlight">$0</span>', $author);
                                        } else {
                                            echo htmlspecialchars($book['author']);
                                        }
                                    ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($book['description'])): ?>
                                    <p class="text-gray-600 mt-2 line-clamp-3 dark:text-gray-400">
                                    <?php 
                                        // Surligner les termes de recherche dans la description
                                        $desc = htmlspecialchars(substr($book['description'], 0, 150)) . (strlen($book['description']) > 150 ? '...' : '');
                                        if (!empty($search_query)) {
                                            $pattern = '/' . preg_quote($search_query, '/') . '/i';
                                            echo preg_replace($pattern, '<span class="search-highlight">$0</span>', $desc);
                                        } else {
                                            echo $desc;
                                        }
                                    ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mt-4 flex justify-between items-center">
                                    <div class="text-gray-600 text-sm dark:text-gray-400 flex items-center">
                                        <i class="fas fa-eye mr-1"></i> <?php echo $book['views_count']; ?> vues
                                    </div>
                                    <span class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors flex items-center">
                                        Détails <i class="fas fa-arrow-right ml-2"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center animate-fadeInUp delay-300">
                    <div class="inline-flex rounded-md shadow-sm">
                        <?php if ($current_page > 1): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $current_page - 1; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 rounded-l-lg transition-colors flex items-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                <i class="fas fa-chevron-left mr-1"></i> Précédent
                            </a>
                        <?php else: ?>
                            <span class="bg-white border border-gray-300 text-gray-300 px-4 py-2 rounded-l-lg cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-600">
                                <i class="fas fa-chevron-left mr-1"></i> Précédent
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        // Déterminer les pages à afficher
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Afficher "..." si nécessaire
                        if ($start_page > 1): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=1" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                                <span class="bg-white border border-gray-300 text-gray-500 px-4 py-2 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
                                    <i class="fas fa-ellipsis-h"></i>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <span class="bg-blue-50 border border-gray-300 text-blue-600 font-medium px-4 py-2 dark:bg-blue-900/30 dark:border-gray-600 dark:text-blue-400">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $i; ?>" 
                                   class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="bg-white border border-gray-300 text-gray-500 px-4 py-2 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
                                    <i class="fas fa-ellipsis-h"></i>
                                </span>
                            <?php endif; ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $total_pages; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 transition-colors dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $current_page + 1; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 rounded-r-lg transition-colors flex items-center dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                Suivant <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php else: ?>
                            <span class="bg-white border border-gray-300 text-gray-300 px-4 py-2 rounded-r-lg cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-600">
                                Suivant <i class="fas fa-chevron-right ml-1"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="bg-blue-50 p-8 rounded-lg text-center animate-fadeIn dark:bg-blue-900/20">
            <div class="text-blue-600 text-5xl mb-4 empty-state-icon dark:text-blue-400">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2 dark:text-gray-200">Aucun résultat trouvé</h3>
                <p class="text-gray-600 mb-4 dark:text-gray-400">
                    <?php if (!empty($search_query)): ?>
                        Aucun livre ne correspond à votre recherche "<span class="font-medium text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($search_query); ?></span>".
                    <?php else: ?>
                        Veuillez effectuer une recherche pour voir les résultats.
                    <?php endif; ?>
                </p>
                <div class="mt-4 max-w-md mx-auto">
                    <p class="text-gray-600 mb-2 dark:text-gray-400 font-medium">Suggestions :</p>
                    <ul class="text-gray-600 bg-white p-4 rounded-lg shadow-sm dark:bg-gray-800 dark:text-gray-400">
                        <li class="py-1 flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Vérifiez l'orthographe des termes de recherche.</li>
                        <li class="py-1 flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Essayez des termes plus généraux.</li>
                        <li class="py-1 flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i> Essayez des termes différents avec une signification similaire.</li>
                    </ul>
                    <?php if (!empty($search_query)): ?>
                        <div class="mt-6">
                            <a href="search.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg inline-flex items-center transition-colors btn">
                                <i class="fas fa-undo-alt mr-2"></i> Réinitialiser la recherche
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
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
    
    // Effet de hover amélioré pour les cartes de résultats
    const resultCards = document.querySelectorAll('.search-result-card');
    resultCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Surlignage dynamique des termes de recherche
    const searchInput = document.getElementById('q');
    const searchQuery = searchInput ? searchInput.value.trim() : '';
    
    if (searchQuery) {
        const terms = searchQuery.toLowerCase().split(/\s+/);
        const contentElements = document.querySelectorAll('.line-clamp-3, h3');
        
        contentElements.forEach(element => {
            const originalText = element.textContent;
            if (originalText) {
                let highlightedText = originalText;
                terms.forEach(term => {
                    if (term.length > 2) { // Ignorer les termes trop courts
                        const regex = new RegExp('(' + term + ')', 'gi');
                        highlightedText = highlightedText.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
                
                // Uniquement si des correspondances ont été trouvées et que le contenu n'a pas déjà été modifié
                if (highlightedText !== originalText && !element.innerHTML.includes('search-highlight')) {
                    element.innerHTML = highlightedText;
                }
            }
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>