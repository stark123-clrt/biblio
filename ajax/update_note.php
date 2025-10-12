<?php
// ajax/update_note.php - Mise à jour d'une note
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si les données requises sont présentes
if (!isset($_POST['note_id']) || !isset($_POST['note_text'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$user_id = $_SESSION['user_id'];
$note_id = intval($_POST['note_id']);
$note_text = trim($_POST['note_text']);

// Validation de base
if ($note_id <= 0 || empty($note_text)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

require_once "../classes/Core.php";
$conn = getDatabase();


try {
    // Vérifier si la note existe et appartient à l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = :note_id AND user_id = :user_id");
    $stmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Note non trouvée ou non autorisée']);
        exit;
    }
    
    // Mettre à jour la note
    $stmt = $conn->prepare("UPDATE notes SET note_text = :note_text, updated_at = NOW() WHERE id = :note_id");
    $stmt->bindParam(':note_text', $note_text);
    $stmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note mise à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la note']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>