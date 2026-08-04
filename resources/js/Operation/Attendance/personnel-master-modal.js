document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-personnel-modal]');
    if (!modal) return;

    const openers = document.querySelectorAll('[data-open-personnel-modal]');
    const closers = modal.querySelectorAll('[data-close-personnel-modal]');
    const firstField = modal.querySelector('input, select');

    const openModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('personnel-modal-open');
        window.setTimeout(() => firstField?.focus(), 50);
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('personnel-modal-open');
    };

    openers.forEach((button) => button.addEventListener('click', openModal));
    closers.forEach((button) => button.addEventListener('click', closeModal));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    if (modal.dataset.openOnError === 'true') openModal();
});