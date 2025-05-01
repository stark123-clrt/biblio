<?php
session_start();
require_once "../config/database.php";

// Nettoyer tout buffer de sortie existant pour éviter les caractères invisibles
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_POST['book_id']) || !isset($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$book_id = (int)$_POST['book_id'];
$user_id = $_SESSION['user_id'];
$rating = (int)$_POST['rating'];
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

// Validation
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'La note doit être entre 1 et 5']);
    exit;
}

try {
    // Vérifier si l'utilisateur a déjà noté ce livre
    $stmt = $conn->prepare("SELECT id FROM comments WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Mettre à jour la note existante
        $comment_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        
        $stmt = $conn->prepare("UPDATE comments 
                               SET rating = :rating, comment_text = :comment_text, updated_at = NOW() 
                               WHERE id = :comment_id");
        $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
        $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Votre note a été mise à jour avec succès']);
    } else {
        // Ajouter une nouvelle note
        $stmt = $conn->prepare("INSERT INTO comments (user_id, book_id, comment_text, rating, is_validated) 
                               VALUES (:user_id, :book_id, :comment_text, :rating, :is_validated)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
        $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
        
        // Les notes peuvent être automatiquement validées ou nécessiter une validation 
        // selon la configuration de votre application
        $auto_validate = 1; // Changer à 0 si la validation est requise
        $stmt->bindParam(':is_validated', $auto_validate, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Merci pour votre note !']);
    }
} catch (PDOException $e) {
    error_log("Erreur de base de données: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement de votre note.']);
}
// Pas d'espace ou de ligne après la balise de fermeture
?>