<?php
// login.php - Page de connexion
session_start();

// Rediriger si l'utilisateur est déjà connecté
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Traitement du formulaire de connexion
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "config/database.php";
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if(empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Vérification des identifiants
        $sql = "SELECT id, username, password, role FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérification du mot de passe
            if(password_verify($password, $user['password'])) {
                // Création de la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // Mise à jour de la dernière connexion
                $update = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $stmt = $conn->prepare($update);
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
                // Redirection selon le rôle
                if($user['role'] == 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: user/my-library.php");
                }
                exit();
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}

$page_title = "Connexion";
include "includes/header.php";
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden transition-all duration-300">
        <div class="py-4 px-6 bg-blue-800 dark:bg-gray-700 text-white text-center">
            <h2 class="text-2xl font-bold">Connexion</h2>
        </div>
        
        <div class="p-6">
            <?php if(!empty($error)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <div class="space-y-2">
                    <label for="email" class="block text-gray-700 dark:text-gray-300 font-bold">Email</label>
                    <input type="email" id="email" name="email" 
                           class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="space-y-2">
                    <label for="password" class="block text-gray-700 dark:text-gray-300 font-bold">Mot de passe</label>
                    <input type="password" id="password" name="password" 
                           class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" required>
                </div>
                
                <div class="flex items-center justify-between py-2">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="mr-2 h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500">
                        <label for="remember" class="text-gray-700 dark:text-gray-300">Se souvenir de moi</label>
                    </div>
                    <a href="forgot-password.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">Mot de passe oublié?</a>
                </div>
                
                <div class="text-center pt-2">
                    <button type="submit" class="bg-blue-800 hover:bg-blue-900 dark:bg-blue-700 dark:hover:bg-blue-800 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 w-full transition-all duration-200 transform hover:scale-[1.02]">
                        Se connecter
                    </button>
                </div>
            </form>
            
            <div class="mt-8 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    Vous n'avez pas de compte? 
                    <a href="register.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                        Inscrivez-vous
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Animation de transition pour les champs du formulaire */
    input, button {
        transition: all 0.3s ease;
    }
    
    input:focus {
        transform: translateY(-2px);
    }
    
    /* Améliorations de style pour les cases à cocher */
    input[type="checkbox"] {
        border-radius: 3px;
        cursor: pointer;
    }
    
    /* Style du curseur sur les boutons */
    button {
        cursor: pointer;
    }
    
    /* Animation plus fluide pour les transitions du mode sombre */
    .dark body, .dark * {
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }
</style>

<?php include "includes/footer.php"; ?>