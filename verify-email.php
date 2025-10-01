<?php
// verify-email.php - Page de vérification d'email
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    // ✅ INITIALISER LE REPOSITORY
    $userRepository = new UserRepository();
    
    // ✅ VARIABLES POUR LA VUE
    $status = 'loading'; // loading, success, error, invalid
    $message = '';
    $title = '';
    $user = null;
    
    // ✅ VÉRIFIER QUE LE TOKEN EST PRÉSENT
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        $status = 'invalid';
        $title = 'Lien invalide';
        $message = 'Le lien de vérification est invalide ou manquant.';
    } else {
        $token = trim($_GET['token']);
        
        // ✅ CHERCHER L'UTILISATEUR PAR TOKEN
        $user = $userRepository->findByEmailVerificationToken($token);
        
        if (!$user) {
            $status = 'invalid';
            $title = 'Token invalide';
            $message = 'Ce lien de vérification est invalide, expiré ou a déjà été utilisé.';
        } elseif ($user->isEmailVerified()) {
            $status = 'success';
            $title = 'Email déjà vérifié';
            $message = 'Votre email a déjà été vérifié ! Vous pouvez vous connecter.';
        } else {
            // ✅ MARQUER L'EMAIL COMME VÉRIFIÉ
            if ($userRepository->markEmailAsVerified($user->getId())) {
                $status = 'success';
                $title = 'Email vérifié avec succès !';
                $message = 'Félicitations ' . htmlspecialchars($user->getFirstName() ?? $user->getUsername()) . ' ! Votre email a été vérifié avec succès. Vous pouvez maintenant vous connecter à votre compte.';
            } else {
                $status = 'error';
                $title = 'Erreur de vérification';
                $message = 'Une erreur est survenue lors de la vérification. Veuillez réessayer ou contactez le support.';
            }
        }
    }
    
} catch (Exception $e) {
    $status = 'error';
    $title = 'Erreur système';
    
    if (config('app.debug', false)) {
        $message = 'Erreur lors de la vérification : ' . $e->getMessage();
    } else {
        error_log("Email verification error: " . $e->getMessage());
        $message = 'Une erreur système est survenue. Veuillez réessayer plus tard.';
    }
}

$page_title = "Vérification d'email";
include "includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
            
            <!-- En-tête avec icône selon le statut -->
            <div class="text-center p-8 <?php 
                echo $status === 'success' ? 'bg-gradient-to-r from-green-500 to-green-600' : 
                    ($status === 'error' || $status === 'invalid' ? 'bg-gradient-to-r from-red-500 to-red-600' : 
                     'bg-gradient-to-r from-blue-500 to-blue-600'); 
            ?>">
                <?php if ($status === 'success'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php elseif ($status === 'error' || $status === 'invalid'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                <?php endif; ?>
                
                <h1 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($title); ?></h1>
            </div>
            
            <!-- Contenu principal -->
            <div class="p-8">
                <div class="text-center mb-8">
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        <?php echo $message; ?>
                    </p>
                </div>
                
                <?php if ($status === 'success'): ?>
                    <!-- Boutons d'action pour le succès -->
                    <div class="space-y-4">
                        <a href="login.php" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Se connecter maintenant
                        </a>
                        
                        <a href="index.php" class="w-full bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 font-semibold py-3 px-6 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Retour à l'accueil
                        </a>
                    </div>
                    
                <?php elseif ($status === 'error' || $status === 'invalid'): ?>
                    <!-- Boutons d'action pour les erreurs -->
                    <div class="space-y-4">
                        <a href="register.php" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            Créer un nouveau compte
                        </a>
                        
                        <a href="login.php" class="w-full bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 font-semibold py-3 px-6 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Se connecter
                        </a>
                        
                        <a href="index.php" class="w-full text-center text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium py-2 transition-colors duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Retour à l'accueil
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pied de page -->
            <div class="bg-gray-50 dark:bg-gray-700 px-8 py-4 border-t border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Bibliothèque - Vérification d'email
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($status === 'success'): ?>
    <!-- Animation de confettis pour le succès (optionnel) -->
    <script>
        // Animation simple pour célébrer le succès
        setTimeout(() => {
            document.body.style.background = 'linear-gradient(45deg, #10b981, #3b82f6)';
            setTimeout(() => {
                document.body.style.background = '';
            }, 2000);
        }, 500);
    </script>
<?php endif; ?>
