window.GCTPartialNavigation.registerInitializer('operation-client-trip-times', '.client-trip-times-page', () => {
    const baseUrl = '/operation/client-trip-times';

    const configModal = document.getElementById('cttConfigModal');
    const slotModal = document.getElementById('cttSlotModal');
    const configForm = document.getElementById('cttConfigForm');
    const slotForm = document.getElementById('cttSlotForm');

    const configClientId = document.getElementById('cttConfigClientId');
    const configCategory = document.getElementById('cttConfigCategory');
    const configClientName = document.getElementById('cttConfigClientName');
    const configCategoryLabel = document.getElementById('cttConfigCategoryLabel');
    const configStart = document.getElementById('cttConfigStart');
    const configEnd = document.getElementById('cttConfigEnd');
    const configActive = document.getElementById('cttConfigActive');
    const configTitle = document.getElementById('cttConfigTitle');
    const configSubmitText = document.getElementById('cttConfigSubmitText');

    const slotId = document.getElementById('cttSlotId');
    const slotTime = document.getElementById('cttSlotTime');
    const slotEnabled = document.getElementById('cttSlotEnabled');
    const slotRemove = document.getElementById('cttSlotRemove');

    const clientSearch = document.getElementById('cttClientSearch');
    const clientCount = document.getElementById('cttClientCount');
    const searchEmpty = document.getElementById('cttSearchEmpty');

    let activeSlotContainer = null;
    let configUpdateUrl = null;

    const categoryLabels = {
        incoming_am: 'Incoming AM',
        incoming_pm: 'Incoming PM',
        outgoing_am: 'Outgoing AM',
        outgoing_pm: 'Outgoing PM',
    };

    function getCsrfToken() {
        return configForm?.querySelector('[name="_token"]')?.value
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

    function openConfigModal({ clientId, clientName, category, startTime, endTime, isActive, updateUrl }) {
        if (!configForm) return;

        clearErrors(configForm);
        configUpdateUrl = updateUrl || null;

        configClientId.value = clientId || '';
        configCategory.value = category || '';
        configClientName.textContent = clientName || '—';
        configCategoryLabel.textContent = categoryLabels[category] || category || '—';
        configStart.value = startTime || '';
        configEnd.value = endTime || '';
        configActive.checked = isActive !== false;

        if (configTitle) {
            configTitle.textContent = updateUrl ? 'Edit Trip Time Range' : 'New Trip Time Range';
        }

        if (configSubmitText) {
            configSubmitText.textContent = updateUrl ? 'Save Configuration' : 'Create Configuration';
        }

        configForm.setAttribute('action', updateUrl || baseUrl);
        openModal(configModal);
    }

    configForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        clearErrors(configForm);

        const payload = {
            start_time: configStart.value,
            end_time: configEnd.value,
            is_active: configActive.checked ? 1 : 0,
        };

        if (!configUpdateUrl) {
            payload.operation_client_id = configClientId.value;
            payload.category = configCategory.value;
        }

        try {
            const data = await postJson(
                configUpdateUrl
                    ? `${configUpdateUrl}`
                    : baseUrl,
                configUpdateUrl
                    ? { ...payload, _method: 'PUT' }
                    : payload
            );

            closeModal(configModal);
            await refreshPanelByUrl(data.panel_url);
        } catch (error) {
            if (error.errors && Object.keys(error.errors).length) {
                renderErrors(configForm, error.errors);
                return;
            }

            renderErrors(configForm, {
                start_time: [error.message || 'The configuration could not be saved.'],
            });
        }
    });

    slotForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        clearErrors(slotForm);

        const slotUrl = `${baseUrl}/slots/${slotId.value}`;

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
            openConfigModal({
                clientId: addConfigButton.dataset.clientId,
                clientName: addConfigButton.dataset.clientName,
                category: addConfigButton.dataset.category,
                isActive: true,
            });
            return;
        }

        const editRangeButton = event.target.closest('.ctt-edit-range');

        if (editRangeButton) {
            const container = panelContainerFor(editRangeButton);
            openConfigModal({
                clientId: container?.dataset.clientId,
                clientName: container?.closest('.ctt-client-card')
                    ?.querySelector('.ctt-client-heading h3')?.textContent?.trim(),
                category: container?.dataset.category,
                startTime: editRangeButton.dataset.startTime,
                endTime: editRangeButton.dataset.endTime,
                isActive: editRangeButton.dataset.isActive === '1',
                updateUrl: `${baseUrl}/${editRangeButton.dataset.configId}`,
            });
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

        try {
            await postJson(`${baseUrl}/${addSlotForm.dataset.configId}/slots`, {
                slot_time: slotInput.value,
            });

            slotInput.value = '';
            await refreshPanel(container);
        } catch (error) {
            if (errorTarget) {
                errorTarget.textContent = Object.values(error.errors || {})[0]?.[0]
                    || error.message
                    || 'The slot could not be added.';
            }
        }
    });

    [configModal, slotModal].forEach((modal) => {
        modal?.querySelectorAll('[data-ui-modal-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeModal(configModal);
        closeModal(slotModal);
    });

    function applyClientSearch() {
        const query = String(clientSearch?.value || '').trim().toLowerCase();
        const cards = Array.from(document.querySelectorAll('.ctt-client-card'));
        let visible = 0;

        cards.forEach((card) => {
            const matches = !query || card.dataset.clientName.includes(query);
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

    clientSearch?.addEventListener('input', applyClientSearch);
});
