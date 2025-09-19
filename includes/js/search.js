
document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments au scroll
    const animateElements = () => {
        const elements = document.querySelectorAll('.animate-fadeInUp, .animate-fadeIn');
        elements.forEach(el => {
            const rect = el.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            if (rect.top <= windowHeight * 0.85 && !el.classList.contains('animated')) {
                el.style.opacity = '1';
                el.classList.add('animated');
            }
        });
    };
    
    // Initialiser les animations
    animateElements();
    
    // Animer au scroll
    window.addEventListener('scroll', animateElements);
    
    // Auto-soumission du formulaire lors des changements de tri ou catégorie
    const categorySelect = document.getElementById('category');
    const sortSelect = document.getElementById('sort');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
    
    // Effet de hover amélioré pour les cartes de résultats
    const resultCards = document.querySelectorAll('.search-result-card');
    resultCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Surlignage dynamique des termes de recherche
    const searchInput = document.getElementById('q');
    const searchQuery = searchInput ? searchInput.value.trim() : '';
    
    if (searchQuery) {
        const terms = searchQuery.toLowerCase().split(/\s+/);
        const contentElements = document.querySelectorAll('.line-clamp-3, h3');
        
        contentElements.forEach(element => {
            const originalText = element.textContent;
            if (originalText) {
                let highlightedText = originalText;
                terms.forEach(term => {
                    if (term.length > 2) { // Ignorer les termes trop courts
                        const regex = new RegExp('(' + term + ')', 'gi');
                        highlightedText = highlightedText.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
                
                // Uniquement si des correspondances ont été trouvées et que le contenu n'a pas déjà été modifié
                if (highlightedText !== originalText && !element.innerHTML.includes('search-highlight')) {
                    element.innerHTML = highlightedText;
                }
            }
        });
    }
});
