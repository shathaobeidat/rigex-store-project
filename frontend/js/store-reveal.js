'use strict';

// Visual enhancement only: keep the current database, filters and cart unchanged.
(() => {
    const cards = [...document.querySelectorAll('.store-catalog .landing-product-card')];
    if (!cards.length || !('IntersectionObserver' in window)) return;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let observer;

    const reveal = card => {
        card.classList.remove('store-reveal-pending');
        observer?.unobserve(card);
    };
    function start() {
        observer?.disconnect();
        if (reduced.matches) {
            cards.forEach(reveal);
            return;
        }
        observer = new IntersectionObserver(entries => {
            let stagger = 0;
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.style.setProperty('--store-reveal-delay', `${(stagger++ % 4) * 80}ms`);
                reveal(entry.target);
            });
        }, {threshold: 0.05});
        cards.forEach(card => {
            if (card.dataset.storeRevealReady) return;
            card.dataset.storeRevealReady = 'true';
            card.classList.add('store-reveal-card', 'store-reveal-pending');
            observer.observe(card);
        });
    }
    document.addEventListener('focusin', event => {
        const card = event.target.closest('.store-reveal-pending');
        if (card) {
            card.style.setProperty('--store-reveal-delay', '0ms');
            reveal(card);
        }
    });
    reduced.addEventListener('change', () => {
        // Never hide content again when a preference changes mid-visit.
        observer?.disconnect(); cards.forEach(reveal);
    });
    start();
})();
