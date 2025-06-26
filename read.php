<?php
// read.php - Interface de lecture FINALE optimisée mobile
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Vérifier si l'ID du livre est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: library.php");
    exit();
}

$book_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once "config/database.php";

// Récupérer les informations du livre
$stmt = $conn->prepare("SELECT b.*, c.name as category_name FROM books b 
                        LEFT JOIN book_categories bc ON b.id = bc.book_id 
                        LEFT JOIN categories c ON bc.category_id = c.id 
                        WHERE b.id = :book_id LIMIT 1");
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    echo "<script>alert('Livre introuvable. Redirection vers la bibliothèque.');</script>";
    echo "<script>window.location.href = 'library.php';</script>";
    exit();
}

$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book || !isset($book['file_path']) || empty($book['file_path'])) {
    echo "<script>alert('Fichier du livre introuvable. Contactez l\\'administrateur.');</script>";
    echo "<script>window.location.href = 'library.php';</script>";
    exit();
}

// Corriger le chemin du fichier PDF
$original_path = $book['file_path'];
$book_path = $original_path;

if (strpos($book_path, '../') === 0) {
    $book_path = substr($book_path, 3);
}

if (!file_exists($book_path)) {
    $alternative_paths = [
        'assets/uploads/books/' . basename($original_path),
        '../assets/uploads/books/' . basename($original_path),
        $original_path
    ];
    
    $found = false;
    foreach ($alternative_paths as $alt_path) {
        if (file_exists($alt_path)) {
            $book_path = $alt_path;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "<script>alert('Fichier PDF physiquement introuvable. Contactez l\\'administrateur.');</script>";
        echo "<script>window.location.href = 'library.php';</script>";
        exit();
    }
}

// Récupérer la dernière page lue
$stmt = $conn->prepare("SELECT last_page_read FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();

$last_page = 1;
if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_page = $row['last_page_read'];
} else {
    $stmt = $conn->prepare("INSERT INTO user_library (user_id, book_id, last_page_read, added_at) 
                            VALUES (:user_id, :book_id, 1, NOW())");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
}

// Vérifier si l'utilisateur a déjà noté ce livre
$has_rated = false;
$user_rating = 0;
$stmt = $conn->prepare("SELECT rating FROM comments WHERE user_id = :user_id AND book_id = :book_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $has_rated = true;
    $user_rating = $stmt->fetch(PDO::FETCH_ASSOC)['rating'];
}

// Calculer la note moyenne du livre
$average_rating = 0;
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM comments WHERE book_id = :book_id AND is_validated = 1");
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$average_rating = round($result['avg_rating'] ?? 0, 1);

$page_title = "Lecture - " . htmlspecialchars($book['title']);

// Corriger le chemin de l'image de couverture
$cover_image = $book['cover_image'];
if (strpos($cover_image, '../') === 0) {
    $cover_image = substr($cover_image, 3);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* RESET COMPLET POUR MOBILE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
            height: 100vh;
            height: 100dvh; /* Dynamic viewport height pour mobiles modernes */
            overflow: hidden;
            position: fixed;
            width: 100%;
            background: #1a1a1a;
            font-family: system-ui, -apple-system, sans-serif;
            touch-action: manipulation;
        }

        /* CONTENEUR PRINCIPAL - UTILISE 100% DE L'ESPACE */
        .reader-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: #1a1a1a;
        }

        /* EN-TÊTE ULTRA-COMPACT */
        .header-bar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 2px 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 24px;
            min-height: 24px;
            max-height: 24px;
            flex-shrink: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        .header-bar h1 {
            font-size: 10px;
            font-weight: 500;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
            line-height: 1;
        }

        /* CONTRÔLES ULTRA-COMPACTS */
        .control-bar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(8px);
            padding: 2px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 28px;
            min-height: 28px;
            max-height: 28px;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            z-index: 90;
        }

        /* ZONE PDF - UTILISE TOUT L'ESPACE RESTANT */
        .pdf-viewer-container {
            flex: 1;
            position: relative;
            overflow: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #1a1a1a;
            width: 100vw;
            height: calc(100vh - 52px); /* Total - header - controls */
            height: calc(100dvh - 52px);
            padding: 0;
            margin: 0;
            -webkit-overflow-scrolling: touch;
        }

        #pdfCanvas {
            background: white;
            display: block;
            margin: 0 auto;
            max-width: 100vw;
            height: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        /* BOUTONS OPTIMISÉS */
        .btn {
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 500;
            font-size: 8px;
            transition: all 0.15s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 1px;
            border: none;
            min-height: 20px;
            white-space: nowrap;
            touch-action: manipulation;
            line-height: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 1px 2px rgba(59,130,246,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            box-shadow: 0 1px 2px rgba(107,114,128,0.3);
        }

        .btn span {
            display: none; /* Masquer texte sur mobile */
        }

        .btn i {
            font-size: 10px;
        }

        /* CONTRÔLES GROUPÉS */
        .control-group {
            display: flex;
            align-items: center;
            gap: 1px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            padding: 1px;
        }

        .page-input {
            width: 24px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 8px;
            color: #374151;
            font-weight: 500;
            padding: 0;
            margin: 0;
            line-height: 1;
        }

        .zoom-display {
            font-size: 7px;
            color: #6b7280;
            min-width: 24px;
            text-align: center;
            font-weight: 500;
            line-height: 1;
        }

        /* PANNEAU LATÉRAL */
        .side-panel {
            position: fixed;
            top: 0;
            right: -100vw;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            transition: right 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 200;
            display: flex;
            flex-direction: column;
        }

        .side-panel.open {
            right: 0;
        }

        /* LOADING SPINNER */
        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
        }

        .spinner {
            width: 24px;
            height: 24px;
            border: 2px solid rgba(59,130,246,0.3);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 0.8s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* MODAL MASQUÉ */
        #ratingModal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 300;
            padding: 12px;
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        #ratingModal:not(.hidden) {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            align-items: center;
            justify-content: center;
        }

        #ratingModal > div {
            background: white;
            border-radius: 8px;
            padding: 16px;
            max-width: calc(100vw - 24px);
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .star-rating {
            display: flex;
            gap: 4px;
            font-size: 18px;
            justify-content: center;
        }

        .star {
            cursor: pointer;
            color: #d1d5db;
            transition: color 0.15s ease;
        }

        .star:hover,
        .star.active {
            color: #fbbf24;
        }

        /* ZONES TACTILES POUR NAVIGATION */
        .touch-area {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            z-index: 5;
            pointer-events: auto;
        }

        .touch-left, .touch-right {
            flex: 0 0 20%;
            cursor: pointer;
            user-select: none;
        }

        .touch-center {
            flex: 1;
            pointer-events: none;
        }

        /* CLASSES UTILITAIRES */
        .hidden {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        /* DESKTOP - GARDE L'ANCIEN STYLE */
        @media (min-width: 769px) {
            html, body {
                position: static;
                height: auto;
                overflow: auto;
            }

            .reader-container {
                position: static;
                height: 100vh;
            }

            .header-bar {
                height: 40px;
                min-height: 40px;
                max-height: 40px;
                padding: 8px 16px;
            }

            .header-bar h1 {
                font-size: 16px;
                max-width: none;
            }

            .control-bar {
                height: 44px;
                min-height: 44px;
                max-height: 44px;
                padding: 8px 16px;
            }

            .pdf-viewer-container {
                height: calc(100vh - 84px);
                padding: 16px;
            }

            .btn {
                padding: 8px 12px;
                font-size: 12px;
                min-height: 32px;
                gap: 4px;
            }

            .btn span {
                display: inline;
            }

            .btn i {
                font-size: 12px;
            }

            .touch-area {
                display: none;
            }

            .side-panel {
                width: 400px;
                right: -400px;
            }
        }

        /* ULTRA-PETITS ÉCRANS */
        @media (max-width: 375px) {
            .header-bar {
                height: 20px;
                min-height: 20px;
                max-height: 20px;
                padding: 1px 2px;
            }

            .header-bar h1 {
                font-size: 8px;
                max-width: 80px;
            }

            .control-bar {
                height: 24px;
                min-height: 24px;
                max-height: 24px;
                padding: 1px;
            }

            .pdf-viewer-container {
                height: calc(100vh - 44px);
                height: calc(100dvh - 44px);
            }

            .btn {
                padding: 1px 2px;
                font-size: 7px;
                min-height: 16px;
            }

            .btn i {
                font-size: 8px;
            }

            .page-input {
                width: 20px;
                font-size: 7px;
            }

            .zoom-display {
                font-size: 6px;
                min-width: 20px;
            }
        }


        /* UNIQUEMENT pour mobile */
        @media (max-width: 768px) {
             #pdfCanvas {
           transform: scaleX(1.2);
            }
        }

        /* SUPPRESSION DU BOUNCE iOS */
        body {
            overscroll-behavior: none;
            -webkit-overflow-scrolling: touch;
        }

        .pdf-viewer-container {
            overscroll-behavior: contain;
        }

        /* OPTIMISATIONS DE PERFORMANCE */
        .pdf-viewer-container,
        #pdfCanvas,
        .side-panel {
            will-change: transform;
        }
    </style>
</head>
<body>
    <div class="reader-container">
        <!-- En-tête ultra-compact -->
        <div class="header-bar">
            <div class="flex items-center gap-1">
                <a href="user/my-library.php" class="text-white hover:opacity-80">
                    <i class="fas fa-arrow-left text-xs"></i>
                </a>
                <h1><?php echo htmlspecialchars($book['title']); ?></h1>
            </div>
            
            <div class="flex items-center gap-1">
                <div class="flex items-center gap-1 text-xs">
                    <i class="fas fa-star text-yellow-400 text-xs"></i>
                    <span style="font-size: 8px;"><?php echo $average_rating; ?></span>
                </div>
                
                <?php if (!$has_rated): ?>
                <button id="rateBtn" class="btn btn-primary">
                    <i class="fas fa-star"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Barre de contrôle ultra-compacte -->
        <div class="control-bar">
            <div class="control-group">
                <button id="prevBtn" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <div class="flex items-center gap-1 px-1">
                    <input type="number" id="pageInput" value="<?php echo $last_page; ?>" 
                           class="page-input" min="1">
                    <span style="font-size: 7px; color: #666;">/</span>
                    <span id="totalPages" style="font-size: 7px; color: #666;">...</span>
                </div>
                
                <button id="nextBtn" class="btn btn-secondary">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="control-group">
                <button id="zoomOutBtn" class="btn btn-secondary">
                    <i class="fas fa-search-minus"></i>
                </button>
                
                <span id="zoomLevel" class="zoom-display">300%</span>
                
                <button id="zoomInBtn" class="btn btn-secondary">
                    <i class="fas fa-search-plus"></i>
                </button>
            </div>
            
            <div class="control-group">
                <button id="notesBtn" class="btn btn-primary">
                    <i class="fas fa-sticky-note"></i>
                </button>
                
                <button id="bookmarkBtn" class="btn btn-primary">
                    <i class="fas fa-bookmark"></i>
                </button>
            </div>
        </div>
        
        <!-- Zone PDF - UTILISE TOUT L'ESPACE RESTANT -->
        <div class="pdf-viewer-container" id="pdfContainer">
            <div class="loading-spinner" id="loadingSpinner">
                <div class="spinner"></div>
            </div>
            
            <canvas id="pdfCanvas"></canvas>
            
            <!-- Zones tactiles pour navigation mobile -->
            <div class="touch-area" id="touchArea">
                <div class="touch-left" id="touchLeft"></div>
                <div class="touch-center" id="touchCenter"></div>
                <div class="touch-right" id="touchRight"></div>
            </div>
        </div>
        
        <!-- Panneau latéral -->
        <div class="side-panel" id="sidePanel">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 flex justify-between items-center">
                <h2 class="text-lg font-bold" id="panelTitle">Notes</h2>
                <button id="closePanelBtn" class="text-2xl hover:opacity-80">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="flex border-b bg-white">
                <button class="tab-btn flex-1 py-3 text-center font-medium border-b-2 border-blue-600 text-blue-600" 
                        data-tab="notes">Notes</button>
                <button class="tab-btn flex-1 py-3 text-center font-medium text-gray-600" 
                        data-tab="bookmarks">Signets</button>
                <button class="tab-btn flex-1 py-3 text-center font-medium text-gray-600" 
                        data-tab="info">Info</button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Onglet Notes -->
                <div id="notesTab" class="tab-content">
                    <div class="mb-4">
                        <label class="block font-medium mb-2 text-sm">Note pour la page <span id="currentPageNote"><?php echo $last_page; ?></span></label>
                        <textarea id="noteText" rows="3" class="w-full p-3 border rounded-lg resize-none focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
                        <button id="saveNoteBtn" class="btn btn-primary w-full mt-2">
                            <i class="fas fa-save"></i>
                            Enregistrer
                        </button>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h3 class="font-bold mb-3 text-sm">Notes précédentes</h3>
                        <div id="notesList" class="space-y-2">
                            <p class="text-gray-500 text-center text-sm">Chargement...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Signets -->
                <div id="bookmarksTab" class="tab-content hidden">
                    <div class="mb-4">
                        <label class="block font-medium mb-2 text-sm">Ajouter un signet</label>
                        <input type="text" id="bookmarkName" placeholder="Nom du signet" 
                               class="w-full p-3 border rounded-lg mb-2 text-sm">
                        <button id="saveBookmarkBtn" class="btn btn-primary w-full">
                            <i class="fas fa-plus"></i>
                            Ajouter
                        </button>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h3 class="font-bold mb-3 text-sm">Signets enregistrés</h3>
                        <div id="bookmarksList" class="space-y-2">
                            <p class="text-gray-500 text-center text-sm">Chargement...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Informations -->
                <div id="infoTab" class="tab-content hidden">
                    <div class="text-center mb-4">
                        <img src="<?php echo htmlspecialchars($cover_image); ?>" 
                             alt="Couverture" class="w-24 h-auto mx-auto rounded shadow-lg mb-4">
                        <h3 class="text-lg font-bold"><?php echo htmlspecialchars($book['title']); ?></h3>
                    </div>
                    
                    <div class="space-y-2 text-sm">
                        <p><strong>Auteur:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Inconnu'); ?></p>
                        <p><strong>Éditeur:</strong> <?php echo htmlspecialchars($book['publisher'] ?? 'Inconnu'); ?></p>
                        <p><strong>Année:</strong> <?php echo htmlspecialchars($book['publication_year'] ?? 'Inconnue'); ?></p>
                        <p><strong>Pages:</strong> <?php echo htmlspecialchars($book['pages_count'] ?? 'Inconnu'); ?></p>
                        <div>
                            <strong>Description:</strong>
                            <p class="mt-2 text-gray-700 text-xs leading-relaxed"><?php echo nl2br(htmlspecialchars($book['description'] ?? 'Aucune description disponible.')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de notation -->
    <div id="ratingModal" class="hidden">
        <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Noter ce livre</h3>
                <button id="closeRatingModal" class="text-gray-500 hover:text-gray-700 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <p class="mb-4 text-center text-sm">Quelle note donnez-vous à ce livre ?</p>
            
            <div class="star-rating mb-6" id="starRating">
                <i class="fas fa-star star" data-rating="1"></i>
                <i class="fas fa-star star" data-rating="2"></i>
                <i class="fas fa-star star" data-rating="3"></i>
                <i class="fas fa-star star" data-rating="4"></i>
                <i class="fas fa-star star" data-rating="5"></i>
            </div>
            
            <div class="mb-4">
                <label class="block font-medium mb-2 text-sm">Commentaire (optionnel)</label>
                <textarea id="ratingComment" rows="3" class="w-full p-3 border rounded-lg resize-none text-sm"></textarea>
            </div>
            
            <div class="flex gap-2">
                <button id="cancelRatingBtn" class="btn btn-secondary flex-1" style="padding: 8px; font-size: 12px;">Annuler</button>
                <button id="submitRatingBtn" class="btn btn-primary flex-1" style="padding: 8px; font-size: 12px;">Envoyer</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Configuration PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        // Variables globales
        let pdfDoc = null;
        let pageNum = <?php echo $last_page; ?>;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.0; // 300% par défaut sur mobile
        let selectedRating = 0;
        let currentNote = null;
        
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');
        const bookId = <?php echo $book_id; ?>;
        const userId = <?php echo $user_id; ?>;
        
        // Détection mobile
        function isMobile() {
            return window.innerWidth <= 768;
        }

        // Chargement du PDF
        function loadPDF() {
            document.getElementById('loadingSpinner').style.display = 'block';
            
            pdfjsLib.getDocument('<?php echo addslashes($book_path); ?>').promise.then(function(pdf) {
                pdfDoc = pdf;
                document.getElementById('totalPages').textContent = pdf.numPages;
                
                // Ajuster le zoom selon l'écran
            if (!isMobile()) {
                 scale = 1.5; // 150% sur desktop
                 document.getElementById('zoomLevel').textContent = '150%';
              }

                document.getElementById('pageInput').max = pdf.numPages;
                
                renderPage(pageNum);
            }).catch(function(error) {
                console.error('Erreur lors du chargement du PDF:', error);
                alert('Erreur lors du chargement du livre.');
            });
        }
        
        // Rendu optimisé d'une page
        function renderPage(num) {
            pageRendering = true;
            document.getElementById('loadingSpinner').style.display = 'block';
            
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: scale });
                
                // Configuration canvas haute résolution
                const outputScale = window.devicePixelRatio || 1;
                canvas.width = Math.floor(viewport.width * outputScale);
                canvas.height = Math.floor(viewport.height * outputScale);
                canvas.style.width = Math.floor(viewport.width) + "px";
                canvas.style.height = Math.floor(viewport.height) + "px";
                
                const transform = outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null;
                
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport,
                    transform: transform
                };
                
                page.render(renderContext).promise.then(function() {
                    pageRendering = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                    
                    // Mettre à jour l'interface
                    document.getElementById('pageInput').value = num;
                    document.getElementById('currentPageNote').textContent = num;
                    
                    // Sauvegarder la progression
                    saveProgress(num);
                    loadNoteForPage(num);
                    
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
        }
        
        // Navigation
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }
        
        function onPrevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }
        
        function onNextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }
        
        // Zoom
        function zoomIn() {
            scale = Math.min(scale + 0.25, isMobile() ? 5.0 : 3.0);
            document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
            queueRenderPage(pageNum);
        }
        
        function zoomOut() {
            scale = Math.max(scale - 0.25, isMobile() ? 1.0 : 0.5);
            document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
            queueRenderPage(pageNum);
        }
        
        // Gestion du panneau latéral
        function openSidePanel() {
            document.getElementById('sidePanel').classList.add('open');
        }
        
        function closeSidePanel() {
            document.getElementById('sidePanel').classList.remove('open');
        }
        
        // Changement d'onglet
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-600', 'text-blue-600');
                btn.classList.add('text-gray-600');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
            activeBtn.classList.add('border-blue-600', 'text-blue-600');
            activeBtn.classList.remove('text-gray-600');
            
            document.getElementById(tabName + 'Tab').classList.remove('hidden');
            
            const titles = {
                'notes': 'Notes',
                'bookmarks': 'Signets',
                'info': 'Informations'
            };
            document.getElementById('panelTitle').textContent = titles[tabName] || 'Notes';
        }
        
        // Gestion de la notation
        function showRatingModal() {
            document.getElementById('ratingModal').classList.remove('hidden');
        }
        
        function hideRatingModal() {
            document.getElementById('ratingModal').classList.add('hidden');
            selectedRating = 0;
            updateStarDisplay();
            document.getElementById('ratingComment').value = '';
        }
        
        function updateStarDisplay() {
            document.querySelectorAll('.star').forEach((star, index) => {
                if (index < selectedRating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
        
        function submitRating() {
            if (selectedRating === 0) {
                alert('Veuillez sélectionner une note');
                return;
            }
            
            const comment = document.getElementById('ratingComment').value.trim();
            
            $.ajax({
                url: 'ajax/save_rating.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    rating: selectedRating,
                    comment_text: comment
                },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            showAlert('success', 'Merci pour votre notation !');
                            hideRatingModal();
                            setTimeout(() => location.reload(), 1500);
                        }
                    } catch (e) {
                        console.error('Erreur:', e);
                    }
                }
            });
        }
        
        // Sauvegarder la progression
        function saveProgress(pageNumber) {
            $.ajax({
                url: 'ajax/save_progress.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    page_number: pageNumber
                }
            });
        }
        
        // Charger les notes
        function loadNotes() {
            $.ajax({
                url: 'ajax/get_notes.php',
                type: 'GET',
                data: { book_id: bookId },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        const notesList = document.getElementById('notesList');
                        
                        if (data.success && data.notes && data.notes.length > 0) {
                            notesList.innerHTML = data.notes.map(note => `
                                <div class="p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100" 
                                     onclick="goToPage(${note.page_number})">
                                    <div class="flex justify-between mb-1">
                                        <span class="font-medium text-blue-600 text-sm">Page ${note.page_number}</span>
                                        <span class="text-xs text-gray-500">${formatDate(note.created_at)}</span>
                                    </div>
                                    <p class="text-gray-700 text-xs">${note.note_text}</p>
                                </div>
                            `).join('');
                        } else {
                            notesList.innerHTML = '<p class="text-gray-500 text-center text-sm">Aucune note</p>';
                        }
                    } catch (e) {
                        console.error('Erreur parsing notes:', e);
                    }
                }
            });
        }
        
        function loadNoteForPage(pageNumber) {
            document.getElementById('noteText').value = '';
            currentNote = null;
            
            $.ajax({
                url: 'ajax/get_note_for_page.php',
                type: 'GET',
                data: {
                    book_id: bookId,
                    page_number: pageNumber
                },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success && data.note) {
                            document.getElementById('noteText').value = data.note.note_text;
                            currentNote = data.note;
                        }
                    } catch (e) {
                        console.error('Erreur parsing note:', e);
                    }
                }
            });
        }
        
        function saveNote() {
            const noteText = document.getElementById('noteText').value.trim();
            if (!noteText) {
                alert('Veuillez écrire une note avant de l\'enregistrer.');
                return;
            }
            
            $.ajax({
                url: 'ajax/save_note.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    page_number: pageNum,
                    note_text: noteText,
                    note_id: currentNote ? currentNote.id : null
                },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            showAlert('success', 'Note enregistrée !');
                            loadNotes();
                        }
                    } catch (e) {
                        console.error('Erreur:', e);
                    }
                }
            });
        }
        
        // Charger les signets
        function loadBookmarks() {
            $.ajax({
                url: 'ajax/get_bookmarks.php',
                type: 'GET',
                data: { book_id: bookId },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        const bookmarksList = document.getElementById('bookmarksList');
                        
                        if (data.success && data.bookmarks && data.bookmarks.length > 0) {
                            bookmarksList.innerHTML = data.bookmarks.map(bookmark => `
                                <div class="p-3 bg-gray-50 rounded-lg flex justify-between items-center">
                                    <div class="cursor-pointer hover:text-blue-600" onclick="goToPage(${bookmark.page_number})">
                                        <div class="font-medium text-sm">${bookmark.bookmark_name || 'Sans nom'}</div>
                                        <div class="text-xs text-gray-500">Page ${bookmark.page_number}</div>
                                    </div>
                                    <button onclick="deleteBookmark(${bookmark.id})" 
                                            class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            `).join('');
                        } else {
                            bookmarksList.innerHTML = '<p class="text-gray-500 text-center text-sm">Aucun signet</p>';
                        }
                    } catch (e) {
                        console.error('Erreur parsing bookmarks:', e);
                    }
                }
            });
        }
        
        function saveBookmark() {
            const bookmarkName = document.getElementById('bookmarkName').value.trim();
            
            $.ajax({
                url: 'ajax/save_bookmark.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    page_number: pageNum,
                    bookmark_name: bookmarkName
                },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            showAlert('success', 'Signet ajouté !');
                            document.getElementById('bookmarkName').value = '';
                            loadBookmarks();
                        }
                    } catch (e) {
                        console.error('Erreur:', e);
                    }
                }
            });
        }
        
        function deleteBookmark(bookmarkId) {
            if (!confirm('Supprimer ce signet ?')) return;
            
            $.ajax({
                url: 'ajax/delete_bookmark.php',
                type: 'POST',
                data: { bookmark_id: bookmarkId },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            showAlert('success', 'Signet supprimé');
                            loadBookmarks();
                        }
                    } catch (e) {
                        console.error('Erreur:', e);
                    }
                }
            });
        }
        
        function goToPage(page) {
            pageNum = parseInt(page);
            queueRenderPage(pageNum);
            closeSidePanel();
        }
        
        function showAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
            alert.innerHTML = `<div class="flex items-center gap-2">
                <i class="fas fa-${type === 'success' ? 'check' : 'times'}-circle"></i>
                <span class="text-sm">${message}</span>
            </div>`;
            
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 3000);
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Navigation tactile
        function setupTouchNavigation() {
            if (!isMobile()) return;
            
            let touchStartX = 0;
            let touchStartY = 0;
            let touchEndX = 0;
            let touchEndY = 0;
            let lastTap = 0;
            
            const touchArea = document.getElementById('touchArea');
            
            touchArea.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });
            
            touchArea.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                touchEndY = e.changedTouches[0].screenY;
                
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                
                // Double tap pour zoom
                // if (tapLength < 500 && tapLength > 0) {
                //     if (scale > 3.0) {
                //         scale = 2.0;
                //     } else {
                //         scale = 4.0;
                //     }
                //     document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
                //     queueRenderPage(pageNum);
                //     return;
                // }
                
                lastTap = currentTime;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const xDiff = touchStartX - touchEndX;
                const yDiff = touchStartY - touchEndY;
                const threshold = 50;
                
                if (Math.abs(xDiff) > Math.abs(yDiff) && Math.abs(xDiff) > threshold) {
                    if (xDiff > 0) {
                        onNextPage();
                    } else {
                        onPrevPage();
                    }
                }
            }
            
            document.getElementById('touchLeft').addEventListener('click', onPrevPage);
            document.getElementById('touchRight').addEventListener('click', onNextPage);
        }
        
        // INITIALISATION
        document.addEventListener('DOMContentLoaded', function() {
            // Force le masquage du modal
            const ratingModal = document.getElementById('ratingModal');
            if (ratingModal) {
                ratingModal.classList.add('hidden');
                ratingModal.style.display = 'none';
            }
            
            // Charger le PDF
            loadPDF();
            
            // Configuration tactile
            setupTouchNavigation();
            
            // Navigation
            document.getElementById('prevBtn').addEventListener('click', onPrevPage);
            document.getElementById('nextBtn').addEventListener('click', onNextPage);
            
            document.getElementById('pageInput').addEventListener('change', function() {
                const page = parseInt(this.value);
                if (page >= 1 && page <= pdfDoc.numPages) {
                    pageNum = page;
                    queueRenderPage(pageNum);
                }
            });
            
            // Zoom
            document.getElementById('zoomInBtn').addEventListener('click', zoomIn);
            document.getElementById('zoomOutBtn').addEventListener('click', zoomOut);
            
            // Panneau latéral
            document.getElementById('notesBtn').addEventListener('click', function() {
                switchTab('notes');
                openSidePanel();
            });
            
            document.getElementById('bookmarkBtn').addEventListener('click', function() {
                switchTab('bookmarks');
                openSidePanel();
            });
            
            document.getElementById('closePanelBtn').addEventListener('click', closeSidePanel);
            
            // Onglets
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this.dataset.tab);
                });
            });
            
            // Notes et signets
            document.getElementById('saveNoteBtn').addEventListener('click', saveNote);
            document.getElementById('saveBookmarkBtn').addEventListener('click', saveBookmark);
            
            // Notation
            <?php if (!$has_rated): ?>
            document.getElementById('rateBtn').addEventListener('click', function(e) {
                e.preventDefault();
                showRatingModal();
            });
            document.getElementById('closeRatingModal').addEventListener('click', hideRatingModal);
            document.getElementById('cancelRatingBtn').addEventListener('click', hideRatingModal);
            document.getElementById('submitRatingBtn').addEventListener('click', submitRating);
            
            // Fermer modal sur arrière-plan
            document.getElementById('ratingModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideRatingModal();
                }
            });
            
            // Étoiles
            document.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', function() {
                    selectedRating = parseInt(this.dataset.rating);
                    updateStarDisplay();
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.dataset.rating);
                    document.querySelectorAll('.star').forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
            
            document.getElementById('starRating').addEventListener('mouseleave', updateStarDisplay);
            <?php endif; ?>
            
            // Charger données
            loadNotes();
            loadBookmarks();
            
            // Navigation clavier
            document.addEventListener('keydown', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                
                switch(e.key) {
                    case 'ArrowLeft':
                    case 'PageUp':
                        onPrevPage();
                        break;
                    case 'ArrowRight':
                    case 'PageDown':
                    case ' ':
                        e.preventDefault();
                        onNextPage();
                        break;
                    case 'Home':
                        pageNum = 1;
                        queueRenderPage(pageNum);
                        break;
                    case 'End':
                        pageNum = pdfDoc.numPages;
                        queueRenderPage(pageNum);
                        break;
                    case 'Escape':
                        if (document.getElementById('sidePanel').classList.contains('open')) {
                            closeSidePanel();
                        }
                        if (!document.getElementById('ratingModal').classList.contains('hidden')) {
                            hideRatingModal();
                        }
                        break;
                }
            });
            
            // Redimensionnement
            window.addEventListener('resize', function() {
                if (pdfDoc) {
                    queueRenderPage(pageNum);
                }
            });
            
            // Gestion orientation mobile
            window.addEventListener('orientationchange', function() {
                setTimeout(() => {
                    if (pdfDoc) {
                        queueRenderPage(pageNum);
                    }
                }, 100);
            });
        });
    </script>
</body>
</html>