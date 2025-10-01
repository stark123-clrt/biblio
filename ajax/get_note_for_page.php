<?php
session_start();

require_once "../classes/Core.php";
$conn = getDatabase();

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['book_id']) || !isset($_GET['page_number'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $book_id = (int)$_GET['book_id'];
    $page_number = (int)$_GET['page_number'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM notes 
                           WHERE user_id = :user_id 
                           AND book_id = :book_id 
                           AND page_number = :page_number 
                           LIMIT 1");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
    $stmt->execute();
    
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'note' => $note ?: null]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>