<?php
// Assurez-vous qu'il n'y a aucun espace ou saut de ligne avant <?php
session_start();
require_once "../config/database.php";

// Nettoyage complet du tampon de sortie
while (ob_get_level()) {
    ob_end_clean();
}

// Définir l'en-tête de réponse JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (!isset($_POST['bookmark_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du marque-page manquant']);
    exit;
}

$bookmark_id = (int)$_POST['bookmark_id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("DELETE FROM bookmarks 
                            WHERE id = :bookmark_id AND user_id = :user_id");
    $stmt->bindParam(':bookmark_id', $bookmark_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Marque-page supprimé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Marque-page non trouvé ou non autorisé']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
// Assurez-vous qu'il n'y a aucun espace ou saut de ligne après la balise de fermeture
?>