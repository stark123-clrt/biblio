<?php
// index.php - Page d'accueil MODERNE
require_once "config/database.php";

// Mode SQL moins strict pour les GROUP BY (alternative temporaire)
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Récupérer les statistiques réelles de la base de données
$stats = [
    'total_books' => 0,
    'total_users' => 0,
    'total_categories' => 0,
    'total_views' => 0
];

// Compter les livres
$stmt = $conn->query("SELECT COUNT(*) FROM books");
$stats['total_books'] = $stmt->fetchColumn();

// Compter les utilisateurs
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Compter les catégories
$stmt = $conn->query("SELECT COUNT(*) FROM categories");
$stats['total_categories'] = $stmt->fetchColumn();

// Compter les vues totales (si la colonne existe)
try {
    $stmt = $conn->query("SELECT SUM(COALESCE(views_count, 0)) FROM books");
    $stats['total_views'] = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {
    $stats['total_views'] = 0;
}

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
/* Styles modernes améliorés */
.hero-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.hero-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
    background-size: cover;
}

.dark .hero-gradient {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.dark .glass-card {
    background: rgba(31, 41, 55, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.modern-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(0);
}

.modern-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
}

.dark .modern-card:hover {
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
}

.category-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dark .category-card {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #3b82f6, transparent);
    transition: left 0.5s ease;
}

.category-card:hover::before {
    left: 100%;
}

.btn-modern {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-modern:hover::before {
    left: 100%;
}

.testimonial-modern {
    position: relative;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-left: 4px solid transparent;
    background-clip: padding-box;
}

.dark .testimonial-modern {
    background: linear-gradient(145deg, #374151 0%, #1f2937 100%);
}

.testimonial-modern::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.testimonial-modern:hover::before {
    opacity: 1;
}

.floating-animation {
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite alternate;
}

@keyframes pulse-glow {
    from { box-shadow: 0 0 20px rgba(59, 130, 246, 0.3); }
    to { box-shadow: 0 0 30px rgba(59, 130, 246, 0.6); }
}

.slide-up-enter {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-up-enter.visible {
    opacity: 1;
    transform: translateY(0);
}

.stats-counter {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
}

.dark .stats-counter {
    background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
}

.stats-counter::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 10s linear infinite;
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="min-h-screen">
    <!-- Hero Section Moderne -->
    <section class="hero-gradient min-h-screen flex items-center relative">
        <div class="container mx-auto px-4 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Contenu texte -->
                <div class="text-white">
                    <h1 class="text-5xl lg:text-6xl font-bold mb-6 leading-tight">
                        <span class="block">Bibliothèque</span>
                        <span class="block bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">
                            Chrétienne
                        </span>
                    </h1>
                    <p class="text-xl lg:text-2xl mb-8 opacity-90 leading-relaxed">
                        Découvrez une collection exceptionnelle de livres spirituels pour nourrir votre foi et enrichir votre parcours spirituel.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="library.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold text-lg inline-flex items-center justify-center">
                            <i class="fas fa-book-open mr-3"></i> Explorer la bibliothèque
                        </a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-8 py-4 rounded-2xl font-bold text-lg inline-flex items-center justify-center transition-all duration-300 border border-white/30">
                                <i class="fas fa-user-plus mr-3"></i> Rejoindre gratuitement
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistiques flottantes -->
                <div class="lg:block hidden">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_books']; ?>"><?php echo $stats['total_books']; ?>+</div>
                            <div class="text-white/80 relative z-10">Livres disponibles</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -2s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_users']; ?>"><?php echo $stats['total_users']; ?>+</div>
                            <div class="text-white/80 relative z-10">Lecteurs inscrits</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -4s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_categories']; ?>"><?php echo $stats['total_categories']; ?>+</div>
                            <div class="text-white/80 relative z-10">Catégories</div>
                        </div>
                        <div class="stats-counter p-6 rounded-3xl text-center floating-animation" style="animation-delay: -6s;">
                            <div class="text-4xl font-bold mb-2 relative z-10" data-target="<?php echo $stats['total_views']; ?>"><?php echo number_format($stats['total_views']); ?>+</div>
                            <div class="text-white/80 relative z-10">Lectures totales</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 text-white animate-bounce">
            <i class="fas fa-chevron-down text-2xl opacity-60"></i>
        </div>
    </section>

    <!-- Livres en vedette -->
    <section class="py-20 bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
            
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Découvrez notre sélection de livres spirituels soigneusement choisis pour enrichir votre parcours de foi
                </p>
            </div>
            
            <?php if (!empty($featuredBooks)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($featuredBooks as $index => $book): ?>
                        <div class="glass-card rounded-3xl overflow-hidden modern-card slide-up-enter" style="transition-delay: <?php echo $index * 0.1; ?>s">
                            <a href="book.php?id=<?php echo $book['id']; ?>" class="block">
                                <div class="relative overflow-hidden">
                                    <?php if (!empty($book['cover_image'])): ?>
                                        <?php
                                        $featured_image_path = $book['cover_image'];
                                        if (strpos($featured_image_path, '../') === 0) {
                                            $featured_image_path = substr($featured_image_path, 3);
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($featured_image_path); ?>" 
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
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <span class="bg-blue-500/80 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm">
                                                <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                                            </span>
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
                                    <div class="flex items-center justify-between">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">En savoir plus</span>
                                        <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 transform transition-transform group-hover:translate-x-2"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-book text-6xl text-gray-400 mb-4"></i>
                    <p class="text-xl text-gray-500 dark:text-gray-400">Aucun livre en vedette pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Catégories modernes -->
    <section class="py-20 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gray-800 dark:text-white">
                    Explorez par <span class="bg-gradient-to-r from-green-500 to-blue-500 bg-clip-text text-transparent">catégorie</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Trouvez facilement les livres qui correspondent à vos centres d'intérêt spirituels
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($categories as $index => $category): ?>
                    <a href="category.php?id=<?php echo $category['id']; ?>" 
                       class="category-card rounded-2xl p-8 text-center hover:scale-105 transition-all duration-300 slide-up-enter" 
                       style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-6 text-white">
                            <i class="fas fa-tag text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-3 text-gray-800 dark:text-white">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php echo $category['book_count']; ?> livre<?php echo $category['book_count'] > 1 ? 's' : ''; ?> disponible<?php echo $category['book_count'] > 1 ? 's' : ''; ?>
                        </p>
                        <div class="inline-flex items-center text-blue-600 dark:text-blue-400 font-medium">
                            Explorer <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="library.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold inline-flex items-center">
                    <i class="fas fa-th-large mr-3"></i> Voir toutes les catégories
                </a>
            </div>
        </div>
    </section>

    <!-- Témoignages modernes -->
    <?php if (!empty($testimonials)): ?>
    <section class="py-20 bg-gradient-to-br from-blue-50 to-purple-50 dark:from-gray-800 dark:to-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16 slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gray-800 dark:text-white">
                    Ce que disent nos <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">lecteurs</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                    Découvrez les témoignages authentiques de notre communauté de lecteurs passionnés
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                    <div class="testimonial-modern rounded-2xl p-8 hover:scale-105 transition-all duration-300 slide-up-enter" 
                         style="transition-delay: <?php echo $index * 0.1; ?>s">
                        <!-- Étoiles -->
                        <div class="flex justify-center mb-6">
                            <div class="text-yellow-400 text-xl">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $testimonial['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Citation -->
                        <div class="relative mb-8">
                            <i class="fas fa-quote-left text-4xl text-blue-500/20 absolute -top-2 -left-2"></i>
                            <p class="text-gray-700 dark:text-gray-300 text-lg leading-relaxed italic pl-8">
                                <?php echo htmlspecialchars($testimonial['comment_text']); ?>
                            </p>
                        </div>
                        
                        <!-- Auteur -->
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-gray-800 dark:text-white text-lg">
                                    <?php echo htmlspecialchars($testimonial['username']); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    À propos de "<?php echo htmlspecialchars($testimonial['book_title']); ?>"
                                </div>
                            </div>
                            <a href="book.php?id=<?php echo $testimonial['book_id']; ?>" 
                               class="w-12 h-12 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center transition-colors">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="testimonials.php" class="btn-modern text-white px-8 py-4 rounded-2xl font-bold inline-flex items-center">
                    <i class="fas fa-comments mr-3"></i> Voir tous les témoignages
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Final -->
    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="py-20 bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="max-w-4xl mx-auto slide-up-enter">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6">
                    Prêt à commencer votre <span class="text-yellow-400">voyage spirituel</span> ?
                </h2>
                <p class="text-xl lg:text-2xl mb-12 opacity-90 leading-relaxed">
                    Rejoignez des milliers de lecteurs qui ont déjà transformé leur vie grâce à notre bibliothèque spirituelle
                </p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <a href="register.php" class="bg-white hover:bg-gray-100 text-blue-600 px-10 py-5 rounded-2xl font-bold text-xl transition-all duration-300 hover:scale-105 pulse-glow">
                        <i class="fas fa-rocket mr-3"></i> Commencer gratuitement
                    </a>
                    <a href="library.php" class="border-2 border-white/30 hover:border-white text-white px-10 py-5 rounded-2xl font-bold text-xl transition-all duration-300 hover:bg-white/10">
                        <i class="fas fa-eye mr-3"></i> Parcourir d'abord
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
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
    document.querySelectorAll('.slide-up-enter').forEach(element => {
        observer.observe(element);
    });

    // Animation des compteurs (stats) avec vraies données
    const animateCounters = () => {
        const counters = document.querySelectorAll('[data-target]');
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            let current = 0;
            const increment = target / 50;
            
            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    if (target > 1000) {
                        counter.textContent = Math.floor(current).toLocaleString() + '+';
                    } else {
                        counter.textContent = Math.floor(current) + '+';
                    }
                    setTimeout(updateCounter, 30);
                } else {
                    if (target > 1000) {
                        counter.textContent = target.toLocaleString() + '+';
                    } else {
                        counter.textContent = target + '+';
                    }
                }
            };
            
            updateCounter();
        });
    };

    // Démarrer l'animation des compteurs après un délai
    setTimeout(animateCounters, 1000);
});
</script>

<?php include "includes/footer.php"; ?>