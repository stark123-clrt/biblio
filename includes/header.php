<?php
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
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque Chrétienne - <?php echo $page_title ?? 'Accueil'; ?></title>
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Bibliothèque chrétienne en ligne avec des livres spirituels, témoignages et ressources pour votre croissance spirituelle">
    <meta name="keywords" content="bibliothèque chrétienne, livres spirituels, témoignages, croissance spirituelle">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base_path; ?>assets/favicon.ico" type="image/x-icon">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Styles personnalisés */
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            min-width: 200px;
            z-index: 50;
            margin-top: 0.5rem;
            padding: 0.5rem 0;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }
        
        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .dropdown-menu a {
            display: block;
            padding: 0.5rem 1rem;
            color: #4b5563;
            transition: all 0.2s ease;
        }
        
        .dropdown-menu a:hover {
            background-color: #f3f4f6;
            color: #1f2937;
        }
        
        .dark .dropdown-menu {
            background-color: #1f2937;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.25), 0 4px 6px -2px rgba(0, 0, 0, 0.1);
        }
        
        .dark .dropdown-menu a {
            color: #d1d5db;
        }
        
        .dark .dropdown-menu a:hover {
            background-color: #374151;
            color: #f9fafb;
        }
        
        /* Style pour le menu mobile */
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .mobile-menu.open {
            max-height: 500px;
        }
        
        /* Animation pour les liens */
        .nav-link {
            position: relative;
            transition: all 0.2s ease;
        }
        
        .nav-link.active {
            font-weight: 600;
            border-bottom: 2px solid white;
        }
        
        /* Style pour le bouton hamburger */
        .hamburger {
            width: 24px;
            height: 24px;
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
            transition: all 0.3s ease;
        }
        
        .hamburger span:nth-child(1) {
            top: 4px;
        }
        
        .hamburger span:nth-child(2), .hamburger span:nth-child(3) {
            top: 12px;
        }
        
        .hamburger span:nth-child(4) {
            top: 20px;
        }
        
        .hamburger.open span:nth-child(1) {
            top: 12px;
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
            top: 12px;
            width: 0%;
            left: 50%;
        }
        
        /* Style pour le bouton de thème */
        .theme-toggle {
            transition: all 0.3s ease;
        }
        
        .theme-toggle:hover {
            transform: rotate(15deg);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex flex-col">
    <header class="bg-blue-800 dark:bg-gray-800 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <!-- Logo et bouton mobile -->
                <div class="flex items-center">
                    <a href="<?php echo $base_path; ?>index.php" class="text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-book-open mr-2"></i>
                        <span class="hidden sm:inline">Bibliothèque Chrétienne</span>
                        <span class="sm:hidden">BC</span>
                    </a>
                </div>
                
                <!-- Boutons droite (recherche, thème, compte) -->
                <div class="flex items-center space-x-4">
                    <!-- Bouton thème -->
                    <button id="theme-toggle" class="theme-toggle p-2 rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <!-- Bouton mobile -->
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <div class="hamburger">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </button>
                </div>
                
                <!-- Navigation desktop -->
                <nav class="hidden md:block">
                    <ul class="flex space-x-2 lg:space-x-6 items-center">
                        <li>
                            <a href="<?php echo $base_path; ?>index.php" 
                               class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 <?php echo ($current_page == 'index.php') ? 'active' : '' ?>">
                                <i class="fas fa-home mr-1"></i>
                                <span>Accueil</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>library.php" 
                               class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 <?php echo ($current_page == 'library.php') ? 'active' : '' ?>">
                                <i class="fas fa-book mr-1"></i>
                                <span>Bibliothèque</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>testimonials.php" 
                               class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 <?php echo ($current_page == 'testimonials.php') ? 'active' : '' ?>">
                                <i class="fas fa-comment-alt mr-1"></i>
                                <span>Témoignages</span>
                            </a>
                        </li>
                        
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li>
                                <a href="<?php echo $base_path; ?>user/my-library.php" 
                                   class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 <?php echo ($current_page == 'my-library.php') ? 'active' : '' ?>">
                                    <i class="fas fa-bookmark mr-1"></i>
                                    <span>Ma Bibliothèque</span>
                                </a>
                            </li>
                            <li class="relative" id="accountDropdown">
                                <a href="#" class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 flex items-center" id="accountDropdownBtn">
                                    <i class="fas fa-user-circle mr-1"></i>
                                    <span>Mon Compte</span>
                                    <i class="fas fa-chevron-down text-xs ml-1"></i>
                                </a>
                                <ul class="dropdown-menu" id="accountMenu">
                                    <li><a href="<?php echo $base_path; ?>user/profile.php" class="flex items-center"><i class="fas fa-user mr-2"></i> Profil</a></li>
                                    <li><a href="<?php echo $base_path; ?>user/notes.php" class="flex items-center"><i class="fas fa-sticky-note mr-2"></i> Mes Notes</a></li>
                                    <li><a href="<?php echo $base_path; ?>user/comments.php" class="flex items-center"><i class="fas fa-comments mr-2"></i> Mes Commentaires</a></li>
                                    <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                        <li><a href="<?php echo $base_path; ?>admin/index.php" class="flex items-center"><i class="fas fa-cog mr-2"></i> Administration</a></li>
                                    <?php endif; ?>
                                    <li class="border-t border-gray-200 dark:border-gray-700 mt-1 pt-1"><a href="<?php echo $base_path; ?>logout.php" class="flex items-center text-red-600 dark:text-red-400"><i class="fas fa-sign-out-alt mr-2"></i> Déconnexion</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li>
                                <a href="<?php echo $base_path; ?>login.php" 
                                   class="nav-link px-3 py-2 hover:text-blue-200 dark:hover:text-blue-300 <?php echo ($current_page == 'login.php') ? 'active' : '' ?>">
                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                    <span>Connexion</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo $base_path; ?>register.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg shadow-sm flex items-center">
                                    <i class="fas fa-user-plus mr-1"></i>
                                    <span>Inscription</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            
            <!-- Barre de recherche mobile -->
            <div class="mt-3 md:hidden">
                <form action="<?php echo $base_path; ?>search.php" method="GET" class="flex">
                    <input type="text" name="q" placeholder="Rechercher..." 
                           class="flex-1 px-4 py-2 rounded-l-lg focus:outline-none text-gray-800">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-r-lg">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Menu mobile -->
        <div id="mobile-menu" class="mobile-menu bg-blue-700 dark:bg-gray-800 md:hidden">
            <div class="container mx-auto px-4 py-2">
                <ul class="space-y-2 pb-3">
                    <li>
                        <a href="<?php echo $base_path; ?>index.php" 
                           class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'index.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                            <i class="fas fa-home mr-2"></i> Accueil
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>library.php" 
                           class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'library.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                            <i class="fas fa-book mr-2"></i> Bibliothèque
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>testimonials.php" 
                           class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'testimonials.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                            <i class="fas fa-comment-alt mr-2"></i> Témoignages
                        </a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li>
                            <a href="<?php echo $base_path; ?>user/my-library.php" 
                               class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'my-library.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                                <i class="fas fa-bookmark mr-2"></i> Ma Bibliothèque
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>user/profile.php" 
                               class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'profile.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                                <i class="fas fa-user mr-2"></i> Profil
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>user/notes.php" 
                               class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'notes.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                                <i class="fas fa-sticky-note mr-2"></i> Mes Notes
                            </a>
                        </li>
                        <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li>
                                <a href="<?php echo $base_path; ?>admin/index.php" 
                                   class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'index.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                                    <i class="fas fa-cog mr-2"></i> Administration
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?php echo $base_path; ?>logout.php" class="block px-3 py-2 text-red-300 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg">
                                <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo $base_path; ?>login.php" 
                               class="block px-3 py-2 hover:bg-blue-600 dark:hover:bg-gray-700 rounded-lg <?php echo ($current_page == 'login.php') ? 'bg-blue-600 dark:bg-gray-700 font-medium' : '' ?>">
                                <i class="fas fa-sign-in-alt mr-2"></i> Connexion
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $base_path; ?>register.php" 
                               class="block px-3 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg text-center <?php echo ($current_page == 'register.php') ? 'bg-blue-700 font-medium' : '' ?>">
                                <i class="fas fa-user-plus mr-2"></i> Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-6">
        <!-- Contenu de la page -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du thème
            const themeToggle = document.getElementById('theme-toggle');
            
            function updateThemeIcon() {
                if (document.documentElement.classList.contains('dark')) {
                    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                    themeToggle.title = 'Passer en mode clair';
                } else {
                    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                    themeToggle.title = 'Passer en mode sombre';
                }
            }
            
            themeToggle.addEventListener('click', function() {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                updateThemeIcon();
            });
            
            // Vérifier le thème au chargement
            if (localStorage.getItem('theme') === 'dark' || 
                (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
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
                }, 300);
            }
            
            if (accountDropdownBtn) {
                accountDropdownBtn.addEventListener('click', function(e) {
                    e.preventDefault();
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
                mobileMenuButton.addEventListener('click', function() {
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
            });
        });
    </script>