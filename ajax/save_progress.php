<?php
session_start();


require_once "../classes/Core.php";
$conn = getDatabase();


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_POST['book_id']) || !isset($_POST['page_number'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$book_id = (int)$_POST['book_id'];
$page_number = (int)$_POST['page_number'];
$user_id = $_SESSION['user_id'];

try {
    // Mettre à jour la dernière page lue
    $stmt = $conn->prepare("UPDATE user_library 
                           SET last_page_read = :page_number, last_read_at = NOW() 
                           WHERE user_id = :user_id AND book_id = :book_id");
    $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Enregistrer dans l'historique (toujours, même si la page existe déjà)
    // On limite à 1 entrée par page toutes les 5 minutes pour éviter le spam
    $stmt = $conn->prepare("SELECT id, timestamp FROM reading_history 
                           WHERE user_id = :user_id 
                           AND book_id = :book_id 
                           AND page_number = :page_number 
                           AND action = 'continued'
                           ORDER BY timestamp DESC 
                           LIMIT 1");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
    $stmt->execute();
    
    $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
    $shouldInsert = false;
    
    if (!$lastEntry) {
        // Aucune entrée pour cette page, on l'ajoute
        $shouldInsert = true;
    } else {
        // Vérifier si la dernière entrée date de plus de 5 minutes
        $lastTime = strtotime($lastEntry['timestamp']);
        $now = time();
        $minutesSinceLastEntry = ($now - $lastTime) / 60;
        
        if ($minutesSinceLastEntry >= 5) {
            $shouldInsert = true;
        }
    }
    
    if ($shouldInsert) {
        $stmt = $conn->prepare("INSERT INTO reading_history (user_id, book_id, action, page_number) 
                               VALUES (:user_id, :book_id, 'continued', :page_number)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}