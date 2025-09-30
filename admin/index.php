<?php
// admin/index.php - Tableau de bord administrateur
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est un administrateur

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?message=unauthorized");
    exit();
}

require_once "../classes/Core.php";

$conn = getDatabase();

// Récupération des statistiques globales
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_books' => $conn->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'total_categories' => $conn->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'total_comments' => $conn->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'pending_comments' => $conn->query("SELECT COUNT(*) FROM comments WHERE is_validated = 0")->fetchColumn(),
    'total_notes' => $conn->query("SELECT COUNT(*) FROM notes")->fetchColumn(),
    'total_reading_sessions' => $conn->query("SELECT COUNT(*) FROM reading_sessions")->fetchColumn()
];

// Récupérer les 5 derniers utilisateurs inscrits
$stmt = $conn->prepare("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les 5 derniers livres ajoutés
$stmt = $conn->prepare("SELECT id, title, author, created_at FROM books ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les 5 derniers commentaires postés
$stmt = $conn->prepare("SELECT c.id, c.comment_text, c.rating, c.is_validated, c.created_at, 
                        u.username as user_username, b.title as book_title 
                        FROM comments c
                        JOIN users u ON c.user_id = u.id
                        JOIN books b ON c.book_id = b.id
                        ORDER BY c.created_at DESC LIMIT 5");
$stmt->execute();
$recent_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les livres les plus lus
$stmt = $conn->prepare("SELECT b.id, b.title, b.author, COUNT(rs.id) as read_count
                        FROM books b
                        LEFT JOIN reading_sessions rs ON b.id = rs.book_id
                        GROUP BY b.id
                        ORDER BY read_count DESC
                        LIMIT 5");
$stmt->execute();
$most_read_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Tableau de bord - Administration";
include "../includes/header.php";
?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque Chrétienne - <?php echo $page_title ?? 'Accueil'; ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery pour AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Tableau de bord administrateur</h1>
        <div>
            <a href="../index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center mr-2">
                <i class="fas fa-home mr-2"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- Cartes de statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Utilisateurs</h3>
                <i class="fas fa-users text-blue-600 text-2xl"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_users']; ?></p>
            <p class="text-gray-600"><?php echo $stats['active_users']; ?> actifs</p>
            <a href="users.php" class="text-blue-600 hover:text-blue-800 text-sm mt-4 inline-block">
                Gérer les utilisateurs <i class="fas fa-chevron-right ml-1"></i>
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Livres</h3>
                <i class="fas fa-book text-green-600 text-2xl"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_books']; ?></p>
            <p class="text-gray-600"><?php echo $stats['total_categories']; ?> catégories</p>
            <a href="books.php" class="text-green-600 hover:text-green-800 text-sm mt-4 inline-block">
                Gérer les livres <i class="fas fa-chevron-right ml-1"></i>
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Commentaires</h3>
                <i class="fas fa-comments text-yellow-600 text-2xl"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_comments']; ?></p>
            <p class="text-gray-600"><?php echo $stats['pending_comments']; ?> en attente</p>
            <a href="comments.php" class="text-yellow-600 hover:text-yellow-800 text-sm mt-4 inline-block">
                Gérer les commentaires <i class="fas fa-chevron-right ml-1"></i>
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Notes</h3>
                <i class="fas fa-sticky-note text-purple-600 text-2xl"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_notes']; ?></p>
            <p class="text-gray-600"><?php echo $stats['total_reading_sessions']; ?> sessions de lecture</p>
            <a href="#" class="text-purple-600 hover:text-purple-800 text-sm mt-4 inline-block">
                Voir les statistiques <i class="fas fa-chevron-right ml-1"></i>
            </a>
        </div>
    </div>

    <!-- Dernières activités -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Derniers utilisateurs -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Derniers utilisateurs inscrits</h2>
                <a href="users.php" class="text-blue-600 hover:text-blue-800">Voir tous</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 text-left">Nom d'utilisateur</th>
                            <th class="py-2 px-4 text-left">Email</th>
                            <th class="py-2 px-4 text-left">Date d'inscription</th>
                            <th class="py-2 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-2 px-4"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td class="py-2 px-4 text-center">
                                    <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="users.php?action=view&id=<?php echo $user['id']; ?>" class="text-green-600 hover:text-green-800 mx-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recent_users)): ?>
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">Aucun utilisateur récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Derniers livres -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Derniers livres ajoutés</h2>
                <a href="books.php" class="text-green-600 hover:text-green-800">Voir tous</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 text-left">Titre</th>
                            <th class="py-2 px-4 text-left">Auteur</th>
                            <th class="py-2 px-4 text-left">Date d'ajout</th>
                            <th class="py-2 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_books as $book): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($book['title']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($book['author'] ?? 'Non spécifié'); ?></td>
                                <td class="py-2 px-4"><?php echo date('d/m/Y', strtotime($book['created_at'])); ?></td>
                                <td class="py-2 px-4 text-center">
                                    <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../book.php?id=<?php echo $book['id']; ?>" target="_blank" class="text-green-600 hover:text-green-800 mx-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recent_books)): ?>
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">Aucun livre récent</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <!-- Derniers commentaires -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Derniers commentaires</h2>
                <a href="comments.php" class="text-yellow-600 hover:text-yellow-800">Voir tous</a>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($recent_comments as $comment): ?>
                    <div class="border-b pb-4">
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold"><?php echo htmlspecialchars($comment['user_username']); ?></span>
                            <span class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <p class="text-gray-700 mb-2">
                            "<?php echo htmlspecialchars(substr($comment['comment_text'], 0, 100)) . (strlen($comment['comment_text']) > 100 ? '...' : ''); ?>"
                        </p>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">
                                Livre: <?php echo htmlspecialchars($comment['book_title']); ?>
                                <span class="ml-2 text-yellow-500">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $comment['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </span>
                            </span>
                            <div>
                                <?php if (!$comment['is_validated']): ?>
                                    <a href="comments.php?action=validate&id=<?php echo $comment['id']; ?>" class="text-green-600 hover:text-green-800 mx-1">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="comments.php?action=view&id=<?php echo $comment['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($recent_comments)): ?>
                    <div class="py-4 text-center text-gray-500">Aucun commentaire récent</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Livres les plus lus -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">Livres les plus lus</h2>
                <a href="books.php" class="text-purple-600 hover:text-purple-800">Voir tous</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 text-left">Titre</th>
                            <th class="py-2 px-4 text-left">Auteur</th>
                            <th class="py-2 px-4 text-center">Lectures</th>
                            <th class="py-2 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($most_read_books as $book): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($book['title']); ?></td>
                                <td class="py-2 px-4"><?php echo htmlspecialchars($book['author'] ?? 'Non spécifié'); ?></td>
                                <td class="py-2 px-4 text-center"><?php echo $book['read_count']; ?></td>
                                <td class="py-2 px-4 text-center">
                                    <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../book.php?id=<?php echo $book['id']; ?>" target="_blank" class="text-green-600 hover:text-green-800 mx-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($most_read_books)): ?>
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">Aucune donnée disponible</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

