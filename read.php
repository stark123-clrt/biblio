<?php
/**
 * read.php - Interface de lecture PDF (Version POO)
 * Refactorisé en POO le 08/11/2025
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";

try {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID du livre non fourni");
    }

    $userRepository = new UserRepository();
    $bookRepository = new BookRepository();
    $readingSessionRepository = new ReadingSessionRepository();

    $user = $userRepository->findById($_SESSION['user_id']);
    $book = $bookRepository->findById(intval($_GET['id']));

    if (!$user || !$book) {
        throw new Exception("Utilisateur ou livre non trouvé");
    }
    
    // Vérifier que le livre a un fichier PDF
    if (empty($book->getFilePath())) {
        throw new Exception("Ce livre n'a pas de fichier PDF associé. Contactez l'administrateur.");
    }

    $pdfManager = PDFManager::getInstance();
    
    if (!$pdfManager->validateAccess($user, $book)) {
        throw new Exception("Accès au fichier PDF non autorisé ou fichier introuvable");
    }

    $book_path = $pdfManager->getFilePath($book);
    $readingSession = $readingSessionRepository->findOrCreateSession($user->getId(), $book->getId());
    $bookmarks = $readingSessionRepository->getBookmarks($user->getId(), $book->getId());

} catch (Exception $e) {
    error_log("Erreur de lecture : " . $e->getMessage());
    
    // Afficher un message d'erreur plus convivial
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Erreur</title>";
    echo "<style>body{font-family:system-ui;padding:20px;text-align:center;}";
    echo ".error{background:#fee;border:1px solid #fcc;padding:20px;border-radius:8px;max-width:500px;margin:50px auto;}";
    echo ".error h2{color:#c33;margin-bottom:10px;}</style></head><body>";
    echo "<div class='error'><h2>❌ Erreur de chargement</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='library.php' style='color:#3b82f6;text-decoration:none;'>← Retour à la bibliothèque</a></p>";
    echo "</div></body></html>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecture - <?= htmlspecialchars($book->getTitle()) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/style/read-new.css">
</head>
<body>
    <div class="reader-container">
        <div class="header-bar">
            <a href="user/my-library.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
            <h1><?= htmlspecialchars($book->getTitle()) ?></h1>
            <div class="header-actions">
                <button id="toggleSidePanel" class="icon-button" title="Menu"><i class="fas fa-bars"></i></button>
                <button id="rateBookBtn" class="icon-button" title="Noter le livre"><i class="fas fa-star"></i></button>
            </div>
        </div>
        <div class="control-bar">
            <div class="page-controls">
                <button id="prevPage" class="control-button"><i class="fas fa-chevron-left"></i></button>
                <span id="pageInfo">Page <?= $readingSession->getCurrentPage() ?></span>
                <button id="nextPage" class="control-button"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="audio-controls">
                <button id="playAudioBtn" class="control-button" title="Lire / Pause"><i class="fas fa-play"></i></button>
                <select id="audioSpeed" class="audio-speed-select" title="Vitesse de lecture">
                    <option value="0.75">0.75x</option>
                    <option value="1.0" selected>1.0x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="1.75">1.75x</option>
                    <option value="2.0">2.0x</option>
                </select>
            </div>
            <div class="zoom-controls">
                <button id="zoomOut" class="control-button"><i class="fas fa-search-minus"></i></button>
                <button id="zoomIn" class="control-button"><i class="fas fa-search-plus"></i></button>
            </div>
        </div>
        <div class="pdf-viewer-container">
            <canvas id="pdfCanvas"></canvas>
            <!-- Indicateur de chargement audio -->
            <div id="audioLoadingIndicator" class="audio-loading" style="display: none;">
                <div class="audio-loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p id="audioLoadingText">Génération de l'audio...</p>
                    <small id="chunkProgress"></small>
                </div>
            </div>
        </div>
        <!-- Lecteur audio caché -->
        <audio id="audioPlayer" style="display: none;" controls></audio>
        
        <!-- Panneau latéral avec onglets -->
        <div id="sidePanel" class="side-panel">
            <div class="side-panel-header">
                <h3><i class="fas fa-bars"></i> Menu</h3>
                <button id="closeSidePanel" class="icon-button"><i class="fas fa-times"></i></button>
            </div>
            
            <!-- Onglets de navigation -->
            <div class="tabs-navigation">
                <button class="tab-button" data-tab="notes">Notes</button>
                <button class="tab-button active" data-tab="signets">Signets</button>
                <button class="tab-button" data-tab="info">Info</button>
            </div>
            
            <!-- Contenu de l'onglet Notes -->
            <div class="tab-content" id="notesTab">
                <div class="tab-add-section">
                    <h4>Ajouter une note</h4>
                    <textarea id="noteInput" class="tab-textarea" placeholder="Écrivez votre note ici..."></textarea>
                    <button id="addNoteFromPanel" class="btn-add">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
                <div class="tab-divider">
                    <h4>Notes récentes</h4>
                </div>
                <div class="notes-list">
                    <!-- Les notes seront chargées dynamiquement -->
                </div>
            </div>
            
            <!-- Contenu de l'onglet Signets -->
            <div class="tab-content active" id="signetsTab">
                <div class="tab-add-section">
                    <h4>Ajouter un signet</h4>
                    <input type="text" id="bookmarkInput" class="tab-input" placeholder="Nom du signet">
                    <button id="addBookmarkFromPanel" class="btn-add">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
                <div class="tab-divider">
                    <h4>Signets enregistrés</h4>
                </div>
                <div class="bookmarks-list">
                    <!-- Les signets seront chargés dynamiquement -->
                </div>
            </div>
            
            <!-- Contenu de l'onglet Info -->
            <div class="tab-content" id="infoTab">
                <div class="book-info">
                    <div class="book-cover-container">
                        <img src="<?= htmlspecialchars($book->getCoverImagePath()) ?>" alt="<?= htmlspecialchars($book->getTitle()) ?>" class="book-cover-image">
                    </div>
                    <h4><?= htmlspecialchars($book->getTitle()) ?></h4>
                    <div class="info-item">
                        <strong>Auteur:</strong> <?= htmlspecialchars($book->getAuthor()) ?>
                    </div>
                    <div class="info-item">
                        <strong>Éditeur:</strong> <?= htmlspecialchars($book->getPublisher() ?? 'Non spécifié') ?>
                    </div>
                    <div class="info-item">
                        <strong>Année:</strong> <?= htmlspecialchars($book->getPublicationYear() ?? 'Non spécifiée') ?>
                    </div>
                    <div class="info-item">
                        <strong>Pages:</strong> <?= htmlspecialchars($book->getPagesCount() ?? 'Non spécifié') ?>
                    </div>
                    <?php if ($book->getDescription()): ?>
                    <div class="info-item description">
                        <strong>Description:</strong>
                        <p><?= nl2br(htmlspecialchars($book->getDescription())) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de notation du livre uniquement -->
    <div id="ratingModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Noter ce livre</h3>
                <button class="close-modal" data-modal="ratingModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="rating-stars">
                    <i class="fas fa-star" data-rating="1"></i>
                    <i class="fas fa-star" data-rating="2"></i>
                    <i class="fas fa-star" data-rating="3"></i>
                    <i class="fas fa-star" data-rating="4"></i>
                    <i class="fas fa-star" data-rating="5"></i>
                </div>
                <p class="rating-text">Cliquez sur les étoiles pour noter</p>
                <div class="comment-section">
                    <label for="ratingComment" class="comment-label">
                        <i class="fas fa-comment"></i> Commentaire (optionnel)
                    </label>
                    <textarea id="ratingComment" class="modal-textarea" placeholder="Partagez votre avis sur ce livre..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button id="submitRatingBtn" class="btn-primary">Enregistrer la note</button>
                <button id="cancelRatingBtn" class="btn-secondary">Annuler</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Variables PHP injectées pour JavaScript
        const pdfPath = '<?= addslashes($book_path) ?>';
        const bookId = <?= $book->getId() ?>;
        const initialPage = <?= $readingSession->getCurrentPage() ?>;
    </script>
    <script src="includes/js/read.js"></script>
</body>
</html>
