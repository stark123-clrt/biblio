<?php
// register.php - Version 100% POO avec séparation MVC complète + Vérification d'email
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ REDIRECTION SI DÉJÀ CONNECTÉ
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";
require_once "classes/EmailService.php"; // ✅ NOUVEAU : Service d'email

try {
    // ✅ INITIALISER LE REPOSITORY ET SERVICE EMAIL
    $userRepository = new UserRepository();
    $emailService = new EmailService(); // ✅ NOUVEAU : Service d'email
    
    // ✅ VARIABLES POUR LA VUE
    $error = "";
    $success = "";
    $form_data = [
        'username' => '',
        'email' => '',
        'first_name' => '',
        'last_name' => ''
    ];
    
    // ✅ TRAITEMENT DU FORMULAIRE
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // ✅ RÉCUPÉRER ET NETTOYER LES DONNÉES
        $form_data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'terms' => isset($_POST['terms'])
        ];
        
        // ✅ VALIDATION AVEC LOGIQUE MÉTIER
        if (empty($form_data['username']) || empty($form_data['email']) || 
            empty($form_data['password']) || empty($form_data['confirm_password'])) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (strlen($form_data['username']) < 3 || strlen($form_data['username']) > 50) {
            $error = "Le nom d'utilisateur doit contenir entre 3 et 50 caractères.";
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez entrer une adresse email valide.";
        } elseif (strlen($form_data['password']) < config('security.password_min_length', 6)) {
            $error = "Le mot de passe doit contenir au moins " . config('security.password_min_length', 6) . " caractères.";
        } elseif ($form_data['password'] !== $form_data['confirm_password']) {
            $error = "Les mots de passe ne correspondent pas.";
        } elseif (!$form_data['terms']) {
            $error = "Vous devez accepter les conditions d'utilisation.";
        } else {
            // ✅ VÉRIFIER L'UNICITÉ AVEC LE REPOSITORY
            if ($userRepository->existsByEmailOrUsername($form_data['email'], $form_data['username'])) {
                $error = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e).";
            } else {
                // ✅ CRÉER UN OBJET USER AVEC LES BONNES DONNÉES
                $newUser = new User([
                    'username' => $form_data['username'],
                    'email' => $form_data['email'],
                    'first_name' => $form_data['first_name'],
                    'last_name' => $form_data['last_name'],
                    'role' => 'user',
                    'is_active' => true
                ]);
                
                // ✅ UTILISER LA MÉTHODE MÉTIER POUR LE MOT DE PASSE
                $newUser->setNewPassword($form_data['password']);
                
                // ✅ NOUVEAU : GÉNÉRER LE TOKEN DE VÉRIFICATION
                $newUser->generateVerificationToken();
                
                // ✅ SAUVEGARDER AVEC LE REPOSITORY
                if ($userRepository->create($newUser)) {
                    // ✅ NOUVEAU : ENVOYER L'EMAIL DE VÉRIFICATION
                    if ($emailService->sendVerificationEmail($newUser, $newUser->getEmailVerificationToken())) {
                        $success = "🎉 Inscription réussie ! Un email de vérification a été envoyé à " . 
                                   htmlspecialchars($form_data['email']) . 
                                   ". Veuillez vérifier votre boîte email (et vos spams) et cliquer sur le lien de vérification pour activer votre compte.";
                    } else {
                        $success = "Votre compte a été créé, mais l'email de vérification n'a pas pu être envoyé. " .
                                   "Contactez l'administrateur si le problème persiste.";
                    }
                    
                    // ✅ NETTOYER LE FORMULAIRE APRÈS SUCCÈS
                    $form_data = [
                        'username' => '',
                        'email' => '',
                        'first_name' => '',
                        'last_name' => ''
                    ];
                    
                    // ✅ MODIFIÉ : Pas de redirection automatique - l'utilisateur doit vérifier son email
                    
                } else {
                    $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
                }
            }
        }
    }
    
} catch (Exception $e) {
    // ✅ GESTION D'ERREUR ROBUSTE
    if (config('app.debug', false)) {
        $error = "Erreur lors de l'inscription : " . $e->getMessage();
    } else {
        error_log("Registration error: " . $e->getMessage());
        $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
    }
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Inscription";
include "includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center p-4 transition-colors duration-300">
    <link rel="stylesheet" href="includes/style/register.css">
    
    <div class="w-full max-w-md mx-auto">
        <div class="premium-card">
            <!-- En-tête -->
            <div class="premium-header">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h1 class="text-2xl font-bold text-white">Devenez Membre</h1>
                <p class="text-white opacity-90 mt-2">Rejoignez notre communauté littéraire</p>
            </div>
            
            <!-- Contenu du formulaire -->
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success dark:bg-green-900/30 text-green-800 dark:text-green-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <!-- Nom d'utilisateur -->
                    <div class="form-group">
                        <label for="username" class="form-label">
                            Nom d'utilisateur <span class="required-field">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="username" name="username" 
                                   class="premium-input"
                                   value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                   placeholder="Votre nom d'utilisateur"
                                   required>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Entre 3 et 50 caractères</p>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            Email <span class="required-field">*</span>
                        </label>
                        <div class="relative">
                            <input type="email" id="email" name="email" 
                                   class="premium-input"
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                   placeholder="votre@email.com"
                                   required>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <!-- Mot de passe -->
                        <div class="form-group">
                            <label for="password" class="form-label">
                                Mot de passe <span class="required-field">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" 
                                       class="premium-input"
                                       placeholder="••••••••"
                                       required>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Confirmation mot de passe -->
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                Confirmer <span class="required-field">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="premium-input"
                                       placeholder="••••••••"
                                       required>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <!-- Prénom -->
                        <div class="form-group">
                            <label for="first_name" class="form-label">Prénom</label>
                            <div class="relative">
                                <input type="text" id="first_name" name="first_name" 
                                       class="premium-input"
                                       value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                                       placeholder="Votre prénom">
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Nom -->
                        <div class="form-group">
                            <label for="last_name" class="form-label">Nom</label>
                            <div class="relative">
                                <input type="text" id="last_name" name="last_name" 
                                       class="premium-input"
                                       value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                                       placeholder="Votre nom">
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conditions -->
                    <div class="form-group mb-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="terms" name="terms" type="checkbox" 
                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded" 
                                       required>
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="terms" class="form-label">
                                    J'accepte les <a href="#" class="text-link">conditions d'utilisation</a> et la <a href="#" class="text-link">politique de confidentialité</a>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton d'inscription -->
                    <button type="submit" class="premium-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        S'inscrire
                    </button>
                </form>
                
                <!-- Lien de connexion -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Déjà membre? 
                        <a href="login.php" class="text-link">Connectez-vous</a>
                    </p>
                </div>
                
                <!-- Lien retour accueil -->
                <div class="mt-4 text-center">
                    <a href="index.php" class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>