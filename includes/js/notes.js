
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

