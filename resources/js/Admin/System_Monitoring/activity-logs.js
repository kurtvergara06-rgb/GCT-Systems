import '../../../css/Admin/System_Monitoring/activity-logs-controls.css';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('activityFilterForm');
    const searchInput = form?.querySelector('input[name="search"]');
    const loading = document.getElementById('activityTableLoading');
    let searchTimer = null;

    const submitFilters = () => {
        if (!form) {
            return;
        }

        if (loading) {
            loading.hidden = false;
        }

        form.requestSubmit();
    };

    if (searchInput) {
        searchInput.id = 'activitySearch';
        searchInput.setAttribute('autocomplete', 'off');

        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(submitFilters, 350);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                searchInput.value = '';
                submitFilters();
            }
        });
    }

    form?.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', submitFilters);
    });

    form?.addEventListener('submit', () => {
        if (loading) {
            loading.hidden = false;
        }
    });

    const modal = document.getElementById('activityDetailsModal');
    const closeButton = document.getElementById('closeActivityModal');
    const closeFooterButton = document.getElementById('closeActivityModalFooter');

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        document.body.classList.remove('activity-modal-open');
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.open-log-modal');

        if (button && modal) {
            document.getElementById('modalUser').textContent = button.dataset.user || '—';
            document.getElementById('modalRole').textContent = button.dataset.role || '—';
            document.getElementById('modalModule').textContent = button.dataset.module || '—';
            document.getElementById('modalType').textContent = button.dataset.type || '—';
            document.getElementById('modalReference').textContent = button.dataset.reference || '—';
            document.getElementById('modalDateTime').textContent = `${button.dataset.date || '—'} ${button.dataset.time || ''}`.trim();
            document.getElementById('modalActivity').textContent = button.dataset.activity || '—';
            document.getElementById('modalDetails').textContent = button.dataset.details || '—';
            document.getElementById('modalIp').textContent = button.dataset.ip || '—';
            document.getElementById('modalDevice').textContent = button.dataset.device || '—';
            modal.classList.add('show');
            document.body.classList.add('activity-modal-open');
            return;
        }

        if (event.target === modal) {
            closeModal();
        }
    });

    closeButton?.addEventListener('click', closeModal);
    closeFooterButton?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('show')) {
            closeModal();
        }
    });
});
