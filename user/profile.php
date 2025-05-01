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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Mon Profil</h1>
    
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
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Informations et statistiques -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="text-center mb-6">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo $user['profile_picture']; ?>" alt="Photo de profil" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-blue-100">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-blue-100 flex items-center justify-center mx-auto border-4 border-blue-200">
                            <span class="text-4xl text-blue-800"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <h2 class="text-xl font-bold mt-4"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="text-gray-600">
                        <?php 
                            $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                            echo !empty($full_name) ? htmlspecialchars($full_name) : 'Membre';
                        ?>
                    </p>
                </div>
                
                <div class="border-t pt-4">
                    <h3 class="font-bold mb-4">Mes informations</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-gray-500">Email:</span>
                            <span class="font-semibold"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Rôle:</span>
                            <span class="font-semibold"><?php echo $user['role'] == 'admin' ? 'Administrateur' : 'Membre'; ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Membre depuis:</span>
                            <span class="font-semibold"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Dernière connexion:</span>
                            <span class="font-semibold">
                                <?php echo $user['last_login'] ? date('d/m/Y à H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="font-bold mb-4">Statistiques</h3>
                
                <?php
                    // Récupérer quelques statistiques
                    $books_count = $conn->query("SELECT COUNT(*) FROM user_library WHERE user_id = $user_id")->fetchColumn();
                    $notes_count = $conn->query("SELECT COUNT(*) FROM notes WHERE user_id = $user_id")->fetchColumn();
                    $comments_count = $conn->query("SELECT COUNT(*) FROM comments WHERE user_id = $user_id")->fetchColumn();
                    $favorite_books = $conn->query("SELECT COUNT(*) FROM user_library WHERE user_id = $user_id AND is_favorite = 1")->fetchColumn();
                ?>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Livres dans ma bibliothèque:</span>
                        <span class="font-semibold"><?php echo $books_count; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Notes prises:</span>
                        <span class="font-semibold"><?php echo $notes_count; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Commentaires postés:</span>
                        <span class="font-semibold"><?php echo $comments_count; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Livres favoris:</span>
                        <span class="font-semibold"><?php echo $favorite_books; ?></span>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-around">
                    <a href="my-library.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-books mr-1"></i> Ma bibliothèque
                    </a>
                    <a href="notes.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-sticky-note mr-1"></i> Mes notes
                    </a>
                    <a href="comments.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-comments mr-1"></i> Mes commentaires
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Formulaires -->
        <div class="lg:col-span-2">
            <!-- Formulaire de mise à jour du profil -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold mb-6">Modifier mon profil</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="username" class="block text-gray-700 font-bold mb-2">Nom d'utilisateur <span class="text-red-500">*</span></label>
                            <input type="text" id="username" name="username" 
                                  value="<?php echo htmlspecialchars($user['username']); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 font-bold mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" 
                                  value="<?php echo htmlspecialchars($user['email']); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label for="first_name" class="block text-gray-700 font-bold mb-2">Prénom</label>
                            <input type="text" id="first_name" name="first_name" 
                                  value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-gray-700 font-bold mb-2">Nom</label>
                            <input type="text" id="last_name" name="last_name" 
                                  value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="profile_picture" class="block text-gray-700 font-bold mb-2">Photo de profil</label>
                            <input type="file" id="profile_picture" name="profile_picture" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  accept="image/jpeg,image/png,image/gif">
                            <p class="text-sm text-gray-500 mt-1">Formats acceptés: JPG, PNG, GIF. Taille max: 2 Mo.</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Mettre à jour mon profil
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Formulaire de changement de mot de passe -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-6">Changer mon mot de passe</h2>
                
                <form method="POST">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label for="current_password" class="block text-gray-700 font-bold mb-2">Mot de passe actuel <span class="text-red-500">*</span></label>
                            <input type="password" id="current_password" name="current_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-gray-700 font-bold mb-2">Nouveau mot de passe <span class="text-red-500">*</span></label>
                            <input type="password" id="new_password" name="new_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <p class="text-sm text-gray-500 mt-1">Minimum 6 caractères.</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirmer le nouveau mot de passe <span class="text-red-500">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                  class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="change_password" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Changer mon mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>