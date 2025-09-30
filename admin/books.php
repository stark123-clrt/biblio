<?php

ini_set('upload_max_filesize', '6M');
ini_set('post_max_size', '8M');
// admin/books.php - Gestion des livres
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification admin désactivée temporairement pour débogage
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?message=unauthorized");
    exit();
}

require_once "../classes/Core.php";

$conn = getDatabase();

// Messages de feedback
$success_message = "";
$error_message = "";

// Définir le livre en cours d'édition/consultation
$edit_book = [
    'id' => '',
    'title' => '',
    'description' => '',
    'author' => '',
    'publisher' => '',
    'publication_year' => '',
    'pages_count' => '',
    'cover_image' => '',
    'file_path' => '',
    'is_featured' => 0
];

// Traitement de la suppression d'un livre
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $book_id = (int)$_GET['id'];
    
    // Vérifier si le livre existe
    $stmt = $conn->prepare("SELECT id, title, file_path, cover_image FROM books WHERE id = :id");
    $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($book) {
        try {
            $conn->beginTransaction();
            
            // Supprimer les relations livre-catégories
            $stmt = $conn->prepare("DELETE FROM book_categories WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
           
          
            // Supprimer les commentaires associés
            $stmt = $conn->prepare("DELETE FROM comments WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les notes associées
            $stmt = $conn->prepare("DELETE FROM notes WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les signets associés
            $stmt = $conn->prepare("DELETE FROM bookmarks WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les sessions de lecture et l'historique
            $stmt = $conn->prepare("DELETE FROM reading_sessions WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare("DELETE FROM reading_history WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les références dans la bibliothèque des utilisateurs
            $stmt = $conn->prepare("DELETE FROM user_library WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer le livre de la base de données
            $stmt = $conn->prepare("DELETE FROM books WHERE id = :id");
            $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Supprimer le fichier PDF associé si existant
                if (!empty($book['file_path']) && file_exists($book['file_path'])) {
                    unlink($book['file_path']);
                }
                
                // Supprimer la couverture si existante
                if (!empty($book['cover_image']) && file_exists($book['cover_image'])) {
                    unlink($book['cover_image']);
                }
                
                $conn->commit();
                $success_message = "Le livre \"" . htmlspecialchars($book['title']) . "\" a été supprimé avec succès.";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Erreur lors de la suppression du livre : " . $e->getMessage();
        }
    } else {
        $error_message = "Livre introuvable.";
    }
}

// Traitement de la mise en avant d'un livre
if (isset($_GET['action']) && ($_GET['action'] == 'feature' || $_GET['action'] == 'unfeature') && isset($_GET['id'])) {
    $book_id = (int)$_GET['id'];
    $is_featured = $_GET['action'] == 'feature' ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE books SET is_featured = :is_featured WHERE id = :id");
    $stmt->bindParam(':is_featured', $is_featured, PDO::PARAM_INT);
    $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $success_message = $is_featured 
            ? "Le livre a été mis en avant sur la page d'accueil." 
            : "Le livre a été retiré de la section en vedette.";
    } else {
        $error_message = "Erreur lors de la mise à jour du livre.";
    }
}

// Récupérer un livre pour l'édition
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $book_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = :id");
    $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($book) {
        $edit_book = $book;
        
        // Récupérer les catégories du livre
        $stmt = $conn->prepare("SELECT category_id FROM book_categories WHERE book_id = :book_id");
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        $book_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $error_message = "Livre introuvable.";
    }
}

// Traitement du formulaire d'ajout/modification de livre
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_book'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $author = trim($_POST['author']);
    $publisher = trim($_POST['publisher']);
    $publication_year = !empty($_POST['publication_year']) ? (int)$_POST['publication_year'] : null;
    $pages_count = !empty($_POST['pages_count']) ? (int)$_POST['pages_count'] : null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : null;
    
    // Validation basique
    if (empty($title)) {
        $error_message = "Le titre du livre est obligatoire.";
    } else {
        try {
            $conn->beginTransaction();
            
            // Gestion du téléchargement du fichier PDF
            $file_path = null;
            if ($book_id) {
                // Récupérer le chemin du fichier existant
                $stmt = $conn->prepare("SELECT file_path FROM books WHERE id = :id");
                $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
                $stmt->execute();
                $file_path = $stmt->fetchColumn();
            }
            
            
            if (isset($_FILES['book_file']) && $_FILES['book_file']['size'] > 0) {
                $file_tmp = $_FILES['book_file']['tmp_name'];
                $file_name = $_FILES['book_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $file_type = $_FILES['book_file']['type'];
                
                // Définition de la taille maximale à 6 Mo
                $max_size = 6 * 1024 * 1024; // 6 Mo en octets
                
                // Afficher des informations de débogage (vous pouvez retirer cette partie plus tard)
                error_log("Upload de fichier - Type: " . $file_type . ", Extension: " . $file_ext . ", Taille: " . $_FILES['book_file']['size'] . " octets, Erreur: " . $_FILES['book_file']['error']);
                
                // Vérification de la taille du fichier
                if ($_FILES['book_file']['size'] > $max_size) {
                    throw new Exception("Le fichier est trop volumineux. La taille maximale autorisée est de 6 Mo.");
                }
                
                // Vérification plus souple du type de fichier (extension ou type MIME)
                if ($file_ext != 'pdf' && strpos($file_type, 'pdf') === false && strpos($file_type, 'application/pdf') === false) {
                    throw new Exception("Seuls les fichiers PDF sont acceptés. Votre fichier a l'extension ." . $file_ext . " et le type " . $file_type);
                }
                
                $upload_dir = "../assets/uploads/books/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_file_name = time() . '_' . str_replace(' ', '_', $title) . '.' . $file_ext;
                $new_file_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $new_file_path)) {
                    // Si le livre avait déjà un fichier, le supprimer
                    if ($book_id && !empty($file_path) && file_exists($file_path) && $file_path != $new_file_path) {
                        unlink($file_path);
                    }
                    $file_path = $new_file_path;
                } else {
                    // Message d'erreur plus détaillé
                    $upload_error = $_FILES['book_file']['error'];
                    $error_message = "";
                    
                    switch ($upload_error) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message = "Le fichier dépasse la limite définie dans php.ini (upload_max_filesize).";
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = "Le fichier dépasse la limite définie dans le formulaire HTML.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = "Le fichier n'a été que partiellement téléchargé.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = "Dossier temporaire manquant.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = "Échec de l'écriture du fichier sur le disque.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = "Une extension PHP a arrêté le téléchargement.";
                            break;
                        default:
                            $error_message = "Erreur inconnue lors du téléchargement.";
                    }
                    
                    throw new Exception("Erreur lors du téléchargement du fichier: " . $error_message);
                }
            }

            // Gestion de l'upload de la couverture
            $cover_image = null;
            if ($book_id) {
                // Récupérer le chemin de l'image existante
                $stmt = $conn->prepare("SELECT cover_image FROM books WHERE id = :id");
                $stmt->bindParam(':id', $book_id, PDO::PARAM_INT);
                $stmt->execute();
                $cover_image = $stmt->fetchColumn();
            }
            
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                $img_tmp = $_FILES['cover_image']['tmp_name'];
                $img_name = $_FILES['cover_image']['name'];
                $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($img_ext, $allowed_exts)) {
                    throw new Exception("Format d'image non accepté. Utilisez JPG, PNG ou GIF.");
                }
                
                $upload_dir = "../assets/uploads/covers/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_img_name = time() . '_' . str_replace(' ', '_', $title) . '.' . $img_ext;
                $new_img_path = $upload_dir . $new_img_name;
                
                if (move_uploaded_file($img_tmp, $new_img_path)) {
                    // Si le livre avait déjà une image, la supprimer
                    if ($book_id && !empty($cover_image) && file_exists($cover_image) && $cover_image != $new_img_path) {
                        unlink($cover_image);
                    }
                    $cover_image = $new_img_path;
                } else {
                    throw new Exception("Erreur lors du téléchargement de l'image de couverture.");
                }
            }
            
            // Ajout ou mise à jour du livre
            if ($book_id) {
                // Mise à jour
                $stmt = $conn->prepare("UPDATE books SET 
                                       title = :title, 
                                       description = :description, 
                                       author = :author, 
                                       publisher = :publisher,
                                       publication_year = :publication_year,
                                       pages_count = :pages_count,
                                       is_featured = :is_featured
                                       " . ($file_path ? ", file_path = :file_path" : "") . "
                                       " . ($cover_image ? ", cover_image = :cover_image" : "") . "
                                       WHERE id = :id");
                                       
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':author', $author);
                $stmt->bindParam(':publisher', $publisher);
                $stmt->bindParam(':publication_year', $publication_year);
                $stmt->bindParam(':pages_count', $pages_count);
                $stmt->bindParam(':is_featured', $is_featured);
                $stmt->bindParam(':id', $book_id);
                
                if ($file_path) {
                    $stmt->bindParam(':file_path', $file_path);
                }
                
                if ($cover_image) {
                    $stmt->bindParam(':cover_image', $cover_image);
                }
                
                $stmt->execute();
                
                // Supprimer les anciennes catégories
                $stmt = $conn->prepare("DELETE FROM book_categories WHERE book_id = :book_id");
                $stmt->bindParam(':book_id', $book_id);
                $stmt->execute();
                
                $success_message = "Livre mis à jour avec succès.";
            } else {
                // Ajout
                if (empty($file_path)) {
                    throw new Exception("Veuillez télécharger un fichier PDF pour le livre.");
                }
                
                $stmt = $conn->prepare("INSERT INTO books (title, description, author, publisher, publication_year, pages_count, cover_image, file_path, is_featured)
                                       VALUES (:title, :description, :author, :publisher, :publication_year, :pages_count, :cover_image, :file_path, :is_featured)");
                                       
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':author', $author);
                $stmt->bindParam(':publisher', $publisher);
                $stmt->bindParam(':publication_year', $publication_year);
                $stmt->bindParam(':pages_count', $pages_count);
                $stmt->bindParam(':cover_image', $cover_image);
                $stmt->bindParam(':file_path', $file_path);
                $stmt->bindParam(':is_featured', $is_featured);
                
                $stmt->execute();
                $book_id = $conn->lastInsertId();
                
                $success_message = "Livre ajouté avec succès.";
            }
            
            // Enregistrer les catégories sélectionnées
            if ($book_id && !empty($selected_categories)) {
                $insertCat = $conn->prepare("INSERT INTO book_categories (book_id, category_id) VALUES (:book_id, :category_id)");
                
                foreach ($selected_categories as $cat_id) {
                    $insertCat->bindParam(':book_id', $book_id);
                    $insertCat->bindParam(':category_id', $cat_id);
                    $insertCat->execute();
                }
            }
            
            $conn->commit();
            
            // Rediriger pour éviter la soumission multiple du formulaire
            header("Location: books.php?success=" . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupérer toutes les catégories pour le formulaire
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Paramètres de pagination et filtrage
$books_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $books_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$featured_filter = isset($_GET['featured']) ? $_GET['featured'] : '';

// Construction de la requête de recherche
$query = "SELECT DISTINCT b.* FROM books b";
$count_query = "SELECT COUNT(DISTINCT b.id) FROM books b";

if ($category_filter > 0) {
    $query .= " JOIN book_categories bc ON b.id = bc.book_id";
    $count_query .= " JOIN book_categories bc ON b.id = bc.book_id";
}

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE :search OR b.author LIKE :search OR b.description LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

if ($category_filter > 0) {
    $where_conditions[] = "bc.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if ($featured_filter !== '') {
    $where_conditions[] = "b.is_featured = :is_featured";
    $params[':is_featured'] = (int)$featured_filter;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
    $count_query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Récupérer le nombre total de livres pour la pagination
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_books = $stmt->fetchColumn();
$total_pages = ceil($total_books / $books_per_page);

// Récupérer les livres pour la page actuelle
$query .= " ORDER BY b.created_at DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $books_per_page, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour chaque livre
foreach ($books as $key => $book) {
    $stmt = $conn->prepare("SELECT c.name FROM categories c 
                          JOIN book_categories bc ON c.id = bc.category_id 
                          WHERE bc.book_id = :book_id");
    $stmt->bindParam(':book_id', $book['id']);
    $stmt->execute();
    $book_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $books[$key]['categories'] = $book_categories;
}

$page_title = "Gestion des livres - Administration";

// Inclusion du header admin désactivée pour débogage
include "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque Chrétienne - <?php echo $page_title ?? 'Administration'; ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery pour AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col min-h-screen">
  
        <div class="flex flex-grow">
            <!-- Menu latéral -->
            <nav class="bg-gray-800 text-white w-64 p-4 hidden md:block">
                <div class="space-y-4">
                    <a href="index.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt mr-2"></i> Tableau de bord
                    </a>
                    <a href="books.php" class="block py-2 px-4 rounded bg-gray-700">
                        <i class="fas fa-book mr-2"></i> Livres
                    </a>
                    <a href="categories.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-tags mr-2"></i> Catégories
                    </a>
                    <a href="users.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-users mr-2"></i> Utilisateurs
                    </a>
                    <a href="comments.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-comments mr-2"></i> Commentaires
                    </a>
                </div>
            </nav>

            <!-- Contenu principal -->
            <main class="flex-grow p-6">
                <div class="container mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold">Gestion des livres</h1>
                        <a href="books.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Nouveau livre
                        </a>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($_GET['success']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])): ?>
                        <!-- Formulaire d'édition de livre -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold">Modifier le livre</h2>
                                <a href="books.php" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded">
                                    <i class="fas fa-arrow-left"></i> Retour à la liste
                                </a>
                            </div>
                            
                            <form method="POST" action="books.php" enctype="multipart/form-data">
                                <input type="hidden" name="book_id" value="<?php echo $edit_book['id']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="title" class="block text-gray-700 font-bold mb-2">Titre <span class="text-red-500">*</span></label>
                                        <input type="text" id="title" name="title" 
                                              value="<?php echo htmlspecialchars($edit_book['title']); ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    
                                    <div>
                                        <label for="author" class="block text-gray-700 font-bold mb-2">Auteur</label>
                                        <input type="text" id="author" name="author" 
                                              value="<?php echo htmlspecialchars($edit_book['author']); ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label for="publisher" class="block text-gray-700 font-bold mb-2">Éditeur</label>
                                        <input type="text" id="publisher" name="publisher" 
                                              value="<?php echo htmlspecialchars($edit_book['publisher']); ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label for="publication_year" class="block text-gray-700 font-bold mb-2">Année de publication</label>
                                        <input type="number" id="publication_year" name="publication_year" 
                                              value="<?php echo $edit_book['publication_year']; ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              min="1" max="<?php echo date('Y'); ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="pages_count" class="block text-gray-700 font-bold mb-2">Nombre de pages</label>
                                        <input type="number" id="pages_count" name="pages_count" 
                                              value="<?php echo $edit_book['pages_count']; ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                                    <textarea id="description" name="description" rows="5"
                                             class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($edit_book['description']); ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="cover_image" class="block text-gray-700 font-bold mb-2">Image de couverture</label>
                                        <?php if (!empty($edit_book['cover_image'])): ?>
                                            <div class="mb-2">
                                                <img src="<?php echo $edit_book['cover_image']; ?>" alt="Couverture actuelle" class="w-32 h-auto border">
                                                <p class="text-sm text-gray-500">Couverture actuelle</p>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" id="cover_image" name="cover_image" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              accept="image/jpeg,image/png,image/gif">
                                        <p class="text-sm text-gray-500 mt-1">Formats acceptés: JPG, PNG, GIF. Pour de meilleurs résultats, utilisez une image de ratio 2:3.</p>
                                    </div>
                                    
                                    <div>
                                        <label for="book_file" class="block text-gray-700 font-bold mb-2">Fichier PDF</label>
                                        <?php if (!empty($edit_book['file_path'])): ?>
                                            <div class="mb-2">
                                                <p class="text-sm text-gray-800">
                                                    <i class="fas fa-file-pdf text-red-600 mr-1"></i>
                                                    Fichier actuel: <?php echo basename($edit_book['file_path']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" id="book_file" name="book_file" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              accept="application/pdf">
                                        <p class="text-sm text-gray-500 mt-1">Format accepté: PDF uniquement. <?php echo $edit_book['id'] ? 'Laissez vide pour conserver le fichier actuel.' : ''; ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 font-bold mb-2">Catégories</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="category_<?php echo $category['id']; ?>" name="categories[]" 
                                                      value="<?php echo $category['id']; ?>"
                                                      class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                      <?php echo (isset($book_categories) && in_array($category['id'], $book_categories)) ? 'checked' : ''; ?>>
                                                <label for="category_<?php echo $category['id']; ?>" class="ml-2 text-gray-700">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-6">
                                    <input type="checkbox" id="is_featured" name="is_featured" 
                                          class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                          <?php echo $edit_book['is_featured'] ? 'checked' : ''; ?>>
                                    <label for="is_featured" class="ml-2 text-gray-700 font-medium">
                                        Mettre ce livre en vedette sur la page d'accueil
                                    </label>
                                </div>
                                
                                <div class="flex justify-between">
                                    <button type="submit" name="save_book" 
                                           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                                        Enregistrer les modifications
                                    </button>
                                    
                                    <a href="books.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                                        Annuler
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php elseif (!isset($_GET['action'])): ?>
                        <!-- Liste des livres -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                                <h2 class="text-xl font-bold mb-4 md:mb-0">Liste des livres</h2>
                                
                                <form method="GET" action="books.php" class="flex flex-wrap md:flex-nowrap items-center gap-2">
                                    <input type="text" name="search" placeholder="Rechercher..." 
                                          value="<?php echo htmlspecialchars($search); ?>"
                                          class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    
                                    <select name="category" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="0">Toutes les catégories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="featured" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Tous les livres</option>
                                        <option value="1" <?php echo $featured_filter === '1' ? 'selected' : ''; ?>>En vedette</option>
                                        <option value="0" <?php echo $featured_filter === '0' ? 'selected' : ''; ?>>Non en vedette</option>
                                    </select>
                                    
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex-shrink-0">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <?php if (!empty($books)): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="py-2 px-4 border-b text-left">Livre</th>
                                                <th class="py-2 px-4 border-b text-left">Auteur</th>
                                                <th class="py-2 px-4 border-b text-left">Catégories</th>
                                                <th class="py-2 px-4 border-b text-center">Pages</th>
                                                <th class="py-2 px-4 border-b text-center">En vedette</th>
                                                <th class="py-2 px-4 border-b text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($books as $book): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="py-2 px-4 border-b">
                                                        <div class="flex items-center">
                                                            <?php if (!empty($book['cover_image'])): ?>
                                                                <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="w-12 h-16 object-cover mr-3">
                                                            <?php else: ?>
                                                                <div class="w-12 h-16 bg-gray-200 flex items-center justify-center mr-3">
                                                                    <i class="fas fa-book text-gray-400"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="font-semibold"><?php echo htmlspecialchars($book['title']); ?></div>
                                                                <div class="text-xs text-gray-500">
                                                                    <?php echo !empty($book['publication_year']) ? $book['publication_year'] : ''; ?>
                                                                    <?php echo !empty($book['publisher']) ? ' • ' . htmlspecialchars($book['publisher']) : ''; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo !empty($book['author']) ? htmlspecialchars($book['author']) : '<span class="text-gray-400">Non spécifié</span>'; ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php if (!empty($book['categories'])): ?>
                                                            <div class="flex flex-wrap gap-1">
                                                                <?php foreach ($book['categories'] as $category): ?>
                                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                                                        <?php echo htmlspecialchars($category); ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">Non catégorisé</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <?php echo !empty($book['pages_count']) ? $book['pages_count'] : '-'; ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <?php if ($book['is_featured']): ?>
                                                            <span class="text-green-600"><i class="fas fa-check"></i></span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400"><i class="fas fa-times"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <a href="../book.php?id=<?php echo $book['id']; ?>" target="_blank" class="text-green-600 hover:text-green-800 mx-1" title="Voir">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($book['is_featured']): ?>
                                                            <a href="books.php?action=unfeature&id=<?php echo $book['id']; ?>" class="text-yellow-600 hover:text-yellow-800 mx-1" title="Retirer de la mise en avant">
                                                                <i class="fas fa-star-half-alt"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="books.php?action=feature&id=<?php echo $book['id']; ?>" class="text-purple-600 hover:text-purple-800 mx-1" title="Mettre en avant">
                                                                <i class="fas fa-star"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="#" onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')" class="text-red-600 hover:text-red-800 mx-1" title="Supprimer">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="flex justify-center mt-6">
                                        <div class="flex space-x-1">
                                            <?php if ($current_page > 1): ?>
                                                <a href="books.php?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                <a href="books.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>" 
                                                   class="px-4 py-2 border rounded-lg <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($current_page < $total_pages): ?>
                                                <a href="books.php?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="bg-blue-50 p-4 rounded-lg text-center">
                                    <p class="text-blue-700 mb-2">Aucun livre trouvé</p>
                                    <?php if (!empty($search) || $category_filter > 0 || $featured_filter !== ''): ?>
                                        <p class="text-gray-600 text-sm">Essayez de modifier vos critères de recherche ou <a href="books.php" class="text-blue-600 hover:text-blue-800">afficher tous les livres</a>.</p>
                                    <?php else: ?>
                                        <p class="text-gray-600 text-sm">Commencez par ajouter des livres à votre bibliothèque.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire d'ajout de livre (affiché uniquement sur la page principale) -->
                        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                            <h2 class="text-xl font-bold mb-6">Ajouter un nouveau livre</h2>
                            
                            <form method="POST" action="books.php" enctype="multipart/form-data">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="title_new" class="block text-gray-700 font-bold mb-2">Titre <span class="text-red-500">*</span></label>
                                        <input type="text" id="title_new" name="title" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    
                                    <div>
                                        <label for="author_new" class="block text-gray-700 font-bold mb-2">Auteur</label>
                                        <input type="text" id="author_new" name="author" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label for="publisher_new" class="block text-gray-700 font-bold mb-2">Éditeur</label>
                                        <input type="text" id="publisher_new" name="publisher" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label for="publication_year_new" class="block text-gray-700 font-bold mb-2">Année de publication</label>
                                        <input type="number" id="publication_year_new" name="publication_year" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              min="1" max="<?php echo date('Y'); ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="pages_count_new" class="block text-gray-700 font-bold mb-2">Nombre de pages</label>
                                        <input type="number" id="pages_count_new" name="pages_count" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="description_new" class="block text-gray-700 font-bold mb-2">Description</label>
                                    <textarea id="description_new" name="description" rows="5"
                                             class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label for="cover_image_new" class="block text-gray-700 font-bold mb-2">Image de couverture</label>
                                        <input type="file" id="cover_image_new" name="cover_image" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              accept="image/jpeg,image/png,image/gif">
                                        <p class="text-sm text-gray-500 mt-1">Formats acceptés: JPG, PNG, GIF. Pour de meilleurs résultats, utilisez une image de ratio 2:3.</p>
                                    </div>
                                    
                                    <div>
                                        <label for="book_file_new" class="block text-gray-700 font-bold mb-2">Fichier PDF <span class="text-red-500">*</span></label>
                                        <input type="file" id="book_file_new" name="book_file" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              accept="application/pdf" required>
                                        <p class="text-sm text-gray-500 mt-1">Format accepté: PDF uniquement.</p>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 font-bold mb-2">Catégories</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="flex items-center">
                                                <input type="checkbox" id="category_new_<?php echo $category['id']; ?>" name="categories[]" 
                                                      value="<?php echo $category['id']; ?>"
                                                      class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                <label for="category_new_<?php echo $category['id']; ?>" class="ml-2 text-gray-700">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-6">
                                    <input type="checkbox" id="is_featured_new" name="is_featured" 
                                          class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="is_featured_new" class="ml-2 text-gray-700 font-medium">
                                        Mettre ce livre en vedette sur la page d'accueil
                                    </label>
                                </div>
                                
                                <div>
                                    <button type="submit" name="save_book" 
                                           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                                        Ajouter le livre
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>

        <!-- Pied de page -->
        <footer class="bg-gray-800 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?php echo date('Y'); ?> Bibliothèque - Interface d'administration</p>
            </div>
        </footer>
    </div>

    <script>
        function confirmDelete(id, title) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le livre "' + title + '" ? Cette action supprimera également tous les commentaires, notes et signets associés.')) {
                window.location.href = 'books.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
