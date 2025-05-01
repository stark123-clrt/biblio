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

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden mt-10">
    <div class="py-4 px-6 bg-blue-800 text-white text-center">
        <h2 class="text-2xl font-bold">Connexion</h2>
    </div>
    
    <div class="p-6">
        <?php if(!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-bold mb-2">Email</label>
                <input type="email" id="email" name="email" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 font-bold mb-2">Mot de passe</label>
                <input type="password" id="password" name="password" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            
            <div class="flex items-center justify-between mb-4">
                <div>
                    <input type="checkbox" id="remember" name="remember" class="mr-2">
                    <label for="remember" class="text-gray-700">Se souvenir de moi</label>
                </div>
                <a href="forgot-password.php" class="text-blue-600 hover:text-blue-800">Mot de passe oublié?</a>
            </div>
            
            <div class="text-center">
                <button type="submit" class="bg-blue-800 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Se connecter
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">Vous n'avez pas de compte? <a href="register.php" class="text-blue-600 hover:text-blue-800">Inscrivez-vous</a></p>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>