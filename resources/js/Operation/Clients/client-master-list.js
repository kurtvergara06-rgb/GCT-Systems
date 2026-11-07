// Client Master List uses the shared form-modal and confirmation conventions.
window.GCTPartialNavigation?.registerInitializer('operation-client-master-list', '.client-master-page', (root) => {
    const button = root.querySelector('[data-client-create-open]');
    const modal = document.getElementById('clientMasterCreateModal');
    if (!button || !modal) return;

    const open = () => {
        modal.classList.add('show', 'active');
        document.body.classList.add('modal-open');
        modal.querySelector('input[name="name"]')?.focus();
    };
    const onClose = (event) => {
        if (!event.target.closest('[data-ui-modal-close]')) return;
        modal.classList.remove('show', 'active');
        if (!document.querySelector('.ui-form-overlay.show, .ui-form-overlay.active')) {
            document.body.classList.remove('modal-open');
        }
    };
    button.addEventListener('click', open);
    if (button.dataset.openOnError === 'true') open();
    modal.addEventListener('click', onClose);
    return () => {
        button.removeEventListener('click', open);
        modal.removeEventListener('click', onClose);
        modal.classList.remove('show', 'active');
    };
});
