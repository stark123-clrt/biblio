<?php
// category.php - Affichage des livres par catégorie
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
$sql = "SELECT b.*, c.name as category_name 
        FROM books b 
        JOIN book_categories bc ON b.id = bc.book_id 
        LEFT JOIN categories c ON bc.category_id = c.id 
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
        $sql .= " ORDER BY b.views_count DESC";
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

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <nav class="text-sm">
            <ol class="list-none p-0 inline-flex">
                <li class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800">Accueil</a>
                    <svg class="w-3 h-3 fill-current mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li class="flex items-center">
                    <a href="library.php" class="text-blue-600 hover:text-blue-800">Bibliothèque</a>
                    <svg class="w-3 h-3 fill-current mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <?php if ($parent_category): ?>
                    <li class="flex items-center">
                        <a href="category.php?id=<?php echo $parent_category['id']; ?>" class="text-blue-600 hover:text-blue-800">
                            <?php echo htmlspecialchars($parent_category['name']); ?>
                        </a>
                        <svg class="w-3 h-3 fill-current mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                    </li>
                <?php endif; ?>
                <li class="text-gray-500"><?php echo htmlspecialchars($category['name']); ?></li>
            </ol>
        </nav>
    </div>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($category['name']); ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($category['description']); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Sous-catégories -->
    <?php if (!empty($subcategories)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4">Sous-catégories</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($subcategories as $subcat): ?>
                    <a href="category.php?id=<?php echo $subcat['id']; ?>" class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow">
                        <h3 class="font-bold"><?php echo htmlspecialchars($subcat['name']); ?></h3>
                        <?php if (!empty($subcat['description'])): ?>
                            <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($subcat['description']); ?></p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Options de tri -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 flex flex-wrap justify-between items-center">
        <div>
            <span class="text-gray-600"><?php echo $total_books; ?> livre<?php echo $total_books > 1 ? 's' : ''; ?> dans cette catégorie</span>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-gray-600">Trier par:</span>
            <select id="sort-select" class="border rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Popularité</option>
            </select>
        </div>
    </div>
    
    <!-- Liste des livres -->
    <?php if (!empty($books)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($books as $book): ?>
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
                            <div class="mt-4 flex justify-end">
                                <a href="book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    En savoir plus <i class="fas fa-arrow-right ml-1"></i>
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
                <div class="inline-flex rounded-md shadow-sm">
                    <?php if ($page > 1): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">
                            Précédent
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-l-md">
                            Précédent
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-gray-300">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="category.php?id=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">
                            Suivant
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-r-md">
                            Suivant
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-8 bg-white rounded-lg shadow-md">
            <div class="text-gray-400 text-6xl mb-4">
                <i class="fas fa-book-open"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2">Aucun livre dans cette catégorie</h2>
            <p class="text-gray-600 mb-4">
                Il n'y a pas encore de livres disponibles dans cette catégorie.
            </p>
            <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Retour à la bibliothèque
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du tri
        const sortSelect = document.getElementById('sort-select');
        sortSelect.addEventListener('change', function() {
            window.location.href = 'category.php?id=<?php echo $category_id; ?>&sort=' + this.value;
        });
    });
</script>

<?php include "includes/footer.php"; ?>