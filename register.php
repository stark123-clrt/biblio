<?php
// register.php - Page d'inscription (back-end inchangé)
session_start();

if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "config/database.php";
    
    $form_data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name'])
    ];
    
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
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->bindParam(':username', $form_data['username']);
        $stmt->bindParam(':email', $form_data['email']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $error = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e).";
        } else {
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role, is_active, created_at) 
                                    VALUES (:username, :password, :email, :first_name, :last_name, 'user', 1, NOW())");
            $stmt->bindParam(':username', $form_data['username']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':email', $form_data['email']);
            $stmt->bindParam(':first_name', $form_data['first_name']);
            $stmt->bindParam(':last_name', $form_data['last_name']);
            
            if($stmt->execute()) {
                $success = "Votre compte a été créé avec succès! Vous pouvez maintenant vous connecter.";
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



<!DOCTYPE html>
<html lang="fr" class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        /* Fond littéraire - Version originale */
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
            background: rgba(0, 0, 0, 0.5); /* Overlay noir semi-transparent */
            z-index: -1;
            transition: background-color 0.5s ease;
        }
        
        /* Dark Mode - Overlay plus foncé */
        body.dark::before {
            background: rgba(0, 0, 0, 0.7);
        }
        
        /* Animation d'entrée */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Carte principale */
        .premium-card {
            background: rgba(255, 255, 255, 0.9); /* Fond blanc légèrement transparent */
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .dark .premium-card {
            background: rgba(15, 23, 42, 0.9); /* Fond sombre en dark mode */
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
        
        /* Champs de formulaire - Version très visible */
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
        
        .alert-success {
            background-color: #f0fff4;
            border: 1px solid #c6f6d5;
            color: #38a169;
        }
        
        .dark .alert-success {
            background-color: #22543d;
            border-color: #22543d;
            color: #c6f6d5;
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
        }
        
        .dark .text-link {
            color: #63b3ed;
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
        
        .required-field {
            color: #e53e3e;
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
                <h1 class="text-2xl font-bold text-white">Devenez Membre</h1>
                <p class="text-white opacity-90 mt-2">Rejoignez notre communauté littéraire</p>
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
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <!-- Prénom -->
                        <div class="form-group">
                            <label for="first_name" class="form-label">Prénom</label>
                            <div class="relative">
                                <input type="text" id="first_name" name="first_name" 
                                       class="premium-input"
                                       value="<?php echo htmlspecialchars($form_data['first_name']); ?>">
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
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
                                       value="<?php echo htmlspecialchars($form_data['last_name']); ?>">
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nom d'utilisateur -->
                    <div class="form-group">
                        <label for="username" class="form-label">
                            Nom d'utilisateur <span class="required-field">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="username" name="username" 
                                   class="premium-input"
                                   value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                            <div class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            Email <span class="required-field">*</span>
                        </label>
                        <div class="relative">
                            <input type="email" id="email" name="email" 
                                   class="premium-input"
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
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
                                       class="premium-input" required>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum 6 caractères</p>
                        </div>
                        
                        <!-- Confirmation mot de passe -->
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                Confirmation <span class="required-field">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="premium-input" required>
                                <div class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conditions -->
                    <div class="form-group mb-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="terms" name="terms" type="checkbox" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded" required>
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="terms" class="form-label">
                                    J'accepte les <a href="#" class="text-link">conditions</a> et la <a href="#" class="text-link">politique de confidentialité</a>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton d'inscription -->
                    <button type="submit" class="premium-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
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
            </div>
        </div>
    </div>
</body>
</html>



<?php include "includes/footer.php"; ?>