document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observer tous les éléments avec animation
    document.querySelectorAll('.slide-up').forEach(element => {
        observer.observe(element);
    });

    // Aperçu de l'image de profil
    const profilePictureInput = document.getElementById('profile_picture');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Vérifier la taille du fichier (2 Mo max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux. Taille maximum : 2 Mo');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    // Mettre à jour tous les aperçus d'image
                    const previews = document.querySelectorAll('img[alt="Aperçu"], img[alt="Photo de profil"]');
                    previews.forEach(img => {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        // Masquer le placeholder s'il existe
                        const placeholder = img.nextElementSibling;
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    });

                    // Mettre à jour l'aperçu dans la zone d'upload
                    const uploadPreview = document.querySelector('.upload-area img, .upload-area .w-full.h-full.flex');
                    if (uploadPreview) {
                        if (uploadPreview.tagName === 'IMG') {
                            uploadPreview.src = e.target.result;
                        } else {
                            // Créer une nouvelle image
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.className = 'w-full h-full object-cover';
                            newImg.alt = 'Aperçu';
                            uploadPreview.parentNode.replaceChild(newImg, uploadPreview);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Validation du formulaire de mot de passe
    const passwordForm = document.querySelector('form[method="POST"]:has([name="change_password"])');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                
                // Animation d'erreur
                const confirmInput = document.getElementById('confirm_password');
                confirmInput.style.borderColor = '#ef4444';
                confirmInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                
                // Message d'erreur moderne
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert-modern alert-error mt-4';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-2xl mr-4"></i>
                        <p class="font-semibold">Les mots de passe ne correspondent pas.</p>
                    </div>
                `;
                
                // Supprimer l'ancien message d'erreur s'il existe
                const existingError = passwordForm.querySelector('.alert-error');
                if (existingError) {
                    existingError.remove();
                }
                
                passwordForm.insertBefore(errorDiv, passwordForm.firstChild);
                
                // Faire défiler vers l'erreur
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Supprimer l'erreur après 5 secondes
                setTimeout(() => {
                    errorDiv.remove();
                    confirmInput.style.borderColor = '';
                    confirmInput.style.boxShadow = '';
                }, 5000);
            }
        });

        // Validation en temps réel
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (newPasswordInput.value && confirmPasswordInput.value) {
                if (newPasswordInput.value === confirmPasswordInput.value) {
                    confirmPasswordInput.style.borderColor = '#10b981';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.1)';
                } else {
                    confirmPasswordInput.style.borderColor = '#ef4444';
                    confirmPasswordInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                }
            }
        }
        
        newPasswordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    }

    // Animation des statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 150);
    });

    // Animation des champs de formulaire au focus
    const formInputs = document.querySelectorAll('.form-input');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Démarrer les animations après un délai
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 200);
        });
    }, 300);
});
