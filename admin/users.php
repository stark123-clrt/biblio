<?php
// admin/users.php - Gestion des utilisateurs
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

// Définir l'utilisateur en cours d'édition
$edit_user = [
    'id' => '',
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role' => 'user',
    'is_active' => 1
];

// Traitement de la suppression d'un utilisateur
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Ne pas permettre de supprimer son propre compte
    if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
        $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        // Vérifier si l'utilisateur existe
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            try {
                $conn->beginTransaction();
                
                // Supprimer les données associées à l'utilisateur
               $tables = ['notes', 'comments', 'bookmarks', 'reading_history', 'user_library'];
                
                foreach ($tables as $table) {
                    $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                // Supprimer l'utilisateur
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $success_message = "L'utilisateur \"" . htmlspecialchars($user['username']) . "\" a été supprimé avec succès.";
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                $error_message = "Erreur lors de la suppression de l'utilisateur : " . $e->getMessage();
            }
        } else {
            $error_message = "Utilisateur introuvable.";
        }
    }
}

// Traitement de l'activation/désactivation d'un utilisateur
if (isset($_GET['action']) && ($_GET['action'] == 'activate' || $_GET['action'] == 'deactivate') && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $is_active = $_GET['action'] == 'activate' ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $success_message = "Le statut de l'utilisateur a été mis à jour avec succès.";
    } else {
        $error_message = "Erreur lors de la mise à jour du statut de l'utilisateur.";
    }
}

// Récupérer un utilisateur pour l'édition
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT id, username, email, first_name, last_name, role, is_active FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $edit_user = $user;
    } else {
        $error_message = "Utilisateur introuvable.";
    }
}

// Traitement du formulaire d'ajout/modification d'utilisateur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = trim($_POST['password'] ?? '');
    
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    // Validation basique
    if (empty($username) || empty($email)) {
        $error_message = "Le nom d'utilisateur et l'email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Veuillez entrer une adresse email valide.";
    } else {
        try {
            // Vérifier si le nom d'utilisateur ou l'email existe déjà (sauf pour l'utilisateur en cours d'édition)
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e).";
            } else {
                if ($user_id) {
                    // Mise à jour
                    if (!empty($password)) {
                        // Si un nouveau mot de passe est fourni, le mettre à jour
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET 
                                               username = :username, 
                                               email = :email, 
                                               first_name = :first_name, 
                                               last_name = :last_name,
                                               role = :role,
                                               is_active = :is_active,
                                               password = :password
                                               WHERE id = :id");
                        $stmt->bindParam(':password', $hashed_password);
                    } else {
                        // Sinon, ne pas toucher au mot de passe
                        $stmt = $conn->prepare("UPDATE users SET 
                                               username = :username, 
                                               email = :email, 
                                               first_name = :first_name, 
                                               last_name = :last_name,
                                               role = :role,
                                               is_active = :is_active
                                               WHERE id = :id");
                    }
                    
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':first_name', $first_name);
                    $stmt->bindParam(':last_name', $last_name);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success_message = "Utilisateur mis à jour avec succès.";
                        // Réinitialiser le formulaire
                        $edit_user = [
                            'id' => '',
                            'username' => '',
                            'email' => '',
                            'first_name' => '',
                            'last_name' => '',
                            'role' => 'user',
                            'is_active' => 1
                        ];
                    }
                } else {
                    // Ajout d'un nouvel utilisateur
                    if (empty($password)) {
                        $error_message = "Le mot de passe est obligatoire pour un nouvel utilisateur.";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, is_active)
                                               VALUES (:username, :email, :password, :first_name, :last_name, :role, :is_active)");
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':first_name', $first_name);
                        $stmt->bindParam(':last_name', $last_name);
                        $stmt->bindParam(':role', $role);
                        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) {
                            $success_message = "Utilisateur ajouté avec succès.";
                            // Réinitialiser le formulaire
                            $edit_user = [
                                'id' => '',
                                'username' => '',
                                'email' => '',
                                'first_name' => '',
                                'last_name' => '',
                                'role' => 'user',
                                'is_active' => 1
                            ];
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupérer la liste des utilisateurs
$users_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $users_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$active_filter = isset($_GET['active']) ? $_GET['active'] : '';

// Construction de la requête de recherche
$query = "SELECT id, username, email, first_name, last_name, role, is_active, last_login, created_at FROM users";
$count_query = "SELECT COUNT(*) FROM users";

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE :search OR email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}

if ($active_filter !== '') {
    $where_conditions[] = "is_active = :is_active";
    $params[':is_active'] = (int)$active_filter;
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
    $count_query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Récupérer le nombre total d'utilisateurs pour la pagination
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $users_per_page);

// Récupérer les utilisateurs pour la page actuelle
$query .= " ORDER BY created_at DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gestion des utilisateurs - Administration";

// Inclusion du header admin désactivée pour débogage
include "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque<?php echo $page_title ?? 'Administration'; ?></title>
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
                    <a href="users.php" class="block py-2 px-4 rounded bg-gray-700">
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
                        <h1 class="text-3xl font-bold">Gestion des utilisateurs</h1>
                        <a href="users.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Nouvel utilisateur
                        </a>
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

                    <?php if (isset($view_user)): ?>
                        <!-- Détails de l'utilisateur -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold">Détails de l'utilisateur</h2>
                                <div>
                                    <a href="users.php?action=edit&id=<?php echo $view_user['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded mr-2">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded">
                                        <i class="fas fa-arrow-left"></i> Retour
                                    </a>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="md:col-span-1">
                                    <div class="text-center mb-4">
                                        <?php if (!empty($view_user['profile_picture'])): ?>
                                            <img src="<?php echo $view_user['profile_picture']; ?>" alt="Photo de profil" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-blue-100">
                                        <?php else: ?>
                                            <div class="w-32 h-32 rounded-full bg-blue-100 flex items-center justify-center mx-auto border-4 border-blue-200">
                                                <span class="text-4xl text-blue-800"><?php echo strtoupper(substr($view_user['username'], 0, 1)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="bg-gray-100 p-4 rounded-lg">
                                        <h3 class="font-bold mb-2">Statistiques</h3>
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span>Livres dans la bibliothèque:</span>
                                                <span class="font-bold"><?php echo $books_count; ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Notes prises:</span>
                                                <span class="font-bold"><?php echo $notes_count; ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Commentaires:</span>
                                                <span class="font-bold"><?php echo $comments_count; ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Sessions de lecture:</span>
                                                <span class="font-bold"><?php echo $reading_sessions; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <h3 class="font-bold mb-2">Informations utilisateur</h3>
                                            <div class="space-y-2">
                                                <div>
                                                    <span class="text-gray-500">Nom d'utilisateur:</span>
                                                    <span class="font-semibold"><?php echo htmlspecialchars($view_user['username']); ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Email:</span>
                                                    <span class="font-semibold"><?php echo htmlspecialchars($view_user['email']); ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Nom complet:</span>
                                                    <span class="font-semibold">
                                                        <?php
                                                            $full_name = trim($view_user['first_name'] . ' ' . $view_user['last_name']);
                                                            echo !empty($full_name) ? htmlspecialchars($full_name) : 'Non défini';
                                                        ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Rôle:</span>
                                                    <span class="font-semibold"><?php echo $view_user['role'] == 'admin' ? 'Administrateur' : 'Utilisateur'; ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Statut:</span>
                                                    <span class="font-semibold <?php echo $view_user['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $view_user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <h3 class="font-bold mb-2">Dates</h3>
                                            <div class="space-y-2">
                                                <div>
                                                    <span class="text-gray-500">Membre depuis:</span>
                                                    <span class="font-semibold"><?php echo date('d/m/Y', strtotime($view_user['created_at'])); ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Dernière connexion:</span>
                                                    <span class="font-semibold">
                                                        <?php echo $view_user['last_login'] ? date('d/m/Y à H:i', strtotime($view_user['last_login'])) : 'Jamais'; ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Dernière mise à jour:</span>
                                                    <span class="font-semibold"><?php echo date('d/m/Y', strtotime($view_user['updated_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($recent_books)): ?>
                                        <div class="mb-6">
                                            <h3 class="font-bold mb-2">Derniers livres lus</h3>
                                            <div class="bg-gray-50 rounded-lg p-4">
                                                <ul class="space-y-2">
                                                    <?php foreach ($recent_books as $book): ?>
                                                        <li class="flex justify-between">
                                                            <a href="../book.php?id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                                <?php echo htmlspecialchars($book['title']); ?>
                                                            </a>
                                                            <span class="text-gray-500">
                                                                <?php echo $book['end_time'] ? date('d/m/Y', strtotime($book['end_time'])) : '-'; ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($recent_comments)): ?>
                                        <div>
                                            <h3 class="font-bold mb-2">Derniers commentaires</h3>
                                            <div class="space-y-3">
                                                <?php foreach ($recent_comments as $comment): ?>
                                                    <div class="bg-gray-50 rounded-lg p-4">
                                                        <div class="flex justify-between mb-2">
                                                            <span class="font-bold"><?php echo htmlspecialchars($comment['book_title']); ?></span>
                                                            <span class="text-gray-500 text-sm"><?php echo date('d/m/Y', strtotime($comment['created_at'])); ?></span>
                                                        </div>
                                                        <p class="text-gray-700">
                                                            "<?php echo htmlspecialchars(substr($comment['comment_text'], 0, 150)) . (strlen($comment['comment_text']) > 150 ? '...' : ''); ?>"
                                                        </p>
                                                        <div class="mt-2 text-yellow-500">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <?php if ($i <= $comment['rating']): ?>
                                                                    <i class="fas fa-star"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Formulaire d'utilisateur -->
                            <div class="bg-white rounded-lg shadow-md p-6">
                                <h2 class="text-xl font-bold mb-6">
                                    <?php echo $edit_user['id'] ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur'; ?>
                                </h2>
                                
                                <form method="POST" action="users.php">
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                    
                                    <div class="mb-4">
                                        <label for="username" class="block text-gray-700 font-bold mb-2">Nom d'utilisateur <span class="text-red-500">*</span></label>
                                        <input type="text" id="username" name="username" 
                                              value="<?php echo htmlspecialchars($edit_user['username']); ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="email" class="block text-gray-700 font-bold mb-2">Email <span class="text-red-500">*</span></label>
                                        <input type="email" id="email" name="email" 
                                              value="<?php echo htmlspecialchars($edit_user['email']); ?>"
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label for="first_name" class="block text-gray-700 font-bold mb-2">Prénom</label>
                                            <input type="text" id="first_name" name="first_name" 
                                                  value="<?php echo htmlspecialchars($edit_user['first_name']); ?>"
                                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label for="last_name" class="block text-gray-700 font-bold mb-2">Nom</label>
                                            <input type="text" id="last_name" name="last_name" 
                                                  value="<?php echo htmlspecialchars($edit_user['last_name']); ?>"
                                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label for="role" class="block text-gray-700 font-bold mb-2">Rôle</label>
                                            <select id="role" name="role" 
                                                   class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="user" <?php echo $edit_user['role'] == 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                                                <option value="admin" <?php echo $edit_user['role'] == 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="is_active" class="block text-gray-700 font-bold mb-2">Statut</label>
                                            <div class="flex items-center mt-2">
                                                <input type="checkbox" id="is_active" name="is_active" 
                                                      class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                      <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>>
                                                <label for="is_active" class="ml-2 text-gray-700">Compte actif</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-6">
                                        <label for="password" class="block text-gray-700 font-bold mb-2">
                                            <?php echo $edit_user['id'] ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe <span class="text-red-500">*</span>'; ?>
                                        </label>
                                        <input type="password" id="password" name="password" 
                                              class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              <?php echo !$edit_user['id'] ? 'required' : ''; ?>>
                                        <?php if ($edit_user['id']): ?>
                                            <p class="text-sm text-gray-500 mt-1">Minimum 6 caractères. Laissez vide si vous ne souhaitez pas modifier le mot de passe.</p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500 mt-1">Minimum 6 caractères.</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <button type="submit" name="save_user" 
                                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                            <?php echo $edit_user['id'] ? 'Mettre à jour' : 'Ajouter'; ?>
                                        </button>
                                        
                                        <?php if ($edit_user['id']): ?>
                                            <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                                                Annuler
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Liste des utilisateurs -->
                            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h2 class="text-xl font-bold">Liste des utilisateurs</h2>
                                    
                                    <form method="GET" action="users.php" class="flex items-center space-x-2">
                                        <input type="text" name="search" placeholder="Rechercher..." 
                                              value="<?php echo htmlspecialchars($search); ?>"
                                              class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        
                                        <select name="role" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Tous les rôles</option>
                                            <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Utilisateurs</option>
                                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateurs</option>
                                        </select>
                                        
                                        <select name="active" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Tous les statuts</option>
                                            <option value="1" <?php echo $active_filter === '1' ? 'selected' : ''; ?>>Actifs</option>
                                            <option value="0" <?php echo $active_filter === '0' ? 'selected' : ''; ?>>Inactifs</option>
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
                                                <th class="py-2 px-4 border-b text-left">Email</th>
                                                <th class="py-2 px-4 border-b text-center">Rôle</th>
                                                <th class="py-2 px-4 border-b text-center">Statut</th>
                                                <th class="py-2 px-4 border-b text-center">Date d'inscription</th>
                                                <th class="py-2 px-4 border-b text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="py-2 px-4 border-b">
                                                        <div class="flex items-center">
                                                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                                <span class="text-xs text-blue-800"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                                                            </div>
                                                            <div>
                                                                <div class="font-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                                <?php if (!empty($user['first_name']) || !empty($user['last_name'])): ?>
                                                                    <div class="text-xs text-gray-500">
                                                                        <?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $user['role'] == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                            <?php echo $user['role'] == 'admin' ? 'Admin' : 'Utilisateur'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center text-sm">
                                                        <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b text-center">
                                                        <a href="users.php?action=view&id=<?php echo $user['id']; ?>" class="text-green-600 hover:text-green-800 mx-1" title="Voir">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($user['is_active']): ?>
                                                            <a href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" class="text-orange-600 hover:text-orange-800 mx-1" title="Désactiver">
                                                                <i class="fas fa-user-slash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" class="text-green-600 hover:text-green-800 mx-1" title="Activer">
                                                                <i class="fas fa-user-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                                                            <a href="#" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')" class="text-red-600 hover:text-red-800 mx-1" title="Supprimer">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($users)): ?>
                                                <tr>
                                                    <td colspan="6" class="py-4 text-center text-gray-500">Aucun utilisateur trouvé</td>
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
                                                <a href="users.php?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&active=<?php echo urlencode($active_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                                <a href="users.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&active=<?php echo urlencode($active_filter); ?>" 
                                                   class="px-4 py-2 border rounded-lg <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($current_page < $total_pages): ?>
                                                <a href="users.php?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&active=<?php echo urlencode($active_filter); ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
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
        function confirmDelete(id, username) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ? Cette action supprimera également toutes les données associées (notes, commentaires, etc).')) {
                window.location.href = 'users.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>