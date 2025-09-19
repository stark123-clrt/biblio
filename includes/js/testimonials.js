
document.addEventListener('DOMContentLoaded', function() {
    // Auto-soumission du formulaire lors des changements
    const selects = document.querySelectorAll('select[name="book_id"], select[name="rating"], select[name="sort"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    // Animation des cartes au scroll avec effet de cascade
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

    // Observer toutes les cartes de recommandation
    document.querySelectorAll('.group').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });

    // Effet parallaxe léger sur les barres de progression
    const progressBars = document.querySelectorAll('.bg-gradient-to-r');
    progressBars.forEach(bar => {
        bar.addEventListener('mouseenter', function() {
            this.style.transform = 'scaleX(1.02)';
        });
        bar.addEventListener('mouseleave', function() {
            this.style.transform = 'scaleX(1)';
        });
    });
});
