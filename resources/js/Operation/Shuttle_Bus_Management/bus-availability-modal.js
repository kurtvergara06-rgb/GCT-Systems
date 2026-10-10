// Bus Availability details are read-only; delegate clicks so AJAX-replaced rows still work.
window.GCTPartialNavigation.registerInitializer('operation-bus-availability-modal', '.bus-availability-page', () => {
    const page = document.querySelector('.bus-availability-page');
    const modal = document.getElementById('availabilityBusModal');
    const dialog = modal?.querySelector('[role="dialog"]');
    if (!page || !modal || !dialog || page.dataset.availabilityModalBound === 'true') return;
    page.dataset.availabilityModalBound = 'true';

    let trigger = null;

    const close = () => {
        if (!modal.classList.contains('show')) return;
        modal.classList.remove('show', 'active');
        modal.setAttribute('aria-hidden', 'true');
        trigger?.focus?.();
        trigger = null;
    };

    page.addEventListener('click', (event) => {
        const button = event.target.closest('.open-availability-bus');
        if (!button || !page.contains(button)) return;
        event.preventDefault();
        trigger = button;
        const fields = {
            plate: button.dataset.plate,
            model: button.dataset.model,
            masterStatus: button.dataset.masterStatus,
            availability: button.dataset.availability,
            nextTrip: button.dataset.nextTrip,
            nextRoute: button.dataset.nextRoute,
        };
        Object.entries(fields).forEach(([key, value]) => {
            const field = modal.querySelector('[data-availability-detail="' + key + '"]');
            if (field) field.textContent = value || '—';
        });
        const statusBadge = modal.querySelector('[data-availability-detail="availability"]');
        if (statusBadge) statusBadge.dataset.status = fields.availability || '';
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('show', 'active');
        dialog.focus();
    });

    modal.querySelectorAll('[data-close-availability-bus]').forEach((button) => {
        button.addEventListener('click', close);
    });

    // Deliberately do NOT close on backdrop clicks, per GCT modal policy.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            event.preventDefault();
            close();
        }
    });

    window.addEventListener('gct:navigation-before', close);
});
