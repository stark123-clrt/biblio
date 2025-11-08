<?php
session_start();

require_once "../classes/Core.php";
require_once "../classes/Models.php";
require_once "../classes/Repositories.php";

// Nettoyage du tampon de sortie
while (ob_get_level()) {
    ob_end_clean();
}

// Définir l'en-tête JSON
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Non authentifié'
    ]);
    exit;
}

// Vérifier les paramètres
if (!isset($_POST['post_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'ID du post manquant'
    ]);
    exit;
}

$post_id = (int)$_POST['post_id'];
$user_id = $_SESSION['user_id'];

try {
    $postRepo = new ForumPostRepository();
    
    // Toggle le like
    $result = $postRepo->toggleLike($post_id, $user_id);
    
    if ($result) {
        // Récupérer le post mis à jour avec le nombre de likes
        $post = $postRepo->findById($post_id);
        
        // Compter les likes
        $conn = getDatabase();
        $countQuery = "SELECT COUNT(*) as count FROM forum_post_likes WHERE post_id = :post_id";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $countStmt->execute();
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $likes_count = (int)$countRow['count'];
        
        // Vérifier si l'utilisateur actuel a liké
        $checkQuery = "SELECT id FROM forum_post_likes WHERE post_id = :post_id AND user_id = :user_id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $is_liked = $checkStmt->fetch() !== false;
        
        echo json_encode([
            'success' => true,
            'likes_count' => $likes_count,
            'is_liked' => $is_liked,
            'message' => $is_liked ? 'Post liké' : 'Like retiré'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du traitement'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Toggle like error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>