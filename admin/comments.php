<?php
// admin/comments.php - Gestion des commentaires
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?message=unauthorized");
    exit();
}

require_once "../classes/Core.php";

$conn = getDatabase();

// Messages de feedback
$success_message = "";
$error_message = "";

// Traitement de la suppression d'un commentaire
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    
    // Vérifier si le commentaire existe
    $stmt = $conn->prepare("SELECT id FROM comments WHERE id = :id");
    $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = :id");
        $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $success_message = "Le commentaire a été supprimé avec succès.";
        } else {
            $error_message = "Erreur lors de la suppression du commentaire.";
        }
    } else {
        $error_message = "Commentaire introuvable.";
    }
}

// Traitement de la validation d'un commentaire
if (isset($_GET['action']) && $_GET['action'] == 'validate' && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("UPDATE comments SET is_validated = 1 WHERE id = :id");
    $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $success_message = "Le commentaire a été validé avec succès.";
    } else {
        $error_message = "Erreur lors de la validation du commentaire.";
    }
}

// Traitement de la mise en avant d'un commentaire (pour la section témoignages)
if (isset($_GET['action']) && ($_GET['action'] == 'feature' || $_GET['action'] == 'unfeature') && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    $is_featured = $_GET['action'] == 'feature' ? 1 : 0;
    
    // Un commentaire ne peut être mis en avant que s'il est validé
    if ($is_featured) {
        $stmt = $conn->prepare("UPDATE comments SET is_featured = :is_featured, is_validated = 1 WHERE id = :id");
    } else {
        $stmt = $conn->prepare("UPDATE comments SET is_featured = :is_featured WHERE id = :id");
    }
    
    $stmt->bindParam(':is_featured', $is_featured, PDO::PARAM_INT);
    $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($is_featured) {
            $success_message = "Le commentaire a été mis en avant et sera affiché dans la section témoignages.";
        } else {
            $success_message = "Le commentaire a été retiré de la section témoignages.";
        }
    } else {
        $error_message = "Erreur lors de la mise à jour du commentaire.";
    }
}

// Voir les détails d'un commentaire
$view_comment = null;
if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT c.*, 
                          u.username, u.email, 
                          b.title as book_title, b.author as book_author 
                          FROM comments c
                          JOIN users u ON c.user_id = u.id
                          JOIN books b ON c.book_id = b.id
                          WHERE c.id = :id");
    $stmt->bindParam(':id', $comment_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $view_comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$view_comment) {
        $error_message = "Commentaire introuvable.";
    }
}

// Paramètres de pagination et filtrage
$comments_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $comments_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$featured_filter = isset($_GET['featured']) ? $_GET['featured'] : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Construction de la requête de recherche
$query = "SELECT c.*, 
          u.username as user_username, 
          b.title as book_title
          FROM comments c
          JOIN users u ON c.user_id = u.id
          JOIN books b ON c.book_id = b.id";
$count_query = "SELECT COUNT(*) FROM comments c JOIN users u ON c.user_id = u.id JOIN books b ON c.book_id = b.id";

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.comment_text LIKE :search OR u.username LIKE :search OR b.title LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

if ($status_filter !== '') {
    $where_conditions[] = "c.is_validated = :is_validated";
    $params[':is_validated'] = (int)$status_filter;
}

if ($featured_filter !== '') {
    $where_conditions[] = "c.is_featured = :is_featured";
    $params[':is_featured'] = (int)$featured_filter;
}

if ($rating_filter > 0) {
    $where_conditions[] = "c.rating = :rating";
    $params[':rating'] = $rating_filter;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
    $count_query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Récupérer le nombre total de commentaires pour la pagination
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_comments = $stmt->fetchColumn();
$total_pages = ceil($total_comments / $comments_per_page);

// Récupérer les commentaires pour la page actuelle
$query .= " ORDER BY c.created_at DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $comments_per_page, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales des commentaires
$comment_stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'pending' => $conn->query("SELECT COUNT(*) FROM comments WHERE is_validated = 0")->fetchColumn(),
    'validated' => $conn->query("SELECT COUNT(*) FROM comments WHERE is_validated = 1")->fetchColumn(),
    'featured' => $conn->query("SELECT COUNT(*) FROM comments WHERE is_featured = 1")->fetchColumn(),
    'avg_rating' => $conn->query("SELECT AVG(rating) FROM comments")->fetchColumn()
];

$page_title = "Gestion des commentaires - Administration";

// Inclusion du header admin désactivée pour débogage
// include "../includes/admin_header.php";
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
        <!-- En-tête administrateur -->
        <header class="bg-blue-800 text-white shadow-md">
            <div class="container mx-auto px-4 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold">Administration - Bibliothèque Chrétienne</h1>
                    
                    <div class="flex items-center space-x-4">
                        <a href="index.php" class="hover:text-blue-200">
                            <i class="fas fa-tachometer-alt mr-1"></i> Tableau de bord
                        </a>
                        <a href="../index.php" class="hover:text-blue-200">
                            <i class="fas fa-home mr-1"></i> Voir le site
                        </a>
                        <div class="relative group">
                            <button class="flex items-center hover:text-blue-200">
                                <i class="fas fa-user-circle mr-1"></i> Admin <i class="fas fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                <a href="../logout.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-grow">
            <!-- Menu latéral -->
            <nav class="bg-gray-800 text-white w-64 p-4 hidden md:block">
                <div class="space-y-4">
                    <a href="index.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt mr-2"></i> Tableau de bord
                    </a>
                    <a href="books.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-book mr-2"></i> Livres
                    </a>
                    <a href="categories.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-tags mr-2"></i> Catégories
                    </a>
                    <a href="users.php" class="block py-2 px-4 rounded hover:bg-gray-700">
                        <i class="fas fa-users mr-2"></i> Utilisateurs
                    </a>
                    <a href="comments.php" class="block py-2 px-4 rounded bg-gray-700">
                        <i class="fas fa-comments mr-2"></i> Commentaires
                    </a>
                </div>
            </nav>

            <!-- Contenu principal -->
            <main class="flex-grow p-6">
                <div class="container mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold">Gestion des commentaires</h1>
                    </div>

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
                    
                    <?php if ($view_comment): ?>
                        <!-- Détails du commentaire -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold">Détails du commentaire</h2>
                                <div>
                                    <a href="comments.php" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded">
                                        <i class="fas fa-arrow-left"></i> Retour
                                    </a>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="md:col-span-2">
                                    <div class="bg-gray-50 p-6 rounded-lg">
                                        <div class="flex justify-between mb-4">
                                            <div>
                                                <span class="font-bold"><?php echo htmlspecialchars($view_comment['username']); ?></span>
                                                <span class="text-gray-500 ml-2"><?php echo date('d/m/Y à H:i', strtotime($view_comment['created_at'])); ?></span>
                                            </div>
                                            <div>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $view_comment['rating'] ? 'text-yellow-500' : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-white p-4 rounded-lg border mb-4">
                                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($view_comment['comment_text'])); ?></p>
                                        </div>
                                        
                                        <div class="flex items-center">
                                            <span class="text-gray-600 mr-2">Livre:</span>
                                            <a href="../book.php?id=<?php echo $view_comment['book_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                                <?php echo htmlspecialchars($view_comment['book_title']); ?>
                                            </a>
                                            <?php if (!empty($view_comment['book_author'])): ?>
                                                <span class="text-gray-600 ml-2">par <?php echo htmlspecialchars($view_comment['book_author']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="md:col-span-1">
                                    <div class="bg-gray-50 p-6 rounded-lg">
                                        <h3 class="font-bold mb-4">Statut du commentaire</h3>
                                        
                                        <div class="space-y-2 mb-6">
                                            <div class="flex items-center">
                                                <span class="mr-2">Validation:</span>
                                                <?php if ($view_comment['is_validated']): ?>
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Validé</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">En attente</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex items-center">
                                                <span class="mr-2">Mis en avant:</span>
                                                <?php if ($view_comment['is_featured']): ?>
                                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Oui</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">Non</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <h3 class="font-bold mb-4">Actions</h3>
                                        
                                        <div class="space-y-2">
                                            <?php if (!$view_comment['is_validated']): ?>
                                                <a href="comments.php?action=validate&id=<?php echo $view_comment['id']; ?>" class="block w-full py-2 px-4 bg-green-600 hover:bg-green-700 text-white rounded text-center">
                                                    <i class="fas fa-check mr-2"></i> Valider ce commentaire
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$view_comment['is_featured']): ?>
                                                <a href="comments.php?action=feature&id=<?php echo $view_comment['id']; ?>" class="block w-full py-2 px-4 bg-purple-600 hover:bg-purple-700 text-white rounded text-center">
                                                    <i class="fas fa-star mr-2"></i> Mettre en avant
                                                </a>
                                            <?php else: ?>
                                                <a href="comments.php?action=unfeature&id=<?php echo $view_comment['id']; ?>" class="block w-full py-2 px-4 bg-gray-600 hover:bg-gray-700 text-white rounded text-center">
                                                    <i class="fas fa-star-half-alt mr-2"></i> Retirer de la mise en avant
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="#" onclick="confirmDelete(<?php echo $view_comment['id']; ?>)" class="block w-full py-2 px-4 bg-red-600 hover:bg-red-700 text-white rounded text-center">
                                                <i class="fas fa-trash-alt mr-2"></i> Supprimer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 bg-gray-50 p-6 rounded-lg">
                                <h3 class="font-bold mb-4">Informations sur l'utilisateur</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-gray-600 mr-2">Nom d'utilisateur:</span>
                                            <a href="users.php?action=view&id=<?php echo $view_comment['user_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($view_comment['username']); ?>
                                            </a>
                                        </div>
                                        
                                        <div class="flex items-center">
                                            <span class="text-gray-600 mr-2">Email:</span>
                                            <span><?php echo htmlspecialchars($view_comment['email']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <a href="users.php?action=view&id=<?php echo $view_comment['user_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-user mr-1"></i> Voir le profil complet
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Statistiques des commentaires -->
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <div class="text-xl font-bold text-blue-600"><?php echo $comment_stats['total']; ?></div>
                                <div class="text-gray-500">Total</div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <div class="text-xl font-bold text-yellow-600"><?php echo $comment_stats['pending']; ?></div>
                                <div class="text-gray-500">En attente</div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <div class="text-xl font-bold text-green-600"><?php echo $comment_stats['validated']; ?></div>
                                <div class="text-gray-500">Validés</div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <div class="text-xl font-bold text-purple-600"><?php echo $comment_stats['featured']; ?></div>
                                <div class="text-gray-500">En vedette</div>
                            </div>
                            
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <div class="text-xl font-bold text-orange-600">
                                    <?php echo number_format($comment_stats['avg_rating'], 1); ?>
                                    <i class="fas fa-star text-yellow-500 ml-1 text-sm"></i>
                                </div>
                                <div class="text-gray-500">Note moyenne</div>
                            </div>
                        </div>
                        
                        <!-- Liste des commentaires -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold">Liste des commentaires</h2>
                                
                                <form method="GET" action="comments.php" class="flex items-center space-x-2">
                                    <input type="text" name="search" placeholder="Rechercher..." 
                                          value="<?php echo htmlspecialchars($search); ?>"
                                          class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    
                                    <select name="status" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Tous les statuts</option>
                                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Validés</option>
                                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>En attente</option>
                                    </select>
                                    
                                    <select name="featured" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Tous</option>
                                        <option value="1" <?php echo $featured_filter === '1' ? 'selected' : ''; ?>>En vedette</option>
                                        <option value="0" <?php echo $featured_filter === '0' ? 'selected' : ''; ?>>Non mis en avant</option>
                                    </select>
                                    
                                    <select name="rating" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="0">Toutes les notes</option>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $rating_filter === $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="py-2 px-4 border-b text-left">Utilisateur</th>
                                            <th class="py-2 px-4 border-b text-left">Livre</th>
                                            <th class="py-2 px-4 border-b text-left">Commentaire</th>
                                            <th class="py-2 px-4 border-b text-center">Note</th>
                                            <th class="py-2 px-4 border-b text-center">Statut</th>
                                            <th class="py-2 px-4 border-b text-center">Date</th>
                                            <th class="py-2 px-4 border-b text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comments as $comment): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 px-4 border-b">
                                                    <a href="users.php?action=view&id=<?php echo $comment['user_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                        <?php echo htmlspecialchars($comment['user_username']); ?>
                                                    </a>
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <a href="../book.php?id=<?php echo $comment['book_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                        <?php echo htmlspecialchars($comment['book_title']); ?>
                                                    </a>
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <?php echo htmlspecialchars(substr($comment['comment_text'], 0, 50)) . (strlen($comment['comment_text']) > 50 ? '...' : ''); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <div class="text-yellow-500">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="<?php echo $i <= $comment['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <?php if ($comment['is_validated']): ?>
                                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Validé</span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">En attente</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($comment['is_featured']): ?>
                                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs mt-1 inline-block">En vedette</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center text-sm">
                                                    <?php echo date('d/m/Y', strtotime($comment['created_at'])); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <a href="comments.php?action=view&id=<?php echo $comment['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if (!$comment['is_validated']): ?>
                                                        <a href="comments.php?action=validate&id=<?php echo $comment['id']; ?>" class="text-green-600 hover:text-green-800 mx-1" title="Valider">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$comment['is_featured'] && $comment['is_validated']): ?>
                                                        <a href="comments.php?action=feature&id=<?php echo $comment['id']; ?>" class="text-purple-600 hover:text-purple-800 mx-1" title="Mettre en avant">
                                                            <i class="fas fa-star"></i>
                                                        </a>
                                                    <?php elseif ($comment['is_featured']): ?>
                                                        <a href="comments.php?action=unfeature&id=<?php echo $comment['id']; ?>" class="text-yellow-600 hover:text-yellow-800 mx-1" title="Retirer de la mise en avant">
                                                            <i class="fas fa-star-half-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="#" onclick="confirmDelete(<?php echo $comment['id']; ?>)" class="text-red-600 hover:text-red-800 mx-1" title="Supprimer">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($comments)): ?>
                                            <tr>
                                                <td colspan="7" class="py-4 text-center text-gray-500">Aucun commentaire trouvé</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="flex justify-center mt-6">
                                    <div class="flex space-x-1">
                                        <?php if ($current_page > 1): ?>
                                            <a href="comments.php?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                            <a href="comments.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>" 
                                               class="px-4 py-2 border rounded-lg <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="comments.php?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&featured=<?php echo urlencode($featured_filter); ?>&rating=<?php echo urlencode($rating_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
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
        function confirmDelete(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ? Cette action est irréversible.')) {
                window.location.href = 'comments.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>