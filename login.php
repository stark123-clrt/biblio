<?php
// login.php - Page de connexion (code back-end modifié)
session_start();

if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "config/database.php";
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if(empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // ✅ MODIFICATION 1: Récupérer TOUS les champs nécessaires
        $sql = "SELECT id, username, password, email, first_name, last_name, profile_picture, role FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password'])) {
              
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                $_SESSION['role'] = $user['role']; // Changé de 'user_role' à 'role'
                
                $update = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $stmt = $conn->prepare($update);
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
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

<!DOCTYPE html>
<html lang="fr" class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* Fond littéraire - Même style que la page d'inscription */
        body {
            background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: relative;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
            transition: background-color 0.5s ease;
        }
        
        .dark body::before {
            background: rgba(0, 0, 0, 0.7);
        }
        
        /* Animation d'entrée */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Carte principale */
        .premium-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .dark .premium-card {
            background: rgba(15, 23, 42, 0.9);
        }
        
        /* En-tête */
        .premium-header {
            background: linear-gradient(135deg, #4a6fa5 0%, #3a5a8a 100%);
            padding: 2rem;
            text-align: center;
        }
        
        .dark .premium-header {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        }
        
        /* Champs de formulaire */
        .premium-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
            color: #1a202c;
        }
        
        .dark .premium-input {
            background-color: #2d3748;
            border-color: #4a5568;
            color: white;
        }
        
        .premium-input:focus {
            border-color: #4a6fa5;
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.3);
            outline: none;
        }
        
        .dark .premium-input:focus {
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.3);
        }
        
        /* Bouton */
        .premium-btn {
            background: linear-gradient(135deg, #4a6fa5 0%, #3a5a8a 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .premium-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 111, 165, 0.3);
        }
        
        .dark .premium-btn {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        }
        
        /* Messages d'alerte */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-error {
            background-color: #fff5f5;
            border: 1px solid #fed7d7;
            color: #e53e3e;
        }
        
        .dark .alert-error {
            background-color: #742a2a;
            border-color: #742a2a;
            color: #fed7d7;
        }
        
        /* Icônes */
        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        .dark .input-icon {
            color: #718096;
        }
        
        /* Liens */
        .text-link {
            color: #4a6fa5;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .dark .text-link {
            color: #63b3ed;
        }
        
        .text-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: currentColor;
            transition: width 0.3s ease;
        }
        
        .text-link:hover::after {
            width: 100%;
        }
        
        /* Espacement et mise en page */
        .form-container {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .dark .form-label {
            color: #a0aec0;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.5rem 0;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-custom {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            margin-right: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .dark .checkbox-custom {
            border-color: #4a5568;
        }
        
        input[type="checkbox"] {
            position: absolute;
            opacity: 0;
        }
        
        input[type="checkbox"]:checked + .checkbox-custom {
            background-color: #4a6fa5;
            border-color: #4a6fa5;
        }
        
        .dark input[type="checkbox"]:checked + .checkbox-custom {
            background-color: #4299e1;
            border-color: #4299e1;
        }
        
        input[type="checkbox"]:checked + .checkbox-custom::after {
            content: '';
            display: block;
            width: 0.5rem;
            height: 0.25rem;
            border: solid white;
            border-width: 0 0 2px 2px;
            transform: rotate(-45deg);
            margin-bottom: 2px;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 transition-colors duration-300">
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
                <?php if(!empty($error)): ?>
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Champ Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" 
                                   class="premium-input"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
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
                                   class="premium-input" required>
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
            </div>
        </div>
    </div>
</body>
</html>

<?php include "includes/footer.php"; ?>