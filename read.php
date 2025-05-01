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

<div class="flex h-screen bg-gray-100">
    <!-- Lecteur de PDF avec effet de page -->
    <div class="flex-grow flex flex-col">
        <!-- Barre de navigation supérieure -->
        <div class="bg-blue-800 text-white p-4 flex justify-between items-center shadow-lg">
            <div class="flex items-center space-x-4">
                <a href="library.php" class="text-white hover:text-blue-200">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-xl font-bold truncate max-w-md"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="bg-blue-600 px-2 py-1 rounded"><?php echo htmlspecialchars($book['category_name'] ?? 'Non catégorisé'); ?></span>
                        <span><?php echo $book['author'] ?? 'Auteur inconnu'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Affichage de la note moyenne -->
                <div class="flex items-center bg-blue-700 px-3 py-1 rounded-full">
                    <i class="fas fa-star text-yellow-300 mr-1"></i>
                    <span><?php echo $average_rating; ?>/5</span>
                </div>
                
                <!-- Bouton pour noter le livre (si pas encore noté) -->
                <?php if (!$has_rated): ?>
                <button id="rateBookBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-full flex items-center">
                    <i class="fas fa-star mr-2"></i> Noter ce livre
                </button>
                <?php else: ?>
                <div class="bg-green-500 text-white px-4 py-2 rounded-full flex items-center">
                    <i class="fas fa-check mr-2"></i> Noté (<?php echo $user_rating; ?>/5)
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Barre de contrôle de lecture -->
        <div class="bg-white p-3 flex justify-between items-center border-b shadow-sm">
            <div class="flex items-center space-x-3">
                <button id="btnPrev" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded flex items-center">
                    <i class="fas fa-chevron-left mr-2"></i> Précédente
                </button>
                
                <div class="flex items-center bg-gray-100 px-4 py-2 rounded border">
                    <span id="pageInfo">
                        Page <span id="currentPage"><?php echo $last_page; ?></span> / <span id="totalPages">...</span>
                    </span>
                </div>
                
                <button id="btnNext" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded flex items-center">
                    Suivante <i class="fas fa-chevron-right ml-2"></i>
                </button>
            </div>
            
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <button id="zoomInBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 rounded">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button id="zoomOutBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 rounded ml-1">
                        <i class="fas fa-search-minus"></i>
                    </button>
                </div>
                
                <button id="toggleNotes" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center">
                    <i class="fas fa-book mr-2"></i> Notes
                </button>
                
                <button id="addBookmarkBtn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded flex items-center">
                    <i class="fas fa-bookmark mr-2"></i> Marque-page
                </button>
            </div>
        </div>
        
        <!-- Conteneur du PDF -->
        <div id="pdfContainer" class="flex-grow bg-gray-200 relative overflow-auto">
            <div id="viewer" class="pdfViewer flex justify-center items-center p-4"></div>
            
            <!-- Animation de tournage de page -->
            <div id="pageFlip" class="hidden absolute inset-0 bg-black bg-opacity-30 flex justify-center items-center z-10">
                <div class="bg-white w-32 h-48 rounded shadow-xl transform transition-all duration-300"></div>
            </div>
        </div>
    </div>
    
    <!-- Panneau latéral (notes et marque-pages) -->
    <div id="sidePanel" class="w-96 bg-white shadow-lg flex flex-col border-l">
        <!-- En-tête du panneau -->
        <div class="bg-blue-800 text-white p-4 flex justify-between items-center">
            <h3 class="font-bold text-lg" id="panelTitle">Mes Notes</h3>
            <button id="closePanel" class="hover:text-blue-200 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Onglets -->
        <div class="flex border-b">
            <button class="tab-btn active py-3 px-4 font-medium text-blue-800 border-b-2 border-blue-800" data-tab="notes">
                <i class="fas fa-book mr-2"></i>Notes
            </button>
            <button class="tab-btn py-3 px-4 font-medium text-gray-500" data-tab="bookmarks">
                <i class="fas fa-bookmark mr-2"></i>Marque-pages
            </button>
            <button class="tab-btn py-3 px-4 font-medium text-gray-500" data-tab="info">
                <i class="fas fa-info-circle mr-2"></i>Infos
            </button>
        </div>
        
        <!-- Contenu des onglets -->
        <div class="flex-grow overflow-y-auto">
            <!-- Onglet Notes -->
            <div id="notesTab" class="tab-content p-4">
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Page actuelle</label>
                    <div id="currentPageForNote" class="bg-gray-100 p-2 rounded text-center font-medium"><?php echo $last_page; ?></div>
                </div>
                
                <div class="mb-4">
                    <label for="noteText" class="block text-gray-700 font-bold mb-2">Votre note</label>
                    <textarea id="noteText" rows="5" class="w-full p-3 border rounded resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div id="existingNote" class="mb-4 hidden">
                    <div class="bg-blue-50 p-3 rounded border border-blue-200">
                        <div class="font-bold text-blue-800 mb-2">Note existante:</div>
                        <div id="existingNoteText" class="text-gray-700"></div>
                        <div class="text-xs text-gray-500 mt-2" id="noteDate"></div>
                    </div>
                </div>
                
                <button id="saveNote" class="w-full bg-blue-700 hover:bg-blue-800 text-white py-3 px-4 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-save mr-2"></i>Enregistrer la note
                </button>
                
                <div class="mt-6">
                    <h4 class="font-bold mb-3 text-gray-700 border-b pb-2">Notes précédentes</h4>
                    <div id="notesList" class="space-y-3 max-h-64 overflow-y-auto">
                        <div class="text-center text-gray-500 italic py-4">Chargement des notes...</div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Marque-pages -->
            <div id="bookmarksTab" class="tab-content p-4 hidden">
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Page actuelle</label>
                    <div class="flex">
                        <div id="currentPageForBookmark" class="bg-gray-100 p-2 rounded text-center font-medium flex-grow"><?php echo $last_page; ?></div>
                        <input type="text" id="bookmarkName" class="ml-2 p-2 border rounded flex-grow" placeholder="Nom du marque-page">
                    </div>
                </div>
                
                <button id="saveBookmark" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 px-4 rounded-lg font-medium transition duration-200 mb-4">
                    <i class="fas fa-plus mr-2"></i>Ajouter un marque-page
                </button>
                
                <div>
                    <h4 class="font-bold mb-3 text-gray-700 border-b pb-2">Marque-pages enregistrés</h4>
                    <div id="bookmarksList" class="space-y-2 max-h-64 overflow-y-auto">
                        <div class="text-center text-gray-500 italic py-4">Chargement des marque-pages...</div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Infos -->
            <div id="infoTab" class="tab-content p-4 hidden">
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Couverture du livre" class="w-full h-auto rounded shadow-md mb-4">
                    <h4 class="font-bold text-lg mb-2"><?php echo htmlspecialchars($book['title']); ?></h4>
                    <p class="text-gray-600 mb-1"><span class="font-medium">Auteur:</span> <?php echo htmlspecialchars($book['author'] ?? 'Inconnu'); ?></p>
                    <p class="text-gray-600 mb-1"><span class="font-medium">Éditeur:</span> <?php echo htmlspecialchars($book['publisher'] ?? 'Inconnu'); ?></p>
                    <p class="text-gray-600 mb-1"><span class="font-medium">Année:</span> <?php echo htmlspecialchars($book['publication_year'] ?? 'Inconnue'); ?></p>
                    <p class="text-gray-600 mb-1"><span class="font-medium">Pages:</span> <?php echo htmlspecialchars($book['pages_count'] ?? 'Inconnu'); ?></p>
                    
                    <div class="mt-3">
                        <h5 class="font-bold mb-1">Description</h5>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($book['description'] ?? 'Aucune description disponible.')); ?></p>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="font-bold mb-2">Note moyenne</h5>
                        <div class="flex items-center">
                            <div class="flex mr-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($average_rating) ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-gray-700"><?php echo $average_rating; ?> (sur 5)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour noter le livre -->
<div id="rateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-96 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Noter ce livre</h3>
            <button id="closeRateModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-4">
            <p class="text-gray-700 mb-3">Quelle note donnez-vous à "<?php echo htmlspecialchars($book['title']); ?>" ?</p>
            <div class="flex justify-center mb-3">
                <div class="rating-stars flex space-x-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star text-3xl cursor-pointer text-gray-300 hover:text-yellow-400 star-rating" data-rating="<?php echo $i; ?>"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <input type="hidden" id="selectedRating" value="0">
        </div>
        
        <div class="mb-4">
            <label for="ratingComment" class="block text-gray-700 font-medium mb-2">Commentaire (optionnel)</label>
            <textarea id="ratingComment" rows="3" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button id="cancelRating" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-100">Annuler</button>
            <button id="submitRating" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Envoyer</button>
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
    let isPanelOpen = true;
    
    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        canvas = document.createElement('canvas');
        document.getElementById('viewer').appendChild(canvas);
        ctx = canvas.getContext('2d');
        
        // Charger le PDF
        loadPDF('<?php echo $book_path; ?>');
        
        // Événements des boutons
        document.getElementById('btnPrev').addEventListener('click', onPrevPage);
        document.getElementById('btnNext').addEventListener('click', onNextPage);
        document.getElementById('toggleNotes').addEventListener('click', toggleSidePanel);
        document.getElementById('closePanel').addEventListener('click', toggleSidePanel);
        document.getElementById('saveNote').addEventListener('click', saveNote);
        document.getElementById('addBookmarkBtn').addEventListener('click', showBookmarkTab);
        document.getElementById('saveBookmark').addEventListener('click', saveBookmark);
        document.getElementById('zoomInBtn').addEventListener('click', zoomIn);
        document.getElementById('zoomOutBtn').addEventListener('click', zoomOut);
        
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
            if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                onPrevPage();
            } else if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
                onNextPage();
            }
        });
    });
    
    // Charger le PDF
    function loadPDF(url) {
        pdfjsLib.getDocument(url).promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('totalPages').textContent = pdf.numPages;
            
            // Rendre la page initiale
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('Erreur lors du chargement du PDF:', error);
            alert('Une erreur est survenue lors du chargement du livre. Veuillez réessayer.');
        });
    }
    
    // Rendre une page
    function renderPage(num) {
        pageRendering = true;
        
        // Mise à jour des éléments d'interface
        document.getElementById('currentPage').textContent = num;
        document.getElementById('currentPageForNote').textContent = num;
        document.getElementById('currentPageForBookmark').textContent = num;
        
        // Obtenir la page du PDF
        pdfDoc.getPage(num).then(function(page) {
            const viewport = page.getViewport({ scale });
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            // Animation de tournage de page
            showPageFlipAnimation();
            
            // Rendu de la page
            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            
            page.render(renderContext).promise.then(function() {
                pageRendering = false;
                
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
        }, 500);
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
        scale += 0.25;
        queueRenderPage(pageNum);
    }
    
    function zoomOut() {
        if (scale > 0.5) {
            scale -= 0.25;
            queueRenderPage(pageNum);
        }
    }
    
    // Gestion du panneau latéral
    function toggleSidePanel() {
        const sidePanel = document.getElementById('sidePanel');
        sidePanel.classList.toggle('hidden');
        isPanelOpen = !isPanelOpen;
    }
    
    function showBookmarkTab() {
        if (!isPanelOpen) {
            toggleSidePanel();
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
    
    // Gestion des notes
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
                            notesList.innerHTML = '<div class="text-center text-gray-500 italic py-4">Aucune note pour ce livre</div>';
                            return;
                        }
                        
                        notesList.innerHTML = '';
                        data.notes.forEach(note => {
                            const noteItem = document.createElement('div');
                            noteItem.className = 'p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 cursor-pointer transition';
                            noteItem.innerHTML = `
                                <div class="flex justify-between items-start">
                                    <div class="font-bold text-blue-700">Page ${note.page_number}</div>
                                    <div class="text-xs text-gray-500">${formatDate(note.created_at)}</div>
                                </div>
                                <div class="mt-2 text-gray-700 line-clamp-2">${note.note_text}</div>
                            `;
                            
                            // Ajouter un événement pour aller à cette page
                            noteItem.addEventListener('click', function() {
                                pageNum = parseInt(note.page_number);
                                queueRenderPage(pageNum);
                                document.getElementById('noteText').value = note.note_text;
                                switchTab('notes');
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
        dataType: 'json', // Important : forcer jQuery à attendre du JSON
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
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
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
        dataType: 'json', // Important : forcer jQuery à attendre du JSON
        success: function(data) {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
            
            if (data.success) {
                showAlert('success', data.message || 'Note enregistrée avec succès !');
                loadNotes();
                loadNoteForPage(pageNum);
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
            
            // Afficher plus de détails sur l'erreur
            try {
                const responseObj = JSON.parse(xhr.responseText);
                console.error('Détails de l\'erreur:', responseObj);
            } catch (e) {
                console.error('Réponse brute:', xhr.responseText);
            }
        }
    });
}

    
    // Gestion des marque-pages
    

    function loadBookmarks() {
    // Afficher un indicateur de chargement
    const bookmarksList = document.getElementById('bookmarksList');
    bookmarksList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Chargement...</div>';
    
    $.ajax({
        url: 'ajax/get_bookmarks.php',
        type: 'GET',
        data: {
            book_id: bookId
        },
        // Important: ne pas forcer 'dataType: "json"' pour éviter les erreurs strictes
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
                // Réinitialiser la vue avec un message d'erreur
                bookmarksList.innerHTML = '<div class="text-center text-red-500 py-4">Erreur de format dans la réponse</div>';
                return;
            }
            
            // Traiter le résultat
            if (success) {
                bookmarksList.innerHTML = ''; // Vider la liste
                
                if (data.bookmarks && data.bookmarks.length > 0) {
                    data.bookmarks.forEach(bookmark => {
                        const bookmarkItem = document.createElement('div');
                        bookmarkItem.className = 'bookmark-item p-3 mb-2 bg-gray-100 rounded border';
                        bookmarkItem.innerHTML = `
                            <div class="flex justify-between items-center">
                                <div>
                                    <strong>${bookmark.bookmark_name || 'Sans nom'}</strong>
                                    <div class="text-sm">Page ${bookmark.page_number}</div>
                                </div>
                                <div>
                                    <button class="goto-bookmark px-2 py-1 bg-blue-500 text-white rounded mr-1" 
                                            data-page="${bookmark.page_number}">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                    <button class="delete-bookmark px-2 py-1 bg-red-500 text-white rounded" 
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
                                }
                            });
                        }
                        
                        const deleteBtn = bookmarkItem.querySelector('.delete-bookmark');
                        if (deleteBtn) {
                            deleteBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                const id = this.getAttribute('data-id');
                                if (id) {
                                    // Ajouter une classe visuelle pendant la suppression
                                    this.closest('.bookmark-item').classList.add('opacity-50');
                                    deleteBookmark(id);
                                }
                            });
                        }
                        
                        bookmarksList.appendChild(bookmarkItem);
                    });
                } else {
                    bookmarksList.innerHTML = '<div class="text-center text-gray-500 py-4">Aucun marque-page</div>';
                }
            } else {
                bookmarksList.innerHTML = '<div class="text-center text-red-500 py-4">Erreur lors du chargement des marque-pages</div>';
                console.error('Erreur serveur:', data ? data.message : 'Réponse invalide');
            }
        },
        error: function(xhr, status, error) {
            bookmarksList.innerHTML = '<div class="text-center text-red-500 py-4">Erreur lors du chargement des marque-pages</div>';
            console.error('AJAX Error:', status, error);
            console.error('Réponse brute:', xhr.responseText);
        }
    });
}

    


function editBookmark(bookmark) {
    // Remplir le formulaire
    document.getElementById('currentPageForBookmark').textContent = bookmark.page_number;
    document.getElementById('bookmarkName').value = bookmark.bookmark_name || '';
    
    // Changer le bouton "Ajouter" en "Mettre à jour"
    const saveBtn = document.getElementById('saveBookmark');
    saveBtn.textContent = 'Mettre à jour';
    saveBtn.onclick = function() {
        updateBookmark(bookmark.id);
    };
}

function updateBookmark(bookmarkId) {
    const newName = document.getElementById('bookmarkName').value.trim();
    
    $.ajax({
        url: 'ajax/update_bookmark.php',
        type: 'POST',
        data: {
            bookmark_id: bookmarkId,
            bookmark_name: newName
        },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                showAlert('success', 'Marque-page mis à jour');
                loadBookmarks();
                resetBookmarkForm();
            } else {
                showAlert('error', data.message);
            }
        }
    });
}

function resetBookmarkForm() {
    document.getElementById('bookmarkName').value = '';
    const saveBtn = document.getElementById('saveBookmark');
    saveBtn.textContent = 'Ajouter un marque-page';
    saveBtn.onclick = function() {
        saveBookmark();
    };
}


function saveBookmark() {
    const pageNumber = pageNum;
    const bookmarkName = document.getElementById('bookmarkName').value.trim();
    
    // Montrer un indicateur de chargement
    const saveButton = document.getElementById('saveBookmark');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
    saveButton.disabled = true;

    $.ajax({
        url: 'ajax/save_bookmark.php',
        type: 'POST',
        data: {
            book_id: bookId,
            page_number: pageNumber,
            bookmark_name: bookmarkName
        },
        dataType: 'json', // Important : forcer jQuery à attendre du JSON
        success: function(data) {
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
            
            if (data.success) {
                showAlert('success', data.message || 'Marque-page enregistré avec succès !');
                document.getElementById('bookmarkName').value = '';
                loadBookmarks();
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
            
            // Afficher plus de détails sur l'erreur
            try {
                console.error('Détails de l\'erreur:', xhr.responseText);
            } catch (e) {
                console.error('Erreur inattendue lors du traitement de la réponse');
            }
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
        // Ne pas spécifier dataType: 'json' pour éviter les erreurs strictes de parsing
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
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Envoi...';
    submitButton.disabled = true;

    $.ajax({
        url: 'ajax/save_rating.php',
        type: 'POST',
        data: {
            book_id: bookId,
            rating: rating,
            comment_text: comment
        },
        // Ne pas forcer dataType: 'json' pour éviter les erreurs strictes de parsing
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

    
    // Sauvegarder la progression de lecture
    function saveProgress(pageNumber) {
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
    }
    
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
  
    

function showAlert(type, message) {
    // Supprimer les alertes existantes pour éviter l'empilement
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-[9999] alert-message ${
        type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
    }`;
    
    // Ajouter une icône et améliorer le style
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-2 text-xl"></i>
            <span class="font-medium">${message}</span>
        </div>
    `;
    
    // Ajouter un peu de style pour la visibilité
    alertDiv.style.minWidth = '300px';
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
    
    // Journaliser également dans la console pour débogage
    if (type === 'success') {
        console.log('Succès:', message);
    } else {
        console.warn('Alerte:', message);
    }
}




// Solution simple pour corriger les changements de page non désirés
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
                console.log("Page restaurée après saveNote");
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
                console.log("Page restaurée après saveBookmark");
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
                console.log("Page restaurée après submitRating");
            }
        }, 10);
        
        return result;
    };
    
    // Protéger les champs de texte des événements de navigation clavier
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
    
    console.log("Solution anti-changement de page activée");
})();



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
        width: 8px;
        height: 8px;
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
</style>

<?php
// Ne pas inclure le footer standard pour la page de lecture
// pour maximiser l'espace de lecture
?>