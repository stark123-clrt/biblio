<?php
// forgot-password.php - Page de demande de récupération de mot de passe
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
require_once "classes/EmailService.php";

try {
    // ✅ INITIALISER LES SERVICES
    $userRepository = new UserRepository();
    $emailService = new EmailService();
    
    // ✅ VARIABLES POUR LA VUE
    $error = "";
    $success = "";
    $email_value = "";
    
    // ✅ TRAITEMENT DU FORMULAIRE
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = trim($_POST['email'] ?? '');
        $email_value = $email; // Conserver la valeur pour le rechargement
        
        // ✅ VALIDATION
        if (empty($email)) {
            $error = "Veuillez entrer votre adresse email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez entrer une adresse email valide.";
        } else {
            // ✅ CHERCHER L'UTILISATEUR
            $user = $userRepository->findByEmail($email);
            
            if ($user && $user->isActiveUser()) {
                // ✅ VÉRIFIER QUE L'EMAIL EST VÉRIFIÉ
                if (!$user->isEmailVerified()) {
                    $error = "Votre email n'est pas encore vérifié. Veuillez d'abord vérifier votre email avant de pouvoir réinitialiser votre mot de passe.";
                } else {
                    // ✅ GÉNÉRER LE TOKEN DE RÉCUPÉRATION
                    $user->generatePasswordResetToken();
                    
                    // ✅ SAUVEGARDER LE TOKEN EN BASE
                    if ($userRepository->savePasswordResetToken(
                        $user->getId(), 
                        $user->getPasswordResetToken(), 
                        $user->getPasswordResetExpires()
                    )) {
                        // ✅ ENVOYER L'EMAIL DE RÉCUPÉRATION
                        if ($emailService->sendPasswordResetEmail($user, $user->getPasswordResetToken())) {
                            $success = "📧 Un email de récupération a été envoyé à " . htmlspecialchars($email) . 
                                      ". Vérifiez votre boîte email (et vos spams) et suivez les instructions pour réinitialiser votre mot de passe. Le lien expire dans 1 heure.";
                            $email_value = ""; // Nettoyer le champ après succès
                        } else {
                            $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.";
                        }
                    } else {
                        $error = "Erreur lors de la génération du lien de récupération. Veuillez réessayer.";
                    }
                }
            } else {
                // ✅ MESSAGE GÉNÉRIQUE POUR LA SÉCURITÉ
                // On ne révèle pas si l'email existe ou non pour éviter l'énumération d'utilisateurs
                $success = "Si un compte existe avec cette adresse email, vous recevrez un lien de récupération dans quelques instants.";
                $email_value = ""; // Nettoyer le champ
            }
        }
    }
    
} catch (Exception $e) {
    // ✅ GESTION D'ERREUR ROBUSTE
    if (config('app.debug', false)) {
        $error = "Erreur lors de la récupération : " . $e->getMessage();
    } else {
        error_log("Password reset error: " . $e->getMessage());
        $error = "Une erreur est survenue. Veuillez réessayer plus tard.";
    }
}

$page_title = "Mot de passe oublié";
include "includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center p-4 transition-colors duration-300">
    <link rel="stylesheet" href="includes/style/login.css">
    
    <div class="w-full max-w-md mx-auto">
        <div class="premium-card">
            <!-- En-tête -->
            <div class="premium-header" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <h1 class="text-2xl font-bold text-white">Mot de passe oublié</h1>
                <p class="text-white opacity-90 mt-2">Récupérez l'accès à votre compte</p>
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
                
                <!-- Instructions -->
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <div class="flex items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-1">Comment ça fonctionne</h3>
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Entrez votre adresse email ci-dessous. Nous vous enverrons un lien sécurisé pour créer un nouveau mot de passe. Le lien expire dans 1 heure.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Champ Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Adresse email</label>
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
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">L'email associé à votre compte</p>
                    </div>
                    
                    <!-- Bouton d'envoi -->
                    <button type="submit" class="premium-btn" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Envoyer le lien de récupération
                    </button>
                </form>
                
                <!-- Liens de navigation -->
                <div class="mt-6 text-center space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Vous vous souvenez de votre mot de passe ? 
                        <a href="login.php" class="text-link">Connectez-vous</a>
                    </p>
                    
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