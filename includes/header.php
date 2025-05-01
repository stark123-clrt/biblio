<?php
// Vérifier si une session n'est pas déjà démarrée avant d'en démarrer une nouvelle
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Déterminer le chemin de base en fonction de l'emplacement du fichier actuel
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base_path = '../'; // Si on est dans le dossier admin
} elseif (strpos($_SERVER['PHP_SELF'], '/user/') !== false) {
    $base_path = '../'; // Si on est dans le dossier user
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque Chrétienne - <?php echo $page_title ?? 'Accueil'; ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery pour AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Style pour le menu déroulant */
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 12rem;
            margin-top: 0.5rem;
            padding: 0.5rem 0;
            z-index: 10;
        }
        
        /* Cette classe sera ajoutée par JavaScript pour afficher le menu */
        .dropdown-menu.show {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-blue-800 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <a href="<?php echo $base_path; ?>index.php" class="text-2xl font-bold">Bibliothèque Chrétienne</a>
                </div>
                <div class="flex-1 mx-10">
                    <form action="<?php echo $base_path; ?>search.php" method="GET" class="flex">
                        <input type="text" name="q" placeholder="Rechercher un livre..." 
                               class="w-full px-4 py-2 rounded-l-lg focus:outline-none text-gray-800">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-r-lg">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <nav>
                    <ul class="flex space-x-6">
                        <li><a href="<?php echo $base_path; ?>index.php" class="hover:text-blue-200">Accueil</a></li>
                        <li><a href="<?php echo $base_path; ?>library.php" class="hover:text-blue-200">Bibliothèque</a></li>
                        <li><a href="<?php echo $base_path; ?>testimonials.php" class="hover:text-blue-200">Témoignages</a></li>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li>
                                <a href="<?php echo $base_path; ?>user/my-library.php" class="hover:text-blue-200">Ma Bibliothèque</a>
                            </li>
                            <li class="relative" id="accountDropdown">
                                <a href="#" class="hover:text-blue-200" id="accountDropdownBtn">
                                    Mon Compte <i class="fas fa-chevron-down text-xs ml-1"></i>
                                </a>
                                <ul class="dropdown-menu" id="accountMenu">
                                    <li><a href="<?php echo $base_path; ?>user/profile.php" class="block px-4 py-2 hover:bg-gray-100">Profil</a></li>
                                    <li><a href="<?php echo $base_path; ?>user/notes.php" class="block px-4 py-2 hover:bg-gray-100">Mes Notes</a></li>
                                    <li><a href="<?php echo $base_path; ?>user/comments.php" class="block px-4 py-2 hover:bg-gray-100">Mes Commentaires</a></li>
                                    <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                        <li><a href="<?php echo $base_path; ?>admin/index.php" class="block px-4 py-2 hover:bg-gray-100">Administration</a></li>
                                    <?php endif; ?>
                                    <li><a href="<?php echo $base_path; ?>logout.php" class="block px-4 py-2 hover:bg-gray-100">Déconnexion</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li><a href="<?php echo $base_path; ?>login.php" class="hover:text-blue-200">Connexion</a></li>
                            <li><a href="<?php echo $base_path; ?>register.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">Inscription</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-6">
        <!-- Contenu de la page -->
        
    <script>
        // Script pour gérer le menu déroulant de manière plus robuste
        document.addEventListener('DOMContentLoaded', function() {
            const accountDropdown = document.getElementById('accountDropdown');
            const accountDropdownBtn = document.getElementById('accountDropdownBtn');
            const accountMenu = document.getElementById('accountMenu');
            let timeoutId;
            
            // Fonction pour afficher le menu
            function showMenu() {
                clearTimeout(timeoutId); // Annuler tout délai de fermeture en cours
                accountMenu.classList.add('show');
            }
            
            // Fonction pour masquer le menu avec délai
            function hideMenuWithDelay() {
                timeoutId = setTimeout(function() {
                    accountMenu.classList.remove('show');
                }, 300); // Délai de 300ms avant de fermer le menu
            }
            
            // Afficher le menu au clic sur le bouton
            accountDropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                accountMenu.classList.toggle('show');
            });
            
            // Afficher le menu au survol du conteneur
            accountDropdown.addEventListener('mouseenter', showMenu);
            
            // Masquer le menu avec délai quand on quitte le conteneur
            accountDropdown.addEventListener('mouseleave', hideMenuWithDelay);
            
            // Annuler le délai quand on entre dans le menu
            accountMenu.addEventListener('mouseenter', showMenu);
            
            // Remettre le délai quand on quitte le menu
            accountMenu.addEventListener('mouseleave', hideMenuWithDelay);
            
            // Fermer le menu si on clique ailleurs sur la page
            document.addEventListener('click', function(e) {
                if (!accountDropdown.contains(e.target)) {
                    accountMenu.classList.remove('show');
                }
            });
        });
    </script>