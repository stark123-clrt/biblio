<?php
// ajax/toggle_favorite.php - Ajouter/retirer un livre des favoris
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si les données requises sont présentes
if (!isset($_POST['book_id']) || !isset($_POST['is_favorite'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = intval($_POST['book_id']);
$is_favorite = intval($_POST['is_favorite']) ? 1 : 0;

// Validation de base
if ($book_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

require_once "../config/database.php";

try {
    // Vérifier si le livre est dans la bibliothèque de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Mettre à jour l'état du favori
        $stmt = $conn->prepare("UPDATE user_library SET is_favorite = :is_favorite WHERE user_id = :user_id AND book_id = :book_id");
        $stmt->bindParam(':is_favorite', $is_favorite, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $is_favorite ? 'Livre ajouté aux favoris' : 'Livre retiré des favoris']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du favori']);
        }
    } else {
        // Ajouter le livre à la bibliothèque de l'utilisateur avec l'état du favori
        $stmt = $conn->prepare("INSERT INTO user_library (user_id, book_id, is_favorite, added_at) VALUES (:user_id, :book_id, :is_favorite, NOW())");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':is_favorite', $is_favorite, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Livre ajouté à la bibliothèque et aux favoris']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout à la bibliothèque']);
        }
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>