<?php
session_start();


require_once "../classes/Core.php";
$conn = getDatabase();


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_GET['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du livre manquant']);
    exit;
}

$book_id = (int)$_GET['book_id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM notes 
                           WHERE user_id = :user_id AND book_id = :book_id 
                           ORDER BY page_number ASC");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'notes' => $notes]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}