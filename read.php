<?php
// read.php - Interface de lecture de livre améliorée
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
    header("Location: library.php");
    exit();
}

$book = $stmt->fetch(PDO::FETCH_ASSOC);
$book_path = $book['file_path'];

// Récupérer la dernière page lue
$stmt = $conn->prepare("SELECT last_page_read FROM user_library WHERE user_id = :user_id AND book_id = :book_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();

$last_page = 1; // Par défaut, commencer à la page 1
if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_page = $row['last_page_read'];
} else {
    // Si le livre n'est pas encore dans la bibliothèque de l'utilisateur, l'ajouter
    $stmt = $conn->prepare("INSERT INTO user_library (user_id, book_id, last_page_read, added_at) 
                            VALUES (:user_id, :book_id, 1, NOW())");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Enregistrer le début de la lecture
    $stmt = $conn->prepare("INSERT INTO reading_history (user_id, book_id, action, page_number) 
                            VALUES (:user_id, :book_id, 'started', 1)");
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

$page_title = "Lecture - " . $book['title'];
include "includes/header.php";
?>

<!-- Structure principale avec support mode sombre -->
<div class="flex flex-col h-screen bg-gray-100 dark:bg-gray-900 overflow-hidden">
    <!-- En-tête réduite -->
    <div class="bg-blue-800 dark:bg-gray-800 text-white p-1 md:p-2 flex justify-between items-center shadow-lg">
        <div class="flex items-center">
            <button id="toggleMenu" class="md:hidden text-white p-1 mr-1">
                <i class="fas fa-bars"></i>
            </button>
            <a href="library.php" class="text-white hover:text-blue-200 dark:hover:text-blue-300 mr-1">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="truncate">
                <h1 class="text-sm md:text-base font-bold truncate max-w-[150px] md:max-w-md"><?php echo htmlspecialchars($book['title']); ?></h1>
            </div>
        </div>
        
        <div class="flex items-center space-x-1">
            <!-- Menu d'actions compacté -->
            <div class="relative md:hidden">
                <button id="mobileActionsToggle" class="bg-blue-700 dark:bg-gray-700 p-1 rounded-full text-sm">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div id="mobileActionsMenu" class="absolute right-0 mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-xl w-48 hidden z-50">
                    <div class="p-2 border-b dark:border-gray-700">
                        <div class="flex items-center justify-center mb-1">
                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                            <span class="text-gray-800 dark:text-gray-200"><?php echo $average_rating; ?>/5</span>
                        </div>
                        <?php if (!$has_rated): ?>
                        <button id="rateBookBtnMobile" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm flex items-center justify-center">
                            <i class="fas fa-star mr-1"></i> Noter
                        </button>
                        <?php else: ?>
                        <div class="w-full bg-green-500 text-white px-3 py-1 rounded text-sm text-center">
                            <i class="fas fa-check mr-1"></i> Noté (<?php echo $user_rating; ?>/5)
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-2">
                        <button id="toggleNotesMobile" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-sm text-gray-800 dark:text-gray-200">
                            <i class="fas fa-book mr-2"></i> Notes
                        </button>
                        <button id="addBookmarkBtnMobile" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-sm text-gray-800 dark:text-gray-200">
                            <i class="fas fa-bookmark mr-2"></i> Marque-page
                        </button>
                        <button id="toggleZoomControlsMobile" class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded text-sm text-gray-800 dark:text-gray-200">
                            <i class="fas fa-search mr-2"></i> Zoom
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Note moyenne très compacte -->
            <div class="hidden md:flex items-center bg-blue-700 dark:bg-gray-700 px-1 py-0.5 rounded-full text-xs">
                <i class="fas fa-star text-yellow-300 mr-1"></i>
                <span><?php echo $average_rating; ?></span>
            </div>
            
            <!-- Bouton pour noter le livre - visible uniquement sur desktop -->
            <?php if (!$has_rated): ?>
            <button id="rateBookBtn" class="hidden md:flex bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-0.5 rounded-full items-center text-xs">
                <i class="fas fa-star mr-1"></i> Noter
            </button>
            <?php else: ?>
            <div class="hidden md:flex bg-green-500 text-white px-2 py-0.5 rounded-full items-center text-xs">
                <i class="fas fa-check mr-1"></i> Noté (<?php echo $user_rating; ?>/5)
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Barre de navigation compacte -->
    <div class="bg-white dark:bg-gray-800 p-1 flex justify-between items-center border-b dark:border-gray-700 shadow-sm">
        <div class="flex items-center space-x-1">
            <button id="btnPrev" class="bg-blue-700 hover:bg-blue-800 dark:bg-gray-700 dark:hover:bg-gray-600 text-white px-2 py-1 rounded flex items-center text-xs">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="flex items-center bg-gray-100 dark:bg-gray-700 px-1 py-1 rounded border dark:border-gray-600 text-xs text-gray-800 dark:text-gray-200">
                <span id="currentPage"><?php echo $last_page; ?></span>/<span id="totalPages">...</span>
            </div>
            
            <button id="btnNext" class="bg-blue-700 hover:bg-blue-800 dark:bg-gray-700 dark:hover:bg-gray-600 text-white px-2 py-1 rounded flex items-center text-xs">
                <i class="fas fa-chevron-right"></i>
            </button>
    </div>
        
        <!-- Contrôles essentiels compactés -->
        <div class="flex items-center space-x-1">
            <button id="zoomOutBtn" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 p-1 rounded text-xs">
                <i class="fas fa-search-minus"></i>
            </button>
            <button id="zoomInBtn" class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 p-1 rounded text-xs">
                <i class="fas fa-search-plus"></i>
            </button>
            <button id="toggleNotes" class="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white p-1 rounded text-xs">
                <i class="fas fa-book"></i>
            </button>
            <button id="addBookmarkBtn" class="bg-purple-600 hover:bg-purple-700 dark:bg-purple-700 dark:hover:bg-purple-800 text-white p-1 rounded text-xs">
                <i class="fas fa-bookmark"></i>
            </button>
        </div>
    </div>
    
    <!-- Contrôles de zoom pour mobile -->
    <div id="mobileZoomControls" class="bg-white dark:bg-gray-800 p-1 flex justify-center items-center border-b dark:border-gray-700 shadow-sm hidden">
        <div class="flex items-center space-x-4">
            <button id="zoomOutBtnMobile" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 p-1 rounded-full text-xs">
                <i class="fas fa-search-minus"></i>
            </button>
            <span id="zoomLevel" class="text-gray-700 dark:text-gray-300 font-medium text-xs">150%</span>
            <button id="zoomInBtnMobile" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 p-1 rounded-full text-xs">
                <i class="fas fa-search-plus"></i>
            </button>
        </div>
    </div>
    
    <!-- Conteneur PDF optimisé -->
    <div id="pdfContainer" class="flex-grow bg-gray-100 dark:bg-gray-900 relative overflow-auto">
        <div id="viewer" class="pdfViewer flex justify-center items-start p-0 h-full">
            <!-- Le canvas sera ajouté ici par JavaScript -->
        </div>
        
        <!-- Animation de tournage de page -->
        <div id="pageFlip" class="hidden absolute inset-0 bg-black bg-opacity-30 flex justify-center items-center z-10">
            <div class="bg-white dark:bg-gray-800 w-16 h-24 md:w-24 md:h-32 rounded shadow-xl transform transition-all duration-300"></div>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loadingIndicator" class="absolute inset-0 flex justify-center items-center bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 z-20">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-3 border-blue-600 dark:border-blue-400 border-t-transparent"></div>
                <div class="mt-1 text-blue-800 dark:text-blue-400 font-medium text-xs">Chargement...</div>
            </div>
        </div>
    </div>
    
    <!-- Panneau latéral (notes et marque-pages) -->
    <div id="sidePanel" class="w-full md:w-72 lg:w-80 bg-white dark:bg-gray-800 shadow-lg flex flex-col border-l dark:border-gray-700 fixed inset-y-0 right-0 z-30 transform translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out h-screen">
        <!-- En-tête du panneau -->
        <div class="bg-blue-800 dark:bg-gray-700 text-white p-2 flex justify-between items-center">
            <h3 class="font-bold text-base" id="panelTitle">Mes Notes</h3>
            <button id="closePanel" class="hover:text-blue-200 dark:hover:text-gray-300 text-lg">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Onglets -->
        <div class="flex border-b dark:border-gray-700">
            <button class="tab-btn active py-1 px-2 font-medium text-blue-800 dark:text-blue-400 border-b-2 border-blue-800 dark:border-blue-400 text-xs" data-tab="notes">
                <i class="fas fa-book mr-1"></i><span>Notes</span>
            </button>
            <button class="tab-btn py-1 px-2 font-medium text-gray-500 dark:text-gray-400 text-xs" data-tab="bookmarks">
                <i class="fas fa-bookmark mr-1"></i><span>Marque-pages</span>
            </button>
            <button class="tab-btn py-1 px-2 font-medium text-gray-500 dark:text-gray-400 text-xs" data-tab="info">
                <i class="fas fa-info-circle mr-1"></i><span>Infos</span>
            </button>
        </div>
        
        <!-- Contenu des onglets -->
        <div class="flex-grow overflow-y-auto">
            <!-- Onglet Notes -->
            <div id="notesTab" class="tab-content p-2">
                <div class="mb-2">
                    <label class="block text-gray-700 dark:text-gray-300 font-bold mb-1 text-xs">Page actuelle</label>
                    <div id="currentPageForNote" class="bg-gray-100 dark:bg-gray-700 p-1 rounded text-center font-medium text-xs text-gray-800 dark:text-gray-200"><?php echo $last_page; ?></div>
                </div>
                
                <div class="mb-2">
                    <label for="noteText" class="block text-gray-700 dark:text-gray-300 font-bold mb-1 text-xs">Votre note</label>
                    <textarea id="noteText" rows="3" class="w-full p-2 border dark:border-gray-600 rounded resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200"></textarea>
                </div>
                
                <div id="existingNote" class="mb-2 hidden">
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-2 rounded border border-blue-200 dark:border-blue-800">
                        <div class="font-bold text-blue-800 dark:text-blue-400 mb-1 text-xs">Note existante:</div>
                        <div id="existingNoteText" class="text-gray-700 dark:text-gray-300 text-xs"></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="noteDate"></div>
                    </div>
                </div>
                
                <button id="saveNote" class="w-full bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 text-white py-1 px-2 rounded-lg font-medium transition duration-200 text-xs">
                    <i class="fas fa-save mr-1"></i>Enregistrer la note
                </button>
                
                <div class="mt-3">
                    <h4 class="font-bold mb-1 text-gray-700 dark:text-gray-300 border-b dark:border-gray-700 pb-1 text-xs">Notes précédentes</h4>
                    <div id="notesList" class="space-y-1 max-h-32 md:max-h-56 overflow-y-auto">
                        <div class="text-center text-gray-500 dark:text-gray-400 italic py-2 text-xs">Chargement des notes...</div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Marque-pages -->
            <div id="bookmarksTab" class="tab-content p-2 hidden">
                <div class="mb-2">
                    <label class="block text-gray-700 dark:text-gray-300 font-bold mb-1 text-xs">Page actuelle</label>
                    <div class="flex items-center space-x-1">
                        <div id="currentPageForBookmark" class="bg-gray-100 dark:bg-gray-700 p-1 rounded text-center font-medium flex-shrink-0 w-12 text-xs text-gray-800 dark:text-gray-200"><?php echo $last_page; ?></div>
                        <input type="text" id="bookmarkName" class="flex-grow p-1 border dark:border-gray-600 rounded text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200" placeholder="Nom du marque-page">
                    </div>
                </div>
                
                <button id="saveBookmark" class="w-full bg-purple-600 hover:bg-purple-700 dark:bg-purple-700 dark:hover:bg-purple-800 text-white py-1 px-2 rounded-lg font-medium transition duration-200 mb-2 text-xs">
                    <i class="fas fa-plus mr-1"></i>Ajouter un marque-page
                </button>
                
                <div>
                    <h4 class="font-bold mb-1 text-gray-700 dark:text-gray-300 border-b dark:border-gray-700 pb-1 text-xs">Marque-pages enregistrés</h4>
                    <div id="bookmarksList" class="space-y-1 max-h-32 md:max-h-56 overflow-y-auto">
                        <div class="text-center text-gray-500 dark:text-gray-400 italic py-2 text-xs">Chargement des marque-pages...</div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Infos -->
            <div id="infoTab" class="tab-content p-2 hidden">
                <div class="flex flex-col items-center mb-2">
                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Couverture du livre" class="w-24 h-auto rounded shadow-md mb-2">
                    <h4 class="font-bold text-sm mb-1 text-center text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($book['title']); ?></h4>
                </div>
                
                <div class="space-y-1 text-xs">
                    <p class="text-gray-600 dark:text-gray-400"><span class="font-medium">Auteur:</span> <?php echo htmlspecialchars($book['author'] ?? 'Inconnu'); ?></p>
                    <p class="text-gray-600 dark:text-gray-400"><span class="font-medium">Éditeur:</span> <?php echo htmlspecialchars($book['publisher'] ?? 'Inconnu'); ?></p>
                    <p class="text-gray-600 dark:text-gray-400"><span class="font-medium">Année:</span> <?php echo htmlspecialchars($book['publication_year'] ?? 'Inconnue'); ?></p>
                    <p class="text-gray-600 dark:text-gray-400"><span class="font-medium">Pages:</span> <?php echo htmlspecialchars($book['pages_count'] ?? 'Inconnu'); ?></p>
                </div>
                
                <div class="mt-2">
                    <h5 class="font-bold mb-1 text-xs text-gray-700 dark:text-gray-300">Description</h5>
                    <p class="text-gray-700 dark:text-gray-300 text-xs"><?php echo nl2br(htmlspecialchars($book['description'] ?? 'Aucune description disponible.')); ?></p>
                </div>
                
                <div class="mt-2">
                    <h5 class="font-bold mb-1 text-xs text-gray-700 dark:text-gray-300">Note moyenne</h5>
                    <div class="flex items-center">
                        <div class="flex mr-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($average_rating) ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-gray-700 dark:text-gray-300 text-xs"><?php echo $average_rating; ?> (sur 5)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Overlay pour fermer le panneau latéral sur mobile -->
    <div id="sidePanelOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>
</div>

<!-- Modal pour noter le livre -->
<div id="rateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-11/12 max-w-xs p-3 mx-2">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">Noter ce livre</h3>
            <button id="closeRateModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-2">
            <p class="text-gray-700 dark:text-gray-300 mb-1 text-xs">Quelle note donnez-vous à "<?php echo htmlspecialchars($book['title']); ?>" ?</p>
            <div class="flex justify-center mb-1">
                <div class="rating-stars flex space-x-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star text-xl md:text-2xl cursor-pointer text-gray-300 dark:text-gray-600 hover:text-yellow-400 star-rating" data-rating="<?php echo $i; ?>"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <input type="hidden" id="selectedRating" value="0">
        </div>
        
        <div class="mb-2">
            <label for="ratingComment" class="block text-gray-700 dark:text-gray-300 font-medium mb-1 text-xs">Commentaire (optionnel)</label>
            <textarea id="ratingComment" rows="2" class="w-full p-2 border dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200"></textarea>
        </div>
        
        <div class="flex justify-end space-x-2">
            <button id="cancelRating" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs">Annuler</button>
            <button id="submitRating" class="px-2 py-1 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-800 text-xs">Envoyer</button>
        </div>
    </div>
</div>

<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Configuration PDF.js
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
    
    // Variables globales
    let pdfDoc = null;
    let pageNum = <?php echo $last_page; ?>;
    let pageRendering = false;
    let pageNumPending = null;
    let scale = 1.5;
    let canvas = null;
    let ctx = null;
    const bookId = <?php echo $book_id; ?>;
    const userId = <?php echo $user_id; ?>;
    let currentNote = null;
    let isPanelOpen = false;
    let isMobile = window.innerWidth < 768;
    
    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier si l'appareil est mobile
        checkMobileView();
        
        // Créer le canvas
        canvas = document.createElement('canvas');
        document.getElementById('viewer').appendChild(canvas);
        ctx = canvas.getContext('2d');
        
        // Charger le PDF
        loadPDF('<?php echo $book_path; ?>');
        
        // Événements des boutons de navigation
        document.getElementById('btnPrev').addEventListener('click', onPrevPage);
        document.getElementById('btnNext').addEventListener('click', onNextPage);
        
        // Panneau latéral - desktop
        document.getElementById('toggleNotes').addEventListener('click', toggleSidePanel);
        document.getElementById('closePanel').addEventListener('click', toggleSidePanel);
        document.getElementById('sidePanelOverlay').addEventListener('click', toggleSidePanel);
        
        // Panneau latéral - mobile
        if (document.getElementById('toggleNotesMobile')) {
            document.getElementById('toggleNotesMobile').addEventListener('click', toggleSidePanel);
        }
        
        // Gestion du menu mobile
        document.getElementById('toggleMenu').addEventListener('click', toggleSidePanel);
        
        // Menu des actions sur mobile
        if (document.getElementById('mobileActionsToggle')) {
            document.getElementById('mobileActionsToggle').addEventListener('click', function() {
                document.getElementById('mobileActionsMenu').classList.toggle('hidden');
            });
            
            // Cliquer ailleurs ferme le menu
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#mobileActionsToggle') && !e.target.closest('#mobileActionsMenu')) {
                    document.getElementById('mobileActionsMenu').classList.add('hidden');
                }
            });
        }
        
        // Contrôles de zoom
        document.getElementById('zoomInBtn').addEventListener('click', zoomIn);
        document.getElementById('zoomOutBtn').addEventListener('click', zoomOut);
        
        // Contrôles de zoom mobile
        if (document.getElementById('zoomInBtnMobile')) {
            document.getElementById('zoomInBtnMobile').addEventListener('click', zoomIn);
            document.getElementById('zoomOutBtnMobile').addEventListener('click', zoomOut);
            document.getElementById('toggleZoomControlsMobile').addEventListener('click', toggleMobileZoomControls);
        }
        
        // Notes
        document.getElementById('saveNote').addEventListener('click', saveNote);
        
        // Marque-pages
        document.getElementById('addBookmarkBtn').addEventListener('click', showBookmarkTab);
        document.getElementById('saveBookmark').addEventListener('click', saveBookmark);
        
        if (document.getElementById('addBookmarkBtnMobile')) {
            document.getElementById('addBookmarkBtnMobile').addEventListener('click', showBookmarkTab);
        }
        
        // Gestion des onglets
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                switchTab(tab);
            });
        });
        
        // Charger les notes et marque-pages
        loadNotes();
        loadBookmarks();
        
        // Gestion de la notation
        <?php if (!$has_rated): ?>
        document.getElementById('rateBookBtn').addEventListener('click', showRateModal);
        
        if (document.getElementById('rateBookBtnMobile')) {
            document.getElementById('rateBookBtnMobile').addEventListener('click', showRateModal);
        }
        
        document.getElementById('closeRateModal').addEventListener('click', hideRateModal);
        document.getElementById('cancelRating').addEventListener('click', hideRateModal);
        document.getElementById('submitRating').addEventListener('click', submitRating);
        document.querySelectorAll('.star-rating').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                setRating(rating);
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(document.getElementById('selectedRating').value);
                highlightStars(currentRating);
            });
        });
        <?php endif; ?>
        
        // Navigation clavier
        document.addEventListener('keydown', function(e) {
            // Pas de navigation clavier quand on est dans un champ texte
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                onPrevPage();
            } else if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
                onNextPage();
            } else if (e.key === 'Escape') {
                if (isPanelOpen && isMobile) {
                    toggleSidePanel();
                }
            }
        });
        
        // Écouter les changements de taille de l'écran pour le responsive
        window.addEventListener('resize', handleResize);
    });
    
    // Vérifier si la vue est mobile
    function checkMobileView() {
        isMobile = window.innerWidth < 768;
        
        // Ajuster l'UI en fonction de la taille d'écran
        if (isMobile) {
            // Sur mobile, le panneau est fermé par défaut
            document.getElementById('sidePanel').classList.add('translate-x-full');
            isPanelOpen = false;
            
            // Ajuster le zoom pour les petits écrans
            if (scale > 1.2) {
                scale = 1.2;
                if (pdfDoc) {
                    queueRenderPage(pageNum);
                }
            }
        } else {
            // Sur desktop, le panneau peut être ouvert
            if (isPanelOpen) {
                document.getElementById('sidePanel').classList.remove('translate-x-full');
            }
        }
    }
    
    // Gérer le redimensionnement de la fenêtre
    function handleResize() {
        const wasMobile = isMobile;
        checkMobileView();
        
        // Si on passe de mobile à desktop ou vice versa, ajuster l'interface
        if (wasMobile !== isMobile) {
            // Ajuster le zoom et re-rendre la page
            if (pdfDoc) {
                queueRenderPage(pageNum);
            }
        }
        
        // Mettre à jour la valeur affichée du zoom
        document.getElementById('zoomLevel').textContent = (scale * 100).toFixed(0) + '%';
    }
    
    // Afficher/masquer les contrôles de zoom sur mobile
    function toggleMobileZoomControls() {
        const controls = document.getElementById('mobileZoomControls');
        controls.classList.toggle('hidden');
        document.getElementById('mobileActionsMenu').classList.add('hidden');
    }
    
    // Charger le PDF
    function loadPDF(url) {
        document.getElementById('loadingIndicator').classList.remove('hidden');
        
        pdfjsLib.getDocument(url).promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('totalPages').textContent = pdf.numPages;
            
            // Rendre la page initiale
            renderPage(pageNum);
            
            // Cacher l'indicateur de chargement
            document.getElementById('loadingIndicator').classList.add('hidden');
        }).catch(function(error) {
            console.error('Erreur lors du chargement du PDF:', error);
            alert('Une erreur est survenue lors du chargement du livre. Veuillez réessayer.');
            document.getElementById('loadingIndicator').classList.add('hidden');
        });
    }
    
    // Rendre une page
    function renderPage(num) {
    pageRendering = true;
    
    // Mise à jour de l'interface 
    document.getElementById('currentPage').textContent = num;
    document.getElementById('currentPageForNote').textContent = num;
    document.getElementById('currentPageForBookmark').textContent = num;
    
    // Obtenir la page du PDF
    pdfDoc.getPage(num).then(function(page) {
        // Calculer l'échelle optimale pour remplir la largeur disponible
        const viewportWidth = document.getElementById('viewer').clientWidth;
        const viewportOriginal = page.getViewport({ scale: 1.0 });
        
        // Calculer l'échelle pour s'adapter à la largeur moins une petite marge
        const optimalScale = (viewportWidth - 10) / viewportOriginal.width;
        
        // Utiliser cette échelle ou celle définie par l'utilisateur, selon ce qui est le plus petit
        // pour garantir que le document est visible en entier sur la largeur
        const effectiveScale = Math.min(scale, optimalScale);
        
        const viewport = page.getViewport({ scale: effectiveScale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        // Rendu de la page
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        page.render(renderContext).promise.then(function() {
            pageRendering = false;
                document.getElementById('loadingIndicator').classList.add('hidden');
                
                // Si une autre page est en attente, la rendre maintenant
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
                
                // Sauvegarder la progression de lecture
                saveProgress(num);
                
                // Charger la note de cette page, si elle existe
                loadNoteForPage(num);
            });
        });
    }
    
    // Afficher l'animation de tournage de page
    function showPageFlipAnimation() {
        const pageFlip = document.getElementById('pageFlip');
        pageFlip.classList.remove('hidden');
        
        setTimeout(() => {
            pageFlip.classList.add('hidden');
        }, 300); // Animation plus rapide pour une meilleure expérience
    }
    
    // Fonctions de navigation dans le PDF
    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }
    
    function onPrevPage() {
        if (pageNum <= 1) {
            return;
        }
        pageNum--;
        queueRenderPage(pageNum);
    }
    
    function onNextPage() {
        if (pageNum >= pdfDoc.numPages) {
            return;
        }
        pageNum++;
        queueRenderPage(pageNum);
    }
    
    // Zoom
    function zoomIn() {
        const maxZoom = isMobile ? 2 : 3; // Limiter le zoom max sur mobile
        if (scale < maxZoom) {
            scale += 0.25;
            document.getElementById('zoomLevel').textContent = (scale * 100).toFixed(0) + '%';
            queueRenderPage(pageNum);
        }
    }
    
    function zoomOut() {
        const minZoom = 0.5;
        if (scale > minZoom) {
            scale -= 0.25;
            document.getElementById('zoomLevel').textContent = (scale * 100).toFixed(0) + '%';
            queueRenderPage(pageNum);
        }
    }
    
    // Gestion du panneau latéral
    function toggleSidePanel() {
        const sidePanel = document.getElementById('sidePanel');
        const overlay = document.getElementById('sidePanelOverlay');
        
        // Sur mobile, on utilise une transformation pour l'animation
        if (isMobile) {
            sidePanel.classList.toggle('translate-x-full');
            overlay.classList.toggle('hidden');
            
            // Fermer aussi le menu des actions sur mobile
            if (document.getElementById('mobileActionsMenu')) {
                document.getElementById('mobileActionsMenu').classList.add('hidden');
            }
            
            // Fermer les contrôles de zoom sur mobile
            if (document.getElementById('mobileZoomControls')) {
                document.getElementById('mobileZoomControls').classList.add('hidden');
            }
        } else {
            // Sur desktop, c'est plus simple
            sidePanel.classList.toggle('md:translate-x-0');
        }
        
        isPanelOpen = !isPanelOpen;
    }
    
    function showBookmarkTab() {
        // Ouvrir le panneau s'il est fermé
        if (!isPanelOpen) {
            toggleSidePanel();
        }
        
        // Fermer le menu des actions sur mobile
        if (isMobile && document.getElementById('mobileActionsMenu')) {
            document.getElementById('mobileActionsMenu').classList.add('hidden');
        }
        
        switchTab('bookmarks');
    }
    
    // Gestion des onglets
    function switchTab(tab) {
        // Mettre à jour les boutons d'onglet
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.getAttribute('data-tab') === tab) {
                btn.classList.add('active', 'text-blue-800', 'border-blue-800');
                btn.classList.remove('text-gray-500');
            } else {
                btn.classList.remove('active', 'text-blue-800', 'border-blue-800');
                btn.classList.add('text-gray-500');
            }
        });
        
        // Afficher le contenu de l'onglet sélectionné
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById(tab + 'Tab').classList.remove('hidden');
        
        // Mettre à jour le titre du panneau
        document.getElementById('panelTitle').textContent = 
            tab === 'notes' ? 'Mes Notes' : 
            tab === 'bookmarks' ? 'Marque-pages' : 'Informations';
    }
    
    // Gestion des notes - optimisé pour le responsive
    function loadNotes() {
        $.ajax({
            url: 'ajax/get_notes.php',
            type: 'GET',
            data: {
                book_id: bookId
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        const notesList = document.getElementById('notesList');
                        
                        if (data.notes.length === 0) {
                            notesList.innerHTML = '<div class="text-center text-gray-500 italic py-3 text-sm">Aucune note pour ce livre</div>';
                            return;
                        }
                        
                        notesList.innerHTML = '';
                        data.notes.forEach(note => {
                            const noteItem = document.createElement('div');
                            noteItem.className = 'p-2 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 cursor-pointer transition text-sm';
                            noteItem.innerHTML = `
                                <div class="flex justify-between items-start">
                                    <div class="font-bold text-blue-700">Page ${note.page_number}</div>
                                    <div class="text-xs text-gray-500">${formatDate(note.created_at)}</div>
                                </div>
                                <div class="mt-1 text-gray-700 line-clamp-2">${note.note_text}</div>
                            `;
                            
                            // Ajouter un événement pour aller à cette page
                            noteItem.addEventListener('click', function() {
                                pageNum = parseInt(note.page_number);
                                queueRenderPage(pageNum);
                                document.getElementById('noteText').value = note.note_text;
                                switchTab('notes');
                                
                                // Fermer le panneau sur mobile après sélection
                                if (isMobile) {
                                    setTimeout(() => toggleSidePanel(), 100);
                                }
                            });
                            
                            notesList.appendChild(noteItem);
                        });
                    } else {
                        console.error('Erreur lors du chargement des notes:', data.message);
                    }
                } catch (error) {
                    console.error('Erreur lors du parsing des notes:', error);
                }
            },
            error: function(error) {
                console.error('Erreur AJAX lors du chargement des notes:', error);
            }
        });
    }
    
    function loadNoteForPage(pageNumber) {
        // Réinitialiser la zone de texte
        document.getElementById('noteText').value = '';
        document.getElementById('existingNote').classList.add('hidden');
        currentNote = null;

        $.ajax({
            url: 'ajax/get_note_for_page.php',
            type: 'GET',
            data: {
                book_id: bookId,
                page_number: pageNumber
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    if (data.note) {
                        // Afficher la note existante
                        document.getElementById('noteText').value = data.note.note_text;
                        document.getElementById('existingNoteText').textContent = data.note.note_text;
                        document.getElementById('noteDate').textContent = 'Dernière modification: ' + formatDate(data.note.updated_at || data.note.created_at);
                        document.getElementById('existingNote').classList.remove('hidden');
                        currentNote = data.note;
                    } else {
                        // Pas de note existante pour cette page
                        document.getElementById('noteText').value = '';
                        document.getElementById('existingNote').classList.add('hidden');
                        currentNote = null;
                    }
                } else {
                    console.error('Erreur serveur:', data.message);
                    showAlert('error', 'Erreur lors du chargement de la note');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Réponse brute:', xhr.responseText);
                showAlert('error', 'Erreur serveur lors du chargement de la note');
            }
        });
    }
    
    function saveNote() {
        const noteText = document.getElementById('noteText').value.trim();
        if (!noteText) {
            showAlert('error', 'Veuillez écrire une note avant de l\'enregistrer.');
            return;
        }

        // Montrer un indicateur de chargement
        const saveButton = document.getElementById('saveNote');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Enregistrement...';
        saveButton.disabled = true;

        $.ajax({
            url: 'ajax/save_note.php',
            type: 'POST',
            data: {
                book_id: bookId,
                page_number: pageNum,
                note_text: noteText,
                note_id: currentNote ? currentNote.id : null
            },
            dataType: 'json',
            success: function(data) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                
                if (data.success) {
                    showAlert('success', data.message || 'Note enregistrée avec succès !');
                    loadNotes();
                    loadNoteForPage(pageNum);
                    
                    // Fermer le panneau sur mobile après sauvegarde
                    if (isMobile) {
                        setTimeout(() => toggleSidePanel(), 1000);
                    }
                } else {
                    showAlert('error', data.message || 'Erreur lors de l\'enregistrement');
                    console.error('Réponse serveur:', data);
                }
            },
            error: function(xhr, status, error) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                showAlert('error', 'Erreur serveur: ' + status);
                console.error('AJAX Error:', status, error, xhr.responseText);
            }
        });
    }
    
    // Gestion des marque-pages - optimisé pour le responsive
    function loadBookmarks() {
        // Afficher un indicateur de chargement
        const bookmarksList = document.getElementById('bookmarksList');
        bookmarksList.innerHTML = '<div class="text-center py-3 text-sm"><i class="fas fa-spinner fa-spin mr-1"></i>Chargement...</div>';
        
        $.ajax({
            url: 'ajax/get_bookmarks.php',
            type: 'GET',
            data: {
                book_id: bookId
            },
            success: function(response) {
                let data;
                let success = false;
                
                // Traiter la réponse de manière robuste
                try {
                    // Si c'est une chaîne, essayer de la parser
                    if (typeof response === 'string') {
                        data = JSON.parse(response.trim());
                    } else {
                        // Sinon jQuery l'a déjà parsée
                        data = response;
                    }
                    success = data && data.success;
                } catch (error) {
                    console.warn("Erreur lors du parsing de la réponse des marque-pages:", error);
                    bookmarksList.innerHTML = '<div class="text-center text-red-500 py-3 text-sm">Erreur de format dans la réponse</div>';
                    return;
                }
                
                // Traiter le résultat
                if (success) {
                    bookmarksList.innerHTML = ''; // Vider la liste
                    
                    if (data.bookmarks && data.bookmarks.length > 0) {
                        data.bookmarks.forEach(bookmark => {
                            const bookmarkItem = document.createElement('div');
                            bookmarkItem.className = 'bookmark-item p-2 mb-2 bg-gray-100 rounded border text-sm';
                            bookmarkItem.innerHTML = `
                                <div class="flex justify-between items-center">
                                    <div class="truncate max-w-[60%]">
                                        <strong>${bookmark.bookmark_name || 'Sans nom'}</strong>
                                        <div class="text-xs">Page ${bookmark.page_number}</div>
                                    </div>
                                    <div class="flex items-center">
                                        <button class="goto-bookmark p-1 bg-blue-500 text-white rounded mr-1" 
                                                data-page="${bookmark.page_number}">
                                            <i class="fas fa-arrow-right"></i>
                                        </button>
                                        <button class="delete-bookmark p-1 bg-red-500 text-white rounded" 
                                                data-id="${bookmark.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                            
                            // Gestion des événements
                            const gotoBtn = bookmarkItem.querySelector('.goto-bookmark');
                            if (gotoBtn) {
                                gotoBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const page = parseInt(this.getAttribute('data-page'));
                                    if (!isNaN(page) && page > 0) {
                                        pageNum = page;
                                        queueRenderPage(pageNum);
                                        
                                        // Fermer le panneau sur mobile après navigation
                                        if (isMobile) {
                                            setTimeout(() => toggleSidePanel(), 100);
                                        }
                                    }
                                });
                            }
                            
                            const deleteBtn = bookmarkItem.querySelector('.delete-bookmark');
                            if (deleteBtn) {
                                deleteBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const id = this.getAttribute('data-id');
                                    if (id) {
                                        this.closest('.bookmark-item').classList.add('opacity-50');
                                        deleteBookmark(id);
                                    }
                                });
                            }
                            
                            bookmarksList.appendChild(bookmarkItem);
                        });
                    } else {
                        bookmarksList.innerHTML = '<div class="text-center text-gray-500 py-3 text-sm">Aucun marque-page</div>';
                    }
                } else {
                    bookmarksList.innerHTML = '<div class="text-center text-red-500 py-3 text-sm">Erreur lors du chargement des marque-pages</div>';
                    console.error('Erreur serveur:', data ? data.message : 'Réponse invalide');
                }
            },
            error: function(xhr, status, error) {
                bookmarksList.innerHTML = '<div class="text-center text-red-500 py-3 text-sm">Erreur lors du chargement des marque-pages</div>';
                console.error('AJAX Error:', status, error);
                console.error('Réponse brute:', xhr.responseText);
            }
        });
    }

    function saveBookmark() {
        const pageNumber = pageNum;
        const bookmarkName = document.getElementById('bookmarkName').value.trim();
        
        // Montrer un indicateur de chargement
        const saveButton = document.getElementById('saveBookmark');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Enregistrement...';
        saveButton.disabled = true;

        $.ajax({
            url: 'ajax/save_bookmark.php',
            type: 'POST',
            data: {
                book_id: bookId,
                page_number: pageNumber,
                bookmark_name: bookmarkName
            },
            dataType: 'json',
            success: function(data) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                
                if (data.success) {
                    showAlert('success', data.message || 'Marque-page enregistré avec succès !');
                    document.getElementById('bookmarkName').value = '';
                    loadBookmarks();
                    
                    // Fermer le panneau sur mobile après sauvegarde
                    if (isMobile) {
                        setTimeout(() => toggleSidePanel(), 1000);
                    }
                } else {
                    showAlert('error', data.message || 'Erreur lors de l\'enregistrement');
                    console.error('Réponse serveur:', data);
                }
            },
            error: function(xhr, status, error) {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                showAlert('error', 'Erreur serveur: ' + status);
                console.error('AJAX Error:', status, error);
                console.error('Réponse brute:', xhr.responseText);
            }
        });
    }
    
    function deleteBookmark(bookmarkId) {
        if (!confirm('Voulez-vous vraiment supprimer ce marque-page ?')) {
            return;
        }
        
        $.ajax({
            url: 'ajax/delete_bookmark.php',
            type: 'POST',
            data: {
                bookmark_id: bookmarkId
            },
            success: function(response) {
                // Approche robuste pour détecter le succès même avec des erreurs de parsing
                let success = false;
                
                try {
                    // Essayer de nettoyer et parser la réponse comme JSON
                    if (typeof response === 'string') {
                        const cleanResponse = response.trim();
                        const data = JSON.parse(cleanResponse);
                        success = data.success;
                    } else if (typeof response === 'object') {
                        // Si jQuery a déjà parsé la réponse
                        success = response.success;
                    }
                } catch (error) {
                    // Si le parsing échoue, chercher des indications de succès dans la chaîne brute
                    if (typeof response === 'string' && response.includes('"success":true')) {
                        success = true;
                    }
                    console.warn("Erreur de parsing, mais vérification manuelle effectuée:", error);
                }
                
                // Que ce soit parsé ou non, si nous détectons un succès, montrer un message positif
                if (success) {
                    showAlert('success', 'Marque-page supprimé avec succès !');
                    loadBookmarks();
                } else {
                    console.error('Réponse problématique:', response);
                    showAlert('error', 'Une erreur est survenue lors de la suppression du marque-page.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', status, error);
                console.error('Réponse brute:', xhr.responseText);
                showAlert('error', 'Une erreur est survenue lors de la suppression du marque-page.');
            }
        });
    }
    
    // Gestion de la notation du livre
    function showRateModal() {
        document.getElementById('rateModal').classList.remove('hidden');
        
        // Fermer tous les menus mobiles
        if (isMobile) {
            document.getElementById('mobileActionsMenu').classList.add('hidden');
        }
    }
    
    function hideRateModal() {
        document.getElementById('rateModal').classList.add('hidden');
    }
    
    function setRating(rating) {
        document.getElementById('selectedRating').value = rating;
        highlightStars(rating);
    }
    
    function highlightStars(rating) {
        document.querySelectorAll('.star-rating').forEach((star, index) => {
            if (index < rating) {
                star.classList.add('text-yellow-400');
                star.classList.remove('text-gray-300');
            } else {
                star.classList.add('text-gray-300');
                star.classList.remove('text-yellow-400');
            }
        });
    }
    
    function submitRating() {
        const rating = parseInt(document.getElementById('selectedRating').value);
        const comment = document.getElementById('ratingComment').value.trim();
        
        if (rating === 0) {
            alert('Veuillez sélectionner une note entre 1 et 5 étoiles.');
            return;
        }
        
        // Montrer un indicateur de chargement
        const submitButton = document.getElementById('submitRating');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Envoi...';
        submitButton.disabled = true;

        $.ajax({
            url: 'ajax/save_rating.php',
            type: 'POST',
            data: {
                book_id: bookId,
                rating: rating,
                comment_text: comment
            },
            success: function(response) {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
                
                let success = false;
                
                // Essayer différentes approches pour détecter le succès
                try {
                    // Si c'est une chaîne, essayer de parser comme JSON
                    if (typeof response === 'string') {
                        const data = JSON.parse(response.trim());
                        success = data.success;
                        
                        if (success) {
                            showAlert('success', data.message || 'Merci pour votre notation !');
                            hideRateModal();
                            
                            // Informer l'utilisateur que la page va être rechargée
                            setTimeout(function() {
                                location.reload(); // Recharger pour afficher la nouvelle note
                            }, 1500);
                        } else {
                            showAlert('error', data.message || 'Erreur lors de l\'enregistrement');
                        }
                    } else if (typeof response === 'object') {
                        // Si jQuery a déjà parsé la réponse comme objet
                        success = response.success;
                        
                        if (success) {
                            showAlert('success', response.message || 'Merci pour votre notation !');
                            hideRateModal();
                            
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert('error', response.message || 'Erreur lors de l\'enregistrement');
                        }
                    }
                } catch (error) {
                    console.warn('Erreur de parsing JSON:', error);
                    
                    // Vérifier si la réponse contient des indications de succès
                    if (typeof response === 'string' && response.includes('"success":true')) {
                        showAlert('success', 'Merci pour votre notation !');
                        hideRateModal();
                        
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        console.error('Réponse problématique:', response);
                        showAlert('error', 'Erreur lors de l\'enregistrement. Veuillez réessayer.');
                    }
                }
            },
            error: function(xhr, status, error) {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
                
                console.error('AJAX Error:', status, error);
                console.error('Réponse brute:', xhr.responseText);
                
                // Dernière tentative - vérifier si l'opération a réussi malgré l'erreur AJAX
                if (xhr.responseText && xhr.responseText.includes('"success":true')) {
                    showAlert('success', 'Merci pour votre notation !');
                    hideRateModal();
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('error', 'Erreur lors de l\'enregistrement. Veuillez réessayer.');
                }
            }
        });
    }
    
    // Sauvegarder la progression de lecture - version simplifiée
    function saveProgress(pageNumber) {
        // On peut utiliser un debounce ici pour éviter trop d'appels au serveur
        clearTimeout(window.saveProgressTimeout);
        window.saveProgressTimeout = setTimeout(() => {
            $.ajax({
                url: 'ajax/save_progress.php',
                type: 'POST',
                data: {
                    book_id: bookId,
                    page_number: pageNumber
                },
                success: function(response) {
                    console.log('Progression sauvegardée');
                },
                error: function(error) {
                    console.error('Erreur lors de la sauvegarde de la progression', error);
                }
            });
        }, 500); // Attendre 500ms avant de sauvegarder pour éviter trop de requêtes
    }
    
    // Fonctions utilitaires
    // Fonctions utilitaires
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
  
    // Affichage des messages d'alerte - optimisé pour le responsive
    function showAlert(type, message) {
        // Supprimer les alertes existantes
        const existingAlerts = document.querySelectorAll('.alert-message');
        existingAlerts.forEach(alert => alert.remove());
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed z-[9999] alert-message ${
            type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
        }`;
        
        // Positionnement différent selon mobile ou desktop
        if (isMobile) {
            alertDiv.classList.add('bottom-4', 'left-4', 'right-4', 'p-3', 'rounded-lg', 'shadow-lg');
        } else {
            alertDiv.classList.add('top-4', 'right-4', 'p-4', 'rounded-lg', 'shadow-lg');
        }
        
        // Ajouter une icône
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-2 text-lg"></i>
                <span class="${isMobile ? 'text-sm' : 'text-base'} font-medium">${message}</span>
            </div>
        `;
        
        // Ajouter un peu de style pour la visibilité
        if (!isMobile) {
            alertDiv.style.minWidth = '300px';
        }
        alertDiv.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
        
        document.body.appendChild(alertDiv);
        
        // Animation de fondu
        setTimeout(() => {
            alertDiv.style.transition = 'opacity 500ms';
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                alertDiv.remove();
            }, 500);
        }, 3000);
    }

    // Solution pour corriger les changements de page non désirés lors de l'interaction avec les formulaires
    (function() {
        // Sauvegarder les fonctions originales
        var originalSaveNote = saveNote;
        var originalSaveBookmark = saveBookmark;
        var originalSubmitRating = submitRating;
        
        // Remplacer saveNote pour préserver la page actuelle
        saveNote = function() {
            var currentPage = pageNum;
            var result = originalSaveNote.apply(this, arguments);
            
            // Revenir à la page d'origine après l'action
            setTimeout(function() {
                if (pageNum !== currentPage) {
                    pageNum = currentPage;
                    queueRenderPage(currentPage);
                }
            }, 10);
            
            return result;
        };
        
        // Remplacer saveBookmark pour préserver la page actuelle
        saveBookmark = function() {
            var currentPage = pageNum;
            var result = originalSaveBookmark.apply(this, arguments);
            
            // Revenir à la page d'origine après l'action
            setTimeout(function() {
                if (pageNum !== currentPage) {
                    pageNum = currentPage;
                    queueRenderPage(currentPage);
                }
            }, 10);
            
            return result;
        };
        
        // Remplacer submitRating pour préserver la page actuelle
        submitRating = function() {
            var currentPage = pageNum;
            var result = originalSubmitRating.apply(this, arguments);
            
            // Revenir à la page d'origine après l'action
            setTimeout(function() {
                if (pageNum !== currentPage) {
                    pageNum = currentPage;
                    queueRenderPage(currentPage);
                }
            }, 10);
            
            return result;
        };
        
        // Protéger les champs de texte des événements de navigation clavier
        document.addEventListener('DOMContentLoaded', function() {
            var textInputs = document.querySelectorAll('input, textarea');
            textInputs.forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    // Empêcher que les touches de navigation soient capturées
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || 
                        e.key === 'PageUp' || e.key === 'PageDown' || e.key === ' ') {
                        e.stopPropagation();
                    }
                }, true);
            });
        });
    })();

    // Gestionnaire d'événements pour les appareils tactiles
    document.addEventListener('DOMContentLoaded', function() {
        // Détection du support tactile
        const hasTouchSupport = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        if (hasTouchSupport) {
            let touchStartX = 0;
            let touchEndX = 0;
            
            // Ajouter les listeners pour le swipe
            const pdfContainer = document.getElementById('pdfContainer');
            
            pdfContainer.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            pdfContainer.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
            
            // Gérer le swipe pour la navigation dans les pages
            function handleSwipe() {
                const minSwipeDistance = 50;
                const swipeDistance = touchEndX - touchStartX;
                
                if (swipeDistance > minSwipeDistance) {
                    // Swipe vers la droite -> page précédente
                    onPrevPage();
                } else if (swipeDistance < -minSwipeDistance) {
                    // Swipe vers la gauche -> page suivante
                    onNextPage();
                }
            }
        }
    });



    // Ajouter cette fonction après l'initialisation du document
function setupDarkMode() {
    // Détecter le mode sombre
    function isDarkMode() {
        return document.documentElement.classList.contains('dark') || 
               localStorage.getItem('theme') === 'dark' ||
               (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }
    
    // Appliquer le mode sombre
    function applyDarkMode() {
        if (isDarkMode()) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
    
    // Appliquer au chargement
    applyDarkMode();
    
    // Écouter les changements de thème
    window.addEventListener('storage', function(e) {
        if (e.key === 'theme') {
            applyDarkMode();
        }
    });
}

// Appelez cette fonction au chargement du document
document.addEventListener('DOMContentLoaded', function() {
    setupDarkMode();
    // Reste de votre code d'initialisation...
});
</script>

<style>
    /* Animation de tournage de page */
    @keyframes flip {
        0% { transform: rotateY(0) scale(0.5); opacity: 0; }
        50% { transform: rotateY(90deg) scale(1); opacity: 0.7; }
        100% { transform: rotateY(180deg) scale(0.5); opacity: 0; }
    }
    
    /* Effet de ligne clamp pour les notes */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Style pour le conteneur PDF */
    #pdfContainer {
        scrollbar-width: thin;
        scrollbar-color: #3b82f6 #e5e7eb;
    }
    
    #pdfContainer::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    
    #pdfContainer::-webkit-scrollbar-track {
        background: #e5e7eb;
    }
    
    #pdfContainer::-webkit-scrollbar-thumb {
        background-color: #3b82f6;
        border-radius: 4px;
    }
    
    /* Style pour les onglets */
    .tab-btn {
        transition: all 0.2s ease;
    }
    
    .tab-btn:hover:not(.active) {
        color: #1e40af;
    }
    
    .pdfViewer {
    padding: 0 !important; /* Suppression des paddings */
    min-height: calc(100vh - 64px); /* Ajuster selon la hauteur exacte des barres */
    }


    #viewer canvas {
    max-width: 100%;
    height: auto !important;
    margin: 0 auto; /* Centre le contenu */
    }
    /* Styles pour le responsive */
    @media (max-width: 768px) {
        /* Ajustements spécifiques pour les appareils mobiles */
        
        .pdfViewer {
        min-height: calc(10vh - 58px); /* Ajuster selon la hauteur exacte des barres mobiles */
        }
        
        #viewer canvas {
            max-width: 100%;
            height: auto !important;
        }
        
        #pdfContainer {
        padding: 0;
       }
        /* Optimisation des contrôles sur mobile */
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 0.5rem 0;
        }
        
        /* Transition fluide pour le panneau latéral */
        #sidePanel {
            transition: transform 0.3s ease;
        }

        #viewer {
        padding-top: 0;
        padding-bottom: 0;
        }
    }
</style>

    
</body>