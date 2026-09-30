const revealPageSections = () => {
    const body = document.body;
    if (!body) return;

    body.classList.add('gct-initial-reveal');

    window.setTimeout(() => {
        body.classList.remove('gct-initial-reveal');
    }, 500);
};

document.addEventListener('DOMContentLoaded', revealPageSections, { once: true });

window.addEventListener('pageshow', (event) => {
    if (event.persisted) revealPageSections();
});

window.GCTPageTransition = Object.freeze({
    show: () => {},
    hide: revealPageSections,
});
