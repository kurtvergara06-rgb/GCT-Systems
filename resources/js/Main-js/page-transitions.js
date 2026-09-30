const getMainElement = () => document.querySelector('main.main, main');

const show = () => {
    getMainElement()?.classList.add('gct-main-fetching');
};

const hide = () => {
    const main = getMainElement();
    main?.classList.remove('gct-main-fetching', 'gct-main-leaving');
};

window.addEventListener('pageshow', hide);
window.addEventListener('gct:navigation-ready', hide);

window.GCTPageTransition = Object.freeze({ show, hide });
