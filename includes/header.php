<?php
// includes/header.php - Version modernisée en POO
// Vérifier si une session n'est pas déjà démarrée avant d'en démarrer une nouvelle
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Déterminer le chemin de base et la page active
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base_path = '../'; // Si on est dans le dossier admin
} elseif (strpos($_SERVER['PHP_SELF'], '/user/') !== false) {
    $base_path = '../'; // Si on est dans le dossier user
}

$current_page = basename($_SERVER['PHP_SELF']);

// ✅ MODERNISATION POO - Récupérer les informations utilisateur
$user = null;
$profile_picture_path = '';

if (isset($_SESSION['user_id'])) {
    try {
        // Charger les classes POO si pas déjà fait
        if (!class_exists('UserRepository')) {
            require_once $base_path . "classes/Core.php";
            require_once $base_path . "classes/Models.php";
            require_once $base_path . "classes/Repositories.php";
        }
        
        $userRepository = new UserRepository();
        $userObj = $userRepository->findById($_SESSION['user_id']);
        
        if ($userObj) {
            // Convertir en array pour compatibilité avec HTML existant
            $user = $userObj->toArray();
            
            // Gérer le chemin de l'image de profil avec objet
            if ($userObj->getProfilePicture()) {
                $profile_picture_path = getCorrectImagePath($userObj->getProfilePicture(), $base_path);
            }
        }
        
    } catch (Exception $e) {
        // En cas d'erreur, log et continuer sans utilisateur
        error_log("Header user loading error: " . $e->getMessage());
        $user = null;
        $profile_picture_path = '';
    }
}

// Fonction pour corriger le chemin des images selon la page actuelle
function getCorrectImagePath($imagePath, $basePath) {
    if (empty($imagePath)) {
        return '';
    }
    
    // Si le chemin commence déjà par http:// ou https://, le retourner tel quel
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // Nettoyer le chemin de l'image
    $cleanPath = ltrim($imagePath, './');
    
    // Enlever les ../ existants pour éviter la duplication
    while (strpos($cleanPath, '../') === 0) {
        $cleanPath = substr($cleanPath, 3);
    }
    
    // Si on est dans un sous-dossier (admin/ ou user/), ajouter ../
    if (!empty($basePath) && $basePath === '../') {
        // Si le chemin ne commence pas déjà par assets/, l'ajouter
        if (strpos($cleanPath, 'assets/') !== 0) {
            $cleanPath = 'assets/uploads/profiles/' . basename($cleanPath);
        }
        return '../' . $cleanPath;
    }
    
    // Si on est à la racine
    // Si le chemin ne commence pas déjà par assets/, l'ajouter
    if (strpos($cleanPath, 'assets/') !== 0) {
        $cleanPath = 'assets/uploads/profiles/' . basename($cleanPath);
    }
    
    return $cleanPath;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque Chrétienne - <?php echo $page_title ?? 'Accueil'; ?></title>

    
    
    <meta name="description" content="Bibliothèque chrétienne en ligne avec des livres spirituels, témoignages et ressources pour votre croissance spirituelle">
    <meta name="keywords" content="bibliothèque chrétienne, livres spirituels, témoignages, croissance spirituelle">

    <link rel="stylesheet" href="style/index.css">
    <link rel="icon" href="<?php echo $base_path; ?>assets/favicon.ico" type="image/x-icon">
    

    <script>
    (function() {
        const savedTheme = localStorage.getItem('theme');
        const isDark = savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
            document.documentElement.classList.add('dark');
        }
    })();
    </script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['"Poppins"', 'sans-serif'],
                        'serif': ['"Cormorant Garamond"', 'serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: {
                            400: '#e6b31e',
                            500: '#d4a017',
                        }
                    },
                    boxShadow: {
                        'nav': '0 4px 30px rgba(0, 0, 0, 0.1)',
                        'card': '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                    }
                }
            }
        }
    </script>
    
    <!-- jQuery pour AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Icônes Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Police Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">

    
    <style>
        /* Animation douce pour les éléments */
        .transition-smooth {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Menu déroulant pro */
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 0.5rem);
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            min-width: 220px;
            z-index: 50;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.2s ease-out;
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.9);
        }
        
        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: #4b5563;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .dropdown-menu a:hover {
            background-color: #f8fafc;
            color: #1e40af;
            border-left-color: #1e40af;
        }
        
        .dark .dropdown-menu {
            background-color: rgba(31, 41, 55, 0.95);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        
        .dark .dropdown-menu a {
            color: #d1d5db;
        }
        
        .dark .dropdown-menu a:hover {
            background-color: rgba(55, 65, 81, 0.8);
            color: #93c5fd;
            border-left-color: #93c5fd;
        }
        
        /* Style pour le menu mobile */
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(to bottom, #1e40af, #1e3a8a);
        }
        
        .dark .mobile-menu {
            background: linear-gradient(to bottom, #111827, #1f2937);
        }
        
        .mobile-menu.open {
            max-height: 100vh;
        }
        
        /* Style pour les liens de navigation */
        .nav-link {
            position: relative;
            transition: all 0.2s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: currentColor;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 70%;
        }
        
        .nav-link.active {
            font-weight: 500;
        }
        
        /* Style pour le bouton hamburger */
        .hamburger {
            width: 28px;
            height: 28px;
            position: relative;
            transform: rotate(0deg);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .hamburger span {
            display: block;
            position: absolute;
            height: 2px;
            width: 100%;
            background: currentColor;
            border-radius: 2px;
            opacity: 1;
            left: 0;
            transform: rotate(0deg);
            transition: all 0.25s ease;
        }
        
        .hamburger span:nth-child(1) {
            top: 6px;
        }
        
        .hamburger span:nth-child(2), 
        .hamburger span:nth-child(3) {
            top: 14px;
        }
        
        .hamburger span:nth-child(4) {
            top: 22px;
        }
        
        .hamburger.open span:nth-child(1) {
            top: 14px;
            width: 0%;
            left: 50%;
        }
        
        .hamburger.open span:nth-child(2) {
            transform: rotate(45deg);
        }
        
        .hamburger.open span:nth-child(3) {
            transform: rotate(-45deg);
        }
        
        .hamburger.open span:nth-child(4) {
            top: 14px;
            width: 0%;
            left: 50%;
        }
        
        /* Style pour le bouton de thème */
        .theme-toggle {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .theme-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #1e40af, #3b82f6);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }
        
        .theme-toggle:hover::before {
            opacity: 0.2;
        }
        
        .theme-toggle i {
            position: relative;
            z-index: 1;
        }
        
        /* Style pour le badge de notification */
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }
        
        /* Effet de verre (glassmorphism) */
        .glass-nav {
            background: rgba(30, 64, 175, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        
        .dark .glass-nav {
            background: rgba(17, 24, 39, 0.85);
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex flex-col">
    <header class="glass-nav text-white shadow-nav fixed w-full z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="<?php echo $base_path; ?>index.php" class="text-2xl font-bold flex items-center group">
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gold-400 group-hover:text-gold-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span class="absolute -bottom-1 -right-1 w-2 h-2 bg-blue-400 rounded-full"></span>
                        </div>
                        <span class="ml-2 font-serif text-xl bg-clip-text text-transparent bg-gradient-to-r from-gold-400 to-gold-500">
                            Bibliothèque
                        </span>
                    </a>
                </div>
                
                <!-- Navigation desktop -->
                <nav class="hidden lg:flex items-center space-x-1">
                    <a href="<?php echo $base_path; ?>index.php" 
                       class="nav-link px-4 py-2 font-medium hover:text-blue-100 dark:hover:text-blue-200 relative transition-smooth <?php echo ($current_page == 'index.php') ? 'text-white' : 'text-blue-100' ?>">
                        Accueil
                    </a>
                    <a href="<?php echo $base_path; ?>library.php" 
                       class="nav-link px-4 py-2 font-medium hover:text-blue-100 dark:hover:text-blue-200 relative transition-smooth <?php echo ($current_page == 'library.php') ? 'text-white' : 'text-blue-100' ?>">
                        Bibliothèque
                    </a>
                    <a href="<?php echo $base_path; ?>testimonials.php" 
                       class="nav-link px-4 py-2 font-medium hover:text-blue-100 dark:hover:text-blue-200 relative transition-smooth <?php echo ($current_page == 'testimonials.php') ? 'text-white' : 'text-blue-100' ?>">
                        Avis
                    </a>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $base_path; ?>user/my-library.php" 
                           class="nav-link px-4 py-2 font-medium hover:text-blue-100 dark:hover:text-blue-200 relative transition-smooth <?php echo ($current_page == 'my-library.php') ? 'text-white' : 'text-blue-100' ?>">
                            Ma Bibliothèque
                        </a>
                    <?php endif; ?>
                </nav>
                
                <!-- Boutons droite -->
                <div class="flex items-center space-x-4">
                    <!-- Barre de recherche (desktop) -->
                    <div class="hidden md:block relative group">
                        <form action="<?php echo $base_path; ?>search.php" method="GET" class="relative">
                            <input type="text" name="q" placeholder="Rechercher..." 
                                   class="w-48 lg:w-64 px-4 py-2 rounded-full bg-white bg-opacity-20 focus:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent text-white placeholder-blue-100 transition-all duration-300">
                            <button type="submit" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-100 hover:text-white">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Bouton thème -->
                    <button id="theme-toggle" class="theme-toggle p-2 rounded-full bg-white bg-opacity-10 hover:bg-opacity-20 transition-smooth">
                        <i class="fas fa-moon text-blue-100"></i>
                    </button>
                    
                    <!-- Menu utilisateur -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="relative" id="accountDropdown">
                            <button id="accountDropdownBtn" class="flex items-center space-x-2 focus:outline-none group">
                                <div class="relative">
                                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center overflow-hidden border-2 border-blue-400 group-hover:border-blue-300 transition-smooth">
                                        <?php if(!empty($profile_picture_path)): ?>
                                            <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Avatar" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-white"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="hidden lg:inline text-blue-100 group-hover:text-white font-medium transition-smooth">
                                    <?php 
                                    if(isset($user['first_name']) && !empty($user['first_name'])) {
                                        echo htmlspecialchars($user['first_name']);
                                    } elseif(isset($user['username'])) {
                                        echo htmlspecialchars($user['username']);
                                    } elseif(isset($_SESSION['username'])) {
                                        echo htmlspecialchars($_SESSION['username']);
                                    }
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down text-xs text-blue-100 group-hover:text-white transition-smooth"></i>
                            </button>
                            
                            <ul class="dropdown-menu" id="accountMenu">
                                <li class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center overflow-hidden">
                                            <?php if(!empty($profile_picture_path)): ?>
                                                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Avatar" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user text-blue-800"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php 
                                                if(isset($user['first_name']) && isset($user['last_name'])) {
                                                    echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                                } elseif(isset($user['username'])) {
                                                    echo htmlspecialchars($user['username']);
                                                } elseif(isset($_SESSION['username'])) {
                                                    echo htmlspecialchars($_SESSION['username']);
                                                }
                                                ?>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo isset($user['email']) ? htmlspecialchars($user['email']) : (isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''); ?>
                                            </p>
                                        </div>
                                    </div>
                                </li>
                                <li><a href="<?php echo $base_path; ?>user/profile.php" class="flex items-center"><i class="fas fa-user-circle mr-3 text-gray-500 dark:text-gray-400"></i> Mon Profil</a></li>
                                <li><a href="<?php echo $base_path; ?>user/notes.php" class="flex items-center"><i class="fas fa-sticky-note mr-3 text-gray-500 dark:text-gray-400"></i> Mes Notes</a></li>
                                <li><a href="<?php echo $base_path; ?>user/comments.php" class="flex items-center"><i class="fas fa-comments mr-3 text-gray-500 dark:text-gray-400"></i> Mes Commentaires</a></li>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    
                                    <li class="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1"><a href="<?php echo ($base_path === '../') ? '../admin/index.php' : 'admin/index.php'; ?>" class="flex items-center"><i class="fas fa-cog mr-3 text-gray-500 dark:text-gray-400"></i> Administration</a></li>
                                <?php endif; ?>
                                <li class="border-t border-gray-100 dark:border-gray-700 mt-1 pt-1"><a href="<?php echo $base_path; ?>logout.php" class="flex items-center text-red-600 dark:text-red-400"><i class="fas fa-sign-out-alt mr-3"></i> Déconnexion</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="hidden md:flex items-center space-x-3">
                            <a href="<?php echo $base_path; ?>login.php" 
                               class="px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth text-blue-100 hover:text-white font-medium">
                                Connexion
                            </a>
                            <a href="<?php echo $base_path; ?>register.php" 
                               class="px-4 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-medium shadow-md transition-smooth transform hover:scale-105">
                                Inscription
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Bouton mobile -->
                    <button id="mobile-menu-button" class="lg:hidden text-white focus:outline-none ml-2">
                        <div class="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Menu mobile -->
        <div id="mobile-menu" class="mobile-menu lg:hidden">
            <div class="container mx-auto px-4 py-3">
                <!-- Barre de recherche mobile -->
                <form action="<?php echo $base_path; ?>search.php" method="GET" class="mb-4 flex">
                    <input type="text" name="q" placeholder="Rechercher..." 
                           class="flex-1 px-4 py-2 rounded-l-lg bg-white bg-opacity-20 focus:bg-opacity-30 focus:outline-none text-white placeholder-blue-100">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-400 px-4 py-2 rounded-r-lg text-white">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <ul class="space-y-2">
                    <li>
                        <a href="<?php echo $base_path; ?>index.php" 
                           class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'index.php') ? 'bg-white bg-opacity-10' : '' ?>">
                            <i class="fas fa-home mr-3"></i> Accueil
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>library.php" 
                           class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'library.php') ? 'bg-white bg-opacity-10' : '' ?>">
                            <i class="fas fa-book mr-3"></i> Bibliothèque
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>testimonials.php" 
                           class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'testimonials.php') ? 'bg-white bg-opacity-10' : '' ?>">
                            <i class="fas fa-comment-alt mr-3"></i> Avis
                        </a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li>
                            <a href="<?php echo $base_path; ?>user/my-library.php" 
                               class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'my-library.php') ? 'bg-white bg-opacity-10' : '' ?>">
                                <i class="fas fa-bookmark mr-3"></i> Ma Bibliothèque
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>user/profile.php" 
                               class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'profile.php') ? 'bg-white bg-opacity-10' : '' ?>">
                                <i class="fas fa-user mr-3"></i> Mon Profil
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>logout.php" 
                               class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth text-red-300">
                                <i class="fas fa-sign-out-alt mr-3"></i> Déconnexion
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="pt-2 border-t border-blue-600 dark:border-gray-700">
                            <a href="<?php echo $base_path; ?>login.php" 
                               class="block px-4 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-smooth font-medium <?php echo ($current_page == 'login.php') ? 'bg-white bg-opacity-10' : '' ?>">
                                <i class="fas fa-sign-in-alt mr-3"></i> Connexion
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>register.php" 
                               class="block px-4 py-3 rounded-lg bg-gradient-to-r from-blue-600 to-blue-500 text-white font-medium text-center shadow-md transition-smooth">
                                <i class="fas fa-user-plus mr-3"></i> Créer un compte
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <!-- Espace pour le header fixe -->
    <div class="h-16"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du thème
            const themeToggle = document.getElementById('theme-toggle');
            const html = document.documentElement;
            
            function updateThemeIcon() {
                if (html.classList.contains('dark')) {
                    themeToggle.innerHTML = '<i class="fas fa-sun text-yellow-300"></i>';
                    themeToggle.title = 'Passer en mode clair';
                } else {
                    themeToggle.innerHTML = '<i class="fas fa-moon text-blue-100"></i>';
                    themeToggle.title = 'Passer en mode sombre';
                }
            }
            
            themeToggle.addEventListener('click', function() {
                html.classList.toggle('dark');
                localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
                updateThemeIcon();
            });
            
            // Vérifier le thème au chargement
            if (localStorage.getItem('theme') === 'dark' || 
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.classList.add('dark');
            }
            updateThemeIcon();
            
            // Menu déroulant desktop
            const accountDropdown = document.getElementById('accountDropdown');
            const accountDropdownBtn = document.getElementById('accountDropdownBtn');
            const accountMenu = document.getElementById('accountMenu');
            
            let dropdownTimeout;
            
            function showDropdown() {
                clearTimeout(dropdownTimeout);
                accountMenu.classList.add('show');
            }
            
            function hideDropdown() {
                dropdownTimeout = setTimeout(() => {
                    accountMenu.classList.remove('show');
                }, 200);
            }
            
            if (accountDropdownBtn) {
                accountDropdownBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    accountMenu.classList.toggle('show');
                });
                
                accountDropdown.addEventListener('mouseenter', showDropdown);
                accountDropdown.addEventListener('mouseleave', hideDropdown);
                
                accountMenu.addEventListener('mouseenter', showDropdown);
                accountMenu.addEventListener('mouseleave', hideDropdown);
            }
            
            // Menu mobile
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const hamburger = document.querySelector('.hamburger');
            
            if (mobileMenuButton) {
                mobileMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('open');
                    hamburger.classList.toggle('open');
                });
            }
            
            // Fermer le menu mobile quand on clique sur un lien
            const mobileLinks = document.querySelectorAll('#mobile-menu a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('open');
                    hamburger.classList.remove('open');
                });
            });
            
            // Fermer les menus quand on clique ailleurs
            document.addEventListener('click', function(e) {
                if (!accountDropdown?.contains(e.target)) {
                    accountMenu?.classList.remove('show');
                }
                
                if (!mobileMenu?.contains(e.target) && !mobileMenuButton?.contains(e.target)) {
                    mobileMenu?.classList.remove('open');
                    hamburger?.classList.remove('open');
                }
            });
            
            // Ajouter une ombre au header lors du défilement
            const header = document.querySelector('header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.classList.add('shadow-lg');
                } else {
                    header.classList.remove('shadow-lg');
                }
            });
        });
    </script>