<?php
// book.php - Page de détail d'un livre ULTRA MODERNE
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

// Désactiver temporairement le mode strict pour GROUP BY
$conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Vérifier si l'ID du livre est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: library.php");
    exit();
}

$book_id = intval($_GET['id']);

// Récupérer les détails du livre avec statistiques
$stmt = $conn->prepare("SELECT b.*, c.name as category_name,
                               COALESCE(AVG(comm.rating), 0) as avg_rating,
                               COUNT(comm.id) as total_reviews
                        FROM books b 
                        LEFT JOIN book_categories bc ON b.id = bc.book_id 
                        LEFT JOIN categories c ON bc.category_id = c.id 
                        LEFT JOIN comments comm ON b.id = comm.book_id AND comm.is_validated = 1
                        WHERE b.id = :book_id 
                        GROUP BY b.id");
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();

// Vérifier si le livre existe
if ($stmt->rowCount() == 0) {
    header("Location: library.php");
    exit();
}

$book = $stmt->fetch(PDO::FETCH_ASSOC);

// Variables pour les messages
$success_message = "";
$error_message = "";
$comment_success = "";
$comment_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    if (isset($_POST['action']) && $_POST['action'] == 'add_to_library' && isset($_POST['book_id'])) {
        $book_id_to_add = (int)$_POST['book_id'];
        
        try {
            // Vérifier si le livre existe
            $stmt = $conn->prepare("SELECT id FROM books WHERE id = :book_id");
            $stmt->bindParam(':book_id', $book_id_to_add, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Ajouter le livre à la bibliothèque de l'utilisateur si ce n'est pas déjà fait
                $stmt = $conn->prepare("INSERT IGNORE INTO user_library (user_id, book_id, last_page_read, added_at) 
                                      VALUES (:user_id, :book_id, 1, NOW())");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id_to_add, PDO::PARAM_INT);
                $stmt->execute();
                
                $success_message = "Le livre a été ajouté à votre bibliothèque avec succès.";
            } else {
                $error_message = "Le livre spécifié n'existe pas.";
            }
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue lors de l'ajout du livre à votre bibliothèque.";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_favorite' && isset($_POST['book_id'])) {
        $book_id_to_toggle = (int)$_POST['book_id'];
        
        try {
            // Récupérer l'état actuel du livre (favori ou non)
            $stmt = $conn->prepare("SELECT is_favorite FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $book_id_to_toggle, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $is_favorite = $stmt->fetchColumn();
                $new_status = $is_favorite ? 0 : 1;
                
                // Mettre à jour le statut de favori
                $stmt = $conn->prepare("UPDATE user_library SET is_favorite = :is_favorite WHERE user_id = :user_id AND book_id = :book_id");
                $stmt->bindParam(':is_favorite', $new_status, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id_to_toggle, PDO::PARAM_INT);
                $stmt->execute();
                
                $success_message = $new_status 
                    ? "Le livre a été ajouté à vos favoris." 
                    : "Le livre a été retiré de vos favoris.";
            } else {
                $error_message = "Ce livre n'est pas dans votre bibliothèque.";
            }
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue.";
        }
    }
}

// Traitement du formulaire de commentaire
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    if (!isset($_SESSION['user_id'])) {
        $comment_error = "Vous devez être connecté pour laisser un commentaire.";
    } else {
        $comment_text = trim($_POST['comment_text']);
        $rating = intval($_POST['rating']);
        
        if (empty($comment_text)) {
            $comment_error = "Le commentaire ne peut pas être vide.";
        } elseif ($rating < 1 || $rating > 5) {
            $comment_error = "La note doit être comprise entre 1 et 5.";
        } else {
            // Vérifier si l'utilisateur a déjà commenté ce livre
            $stmt = $conn->prepare("SELECT * FROM comments WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            $user_comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_comment) {
                // Mettre à jour le commentaire existant (auto-validé car l'utilisateur a lu le livre)
                $stmt = $conn->prepare("UPDATE comments 
                                        SET comment_text = :comment_text, rating = :rating, is_validated = 1, updated_at = NOW() 
                                        WHERE id = :comment_id");
                $stmt->bindParam(':comment_text', $comment_text);
                $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
                $stmt->bindParam(':comment_id', $user_comment['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $comment_success = "Votre commentaire a été mis à jour et publié avec succès !";
                } else {
                    $comment_error = "Une erreur est survenue lors de la mise à jour du commentaire.";
                }
            } else {
                // Ajouter un nouveau commentaire (auto-validé car l'utilisateur a lu le livre)
                $stmt = $conn->prepare("INSERT INTO comments (user_id, book_id, comment_text, rating, is_validated, created_at) 
                                        VALUES (:user_id, :book_id, :comment_text, :rating, 1, NOW())");
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                $stmt->bindParam(':comment_text', $comment_text);
                $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $comment_success = "Votre commentaire a été publié avec succès ! Il est maintenant visible par tous les lecteurs.";
                } else {
                    $comment_error = "Une erreur est survenue lors de l'ajout du commentaire.";
                }
            }
        }
    }
}

// Incrémenter le compteur de vues
$update_views = $conn->prepare("UPDATE books SET views_count = views_count + 1 WHERE id = :book_id");
$update_views->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$update_views->execute();

// Récupérer les commentaires validés pour ce livre
$stmt = $conn->prepare("SELECT c.*, u.username 
                        FROM comments c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.book_id = :book_id AND c.is_validated = 1 
                        ORDER BY c.created_at DESC");
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si l'utilisateur a déjà commenté ce livre
$user_comment = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM comments WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_comment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Corriger le chemin de l'image
$image_path = '';
if (!empty($book['cover_image'])) {
    $image_path = $book['cover_image'];
    if (strpos($image_path, '../') === 0) {
        $image_path = substr($image_path, 3);
    }
}

$page_title = $book['title'];
include "includes/header.php";
?>

<style>
/* Styles modernes ultra-premium */
.hero-book {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.hero-book::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
    background-size: cover;
}

.dark .hero-book {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.dark .glass-card {
    background: rgba(31, 41, 55, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
}

.dark .glass-card:hover {
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
}

.book-cover {
    position: relative;
    overflow: hidden;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.book-cover:hover {
    transform: scale(1.05) rotateY(5deg);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
}

.floating-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border-radius: 50%;
    width: 80px;
    height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    box-shadow: 0 10px 25px rgba(251, 191, 36, 0.3);
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.btn-modern {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border: none;
    border-radius: 16px;
    padding: 16px 32px;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.btn-modern:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4);
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

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
    box-shadow: 0 15px 35px rgba(245, 158, 11, 0.4);
}

.star-rating {
    display: flex;
    gap: 4px;
}

.star-interactive {
    cursor: pointer;
    font-size: 2rem;
    color: #d1d5db;
    transition: all 0.2s ease;
}

.star-interactive:hover,
.star-interactive.active {
    color: #fbbf24;
    transform: scale(1.1);
}

/* Styles pour le scroll horizontal des commentaires */
.comment-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dark .comment-card {
    background: linear-gradient(135deg, rgba(55, 65, 81, 0.9) 0%, rgba(31, 41, 55, 0.9) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.comment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #3b82f6, transparent);
    transition: left 0.5s ease;
}

.comment-card:hover::before {
    left: 100%;
}

.comment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.dark .comment-card:hover {
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

/* Styles pour le scroll horizontal mobile */
@media (max-width: 1023px) {
    .comment-card {
        padding: 16px;
        margin-bottom: 0;
    }
    
    /* Scroll horizontal personnalisé */
    .overflow-x-auto {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-track {
        background: rgba(203, 213, 225, 0.3);
        border-radius: 10px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        border-radius: 10px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(90deg, #2563eb, #7c3aed);
    }
    
    .dark .overflow-x-auto::-webkit-scrollbar-track {
        background: rgba(75, 85, 99, 0.3);
    }
}

/* Utilitaire pour limiter les lignes (line-clamp) */
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.breadcrumb-modern {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 12px 24px;
    display: inline-flex;
    align-items: center;
    margin-bottom: 2rem;
    width: 100%;
    max-width: 100%;
}

.dark .breadcrumb-modern {
    background: rgba(31, 41, 55, 0.3);
}

.info-badge {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.dark .info-badge {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

.info-badge:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
}

.stat-item {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.stat-item:hover {
    border-color: #3b82f6;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
}

.form-modern {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 197, 253, 0.05) 100%);
    border-radius: 24px;
    padding: 32px;
    border: 2px solid rgba(59, 130, 246, 0.1);
}

.dark .form-modern {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
}

.textarea-modern {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(59, 130, 246, 0.1);
    border-radius: 16px;
    padding: 16px;
    transition: all 0.3s ease;
    resize: none;
}

.dark .textarea-modern {
    background: rgba(55, 65, 81, 0.9);
    border-color: rgba(156, 163, 175, 0.2);
    color: #f8fafc;
}

.textarea-modern:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    outline: none;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .hero-book {
        padding: 40px 0;
    }
    
    .book-cover {
        max-width: 280px;
        margin: 0 auto;
    }
    
    .floating-badge {
        width: 60px;
        height: 60px;
        top: -8px;
        right: -8px;
    }
    
    .floating-badge .text-lg {
        font-size: 14px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .stat-item {
        padding: 12px;
    }
    
    .btn-modern {
        padding: 12px 24px;
        font-size: 14px;
        width: 100%;
        margin-bottom: 12px;
    }
    
    .star-rating {
        justify-content: center;
    }
    
    .star-interactive {
        font-size: 1.5rem;
    }
    
    .glass-card {
        margin: 0 -8px;
        border-radius: 20px;
    }
    
    .comment-card {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .form-modern {
        padding: 20px;
    }
    
    .info-badge {
        padding: 12px;
    }
    
    .container {
        padding-left: 12px;
        padding-right: 12px;
    }
    
    h1 {
        font-size: 2rem !important;
        line-height: 2.2rem;
    }
    
    h2 {
        font-size: 1.5rem !important;
    }
    
    h3 {
        font-size: 1.25rem !important;
    }
    
    .breadcrumb-modern {
        padding: 8px 16px;
        font-size: 12px;
        border-radius: 16px;
        width: 100%;
        margin: 0 0 16px 0;
    }
    
    .breadcrumb-modern .fas {
        font-size: 10px;
    }
    
    .breadcrumb-modern .flex {
        width: 100%;
        min-width: 0;
    }
}
</style>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <!-- Hero Section avec détails du livre -->
    <section class="hero-book py-12 lg:py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <!-- Breadcrumb moderne responsive -->
            <nav class="breadcrumb-modern text-white/90 mb-6 lg:mb-8 max-w-full overflow-hidden">
                <div class="flex items-center text-xs lg:text-sm">
                    <a href="index.php" class="hover:text-white transition-colors flex items-center flex-shrink-0">
                        <i class="fas fa-home mr-1 lg:mr-2"></i>
                        <span class="hidden sm:inline">Accueil</span>
                    </a>
                    <i class="fas fa-chevron-right mx-2 lg:mx-3 text-white/60 flex-shrink-0"></i>
                    <a href="library.php" class="hover:text-white transition-colors flex-shrink-0">
                        <span class="hidden sm:inline">Bibliothèque</span>
                        <span class="sm:hidden">Biblio.</span>
                    </a>
                    <i class="fas fa-chevron-right mx-2 lg:mx-3 text-white/60 flex-shrink-0"></i>
                    <span class="text-white font-medium truncate min-w-0">
                        <?php echo htmlspecialchars($book['title']); ?>
                    </span>
                </div>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-center">
                <!-- Image et actions -->
                <div class="text-center order-2 lg:order-1">
                    <div class="book-cover relative inline-block mb-6 lg:mb-8">
                        <?php if (!empty($image_path)): ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                 class="w-full max-w-sm mx-auto object-cover"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-full max-w-sm h-80 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center mx-auto" style="display:none;">
                                <i class="fas fa-book text-6xl lg:text-8xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php else: ?>
                            <div class="w-full max-w-sm h-80 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center mx-auto">
                                <i class="fas fa-book text-6xl lg:text-8xl text-gray-400 dark:text-gray-500"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge de note flottant -->
                        <?php if ($book['avg_rating'] > 0): ?>
                            <div class="floating-badge">
                                <div class="text-sm lg:text-lg font-bold"><?php echo round($book['avg_rating'], 1); ?></div>
                                <div class="text-xs">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="stats-grid mb-6 lg:mb-8">
                        <div class="stat-item">
                            <div class="text-xl lg:text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="text-xs lg:text-sm text-white/80">
                                <?php echo number_format($book['views_count'] ?? 0); ?> vues
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="text-xl lg:text-2xl font-bold text-green-600 dark:text-green-400 mb-2">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="text-xs lg:text-sm text-white/80">
                                <?php echo $book['total_reviews']; ?> avis
                            </div>
                        </div>
                        <?php if (!empty($book['pages_count'])): ?>
                            <div class="stat-item lg:col-span-2">
                                <div class="text-xl lg:text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="text-xs lg:text-sm text-white/80">
                                    <?php echo $book['pages_count']; ?> pages
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations du livre -->
                <div class="text-white text-center lg:text-left order-1 lg:order-2">
                    <h1 class="text-3xl lg:text-4xl xl:text-6xl font-bold mb-4 lg:mb-6 leading-tight">
                        <?php echo htmlspecialchars($book['title']); ?>
                    </h1>
                    
                    <?php if (!empty($book['author'])): ?>
                        <p class="text-lg lg:text-xl xl:text-2xl mb-4 lg:mb-6 opacity-90">
                            par <span class="font-semibold text-yellow-300"><?php echo htmlspecialchars($book['author']); ?></span>
                        </p>
                    <?php endif; ?>

                    <!-- Badges -->
                    <div class="flex flex-wrap justify-center lg:justify-start gap-2 lg:gap-3 mb-6 lg:mb-8">
                        <span class="bg-white/20 backdrop-blur-sm rounded-full px-3 lg:px-4 py-1 lg:py-2 text-xs lg:text-sm font-semibold">
                            <i class="fas fa-tag mr-1 lg:mr-2"></i>
                            <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                        </span>
                        <?php if (!empty($book['publication_year'])): ?>
                            <span class="bg-white/20 backdrop-blur-sm rounded-full px-3 lg:px-4 py-1 lg:py-2 text-xs lg:text-sm font-semibold">
                                <i class="fas fa-calendar-alt mr-1 lg:mr-2"></i>
                                <?php echo $book['publication_year']; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Note avec étoiles -->
                    <?php if ($book['avg_rating'] > 0): ?>
                        <div class="flex items-center justify-center lg:justify-start mb-6 lg:mb-8">
                            <div class="star-rating mr-3 lg:mr-4">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $book['avg_rating']): ?>
                                        <i class="fas fa-star text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php elseif ($i - 0.5 <= $book['avg_rating']): ?>
                                        <i class="fas fa-star-half-alt text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-yellow-400 text-lg lg:text-2xl"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="text-lg lg:text-xl font-semibold">
                                <?php echo round($book['avg_rating'], 1); ?>/5 (<?php echo $book['total_reviews']; ?> avis)
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Boutons d'action -->
                    <div class="space-y-3 lg:space-y-4">
                        <a href="read.php?id=<?php echo $book['id']; ?>" class="btn-modern">
                            <i class="fas fa-book-reader mr-2 lg:mr-3 text-lg lg:text-xl"></i>
                            Lire maintenant
                        </a>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php
                            // Vérifier si le livre est dans la bibliothèque
                            $stmt = $conn->prepare("SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $in_library = $stmt->rowCount() > 0;
                            $is_favorite = $in_library ? $stmt->fetch(PDO::FETCH_ASSOC)['is_favorite'] : 0;
                            ?>
                            
                            <div class="space-y-3 lg:space-y-0 lg:space-x-4 lg:flex lg:flex-col xl:flex-row">
                                <?php if (!$in_library): ?>
                                    <form method="POST" action="book.php?id=<?php echo $book['id']; ?>" class="w-full">
                                        <input type="hidden" name="action" value="add_to_library">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="btn-modern btn-success">
                                            <i class="fas fa-plus mr-2 lg:mr-3"></i>
                                            Ajouter à ma bibliothèque
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="book.php?id=<?php echo $book['id']; ?>" class="w-full">
                                        <input type="hidden" name="action" value="toggle_favorite">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" class="btn-modern <?php echo $is_favorite ? 'btn-warning' : 'btn-success'; ?>">
                                            <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart mr-2 lg:mr-3"></i>
                                            <?php echo $is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-12">
        <!-- Messages d'alerte -->
        <?php if (!empty($success_message)): ?>
            <div class="glass-card rounded-2xl p-6 mb-8 border-l-4 border-green-500 slide-up">
                <div class="flex items-center text-green-700 dark:text-green-300">
                    <i class="fas fa-check-circle text-2xl mr-4"></i>
                    <p class="font-semibold text-lg"><?php echo $success_message; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="glass-card rounded-2xl p-6 mb-8 border-l-4 border-red-500 slide-up">
                <div class="flex items-center text-red-700 dark:text-red-300">
                    <i class="fas fa-exclamation-circle text-2xl mr-4"></i>
                    <p class="font-semibold text-lg"><?php echo $error_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section principale du contenu -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <!-- Contenu principal -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Description du livre -->
                <?php if (!empty($book['description'])): ?>
                    <div class="glass-card rounded-3xl p-8 slide-up">
                        <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                            <i class="fas fa-align-left text-blue-600 dark:text-blue-400 mr-3"></i>
                            À propos de ce livre
                        </h2>
                        <div class="prose prose-lg max-w-none text-gray-700 dark:text-gray-300 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($book['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section Commentaires -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-8 flex items-center">
                        <i class="fas fa-comments text-blue-600 dark:text-blue-400 mr-3"></i>
                        Avis des lecteurs
                        <?php if (count($comments) > 0): ?>
                            <span class="ml-3 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-lg px-3 py-1 rounded-full">
                                <?php echo count($comments); ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <!-- Messages pour les commentaires -->
                    <?php if (!empty($comment_success)): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 text-green-700 dark:text-green-300 px-6 py-4 rounded-r-xl mb-6 flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <?php echo $comment_success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($comment_error)): ?>
                        <div class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 border-l-4 border-red-500 text-red-700 dark:text-red-300 px-6 py-4 rounded-r-xl mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <?php echo $comment_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Affichage des commentaires -->
                    <?php if (!empty($comments)): ?>
                        <div class="mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                                    Derniers avis (<?php echo count($comments); ?>)
                                </h3>
                                <?php if (count($comments) > 3): ?>
                                    <div class="hidden lg:flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-hand-point-right mr-2"></i>
                                        Faites défiler pour voir plus
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Version mobile : scroll horizontal -->
                            <div class="lg:hidden">
                                <div class="flex space-x-4 overflow-x-auto pb-4 px-2" style="scrollbar-width: thin;">
                                    <?php foreach ($comments as $index => $comment): ?>
                                        <div class="comment-card flex-shrink-0 w-80 max-w-[90vw]" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                            <div class="flex items-start space-x-3">
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                                        <span class="text-white font-bold text-sm">
                                                            <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm truncate">
                                                            <?php echo htmlspecialchars($comment['username']); ?>
                                                        </h4>
                                                        <div class="flex items-center bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/30 rounded-full px-2 py-1 ml-2">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="<?php echo ($i <= $comment['rating']) ? 'fas' : 'far'; ?> fa-star text-yellow-400 text-xs"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center">
                                                        <i class="far fa-clock mr-1"></i>
                                                        <?php echo date('d/m/Y', strtotime($comment['created_at'])); ?>
                                                        <?php if ($comment['created_at'] != $comment['updated_at']): ?>
                                                            <span class="ml-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs px-1 py-0.5 rounded">
                                                                modifié
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed line-clamp-3">
                                                        <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Indicateur de scroll -->
                                <?php if (count($comments) > 1): ?>
                                    <div class="flex justify-center mt-4">
                                        <div class="flex items-center space-x-2 text-gray-400 dark:text-gray-500">
                                            <i class="fas fa-chevron-left text-xs"></i>
                                            <span class="text-xs">Glissez pour voir plus d'avis</span>
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Version desktop : grille verticale -->
                            <div class="hidden lg:block space-y-6">
                                <?php foreach ($comments as $index => $comment): ?>
                                    <div class="comment-card" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                                                    <span class="text-white font-bold text-lg">
                                                        <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div>
                                                        <h4 class="font-bold text-gray-800 dark:text-gray-200 text-lg">
                                                            <?php echo htmlspecialchars($comment['username']); ?>
                                                        </h4>
                                                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                            <i class="far fa-clock mr-1"></i>
                                                            <?php echo date('d/m/Y à H:i', strtotime($comment['created_at'])); ?>
                                                            <?php if ($comment['created_at'] != $comment['updated_at']): ?>
                                                                <span class="ml-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs px-2 py-0.5 rounded-full">
                                                                    modifié
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/30 dark:to-orange-900/30 rounded-full px-3 py-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="<?php echo ($i <= $comment['rating']) ? 'fas' : 'far'; ?> fa-star text-yellow-400 text-sm"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 mb-8">
                            <div class="bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-3xl p-12">
                                <div class="text-gray-400 dark:text-gray-500 text-8xl mb-6">
                                    <i class="far fa-comment-dots"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-600 dark:text-gray-400 mb-4">
                                    Aucun avis pour le moment
                                </h3>
                                <p class="text-gray-500 dark:text-gray-500 text-lg">
                                    Soyez le premier à partager votre opinion sur ce livre !
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulaire de commentaire -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        // Vérifier si l'utilisateur a commencé à lire le livre
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM reading_history WHERE user_id = :user_id AND book_id = :book_id");
                        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $has_read = $stmt->fetchColumn() > 0;
                        
                        if ($has_read):
                        ?>
                            <div class="form-modern">
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                                    <i class="fas fa-pen mr-3 text-blue-600 dark:text-blue-400"></i>
                                    <?php echo $user_comment ? 'Modifier votre avis' : 'Partager votre avis'; ?>
                                </h3>
                                
                                <form method="POST" action="book.php?id=<?php echo $book_id; ?>" id="comment-form" class="space-y-6">
                                    <!-- Système de notation par étoiles -->
                                    <div>
                                        <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-4 text-lg">
                                            Votre note
                                        </label>
                                        <div class="flex items-center space-x-2" id="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" id="rating_<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden" <?php echo ($user_comment && $user_comment['rating'] == $i) ? 'checked' : ''; ?> required>
                                                <label for="rating_<?php echo $i; ?>" class="star-interactive" data-rating="<?php echo $i; ?>">
                                                    <i class="fas fa-star"></i>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Cliquez sur les étoiles pour noter ce livre
                                        </p>
                                    </div>
                                    
                                    <!-- Zone de commentaire -->
                                    <div>
                                        <label for="comment_text" class="block text-gray-700 dark:text-gray-300 font-semibold mb-4 text-lg">
                                            Votre commentaire
                                        </label>
                                        <textarea id="comment_text" name="comment_text" rows="6" 
                                                class="textarea-modern w-full" 
                                                placeholder="Partagez votre expérience de lecture, ce que vous avez aimé ou moins aimé..."
                                                required><?php echo $user_comment ? htmlspecialchars($user_comment['comment_text']) : ''; ?></textarea>
                                        <div class="flex justify-between items-center mt-2">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-lightbulb mr-1"></i>
                                                Minimum 10 caractères
                                            </p>
                                            <div id="char-counter" class="text-sm text-gray-500 dark:text-gray-400"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Bouton de soumission -->
                                    <div class="flex justify-end pt-4">
                                        <button type="submit" name="submit_comment" class="btn-modern">
                                            <i class="fas fa-paper-plane mr-3"></i>
                                            <?php echo $user_comment ? 'Mettre à jour mon avis' : 'Publier mon avis'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Message pour commencer la lecture -->
                            <div class="form-modern text-center">
                                <div class="text-yellow-500 text-8xl mb-6 pulse-animation">
                                    <i class="fas fa-book-reader"></i>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                                    Commencez votre lecture pour donner votre avis
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg">
                                    Vous devez commencer à lire ce livre avant de pouvoir partager votre opinion et noter votre expérience de lecture.
                                </p>
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl p-6 mb-6">
                                    <div class="flex items-start text-left">
                                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-3 mt-1 text-xl"></i>
                                        <div class="text-gray-700 dark:text-gray-300">
                                            <p class="font-semibold mb-2">Pourquoi cette restriction ?</p>
                                            <ul class="text-sm space-y-1">
                                                <li>• Garantit des avis authentiques et éclairés</li>
                                                <li>• Évite les commentaires non fondés</li>
                                                <li>• Vos avis seront publiés immédiatement</li>
                                                <li>• Pas d'attente de validation pour les vrais lecteurs</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <a href="read.php?id=<?php echo $book_id; ?>" class="btn-modern">
                                    <i class="fas fa-book-open mr-3"></i>
                                    Commencer à lire maintenant
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="form-modern text-center">
                            <div class="text-blue-500 text-8xl mb-6">
                                <i class="fas fa-user-lock"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                                Connectez-vous pour partager votre avis
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg">
                                Rejoignez notre communauté de lecteurs et partagez vos impressions sur ce livre.
                            </p>
                            <div class="flex flex-col sm:flex-row justify-center gap-4">
                                <a href="login.php" class="btn-modern">
                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                    Se connecter
                                </a>
                                <a href="register.php" class="btn-modern btn-success">
                                    <i class="fas fa-user-plus mr-2"></i>
                                    S'inscrire
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar avec informations complémentaires -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Informations détaillées -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mr-3"></i>
                        Détails du livre
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if (!empty($book['author'])): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-user-edit text-blue-600 dark:text-blue-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Auteur</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($book['author']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($book['publisher'])): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-building text-green-600 dark:text-green-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Éditeur</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo htmlspecialchars($book['publisher']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($book['publication_year'])): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-purple-600 dark:text-purple-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Année de publication</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo $book['publication_year']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($book['pages_count'])): ?>
                            <div class="info-badge">
                                <div class="flex items-center">
                                    <i class="fas fa-file-alt text-orange-600 dark:text-orange-400 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Nombre de pages</div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            <?php echo number_format($book['pages_count']); ?> pages
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-badge">
                            <div class="flex items-center">
                                <i class="fas fa-plus text-blue-600 dark:text-blue-400 mr-3 text-xl"></i>
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Ajouté le</div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">
                                        <?php echo date('d/m/Y', strtotime($book['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques de lecture -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-chart-bar text-green-600 dark:text-green-400 mr-3"></i>
                        Statistiques
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                <?php echo number_format($book['views_count'] ?? 0); ?>
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Lectures totales</div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-4xl font-bold text-green-600 dark:text-green-400 mb-2">
                                <?php echo $book['total_reviews']; ?>
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Avis de lecteurs</div>
                        </div>
                        
                        <?php if ($book['avg_rating'] > 0): ?>
                            <div class="text-center">
                                <div class="text-4xl font-bold text-yellow-500 mb-2">
                                    <?php echo round($book['avg_rating'], 1); ?>/5
                                </div>
                                <div class="text-gray-600 dark:text-gray-400">Note moyenne</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-bolt text-yellow-600 dark:text-yellow-400 mr-3"></i>
                        Actions rapides
                    </h3>
                    
                    <div class="space-y-4">
                        <a href="read.php?id=<?php echo $book['id']; ?>" 
                           class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-book-reader mr-2"></i>
                            Commencer la lecture
                        </a>
                        
                        <button onclick="window.print()" 
                                class="w-full bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-print mr-2"></i>
                            Imprimer cette page
                        </button>
                        
                        <button onclick="shareBook()" 
                                class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg flex items-center justify-center">
                            <i class="fas fa-share-alt mr-2"></i>
                            Partager ce livre
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observer tous les éléments avec animation
    document.querySelectorAll('.slide-up').forEach(element => {
        observer.observe(element);
    });

    // Système de notation par étoiles interactif
    const starLabels = document.querySelectorAll('.star-interactive');
    if (starLabels.length > 0) {
        const setRating = (rating) => {
            starLabels.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                    star.style.color = '#fbbf24';
                } else {
                    star.classList.remove('active');
                    star.style.color = '#d1d5db';
                }
            });
        };
        
        starLabels.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                setRating(rating);
            });
            
            star.addEventListener('click', () => {
                const rating = parseInt(star.dataset.rating);
                document.getElementById(`rating_${rating}`).checked = true;
                
                // Animation au clic
                star.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    star.style.transform = 'scale(1.1)';
                }, 150);
            });
        });
        
        // Réinitialiser au survol du conteneur
        const starRating = document.getElementById('star-rating');
        starRating.addEventListener('mouseleave', () => {
            let selectedRating = 0;
            for (let i = 1; i <= 5; i++) {
                if (document.getElementById(`rating_${i}`).checked) {
                    selectedRating = i;
                    break;
                }
            }
            setRating(selectedRating);
        });
        
        // Initialiser l'affichage des étoiles
        let initialRating = 0;
        for (let i = 1; i <= 5; i++) {
            if (document.getElementById(`rating_${i}`).checked) {
                initialRating = i;
                break;
            }
        }
        setRating(initialRating);
    }

    // Compteur de caractères pour le textarea
    const textarea = document.getElementById('comment_text');
    const charCounter = document.getElementById('char-counter');
    
    if (textarea && charCounter) {
        const updateCounter = () => {
            const length = textarea.value.length;
            charCounter.textContent = `${length} caractères`;
            
            if (length < 10) {
                charCounter.style.color = '#ef4444';
                textarea.style.borderColor = '#ef4444';
            } else {
                charCounter.style.color = '#6b7280';
                textarea.style.borderColor = '#d1d5db';
            }
        };
        
        textarea.addEventListener('input', updateCounter);
        updateCounter(); // Initialiser
    }

    // Validation du formulaire
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const rating = document.querySelector('input[name="rating"]:checked');
            const commentText = document.getElementById('comment_text').value.trim();
            
            if (!rating) {
                e.preventDefault();
                showAlert('Veuillez sélectionner une note pour ce livre.', 'error');
                return false;
            }
            
            if (!commentText || commentText.length < 10) {
                e.preventDefault();
                showAlert('Votre commentaire doit contenir au moins 10 caractères.', 'error');
                return false;
            }
        });
    }

    // Animation des cartes de commentaires
    const commentCards = document.querySelectorAll('.comment-card');
    commentCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 150);
    });

    // Démarrer les animations
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 100);
        });
    }, 300);
});

// Fonction pour partager le livre
function shareBook() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($book['title']); ?>',
            text: 'Découvrez ce livre : <?php echo addslashes($book['title']); ?>',
            url: window.location.href,
        });
    } else {
        // Fallback: copier l'URL
        navigator.clipboard.writeText(window.location.href).then(() => {
            showAlert('Lien copié dans le presse-papiers !', 'success');
        });
    }
}

// Fonction pour afficher des alertes
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white font-semibold`;
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateX(100%)';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}
</script>

<?php include "includes/footer.php"; ?>