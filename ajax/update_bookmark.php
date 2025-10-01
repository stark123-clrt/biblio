<?php
session_start();

require_once "../classes/Core.php";
$conn = getDatabase();


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_POST['bookmark_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE bookmarks 
                           SET bookmark_name = ?, updated_at = NOW() 
                           WHERE id = ? AND user_id = ?");
    $stmt->execute([
        $_POST['bookmark_name'],
        intval($_POST['bookmark_id']),
        $_SESSION['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Marque-page mis à jour'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
}