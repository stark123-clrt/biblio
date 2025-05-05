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
  --yellow-star: #fbbf24;
  --yellow-star-dark: #f59e0b;
  --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.18);
  --shadow-hover-light: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-hover-dark: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

/* Transitions globales */
body, .bg-white, .bg-gray-100, input, select, textarea, button, a, .card-item {
  transition: all 0.3s ease;
}

/* Bannière principale - mode sombre */
.banner-area {
  position: relative;
  overflow: hidden;
}

.banner-area:before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, #1e3a8a, #1e40af);
  z-index: 0;
}

.dark .banner-area:before {
  background: linear-gradient(45deg, #111827, #1f2937);
}

/* Adaptations du mode sombre */
.dark .bg-white {
  background-color: var(--card-dark);
  color: var(--text-dark);
}

.dark .bg-gray-100 {
  background-color: var(--bg-dark-alt);
  color: var(--text-dark);
}

.dark .bg-gray-200 {
  background-color: var(--card-alt-dark);
}

.dark .text-gray-500,
.dark .text-gray-600 {
  color: var(--text-dark-secondary);
}

.dark .text-gray-400 {
  color: #6b7280;
}

.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .text-blue-800 {
  color: #60a5fa;
}

.dark .hover\:text-blue-800:hover {
  color: #93c5fd;
}

.dark .text-yellow-400 {
  color: var(--yellow-star);
}

/* Cartes livres */
.book-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  overflow: hidden;
}

.book-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-hover-light);
}

.dark .book-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Badges et catégories */
.category-badge {
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.category-badge:after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background-color: var(--primary-blue);
  transform: scaleX(0);
  transform-origin: bottom right;
  transition: transform 0.3s ease;
}

.category-badge:hover:after {
  transform: scaleX(1);
  transform-origin: bottom left;
}

/* Testimonial cards */
.testimonial-card {
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.testimonial-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-hover-light);
}

.dark .testimonial-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

.testimonial-card:before {
  content: '"';
  position: absolute;
  top: 10px;
  left: 10px;
  font-size: 5rem;
  line-height: 1;
  opacity: 0.1;
  font-family: serif;
}

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

/* Custom animations */
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

/* Animation delay classes */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
.delay-400 { animation-delay: 0.4s; }
</style>

<!-- Bannière principale -->
<div class="relative bg-blue-900 text-white banner-area">
    <div class="absolute inset-0 bg-opacity-25"></div>
    <div class="container mx-auto px-4 py-16 relative z-10">
        <div class="max-w-2xl animate-fadeInUp">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight">Bibliothèque Chrétienne</h1>
            <p class="text-xl mb-8 opacity-90">Découvrez une collection de livres chrétiens en ligne pour nourrir votre foi et approfondir vos connaissances spirituelles.</p>
            <div class="flex flex-wrap gap-4">
                <a href="library.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold btn transition-colors">
                    <i class="fas fa-book-open mr-2"></i> Parcourir la bibliothèque
                </a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="bg-white hover:bg-gray-100 text-blue-900 px-6 py-3 rounded-lg font-bold btn transition-colors dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-blue-400">
                        <i class="fas fa-user-plus mr-2"></i> Créer un compte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Livres en vedette -->
<section class="py-12 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8 dark:text-white flex items-center">
            <i class="fas fa-star text-yellow-400 mr-3"></i> Livres en vedette
        </h2>
        
        <?php if (!empty($featuredBooks)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($featuredBooks as $index => $book): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden transition-transform book-card animate-fadeInUp delay-<?php echo ($index + 1) * 100; ?> dark:bg-gray-800">
                        <a href="book.php?id=<?php echo $book['id']; ?>">
                            <?php if (!empty($book['cover_image'])): ?>
                                <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full h-64 object-cover">
                            <?php else: ?>
                                <div class="w-full h-64 bg-gray-200 flex items-center justify-center dark:bg-gray-700">
                                    <i class="fas fa-book text-5xl text-gray-400 dark:text-gray-500"></i>
                                </div>
                            <?php endif; ?>
                            <div class="p-4">
                                <div class="text-sm text-blue-600 mb-1 dark:text-blue-400"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                                <h3 class="text-xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <?php if (!empty($book['author'])): ?>
                                    <p class="text-gray-600 dark:text-gray-400">par <?php echo htmlspecialchars($book['author']); ?></p>
                                <?php endif; ?>
                                <div class="mt-4 flex justify-end">
                                    <span class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                        En savoir plus <i class="fas fa-arrow-right ml-1"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-500 dark:text-gray-400">
                <p>Aucun livre en vedette pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Catégories principales -->
<section class="py-12 bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8 dark:text-white flex items-center">
            <i class="fas fa-tags text-blue-600 dark:text-blue-500 mr-3"></i> Explorez par catégorie
        </h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php foreach ($categories as $index => $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>" 
                   class="block bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow category-badge animate-fadeInUp delay-<?php echo ($index % 3 + 1) * 100; ?> dark:bg-gray-800 dark:text-white">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
                    <div class="flex justify-between items-end">
                        <p class="text-gray-600 dark:text-gray-400"><?php echo $category['book_count']; ?> livre<?php echo $category['book_count'] > 1 ? 's' : ''; ?></p>
                        <i class="fas fa-chevron-right text-blue-600 dark:text-blue-400"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold btn transition-colors">
                <i class="fas fa-th-large mr-2"></i> Voir toutes les catégories
            </a>
        </div>
    </div>
</section>

<!-- Derniers ajouts -->
<section class="py-12 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8 dark:text-white flex items-center">
            <i class="fas fa-clock text-blue-600 dark:text-blue-500 mr-3"></i> Derniers ajouts
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($recentBooks as $index => $book): ?>
                <div class="flex bg-white rounded-lg shadow-md overflow-hidden h-32 hover:shadow-lg transition-shadow animate-fadeInUp delay-<?php echo ($index % 4 + 1) * 100; ?> dark:bg-gray-800">
                    <?php if (!empty($book['cover_image'])): ?>
                        <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-24 h-full object-cover">
                    <?php else: ?>
                        <div class="w-24 h-full bg-gray-200 flex items-center justify-center dark:bg-gray-700">
                            <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 p-3 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold line-clamp-2 dark:text-white"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <div class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></div>
                        </div>
                        <a href="book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                            Détails <i class="fas fa-angle-right ml-1"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold btn transition-colors">
                <i class="fas fa-books mr-2"></i> Voir tous les livres
            </a>
        </div>
    </div>
</section>

<!-- Témoignages -->
<?php if (!empty($testimonials)): ?>
<section class="py-12 bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold mb-8 dark:text-white flex items-center">
            <i class="fas fa-quote-left text-blue-600 dark:text-blue-500 mr-3"></i> Ils nous ont fait confiance
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($testimonials as $index => $testimonial): ?>
                <div class="bg-white rounded-lg shadow-md p-6 testimonial-card animate-fadeInUp delay-<?php echo ($index + 1) * 100; ?> dark:bg-gray-800">
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
                    <p class="text-gray-600 mb-4 dark:text-gray-300"><?php echo htmlspecialchars($testimonial['comment_text']); ?></p>
                    <div class="flex justify-between items-end">
                        <div>
                            <div class="font-bold dark:text-white"><?php echo htmlspecialchars($testimonial['username']); ?></div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">À propos de "<?php echo htmlspecialchars($testimonial['book_title']); ?>"</div>
                        </div>
                        <a href="book.php?id=<?php echo $testimonial['book_id']; ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                            Voir le livre <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="testimonials.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold btn transition-colors">
                <i class="fas fa-comments mr-2"></i> Voir tous les témoignages
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Inscription -->
<?php if (!isset($_SESSION['user_id'])): ?>
<section class="py-12 bg-blue-800 text-white dark:bg-blue-900">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Rejoignez notre communauté</h2>
        <p class="text-xl mb-8 max-w-3xl mx-auto">Créez votre compte gratuitement pour accéder à tous nos livres, prendre des notes et partager vos impressions avec les autres lecteurs.</p>
        <a href="register.php" class="inline-block bg-white hover:bg-gray-100 text-blue-800 px-8 py-4 rounded-lg font-bold text-lg btn transition-colors dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-blue-400">
            <i class="fas fa-user-plus mr-2"></i> S'inscrire maintenant
        </a>
    </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments au scroll
    const animateElements = () => {
        const elements = document.querySelectorAll('.animate-fadeInUp');
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
});
</script>

<?php include "includes/footer.php"; ?>