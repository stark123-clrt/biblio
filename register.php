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

<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md overflow-hidden mt-10 mb-10">
    <div class="py-4 px-6 bg-blue-800 text-white text-center">
        <h2 class="text-2xl font-bold">Créer un compte</h2>
    </div>
    
    <div class="p-6">
        <?php if(!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="username" class="block text-gray-700 font-bold mb-2">Nom d'utilisateur <span class="text-red-500">*</span></label>
                    <input type="text" id="username" name="username" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 font-bold mb-2">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-gray-700 font-bold mb-2">Prénom</label>
                    <input type="text" id="first_name" name="first_name" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($form_data['first_name']); ?>">
                </div>
                
                <div>
                    <label for="last_name" class="block text-gray-700 font-bold mb-2">Nom</label>
                    <input type="text" id="last_name" name="last_name" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           value="<?php echo htmlspecialchars($form_data['last_name']); ?>">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="password" class="block text-gray-700 font-bold mb-2">Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           required>
                    <p class="text-gray-600 text-xs mt-1">Minimum 6 caractères</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirmer le mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                           required>
                </div>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="terms" name="terms" class="mr-2" required>
                    <label for="terms" class="text-gray-700">J'accepte les <a href="#" class="text-blue-600 hover:text-blue-800">conditions d'utilisation</a> et la <a href="#" class="text-blue-600 hover:text-blue-800">politique de confidentialité</a></label>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="bg-blue-800 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Créer mon compte
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">Vous avez déjà un compte? <a href="login.php" class="text-blue-600 hover:text-blue-800">Connectez-vous</a></p>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>