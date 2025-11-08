<?php
session_start();

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;

try {
    // Créer le repository
    $forumCategoryRepo = new ForumCategoryRepository();
    
    // Récupérer toutes les catégories avec statistiques
    $categories = $forumCategoryRepo->findAllWithStats();
    
    // Calculer les statistiques globales
    $total_topics = 0;
    $total_posts = 0;
    foreach ($categories as $category) {
        $total_topics += $category->topics_count;
        $total_posts += $category->posts_count;
    }
    
    // Récupérer le nombre total de membres (si vous avez un UserRepository)
    $userRepo = new UserRepository();
    $total_members = $userRepo->countAll();
    
} catch (Exception $e) {
    error_log("Forum page error: " . $e->getMessage());
    $categories = [];
    $total_topics = 0;
    $total_posts = 0;
    $total_members = 0;
}

$page_title = "Forum Communautaire";
include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-extrabold bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent mb-4">
                Forum Communautaire
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 font-light max-w-2xl mx-auto">
                Rejoignez la discussion, partagez vos idées et connectez-vous avec d'autres passionnés de lecture
            </p>
        </div>

        <!-- Statistiques globales -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <!-- Total discussions -->
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-6 shadow-lg border border-white/20 dark:border-gray-700/20 hover:scale-105 transition-transform duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Discussions</p>
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo number_format($total_topics); ?></p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/30 p-4 rounded-xl">
                        <i class="fas fa-comments text-3xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                </div>
            </div>

            <!-- Total messages -->
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-6 shadow-lg border border-white/20 dark:border-gray-700/20 hover:scale-105 transition-transform duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Messages</p>
                        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo number_format($total_posts); ?></p>
                    </div>
                    <div class="bg-purple-100 dark:bg-purple-900/30 p-4 rounded-xl">
                        <i class="fas fa-comment-dots text-3xl text-purple-600 dark:text-purple-400"></i>
                    </div>
                </div>
            </div>

            <!-- Total membres -->
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-6 shadow-lg border border-white/20 dark:border-gray-700/20 hover:scale-105 transition-transform duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Membres</p>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo number_format($total_members); ?></p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/30 p-4 rounded-xl">
                        <i class="fas fa-users text-3xl text-green-600 dark:text-green-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des catégories -->
        <div class="space-y-4">
            <?php foreach ($categories as $category): ?>
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/20 overflow-hidden hover:shadow-xl transition-all duration-300">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <!-- Icône et infos catégorie -->
                            <div class="flex items-start space-x-4 flex-1">
                                <div class="p-4 rounded-xl" style="background-color: <?php echo htmlspecialchars($category->color); ?>20;">
                                    <i class="<?php echo htmlspecialchars($category->icon); ?> text-3xl" style="color: <?php echo htmlspecialchars($category->color); ?>;"></i>
                                </div>
                                
                                <div class="flex-1">
                                    <a href="forum_category.php?id=<?php echo $category->id; ?>" class="text-2xl font-bold text-gray-800 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <?php echo htmlspecialchars($category->name); ?>
                                    </a>
                                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                                        <?php echo htmlspecialchars($category->description); ?>
                                    </p>
                                    
                                    <!-- Statistiques de la catégorie -->
                                    <div class="flex items-center space-x-6 mt-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center">
                                            <i class="fas fa-comment-alt mr-2"></i>
                                            <?php echo number_format($category->topics_count); ?> discussions
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-comments mr-2"></i>
                                            <?php echo number_format($category->posts_count); ?> messages
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dernier post -->
                            <div class="ml-6 min-w-[250px] hidden lg:block">
                                <?php if ($category->last_post): ?>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Dernier message</p>
                                        <div class="flex items-center space-x-3">
                                            <?php echo renderAvatar($category->last_post->user, 'w-10 h-10', 'text-sm'); ?>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($category->last_post->user->username); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo timeAgo($category->last_post->created_at); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Aucun message</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($categories)): ?>
            <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-12 text-center shadow-lg">
                <i class="fas fa-comments text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                <p class="text-xl text-gray-600 dark:text-gray-400">Aucune catégorie disponible pour le moment.</p>
            </div>
        <?php endif; ?>

        <!-- Bouton floating pour créer une discussion (si connecté) -->
        <?php if ($is_logged_in): ?>
            <div class="fixed bottom-8 right-8 z-50">
                <button onclick="alert('Choisissez d\'abord une catégorie pour créer une discussion !')" 
                        class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-4 rounded-full shadow-2xl hover:shadow-3xl hover:scale-110 transition-all duration-300 flex items-center space-x-2">
                    <i class="fas fa-plus text-xl"></i>
                    <span class="font-semibold hidden sm:inline">Nouvelle discussion</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>