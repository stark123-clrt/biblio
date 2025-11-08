<?php
session_start();

require_once "../classes/Core.php";
$conn = getDatabase();


header('Content-Type: application/json');

// Désactiver la sortie de messages d'erreur PHP qui ruineraient le JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

// Vérification des paramètres requis
if (empty($input['book_id']) || empty($input['page_number']) || !isset($input['content'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $book_id = (int)$input['book_id'];
    $page_number = (int)$input['page_number'];
    $note_text = trim($input['content']);
    $user_id = $_SESSION['user_id'];
    $note_id = isset($input['note_id']) && !empty($input['note_id']) ? (int)$input['note_id'] : null;

    // Vérifier si une note existe déjà pour cette page
    if ($note_id === null) {
        $check = $conn->prepare("SELECT id FROM notes 
                               WHERE user_id = :user_id 
                               AND book_id = :book_id 
                               AND page_number = :page_number");
        $check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $check->bindParam(':page_number', $page_number, PDO::PARAM_INT);
        $check->execute();
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $note_id = $existing['id'];
        }
    }
    

    if ($note_id) {
        // Mise à jour d'une note existante
        $stmt = $conn->prepare("UPDATE notes 
                              SET note_text = :note_text, updated_at = NOW() 
                              WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':note_text', $note_text);
        $stmt->bindParam(':id', $note_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Note mise à jour avec succès', 
            'note_id' => $note_id
        ]);
    } else {
        // Création d'une nouvelle note
        $stmt = $conn->prepare("INSERT INTO notes (user_id, book_id, page_number, note_text) 
                              VALUES (:user_id, :book_id, :page_number, :note_text)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $stmt->bindParam(':page_number', $page_number, PDO::PARAM_INT);
        $stmt->bindParam(':note_text', $note_text);
        $stmt->execute();
        
        $note_id = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Note enregistrée avec succès', 
            'note_id' => $note_id
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
?>