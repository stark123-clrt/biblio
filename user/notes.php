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

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Mes Notes</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Filtres et recherche -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form action="notes.php" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="book_id" class="block text-gray-700 font-bold mb-2">Livre</label>
                    <select id="book_id" name="book_id" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">Tous les livres</option>
                        <?php foreach ($user_books as $book): ?>
                            <option value="<?php echo $book['id']; ?>" <?php echo $book_id == $book['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($book['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-gray-700 font-bold mb-2">Trier par</label>
                    <select id="sort" name="sort" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récentes</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Plus anciennes</option>
                        <option value="book" <?php echo $sort == 'book' ? 'selected' : ''; ?>>Titre du livre</option>
                        <option value="page" <?php echo $sort == 'page' ? 'selected' : ''; ?>>Numéro de page</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label for="search" class="block text-gray-700 font-bold mb-2">Recherche</label>
                    <div class="relative">
                        <input type="text" id="search" name="search" placeholder="Rechercher dans vos notes..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="w-full p-2 pr-10 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="absolute right-2 top-2 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Filtrer
                </button>
                <?php if ($book_id > 0 || !empty($search) || $sort != 'newest'): ?>
                    <a href="notes.php" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg">
                        <i class="fas fa-times mr-2"></i> Réinitialiser
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Liste des notes -->
    <?php if (!empty($notes)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($notes as $note): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-4 border-b flex justify-between items-center">
                        <div>
                            <h3 class="font-bold">
                                <a href="../book.php?id=<?php echo $note['book_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    <?php echo htmlspecialchars($note['book_title']); ?>
                                </a>
                            </h3>
                            <div class="text-sm text-gray-600">
                                Page <?php echo $note['page_number']; ?> - 
                                <?php echo date('d/m/Y à H:i', strtotime($note['created_at'])); ?>
                                <?php if ($note['created_at'] != $note['updated_at']): ?>
                                    (modifiée)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="../read.php?id=<?php echo $note['book_id']; ?>&page=<?php echo $note['page_number']; ?>" 
                               class="text-blue-600 hover:text-blue-800" title="Voir dans le livre">
                                <i class="fas fa-book-open"></i>
                            </a>
                            <a href="#" class="edit-note text-indigo-600 hover:text-indigo-800" 
                               title="Modifier" data-id="<?php echo $note['id']; ?>"
                               data-text="<?php echo htmlspecialchars($note['note_text']); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="notes.php?delete=<?php echo $note['id']; ?>" 
                               class="text-red-600 hover:text-red-800" 
                               title="Supprimer"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette note ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="note-text">
                            <?php echo nl2br(htmlspecialchars($note['note_text'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center bg-white rounded-lg shadow-md p-8">
            <div class="text-gray-400 text-6xl mb-4">
                <i class="fas fa-book"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-700 mb-2">Aucune note trouvée</h2>
            <p class="text-gray-600 mb-4">
                <?php if (!empty($search) || $book_id > 0): ?>
                    Essayez de modifier vos critères de recherche.
                <?php else: ?>
                    Vous n'avez pas encore ajouté de notes à vos livres.
                <?php endif; ?>
            </p>
            <a href="../library.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Parcourir la bibliothèque
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de modification de note -->
<div id="editNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Modifier la note</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editNoteForm" action="../ajax/update_note.php" method="POST">
            <input type="hidden" id="note_id" name="note_id" value="">
            
            <div class="mb-4">
                <label for="note_text" class="block text-gray-700 font-bold mb-2">Note</label>
                <textarea id="note_text" name="note_text" rows="8" 
                          class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du modal de modification de note
        const modal = document.getElementById('editNoteModal');
        const closeModal = document.getElementById('closeModal');
        const cancelEdit = document.getElementById('cancelEdit');
        const editNoteForm = document.getElementById('editNoteForm');
        const noteIdInput = document.getElementById('note_id');
        const noteTextInput = document.getElementById('note_text');
        const editNoteButtons = document.querySelectorAll('.edit-note');
        
        // Fonction pour ouvrir le modal
        function openModal(id, text) {
            noteIdInput.value = id;
            noteTextInput.value = text;
            modal.classList.remove('hidden');
        }
        
        // Fonction pour fermer le modal
        function closeModalFunction() {
            modal.classList.add('hidden');
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
        
        // Soumission du formulaire via AJAX
        editNoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const noteId = noteIdInput.value;
            const noteText = noteTextInput.value.trim();
            
            if (!noteText) {
                alert('Veuillez entrer une note.');
                return;
            }
            
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
                            
                            // Afficher un message de succès
                            alert('Note mise à jour avec succès !');
                        } else {
                            alert('Erreur lors de la mise à jour de la note: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Erreur lors du parsing de la réponse:', error);
                        alert('Une erreur est survenue lors de la mise à jour de la note.');
                    }
                },
                error: function(error) {
                    console.error('Erreur AJAX lors de la mise à jour de la note:', error);
                    alert('Une erreur est survenue lors de la communication avec le serveur.');
                }
            });
        });
    });
</script>

<?php include "../includes/footer.php"; ?>