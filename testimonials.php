<?php
// testimonials.php - Page des témoignages et commentaires validés
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

// Paramètres de filtrage et pagination
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9; // Nombre de témoignages par page
$offset = ($page - 1) * $per_page;

// Construction de la requête SQL
$sql = "SELECT c.*, u.username, b.title as book_title, b.cover_image, b.id as book_id
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN books b ON c.book_id = b.id
        WHERE c.is_validated = 1";
$params = [];

// Filtres
if ($book_id > 0) {
    $sql .= " AND c.book_id = :book_id";
    $params[':book_id'] = $book_id;
}

if ($rating > 0) {
    $sql .= " AND c.rating = :rating";
    $params[':rating'] = $rating;
}

// Tri
switch ($sort) {
    case 'highest':
        $sql .= " ORDER BY c.rating DESC, c.created_at DESC";
        break;
    case 'lowest':
        $sql .= " ORDER BY c.rating ASC, c.created_at DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY c.created_at ASC";
        break;
    case 'recent':
    default:
        $sql .= " ORDER BY c.created_at DESC";
        break;
}

// Ajouter la limite pour la pagination
$sql .= " LIMIT :offset, :per_page";

// Exécuter la requête avec pagination
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total de témoignages pour la pagination
$count_sql = "SELECT COUNT(*) as total FROM comments c WHERE c.is_validated = 1";
if ($book_id > 0) {
    $count_sql .= " AND c.book_id = :book_id";
}
if ($rating > 0) {
    $count_sql .= " AND c.rating = :rating";
}

$stmt = $conn->prepare($count_sql);
if ($book_id > 0) {
    $stmt->bindValue(':book_id', $book_id);
}
if ($rating > 0) {
    $stmt->bindValue(':rating', $rating);
}
$stmt->execute();
$total_testimonials = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_testimonials / $per_page);

// Récupérer la liste des livres pour le filtre
$stmt = $conn->prepare("SELECT DISTINCT b.id, b.title
                        FROM books b
                        JOIN comments c ON b.id = c.book_id
                        WHERE c.is_validated = 1
                        ORDER BY b.title");
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM comments WHERE is_validated = 1");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$avg_rating = (!is_null($stats['avg_rating'])) ? round(floatval($stats['avg_rating']), 1) : 0;

// Répartition des notes
$ratings_distribution = [];
for ($i = 5; $i >= 1; $i--) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE rating = :rating AND is_validated = 1");
    $stmt->bindValue(':rating', $i);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $percentage = $stats['total_ratings'] > 0 ? round(($count / $stats['total_ratings']) * 100) : 0;
    $ratings_distribution[$i] = [
        'count' => $count,
        'percentage' => $percentage
    ];
}

$page_title = "Témoignages";
include "includes/header.php";
?>

<style>
/* Styles pour le mode clair/sombre */
:root {
  --primary-light: #2563eb;
  --primary-dark: #1d4ed8;
  --secondary-light: #dbeafe;
  --secondary-dark: #1e3a8a;
  --bg-light: #f9fafb;
  --bg-dark: #111827;
  --card-light: #ffffff;
  --card-dark: #1f2937;
  --card-alt-light: #f9fafb;
  --card-alt-dark: #374151;
  --text-light: #374151;
  --text-dark: #e5e7eb;
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
body, .bg-white, .bg-gray-50, input, select, textarea, button, a, .testimonial-card {
  transition: all 0.3s ease;
}

/* Adaptations du mode sombre */
.dark .bg-white {
  background-color: var(--card-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark .bg-gray-50 {
  background-color: var(--card-alt-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark .bg-blue-50 {
  background-color: rgba(30, 58, 138, 0.3);
  border-color: rgba(59, 130, 246, 0.5);
}

.dark .text-blue-800 {
  color: #60a5fa;
}

.dark .text-gray-700 {
  color: var(--text-dark);
}

.dark .text-gray-600 {
  color: #9ca3af;
}

.dark .text-gray-400 {
  color: #6b7280;
}

.dark .border-gray-300 {
  border-color: #374151;
}

.dark .border-blue-100, .dark .border-blue-200 {
  border-color: rgba(59, 130, 246, 0.3);
}

/* Style pour la barre de progression */
.dark .bg-gray-200 {
  background-color: #374151;
}

.dark .bg-blue-600 {
  background-color: #3b82f6;
}

/* Style pour les cases actives de pagination */
.dark .bg-blue-50 {
  background-color: rgba(59, 130, 246, 0.2);
}

.dark .text-blue-600 {
  color: #60a5fa;
}

/* Désactiver les éléments de pagination */
.dark .text-gray-300 {
  color: #4b5563;
}

/* Style amélioré des témoignages */
.testimonial-card {
  transition: transform 0.2s ease, box-shadow 0.3s ease, background-color 0.3s ease;
  overflow: hidden;
}

.testimonial-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-hover-light);
}

.dark .testimonial-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Formulaires */
.dark input, .dark select, .dark textarea {
  background-color: var(--card-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark input:focus, .dark select:focus, .dark textarea:focus {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
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

/* Pagination hover */
.dark .hover\:bg-gray-50:hover {
  background-color: #374151;
}

/* Badges */
.dark .bg-blue-50.border-t.border-blue-100 {
  background-color: rgba(30, 58, 138, 0.3);
  border-color: rgba(59, 130, 246, 0.5);
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white flex items-center">
        <i class="fas fa-comment-dots mr-3 text-blue-600 dark:text-blue-500"></i>
        Témoignages des lecteurs
    </h1>
    
    <!-- Statistiques globales -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <div class="md:flex justify-between items-center">
            <div class="md:w-1/3 mb-4 md:mb-0">
                <div class="text-center">
                    <div class="text-5xl font-bold text-blue-600 dark:text-blue-500 mb-2"><?php echo $avg_rating; ?>/5</div>
                    <div class="text-yellow-400 text-xl mb-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $avg_rating): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="text-gray-600 dark:text-gray-400"><?php echo $stats['total_ratings']; ?> avis</div>
                </div>
            </div>
            
            <div class="md:w-2/3 md:pl-6">
                <div class="space-y-2">
                    <?php foreach ($ratings_distribution as $rating => $data): ?>
                        <div class="flex items-center">
                            <div class="text-sm w-12 text-gray-600 dark:text-gray-400"><?php echo $rating; ?> étoile<?php echo $rating > 1 ? 's' : ''; ?></div>
                            <div class="flex-1 mx-2">
                                <div class="bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                    <div class="bg-blue-600 h-2.5 rounded-full dark:bg-blue-500" style="width: <?php echo $data['percentage']; ?>%"></div>
                                </div>
                            </div>
                            <div class="text-sm w-16 text-gray-600 dark:text-gray-400"><?php echo $data['count']; ?> (<?php echo $data['percentage']; ?>%)</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-8">
        <form action="testimonials.php" method="GET" class="md:flex items-center space-y-4 md:space-y-0 md:space-x-4">
            <div class="md:w-1/3">
                <label for="book_id" class="block text-gray-700 font-medium mb-1 dark:text-gray-300">
                    <i class="fas fa-book mr-1"></i> Livre
                </label>
                <select id="book_id" name="book_id" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                    <option value="0">Tous les livres</option>
                    <?php foreach ($books as $book): ?>
                        <option value="<?php echo $book['id']; ?>" <?php echo $book_id == $book['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($book['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:w-1/4">
                <label for="rating" class="block text-gray-700 font-medium mb-1 dark:text-gray-300">
                    <i class="fas fa-star mr-1"></i> Note
                </label>
                <select id="rating" name="rating" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                    <option value="0">Toutes les notes</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $rating == $i ? 'selected' : ''; ?>>
                            <?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="md:w-1/4">
                <label for="sort" class="block text-gray-700 font-medium mb-1 dark:text-gray-300">
                    <i class="fas fa-sort mr-1"></i> Trier par
                </label>
                <select id="sort" name="sort" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                    <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                    <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>Mieux notés</option>
                    <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>Moins bien notés</option>
                </select>
            </div>
            
            <div class="flex md:flex-col md:justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex-1 md:mt-auto transition-colors btn">
                    <i class="fas fa-filter mr-2"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
    
    <!-- Liste des témoignages -->
    <?php if (!empty($testimonials)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden testimonial-card">
                    <div class="flex items-center p-4 bg-gray-50 border-b dark:bg-gray-800 dark:border-gray-700">
                        <div>
                            <?php if (!empty($testimonial['cover_image'])): ?>
                                <img src="<?php echo $testimonial['cover_image']; ?>" alt="<?php echo htmlspecialchars($testimonial['book_title']); ?>" class="w-16 h-16 object-cover rounded shadow-sm">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gray-200 flex items-center justify-center rounded shadow-sm dark:bg-gray-700">
                                    <i class="fas fa-book text-2xl text-gray-400 dark:text-gray-500"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-bold">
                                <a href="book.php?id=<?php echo $testimonial['book_id']; ?>" class="text-blue-600 hover:text-blue-800 transition-colors dark:text-blue-400 dark:hover:text-blue-300">
                                    <?php echo htmlspecialchars($testimonial['book_title']); ?>
                                </a>
                            </h3>
                            <div class="text-yellow-400 text-sm">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $testimonial['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-5">
                        <p class="text-gray-700 mb-4 dark:text-gray-300"><?php echo nl2br(htmlspecialchars($testimonial['comment_text'])); ?></p>
                        
                        <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                            <div class="font-semibold flex items-center">
                                <i class="fas fa-user-circle mr-1"></i>
                                <?php echo htmlspecialchars($testimonial['username']); ?>
                            </div>
                            <div class="flex items-center">
                                <i class="far fa-calendar-alt mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($testimonial['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($testimonial['is_featured']): ?>
                        <div class="bg-blue-50 px-4 py-2 border-t border-blue-100 dark:bg-blue-900/30 dark:border-blue-800/50">
                            <span class="text-blue-800 text-sm font-semibold dark:text-blue-300 flex items-center">
                                <i class="fas fa-award mr-1"></i> Témoignage en vedette
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center">
                <div class="inline-flex rounded-md shadow-sm">
                    <?php if ($page > 1): ?>
                        <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            <i class="fas fa-chevron-left mr-1"></i> Précédent
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-l-md dark:bg-gray-800 dark:text-gray-600 dark:border-gray-600">
                            <i class="fas fa-chevron-left mr-1"></i> Précédent
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-gray-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-gray-600">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="testimonials.php?book_id=<?php echo $book_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Suivant <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 rounded-r-md dark:bg-gray-800 dark:text-gray-600 dark:border-gray-600">
                            Suivant <i class="fas fa-chevron-right ml-1"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-8 bg-white rounded-lg shadow-md dark:bg-gray-800">
            <div class="text-gray-400 text-6xl mb-4 dark:text-gray-600">
                <i class="fas fa-comments"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2 dark:text-gray-300">Aucun témoignage</h2>
            <p class="text-gray-600 mb-4 dark:text-gray-400">
                <?php if ($book_id > 0 || $rating > 0): ?>
                    Aucun témoignage ne correspond à vos critères de recherche.
                <?php else: ?>
                    Il n'y a pas encore de témoignages validés sur notre site.
                <?php endif; ?>
            </p>
            <?php if ($book_id > 0 || $rating > 0): ?>
                <a href="testimonials.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors btn">
                    <i class="fas fa-undo-alt mr-2"></i> Voir tous les témoignages
                </a>
            <?php else: ?>
                <a href="library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors btn">
                    <i class="fas fa-book-open mr-2"></i> Parcourir la bibliothèque
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-8 dark:bg-blue-900/20 dark:border-blue-800/50">
            <h2 class="text-xl font-bold text-blue-800 mb-4 dark:text-blue-300">Partagez votre expérience</h2>
            <p class="mb-4 dark:text-gray-300">Avez-vous lu un livre dans notre bibliothèque ? Partagez votre opinion et aidez les autres lecteurs à découvrir de nouveaux ouvrages.</p>
            <div class="flex justify-center">
                <a href="library.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold transition-colors btn">
                    <i class="fas fa-pen mr-2"></i> Laisser un commentaire
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes de témoignage
    const testimonialCards = document.querySelectorAll('.testimonial-card');
    testimonialCards.forEach((card, index) => {
        // Ajouter un léger délai progressif pour créer un effet de cascade
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php include "includes/footer.php"; ?>