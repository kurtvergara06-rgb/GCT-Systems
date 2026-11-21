window.GCTPartialNavigation.registerInitializer('admin-activity-logs', '.activity-logs-page', () => {
    const form = document.getElementById('activityFilterForm');
    const searchInput = form?.querySelector('input[name="search"]');
    const loading = document.getElementById('activityTableLoading');
    const moduleFilter = document.getElementById('activityModuleFilter');
    const eventFilter = document.getElementById('activityEventFilter');
    const dateFilter = document.getElementById('activityDateFilter');
    let searchTimer = null;

    const params = new URLSearchParams(window.location.search);

    // Keep filter controls aligned with the actual URL instead of allowing the
    // browser to restore stale select values from a previous page state.
    if (moduleFilter) {
        moduleFilter.value = params.get('module') || 'all';
    }

    if (eventFilter) {
        eventFilter.value = params.get('event') || 'all';
    }

    if (dateFilter) {
        dateFilter.value = params.get('date') || 'all';
    }

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

    // Only render change cards when a record contains the known permission
    // audit format. Preserve every other activity detail verbatim.
    const renderChanges = (details) => {
        const list = document.getElementById('modalChanges');
        const fallback = document.getElementById('modalDetailsFallback');
        if (!list || !fallback) return;
        list.replaceChildren();
        const raw = details.trim();
        const entries = raw.replace(/^Changes:\s*/i, '').split(';').map(value => value.trim()).filter(Boolean);
        const parsed = entries.map(entry => {
            const match = entry.match(/^([a-z_]+)\.([a-z_]+):\s*(denied|allowed)\s*(?:→|->)\s*(denied|allowed)$/i);
            return match ? { module: match[1], capability: match[2], from: match[3].toLowerCase(), to: match[4].toLowerCase() } : null;
        });
        if (!entries.length || parsed.some(entry => !entry)) {
            list.hidden = true;
            fallback.hidden = false;
            return;
        }
        for (const change of parsed) {
            const row = document.createElement('div');
            row.className = 'activity-change-row';
            const icon = document.createElement('span');
            icon.className = 'activity-change-icon';
            const glyph = document.createElement('i');
            glyph.className = 'fa-solid fa-shield-halved';
            glyph.setAttribute('aria-hidden', 'true');
            icon.appendChild(glyph);
            const body = document.createElement('span');
            body.className = 'activity-change-text';
            const title = document.createElement('strong');
            const readable = value => value.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            title.textContent = readable(change.capability === 'recommendations' ? 'prescriptive' : change.capability) + ' ' + readable(change.module);
            const subtitle = document.createElement('small');
            subtitle.textContent = readable(change.from) + ' → ' + readable(change.to);
            body.append(title, subtitle);
            const chip = document.createElement('span');
            chip.className = 'activity-change-chip ' + (change.to === 'allowed' ? 'granted' : 'revoked');
            chip.textContent = change.to === 'allowed' ? 'Granted' : 'Revoked';
            row.append(icon, body, chip);
            list.appendChild(row);
        }
        list.hidden = false;
        fallback.hidden = true;
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
            renderChanges(button.dataset.details || ''); 
            modal.querySelector('.activity-device-details')?.removeAttribute('open');
            document.getElementById('modalIp').textContent = button.dataset.ip || '—';
            document.getElementById('modalDevice').textContent = button.dataset.device || '—';
            modal.classList.add('show');
            document.body.classList.add('activity-modal-open');
            return;
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
