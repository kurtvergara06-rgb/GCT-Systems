window.GCTPartialNavigation.registerInitializer('operation-client-trip-times', '.client-trip-times-page', () => {
    const baseUrl = '/operation/client-trip-times';

    const slotModal = document.getElementById('cttSlotModal');
    const slotForm = document.getElementById('cttSlotForm');

    const slotId = document.getElementById('cttSlotId');
    const slotTime = document.getElementById('cttSlotTime');
    const slotEnabled = document.getElementById('cttSlotEnabled');
    const slotRemove = document.getElementById('cttSlotRemove');

    const clientToolbar = document.getElementById('cttClientToolbar');
    const clientSearch = clientToolbar?.querySelector('input[name="search"]');
    const clientFilter = document.getElementById('cttClientFilter');
    const clientSort = clientToolbar?.querySelector('select[name="sort"]');
    const clientCount = document.getElementById('cttClientCount');
    const clientsContainer = document.getElementById('cttClients');
    const searchEmpty = document.getElementById('cttSearchEmpty');

    let activeSlotContainer = null;
    let activeRangeEditor = null;

    const categoryLabels = {
        incoming_am: 'Incoming AM',
        incoming_pm: 'Incoming PM',
        outgoing_am: 'Outgoing AM',
        outgoing_pm: 'Outgoing PM',
    };

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            || slotForm?.querySelector('[name="_token"]')?.value
            || '';
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('show', 'active');
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('show', 'active');
        const hasOpenModal = document.querySelector('.ui-form-overlay.show, .ui-form-overlay.active');
        if (!hasOpenModal) {
            document.body.classList.remove('modal-open');
        }
    }

    function clearErrors(scope) {
        scope.querySelectorAll('.ctt-field-error').forEach((element) => {
            element.textContent = '';
        });
    }

    function renderErrors(scope, errors) {
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const target = scope.querySelector(`[data-error-for="${field}"]`);
            if (target) {
                target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            }
        });
    }

    function panelContainerFor(element) {
        return element?.closest?.('.ctt-category-panel') || null;
    }

    async function refreshPanel(container) {
        if (!container) return;

        const clientId = container.dataset.clientId;
        const category = container.dataset.category;

        const response = await fetch(`${baseUrl}/panels/${clientId}/${category}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to refresh the trip time configuration.');
        }

        container.innerHTML = await response.text();
        updateClientSummary(container.dataset.clientId);
    }

    function updateClientSummary(clientId) {
        const card = document.querySelector(`.ctt-client-card[data-client-id="${clientId}"]`);
        if (!card) return;

        let configuredCount = 0;

        card.querySelectorAll('.ctt-category-panel').forEach((panel) => {
            const category = panel.dataset.category;
            const isConfigured = Boolean(panel.querySelector('.ctt-panel-header'))
                && !panel.querySelector('.ctt-status-none');
            const indicator = card.querySelector(`[data-category-chip="${category}"]`);

            if (indicator) {
                indicator.classList.toggle('is-configured', isConfigured);
                indicator.title = `${categoryLabels[category] || category}: ${isConfigured ? 'Configured' : 'Not configured'}`;

                const icon = indicator.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-circle-check', isConfigured);
                    icon.classList.toggle('fa-circle', !isConfigured);
                }
            }

            if (isConfigured) configuredCount += 1;
        });

        const totalCategories = Object.keys(categoryLabels).length;
        const percentage = totalCategories ? Math.round((configuredCount / totalCategories) * 100) : 0;
        card.dataset.configuredCount = String(configuredCount);
        card.dataset.totalCategories = String(totalCategories);

        const count = card.querySelector('[data-configured-count]');
        const countBadge = card.querySelector('.ctt-client-config-count');
        const progressFill = card.querySelector('.ctt-progress-fill');
        const progressTrack = card.querySelector('.ctt-progress-track');
        const actionLabel = card.querySelector('.ctt-client-expand > span');

        if (count) count.textContent = String(configuredCount);
        countBadge?.classList.toggle('is-complete', configuredCount === totalCategories);
        if (progressFill) progressFill.style.width = `${percentage}%`;
        if (progressTrack) progressTrack.setAttribute('aria-label', `${percentage} percent configured`);
        if (actionLabel) actionLabel.textContent = configuredCount > 0 ? 'Manage settings' : 'Configure client';

        const cards = Array.from(document.querySelectorAll('.ctt-client-card'));
        const configuredCounts = cards.map((item) => item.querySelectorAll('.ctt-category-indicator.is-configured').length);
        const totalConfigured = configuredCounts.reduce((sum, count) => sum + count, 0);
        const totalPossible = cards.length * totalCategories;
        const totalCompleteClients = configuredCounts.filter((count) => count >= totalCategories).length;
        const configuredValue = document.querySelector('[data-configured-value]');
        const completeClientsStat = document.querySelector('[data-stat="complete-clients"]');
        const needsSetupStat = document.querySelector('[data-stat="needs-setup"]');

        if (configuredValue) configuredValue.textContent = String(totalConfigured);
        if (completeClientsStat) completeClientsStat.textContent = String(totalCompleteClients);
        if (needsSetupStat) needsSetupStat.textContent = String(Math.max(0, cards.length - totalCompleteClients));

        applyClientSearch();
    }

    async function refreshPanelByUrl(panelUrl) {
        if (!panelUrl) return;

        const response = await fetch(panelUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to refresh the trip time configuration.');
        }

        const html = await response.text();
        const markup = document.createElement('div');
        markup.innerHTML = html.trim();

        const clientId = markup.querySelector('[data-client-id]')?.dataset.clientId;
        const category = markup.querySelector('[data-category]')?.dataset.category;

        if (!clientId || !category) return;

        const container = document.getElementById(`ctt-panel-${clientId}-${category}`);

        if (container) {
            container.innerHTML = html;
            updateClientSummary(clientId);
        }
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error('The request could not be saved.');
            error.status = response.status;
            error.errors = data.errors || {};
            throw error;
        }

        return data;
    }

    function displayTime(value) {
        if (!/^\\d{2}:\\d{2}$/.test(value || '')) return value || '';
        const [hour, minute] = value.split(':').map(Number);
        return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
    }

    function closeRangeEditor(editor) {
        if (!editor) return;
        editor.hidden = true;
        panelContainerFor(editor)?.classList.remove('is-range-editor-open');
        editor.reset();
        clearErrors(editor);
        const panel = panelContainerFor(editor);
        const trigger = panel?.querySelector('.ctt-add-config');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        panel?.querySelectorAll('.ctt-edit-range').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        if (activeRangeEditor === editor) activeRangeEditor = null;
    }

    function openRangeEditor(panel) {
        const editor = panel?.querySelector('[data-ctt-range-editor]');
        if (!editor) return;
        if (activeRangeEditor && activeRangeEditor !== editor) closeRangeEditor(activeRangeEditor);
        if (!editor.hidden) {
            closeRangeEditor(editor);
            return;
        }
        editor.reset();
        clearErrors(editor);
        editor.hidden = false;
        panel.classList.add('is-range-editor-open');
        activeRangeEditor = editor;
        panel.querySelector('.ctt-add-config')?.setAttribute('aria-expanded', 'true');
        panel.querySelectorAll('.ctt-edit-range').forEach((button) => button.setAttribute('aria-expanded', 'true'));
        editor.querySelector('input[name="start_time"]')?.focus();
    }

    document.addEventListener('submit', async (event) => {
        const editor = event.target.closest('[data-ctt-range-editor]');
        if (!editor) return;
        event.preventDefault();
        if (editor.hidden || !editor.reportValidity()) return;
        clearErrors(editor);
        const button = editor.querySelector('button[type="submit"]');
        if (button?.disabled) return;
        const start = editor.querySelector('input[name="start_time"]')?.value || '';
        const end = editor.querySelector('input[name="end_time"]')?.value || '';
        const category = editor.dataset.category;
        const minimum = category.endsWith('_am') ? '00:00' : '12:00';
        const maximum = category.endsWith('_am') ? '11:59' : '23:59';
        if (start < minimum || end > maximum || start >= end) {
            renderErrors(editor, { end_time: ['Select a valid time range within this category. End Time must be later than Start Time.'] });
            return;
        }
        const updateUrl = editor.dataset.updateUrl;
        const payload = {
            start_time: start,
            end_time: end,
            is_active: editor.querySelector('[name="is_active"]')?.checked ? 1 : 0,
        };
        if (!updateUrl) {
            payload.operation_client_id = editor.dataset.clientId;
            payload.category = category;
        }
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
        try {
            const data = await postJson(updateUrl || baseUrl, updateUrl ? { ...payload, _method: 'PUT' } : payload);
            const panel = panelContainerFor(editor);
            await refreshPanelByUrl(data.panel_url);
            // The refreshed server-side card replaces the inline editor and reflects saved settings.
            if (activeRangeEditor === editor) activeRangeEditor = null;
            if (panel && editor.isConnected) await refreshPanel(panel);
        } catch (error) {
            if (error.errors && Object.keys(error.errors).length) {
                renderErrors(editor, error.errors);
            } else {
                renderErrors(editor, { start_time: [error.message || 'The configuration could not be saved.'] });
            }
        } finally {
            if (button?.isConnected) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }
    });

    slotForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        clearErrors(slotForm);

        const slotUrl = `${baseUrl}/slots/${slotId.value}`;
        if (slotTime.value && slotTime.min && slotTime.max
            && (slotTime.value < slotTime.min || slotTime.value > slotTime.max)) {
            renderErrors(slotForm, {
                slot_time: [`Select a time between ${displayTime(slotTime.min)} and ${displayTime(slotTime.max)}.`],
            });
            slotTime.focus();
            return;
        }

        try {
            await postJson(slotUrl, {
                _method: 'PUT',
                slot_time: slotTime.value,
                is_enabled: slotEnabled.checked ? 1 : 0,
            });

            closeModal(slotModal);
            await refreshPanel(activeSlotContainer);
        } catch (error) {
            if (error.errors && Object.keys(error.errors).length) {
                renderErrors(slotForm, error.errors);
                return;
            }

            renderErrors(slotForm, {
                slot_time: [error.message || 'The slot could not be saved.'],
            });
        }
    });

    slotRemove?.addEventListener('click', async () => {
        if (!slotId.value) return;

        try {
            await postJson(`${baseUrl}/slots/${slotId.value}`, {
                _method: 'DELETE',
            });

            closeModal(slotModal);
            await refreshPanel(activeSlotContainer);
        } catch (error) {
            renderErrors(slotForm, {
                slot_time: [error.message || 'The slot could not be removed.'],
            });
        }
    });

    document.addEventListener('click', async (event) => {
        const addConfigButton = event.target.closest('.ctt-add-config');
        if (addConfigButton) {
            openRangeEditor(panelContainerFor(addConfigButton));
            return;
        }
        const rangeCancel = event.target.closest('[data-ctt-range-cancel]');
        if (rangeCancel) {
            closeRangeEditor(rangeCancel.closest('[data-ctt-range-editor]'));
            return;
        }

        const editPanelButton = event.target.closest('.ctt-open-editor');
        if (editPanelButton) {
            const panel = panelContainerFor(editPanelButton);
            const editor = panel?.querySelector('.ctt-ref-slot-editor');
            if (!editor) return;
            const opening = editor.hidden;
            if (!opening) {
                const pendingTime = editor.querySelector('.ctt-add-slot-form input[name="slot_time"]');
                if (pendingTime?.value) {
                    const error = editor.querySelector('[data-error-for="slot_time"]');
                    if (error) {
                        error.textContent = 'This time is not saved. Click Add Slot before selecting Done.';
                    }
                    pendingTime.focus();
                    return;
                }
            }
            editor.hidden = !opening;
            editPanelButton.setAttribute('aria-expanded', String(opening));
            editPanelButton.innerHTML = opening
                ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i> Done'
                : '<i class="fa-solid fa-pen" aria-hidden="true"></i> Edit';
            return;
        }

        const editRangeButton = event.target.closest('.ctt-edit-range');
        if (editRangeButton) {
            openRangeEditor(panelContainerFor(editRangeButton));
            return;
        }

        const slotEditButton = event.target.closest('.ctt-slot-edit');

        if (slotEditButton) {
            const chip = slotEditButton.closest('.ctt-slot-chip');
            activeSlotContainer = panelContainerFor(slotEditButton);
            clearErrors(slotForm);
            slotId.value = chip?.dataset.slotId || '';
            slotTime.value = chip?.dataset.slotTime || '';
            slotEnabled.checked = chip?.dataset.slotEnabled === '1';
            const slotRangeForm = activeSlotContainer?.querySelector('.ctt-add-slot-form');
            slotTime.min = slotRangeForm?.dataset.minTime || '';
            slotTime.max = slotRangeForm?.dataset.maxTime || '';
            openModal(slotModal);
            return;
        }

        const slotToggleButton = event.target.closest('.ctt-slot-toggle');

        if (slotToggleButton) {
            const chip = slotToggleButton.closest('.ctt-slot-chip');
            const container = panelContainerFor(slotToggleButton);

            if (!chip || !container) return;

            try {
                await postJson(`${baseUrl}/slots/${chip.dataset.slotId}`, {
                    _method: 'PUT',
                    slot_time: chip.dataset.slotTime,
                    is_enabled: chip.dataset.slotEnabled === '1' ? 0 : 1,
                });

                await refreshPanel(container);
            } catch (error) {
                window.alert(error.message || 'The slot could not be updated.');
            }
        }
    });

    document.addEventListener('submit', async (event) => {
        const addSlotForm = event.target.closest('.ctt-add-slot-form');

        if (!addSlotForm) return;

        event.preventDefault();

        const container = panelContainerFor(addSlotForm);
        const errorTarget = container?.querySelector('[data-error-for="slot_time"]');

        if (errorTarget) {
            errorTarget.textContent = '';
        }

        const slotInput = addSlotForm.querySelector('[name="slot_time"]');
        const submitButton = addSlotForm.querySelector('button[type="submit"]');
        if (submitButton?.disabled) return;
        const min = addSlotForm.dataset.minTime;
        const max = addSlotForm.dataset.maxTime;
        if (slotInput?.value && min && max && (slotInput.value < min || slotInput.value > max)) {
            if (errorTarget) {
                errorTarget.textContent = `Select a time between ${displayTime(min)} and ${displayTime(max)}.`;
            }
            slotInput.focus();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        }

        try {
            await postJson(`${baseUrl}/${addSlotForm.dataset.configId}/slots`, {
                slot_time: slotInput.value,
            });

            // The server-rendered panel is authoritative for the saved count/chips.
            await refreshPanel(container);
        } catch (error) {
            if (errorTarget) {
                errorTarget.textContent = Object.values(error.errors || {})[0]?.[0]
                    || error.message
                    || 'The slot could not be added.';
            }
        } finally {
            if (submitButton?.isConnected) {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
            }
        }
    });

    [slotModal].forEach((modal) => {
        modal?.querySelectorAll('[data-ui-modal-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (activeRangeEditor) closeRangeEditor(activeRangeEditor);
        closeModal(slotModal);
    });

    function applyClientSearch() {
        const query = String(clientSearch?.value || '').trim().toLowerCase();
        const filter = clientFilter?.value || 'all';
        const sort = clientSort?.value || 'name-asc';
        const cards = Array.from(document.querySelectorAll('.ctt-client-card'));

        const matchesFilter = (card) => {
            const configured = Number(card.dataset.configuredCount || 0);
            const total = Number(card.dataset.totalCategories || Object.keys(categoryLabels).length);

            if (filter === 'complete') return configured >= total;
            if (filter === 'needs-setup') return configured < total;
            return true;
        };

        const sortedCards = [...cards].sort((a, b) => {
            const aCount = Number(a.dataset.configuredCount || 0);
            const bCount = Number(b.dataset.configuredCount || 0);
            const aName = a.dataset.clientName || '';
            const bName = b.dataset.clientName || '';

            if (sort === 'most-configured' && aCount !== bCount) return bCount - aCount;
            if (sort === 'least-configured' && aCount !== bCount) return aCount - bCount;
            return aName.localeCompare(bName);
        });

        if (clientsContainer) {
            sortedCards.forEach((card) => {
                clientsContainer.insertBefore(card, searchEmpty || null);
            });
        }

        let visible = 0;
        cards.forEach((card) => {
            const matchesQuery = !query || (card.dataset.clientName || '').includes(query);
            const matches = matchesQuery && matchesFilter(card);
            card.hidden = !matches;
            if (matches) visible += 1;
        });

        if (clientCount) {
            clientCount.textContent = `${visible} ${visible === 1 ? 'Client' : 'Clients'}`;
        }

        if (searchEmpty) {
            searchEmpty.hidden = !(visible === 0 && cards.length > 0);
        }
    }

    // Keep branding controls compact until the user selects a file or removal.
    document.querySelectorAll('.ctt-client-logo-form').forEach((form) => {
        const file = form.querySelector('input[type="file"][name="logo"]');
        const remove = form.querySelector('input[name="remove_logo"]');
        const filename = form.querySelector('.ctt-logo-file-name');
        const save = form.querySelector('.ctt-logo-save');
        const pick = form.querySelector('.ctt-logo-pick span');
        const originalLabel = pick?.textContent || 'Change';

        function syncLogoActions() {
            const selected = file?.files?.[0];
            const removing = Boolean(remove?.checked) && !selected;
            if (filename) {
                filename.textContent = selected?.name || '';
                filename.hidden = !selected;
                filename.title = selected?.name || '';
            }
            if (save) save.hidden = !(selected || removing);
            if (pick) pick.textContent = selected ? 'Change file' : originalLabel;
            if (remove) remove.disabled = Boolean(selected);
        }
        file?.addEventListener('change', syncLogoActions);
        remove?.addEventListener('change', syncLogoActions);
        syncLogoActions();
    });

    // Expand large allocation lists only when requested; leave the client card open.
    document.querySelectorAll('[data-ctt-buses-toggle]').forEach((button) => {
        const list = document.getElementById(button.getAttribute('aria-controls') || '');
        if (!list) return;
        button.addEventListener('click', () => {
            const expanding = list.hidden;
            list.hidden = !expanding;
            button.setAttribute('aria-expanded', String(expanding));
            const label = button.querySelector('span');
            if (label) label.textContent = expanding ? 'Show fewer buses' : button.dataset.collapsedLabel;
        });
    });

    const toggleAllButton = document.getElementById('cttToggleAllClients');
    const clientCards = Array.from(document.querySelectorAll('.ctt-client-card'));

    function updateToggleAllButton() {
        if (!toggleAllButton || clientCards.length === 0) return;

        const allOpen = clientCards.every((card) => card.open);
        const icon = toggleAllButton.querySelector('i');
        const label = toggleAllButton.querySelector('span');

        if (label) label.textContent = allOpen ? 'Collapse all' : 'Expand all';
        if (icon) {
            icon.classList.toggle('fa-angles-up', allOpen);
            icon.classList.toggle('fa-angles-down', !allOpen);
        }
    }

    toggleAllButton?.addEventListener('click', () => {
        const shouldExpand = clientCards.some((card) => !card.open);
        clientCards.forEach((card) => {
            card.open = shouldExpand;
        });
        updateToggleAllButton();
    });

    clientCards.forEach((card) => {
        card.addEventListener('toggle', updateToggleAllButton);
    });

    // The shared table toolbar normally submits a GET request. This page manages
    // already-rendered client cards, so Apply Filter/Sort must stay client-side.
    clientToolbar?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyClientSearch();
        clientToolbar.querySelectorAll('[data-table-tool-trigger]').forEach((trigger) => {
            const kind = trigger.dataset.tableToolTrigger;
            const active = kind === 'search' ? Boolean(clientSearch?.value.trim())
                : kind === 'filter' ? clientFilter?.value !== 'all'
                : kind === 'sort' ? clientSort?.value !== 'name-asc'
                : false;
            trigger.classList.toggle('is-active', active);
        });
        const filterCount = clientToolbar.querySelector('[data-table-filter-count]');
        if (filterCount) {
            const count = Number(clientFilter?.value !== 'all');
            filterCount.textContent = String(count);
            filterCount.hidden = count === 0;
        }
    });
    // Client Master List links use ?search=<client> to open the matching
    // configurations without creating a second scheduling interface.
    if (clientSearch) {
        const linkedClientName = new URLSearchParams(window.location.search).get('search');
        if (linkedClientName) clientSearch.value = linkedClientName;
    }
    clientSearch?.addEventListener('input', applyClientSearch);
    applyClientSearch();
    updateToggleAllButton();


});
