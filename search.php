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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">
        <?php if (!empty($search_query)): ?>
            Résultats de recherche pour "<?php echo htmlspecialchars($search_query); ?>"
        <?php else: ?>
            Recherche avancée
        <?php endif; ?>
    </h1>
    
    <!-- Filtres et options de recherche -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form action="search.php" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-2">
                    <label for="q" class="block text-gray-700 font-bold mb-2">Rechercher</label>
                    <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Titre, auteur ou description...">
                </div>
                
                <div>
                    <label for="category" class="block text-gray-700 font-bold mb-2">Catégorie</label>
                    <select id="category" name="category" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                    <label for="sort" class="block text-gray-700 font-bold mb-2">Trier par</label>
                    <select id="sort" name="sort" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="relevance" <?php echo $sort_by == 'relevance' ? 'selected' : ''; ?>>Pertinence</option>
                        <option value="title_asc" <?php echo $sort_by == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                        <option value="title_desc" <?php echo $sort_by == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                        <option value="recent" <?php echo $sort_by == 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="popularity" <?php echo $sort_by == 'popularity' ? 'selected' : ''; ?>>Popularité</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Rechercher
                </button>
            </div>
        </form>
    </div>
    
    <!-- Résultats de recherche -->
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">
                <?php echo $total_results; ?> résultat<?php echo $total_results != 1 ? 's' : ''; ?> trouvé<?php echo $total_results != 1 ? 's' : ''; ?>
            </h2>
            
            <?php if ($total_pages > 1): ?>
                <div class="text-gray-600">
                    Page <?php echo $current_page; ?> sur <?php echo $total_pages; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($results as $book): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden transition-transform hover:scale-105">
                        <a href="book.php?id=<?php echo $book['id']; ?>">
                            <?php if (!empty($book['cover_image'])): ?>
                                <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-64 object-cover">
                            <?php else: ?>
                                <div class="w-full h-64 bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-book text-5xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div class="p-4">
                                <div class="text-sm text-blue-600 mb-1"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                                <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <?php if (!empty($book['author'])): ?>
                                    <p class="text-gray-600">par <?php echo htmlspecialchars($book['author']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($book['description'])): ?>
                                    <p class="text-gray-600 mt-2 line-clamp-3"><?php echo htmlspecialchars(substr($book['description'], 0, 150)) . (strlen($book['description']) > 150 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <div class="mt-4 flex justify-between items-center">
                                    <div class="text-gray-600 text-sm">
                                        <i class="fas fa-eye mr-1"></i> <?php echo $book['views_count']; ?> vues
                                    </div>
                                    <a href="book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        Détails <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <div class="inline-flex">
                        <?php if ($current_page > 1): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $current_page - 1; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 rounded-l-lg">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Déterminer les pages à afficher
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Afficher "..." si nécessaire
                        if ($start_page > 1): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=1" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                                <span class="bg-white border border-gray-300 text-gray-500 px-4 py-2">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $i; ?>" 
                               class="bg-white border border-gray-300 <?php echo $i == $current_page ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-500 hover:bg-gray-100'; ?> px-4 py-2">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="bg-white border border-gray-300 text-gray-500 px-4 py-2">...</span>
                            <?php endif; ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $total_pages; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $current_page + 1; ?>" 
                               class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-100 px-4 py-2 rounded-r-lg">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="bg-blue-50 p-8 rounded-lg text-center">
                <div class="text-blue-600 text-5xl mb-4">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Aucun résultat trouvé</h3>
                <p class="text-gray-600 mb-4">
                    <?php if (!empty($search_query)): ?>
                        Aucun livre ne correspond à votre recherche "<?php echo htmlspecialchars($search_query); ?>".
                    <?php else: ?>
                        Veuillez effectuer une recherche pour voir les résultats.
                    <?php endif; ?>
                </p>
                <div class="mt-4">
                    <p class="text-gray-600 mb-2">Suggestions :</p>
                    <ul class="text-gray-600">
                        <li>• Vérifiez l'orthographe des termes de recherche.</li>
                        <li>• Essayez des termes plus généraux.</li>
                        <li>• Essayez des termes différents avec une signification similaire.</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Style pour limiter le nombre de lignes dans la description */
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<?php include "includes/footer.php"; ?>