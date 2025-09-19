document.addEventListener('DOMContentLoaded', function() {
    // Auto-soumission du formulaire lors des changements
    const selects = document.querySelectorAll('select[name="category"], select[name="sort"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    // Animation des cartes au scroll - VERSION CORRIGÉE
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // ✅ CORRECTION: Observer seulement les cartes de livres, pas toutes les .group
    document.querySelectorAll('.book-card').forEach((card, index) => {
        // ✅ Garde l'opacité normale par défaut, anime seulement au scroll
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });

    // Effet de recherche en temps réel
    const searchInput = document.getElementById('search');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Auto-submit après 500ms d'inactivité (optionnel)
                // this.closest('form').submit();
            }, 500);
        });
    }
});
