<?php
// ajax/remove_from_library.php - Script pour supprimer un livre de la bibliothèque de l'utilisateur
session_start();
require_once "../config/database.php";

// Nettoyage du buffer de sortie pour éviter les caractères invisibles
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier si l'ID du livre est fourni
if (!isset($_POST['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du livre manquant']);
    exit;
}

$book_id = (int)$_POST['book_id'];
$user_id = $_SESSION['user_id'];

try {
    // Commencer une transaction
    $conn->beginTransaction();
    
    // Supprimer d'abord les marque-pages associés
    $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Supprimer les notes associées
    $stmt = $conn->prepare("DELETE FROM notes WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Supprimer le livre de la bibliothèque de l'utilisateur
    $stmt = $conn->prepare("DELETE FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Vérifier si le livre a été supprimé
    if ($stmt->rowCount() > 0) {
        // Valider la transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Livre retiré de votre bibliothèque avec succès']);
    } else {
        // Annuler la transaction
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Livre non trouvé dans votre bibliothèque']);
    }
} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>