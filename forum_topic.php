<?php
session_start();

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;


// Récupérer l'ID du topic
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;

// Gérer la création d'un nouveau post
$post_error = '';
$post_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && isset($_POST['create_post'])) {
    $content = trim($_POST['content'] ?? '');
    
    if (empty($content)) {
        $post_error = "Le message ne peut pas être vide.";
    } elseif (strlen($content) < 5) {
        $post_error = "Le message doit contenir au moins 5 caractères.";
    } else {
        $postRepo = new ForumPostRepository();
        $newPost = $postRepo->create($topic_id, $current_user_id, $content);
        
        if ($newPost) {
            $post_success = "Votre message a été publié avec succès !";
            // Rediriger vers la dernière page
            $total_posts = $postRepo->countByTopic($topic_id);
            $last_page = ceil($total_posts / $per_page);
            header("Location: forum_topic.php?id=$topic_id&page=$last_page#post-" . $newPost->id);
            exit;
        } else {
            $post_error = "Une erreur est survenue lors de la publication de votre message.";
        }
    }
}

try {
    // Repositories
    $topicRepo = new ForumTopicRepository();
    $postRepo = new ForumPostRepository();
    
    // Récupérer le topic
    $topic = $topicRepo->findById($topic_id);
    
    if (!$topic) {
        throw new Exception("Discussion non trouvée");
    }
    
    // Incrémenter les vues
    $topicRepo->incrementViews($topic_id);
    
    // Récupérer les posts
    $posts = $postRepo->findByTopic($topic_id, $page, $per_page, $current_user_id);
    
    // Pagination
    $total_posts = $postRepo->countByTopic($topic_id);
    $total_pages = ceil($total_posts / $per_page);
    
} catch (Exception $e) {
    error_log("Forum topic page error: " . $e->getMessage());
    header("Location: forum.php");
    exit;
}

$page_title = htmlspecialchars($topic->title) . " - Forum";
include "includes/header.php";
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900">
    <div class="container mx-auto px-4 py-8">
        
        <!-- Breadcrumb -->
        <nav class="mb-8">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="forum.php" class="hover:text-blue-600 dark:hover:text-blue-400">Forum</a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li><a href="forum_category.php?id=<?php echo $topic->category_id; ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                    <?php echo htmlspecialchars($topic->category->name); ?>
                </a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li class="text-gray-800 dark:text-white font-medium truncate max-w-md"><?php echo htmlspecialchars($topic->title); ?></li>
            </ol>
        </nav>

        <!-- En-tête du topic -->
        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-6 mb-6 shadow-lg border border-white/20 dark:border-gray-700/20">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-2 mb-3">
                        <?php if ($topic->isPinned()): ?>
                            <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 px-3 py-1 rounded-full text-xs font-semibold">
                                <i class="fas fa-thumbtack mr-1"></i>Épinglé
                            </span>
                        <?php endif; ?>
                        <?php if ($topic->isLocked()): ?>
                            <span class="bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-3 py-1 rounded-full text-xs font-semibold">
                                <i class="fas fa-lock mr-1"></i>Verrouillé
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-3"><?php echo htmlspecialchars($topic->title); ?></h1>
                    
                    <div class="flex items-center space-x-6 text-sm text-gray-500 dark:text-gray-400">
                        <div class="flex items-center space-x-2">
                            <?php echo renderAvatar($topic->user, 'w-8 h-8', 'text-xs'); ?>
                            <span class="font-medium">Par <?php echo htmlspecialchars($topic->user->username); ?></span>
                        </div>
                        <span><i class="far fa-clock mr-1"></i><?php echo timeAgo($topic->created_at); ?></span>
                        <span><i class="far fa-eye mr-1"></i><?php echo number_format($topic->views_count); ?> vues</span>
                        <span><i class="far fa-comments mr-1"></i><?php echo number_format($total_posts); ?> réponses</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages d'erreur/succès -->
        <?php if ($post_error): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                <p class="text-red-800 dark:text-red-200"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($post_error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($post_success): ?>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                <p class="text-green-800 dark:text-green-200"><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($post_success); ?></p>
            </div>
        <?php endif; ?>

        <!-- Liste des posts -->
        <div class="space-y-4">
            <?php foreach ($posts as $index => $post): ?>
                <div id="post-<?php echo $post->id; ?>" class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-xl shadow-md border border-white/20 dark:border-gray-700/20 overflow-hidden">
                    <div class="flex">
                        <!-- Sidebar utilisateur -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-6 w-48 flex-shrink-0 hidden md:flex flex-col items-center text-center border-r border-gray-200 dark:border-gray-700">
                            <?php echo renderAvatar($post->user, 'w-20 h-20', 'text-2xl'); ?>
                            
                            <h3 class="font-bold text-gray-900 dark:text-white mb-1 mt-3">
                                <?php echo htmlspecialchars($post->user->username); ?>
                            </h3>
                            
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                Membre depuis<br>
                                <?php echo date('M Y', strtotime($post->user->created_at)); ?>
                            </p>
                            
                            <?php if ($index === 0): ?>
                                <span class="bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 px-2 py-1 rounded-full text-xs font-semibold">
                                    <i class="fas fa-star mr-1"></i>Auteur
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Contenu du post -->
                        <div class="flex-1 p-6">
                            <!-- En-tête mobile -->
                            <div class="flex items-center space-x-3 mb-4 md:hidden">
                                <?php echo renderAvatar($post->user, 'w-10 h-10', 'text-sm'); ?>
                                <div>
                                    <p class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($post->user->username); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo timeAgo($post->created_at); ?></p>
                                </div>
                            </div>

                            <!-- Contenu du message -->
                            <div class="prose prose-blue dark:prose-invert max-w-none mb-4 text-gray-800 dark:text-gray-200">
                                <?php echo $post->getFormattedContent(); ?>
                            </div>

                            <?php if ($post->isEdited()): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic mb-4">
                                    <i class="fas fa-edit mr-1"></i>Modifié le <?php echo date('d/m/Y à H:i', strtotime($post->edited_at)); ?>
                                </p>
                            <?php endif; ?>

                            <!-- Footer du post -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-center space-x-4">
                                    <!-- Bouton Like -->
                                    <?php if ($is_logged_in): ?>
                                        <button onclick="toggleLike(<?php echo $post->id; ?>)" 
                                                id="like-btn-<?php echo $post->id; ?>"
                                                class="flex items-center space-x-2 px-3 py-2 rounded-lg transition-all duration-300 <?php echo $post->is_liked_by_current_user ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400'; ?>">
                                            <i class="<?php echo $post->is_liked_by_current_user ? 'fas' : 'far'; ?> fa-thumbs-up"></i>
                                            <span id="like-count-<?php echo $post->id; ?>"><?php echo number_format($post->likes_count); ?></span>
                                        </button>
                                    <?php else: ?>
                                        <div class="flex items-center space-x-2 text-gray-500 dark:text-gray-400">
                                            <i class="far fa-thumbs-up"></i>
                                            <span><?php echo number_format($post->likes_count); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400 hidden md:block">
                                    <i class="far fa-clock mr-1"></i><?php echo timeAgo($post->created_at); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $topic_id; ?>&page=<?php echo ($page - 1); ?>" 
                           class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?id=<?php echo $topic_id; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 rounded-lg transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $topic_id; ?>&page=<?php echo ($page + 1); ?>" 
                           class="px-4 py-2 bg-white dark:bg-gray-800 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Formulaire de réponse -->
        <?php if ($is_logged_in): ?>
            <?php if (!$topic->isLocked()): ?>
                <div class="mt-8 bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg rounded-2xl p-6 shadow-lg border border-white/20 dark:border-gray-700/20">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-reply mr-2"></i>Répondre à cette discussion
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="create_post" value="1">
                        
                        <div class="mb-4">
                            <textarea name="content" 
                                      required
                                      minlength="5"
                                      rows="6"
                                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white resize-none"
                                      placeholder="Partagez votre réponse..."></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all duration-300">
                                <i class="fas fa-paper-plane mr-2"></i>Publier la réponse
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-8 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6 text-center">
                    <i class="fas fa-lock text-4xl text-red-600 dark:text-red-400 mb-3"></i>
                    <p class="text-lg font-semibold text-red-800 dark:text-red-200">Cette discussion est verrouillée</p>
                    <p class="text-sm text-red-600 dark:text-red-300 mt-2">Vous ne pouvez plus ajouter de réponses.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 text-center">
                <i class="fas fa-user-lock text-4xl text-blue-600 dark:text-blue-400 mb-3"></i>
                <p class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">Connectez-vous pour participer</p>
                <p class="text-sm text-blue-600 dark:text-blue-300 mb-4">Vous devez être connecté pour répondre à cette discussion.</p>
                <a href="login.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Script pour les likes -->
<?php if ($is_logged_in): ?>
<script>
function toggleLike(postId) {
    const likeBtn = document.getElementById('like-btn-' + postId);
    const likeCount = document.getElementById('like-count-' + postId);
    
    fetch('ajax/toggle_post_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            likeCount.textContent = data.likes_count;
            
            // Changer le style du bouton
            const icon = likeBtn.querySelector('i');
            if (data.is_liked) {
                likeBtn.classList.add('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-600', 'dark:text-blue-400');
                likeBtn.classList.remove('hover:bg-gray-100', 'dark:hover:bg-gray-700', 'text-gray-600', 'dark:text-gray-400');
                icon.classList.remove('far');
                icon.classList.add('fas');
            } else {
                likeBtn.classList.remove('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-600', 'dark:text-blue-400');
                likeBtn.classList.add('hover:bg-gray-100', 'dark:hover:bg-gray-700', 'text-gray-600', 'dark:text-gray-400');
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
<?php endif; ?>

<?php include "includes/footer.php"; ?>