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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Bibliothèque</h1>
    
    <!-- Barre de recherche et filtres -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form action="library.php" method="GET" class="space-y-4">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-gray-700 font-bold mb-2">Rechercher</label>
                    <input type="text" id="search" name="search" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Titre, auteur ou description...">
                </div>
                
                <div class="w-full md:w-1/4">
                    <label for="category" class="block text-gray-700 font-bold mb-2">Catégorie</label>
                    <select id="category" name="category" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="w-full md:w-1/4">
                    <label for="sort" class="block text-gray-700 font-bold mb-2">Trier par</label>
                    <select id="sort" name="sort" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                        <option value="title_asc" <?php echo $sort == 'title_asc' ? 'selected' : ''; ?>>Titre (A-Z)</option>
                        <option value="title_desc" <?php echo $sort == 'title_desc' ? 'selected' : ''; ?>>Titre (Z-A)</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Plus populaires</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-search mr-2"></i> Rechercher
                </button>
            </div>
        </form>
    </div>
    
    <!-- Résultats de la recherche -->
    <div>
        <?php if (!empty($search) || $category_id > 0): ?>
            <div class="mb-4 flex justify-between items-center">
                <h2 class="text-xl font-bold">
                    <?php echo count($books); ?> résultat(s)
                    <?php if (!empty($search)): ?>
                        pour "<?php echo htmlspecialchars($search); ?>"
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
                        dans la catégorie "<?php echo htmlspecialchars($category_name); ?>"
                    <?php endif; ?>
                </h2>
                
                <a href="library.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-times mr-1"></i> Effacer les filtres
                </a>
            </div>
        <?php endif; ?>
        
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
        <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 text-6xl mb-4">
                    <i class="fas fa-book-open"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-700 mb-2">Aucun livre trouvé</h2>
                <p class="text-gray-600 mb-4">
                    <?php if (!empty($search) || $category_id > 0): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucun livre n'est disponible dans la bibliothèque pour le moment.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || $category_id > 0): ?>
                    <a href="library.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Afficher tous les livres
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>