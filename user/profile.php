<?php
// user/profile.php - Profil et paramètres de l'utilisateur MODERNE
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

// Récupérer les statistiques
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

// Corriger le chemin de l'image de profil
$profile_picture_path = '';
if (!empty($user['profile_picture'])) {
    $profile_picture_path = $user['profile_picture'];
    if (strpos($profile_picture_path, '../') === 0) {
        $profile_picture_path = substr($profile_picture_path, 3);
    }
}

$page_title = "Mon Profil";
include "../includes/header.php";
?>

<style>
/* Styles modernes pour la page de profil */
.hero-profile {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.hero-profile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
    background-size: cover;
}

.dark .hero-profile {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.dark .glass-card {
    background: rgba(31, 41, 55, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.dark .glass-card:hover {
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.profile-avatar {
    position: relative;
    display: inline-block;
}

.profile-avatar::before {
    content: '';
    position: absolute;
    inset: -4px;
    background: linear-gradient(45deg, #3b82f6, #8b5cf6, #06b6d4, #10b981);
    border-radius: 50%;
    z-index: -1;
    animation: rotate 3s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dark .stat-card {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, currentColor, transparent);
    transition: left 0.5s ease;
}

.stat-card:hover::before {
    left: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.dark .stat-card:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.form-input {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(59, 130, 246, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    transition: all 0.3s ease;
    font-size: 16px;
}

.dark .form-input {
    background: rgba(55, 65, 81, 0.9);
    border-color: rgba(156, 163, 175, 0.2);
    color: #f8fafc;
}

.form-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    outline: none;
    transform: translateY(-2px);
}

.btn-modern {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
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

.alert-modern {
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
    border-left: 4px solid currentColor;
    position: relative;
    overflow: hidden;
}

.alert-success {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #065f46;
    border-left-color: #10b981;
}

.dark .alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
    color: #6ee7b7;
}

.alert-error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #991b1b;
    border-left-color: #ef4444;
}

.dark .alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
    color: #fca5a5;
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

.floating-animation {
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.profile-info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
    transition: all 0.3s ease;
    gap: 8px;
}

.dark .profile-info-item {
    border-bottom-color: rgba(75, 85, 99, 0.5);
}

.profile-info-item:hover {
    background: rgba(59, 130, 246, 0.05);
    margin: 0 -16px;
    padding-left: 16px;
    padding-right: 16px;
    border-radius: 8px;
}

.profile-info-item span:first-child {
    flex-shrink: 0;
    min-width: 100px;
}

.profile-info-item span:last-child {
    flex: 1;
    min-width: 0;
    text-align: right;
}

.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.dark .upload-area {
    border-color: #4b5563;
}

.upload-area:hover {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.05);
}
</style>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <!-- Hero Section avec profil -->
    <section class="hero-profile py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center text-white">
                <!-- Avatar principal -->
                <div class="profile-avatar mb-6">
                    <?php if (!empty($profile_picture_path)): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
                             alt="Photo de profil" 
                             class="w-32 h-32 rounded-full object-cover border-4 border-white/20 shadow-2xl"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/20 shadow-2xl" style="display:none;">
                            <span class="text-4xl font-bold"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/20 shadow-2xl">
                            <span class="text-4xl font-bold"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <h1 class="text-4xl lg:text-5xl font-bold mb-4">
                    <?php echo htmlspecialchars($user['username']); ?>
                </h1>
                
                <p class="text-xl lg:text-2xl mb-6 opacity-90">
                    <?php 
                    $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    echo !empty($full_name) ? htmlspecialchars($full_name) : 'Membre de la bibliothèque';
                    ?>
                </p>

                <div class="flex flex-wrap justify-center gap-4 text-sm">
                    <div class="bg-white/20 backdrop-blur-sm rounded-full px-4 py-2">
                        <i class="fas fa-envelope mr-2"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-full px-4 py-2">
                        <i class="fas fa-calendar mr-2"></i>
                        Membre depuis <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </div>
                    <?php if ($user['role'] == 'admin'): ?>
                        <div class="bg-yellow-500/20 backdrop-blur-sm rounded-full px-4 py-2">
                            <i class="fas fa-crown mr-2"></i>
                            Administrateur
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-12">
        <!-- Messages d'alerte -->
        <?php if (!empty($success_message)): ?>
            <div class="alert-modern alert-success slide-up">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl mr-4"></i>
                    <div>
                        <p class="font-semibold"><?php echo $success_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert-modern alert-error slide-up">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-2xl mr-4"></i>
                    <div>
                        <p class="font-semibold"><?php echo $error_message; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Colonne gauche : Statistiques et informations -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Statistiques -->
                <div class="glass-card rounded-3xl p-8 slide-up floating-animation">
                    <h3 class="text-2xl font-bold mb-8 text-gray-800 dark:text-white text-center">
                        <i class="fas fa-chart-bar mr-3 text-blue-600 dark:text-blue-400"></i>
                        Mes Statistiques
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="stat-card text-blue-600 dark:text-blue-400">
                            <div class="text-3xl font-bold mb-2"><?php echo $books_count; ?></div>
                            <div class="text-sm opacity-80">Livres</div>
                        </div>
                        <div class="stat-card text-green-600 dark:text-green-400">
                            <div class="text-3xl font-bold mb-2"><?php echo $completed_books; ?></div>
                            <div class="text-sm opacity-80">Terminés</div>
                        </div>
                        <div class="stat-card text-purple-600 dark:text-purple-400">
                            <div class="text-3xl font-bold mb-2"><?php echo $notes_count; ?></div>
                            <div class="text-sm opacity-80">Notes</div>
                        </div>
                        <div class="stat-card text-yellow-600 dark:text-yellow-400">
                            <div class="text-3xl font-bold mb-2"><?php echo $favorite_books; ?></div>
                            <div class="text-sm opacity-80">Favoris</div>
                        </div>
                    </div>
                </div>

                <!-- Informations du profil -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h3 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white">
                        <i class="fas fa-info-circle mr-3 text-green-600 dark:text-green-400"></i>
                        Informations
                    </h3>
                    
                    <div class="space-y-2">
                        <div class="profile-info-item">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">
                                <i class="fas fa-user mr-2"></i>Nom d'utilisateur
                            </span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </span>
                        </div>
                        <div class="profile-info-item">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200 truncate text-sm lg:text-base">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </span>
                        </div>
                        <div class="profile-info-item">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">
                                <i class="fas fa-calendar mr-2"></i>Inscription
                            </span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                        <?php if ($user['last_login']): ?>
                            <div class="profile-info-item">
                                <span class="text-gray-600 dark:text-gray-400 font-medium">
                                    <i class="fas fa-clock mr-2"></i>Dernière connexion
                                </span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    <?php echo date('d/m/Y', strtotime($user['last_login'])); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Liens rapides -->
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="grid grid-cols-1 gap-3">
                            <a href="my-library.php" class="flex items-center justify-between p-3 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-all">
                                <span><i class="fas fa-book-open mr-2"></i>Ma bibliothèque</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                            <a href="notes.php" class="flex items-center justify-between p-3 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-all">
                                <span><i class="fas fa-sticky-note mr-2"></i>Mes notes</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                            <a href="comments.php" class="flex items-center justify-between p-3 rounded-xl bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/50 transition-all">
                                <span><i class="fas fa-comments mr-2"></i>Mes commentaires</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : Formulaires -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Formulaire de mise à jour du profil -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h2 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white">
                        <i class="fas fa-user-edit mr-3 text-blue-600 dark:text-blue-400"></i>
                        Modifier mon profil
                    </h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Photo de profil -->
                        <div class="text-center mb-8">
                            <div class="upload-area" onclick="document.getElementById('profile_picture').click()">
                                <div class="flex flex-col items-center">
                                    <div class="w-24 h-24 rounded-full overflow-hidden mb-4 bg-gray-100 dark:bg-gray-700">
                                        <?php if (!empty($profile_picture_path)): ?>
                                            <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
                                                 alt="Aperçu" class="w-full h-full object-cover"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="w-full h-full flex items-center justify-center text-gray-400" style="display:none;">
                                                <i class="fas fa-user text-2xl"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                <i class="fas fa-user text-3xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 dark:text-gray-400 font-medium">
                                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                                        Cliquez pour changer votre photo
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                                        JPG, PNG, GIF - Max 2 Mo
                                    </p>
                                </div>
                            </div>
                            <input type="file" id="profile_picture" name="profile_picture" 
                                   class="hidden" accept="image/jpeg,image/png,image/gif">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="username" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-user mr-2 text-blue-600 dark:text-blue-400"></i>
                                    Nom d'utilisateur <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="username" name="username" 
                                      value="<?php echo htmlspecialchars($user['username']); ?>"
                                      class="form-input w-full" required>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-envelope mr-2 text-green-600 dark:text-green-400"></i>
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" 
                                      value="<?php echo htmlspecialchars($user['email']); ?>"
                                      class="form-input w-full" required>
                            </div>
                            
                            <div>
                                <label for="first_name" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-id-card mr-2 text-purple-600 dark:text-purple-400"></i>
                                    Prénom
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                      value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                      class="form-input w-full">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-id-card mr-2 text-orange-600 dark:text-orange-400"></i>
                                    Nom
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                      value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                      class="form-input w-full">
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-6">
                            <button type="submit" name="update_profile" class="btn-modern">
                                <i class="fas fa-save mr-2"></i> Mettre à jour mon profil
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Formulaire de changement de mot de passe -->
                <div class="glass-card rounded-3xl p-8 slide-up">
                    <h2 class="text-3xl font-bold mb-8 text-gray-800 dark:text-white">
                        <i class="fas fa-lock mr-3 text-red-600 dark:text-red-400"></i>
                        Changer mon mot de passe
                    </h2>
                    
                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="current_password" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                <i class="fas fa-key mr-2 text-gray-600 dark:text-gray-400"></i>
                                Mot de passe actuel <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="current_password" name="current_password" 
                                  class="form-input w-full" required 
                                  placeholder="Entrez votre mot de passe actuel">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="new_password" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-lock mr-2 text-blue-600 dark:text-blue-400"></i>
                                    Nouveau mot de passe <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="new_password" name="new_password" 
                                      class="form-input w-full" required 
                                      placeholder="Minimum 6 caractères">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Minimum 6 caractères
                                </p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-gray-700 dark:text-gray-300 font-semibold mb-3">
                                    <i class="fas fa-check-circle mr-2 text-green-600 dark:text-green-400"></i>
                                    Confirmer le mot de passe <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                      class="form-input w-full" required 
                                      placeholder="Répétez le nouveau mot de passe">
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-2xl p-4">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mr-3 mt-1"></i>
                                <div class="text-yellow-800 dark:text-yellow-200">
                                    <p class="font-semibold mb-1">Conseils de sécurité</p>
                                    <ul class="text-sm space-y-1">
                                        <li>• Utilisez au moins 8 caractères</li>
                                        <li>• Mélangez lettres, chiffres et symboles</li>
                                        <li>• Évitez les mots du dictionnaire</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-6">
                            <button type="submit" name="change_password" class="btn-modern bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700">
                                <i class="fas fa-key mr-2"></i> Changer mon mot de passe
                            </button>
                        </div>
                    </form>
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

    // Aperçu de l'image de profil
    const profilePictureInput = document.getElementById('profile_picture');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Vérifier la taille du fichier (2 Mo max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux. Taille maximum : 2 Mo');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    // Mettre à jour tous les aperçus d'image
                    const previews = document.querySelectorAll('img[alt="Aperçu"], img[alt="Photo de profil"]');
                    previews.forEach(img => {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        // Masquer le placeholder s'il existe
                        const placeholder = img.nextElementSibling;
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    });

                    // Mettre à jour l'aperçu dans la zone d'upload
                    const uploadPreview = document.querySelector('.upload-area img, .upload-area .w-full.h-full.flex');
                    if (uploadPreview) {
                        if (uploadPreview.tagName === 'IMG') {
                            uploadPreview.src = e.target.result;
                        } else {
                            // Créer une nouvelle image
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.className = 'w-full h-full object-cover';
                            newImg.alt = 'Aperçu';
                            uploadPreview.parentNode.replaceChild(newImg, uploadPreview);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Validation du formulaire de mot de passe
    const passwordForm = document.querySelector('form[method="POST"]:has([name="change_password"])');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                
                // Animation d'erreur
                const confirmInput = document.getElementById('confirm_password');
                confirmInput.style.borderColor = '#ef4444';
                confirmInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                
                // Message d'erreur moderne
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert-modern alert-error mt-4';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-2xl mr-4"></i>
                        <p class="font-semibold">Les mots de passe ne correspondent pas.</p>
                    </div>
                `;
                
                // Supprimer l'ancien message d'erreur s'il existe
                const existingError = passwordForm.querySelector('.alert-error');
                if (existingError) {
                    existingError.remove();
                }
                
                passwordForm.insertBefore(errorDiv, passwordForm.firstChild);
                
                // Faire défiler vers l'erreur
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Supprimer l'erreur après 5 secondes
                setTimeout(() => {
                    errorDiv.remove();
                    confirmInput.style.borderColor = '';
                    confirmInput.style.boxShadow = '';
                }, 5000);
            }
        });

        // Validation en temps réel
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (newPasswordInput.value && confirmPasswordInput.value) {
                if (newPasswordInput.value === confirmPasswordInput.value) {
                    confirmPasswordInput.style.borderColor = '#10b981';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.1)';
                } else {
                    confirmPasswordInput.style.borderColor = '#ef4444';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                }
            }
        }
        
        newPasswordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    }

    // Animation des statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
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

    // Animation des champs de formulaire au focus
    const formInputs = document.querySelectorAll('.form-input');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Démarrer les animations après un délai
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 200);
        });
    }, 300);
});
</script>

<?php include "../includes/footer.php"; ?>