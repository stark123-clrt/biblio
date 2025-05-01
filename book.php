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



// Traitement de l'ajout à la bibliothèque ou de la mise en favori
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

<div class="container mx-auto px-4 py-8">
    <nav class="text-sm mb-6">
        <ol class="list-none p-0 inline-flex">
            <li class="flex items-center">
                <a href="index.php" class="text-blue-600 hover:text-blue-800">Accueil</a>
                <svg class="w-3 h-3 fill-current mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
            </li>
            <li class="flex items-center">
                <a href="library.php" class="text-blue-600 hover:text-blue-800">Bibliothèque</a>
                <svg class="w-3 h-3 fill-current mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
            </li>
            <li class="text-gray-500"><?php echo htmlspecialchars($book['title']); ?></li>
        </ol>
    </nav>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="md:flex">
            <div class="md:w-1/3 p-4">
                <?php if (!empty($book['cover_image'])): ?>
                    <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-full rounded-lg shadow-md">
                <?php else: ?>
                    <div class="w-full h-96 bg-gray-200 flex items-center justify-center rounded-lg shadow-md">
                        <i class="fas fa-book text-6xl text-gray-400"></i>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 space-y-4">
                    <a href="read.php?id=<?php echo $book['id']; ?>" class="block bg-blue-600 hover:bg-blue-700 text-white text-center font-bold py-3 px-4 rounded-lg">
                        <i class="fas fa-book-reader mr-2"></i> Lire maintenant
                    </a>
                    
                    <!-- Remplacer ce code dans book.php -->

<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    // Vérifier si le livre est dans la bibliothèque de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    $in_library = $stmt->rowCount() > 0;
    $is_favorite = $stmt->fetch(PDO::FETCH_ASSOC)['is_favorite'] ?? 0;
    ?>
    
    <?php if (!$in_library): ?>
        <form method="POST" action="book.php?id=<?php echo $book['id']; ?>">
            <input type="hidden" name="action" value="add_to_library">
            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-bold inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Ajouter à ma bibliothèque
            </button>
        </form>
    <?php else: ?>
        <form method="POST" action="book.php?id=<?php echo $book['id']; ?>">
            <input type="hidden" name="action" value="toggle_favorite">
            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
            <button type="submit" class="bg-<?php echo $is_favorite ? 'yellow' : 'gray'; ?>-600 hover:bg-<?php echo $is_favorite ? 'yellow' : 'gray'; ?>-700 text-white px-6 py-3 rounded-lg font-bold inline-flex items-center">
                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-star mr-2"></i> <?php echo $is_favorite ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
                </div>
            </div>
            
            <div class="md:w-2/3 p-6">
                <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($book['title']); ?></h1>
                
                <?php if (!empty($book['author'])): ?>
                    <p class="text-xl text-gray-700 mb-4">par <span class="font-semibold"><?php echo htmlspecialchars($book['author']); ?></span></p>
                <?php endif; ?>
                
                <div class="mb-6">
                    <span class="inline-block bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full mr-2">
                        <?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?>
                    </span>
                    
                    <?php if (!empty($book['publication_year'])): ?>
                        <span class="inline-block bg-gray-100 text-gray-800 text-sm font-semibold px-3 py-1 rounded-full">
                            Publié en <?php echo $book['publication_year']; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($book['description'])): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-bold mb-2">Description</h2>
                        <div class="text-gray-700">
                            <?php echo nl2br(htmlspecialchars($book['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="border-t border-gray-200 pt-6 mb-6">
                    <div class="flex items-center mb-4">
                        <div class="flex items-center">
                            <span class="text-gray-700 mr-2">Vues:</span>
                            <span class="font-semibold"><?php echo $book['views_count']; ?></span>
                        </div>
                        
                        <?php if (!empty($book['pages_count'])): ?>
                            <div class="flex items-center ml-6">
                                <span class="text-gray-700 mr-2">Pages:</span>
                                <span class="font-semibold"><?php echo $book['pages_count']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($book['publisher'])): ?>
                            <div class="flex items-center ml-6">
                                <span class="text-gray-700 mr-2">Éditeur:</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($book['publisher']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Section Commentaires -->
<div class="mt-10">
    <h2 class="text-2xl font-bold mb-4">Commentaires et évaluations</h2>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
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
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                Erreur lors de la récupération des commentaires: ' . $e->getMessage() . '
              </div>';
        $comments = [];
    }
    ?>
    
    <!-- Afficher les commentaires existants -->
    <?php if (!empty($comments)): ?>
        <?php foreach ($comments as $comment): ?>
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <div class="flex justify-between mb-2">
                    <div class="font-bold"><?php echo htmlspecialchars($comment['username']); ?></div>
                    <div class="text-yellow-500">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<i class="' . ($i <= $comment['rating'] ? 'fas' : 'far') . ' fa-star"></i>';
                        }
                        ?>
                    </div>
                </div>
                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                <div class="text-gray-500 text-sm mt-2">
                    <?php echo date('d/m/Y à H:i', strtotime($comment['created_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="bg-gray-50 p-4 rounded-lg mb-4 text-center">
            <p>Aucun commentaire pour le moment. Soyez le premier à donner votre avis !</p>
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
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    Erreur lors de la vérification de l\'historique de lecture: ' . $e->getMessage() . '
                  </div>';
            $has_read = false;
        }
        
        if ($has_read):
    ?>
            <div class="bg-white p-6 rounded-lg shadow-md mt-6">
                <h3 class="text-xl font-bold mb-4">Ajouter un commentaire</h3>
                
                <form method="POST" action="book.php?id=<?php echo $book_id; ?>">
                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Votre note</label>
                        <div class="flex items-center space-x-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" id="rating_<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="hidden peer" required>
                                <label for="rating_<?php echo $i; ?>" class="cursor-pointer text-2xl text-gray-300 peer-checked:text-yellow-500 hover:text-yellow-500">
                                    <i class="fas fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="comment_text" class="block text-gray-700 font-bold mb-2">Votre commentaire</label>
                        <textarea id="comment_text" name="comment_text" rows="5" 
                                 class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                    </div>
                    
                    <button type="submit" name="add_comment" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Soumettre
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-blue-50 p-4 rounded-lg mt-6 text-center">
                <p class="text-blue-700 mb-2">Vous devez commencer à lire ce livre avant de pouvoir le commenter.</p>
                <a href="reading.php?id=<?php echo $book_id; ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg mt-2">
                    Commencer à lire
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-blue-50 p-4 rounded-lg mt-6 text-center">
            <p class="text-blue-700 mb-2">Connectez-vous pour laisser un commentaire.</p>
            <a href="login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg mt-2">
                Se connecter
            </a>
        </div>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script pour ajouter le livre à la bibliothèque de l'utilisateur
    document.addEventListener('DOMContentLoaded', function() {
        const addToLibraryBtn = document.getElementById('addToLibraryBtn');
        
        if (addToLibraryBtn) {
            addToLibraryBtn.addEventListener('click', function() {
                // Utiliser AJAX pour ajouter le livre à la bibliothèque
                $.ajax({
                    url: 'ajax/add_to_library.php',
                    type: 'POST',
                    data: {
                        book_id: <?php echo $book_id; ?>
                    },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                addToLibraryBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Ajouté à ma bibliothèque';
                                addToLibraryBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                                addToLibraryBtn.classList.add('bg-gray-500', 'hover:bg-gray-600');
                                addToLibraryBtn.disabled = true;
                            } else {
                                alert(data.message || 'Une erreur est survenue.');
                            }
                        } catch (error) {
                            console.error('Erreur lors du parsing de la réponse:', error);
                        }
                    },
                    error: function() {
                        alert('Une erreur est survenue lors de la communication avec le serveur.');
                    }
                });
            });
        }
    });
</script>

<?php include "includes/footer.php"; ?>