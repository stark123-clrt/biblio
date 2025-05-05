<?php
// user/notes.php - Gestion des notes de l'utilisateur
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";

// Récupérer l'ID de l'utilisateur
$user_id = $_SESSION['user_id'];

// Paramètres de filtrage et tri
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Supprimer une note si demandé
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $note_id = intval($_GET['delete']);
    
    // Vérifier que la note appartient bien à l'utilisateur courant
    $check = $conn->prepare("SELECT id FROM notes WHERE id = :note_id AND user_id = :user_id");
    $check->bindParam(':note_id', $note_id, PDO::PARAM_INT);
    $check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check->execute();
    
    if ($check->rowCount() > 0) {
        $delete = $conn->prepare("DELETE FROM notes WHERE id = :note_id");
        $delete->bindParam(':note_id', $note_id, PDO::PARAM_INT);
        if ($delete->execute()) {
            $success_message = "La note a été supprimée avec succès.";
        } else {
            $error_message = "Une erreur est survenue lors de la suppression de la note.";
        }
    } else {
        $error_message = "Vous n'êtes pas autorisé à supprimer cette note.";
    }
}

// Récupérer les livres pour lesquels l'utilisateur a des notes
$stmt = $conn->prepare("SELECT DISTINCT b.id, b.title
                        FROM notes n
                        JOIN books b ON n.book_id = b.id
                        WHERE n.user_id = :user_id
                        ORDER BY b.title");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construire la requête SQL pour récupérer les notes
$sql = "SELECT n.*, b.title as book_title, b.cover_image
        FROM notes n
        JOIN books b ON n.book_id = b.id
        WHERE n.user_id = :user_id";
$params = [':user_id' => $user_id];

// Appliquer les filtres
if ($book_id > 0) {
    $sql .= " AND n.book_id = :book_id";
    $params[':book_id'] = $book_id;
}

if (!empty($search)) {
    $sql .= " AND (n.note_text LIKE :search OR b.title LIKE :search)";
    $params[':search'] = "%$search%";
}

// Appliquer le tri
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY n.created_at ASC";
        break;
    case 'book':
        $sql .= " ORDER BY b.title ASC, n.page_number ASC";
        break;
    case 'page':
        $sql .= " ORDER BY n.book_id ASC, n.page_number ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY n.created_at DESC";
        break;
}

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Mes Notes";
include "../includes/header.php";
?>

<style>
/* Styles pour le mode clair/sombre */
:root {
  --primary-light: #2563eb;
  --primary-dark: #1d4ed8;
  --bg-light: #f9fafb;
  --bg-dark: #111827;
  --card-light: #ffffff;
  --card-dark: #1f2937;
  --text-light: #374151;
  --text-dark: #e5e7eb;
  --border-light: #e5e7eb;
  --border-dark: #374151;
  --hover-light: #f3f4f6;
  --hover-dark: #374151;
  --success-light: #d1fae5;
  --success-dark: #064e3b;
  --success-text-light: #065f46;
  --success-text-dark: #10b981;
  --error-light: #fee2e2;
  --error-dark: #7f1d1d;
  --error-text-light: #b91c1c;
  --error-text-dark: #ef4444;
  --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.18);
  --shadow-hover-light: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  --shadow-hover-dark: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

body {
  transition: background-color 0.3s ease, color 0.3s ease;
}

/* Cards styles avec transitions */
.dark .bg-white {
  background-color: var(--card-dark) !important;
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.bg-white {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

/* Message de succès */
.bg-green-100 {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

.dark .bg-green-100 {
  background-color: var(--success-dark);
  border-color: var(--success-text-dark);
  color: var(--success-text-dark);
}

/* Message d'erreur */
.bg-red-100 {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

.dark .bg-red-100 {
  background-color: var(--error-dark);
  border-color: var(--error-text-dark);
  color: var(--error-text-dark);
}

/* Style des forms */
input, select, textarea {
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

.dark input, .dark select, .dark textarea {
  background-color: var(--bg-dark);
  color: var(--text-dark);
  border-color: var(--border-dark);
}

.dark input:focus, .dark select:focus, .dark textarea:focus {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
}

.dark input::placeholder {
  color: #6b7280;
}

/* Style amélioré des notes */
.note-card {
  transition: transform 0.2s ease, box-shadow 0.3s ease, background-color 0.3s ease;
  overflow: hidden; 
}

.note-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-hover-light);
}

.dark .note-card:hover {
  box-shadow: var(--shadow-hover-dark);
}

/* Amélioration du modal */
#editNoteModal {
  backdrop-filter: blur(4px);
  transition: opacity 0.3s ease;
}

.dark #editNoteModal .bg-white {
  background-color: var(--card-dark);
  color: var(--text-dark);
}

.dark #editNoteModal .text-gray-700 {
  color: #d1d5db;
}

.dark #editNoteModal .text-gray-400:hover {
  color: #9ca3af;
}

/* Améliorations pour les boutons */
.btn {
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.btn:after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 5px;
  height: 5px;
  background: rgba(255, 255, 255, 0.5);
  opacity: 0;
  border-radius: 100%;
  transform: scale(1, 1) translate(-50%);
  transform-origin: 50% 50%;
}

.btn:focus:not(:active)::after {
  animation: ripple 1s ease-out;
}

@keyframes ripple {
  0% {
    transform: scale(0, 0);
    opacity: 0.5;
  }
  20% {
    transform: scale(25, 25);
    opacity: 0.3;
  }
  100% {
    opacity: 0;
    transform: scale(40, 40);
  }
}

/* Style pour no-notes message */
.dark .text-gray-400 {
  color: #6b7280;
}

.dark .text-gray-600 {
  color: #9ca3af;
}

.dark .text-gray-700 {
  color: #d1d5db;
}

/* Text colors for dark mode */
.dark .text-blue-600 {
  color: #60a5fa;
}

.dark .text-blue-800 {
  color: #3b82f6;
}

.dark .text-indigo-600 {
  color: #818cf8;
}

.dark .text-indigo-800 {
  color: #6366f1;
}

.dark .text-red-600 {
  color: #f87171;
}

.dark .text-red-800 {
  color: #ef4444;
}

/* Modal animation */
#modalContent {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

/* Toast notification */
.toast-notification {
  transition: all 0.3s ease;
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white flex items-center">
        <i class="fas fa-sticky-note mr-3 text-blue-600 dark:text-blue-500"></i>
        Mes Notes
    </h1>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-4 dark:bg-green-900 dark:border-green-500 dark:text-green-200">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $success_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-500 dark:text-red-200">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Filtres et recherche -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8 dark:bg-gray-800 dark:border dark:border-gray-700">
        <form action="notes.php" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="book_id" class="block text-gray-700 font-medium mb-2 dark:text-gray-300">
                        <i class="fas fa-book mr-2"></i>Livre
                    </label>
                    <select id="book_id" name="book_id" 
                            class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="0">Tous les livres</option>
                        <?php foreach ($user_books as $book): ?>
                            <option value="<?php echo $book['id']; ?>" <?php echo $book_id == $book['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($book['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-gray-700 font-medium mb-2 dark:text-gray-300">
                        <i class="fas fa-sort mr-2"></i>Trier par
                    </label>
                    <select id="sort" name="sort" 
                            class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récentes</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciennes</option>
                        <option value="book" <?php echo $sort == 'book' ? 'selected' : ''; ?>>Titre du livre</option>
                        <option value="page" <?php echo $sort == 'page' ? 'selected' : ''; ?>>Numéro de page</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label for="search" class="block text-gray-700 font-medium mb-2 dark:text-gray-300">
                        <i class="fas fa-search mr-2"></i>Recherche
                    </label>
                    <div class="relative">
                        <input type="text" id="search" name="search" placeholder="Rechercher dans vos notes..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="w-full p-2 pl-4 pr-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:placeholder-gray-500">
                        <button type="submit" class="absolute right-2 top-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-2">
                <?php if ($book_id > 0 || !empty($search) || $sort != 'newest'): ?>
                    <a href="notes.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg transition-colors flex items-center dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                        <i class="fas fa-times mr-2"></i> Réinitialiser
                    </a>
                <?php endif; ?>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors btn flex items-center">
                    <i class="fas fa-filter mr-2"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
    
    <!-- Liste des notes -->
    <?php if (!empty($notes)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($notes as $note): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow note-card">
                    <div class="p-4 border-b flex justify-between items-center bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                        <div>
                            <h3 class="font-bold">
                                <a href="../book.php?id=<?php echo $note['book_id']; ?>" class="text-blue-600 hover:text-blue-800 transition-colors">
                                    <?php echo htmlspecialchars($note['book_title']); ?>
                                </a>
                            </h3>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="inline-flex items-center">
                                    <i class="fas fa-file-alt mr-1"></i> Page <?php echo $note['page_number']; ?>
                                </span>
                                <span class="mx-2">•</span>
                                <span class="inline-flex items-center">
                                    <i class="far fa-clock mr-1"></i> <?php echo date('d/m/Y à H:i', strtotime($note['created_at'])); ?>
                                </span>
                                <?php if ($note['created_at'] != $note['updated_at']): ?>
                                    <span class="ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full dark:bg-blue-900 dark:text-blue-300">
                                        modifiée
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <a href="../read.php?id=<?php echo $note['book_id']; ?>&page=<?php echo $note['page_number']; ?>" 
                               class="text-blue-600 hover:text-blue-800 transition-colors p-1 hover:bg-blue-50 rounded-full dark:hover:bg-blue-900" 
                               title="Voir dans le livre">
                                <i class="fas fa-book-open"></i>
                            </a>
                            <a href="#" class="edit-note text-indigo-600 hover:text-indigo-800 transition-colors p-1 hover:bg-indigo-50 rounded-full dark:hover:bg-indigo-900" 
                               title="Modifier" data-id="<?php echo $note['id']; ?>"
                               data-text="<?php echo htmlspecialchars($note['note_text']); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="notes.php?delete=<?php echo $note['id']; ?>" 
                               class="text-red-600 hover:text-red-800 transition-colors p-1 hover:bg-red-50 rounded-full dark:hover:bg-red-900" 
                               title="Supprimer"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette note ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-5 note-content dark:text-gray-300">
                        <div class="note-text">
                            <?php echo nl2br(htmlspecialchars($note['note_text'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center bg-white rounded-lg shadow-md p-8 dark:bg-gray-800">
            <div class="text-gray-400 text-6xl mb-4 empty-state-icon">
                <i class="fas fa-book"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2 dark:text-gray-300">Aucune note trouvée</h2>
            <p class="text-gray-600 mb-6 dark:text-gray-400">
                <?php if (!empty($search) || $book_id > 0): ?>
                    Essayez de modifier vos critères de recherche.
                <?php else: ?>
                    Vous n'avez pas encore ajouté de notes à vos livres.
                <?php endif; ?>
            </p>
            <a href="../library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg btn transition-all">
                <i class="fas fa-book-open mr-2"></i> Parcourir la bibliothèque
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de modification de note amélioré -->
<div id="editNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl dark:border dark:border-gray-700 transform transition-transform scale-95 opacity-0" id="modalContent">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold dark:text-gray-200">Modifier la note</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors dark:text-gray-400 dark:hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editNoteForm" action="../ajax/update_note.php" method="POST">
            <input type="hidden" id="note_id" name="note_id" value="">
            
            <div class="mb-4">
                <label for="note_text" class="block text-gray-700 font-bold mb-2 dark:text-gray-300">Note</label>
                <textarea id="note_text" name="note_text" rows="8" 
                          class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300"
                          required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition-colors dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg btn transition-all">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal de modification de note
    const modal = document.getElementById('editNoteModal');
    const modalContent = document.getElementById('modalContent');
    const closeModal = document.getElementById('closeModal');
    const cancelEdit = document.getElementById('cancelEdit');
    const editNoteForm = document.getElementById('editNoteForm');
    const noteIdInput = document.getElementById('note_id');
    const noteTextInput = document.getElementById('note_text');
    const editNoteButtons = document.querySelectorAll('.edit-note');
    
    // Fonction pour ouvrir le modal avec animation
    function openModal(id, text) {
        noteIdInput.value = id;
        noteTextInput.value = text;
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }
    
    // Fonction pour fermer le modal avec animation
    function closeModalFunction() {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    // Événements pour ouvrir le modal
    editNoteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const text = this.dataset.text;
            openModal(id, text);
        });
    });
    
    // Événements pour fermer le modal
    closeModal.addEventListener('click', closeModalFunction);
    cancelEdit.addEventListener('click', closeModalFunction);
    
    // Fermer le modal en cliquant à l'extérieur
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModalFunction();
        }
    });
    
    // Fermer le modal avec échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModalFunction();
        }
    });
    
    // Soumission du formulaire via AJAX
    editNoteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const noteId = noteIdInput.value;
        const noteText = noteTextInput.value.trim();
        
        if (!noteText) {
            alert('Veuillez entrer une note.');
            return;
        }
        
        // Bouton de soumission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
        submitBtn.disabled = true;
        
        // Envoi des données via AJAX
        $.ajax({
            url: '../ajax/update_note.php',
            type: 'POST',
            data: {
                note_id: noteId,
                note_text: noteText
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // Mettre à jour l'interface utilisateur
                        const noteElement = document.querySelector(`.edit-note[data-id="${noteId}"]`)
                            .closest('.bg-white')
                            .querySelector('.note-text');
                        
                        noteElement.innerHTML = noteText.replace(/\n/g, '<br>');
                        
                        // Fermer le modal
                        closeModalFunction();
                        
                        // Ajouter une notification de réussite temporaire
                        const successMessage = document.createElement('div');
                        successMessage.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 shadow-lg rounded dark:bg-green-900 dark:text-green-300 dark:border-green-600 transform translate-y-10 opacity-0 transition-all toast-notification';
                        successMessage.innerHTML = '<div class="flex"><i class="fas fa-check-circle mr-2 mt-1"></i><div>Note mise à jour avec succès !</div></div>';
                        document.body.appendChild(successMessage);
                        
                        setTimeout(() => {
                            successMessage.classList.remove('translate-y-10', 'opacity-0');
                        }, 100);
                        
                        setTimeout(() => {
                            successMessage.classList.add('translate-y-10', 'opacity-0');
                            setTimeout(() => {
                                document.body.removeChild(successMessage);
                            }, 300);
                        }, 3000);
                    } else {
                        alert('Erreur lors de la mise à jour de la note: ' + data.message);
                    }
                } catch (error) {
                    console.error('Erreur lors du parsing de la réponse:', error);
                    alert('Une erreur est survenue lors de la mise à jour de la note.');
                }
                // Restaurer le bouton
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            },
            error: function(error) {
                console.error('Erreur AJAX lors de la mise à jour de la note:', error);
                alert('Une erreur est survenue lors de la communication avec le serveur.');
                // Restaurer le bouton
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }
        });
    });
});
</script>

<?php include "../includes/footer.php"; ?>