<?php
// user/profile.php - Profil et paramètres de l'utilisateur
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Récupérer les informations sur l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement du formulaire de mise à jour du profil
$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Validation
    if (empty($username) || empty($email)) {
        $error_message = "Le nom d'utilisateur et l'email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Veuillez entrer une adresse email valide.";
    } else {
        // Vérifier si le nom d'utilisateur ou l'email existe déjà (sauf pour l'utilisateur actuel)
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error_message = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e).";
        } else {
            // Gestion de l'upload de photo de profil
            $profile_picture = $user['profile_picture']; // Garder l'ancienne photo par défaut
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
                $upload_dir = "../assets/uploads/profiles/";
                
                // Créer le dossier s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
                } else {
                    $new_filename = $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        $profile_picture = $upload_path;
                    } else {
                        $error_message = "Une erreur est survenue lors de l'upload de la photo de profil.";
                    }
                }
            }
            
            if (empty($error_message)) {
                // Mettre à jour les informations de l'utilisateur
                $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email, first_name = :first_name, last_name = :last_name, profile_picture = :profile_picture WHERE id = :user_id");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':profile_picture', $profile_picture);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    // Mettre à jour la session avec le nouveau nom d'utilisateur
                    $_SESSION['username'] = $username;
                    
                    $success_message = "Votre profil a été mis à jour avec succès.";
                    
                    // Mettre à jour les informations de l'utilisateur
                    $user['username'] = $username;
                    $user['email'] = $email;
                    $user['first_name'] = $first_name;
                    $user['last_name'] = $last_name;
                    $user['profile_picture'] = $profile_picture;
                } else {
                    $error_message = "Une erreur est survenue lors de la mise à jour du profil.";
                }
            }
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Tous les champs sont obligatoires.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
        // Vérifier le mot de passe actuel
        if (password_verify($current_password, $user['password'])) {
            // Hasher le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Mettre à jour le mot de passe
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Votre mot de passe a été modifié avec succès.";
            } else {
                $error_message = "Une erreur est survenue lors de la modification du mot de passe.";
            }
        } else {
            $error_message = "Le mot de passe actuel est incorrect.";
        }
    }
}

$page_title = "Mon Profil";
include "../includes/header.php";
?>

<!-- Script pour initialiser le mode sombre correctement -->
<script>
    // Applique immédiatement le thème sombre si nécessaire
    (function() {
        if (localStorage.getItem('theme') === 'dark' || 
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

<!-- Style personnalisé -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8fafc;
    }
    
    .profile-card {
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-radius: 12px;
        overflow: hidden;
        background: white;
        position: relative;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .empty-state {
        background: linear-gradient(135deg, #f6f9fc 0%, #eef2f5 100%);
        border-radius: 16px;
    }
    
    /* Animation pour les cartes */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .profile-card {
        animation: fadeIn 0.5s ease forwards;
    }
    
    /* Style pour le dark mode */
    .dark .profile-card {
        background: #1e293b;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }
    
    .dark .profile-card input,
    .dark .profile-card textarea,
    .dark .profile-card select {
        background-color: #334155;
        border-color: #475569;
        color: #f8fafc;
    }
    
    .dark .profile-card input::placeholder,
    .dark .profile-card textarea::placeholder {
        color: #94a3b8;
    }
    
    .dark .empty-state {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    }
</style>


<!-- Script pour initialiser le mode sombre correctement -->
<script>
    // Applique immédiatement le thème sombre si nécessaire
    (function() {
        if (localStorage.getItem('theme') === 'dark' || 
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

<div class="container mx-auto px-4 py-8">
    <!-- Header avec titre et boutons -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div class="mb-4 md:mb-0">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 dark:text-white">Mon Profil</h1>
            <p class="text-gray-600 dark:text-gray-300">Gérez vos informations personnelles et vos préférences</p>
        </div>
    </div>
    
    <!-- Messages d'alerte -->
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md dark:bg-green-900 dark:text-green-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium"><?php echo $success_message; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md dark:bg-red-900 dark:text-red-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium"><?php echo $error_message; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Informations et statistiques -->
        <div class="lg:col-span-1">
            <!-- Profil -->
            <div class="profile-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-6">
                <div class="text-center mb-6">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo $user['profile_picture']; ?>" alt="Photo de profil" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-blue-100 dark:border-blue-900">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mx-auto border-4 border-blue-200 dark:border-blue-800">
                            <span class="text-4xl text-blue-800 dark:text-blue-200"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <h2 class="text-xl font-bold mt-4 text-gray-800 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        <?php 
                            $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                            echo !empty($full_name) ? htmlspecialchars($full_name) : 'Membre';
                        ?>
                    </p>
                </div>
                
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="font-bold mb-4 text-gray-800 dark:text-white">Mes informations</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Email:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Rôle:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo $user['role'] == 'admin' ? 'Administrateur' : 'Membre'; ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Membre depuis:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Dernière connexion:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                <?php echo $user['last_login'] ? date('d/m/Y à H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="profile-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                <h3 class="font-bold mb-4 text-gray-800 dark:text-white">Statistiques</h3>
                
                <?php
                    // Récupérer quelques statistiques
                    $books_count = $conn->query("SELECT COUNT(*) FROM user_library WHERE user_id = $user_id")->fetchColumn();
                    $notes_count = $conn->query("SELECT COUNT(*) FROM notes WHERE user_id = $user_id")->fetchColumn();
                    $comments_count = $conn->query("SELECT COUNT(*) FROM comments WHERE user_id = $user_id")->fetchColumn();
                    $favorite_books = $conn->query("SELECT COUNT(*) FROM user_library WHERE user_id = $user_id AND is_favorite = 1")->fetchColumn();
                    
                    // Calcul du nombre de livres terminés
                    $completed_books = 0;
                    $stmt = $conn->prepare("
                        SELECT b.*, ul.last_page_read 
                        FROM user_library ul 
                        JOIN books b ON ul.book_id = b.id 
                        WHERE ul.user_id = :user_id
                    ");
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($books as $book) {
                        if (isset($book['pages_count']) && $book['pages_count'] > 0) {
                            $progress = ($book['last_page_read'] / $book['pages_count']) * 100;
                            if ($progress >= 99.5 || $book['last_page_read'] >= $book['pages_count']) {
                                $completed_books++;
                            }
                        }
                    }
                ?>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-300"><?php echo $books_count; ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Livres</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-300"><?php echo $completed_books; ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Terminés</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-300"><?php echo $notes_count; ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Notes</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-300"><?php echo $favorite_books; ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Favoris</div>
                    </div>
                </div>
                
                <div class="flex justify-around border-t border-gray-200 dark:border-gray-700 pt-4">
                    <a href="my-library.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center">
                        <i class="fas fa-book-open mr-1"></i> Ma bibliothèque
                    </a>
                    <a href="notes.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center">
                        <i class="fas fa-sticky-note mr-1"></i> Mes notes
                    </a>
                    <a href="comments.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center">
                        <i class="fas fa-comments mr-1"></i> Commentaires
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulaires -->
        <div class="lg:col-span-2">
            <!-- Formulaire de mise à jour du profil -->
            <div class="profile-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold mb-6 text-gray-800 dark:text-white">Modifier mon profil</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="username" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Nom d'utilisateur <span class="text-red-500">*</span></label>
                            <input type="text" id="username" name="username" 
                                  value="<?php echo htmlspecialchars($user['username']); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" 
                                  value="<?php echo htmlspecialchars($user['email']); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        
                        <div>
                            <label for="first_name" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Prénom</label>
                            <input type="text" id="first_name" name="first_name" 
                                  value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Nom</label>
                            <input type="text" id="last_name" name="last_name" 
                                  value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="profile_picture" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Photo de profil</label>
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?php echo $user['profile_picture']; ?>" alt="Aperçu" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                                            <i class="fas fa-user text-2xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <input type="file" id="profile_picture" name="profile_picture" 
                                          class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                          accept="image/jpeg,image/png,image/gif">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Formats acceptés: JPG, PNG, GIF. Taille max: 2 Mo.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i> Mettre à jour mon profil
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Formulaire de changement de mot de passe -->
            <div class="profile-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                <h2 class="text-xl font-bold mb-6 text-gray-800 dark:text-white">Changer mon mot de passe</h2>
                
                <form method="POST">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label for="current_password" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Mot de passe actuel <span class="text-red-500">*</span></label>
                            <input type="password" id="current_password" name="current_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Nouveau mot de passe <span class="text-red-500">*</span></label>
                            <input type="password" id="new_password" name="new_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Minimum 6 caractères.</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-gray-700 dark:text-gray-300 font-bold mb-2">Confirmer le nouveau mot de passe <span class="text-red-500">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="change_password" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-key mr-2"></i> Changer mon mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        // Gestion du bouton de mode sombre
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            // Supprime les gestionnaires d'événements existants
            const newThemeToggle = themeToggle.cloneNode(true);
            if (themeToggle.parentNode) {
                themeToggle.parentNode.replaceChild(newThemeToggle, themeToggle);
            }
            
            // Ajoute un nouveau gestionnaire d'événements
            newThemeToggle.addEventListener('click', function() {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                
                // Mise à jour de l'icône
                if (document.documentElement.classList.contains('dark')) {
                    this.innerHTML = '<i class="fas fa-sun"></i>';
                } else {
                    this.innerHTML = '<i class="fas fa-moon"></i>';
                }
                
                // Force le rafraîchissement des styles pour les éléments
                document.body.classList.add('theme-transition');
                setTimeout(() => {
                    document.body.classList.remove('theme-transition');
                }, 100);
            });
        }
        
        // Assurez-vous que l'icône du thème est correcte au chargement
        function updateThemeIcon() {
            if (themeToggle) {
                if (document.documentElement.classList.contains('dark')) {
                    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                } else {
                    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                }
            }
        }
        updateThemeIcon();
        
        // Aperçu de l'image de profil
        const profilePictureInput = document.getElementById('profile_picture');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Trouver l'élément d'aperçu
                        const previewContainer = profilePictureInput.closest('div.md\\:col-span-2').querySelector('.w-16.h-16');
                        if (previewContainer) {
                            // Créer ou mettre à jour l'image d'aperçu
                            let img = previewContainer.querySelector('img');
                            if (!img) {
                                previewContainer.innerHTML = '';
                                img = document.createElement('img');
                                img.className = 'w-full h-full object-cover';
                                previewContainer.appendChild(img);
                            }
                            img.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Validation du formulaire de mot de passe
        const passwordForm = document.querySelector('form[name="change_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Les mots de passe ne correspondent pas.');
                }
            });
        }
        
        // Animation pour les cartes
        const profileCards = document.querySelectorAll('.profile-card');
        profileCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    });
</script>

<?php include "../includes/footer.php"; ?>