<?php
// ajax/update_comment.php - Mise à jour d'un commentaire
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si les données requises sont présentes
if (!isset($_POST['comment_id']) || !isset($_POST['book_id']) || !isset($_POST['rating']) || !isset($_POST['comment_text'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$user_id = $_SESSION['user_id'];
$comment_id = intval($_POST['comment_id']);
$book_id = intval($_POST['book_id']);
$rating = intval($_POST['rating']);
$comment_text = trim($_POST['comment_text']);

// Validation de base
if ($comment_id <= 0 || $book_id <= 0 || $rating < 1 || $rating > 5 || empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

require_once "../config/database.php";

try {
    // Vérifier si le commentaire existe et appartient à l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM comments WHERE id = :comment_id AND user_id = :user_id");
    $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Commentaire non trouvé ou non autorisé']);
        exit;
    }
    
    // Mettre à jour le commentaire et réinitialiser le statut de validation
    $stmt = $conn->prepare("UPDATE comments SET 
                        comment_text = :comment_text, 
                        rating = :rating, 
                        is_validated = 0, 
                        updated_at = NOW() 
                        WHERE id = :comment_id");
    $stmt->bindParam(':comment_text', $comment_text);
    $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
    $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Commentaire mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du commentaire']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>