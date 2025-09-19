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

require_once "classes/Core.php";
require_once "classes/Models.php";
require_once "classes/Repositories.php";
$conn = getDatabase();

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

                <button id="audioToggleBtn" class="btn btn-primary" title="Lecture audio">
                    <i class="fas fa-volume-up"></i>
                </button>

                <div id="audioStatus" style="display: none; font-size: 8px; color: #666; margin-left: 2px;">
                   <span id="audioProgress"></span>
                </div>
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





// 🚀 CODE JAVASCRIPT COMPLET - read.php avec extraction ultra-simple

// Configuration PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// Variables globales
let pdfDoc = null;
let pageNum = <?php echo $last_page; ?>;
let pageRendering = false;
let pageNumPending = null;
let scale = 1.0;
let selectedRating = 0;
let currentNote = null;

const canvas = document.getElementById('pdfCanvas');
const ctx = canvas.getContext('2d');
const bookId = <?php echo $book_id; ?>;
const userId = <?php echo $user_id; ?>;

let lastKnownPage = pageNum;

// Détection mobile
function isMobile() {
    return window.innerWidth <= 768;
}

// 🎵 AUDIOREADЕР AVEC EXTRACTION ULTRA-SIMPLE
class AudioReader {
    constructor() {
        this.isReading = false;
        this.isPaused = false;
        this.currentAudio = null;
        this.textChunks = [];
        this.currentChunkIndex = 0;
        this.preloadedAudios = new Map();
        this.readingSpeed = 1.0;
        
        this.chunkSize = 1000;
        this.maxWordsPerChunk = 500; 
        this.minChunkLength = 50; 
        this.preloadCount = 5;
        
        this.currentPageNumber = null;
        this.audioCache = new Map();
        this.isProcessing = false;
        this.pageWatcher = null;
        
        // Système de continuité
        this.nextPageChunks = [];
        this.nextPagePreloaded = new Map();
        this.continuousMode = true;
        this.anticipationThreshold = 2; // 🔥 PLUS TÔT
        this.nextPageText = '';
        this.isPreloadingNextPage = false;
        this.isAutoNavigating = false;
        console.log('🎵 AudioReader ULTRA-SIMPLE initialisé');
    }

    setupPageChangeDetection() {
        this.currentPageNumber = pageNum;
        
        this.pageWatcher = setInterval(() => {
            if (typeof pageNum !== 'undefined' && 
                pageNum !== this.currentPageNumber && 
                !this.isProcessing && 
                !this.continuousMode) {
                
                console.log('📄 Changement de page manuel détecté:', this.currentPageNumber, '->', pageNum);
                this.handlePageChange();
                this.currentPageNumber = pageNum;
            }
        }, 300);
    }
    
    handlePageChange() {
        if (this.isReading && !this.isProcessing && !this.continuousMode) {
            console.log('🛑 Arrêt lecture - changement de page manuel');
            this.stopReading(false);
        }
    }

    // 🔥 EXTRACTION ULTRA-SIMPLE - TOUT LE TEXTE BRUT
    async extractTextFromPage(pageNumber) {
        try {
            if (!pdfDoc) {
                throw new Error('PDF non chargé');
            }

            console.log('📄 Extraction BRUTE page', pageNumber);
            const page = await pdfDoc.getPage(pageNumber);
            const textContent = await page.getTextContent();
            
            // 🔥 ULTRA-SIMPLE : Tout récupérer, point final
            let allText = '';
            textContent.items.forEach(item => {
                if (item.str) {  // Si y'a du texte, on prend
                    allText += item.str + ' ';  // Juste ajouter avec espace
                }
            });

            console.log(`✅ Page ${pageNumber} - BRUT: ${allText.length} caractères`);
            
            return allText.trim();  // Juste enlever espaces début/fin
            
        } catch (error) {
            console.error('❌ Erreur extraction page', pageNumber, ':', error);
            return '';
        }
    }

    // 🔥 EXTRACTION ANTICIPÉE BRUTALE
    async extractTextFromNextPage(currentPage) {
        if (currentPage >= pdfDoc.numPages) {
            return '';
        }
        
        const nextPage = currentPage + 1;
        console.log(`📄 Extraction BRUTE anticipée page ${nextPage}`);
        
        try {
            return await this.extractTextFromPage(nextPage);
        } catch (error) {
            console.error(`❌ Erreur extraction anticipée page ${nextPage}:`, error);
            return '';
        }
    }

    async extractTextFromCurrentPage() {
        this.currentPageNumber = pageNum;
        await this.waitForPageRender(pageNum);
        return this.extractTextFromPage(pageNum);
    }

    // 🔥 NETTOYAGE MINIMAL
    cleanText(text) {
        // JUSTE le minimum : enlever espaces multiples
        return text.replace(/\s+/g, ' ').trim();
    }

   

    // REMPLACE ta fonction splitTextIntoChunks actuelle par celle-ci :
splitTextIntoChunks(text, maxLength = null) {
    if (!text || text.trim().length === 0) return [''];
    
    const maxSize = 1000; // Garde ta taille actuelle
    text = text.trim();
    
    if (text.length <= maxSize) {
        return [text]; // Si petit, garde tout
    }
    
    // 🧠 DÉCOUPAGE INTELLIGENT SUR PONCTUATION
    const chunks = [];
    let currentChunk = '';
    
    // Découper d'abord par phrases
    const sentences = text.split(/([.!?]+\s+)/).filter(part => part.trim());
    
    for (let i = 0; i < sentences.length; i++) {
        const sentence = sentences[i];
        
        // Si ajouter cette phrase dépasse la limite ET qu'on a déjà du contenu
        if ((currentChunk + sentence).length > maxSize && currentChunk.length > 0) {
            chunks.push(currentChunk.trim());
            currentChunk = sentence;
        } else {
            currentChunk += sentence;
        }
    }
    
    // Ajouter le dernier chunk s'il reste quelque chose
    if (currentChunk.trim()) {
        chunks.push(currentChunk.trim());
    }
    
    console.log(`✂️ Découpage INTELLIGENT: ${chunks.length} chunks sur ponctuations`);
    return chunks;
}

    
    // 🔥 SUPPRESSION DE splitByWords ET countWords - PLUS BESOIN
    
    async waitForPageRender(targetPage, maxWait = 2000) {
        console.log('⏳ Attente page', targetPage);
        
        const startTime = Date.now();
        
        return new Promise((resolve) => {
            const checkPageReady = () => {
                const pageInput = document.getElementById('pageInput');
                
                const isPageReady = (
                    typeof pageNum !== 'undefined' && 
                    pageNum === targetPage && 
                    !pageRendering &&
                    pageInput && 
                    parseInt(pageInput.value) === targetPage
                );
                
                if (isPageReady || Date.now() - startTime > maxWait) {
                    console.log('✅ Page prête en', Date.now() - startTime, 'ms');
                    resolve();
                } else {
                    setTimeout(checkPageReady, 100);
                }
            };
            
            checkPageReady();
        });
    }

    // 🚀 PRÉCHARGEMENT ANTICIPÉ DE LA PAGE SUIVANTE
    async preloadNextPageChunks(currentPage) {
        if (this.isPreloadingNextPage || currentPage >= pdfDoc.numPages) {
            return;
        }
        
        this.isPreloadingNextPage = true;
        
        try {
            console.log(`🔄 Préchargement anticipé page ${currentPage + 1}`);
            
            this.nextPageText = await this.extractTextFromNextPage(currentPage);
            
            if (this.nextPageText && this.nextPageText.length > 10) {
                this.nextPageChunks = this.splitTextIntoChunks(this.nextPageText);
                console.log(`✂️ Page ${currentPage + 1} : ${this.nextPageChunks.length} chunks créés`);
                
                // Précharger les premiers chunks de la page suivante
                const chunksToPreload = Math.min(3, this.nextPageChunks.length); // 🔥 MOINS DE PRÉCHARGEMENT
                
                for (let i = 0; i < chunksToPreload; i++) {
                    if (!this.isReading) break;
                    
                    const chunkId = `page${currentPage + 1}_chunk${i}_${Date.now()}`;
                    const audioUrl = await this.generateAudioUrl(this.nextPageChunks[i], chunkId);
                    
                    if (audioUrl) {
                        const audio = new Audio(audioUrl);
                        audio.playbackRate = this.readingSpeed;
                        audio.preload = 'auto';
                        audio.load();
                        
                        this.nextPagePreloaded.set(i, audio);
                        console.log(`Page suivante chunk ${i} préchargé`);
                    }
                }
            }
        } catch (error) {
            console.error('Erreur préchargement page suivante:', error);
        } finally {
            this.isPreloadingNextPage = false;
        }
    }

    async preloadNextChunks(startIndex) {
        console.log(`Préchargement depuis ${startIndex}`);
        
        for (let i = 0; i < this.preloadCount && (startIndex + i) < this.textChunks.length; i++) {
            const index = startIndex + i;
            if (!this.preloadedAudios.has(index)) {
                this.preloadChunk(index).catch(error => {
                    console.warn(`Préchargement chunk ${index} échoué:`, error.message);
                });
            }
        }
    }

    async preloadChunk(index) {
        if (index >= this.textChunks.length) return;
        
        try {
            const cacheKey = `${this.currentPageNumber}_${index}_${this.readingSpeed}`;
            let audioUrl = this.audioCache.get(cacheKey);
            
            if (!audioUrl) {
                const chunkId = `page${this.currentPageNumber}_chunk${index}_${Date.now()}`;
                audioUrl = await this.generateAudioUrl(this.textChunks[index], chunkId);
            }
            
            if (audioUrl) {
                const audio = new Audio(audioUrl);
                audio.playbackRate = this.readingSpeed;
                audio.preload = 'auto';
                audio.load();
                
                this.preloadedAudios.set(index, audio);
                this.audioCache.set(cacheKey, audioUrl);
                console.log(`Chunk ${index} préchargé`);
            }
        } catch (error) {
            console.error(`Erreur préchargement chunk ${index}:`, error);
        }
    }

    async generateAudioUrl(text, chunkId) {
        try {
            const formData = new FormData();
            formData.append('text', text);
            formData.append('chunk_id', chunkId);
            formData.append('speed', this.readingSpeed.toString());
            
            const response = await fetch('tts_piper.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Erreur parsing JSON:', parseError);
                throw new Error('Réponse serveur non-JSON');
            }
            
            if (data.success) {
                return data.audio_url;
            } else {
                throw new Error(data.error || 'Erreur serveur inconnue');
            }
            
        } catch (error) {
            console.error(' Erreur génération audio:', error);
            return null;
        }
    }

    //  LECTURE PRINCIPALE AVEC CONTINUITÉ PARFAITE
    async readCurrentPage(isContinuation = false) {
        if (!isContinuation && this.isReading && !this.isPaused) {
            this.pauseReading();
            return;
        }
        
        if (!isContinuation && this.isPaused) {
            this.resumeReading();
            return;
        }

        if (this.isProcessing) {
            console.log('🔒 Lecture déjà en cours, ignoré');
            return;
        }

        this.isProcessing = true;

        try {
            console.log('🎵 Début lecture BRUTE page', pageNum);
            this.isReading = true;
            this.isPaused = false;
            this.currentPageNumber = pageNum;
            
            if (!isContinuation) {
                this.showLoadingIndicator();
                this.enableContinuousMode();
            }
            
            // 🚀 UTILISER LES CHUNKS PRÉCHARGÉS SI DISPONIBLES
            if (isContinuation && this.nextPageChunks.length > 0) {
                console.log('🔄 Utilisation des chunks préchargés de la page suivante');
                this.textChunks = this.nextPageChunks;
                this.preloadedAudios = this.nextPagePreloaded;
                
                // Réinitialiser pour la prochaine page
                this.nextPageChunks = [];
                this.nextPagePreloaded = new Map();
                this.nextPageText = '';
            } else {
                const pageText = await this.extractTextFromCurrentPage();
                
                if (!pageText || pageText.length < 10) {
                    this.hideLoadingIndicator();
                    
                    if (isContinuation && pageNum < pdfDoc.numPages) {
                        console.log('📄 Page vide, passage à la suivante...');
                        await this.goToNextPageAndContinue();
                    } else {
                        showAlert('warning', 'Aucun texte détecté sur cette page');
                        this.stopReading(false);
                    }
                    return;
                }

                this.textChunks = this.splitTextIntoChunks(pageText);
                console.log(`✂️ Chunks BRUTS créés: ${this.textChunks.length}`);
                
                await this.preloadChunk(0);
            }
            
            this.updateButtonState('playing');
            

            // 🎵 LECTURE SÉQUENTIELLE COMPLÈTE
for (let i = 0; i < this.textChunks.length; i++) {
    if (!this.isReading) {
        console.log('🛑 Lecture arrêtée par l\'utilisateur');
        break;
    }
    
    if (!this.continuousMode && this.currentPageNumber !== pageNum) {
        console.log('📄 Page changée manuellement, arrêt');
        this.stopReading(false);
        return;
    }
    
    if (this.isPaused) {
        await this.waitForResume();
    }
    
    if (!this.isReading) break;
    
    this.currentChunkIndex = i;
    console.log(`🎵 Lecture chunk BRUT ${i + 1}/${this.textChunks.length} (Page ${this.currentPageNumber})`);
    console.log(`📝 Contenu BRUT: "${this.textChunks[i].substring(0, 50)}..."`);
    
    // 🚀 PRÉCHARGEMENT ANTICIPÉ DE LA PAGE SUIVANTE
    const remainingChunks = this.textChunks.length - i;
    if (remainingChunks <= this.anticipationThreshold && 
        this.currentPageNumber < pdfDoc.numPages &&
        !this.isPreloadingNextPage) {
        
        console.log(`🔮 Anticipation : ${remainingChunks} chunks restants, préchargement page suivante`);
        this.preloadNextPageChunks(this.currentPageNumber);
    }
    
    // 🚀 NOUVEAUTÉ : Préchargement massif après le premier chunk
    if (i === 0) {
        console.log('🔄 Démarrage préchargement massif en arrière-plan...');
        // Précharger les 4 suivants sans attendre
        for (let j = 1; j <= Math.min(4, this.textChunks.length - 1); j++) {
            if (!this.preloadedAudios.has(j)) {
                this.preloadChunk(j).catch(() => {});
            }
        }
    }
    
    // Préchargement normal (gardé tel quel)
    if (i < this.textChunks.length - 1) {
        const nextIndex = i + 1;
        if (!this.preloadedAudios.has(nextIndex)) {
            this.preloadChunk(nextIndex).catch(() => {});
        }
    }
    
    this.updateButtonStateWithProgress(i + 1, this.textChunks.length);
    
    await this.playChunkSafe(i);
}


            
            // 🚀 VÉRIFICATION COMPLÈTE
            console.log(`📊 Page ${this.currentPageNumber} - Status: ${this.currentChunkIndex + 1}/${this.textChunks.length} chunks`);
            
            if (this.isReading && this.currentChunkIndex >= this.textChunks.length - 1) {
                if (this.currentPageNumber < pdfDoc.numPages) {
                    console.log('📄 Page complète → Passage automatique à la suivante');
                    await this.goToNextPageAndContinue();
                } else {
                    console.log('📚 Fin du livre atteinte !');
                    this.showCompletionMessage();
                    this.stopReading(false);
                }
            } else if (this.isReading) {
                console.log(`⚠️ Page incomplète détectée: ${this.currentChunkIndex + 1}/${this.textChunks.length}`);
                if (this.currentPageNumber < pdfDoc.numPages) {
                    await this.goToNextPageAndContinue();
                } else {
                    this.stopReading(false);
                }
            }
            
        } catch (error) {
            console.error('❌ Erreur lecture page:', error);
            this.hideLoadingIndicator();
            this.stopReading(false);
        } finally {
            this.isProcessing = false;
        }
    }

    async playChunkSafe(index) {
        if (!this.isReading) return;
        
        try {
            let audio = this.preloadedAudios.get(index);
            
            if (!audio) {
                console.log(`⚡ Chunk ${index} non préchargé, génération...`);
                const chunkId = `page${this.currentPageNumber}_chunk${index}_${Date.now()}`;
                const audioUrl = await this.generateAudioUrl(this.textChunks[index], chunkId);
                
                if (!audioUrl) {
                    console.warn(`⚠️ Impossible de générer audio pour chunk ${index}`);
                    return;
                }
                
                audio = new Audio(audioUrl);
                audio.playbackRate = this.readingSpeed;
            }
            
            this.currentAudio = audio;
            
            if (index === 0) {
                audio.addEventListener('play', () => {
                    this.hideLoadingIndicator();
                }, { once: true });
            }
            
            await new Promise((resolve, reject) => {
                let resolved = false;
                
                const cleanup = () => {
                    if (!resolved) {
                        resolved = true;
                        this.cleanupAudio(index);
                        resolve();
                    }
                };
                
                audio.onended = cleanup;
                audio.onerror = (error) => {
                    console.warn(`⚠️ Erreur audio chunk ${index}:`, error);
                    cleanup();
                };
                
                const timeout = setTimeout(() => {
                    if (!resolved) {
                        console.warn(`⏱️ Timeout chunk ${index}`);
                        cleanup();
                    }
                }, 60000);
                
                audio.play().then(() => {
                    clearTimeout(timeout);
                }).catch((playError) => {
                    console.warn(` Erreur play chunk ${index}:`, playError);
                    clearTimeout(timeout);
                    cleanup();
                });
            });
            
        } catch (error) {
            console.warn(` Erreur lecture chunk ${index}:`, error);
        }
    }

    async goToNextPageAndContinue() {
        console.log('📄 Transition fluide vers la page suivante...');
        
        this.isProcessing = true;
        this.isAutoNavigating = true;  
        
        const hasPreloadedNext = this.nextPageChunks.length > 0;
        
        if (!hasPreloadedNext) {
            this.resetForNewPage();
        }
        
        if (typeof onNextPage === 'function') {
            onNextPage();
            
            await new Promise(resolve => setTimeout(resolve, hasPreloadedNext ? 50 : 150));

            this.isAutoNavigating = false;
            
            if (!hasPreloadedNext) {
                await this.waitForPageRender(pageNum, 1000);
            }
            
            this.currentPageNumber = pageNum;
            
            if (this.isReading) {
                console.log(`🔄 Continuation ${hasPreloadedNext ? 'fluide' : 'normale'} sur page ${pageNum}`);
                this.isProcessing = false;
                await this.readCurrentPage(true);
            }
        } else {
            console.error('❌ Fonction onNextPage non trouvée');
            this.stopReading(false);
            this.isProcessing = false;
        }
    }

    cleanupAudio(index) {
        const audio = this.preloadedAudios.get(index);
        if (audio) {
            audio.pause();
            audio.src = '';
            this.preloadedAudios.delete(index);
        }
    }

    resetForNewPage() {
        this.preloadedAudios.clear();
        this.textChunks = [];
        this.currentChunkIndex = 0;
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio = null;
        }
    }

    pauseReading() {
        if (this.currentAudio && !this.isPaused) {
            this.isPaused = true;
            this.currentAudio.pause();
            console.log('⏸️ Lecture en pause');
            this.updateButtonState('paused');
        }
    }

    resumeReading() {
        if (this.currentAudio && this.isPaused) {
            this.isPaused = false;
            this.currentAudio.play();
            console.log('▶️ Reprise de la lecture');
            this.updateButtonState('playing');
        }
    }

    async waitForResume() {
        while (this.isPaused && this.isReading) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    stopReading(showError = true) {
        this.isReading = false;
        this.isPaused = false;
        this.isProcessing = false;
        this.continuousMode = false;
        
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio = null;
        }
        
        this.preloadedAudios.clear();
        this.nextPagePreloaded.clear();
        this.nextPageChunks = [];
        this.nextPageText = '';
        this.isPreloadingNextPage = false;
        
        this.updateButtonState('stopped');
        this.hideLoadingIndicator();
        
        console.log('🛑 Lecture arrêtée');
    }

    enableContinuousMode() {
        this.continuousMode = true;
        console.log('🔄 Mode continu activé');
    }

    disableContinuousMode() {
        this.continuousMode = false;
        console.log('⏹️ Mode continu désactivé');
    }

    destroy() {
        if (this.pageWatcher) {
            clearInterval(this.pageWatcher);
            this.pageWatcher = null;
        }
        this.stopReading(false);
        console.log('🧹 AudioReader détruit');
    }

    setSpeed(speed) {
        this.readingSpeed = Math.max(0.5, Math.min(2.0, speed));
        if (this.currentAudio) {
            this.currentAudio.playbackRate = this.readingSpeed;
        }
        console.log(`🎵 Vitesse: ${this.readingSpeed}x`);
    }

    showLoadingIndicator() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'block';
        }
    }

    hideLoadingIndicator() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }

    updateButtonStateWithProgress(current, total) {
        const btn = document.getElementById('audioToggleBtn');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-pause';
                btn.title = `Lecture BRUTE (${current}/${total}) - Page ${this.currentPageNumber}`;
            }
        }
    }

    updateButtonState(state) {
        const btn = document.getElementById('audioToggleBtn');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                switch(state) {
                    case 'playing':
                        icon.className = 'fas fa-pause';
                        btn.title = 'Pause (Mode BRUT)';
                        btn.classList.add('audio-loading-pulse');
                        break;
                    case 'paused':
                        icon.className = 'fas fa-play';
                        btn.title = 'Reprendre la lecture BRUTE';
                        btn.classList.remove('audio-loading-pulse');
                        break;
                    default:
                        icon.className = 'fas fa-volume-up';
                        btn.title = 'Lecture audio BRUTE complète';
                        btn.classList.remove('audio-loading-pulse');
                }
            }
        }
    }

    showCompletionMessage() {
        const alert = document.createElement('div');
        alert.className = 'fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-green-500 text-white p-6 rounded-lg shadow-xl z-50 text-center';
        alert.innerHTML = `
            <div class="text-2xl mb-2">🎉</div>
            <h3 class="text-lg font-bold mb-2">Félicitations !</h3>
            <p>Vous avez terminé le livre !</p>
            <p class="text-sm mt-2">Lecture BRUTE terminée</p>
        `;
        
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 5000);
    }

    getDetailedState() {
        return {
            isReading: this.isReading,
            isPaused: this.isPaused,
            isProcessing: this.isProcessing,
            continuousMode: this.continuousMode,
            currentPage: this.currentPageNumber,
            actualPage: pageNum,
            currentChunk: this.currentChunkIndex + 1,
            totalChunks: this.textChunks.length,
            preloadedCount: this.preloadedAudios.size,
            nextPageChunks: this.nextPageChunks.length,
            nextPagePreloaded: this.nextPagePreloaded.size,
            isPreloadingNextPage: this.isPreloadingNextPage,
            cacheSize: this.audioCache.size,
            chunkSize: this.chunkSize
        };
    }
}

// INITIALISATION GLOBALE
let audioReader = null;

function setupCleanupEvents() {
    window.addEventListener('beforeunload', function(e) {
        console.log('🧹 Nettoyage avant fermeture');
        if (audioReader) {
            audioReader.destroy();
        }
    });
}

function setupSimplePageDetection() {
    console.log('📄 Configuration détection simple des changements de page');
    
    let checkPageInterval = setInterval(() => {
        if (typeof pageNum !== 'undefined' && pageNum !== lastKnownPage) {
            console.log('📄 Variable pageNum changée:', lastKnownPage, '->', pageNum);
            
            if (audioReader && !audioReader.isProcessing && !audioReader.continuousMode) {
                audioReader.handlePageChange();
            }
            
            lastKnownPage = pageNum;
        }
    }, 500);
}

// 🚀 FONCTION AUDIO SIMPLIFIÉE
function handleAudioClick() {
    console.log('🎵 Clic audio BRUT détecté - Page courante:', pageNum);
    
    if (!audioReader) {
        console.log('🔧 Initialisation AudioReader ULTRA-SIMPLE...');
        audioReader = new AudioReader();
        audioReader.setupPageChangeDetection();
    }
    
    if (!pdfDoc) {
        console.error('❌ PDF non chargé');
        showAlert('warning', 'Veuillez attendre que le livre soit chargé');
        return;
    }
    
    audioReader.currentPageNumber = pageNum;
    lastKnownPage = pageNum;
    
    console.log('🚀 Lancement lecture BRUTE sur page', pageNum);
    audioReader.readCurrentPage(false);
}

// 🚀 FONCTIONS DE DEBUG
function debugAudioState() {
    if (audioReader) {
        const state = audioReader.getDetailedState();
        console.log('🔍 État AudioReader BRUT:', state);
        return state;
    } else {
        console.log('❌ AudioReader non initialisé');
        return null;
    }
}

function forceNextPage() {
    if (audioReader && audioReader.isReading) {
        console.log('🔧 Forçage passage page suivante...');
        audioReader.goToNextPageAndContinue();
    }
}

function toggleContinuousMode() {
    if (audioReader) {
        if (audioReader.continuousMode) {
            audioReader.disableContinuousMode();
            console.log('⏹️ Mode continu désactivé');
        } else {
            audioReader.enableContinuousMode();
            console.log('🔄 Mode continu activé');
        }
    }
}

// Fonctions exposées globalement pour debug
window.debugAudio = debugAudioState;
window.forceNextPage = forceNextPage;
window.toggleContinuous = toggleContinuousMode;

// TOUTES LES AUTRES FONCTIONS (PDF, navigation, etc.) - INCHANGÉES
function loadPDF() {
    try {
        document.getElementById('loadingSpinner').style.display = 'block';
        
        pdfjsLib.getDocument('<?php echo addslashes($book_path); ?>').promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('totalPages').textContent = pdf.numPages;
            
            if (!isMobile()) {
                scale = 1.5;
                document.getElementById('zoomLevel').textContent = '150%';
            }

            document.getElementById('pageInput').max = pdf.numPages;
            
            renderPage(pageNum);
            console.log('✅ PDF chargé avec succès');
             
        }).catch(function(error) {
            console.error('❌ Erreur lors du chargement du PDF:', error);
            showAlert('error', 'Erreur lors du chargement du livre');
        });
    } catch (error) {
        console.error('❌ Erreur loadPDF:', error);
        showAlert('error', 'Erreur lors du chargement du livre');
    }
}

function renderPage(num) {
    try {
        pageRendering = true;
        document.getElementById('loadingSpinner').style.display = 'block';
        
        pdfDoc.getPage(num).then(function(page) {
            const viewport = page.getViewport({ scale: scale });
            
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
                
                document.getElementById('pageInput').value = num;
                document.getElementById('currentPageNote').textContent = num;
                
                saveProgress(num);
                loadNoteForPage(num);
                
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        }).catch(function(error) {
            console.error('❌ Erreur rendu page:', error);
            pageRendering = false;
            document.getElementById('loadingSpinner').style.display = 'none';
        });
    } catch (error) {
        console.error('❌ Erreur renderPage:', error);
        pageRendering = false;
        document.getElementById('loadingSpinner').style.display = 'none';
    }
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (pageNum <= 1) return;
    
    // 🛑 SEULEMENT si navigation MANUELLE (pas auto)
    if (audioReader && audioReader.isReading && !audioReader.isAutoNavigating) {
        console.log('🛑 Navigation MANUELLE détectée - ARRÊT audio');
        audioReader.stopReading(false);
        audioReader.disableContinuousMode();
        showAlert('info', 'Audio arrêté - Relancez manuellement si désiré');
    }
    
    pageNum--;
    queueRenderPage(pageNum);
}

function onNextPage() {
    if (pageNum >= pdfDoc.numPages) return;
    
    // 🛑 SEULEMENT si navigation MANUELLE (pas auto)
    if (audioReader && audioReader.isReading && !audioReader.isAutoNavigating) {
        console.log('🛑 Navigation MANUELLE détectée - ARRÊT audio');
        audioReader.stopReading(false);
        audioReader.disableContinuousMode();
        showAlert('info', 'Audio arrêté - Relancez manuellement si désiré');
    }
    
    pageNum++;
    queueRenderPage(pageNum);
}


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

function openSidePanel() {
    document.getElementById('sidePanel').classList.add('open');
}

function closeSidePanel() {
    document.getElementById('sidePanel').classList.remove('open');
}

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
        showAlert('warning', 'Veuillez sélectionner une note');
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
                console.error('❌ Erreur:', e);
            }
        },
        error: function() {
            showAlert('error', 'Erreur lors de l\'envoi de la notation');
        }
    });
}

function saveProgress(pageNumber) {
    $.ajax({
        url: 'ajax/save_progress.php',
        type: 'POST',
        data: {
            book_id: bookId,
            page_number: pageNumber
        },
        error: function() {
            console.warn('⚠️ Erreur sauvegarde progression (non-bloquante)');
        }
    });
}

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
                console.error('❌ Erreur parsing notes:', e);
            }
        },
        error: function() {
            console.warn('⚠️ Erreur chargement notes (non-bloquante)');
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
                console.error('❌ Erreur parsing note:', e);
            }
        },
        error: function() {
            console.warn('⚠️ Erreur chargement note (non-bloquante)');
        }
    });
}

function saveNote() {
    const noteText = document.getElementById('noteText').value.trim();
    if (!noteText) {
        showAlert('warning', 'Veuillez écrire une note avant de l\'enregistrer');
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
                console.error('❌ Erreur:', e);
            }
        },
        error: function() {
            showAlert('error', 'Erreur lors de l\'enregistrement de la note');
        }
    });
}

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
                console.error('❌ Erreur parsing bookmarks:', e);
            }
        },
        error: function() {
            console.warn('⚠️ Erreur chargement signets (non-bloquante)');
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
                console.error('❌ Erreur:', e);
            }
        },
        error: function() {
            showAlert('error', 'Erreur lors de l\'ajout du signet');
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
                console.error('❌ Erreur:', e);
            }
        },
        error: function() {
            showAlert('error', 'Erreur lors de la suppression du signet');
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
        type === 'success' ? 'bg-green-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-red-500'
    }`;
    alert.innerHTML = `<div class="flex items-center gap-2">
        <i class="fas fa-${type === 'success' ? 'check' : type === 'warning' ? 'exclamation' : 'times'}-circle"></i>
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

// CSS pour l'animation
const audioStyle = document.createElement('style');
audioStyle.textContent = `
    .audio-loading-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
`;
document.head.appendChild(audioStyle);

// INITIALISATION PRINCIPALE
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initialisation ULTRA-SIMPLE');
    
    const ratingModal = document.getElementById('ratingModal');
    if (ratingModal) {
        ratingModal.classList.add('hidden');
        ratingModal.style.display = 'none';
    }
    
    loadPDF();
    setupTouchNavigation();
    setupSimplePageDetection();
    setupCleanupEvents();
    
    // Event listeners pour les contrôles
    document.getElementById('prevBtn').addEventListener('click', onPrevPage);
    document.getElementById('nextBtn').addEventListener('click', onNextPage);
    
    document.getElementById('pageInput').addEventListener('change', function() {
        const page = parseInt(this.value);
        if (page >= 1 && page <= pdfDoc.numPages) {
            pageNum = page;
            queueRenderPage(pageNum);
        }
    });
    
    document.getElementById('zoomInBtn').addEventListener('click', zoomIn);
    document.getElementById('zoomOutBtn').addEventListener('click', zoomOut);
    
    document.getElementById('notesBtn').addEventListener('click', function() {
        switchTab('notes');
        openSidePanel();
    });
    
    document.getElementById('bookmarkBtn').addEventListener('click', function() {
        switchTab('bookmarks');
        openSidePanel();
    });
    
    document.getElementById('closePanelBtn').addEventListener('click', closeSidePanel);
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            switchTab(this.dataset.tab);
        });
    });
    
    document.getElementById('saveNoteBtn').addEventListener('click', saveNote);
    document.getElementById('saveBookmarkBtn').addEventListener('click', saveBookmark);
    
    // Gestion modal de notation si applicable
    <?php if (!$has_rated): ?>
    document.getElementById('rateBtn').addEventListener('click', function(e) {
        e.preventDefault();
        showRatingModal();
    });
    document.getElementById('closeRatingModal').addEventListener('click', hideRatingModal);
    document.getElementById('cancelRatingBtn').addEventListener('click', hideRatingModal);
    document.getElementById('submitRatingBtn').addEventListener('click', submitRating);
    
    document.getElementById('ratingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideRatingModal();
        }
    });
    
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
    
    loadNotes();
    loadBookmarks();
    
    // CONNEXION AUDIO ULTRA-SIMPLE
    const connectAudioWhenReady = () => {
        if (pdfDoc) {
            const audioBtn = document.getElementById('audioToggleBtn');
            if (audioBtn) {
                const newBtn = audioBtn.cloneNode(true);
                audioBtn.parentNode.replaceChild(newBtn, audioBtn);
                
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleAudioClick();
                });
                
                console.log('🔗 Bouton audio ULTRA-SIMPLE connecté');
            }
        } else {
            setTimeout(connectAudioWhenReady, 500);
        }
    };
    
    connectAudioWhenReady();
    
    // Event listeners pour clavier
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
    
    window.addEventListener('resize', function() {
        if (pdfDoc) {
            queueRenderPage(pageNum);
        }
    });
    
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            if (pdfDoc) {
                queueRenderPage(pageNum);
            }
        }, 100);
    });
    
    console.log('✅ Initialisation ULTRA-SIMPLE terminée');
});






</script>
    <!-- Ajouter AVANT tes scripts jQuery et PDF.js -->
<script src="https://cdn.jsdelivr.net/npm/onnxruntime-web@1.16.3/dist/ort.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@xenova/transformers@2.6.0/dist/transformers.min.js"></script>

</body>
</html>