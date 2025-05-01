<?php
// actions/add_to_library.php - Script pour ajouter un livre à la bibliothèque de l'utilisateur
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
    header("Location: ../library.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];

try {
    // Vérifier si le livre existe
    $stmt = $conn->prepare("SELECT id FROM books WHERE id = :book_id");
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error_message'] = "Le livre spécifié n'existe pas.";
        header("Location: ../library.php");
        exit();
    }
    
    // Ajouter le livre à la bibliothèque de l'utilisateur si ce n'est pas déjà fait
    $stmt = $conn->prepare("INSERT IGNORE INTO user_library (user_id, book_id, last_page_read, added_at) 
                          VALUES (:user_id, :book_id, 1, NOW())");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Message de succès et redirection
    $_SESSION['success_message'] = "Le livre a été ajouté à votre bibliothèque avec succès.";
    header("Location: ../book.php?id=" . $book_id);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Une erreur est survenue lors de l'ajout du livre à votre bibliothèque: " . $e->getMessage();
    header("Location: ../book.php?id=" . $book_id);
    exit();
}
?>