<?php
// actions/toggle_favorite.php - Script pour ajouter/retirer un livre des favoris
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Vérifier si un ID de livre a été passé
if (!isset($_POST['book_id']) || empty($_POST['book_id'])) {
    $_SESSION['error_message'] = "Aucun livre spécifié.";
    header("Location: ../user/my-library.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];

try {
    // Récupérer l'état actuel du livre (favori ou non)
    $stmt = $conn->prepare("SELECT is_favorite FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $is_favorite = $stmt->fetchColumn();
        $new_status = $is_favorite ? 0 : 1;
        
        // Mettre à jour le statut de favori
        $stmt = $conn->prepare("UPDATE user_library SET is_favorite = :is_favorite WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindParam(':is_favorite', $new_status, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success_message'] = $new_status 
            ? "Le livre a été ajouté à vos favoris." 
            : "Le livre a été retiré de vos favoris.";
    } else {
        // Le livre n'est pas dans la bibliothèque de l'utilisateur, l'ajouter d'abord
        $stmt = $conn->prepare("INSERT INTO user_library (user_id, book_id, is_favorite, added_at) 
                              VALUES (:user_id, :book_id, 1, NOW())");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success_message'] = "Le livre a été ajouté à votre bibliothèque et à vos favoris.";
    }
    
    // Redirection vers la page du livre
    header("Location: ../book.php?id=" . $book_id);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Une erreur est survenue: " . $e->getMessage();
    header("Location: ../book.php?id=" . $book_id);
    exit();
}
?>