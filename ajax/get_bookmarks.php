<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (empty($_GET['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID livre manquant']);
    exit;
}

try {
    $book_id = (int)$_GET['book_id'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM bookmarks 
                          WHERE user_id = :user_id AND book_id = :book_id
                          ORDER BY page_number ASC");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bookmarks' => $bookmarks
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>