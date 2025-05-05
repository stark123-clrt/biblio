<?php
// book.php - Page de détail d'un livre
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

// Récupérer les détails du livre
$stmt = $conn->prepare("SELECT b.*, c.name as category_name 
                        FROM books b 
                        LEFT JOIN book_categories bc ON b.id = bc.book_id 
                        LEFT JOIN categories c ON bc.category_id = c.id 
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
            $error_message = "Une erreur est survenue lors de l'ajout du livre à votre bibliothèque: " . $e->getMessage();
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
            $error_message = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}

// Afficher les messages de succès ou d'erreur provenant d'autres pages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// Traitement du formulaire d'ajout de commentaire
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_comment']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $comment_text = trim($_POST['comment_text']);
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    
    // Validation de base
    if (empty($comment_text) || $rating < 1 || $rating > 5) {
        $error_message = "Veuillez entrer un commentaire et sélectionner une note entre 1 et 5 étoiles.";
    } else {
        try {
            // Vérifier si l'utilisateur a lu le livre
            $stmt = $conn->prepare("SELECT COUNT(*) FROM reading_history 
                                  WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                $error_message = "Vous devez commencer à lire ce livre avant de pouvoir le commenter.";
            } else {
                // Vérifier si l'utilisateur a déjà commenté ce livre
                $stmt = $conn->prepare("SELECT id FROM comments WHERE user_id = :user_id AND book_id = :book_id");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Mise à jour du commentaire existant
                    $stmt = $conn->prepare("UPDATE comments SET comment_text = :comment_text, rating = :rating, updated_at = NOW() WHERE user_id = :user_id AND book_id = :book_id");
                    $success_message = "Votre commentaire a été mis à jour avec succès. Il sera visible après validation par un administrateur.";
                } else {
                    // Ajout d'un nouveau commentaire
                    $stmt = $conn->prepare("INSERT INTO comments (user_id, book_id, comment_text, rating, is_validated) VALUES (:user_id, :book_id, :comment_text, :rating, :is_validated)");
                    $is_validated = 0; // Par défaut, les commentaires doivent être validés par un admin
                    $stmt->bindParam(':is_validated', $is_validated, PDO::PARAM_INT);
                    $success_message = "Votre commentaire a été ajouté avec succès. Il sera visible après validation par un administrateur.";
                }
                
                $stmt->bindParam(':comment_text', $comment_text);
                $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                
                $stmt->execute();
            }
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue lors de l'enregistrement de votre commentaire: " . $e->getMessage();
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

// Traitement du formulaire de commentaire
$comment_error = "";
$comment_success = "";

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
            if ($user_comment) {
                // Mettre à jour le commentaire existant
                $stmt = $conn->prepare("UPDATE comments 
                                        SET comment_text = :comment_text, rating = :rating, is_validated = 0, updated_at = NOW() 
                                        WHERE id = :comment_id");
                $stmt->bindParam(':comment_text', $comment_text);
                $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
                $stmt->bindParam(':comment_id', $user_comment['id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $comment_success = "Votre commentaire a été mis à jour et est en attente de validation.";
                    // Mettre à jour la variable user_comment
                    $user_comment['comment_text'] = $comment_text;
                    $user_comment['rating'] = $rating;
                    $user_comment['is_validated'] = 0;
                } else {
                    $comment_error = "Une erreur est survenue lors de la mise à jour du commentaire.";
                }
            } else {
                // Ajouter un nouveau commentaire
                $stmt = $conn->prepare("INSERT INTO comments (user_id, book_id, comment_text, rating, created_at) 
                                        VALUES (:user_id, :book_id, :comment_text, :rating, NOW())");
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                $stmt->bindParam(':comment_text', $comment_text);
                $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $comment_success = "Votre commentaire a été ajouté et est en attente de validation.";
                    // Mettre à jour la variable user_comment
                    $stmt = $conn->prepare("SELECT * FROM comments WHERE user_id = :user_id AND book_id = :book_id");
                    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $user_comment = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $comment_error = "Une erreur est survenue lors de l'ajout du commentaire.";
                }
            }
        }
    }
}

$page_title = $book['title'];
include "includes/header.php";
?>

<style>
/* Styles pour le mode clair/sombre */
:root {
  --primary-blue: #2563eb;
  --primary-blue-dark: #1d4ed8;
  --primary-green: #10b981;
  --primary-green-dark: #059669;
  --primary-yellow: #f59e0b;
  --primary-yellow-dark: #d97706;
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
body, .bg-white, .bg-gray-50, .bg-gray-100, input, select, textarea, button, a, .book-card, .comment-card {
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

.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .text-blue-800 {
  color: #93c5fd;
}

.dark .border-gray-200,
.dark .border-gray-300 {
  border-color: var(--border-dark);
}

/* Fil d'Ariane */
.dark .text-blue-600.hover\:text-blue-800 {
  color: #60a5fa;
}

.dark .text-blue-600.hover\:text-blue-800:hover {
  color: #93c5fd;
}

.dark .text-gray-500 {
  color: #9ca3af;
}

/* Badges */
.dark .bg-blue-100 {
  background-color: rgba(37, 99, 235, 0.2);
}

.dark .text-blue-800 {
  color: #93c5fd;
}

.dark .bg-gray-100 {
  background-color: rgba(75, 85, 99, 0.2);
}

.dark .text-gray-800 {
  color: #d1d5db;
}

/* Boutons d'action */
.dark .bg-blue-600 {
  background-color: #2563eb;
}

.dark .hover\:bg-blue-700:hover {
  background-color: #1d4ed8;
}

.dark .bg-green-600 {
  background-color: #10b981;
}

.dark .hover\:bg-green-700:hover {
  background-color: #059669;
}

.dark .bg-yellow-600 {
  background-color: #f59e0b;
}

.dark .hover\:bg-yellow-700:hover {
  background-color: #d97706;
}

.dark .bg-gray-600 {
  background-color: #4b5563;
}

.dark .hover\:bg-gray-700:hover {
  background-color: #374151;
}

/* Messages */
.dark .bg-green-100 {
  background-color: rgba(16, 185, 129, 0.2);
  border-color: rgba(16, 185, 129, 0.5);
}

.dark .text-green-700 {
  color: #34d399;
}

.dark .bg-red-100 {
  background-color: rgba(239, 68, 68, 0.2);
  border-color: rgba(239, 68, 68, 0.5);
}

.dark .text-red-700 {
  color: #f87171;
}

.dark .bg-blue-50 {
  background-color: rgba(37, 99, 235, 0.1);
}

.dark .text-blue-700 {
  color: #60a5fa;
}

/* Commentaires */
.comment-card {
  transition: all 0.3s ease;
}

.comment-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-hover-light);
}

.dark .comment-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

.dark .text-yellow-500 {
  color: #f59e0b;
}

/* Formulaires */
.dark .focus\:ring-2 {
  --tw-ring-opacity: 0.3;
}

/* Animation stars */
@keyframes star-pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.2);
  }
  100% {
    transform: scale(1);
  }
}

.star-animate {
  animation: star-pulse 0.3s ease-out;
}

/* Custom focus styles */
.dark *:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

/* Page entry animation */
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

.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
</style>

<div class="container mx-auto px-4 py-8">
    <nav class="text-sm mb-6 dark:text-gray-300">
        <ol class="list-none p-0 inline-flex">
            <li class="flex items-center">
                <a href="index.php" class="text-blue-600 hover:text-blue-800 transition-colors">Accueil</a>
                <svg class="w-3 h-3 fill-current mx-2 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
            </li>
            <li class="flex items-center">
                <a href="library.php" class="text-blue-600 hover:text-blue-800 transition-colors">Bibliothèque</a>
                <svg class="w-3 h-3 fill-current mx-2 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
            </li>
            <li class="text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($book['title']); ?></li>
        </ol>
    </nav>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden animate-fadeInUp dark:bg-gray-800 dark:text-white">
        <div class="md:flex">
            <div class="md:w-1/3 p-6 flex flex-col items-center">
                <?php if (!empty($book['cover_image'])): ?>
                    <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full rounded-lg shadow-md max-w-xs">
                <?php else: ?>
                    <div class="w-full h-96 bg-gray-200 flex items-center justify-center rounded-lg shadow-md dark:bg-gray-700">
                        <i class="fas fa-book text-6xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 space-y-4 w-full">
                    <a href="read.php?id=<?php echo $book['id']; ?>" class="block bg-blue-600 hover:bg-blue-700 text-white text-center font-bold py-3 px-4 rounded-lg transition-colors">
                        <i class="fas fa-book-reader mr-2"></i> Lire maintenant
                    </a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        // Vérifier si le livre est dans la bibliothèque de l'utilisateur
                        $stmt = $conn->prepare("SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
                        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $in_library = $stmt->rowCount() > 0;
                        $is_favorite = $in_library ? $stmt->fetch(PDO::FETCH_ASSOC)['is_favorite'] : 0;
                        ?>
                        
                        <?php if (!$in_library): ?>
                            <form method="POST" action="book.php?id=<?php echo $book['id']; ?>">
                                <input type="hidden" name="action" value="add_to_library">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-bold inline-flex items-center justify-center transition-colors">
                                    <i class="fas fa-plus mr-2"></i> Ajouter à ma bibliothèque
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="book.php?id=<?php echo $book['id']; ?>">
                                <input type="hidden" name="action" value="toggle_favorite">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" class="w-full bg-<?php echo $is_favorite ? 'yellow' : 'gray'; ?>-600 hover:bg-<?php echo $is_favorite ? 'yellow' : 'gray'; ?>-700 text-white px-4 py-3 rounded-lg font-bold inline-flex items-center justify-center transition-colors">
                                    <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-star mr-2"></i> <?php echo $is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="md:w-2/3 p-6 animate-fadeInUp delay-100 md:border-l dark:border-gray-700">
                <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($book['title']); ?></h1>
                
                <?php if (!empty($book['author'])): ?>
                    <p class="text-xl text-gray-700 mb-4 dark:text-gray-300">par <span class="font-semibold"><?php echo htmlspecialchars($book['author']); ?></span></p>
                <?php endif; ?>
                
                <div class="mb-6 flex flex-wrap gap-2">
                    <span class="inline-block bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                        <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                    </span>
                    
                    <?php if (!empty($book['publication_year'])): ?>
                        <span class="inline-block bg-gray-100 text-gray-800 text-sm font-semibold px-3 py-1 rounded-full dark:bg-gray-700 dark:text-gray-300">
                            <i class="fas fa-calendar-alt mr-1"></i> Publié en <?php echo $book['publication_year']; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($book['description'])): ?>
                    <div class="mb-6 animate-fadeInUp delay-200">
                        <h2 class="text-xl font-bold mb-2 flex items-center dark:text-gray-200">
                            <i class="fas fa-align-left text-blue-600 dark:text-blue-500 mr-2"></i> Description
                        </h2>
                        <div class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($book['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="border-t border-gray-200 pt-6 mb-6 dark:border-gray-700 animate-fadeInUp delay-300">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center">
                            <i class="fas fa-eye text-blue-600 dark:text-blue-500 mr-2"></i>
                            <span class="text-gray-700 mr-1 dark:text-gray-300">Vues:</span>
                            <span class="font-semibold"><?php echo $book['views_count']; ?></span>
                        </div>
                        
                        <?php if (!empty($book['pages_count'])): ?>
                            <div class="flex items-center">
                                <i class="fas fa-file-alt text-blue-600 dark:text-blue-500 mr-2"></i>
                                <span class="text-gray-700 mr-1 dark:text-gray-300">Pages:</span>
                                <span class="font-semibold"><?php echo $book['pages_count']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($book['publisher'])): ?>
                            <div class="flex items-center">
                                <i class="fas fa-building text-blue-600 dark:text-blue-500 mr-2"></i>
                                <span class="text-gray-700 mr-1 dark:text-gray-300">Éditeur:</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($book['publisher']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Section Commentaires -->
                <div class="mt-10 animate-fadeInUp delay-300">
                    <h2 class="text-2xl font-bold mb-4 flex items-center">
                        <i class="fas fa-comments text-blue-600 dark:text-blue-500 mr-2"></i> Commentaires et évaluations
                    </h2>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-4 flex items-center dark:bg-green-900/20 dark:border-green-500 dark:text-green-400">
                            <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 flex items-center dark:bg-red-900/20 dark:border-red-500 dark:text-red-400">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Récupérer les commentaires existants
                    try {
                        $stmt = $conn->prepare("SELECT c.*, u.username 
                                            FROM comments c
                                            JOIN users u ON c.user_id = u.id
                                            WHERE c.book_id = :book_id AND c.is_validated = 1
                                            ORDER BY c.created_at DESC");
                        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 flex items-center dark:bg-red-900/20 dark:border-red-500 dark:text-red-400">
                                <i class="fas fa-exclamation-circle mr-2"></i> Erreur lors de la récupération des commentaires: ' . $e->getMessage() . '
                            </div>';
                        $comments = [];
                    }
                    ?>
                    
                    <!-- Afficher les commentaires existants -->
                    <?php if (!empty($comments)): ?>
                        <div class="space-y-4 mb-8">
                            <?php foreach ($comments as $index => $comment): ?>
                                <div class="bg-gray-50 p-5 rounded-lg shadow-sm hover:shadow-md transition-shadow comment-card animate-fadeInUp delay-<?php echo ($index % 3 + 1) * 100; ?> dark:bg-gray-700">
                                    <div class="flex justify-between mb-2">
                                        <div class="font-bold flex items-center text-gray-800 dark:text-gray-200">
                                            <i class="fas fa-user-circle mr-2 text-blue-600 dark:text-blue-500"></i>
                                            <?php echo htmlspecialchars($comment['username']); ?>
                                        </div>
                                        <div class="text-yellow-500">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo '<i class="' . ($i <= $comment['rating'] ? 'fas' : 'far') . ' fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 dark:text-gray-300"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                    <div class="text-gray-500 text-sm mt-3 flex items-center dark:text-gray-400">
                                        <i class="far fa-clock mr-1"></i> <?php echo date('d/m/Y à H:i', strtotime($comment['created_at'])); ?>
                                        <?php if ($comment['created_at'] != $comment['updated_at']): ?>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full dark:bg-blue-900/30 dark:text-blue-300">modifié</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-50 p-6 rounded-lg mb-6 text-center shadow-sm dark:bg-gray-700">
                            <div class="text-gray-400 text-5xl mb-3 dark:text-gray-500">
                                <i class="far fa-comment-dots"></i>
                            </div>
                            <p class="text-gray-700 mb-0 dark:text-gray-300">Aucun commentaire pour le moment. Soyez le premier à donner votre avis !</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Vérifier si l'utilisateur est connecté
                    if (isset($_SESSION['user_id'])):
                        
                        // Vérifier si l'utilisateur a commencé à lire le livre
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM reading_history 
                                                WHERE user_id = :user_id AND book_id = :book_id");
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $has_read = $stmt->fetchColumn() > 0;
                        } catch (PDOException $e) {
                            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 flex items-center dark:bg-red-900/20 dark:border-red-500 dark:text-red-400">
                                    <i class="fas fa-exclamation-circle mr-2"></i> Erreur lors de la vérification de l\'historique de lecture: ' . $e->getMessage() . '
                                </div>';
                            $has_read = false;
                        }
                        
                        if ($has_read):
                    ?>
                            <div class="bg-white p-6 rounded-lg shadow-md mt-6 dark:bg-gray-800 animate-fadeInUp">
                                <h3 class="text-xl font-bold mb-4 flex items-center dark:text-white">
                                    <i class="fas fa-pen mr-2 text-blue-600 dark:text-blue-500"></i> Ajouter un commentaire
                                </h3>
                                
                                <form method="POST" action="book.php?id=<?php echo $book_id; ?>" id="comment-form">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 font-bold mb-2 dark:text-gray-300">Votre note</label>
                                        <div class="flex items-center space-x-1" id="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" id="rating_<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden peer" <?php echo ($user_comment && $user_comment['rating'] == $i) ? 'checked' : ''; ?> required>
                                                <label for="rating_<?php echo $i; ?>" class="cursor-pointer text-2xl text-gray-300 peer-checked:text-yellow-500 hover:text-yellow-500 dark:text-gray-600 dark:hover:text-yellow-500 dark:peer-checked:text-yellow-500 transition-colors star-label" data-rating="<?php echo $i; ?>">
                                                    <i class="fas fa-star"></i>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="comment_text" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">Votre commentaire</label>
                                        <textarea id="comment_text" name="comment_text" rows="5" 
                                                class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required><?php echo $user_comment ? htmlspecialchars($user_comment['comment_text']) : ''; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="add_comment" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition-colors">
                                        <i class="fas fa-paper-plane mr-2"></i> Soumettre
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-blue-50 p-6 rounded-lg mt-6 text-center dark:bg-blue-900/20">
                                <p class="text-blue-700 mb-3 dark:text-blue-300"><i class="fas fa-info-circle mr-2"></i> Vous devez commencer à lire ce livre avant de pouvoir le commenter.</p>
                                <a href="read.php?id=<?php echo $book_id; ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition-colors">
                                    <i class="fas fa-book-reader mr-2"></i> Commencer à lire
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-blue-50 p-6 rounded-lg mt-6 text-center dark:bg-blue-900/20">
                            <p class="text-blue-700 mb-3 dark:text-blue-300"><i class="fas fa-lock mr-2"></i> Connectez-vous pour laisser un commentaire.</p>
                            <a href="login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation pour les étoiles de notation
    const starLabels = document.querySelectorAll('.star-label');
    if (starLabels.length > 0) {
        const starRating = document.getElementById('star-rating');
        
        // Fonction pour définir les étoiles
        const setRating = (rating) => {
            starLabels.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('text-yellow-500');
                    star.classList.remove('text-gray-300', 'dark:text-gray-600');
                } else {
                    star.classList.remove('text-yellow-500');
                    star.classList.add('text-gray-300', 'dark:text-gray-600');
                }
            });
        };
        
        // Événements pour le survol des étoiles
        starLabels.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                setRating(rating);
            });
            
            star.addEventListener('click', () => {
                const rating = parseInt(star.dataset.rating);
                document.getElementById(`rating_${rating}`).checked = true;
                
                // Animation au clic
                star.classList.add('star-animate');
                setTimeout(() => {
                    star.classList.remove('star-animate');
                }, 300);
            });
        });
        
        // Réinitialiser au survol du conteneur
        starRating.addEventListener('mouseleave', () => {
            // Trouver le rating sélectionné
            let selectedRating = 0;
            for (let i = 1; i <= 5; i++) {
                if (document.getElementById(`rating_${i}`).checked) {
                    selectedRating = i;
                    break;
                }
            }
            setRating(selectedRating);
        });
    }
    
    // Animation d'entrée pour les cartes
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
    
    // Gestion du formulaire de commentaire
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const rating = document.querySelector('input[name="rating"]:checked');
            const commentText = document.getElementById('comment_text').value.trim();
            
            if (!rating) {
                e.preventDefault();
                alert('Veuillez sélectionner une note pour ce livre.');
                return false;
            }
            
            if (!commentText) {
                e.preventDefault();
                alert('Veuillez entrer un commentaire.');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>