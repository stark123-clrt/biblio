<?php
// login.php - Version 100% POO avec séparation MVC complète + Vérification d'email
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// REDIRECTION SI DÉJÀ CONNECTÉ
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";
require_once "classes/EmailService.php"; 

try {
    // INITIALISER LE REPOSITORY ET SERVICE EMAIL
    $userRepository = new UserRepository();
    $emailService = new EmailService(); 
    
    // VARIABLES POUR LA VUE
    $error = "";
    $success = "";
    $email_value = "";
    $show_resend_option = false;
    $unverified_user_email = ""; 
    
    //TRAITEMENT DU RENVOI D'EMAIL DE VÉRIFICATION
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend_verification'])) {
        $email = trim($_POST['resend_email'] ?? '');
        
        if (!empty($email)) {
            $user = $userRepository->findByEmail($email);
            
            if ($user && !$user->isEmailVerified()) {
                // Générer un nouveau token et l'envoyer
                $user->generateVerificationToken();
                if ($userRepository->updateEmailVerificationToken($user->getId(), $user->getEmailVerificationToken())) {
                    if ($emailService->sendVerificationEmail($user, $user->getEmailVerificationToken())) {
                        $success = "📧 Un nouvel email de vérification a été envoyé à " . htmlspecialchars($email) . ". Vérifiez votre boîte email et vos spams.";
                        $show_resend_option = false;
                    } else {
                        $error = "Erreur lors de l'envoi de l'email. Réessayez plus tard.";
                    }
                } else {
                    $error = "Erreur lors de la génération du nouveau token.";
                }
            } else {
                $error = "Utilisateur introuvable ou email déjà vérifié.";
            }
        }
    }
    
    // ✅ TRAITEMENT DU FORMULAIRE DE CONNEXION
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['resend_verification'])) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Conserver la valeur pour le rechargement
        $email_value = $email;
        
        //  VALIDATION SIMPLE
        if (empty($email) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            // AUTHENTIFICATION AVEC OBJETS
            $user = $userRepository->findByEmail($email);
            
            if ($user && $user->isActiveUser() && $user->verifyPassword($password)) {
                
                // VÉRIFIER SI L'EMAIL EST VÉRIFIÉ
                if (!$user->isEmailVerified()) {
                    $error = "Votre email n'est pas encore vérifié. Veuillez vérifier votre boîte email et cliquer sur le lien de vérification avant de vous connecter.";
                    $show_resend_option = true;
                    $unverified_user_email = $email;
                } else {
            
                    $userRepository->updateLastLogin($user->getId());
                    
                    //  CRÉER LA SESSION AVEC LES DONNÉES DE L'OBJET USER
                    $_SESSION['user_id'] = $user->getId();
                    $_SESSION['username'] = $user->getUsername();
                    $_SESSION['email'] = $user->getEmail();
                    $_SESSION['first_name'] = $user->getFirstName();
                    $_SESSION['last_name'] = $user->getLastName();
                    $_SESSION['profile_picture'] = $user->getProfilePicture();
                    $_SESSION['role'] = $user->getRole();
                    
                    // ✅ GESTION DU "SE SOUVENIR DE MOI" (optionnel)
                    if ($remember) {
                        // Cookies sécurisés pour 30 jours
                        $expire = time() + (30 * 24 * 60 * 60);
                        setcookie('remember_user', $user->getId(), $expire, '/', '', true, true);
                    }
                    
                    // ✅ REDIRECTION SELON LE RÔLE (MÉTHODE MÉTIER)
                    if ($user->isAdmin()) {
                        header("Location: admin/index.php");
                    } else {
                        header("Location: user/my-library.php");
                    }
                    exit();
                }
                
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        }
    }
    
} catch (Exception $e) {
    // ✅ GESTION D'ERREUR ROBUSTE
    $error = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
    
    if (config('app.debug', false)) {
        $error .= " (" . $e->getMessage() . ")";
    }
    
    error_log("Login error: " . $e->getMessage());
}

// ✅ PRÉPARER LES VARIABLES POUR LA VUE (SÉPARATION MVC)
$page_title = "Connexion";
include "includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center p-4 transition-colors duration-300">
    <link rel="stylesheet" href="includes/style/login.css">
    
    <div class="w-full max-w-md mx-auto">
        <div class="premium-card">
            <!-- En-tête -->
            <div class="premium-header">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h1 class="text-2xl font-bold text-white">Connexion</h1>
                <p class="text-white opacity-90 mt-2">Accédez à votre bibliothèque</p>
            </div>
            
            <!-- Contenu du formulaire -->
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <?php echo $error; ?>
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
                
                <!-- ✅ NOUVEAU : Formulaire de renvoi d'email si nécessaire -->
                <?php if ($show_resend_option): ?>
                    <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                        <div class="flex items-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Renvoyer l'email de vérification</h3>
                        </div>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mb-3">
                            Vous n'avez pas reçu l'email ou il a expiré ? Cliquez ci-dessous pour en recevoir un nouveau.
                        </p>
                        <form method="POST" class="inline">
                            <input type="hidden" name="resend_email" value="<?php echo htmlspecialchars($unverified_user_email); ?>">
                            <button type="submit" name="resend_verification" class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-300">
                                📧 Renvoyer l'email
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Champ Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" 
                                   class="premium-input"
                                   value="<?php echo htmlspecialchars($email_value); ?>" 
                                   placeholder="votre@email.com"
                                   required>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Champ Mot de passe -->
                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                   class="premium-input" 
                                   placeholder="Votre mot de passe"
                                   required>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div class="checkbox-container">
                        <label class="checkbox-label">
                            <input type="checkbox" id="remember" name="remember">
                            <span class="checkbox-custom"></span>
                            <span class="text-gray-700 dark:text-gray-300">Se souvenir de moi</span>
                        </label>
                        <a href="forgot-password.php" class="text-link">Mot de passe oublié ?</a>
                    </div>
                    
                    <!-- Bouton de connexion -->
                    <button type="submit" class="premium-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013 3v1" />
                        </svg>
                        Se connecter
                    </button>
                </form>
                
                <!-- Lien d'inscription -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Vous n'avez pas de compte ? 
                        <a href="register.php" class="text-link">Inscrivez-vous</a>
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