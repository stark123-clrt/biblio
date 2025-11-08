<?php
session_start();

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Récupérer l'ID de la catégorie
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;

// Gérer la création d'un nouveau topic
$create_error = '';
$create_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && isset($_POST['create_topic'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title) || empty($content)) {
        $create_error = "Le titre et le contenu sont obligatoires.";
    } elseif (strlen($title) < 5) {
        $create_error = "Le titre doit contenir au moins 5 caractères.";
    } elseif (strlen($content) < 10) {
        $create_error = "Le contenu doit contenir au moins 10 caractères.";
    } else {
        $topicRepo = new ForumTopicRepository();
        $newTopic = $topicRepo->create($category_id, $current_user_id, $title, $content);
        
        if ($newTopic) {
            $create_success = "Votre discussion a été créée avec succès !";
            header("Location: forum_topic.php?id=" . $newTopic->id);
            exit;
        } else {
            $create_error = "Une erreur est survenue lors de la création de la discussion.";
        }
    }
}

try {
    // Repositories
    $categoryRepo = new ForumCategoryRepository();
    $topicRepo = new ForumTopicRepository();
    
    // Récupérer la catégorie
    $category = $categoryRepo->findById($category_id);
    
    if (!$category) {
        throw new Exception("Catégorie non trouvée");
    }
    
    // Récupérer les topics
    $topics = $topicRepo->findByCategory($category_id, $page, $per_page);
    
    // Pagination
    $total_topics = $topicRepo->countByCategory($category_id);
    $total_pages = ceil($total_topics / $per_page);
    
} catch (Exception $e) {
    error_log("Forum category page error: " . $e->getMessage());
    header("Location: forum.php");
    exit;
}

$page_title = htmlspecialchars($category->name) . " - Forum";
include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        
        <!-- Breadcrumb -->
        <nav class="mb-8">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="forum.php" class="hover:text-blue-600 dark:hover:text-blue-400">Forum</a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li class="text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($category->name); ?></li>
            </ol>
        </nav>

        <!-- En-tête de catégorie -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-8 mb-8 shadow-lg border border-white/20 dark:border-gray-700/20">
            <div class="flex items-center space-x-4 mb-4">
                <div class="p-4 rounded-xl" style="background-color: <?php echo htmlspecialchars($category->color); ?>20;">
                    <i class="<?php echo htmlspecialchars($category->icon); ?> text-4xl" style="color: <?php echo htmlspecialchars($category->color); ?>;"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($category->name); ?></h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?php echo htmlspecialchars($category->description); ?></p>
                </div>
            </div>
            
            <?php if ($is_logged_in): ?>
                <button onclick="document.getElementById('createTopicModal').classList.remove('hidden')" 
                        class="mt-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all duration-300 flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Nouvelle discussion</span>
                </button>
            <?php else: ?>
                <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <p class="text-blue-800 dark:text-blue-200">
                        <i class="fas fa-info-circle mr-2"></i>
                        <a href="login.php" class="font-semibold hover:underline">Connectez-vous</a> pour créer une discussion
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Messages d'erreur/succès -->
        <?php if ($create_error): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                <p class="text-red-800 dark:text-red-200"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($create_error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($create_success): ?>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                <p class="text-green-800 dark:text-green-200"><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($create_success); ?></p>
            </div>
        <?php endif; ?>

        <!-- Liste des topics -->
        <div class="space-y-3">
            <?php if (empty($topics)): ?>
                <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-12 text-center shadow-lg">
                    <i class="fas fa-comment-slash text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">Aucune discussion dans cette catégorie</p>
                    <?php if ($is_logged_in): ?>
                        <button onclick="document.getElementById('createTopicModal').classList.remove('hidden')" 
                                class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all duration-300">
                            Soyez le premier à créer une discussion !
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($topics as $topic): ?>
                    <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-xl shadow-md border border-white/20 dark:border-gray-700/20 overflow-hidden hover:shadow-xl hover:scale-[1.01] transition-all duration-300">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <!-- Avatar et contenu du topic -->
                                <div class="flex gap-4 flex-1 min-w-0">
                                    <!-- Avatar de l'auteur -->
                                    <div class="flex-shrink-0 hidden sm:block">
                                        <?php echo renderAvatar($topic->user, 'w-12 h-12', 'text-base'); ?>
                                    </div>
                                    
                                    <!-- Contenu -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                                            <?php if ($topic->isPinned()): ?>
                                                <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 px-2 py-1 rounded-full text-xs font-semibold inline-flex items-center">
                                                    <i class="fas fa-thumbtack mr-1"></i>Épinglé
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($topic->isLocked()): ?>
                                                <span class="bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-2 py-1 rounded-full text-xs font-semibold inline-flex items-center">
                                                    <i class="fas fa-lock mr-1"></i>Verrouillé
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="forum_topic.php?id=<?php echo $topic->id; ?>" 
                                           class="text-xl font-bold text-gray-800 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors block mb-2 line-clamp-2">
                                            <?php echo htmlspecialchars($topic->title); ?>
                                        </a>
                                        
                                        <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 flex-wrap">
                                            <div class="flex items-center gap-2">
                                                <span class="sm:hidden">
                                                    <?php echo renderAvatar($topic->user, 'w-5 h-5', 'text-xs'); ?>
                                                </span>
                                                <span class="font-medium"><?php echo htmlspecialchars($topic->user->username); ?></span>
                                            </div>
                                            <span class="flex items-center">
                                                <i class="far fa-clock mr-1"></i><?php echo timeAgo($topic->created_at); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistiques et dernier post -->
                                <div class="flex items-center gap-4 flex-shrink-0">
                                    <!-- Stats (desktop) -->
                                    <div class="hidden lg:flex items-center gap-6 text-center">
                                        <div class="min-w-[60px]">
                                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo number_format($topic->posts_count); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">réponses</p>
                                        </div>
                                        <div class="min-w-[60px]">
                                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?php echo number_format($topic->views_count); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">vues</p>
                                        </div>
                                    </div>

                                    <!-- Stats (mobile) -->
                                    <div class="flex lg:hidden items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center">
                                            <i class="far fa-comments mr-1 text-blue-600"></i><?php echo $topic->posts_count; ?>
                                        </span>
                                        <span class="flex items-center">
                                            <i class="far fa-eye mr-1 text-purple-600"></i><?php echo $topic->views_count; ?>
                                        </span>
                                    </div>

                                    <!-- Dernier post (desktop uniquement) -->
                                    <?php if ($topic->last_post): ?>
                                        <div class="hidden xl:block">
                                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 min-w-[200px]">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Dernier message</p>
                                                <div class="flex items-center gap-2">
                                                    <?php echo renderAvatar($topic->last_post->user, 'w-8 h-8', 'text-xs'); ?>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                                            <?php echo htmlspecialchars($topic->last_post->user->username); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            <?php echo timeAgo($topic->last_post->created_at); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo ($page - 1); ?>" 
                           class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 rounded-lg transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo ($page + 1); ?>" 
                           class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de création de topic -->
<?php if ($is_logged_in): ?>
<div id="createTopicModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Créer une nouvelle discussion</h2>
            <button onclick="document.getElementById('createTopicModal').classList.add('hidden')" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-6">
            <input type="hidden" name="create_topic" value="1">
            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Titre de la discussion *
                </label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       required
                       minlength="5"
                       maxlength="200"
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                       placeholder="Un titre accrocheur pour votre discussion...">
            </div>
            
            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Message *
                </label>
                <textarea id="content" 
                          name="content" 
                          required
                          minlength="10"
                          rows="8"
                          class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white resize-none"
                          placeholder="Partagez vos pensées, posez une question, lancez un débat..."></textarea>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minimum 10 caractères</p>
            </div>
            
            <div class="flex items-center justify-end space-x-4 pt-4">
                <button type="button" 
                        onclick="document.getElementById('createTopicModal').classList.add('hidden')"
                        class="px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Annuler
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all duration-300">
                    <i class="fas fa-paper-plane mr-2"></i>Publier
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include "includes/footer.php"; ?>