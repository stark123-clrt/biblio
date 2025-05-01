<?php
// index.php - Page d'accueil
require_once "config/database.php";

// Mode SQL moins strict pour les GROUP BY (alternative temporaire)
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Récupérer les livres en vedette
$stmt = $conn->prepare("SELECT b.*, c.name as category_name
                        FROM books b
                        LEFT JOIN book_categories bc ON b.id = bc.book_id
                        LEFT JOIN categories c ON bc.category_id = c.id
                        WHERE b.is_featured = 1
                        GROUP BY b.id
                        ORDER BY b.created_at DESC
                        LIMIT 4");
$stmt->execute();
$featuredBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les derniers livres ajoutés
$stmt = $conn->prepare("SELECT b.*, c.name as category_name
                        FROM books b
                        LEFT JOIN book_categories bc ON b.id = bc.book_id
                        LEFT JOIN categories c ON bc.category_id = c.id
                        GROUP BY b.id
                        ORDER BY b.created_at DESC
                        LIMIT 8");
$stmt->execute();
$recentBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories principales
$stmt = $conn->prepare("SELECT c.*, COUNT(bc.book_id) as book_count
                        FROM categories c
                        LEFT JOIN book_categories bc ON c.id = bc.category_id
                        GROUP BY c.id
                        ORDER BY book_count DESC
                        LIMIT 6");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer quelques témoignages validés
$stmt = $conn->prepare("SELECT c.*, u.username, b.title as book_title
                        FROM comments c
                        JOIN users u ON c.user_id = u.id
                        JOIN books b ON c.book_id = b.id
                        WHERE c.is_validated = 1 AND c.is_featured = 1
                        ORDER BY c.created_at DESC
                        LIMIT 3");
$stmt->execute();
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Accueil";
include "includes/header.php";
?>

<!-- Bannière principale -->
<div class="relative bg-blue-900 text-white">
    <div class="absolute inset-0 bg-opacity-25"></div>
    <div class="container mx-auto px-4 py-16 relative z-10">
        <div class="max-w-2xl">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Bibliothèque Chrétienne</h1>
            <p class="text-xl mb-8">Découvrez une collection de livres chrétiens en ligne pour nourrir votre foi et approfondir vos connaissances spirituelles.</p>
            <div class="flex flex-wrap gap-4">
                <a href="library.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold">
                    Parcourir la bibliothèque
                </a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="bg-white hover:bg-gray-100 text-blue-900 px-6 py-3 rounded-lg font-bold">
                        Créer un compte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Livres en vedette -->
<section class="py-12 bg-white">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8">Livres en vedette</h2>
        
        <?php if (!empty($featuredBooks)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($featuredBooks as $book): ?>
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
            <div class="text-center text-gray-500">
                <p>Aucun livre en vedette pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Catégories principales -->
<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8">Explorez par catégorie</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>" class="block bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
                    <div class="flex justify-between items-end">
                        <p class="text-gray-600"><?php echo $category['book_count']; ?> livre<?php echo $category['book_count'] > 1 ? 's' : ''; ?></p>
                        <i class="fas fa-chevron-right text-blue-600"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold">
                Voir toutes les catégories
            </a>
        </div>
    </div>
</section>

<!-- Derniers ajouts -->
<section class="py-12 bg-white">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8">Derniers ajouts</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($recentBooks as $book): ?>
                <div class="flex bg-white rounded-lg shadow-md overflow-hidden h-32">
                    <?php if (!empty($book['cover_image'])): ?>
                        <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-24 h-full object-cover">
                    <?php else: ?>
                        <div class="w-24 h-full bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-book text-2xl text-gray-400"></i>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 p-3 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold line-clamp-2"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <div class="text-xs text-gray-600"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                        </div>
                        <a href="book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                            Détails
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold">
                Voir tous les livres
            </a>
        </div>
    </div>
</section>

<!-- Témoignages -->
<?php if (!empty($testimonials)): ?>
<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8">Ils nous ont fait confiance</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 text-lg">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $testimonial['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($testimonial['comment_text']); ?></p>
                    <div class="flex justify-between items-end">
                        <div>
                            <div class="font-bold"><?php echo htmlspecialchars($testimonial['username']); ?></div>
                            <div class="text-sm text-gray-500">À propos de "<?php echo htmlspecialchars($testimonial['book_title']); ?>"</div>
                        </div>
                        <a href="book.php?id=<?php echo $testimonial['book_id']; ?>" class="text-blue-600 hover:text-blue-800">
                            Voir le livre
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="testimonials.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold">
                Voir tous les témoignages
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Inscription -->
<?php if (!isset($_SESSION['user_id'])): ?>
<section class="py-12 bg-blue-800 text-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Rejoignez notre communauté</h2>
        <p class="text-xl mb-8 max-w-3xl mx-auto">Créez votre compte gratuitement pour accéder à tous nos livres, prendre des notes et partager vos impressions avec les autres lecteurs.</p>
        <a href="register.php" class="inline-block bg-white hover:bg-gray-100 text-blue-800 px-8 py-4 rounded-lg font-bold text-lg">
            S'inscrire maintenant
        </a>
    </div>
</section>
<?php endif; ?>

<?php include "includes/footer.php"; ?>