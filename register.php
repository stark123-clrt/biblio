<?php
// register.php - Page d'inscription
session_start();

// Rediriger si l'utilisateur est déjà connecté
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Initialisation des variables
$error = "";
$success = "";
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => ''
];

// Traitement du formulaire d'inscription
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "config/database.php";
    
    // Récupération des données du formulaire
    $form_data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name'])
    ];
    
    // Validation des données
    if(empty($form_data['username']) || empty($form_data['email']) || empty($form_data['password']) || empty($form_data['confirm_password'])) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif(strlen($form_data['username']) < 3 || strlen($form_data['username']) > 50) {
        $error = "Le nom d'utilisateur doit contenir entre 3 et 50 caractères.";
    } elseif(!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer une adresse email valide.";
    } elseif(strlen($form_data['password']) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif($form_data['password'] !== $form_data['confirm_password']) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'utilisateur existe déjà
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->bindParam(':username', $form_data['username']);
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $error = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e).";
        } else {
            // Hacher le mot de passe
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            // Insérer le nouvel utilisateur
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role, is_active, created_at) 
                                    VALUES (:username, :password, :email, :first_name, :last_name, 'user', 1, NOW())");
            $stmt->bindParam(':username', $form_data['username']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':email', $form_data['email']);
            $stmt->bindParam(':first_name', $form_data['first_name']);
            $stmt->bindParam(':last_name', $form_data['last_name']);
            
            if($stmt->execute()) {
                $success = "Votre compte a été créé avec succès! Vous pouvez maintenant vous connecter.";
                // Rediriger vers la page de connexion après 3 secondes
                header("refresh:3;url=login.php");
            } else {
                $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}

$page_title = "Inscription";
include "includes/header.php";
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden transition-all duration-300">
        <div class="py-4 px-6 bg-blue-800 dark:bg-gray-700 text-white text-center">
            <h2 class="text-2xl font-bold">Créer un compte</h2>
        </div>
        
        <div class="p-6">
            <?php if(!empty($error)): ?>
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="username" class="block text-gray-700 dark:text-gray-300 font-bold">
                            Nom d'utilisateur <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="username" name="username" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="email" class="block text-gray-700 dark:text-gray-300 font-bold">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="first_name" class="block text-gray-700 dark:text-gray-300 font-bold">Prénom</label>
                        <input type="text" id="first_name" name="first_name" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               value="<?php echo htmlspecialchars($form_data['first_name']); ?>">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="last_name" class="block text-gray-700 dark:text-gray-300 font-bold">Nom</label>
                        <input type="text" id="last_name" name="last_name" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               value="<?php echo htmlspecialchars($form_data['last_name']); ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="password" class="block text-gray-700 dark:text-gray-300 font-bold">
                            Mot de passe <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password" name="password" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               required>
                        <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">Minimum 6 caractères</p>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="confirm_password" class="block text-gray-700 dark:text-gray-300 font-bold">
                            Confirmer le mot de passe <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="shadow appearance-none border dark:border-gray-600 rounded w-full py-3 px-4 text-gray-700 dark:text-white dark:bg-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                               required>
                    </div>
                </div>
                
                <div class="py-2">
                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" class="mr-2 h-4 w-4 text-blue-600 dark:text-blue-400 focus:ring-blue-500" required>
                        <label for="terms" class="text-gray-700 dark:text-gray-300">
                            J'accepte les <a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline">conditions d'utilisation</a> et la <a href="#" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline">politique de confidentialité</a>
                        </label>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="bg-blue-800 hover:bg-blue-900 dark:bg-blue-700 dark:hover:bg-blue-800 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 w-full transition-all duration-200 transform hover:scale-[1.02]">
                        Créer mon compte
                    </button>
                </div>
            </form>
            
            <div class="mt-8 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    Vous avez déjà un compte? 
                    <a href="login.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                        Connectez-vous
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