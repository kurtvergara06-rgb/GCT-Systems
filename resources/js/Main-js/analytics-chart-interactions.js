const animateFuelCard = (card) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    card.querySelectorAll('.fuel-bar-fill').forEach((bar, index) => {
        bar.animate(
            [
                { transform: 'scaleY(0)', opacity: 0.35 },
                { transform: 'scaleY(1)', opacity: 1 },
            ],
            {
                duration: 720,
                delay: 80 + (index * 90),
                easing: 'cubic-bezier(.22,.61,.36,1)',
                fill: 'both',
            }
        );
    });

    card.querySelectorAll('.fuel-efficiency-ring, .fuel-readiness-ring').forEach((ring) => {
        ring.animate(
            [
                { transform: 'scale(.78) rotate(-18deg)', opacity: 0 },
                { transform: 'scale(1) rotate(0deg)', opacity: 1 },
            ],
            {
                duration: 760,
                easing: 'cubic-bezier(.22,.61,.36,1)',
                fill: 'both',
            }
        );
    });

    card.querySelectorAll('.fuel-review-row, .fuel-recommendation-card, .fuel-rule-list > div').forEach((row, index) => {
        row.animate(
            [
                { transform: 'translateY(10px)', opacity: 0 },
                { transform: 'translateY(0)', opacity: 1 },
            ],
            {
                duration: 380,
                delay: 80 + (index * 55),
                easing: 'ease-out',
                fill: 'both',
            }
        );
    });
};

const bindFuelHoverInteractions = (page) => {
    page.querySelectorAll('.fuel-bar-column').forEach((column) => {
        const bar = column.querySelector('.fuel-bar-fill');
        const value = column.querySelector('.fuel-bar-value');

        if (!bar || column.dataset.fuelHoverBound === 'true') {
            return;
        }

        column.dataset.fuelHoverBound = 'true';

        column.addEventListener('mouseenter', () => {
            bar.animate(
                [
                    { transform: 'translateY(0) scaleX(1)' },
                    { transform: 'translateY(-8px) scaleX(1.12)' },
                ],
                { duration: 180, easing: 'ease-out', fill: 'forwards' }
            );

            if (value) {
                value.animate(
                    [
                        { transform: 'scale(1)', opacity: 0.82 },
                        { transform: 'scale(1.12)', opacity: 1 },
                    ],
                    { duration: 180, easing: 'ease-out', fill: 'forwards' }
                );
            }
        });

        column.addEventListener('mouseleave', () => {
            bar.animate(
                [
                    { transform: 'translateY(-8px) scaleX(1.12)' },
                    { transform: 'translateY(0) scaleX(1)' },
                ],
                { duration: 180, easing: 'ease-out', fill: 'forwards' }
            );

            if (value) {
                value.animate(
                    [
                        { transform: 'scale(1.12)', opacity: 1 },
                        { transform: 'scale(1)', opacity: 0.82 },
                    ],
                    { duration: 180, easing: 'ease-out', fill: 'forwards' }
                );
            }
        });
    });

    page.querySelectorAll('.fuel-efficiency-ring, .fuel-readiness-ring').forEach((ring) => {
        if (ring.dataset.fuelHoverBound === 'true') {
            return;
        }

        ring.dataset.fuelHoverBound = 'true';
        ring.addEventListener('mouseenter', () => ring.animate(
            [{ transform: 'scale(1)' }, { transform: 'scale(1.055) rotate(2deg)' }],
            { duration: 190, easing: 'ease-out', fill: 'forwards' }
        ));
        ring.addEventListener('mouseleave', () => ring.animate(
            [{ transform: 'scale(1.055) rotate(2deg)' }, { transform: 'scale(1) rotate(0deg)' }],
            { duration: 190, easing: 'ease-out', fill: 'forwards' }
        ));
    });
};

const initializeAnalyticsPage = (page) => {
    if (page.dataset.analyticsMotionInitialized === 'true') {
        return;
    }

    page.dataset.analyticsMotionInitialized = 'true';
    page.classList.add('analytics-motion-ready');

    const cards = Array.from(page.querySelectorAll('.analytics-card'));
    const isFuelPage = page.classList.contains('fuel-analytics-page');

    if (isFuelPage) {
        bindFuelHoverInteractions(page);
    }

    if (!cards.length) {
        return;
    }

    const revealCard = (card) => {
        if (card.classList.contains('is-chart-visible')) {
            return;
        }

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                card.classList.add('is-chart-visible');
                if (isFuelPage) {
                    animateFuelCard(card);
                }
            });
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

const initializeAnalyticsChartInteractions = () => {
    document
        .querySelectorAll('.fleet-trip-page, .fuel-analytics-page')
        .forEach(initializeAnalyticsPage);
};

initializeAnalyticsChartInteractions();
document.addEventListener('DOMContentLoaded', initializeAnalyticsChartInteractions);
document.addEventListener('ajax:content-updated', initializeAnalyticsChartInteractions);
