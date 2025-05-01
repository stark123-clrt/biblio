<?php
// admin/categories.php - Gestion des catégories
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification admin désactivée temporairement pour débogage
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     header("Location: ../login.php?message=unauthorized");
//     exit();
// }

require_once "../config/database.php";

// Messages de feedback
$success_message = "";
$error_message = "";

// Définir la catégorie en cours d'édition
$edit_category = [
    'id' => '',
    'name' => '',
    'description' => '',
    'parent_id' => null
];

// Traitement de la suppression d'une catégorie
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    // Vérifier si la catégorie existe
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        try {
            // Vérifier si la catégorie est utilisée comme parent
            $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = :id");
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            $stmt->execute();
            $has_children = $stmt->fetchColumn() > 0;
            
            if ($has_children) {
                $error_message = "Cette catégorie ne peut pas être supprimée car elle est utilisée comme catégorie parente.";
            } else {
                // Vérifier si des livres sont associés à cette catégorie
                $stmt = $conn->prepare("SELECT COUNT(*) FROM book_categories WHERE category_id = :id");
                $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
                $stmt->execute();
                $has_books = $stmt->fetchColumn() > 0;
                
                if ($has_books && !isset($_GET['force'])) {
                    $error_message = "Des livres sont associés à cette catégorie. <a href='categories.php?action=delete&id={$category_id}&force=1' class='text-red-600 hover:text-red-800'>Forcer la suppression</a>";
                } else {
                    // Supprimer les relations livre-catégorie si elles existent
                    if ($has_books) {
                        $stmt = $conn->prepare("DELETE FROM book_categories WHERE category_id = :id");
                        $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                    
                    // Supprimer la catégorie
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
                    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success_message = "La catégorie \"" . htmlspecialchars($category['name']) . "\" a été supprimée avec succès.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la suppression de la catégorie : " . $e->getMessage();
        }
    } else {
        $error_message = "Catégorie introuvable.";
    }
}

// Récupérer une catégorie pour l'édition
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $edit_category = $cat;
    } else {
        $error_message = "Catégorie introuvable.";
    }
}

// Traitement du formulaire d'ajout/modification de catégorie
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    
    // Validation basique
    if (empty($name)) {
        $error_message = "Le nom de la catégorie est obligatoire.";
    } else {
        try {
            // Vérifier si le nom existe déjà (sauf pour la catégorie en cours d'édition)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id != :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Une catégorie avec ce nom existe déjà.";
            } else {
                // Vérifier les boucles de parenté pour éviter les références circulaires
                if ($category_id && $parent_id) {
                    // Vérifier si on essaie de définir comme parent une de ses propres sous-catégories
                    $is_child = false;
                    $check_id = $parent_id;
                    
                    while ($check_id && !$is_child) {
                        $stmt = $conn->prepare("SELECT parent_id FROM categories WHERE id = :id");
                        $stmt->bindParam(':id', $check_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $check_parent = $stmt->fetchColumn();
                        
                        if ($check_parent == $category_id) {
                            $is_child = true;
                        }
                        $check_id = $check_parent;
                    }
                    
                    if ($is_child) {
                        $error_message = "Une catégorie ne peut pas avoir une de ses sous-catégories comme parent.";
                    }
                }
                
                if (empty($error_message)) {
                    if ($category_id) {
                        // Mise à jour
                        $stmt = $conn->prepare("UPDATE categories SET name = :name, description = :description, parent_id = :parent_id WHERE id = :id");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':parent_id', $parent_id);
                        $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) {
                            $success_message = "Catégorie mise à jour avec succès.";
                            // Réinitialiser le formulaire
                            $edit_category = [
                                'id' => '',
                                'name' => '',
                                'description' => '',
                                'parent_id' => null
                            ];
                        }
                    } else {
                        // Ajout
                        $stmt = $conn->prepare("INSERT INTO categories (name, description, parent_id) VALUES (:name, :description, :parent_id)");
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':parent_id', $parent_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "Catégorie ajoutée avec succès.";
                            // Réinitialiser le formulaire
                            $edit_category = [
                                'id' => '',
                                'name' => '',
                                'description' => '',
                                'parent_id' => null
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

// Récupérer toutes les catégories pour l'affichage
$stmt = $conn->prepare("SELECT c.*, 
                      (SELECT COUNT(*) FROM book_categories WHERE category_id = c.id) as book_count,
                      (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as children_count,
                      p.name as parent_name
                      FROM categories c
                      LEFT JOIN categories p ON c.parent_id = p.id
                      ORDER BY c.name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les catégories pour le menu déroulant (sauf la catégorie en cours d'édition)
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE id != :id ORDER BY name ASC");
$stmt->bindParam(':id', $edit_category['id']);
$stmt->execute();
$category_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gestion des catégories - Administration";

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
                    <a href="categories.php" class="block py-2 px-4 rounded bg-gray-700">
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
                        <h1 class="text-3xl font-bold">Gestion des catégories</h1>
                        <a href="categories.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Nouvelle catégorie
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

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Formulaire de catégorie -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-bold mb-6">
                                <?php echo $edit_category['id'] ? 'Modifier la catégorie' : 'Ajouter une catégorie'; ?>
                            </h2>
                            
                            <form method="POST" action="categories.php">
                                <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                                
                                <div class="mb-4">
                                    <label for="name" class="block text-gray-700 font-bold mb-2">Nom de la catégorie <span class="text-red-500">*</span></label>
                                    <input type="text" id="name" name="name" 
                                          value="<?php echo htmlspecialchars($edit_category['name']); ?>"
                                          class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                                    <textarea id="description" name="description" rows="4"
                                             class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="parent_id" class="block text-gray-700 font-bold mb-2">Catégorie parente</label>
                                    <select id="parent_id" name="parent_id"
                                           class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Aucune (catégorie principale)</option>
                                        <?php foreach ($category_options as $option): ?>
                                            <option value="<?php echo $option['id']; ?>" <?php echo $edit_category['parent_id'] == $option['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex justify-between">
                                    <button type="submit" name="save_category" 
                                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                        <?php echo $edit_category['id'] ? 'Mettre à jour' : 'Ajouter'; ?>
                                    </button>
                                    
                                    <?php if ($edit_category['id']): ?>
                                        <a href="categories.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                                            Annuler
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Liste des catégories -->
                        <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-bold mb-6">Liste des catégories</h2>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="py-2 px-4 border-b text-left">Nom</th>
                                            <th class="py-2 px-4 border-b text-left">Catégorie parente</th>
                                            <th class="py-2 px-4 border-b text-center">Livres</th>
                                            <th class="py-2 px-4 border-b text-center">Sous-catégories</th>
                                            <th class="py-2 px-4 border-b text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 px-4 border-b">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <?php echo $category['parent_name'] ? htmlspecialchars($category['parent_name']) : '<span class="text-gray-400">-</span>'; ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <?php echo $category['book_count']; ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <?php echo $category['children_count']; ?>
                                                </td>
                                                <td class="py-2 px-4 border-b text-center">
                                                    <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <a href="#" onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')" class="text-red-600 hover:text-red-800 mx-1">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($categories)): ?>
                                            <tr>
                                                <td colspan="5" class="py-4 text-center text-gray-500">Aucune catégorie disponible</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Pied de page -->
        <footer class="bg-gray-800 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?php echo date('Y'); ?> Bibliothèque Chrétienne - Interface d'administration</p>
            </div>
        </footer>
    </div>

    <script>
        function confirmDelete(id, name) {
            if (confirm('Êtes-vous sûr de vouloir supprimer la catégorie "' + name + '" ?')) {
                window.location.href = 'categories.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>