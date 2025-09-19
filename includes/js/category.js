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
                observer.unobserve(entry.target); // Ne pas re-animer
            }
        });
    }, observerOptions);

    // Observer tous les éléments avec animation
    document.querySelectorAll('.slide-up').forEach(element => {
        observer.observe(element);
    });

    // Gestion du tri
    const sortSelect = document.getElementById('sort-select');
    sortSelect.addEventListener('change', function() {
        // Animation de transition
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.style.transform = 'scale(1)';
            window.location.href = 'category.php?id=<?php echo $category_id; ?>&sort=' + this.value;
        }, 150);
    });

    // Animation des cartes au chargement
    setTimeout(() => {
        document.querySelectorAll('.slide-up').forEach(element => {
            element.classList.add('visible');
        });
    }, 100);
});
