<?php
// reset-password.php - Page de réinitialisation de mot de passe
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

try {
    // ✅ INITIALISER LE REPOSITORY
    $userRepository = new UserRepository();
    
    // ✅ VARIABLES POUR LA VUE
    $status = 'loading'; // loading, form, success, error, invalid, expired
    $message = '';
    $title = '';
    $user = null;
    $token = '';
    
    // ✅ VÉRIFIER QUE LE TOKEN EST PRÉSENT
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        $status = 'invalid';
        $title = 'Lien invalide';
        $message = 'Le lien de récupération est invalide ou manquant.';
    } else {
        $token = trim($_GET['token']);
        
        // ✅ CHERCHER L'UTILISATEUR PAR TOKEN
        $user = $userRepository->findByPasswordResetToken($token);
        
        if (!$user) {
            $status = 'invalid';
            $title = 'Token invalide';
            $message = 'Ce lien de récupération est invalide ou a déjà été utilisé.';
        } elseif ($user->isPasswordResetTokenExpired()) {
            $status = 'expired';
            $title = 'Lien expiré';
            $message = 'Ce lien de récupération a expiré. Veuillez faire une nouvelle demande de récupération.';
        } elseif (!$user->isPasswordResetTokenValid($token)) {
            $status = 'invalid';
            $title = 'Token invalide';
            $message = 'Ce lien de récupération n\'est pas valide.';
        } else {
            // ✅ TOKEN VALIDE - AFFICHER LE FORMULAIRE
            $status = 'form';
            $title = 'Nouveau mot de passe';
            $message = 'Choisissez un nouveau mot de passe sécurisé pour votre compte.';
        }
    }
    
    // ✅ TRAITEMENT DU FORMULAIRE DE NOUVEAU MOT DE PASSE
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $status === 'form' && $user) {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // ✅ VALIDATION
        if (empty($password) || empty($confirm_password)) {
            $message = 'Veuillez remplir tous les champs.';
        } elseif (strlen($password) < config('security.password_min_length', 6)) {
            $message = 'Le mot de passe doit contenir au moins ' . config('security.password_min_length', 6) . ' caractères.';
        } elseif ($password !== $confirm_password) {
            $message = 'Les mots de passe ne correspondent pas.';
        } else {
            // ✅ VÉRIFIER À NOUVEAU LA VALIDITÉ DU TOKEN (sécurité)
            if (!$user->isPasswordResetTokenValid($token)) {
                $status = 'expired';
                $title = 'Session expirée';
                $message = 'Votre session de récupération a expiré. Veuillez faire une nouvelle demande.';
            } else {
                // ✅ HASHER LE NOUVEAU MOT DE PASSE
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // ✅ METTRE À JOUR LE MOT DE PASSE ET NETTOYER LE TOKEN
                if ($userRepository->resetPassword($user->getId(), $hashedPassword)) {
                    $status = 'success';
                    $title = 'Mot de passe modifié !';
                    $message = 'Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.';
                } else {
                    $status = 'error';
                    $title = 'Erreur';
                    $message = 'Une erreur est survenue lors de la modification du mot de passe. Veuillez réessayer.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    $status = 'error';
    $title = 'Erreur système';
    
    if (config('app.debug', false)) {
        $message = 'Erreur lors de la réinitialisation : ' . $e->getMessage();
    } else {
        error_log("Password reset error: " . $e->getMessage());
        $message = 'Une erreur système est survenue. Veuillez réessayer plus tard.';
    }
}

$page_title = "Nouveau mot de passe";
include "includes/header.php";
?>

<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-red-50 to-orange-100 dark:from-gray-900 dark:to-gray-800">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
            
            <!-- En-tête avec icône selon le statut -->
            <div class="text-center p-8 <?php 
                echo $status === 'success' ? 'bg-gradient-to-r from-green-500 to-green-600' : 
                    (($status === 'error' || $status === 'invalid' || $status === 'expired') ? 'bg-gradient-to-r from-red-500 to-red-600' : 
                     'bg-gradient-to-r from-orange-500 to-orange-600'); 
            ?>">
                <?php if ($status === 'success'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php elseif ($status === 'error' || $status === 'invalid' || $status === 'expired'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                <?php endif; ?>
                
                <h1 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($title); ?></h1>
            </div>
            
            <!-- Contenu principal -->
            <div class="p-8">
                
                <?php if ($status === 'form'): ?>
                    <!-- FORMULAIRE DE NOUVEAU MOT DE PASSE -->
                    <div class="mb-6">
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed mb-4">
                            <?php echo $message; ?>
                        </p>
                        
                        <?php if ($user): ?>
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Compte : <?php echo htmlspecialchars($user->getEmail()); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1 ml-7">
                                    Temps restant : <?php echo $user->getPasswordResetTimeRemaining(); ?> minutes
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <!-- Nouveau mot de passe -->
                        <div class="form-group">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nouveau mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" 
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-300"
                                       placeholder="Votre nouveau mot de passe"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Au moins <?php echo config('security.password_min_length', 6); ?> caractères
                            </p>
                        </div>
                        
                        <!-- Confirmation mot de passe -->
                        <div class="form-group">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Confirmer le mot de passe
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all duration-300"
                                       placeholder="Confirmez votre nouveau mot de passe"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bouton de soumission -->
                        <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Modifier mon mot de passe
                        </button>
                    </form>
                    
                <?php else: ?>
                    <!-- MESSAGE D'ÉTAT (SUCCESS, ERROR, INVALID, EXPIRED) -->
                    <div class="text-center mb-8">
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            <?php echo $message; ?>
                        </p>
                    </div>
                    
                    <!-- Boutons d'action selon le statut -->
                    <div class="space-y-4">
                        <?php if ($status === 'success'): ?>
                            <a href="login.php" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                Se connecter maintenant
                            </a>
                            
                        <?php else: ?>
                            <a href="forgot-password.php" class="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Faire une nouvelle demande
                            </a>
                        <?php endif; ?>
                        
                        <a href="login.php" class="w-full bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 font-semibold py-3 px-6 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Retour à la connexion
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Bibliothèque Chrétienne - Récupération sécurisée
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>