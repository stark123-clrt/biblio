<?php
session_start();
require_once "../config/database.php";

// Définir l'en-tête pour indiquer que la réponse est du JSON
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs PHP qui ruineraient le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les paramètres requis
if (empty($_POST['book_id']) || empty($_POST['page_number'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $book_id = (int)$_POST['book_id'];
    $page_number = (int)$_POST['page_number'];
    $bookmark_name = !empty($_POST['bookmark_name']) ? trim($_POST['bookmark_name']) : "Page " . $page_number;
    $user_id = $_SESSION['user_id'];

    // Vérifier si un marque-page existe déjà pour cette page
    $stmt = $conn->prepare("SELECT id FROM bookmarks 
                           WHERE user_id = :user_id 
                           AND book_id = :book_id 
                           AND page_number = :page_number");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Un marque-page existe déjà, mettre à jour son nom
        $bookmark_id = $stmt->fetchColumn();
        $stmt = $conn->prepare("UPDATE bookmarks 
                               SET bookmark_name = :bookmark_name 
                               WHERE id = :id");
        $stmt->bindParam(':bookmark_name', $bookmark_name);
        $stmt->bindParam(':id', $bookmark_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Marque-page mis à jour', 
            'bookmark_id' => $bookmark_id
        ]);
    } else {
        // Créer un nouveau marque-page
        $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, book_id, page_number, bookmark_name) 
                               VALUES (:user_id, :book_id, :page_number, :bookmark_name)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
        $stmt->bindParam(':bookmark_name', $bookmark_name);
        $stmt->execute();
        
        $bookmark_id = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Marque-page créé avec succès', 
            'bookmark_id' => $bookmark_id
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
// Assurez-vous qu'il n'y a pas de code après cette ligne qui pourrait générer une sortie
?>