const initializeAnalyticsChartInteractions = () => {
    const page = document.querySelector('.fleet-trip-page');

    if (!page || page.dataset.analyticsMotionInitialized === 'true') {
        return;
    }

    page.dataset.analyticsMotionInitialized = 'true';
    page.classList.add('analytics-motion-ready');

    const cards = Array.from(page.querySelectorAll('.analytics-card'));

    if (!cards.length) {
        return;
    }

    const revealCard = (card) => {
        if (card.classList.contains('is-chart-visible')) {
            return;
        }

        requestAnimationFrame(() => {
            requestAnimationFrame(() => card.classList.add('is-chart-visible'));
        });
    };

    if (!('IntersectionObserver' in window)) {
        cards.forEach(revealCard);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            revealCard(entry.target);
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.18,
        rootMargin: '0px 0px -8% 0px',
    });

    cards.forEach((card) => observer.observe(card));
};

document.addEventListener('DOMContentLoaded', initializeAnalyticsChartInteractions);

document.addEventListener('ajax:content-updated', initializeAnalyticsChartInteractions);
