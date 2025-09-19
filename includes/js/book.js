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

    // Système de notation par étoiles interactif
    const starLabels = document.querySelectorAll('.star-interactive');
    if (starLabels.length > 0) {
        const setRating = (rating) => {
            starLabels.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                    star.style.color = '#fbbf24';
                } else {
                    star.classList.remove('active');
                    star.style.color = '#d1d5db';
                }
            });
        };
        
        starLabels.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                setRating(rating);
            });
            
            star.addEventListener('click', () => {
                const rating = parseInt(star.dataset.rating);
                document.getElementById(`rating_${rating}`).checked = true;
                
                // Animation au clic
                star.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    star.style.transform = 'scale(1.1)';
                }, 150);
            });
        });
        
        // Réinitialiser au survol du conteneur
        const starRating = document.getElementById('star-rating');
        starRating.addEventListener('mouseleave', () => {
            let selectedRating = 0;
            for (let i = 1; i <= 5; i++) {
                if (document.getElementById(`rating_${i}`).checked) {
                    selectedRating = i;
                    break;
                }
            }
            setRating(selectedRating);
        });
        
        // Initialiser l'affichage des étoiles
        let initialRating = 0;
        for (let i = 1; i <= 5; i++) {
            if (document.getElementById(`rating_${i}`).checked) {
                initialRating = i;
                break;
            }
        }
        setRating(initialRating);
    }

    // Compteur de caractères pour le textarea
    const textarea = document.getElementById('comment_text');
    const charCounter = document.getElementById('char-counter');
    
    if (textarea && charCounter) {
        const updateCounter = () => {
            const length = textarea.value.length;
            charCounter.textContent = `${length} caractères`;
            
            if (length < 10) {
                charCounter.style.color = '#ef4444';
                textarea.style.borderColor = '#ef4444';
            } else {
                charCounter.style.color = '#6b7280';
                textarea.style.borderColor = '#d1d5db';
            }
        };
        
        textarea.addEventListener('input', updateCounter);
        updateCounter(); // Initialiser
    }

    // Validation du formulaire
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const rating = document.querySelector('input[name="rating"]:checked');
            const commentText = document.getElementById('comment_text').value.trim();
            
            if (!rating) {
                e.preventDefault();
                showAlert('Veuillez sélectionner une note pour ce livre.', 'error');
                return false;
            }
            
            if (!commentText || commentText.length < 10) {
                e.preventDefault();
                showAlert('Votre commentaire doit contenir au moins 10 caractères.', 'error');
                return false;
            }
        });
    }

    // Animation des cartes de commentaires
    const commentCards = document.querySelectorAll('.comment-card');
    commentCards.forEach((card, index) => {
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

    // Démarrer les animations
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 100);
        });
    }, 300);
});

// Fonction pour afficher des alertes
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white font-semibold`;
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateX(100%)';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}


