window.GCTPartialNavigation.registerInitializer('operation-personnel-master', '.personnel-master-page', () => {
    const page = document.querySelector('.personnel-master-page');
    const modal = document.querySelector('[data-personnel-modal]');
    const form = modal?.querySelector('[data-personnel-form]');

    if (!page || !modal || !form) return;

    // Portal the table disclosure into the document overlay layer so it never
    // changes the table row height or gets clipped by the scrollable table.
    let openBusDisclosure = null;
    const closeBusDisclosure = () => {
        if (!openBusDisclosure) return;
        const { details, panel } = openBusDisclosure;
        panel.removeAttribute('style');
        details.appendChild(panel);
        details.open = false;
        openBusDisclosure = null;
    };
    page.addEventListener('click', (event) => {
        const trigger = event.target.closest('.driver-bus-more > summary');
        if (!trigger || !page.contains(trigger)) return;
        event.preventDefault();
        const details = trigger.parentElement;
        if (openBusDisclosure?.details === details) {
            closeBusDisclosure();
            return;
        }
        closeBusDisclosure();
        const panel = details.querySelector('.driver-bus-more-panel');
        if (!panel) return;
        details.open = true;
        document.body.appendChild(panel);
        const rect = trigger.getBoundingClientRect();
        const width = 225;
        const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
        panel.style.position = 'fixed';
        panel.style.width = width + 'px';
        panel.style.maxHeight = 'min(260px, 55vh)';
        panel.style.overflowY = 'auto';
        panel.style.left = left + 'px';
        panel.style.top = '0px';
        panel.style.margin = '0';
        panel.style.zIndex = '10050';
        const height = panel.getBoundingClientRect().height;
        const below = window.innerHeight - rect.bottom - 8;
        const top = below >= height || below >= rect.top
            ? Math.min(rect.bottom + 5, window.innerHeight - height - 8)
            : Math.max(8, rect.top - height - 5);
        panel.style.top = Math.max(8, top) + 'px';
        openBusDisclosure = { details, panel };
    });
    document.addEventListener('click', (event) => {
        if (openBusDisclosure && !openBusDisclosure.details.contains(event.target)
            && !openBusDisclosure.panel.contains(event.target)) closeBusDisclosure();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && openBusDisclosure) closeBusDisclosure();
    });
    const onBusDisclosureScroll = () => closeBusDisclosure();
    document.addEventListener('scroll', onBusDisclosureScroll, true);
    window.addEventListener('resize', onBusDisclosureScroll);

    const title = modal.querySelector('[data-modal-title]');
    const subtitle = modal.querySelector('[data-modal-subtitle]');
    const headerIcon = modal.querySelector('[data-personnel-modal-icon]');
    const viewNotice = modal.querySelector('[data-personnel-view-notice]');
    const methodField = form.querySelector('[data-method-field]');
    const editingPersonnelId = form.querySelector('[data-editing-personnel-id]');
    const submitButton = form.querySelector('[data-submit-button]');
    const modalActions = form.querySelector('.personnel-modal-actions');
    const fields = [...form.querySelectorAll('input[name]:not([name="_token"]):not([name="_method"]):not([name="editing_personnel_id"]), select[name]')];
    const entity = form.querySelector('[name="driver_id"]') ? 'Driver' : 'Mechanic';
    const busPicker = form.querySelector('[data-driver-bus-picker]');
    const busTrigger = busPicker?.querySelector('[data-driver-bus-trigger]');
    const busPanel = busPicker?.querySelector('[data-driver-bus-panel]');
    const busLabel = busPicker?.querySelector('[data-driver-bus-label]');
    const busSearch = busPicker?.querySelector('[data-driver-bus-search]');
    const busOptions = [...(busPicker?.querySelectorAll('[data-driver-bus-option]') || [])];
    const busChecks = [...(busPicker?.querySelectorAll('[data-driver-bus-checkbox]') || [])];
    const busEmpty = busPicker?.querySelector('[data-driver-bus-empty]');
    const busChips = form.querySelector('[data-driver-bus-chips]');
    const updateBusPicker = () => {
        if (!busLabel) return;
        const selected = busChecks.filter((check) => check.checked);
        busLabel.textContent = selected.length
            ? `${selected.length} bus${selected.length === 1 ? '' : 'es'} selected`
            : 'Choose buses';
        if (!busChips) return;
        busChips.replaceChildren();
        selected.forEach((check) => {
            const chip = document.createElement('span');
            chip.className = 'driver-bus-chip';
            const label = document.createElement('span');
            label.textContent = check.dataset.busNumber || check.value;
            chip.append(label);
            if (modal.dataset.mode !== 'view') {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'driver-bus-chip-remove';
                remove.setAttribute('aria-label', `Remove ${label.textContent}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    check.checked = false;
                    updateBusPicker();
                });
                chip.append(remove);
            }
            busChips.append(chip);
        });
    };
    const closeBusPicker = () => {
        if (busPanel) busPanel.hidden = true;
        busTrigger?.setAttribute('aria-expanded', 'false');
    };
    busTrigger?.addEventListener('click', () => {
        if (busTrigger.disabled || modal.dataset.mode === 'view') return;
        // Reapply ownership every time: a reused modal may retain stale DOM state.
        refreshBusAuthorizationOptions();
        if (busPanel) busPanel.hidden = !busPanel.hidden;
        busTrigger.setAttribute('aria-expanded', String(!busPanel?.hidden));
        if (!busPanel?.hidden) busSearch?.focus();
    });
    busChecks.forEach((check) => check.addEventListener('change', updateBusPicker));
    busSearch?.addEventListener('input', () => {
        const query = busSearch.value.trim().toLowerCase();
        let count = 0;
        busOptions.forEach((option) => {
            const match = !option.querySelector('[data-driver-bus-checkbox]')?.disabled
                && option.textContent.toLowerCase().includes(query);
            option.hidden = !match;
            if (match) count++;
        });
        if (busEmpty) busEmpty.hidden = count > 0;
    });
    document.addEventListener('click', (event) => {
        if (busPicker && !busPicker.contains(event.target)) closeBusPicker();
    });


    const specializationValue = form.querySelector('[data-specialization-value]');
    const specializationPicker = form.querySelector('[data-specialization-picker]');
    const specializationSelected = form.querySelector('[data-specialization-selected]');
    const specializationCount = form.querySelector('[data-specialization-count]');
    const specializationCountNumber = form.querySelector('[data-specialization-count-number]');
    const specializationSearch = form.querySelector('[data-specialization-search]');
    const specializationOptions = [...form.querySelectorAll('[data-specialization-option]')];

    let selectedSpecializations = [];
    let specializationReadOnly = false;

    const normalizeSpecializations = (value) => {
        const seen = new Set();

        return String(value || '')
            .split(/[,;]+/)
            .map((item) => item.trim())
            .filter((item) => {
                const key = item.toLowerCase();

                if (!item || seen.has(key)) return false;

                seen.add(key);
                return true;
            });
    };

    const hasSpecialization = (value) => selectedSpecializations.some(
        (item) => item.toLowerCase() === String(value).toLowerCase()
    );

    const updateSpecializationControls = () => {
        if (specializationValue) {
            specializationValue.value = selectedSpecializations.join(', ');
        }

        if (specializationCount) {
            const count = selectedSpecializations.length;
            specializationCount.textContent = `${count} selected`;
        }

        if (specializationCountNumber) {
            specializationCountNumber.textContent = String(selectedSpecializations.length);
        }

        specializationOptions.forEach((button) => {
            const active = hasSpecialization(button.dataset.specializationOption);
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.disabled = specializationReadOnly;

            const stateIcon = button.querySelector('.personnel-specialization-state-icon');
            if (stateIcon) {
                stateIcon.className = active
                    ? 'fa-solid fa-check personnel-specialization-state-icon'
                    : 'fa-solid fa-plus personnel-specialization-state-icon';
            }
        });

        if (specializationSearch) {
            specializationSearch.disabled = specializationReadOnly;
            specializationSearch.hidden = specializationReadOnly;
        }

        if (!specializationSelected) return;

        specializationSelected.innerHTML = '';

        if (selectedSpecializations.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'personnel-specialization-empty';
            empty.innerHTML = `
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <strong>No specializations selected yet</strong>
                <span>Choose one or more specializations from above.</span>
            `;
            specializationSelected.appendChild(empty);
            return;
        }

        selectedSpecializations.forEach((specialization) => {
            const chip = document.createElement('span');
            chip.className = 'personnel-specialization-selected-chip';

            const label = document.createElement('span');
            label.textContent = specialization;
            chip.appendChild(label);

            if (!specializationReadOnly) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', `Remove ${specialization}`);
                remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                remove.addEventListener('click', () => {
                    selectedSpecializations = selectedSpecializations.filter(
                        (item) => item.toLowerCase() !== specialization.toLowerCase()
                    );
                    updateSpecializationControls();
                });
                chip.appendChild(remove);
            }

            specializationSelected.appendChild(chip);
        });
    };

    const setSpecializations = (value) => {
        selectedSpecializations = normalizeSpecializations(value);
        updateSpecializationControls();
    };

    const addSpecialization = (value) => {
        const specialization = String(value || '').trim();

        if (!specialization || hasSpecialization(specialization)) return;

        selectedSpecializations.push(specialization);
        updateSpecializationControls();
    };

    const toggleSpecialization = (value) => {
        if (specializationReadOnly) return;

        if (hasSpecialization(value)) {
            selectedSpecializations = selectedSpecializations.filter(
                (item) => item.toLowerCase() !== String(value).toLowerCase()
            );
        } else {
            selectedSpecializations.push(String(value).trim());
        }

        updateSpecializationControls();
    };

    specializationPicker?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-specialization-option]');
        if (!button) return;

        toggleSpecialization(button.dataset.specializationOption);
    });

    specializationSearch?.addEventListener('input', () => {
        const query = specializationSearch.value.trim().toLowerCase();

        specializationOptions.forEach((button) => {
            const label = String(button.dataset.specializationOption || '').toLowerCase();
            button.hidden = query !== '' && !label.includes(query);
        });
    });

    // Persist newly created categories before offering them for mechanic selection.
    const specializationCreateToggle = form.querySelector('[data-specialization-create-toggle]');
    const specializationCreateName = form.querySelector('[data-specialization-create-name]');
    const specializationCreateSave = form.querySelector('[data-specialization-create-save]');
    const specializationCreateLabel = form.querySelector('[data-specialization-create-label]');
    const specializationCreateFeedback = form.querySelector('[data-specialization-create-feedback]');
    let specializationCreating = false;

    const setSpecializationCreating = (enabled) => {
        specializationCreating = enabled && !specializationReadOnly;
        if (specializationCreateName) specializationCreateName.hidden = !specializationCreating;
        if (specializationCreateLabel) specializationCreateLabel.textContent = specializationCreating ? 'Add' : 'New';
        specializationCreateToggle?.setAttribute('aria-expanded', String(specializationCreating));
        if (specializationCreating) specializationCreateName?.focus();
    };

    specializationCreateToggle?.addEventListener('click', async () => {
        if (specializationReadOnly || !specializationCreateSave) return;
        if (!specializationCreating) {
            setSpecializationCreating(true);
            return;
        }
        const name = String(specializationCreateName?.value || '').trim();
        if (!name) {
            if (specializationCreateFeedback) specializationCreateFeedback.textContent = 'Enter a specialization name.';
            specializationCreateName?.focus();
            return;
        }
        specializationCreateSave.disabled = true;
        if (specializationCreateFeedback) specializationCreateFeedback.textContent = '';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || form.querySelector('input[name="_token"]')?.value;
            const response = await fetch(specializationCreateSave.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
                body: JSON.stringify({ name }),
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to save specialization.');
            }
            const saved = String(result.name || name);
            if (!specializationOptions.some(button => button.dataset.specializationOption.toLowerCase() === saved.toLowerCase())) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'personnel-specialization-option tone-blue';
                button.dataset.specializationOption = saved;
                button.setAttribute('aria-pressed', 'false');
                const label = document.createElement('span');
                label.textContent = saved;
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-plus personnel-specialization-state-icon';
                button.append(label, icon);
                specializationPicker?.appendChild(button);
                specializationOptions.push(button);
            }
            if (result.delete_url && ![...specializationPicker.querySelectorAll('[data-specialization-delete]')]
                .some(button => button.dataset.specializationName.toLowerCase() === saved.toLowerCase())) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'personnel-specialization-delete-btn';
                remove.dataset.specializationDelete = '';
                remove.dataset.specializationName = saved;
                remove.dataset.endpoint = result.delete_url;
                remove.setAttribute('aria-label', 'Delete custom specialization ' + saved);
                remove.title = 'Delete custom specialization ' + saved;
                remove.style.cssText = 'min-height:18px;min-width:18px;padding:0 3px;font-size:10px;border:1px solid #fecaca;border-radius:6px;background:#fff1f2;color:#b91c1c;cursor:pointer;align-items:center;justify-content:center';
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-xmark';
                icon.setAttribute('aria-hidden', 'true');
                remove.appendChild(icon);
                specializationPicker.appendChild(remove);
                renderSpecializationEditMode();
            }
            addSpecialization(saved);
            if (specializationCreateName) specializationCreateName.value = '';
            setSpecializationCreating(false);
            if (specializationCreateFeedback) specializationCreateFeedback.textContent = '';
            window.showSystemToast?.(saved + ' added successfully.', 'success', 'Specialization');
        } catch (error) {
            if (specializationCreateFeedback) specializationCreateFeedback.textContent = '';
            window.showSystemToast?.(error.message, 'error', 'Specialization');
            specializationCreateName?.focus();
        } finally {
            specializationCreateSave.disabled = false;
        }
    });

    const specializationEditToggle = form.querySelector('[data-specialization-edit-toggle]');
    const specializationEditLabel = form.querySelector('[data-specialization-edit-label]');
    let specializationEditing = false;
    const pendingSpecializationDeletes = new Set();

    const renderSpecializationEditMode = () => {
        specializationPicker?.querySelectorAll('[data-specialization-delete]').forEach((button) => {
            button.hidden = !specializationEditing;
            button.style.display = specializationEditing ? 'inline-flex' : 'none';
            button.disabled = pendingSpecializationDeletes.has(button.dataset.specializationName.toLowerCase());
            button.title = button.disabled ? 'Marked for deletion' : 'Remove custom specialization';
        });
        if (specializationEditLabel) specializationEditLabel.textContent = specializationEditing ? 'Save' : 'Edit';
        specializationEditToggle?.setAttribute('aria-pressed', String(specializationEditing));
    };

    specializationPicker?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-specialization-delete]');
        if (!remove || !specializationEditing || specializationReadOnly) return;
        event.preventDefault();
        event.stopPropagation();
        const name = String(remove.dataset.specializationName || '').trim();
        if (!name) return;
        pendingSpecializationDeletes.add(name.toLowerCase());
        const option = specializationOptions.find(button =>
            String(button.dataset.specializationOption || '').toLowerCase() === name.toLowerCase()
        );
        option?.classList.add('is-pending-deletion');
        renderSpecializationEditMode();
    });

    specializationEditToggle?.addEventListener('click', async () => {
        if (specializationReadOnly) return;
        if (!specializationEditing) {
            specializationEditing = true;
            setSpecializationCreating(false);
            renderSpecializationEditMode();
            return;
        }
        if (pendingSpecializationDeletes.size === 0) {
            specializationEditing = false;
            renderSpecializationEditMode();
            return;
        }
        if (typeof window.openSystemConfirmation !== 'function') {
            window.showSystemToast?.('Confirmation dialog is unavailable. Please reload the page.', 'error', 'Specializations');
            return;
        }
        const confirmed = await new Promise((resolve) => {
            let accepted = false;
            window.openSystemConfirmation({
                title: 'Delete Specializations?',
                message: 'Delete the selected unused specializations? This action cannot be undone. Specializations assigned to mechanics will not be deleted.',
                button: 'Yes, Delete',
                type: 'delete',
                closeOnConfirm: true,
            }, () => {
                accepted = true;
                resolve(true);
            });
            // Cancellation leaves this promise pending; opening another confirmation
            // replaces the callback. The pending promise does not mutate data.
        });
        if (!confirmed) return;
        specializationEditToggle.disabled = true;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content
            || form.querySelector('input[name="_token"]')?.value;
        let successes = 0;
        let failures = [];
        for (const button of [...specializationPicker.querySelectorAll('[data-specialization-delete]')]) {
            const name = String(button.dataset.specializationName || '');
            if (!pendingSpecializationDeletes.has(name.toLowerCase())) continue;
            try {
                const response = await fetch(button.dataset.endpoint, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Deletion failed.');
                const option = specializationOptions.find(item =>
                    String(item.dataset.specializationOption || '').toLowerCase() === name.toLowerCase()
                );
                if (option) {
                    specializationOptions.splice(specializationOptions.indexOf(option), 1);
                    option.remove();
                }
                button.remove();
                pendingSpecializationDeletes.delete(name.toLowerCase());
                selectedSpecializations = selectedSpecializations.filter(item => item.toLowerCase() !== name.toLowerCase());
                successes += 1;
            } catch (error) {
                failures.push(name + ': ' + error.message);
                const option = specializationOptions.find(item => item.dataset.specializationOption.toLowerCase() === name.toLowerCase());
                option?.classList.remove('is-pending-deletion');
                pendingSpecializationDeletes.delete(name.toLowerCase());
            }
        }
        updateSpecializationControls();
        specializationEditing = false;
        renderSpecializationEditMode();
        specializationEditToggle.disabled = false;
        if (successes) window.showSystemToast?.(successes + ' specialization(s) deleted.', 'success', 'Specializations');
        if (failures.length) window.showSystemToast?.(failures.join(' '), 'error', 'Unable to delete');
    });

    specializationCreateName?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            specializationCreateToggle?.click();
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            setSpecializationCreating(false);
            specializationCreateToggle?.focus();
        }
    });

    const openModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('personnel-modal-open');
        window.setTimeout(() => {
            const focusTarget = modal.dataset.mode === 'view'
                ? modal.querySelector('[data-close-personnel-modal]')
                : fields.find((field) => field.type !== 'hidden' && !field.disabled);
            focusTarget?.focus();
        }, 50);
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('personnel-modal-open');
    };

    const setSpecializationReadOnly = (readOnly) => {
        specializationReadOnly = readOnly;
        updateSpecializationControls();
    };

    const refreshBusAuthorizationOptions = () => {
        if (entity !== 'Driver') return;
        const currentDriverId = String(editingPersonnelId?.value || '');
        busOptions.forEach((option) => {
            const check = option.querySelector('[data-driver-bus-checkbox]');
            if (!check) return;
            const ownerId = String(check.dataset.authorizedDriverId || '');
            const assignedToAnotherDriver = ownerId !== '' && ownerId !== currentDriverId;
            check.disabled = assignedToAnotherDriver;
            // Never retain a checked option from a previously edited driver.
            if (assignedToAnotherDriver) check.checked = false;
            option.hidden = assignedToAnotherDriver;
            option.title = '';
        });
        const query = busSearch?.value.trim().toLowerCase() || '';
        let availableCount = 0;
        busOptions.forEach((option) => {
            const check = option.querySelector('[data-driver-bus-checkbox]');
            const match = !check?.disabled && option.textContent.toLowerCase().includes(query);
            option.hidden = !match;
            if (match) availableCount++;
        });
        if (busEmpty) busEmpty.hidden = availableCount > 0;
        updateBusPicker();
    };

    const resetForm = () => {
        form.reset();
        // Reset saved state explicitly; form.reset() restores Blade defaults.
        busChecks.forEach((check) => {
            check.checked = false;
            check.defaultChecked = false;
            check.disabled = false;
        });
        if (busTrigger) busTrigger.disabled = false;
        if (busSearch) busSearch.value = '';
        busOptions.forEach((option) => {
            option.hidden = false;
        });
        if (busEmpty) busEmpty.hidden = true;
        closeBusPicker();
        updateBusPicker();
        form.action = form.dataset.storeUrl;
        methodField.value = 'POST';
        if (editingPersonnelId) editingPersonnelId.value = '';
        refreshBusAuthorizationOptions();

        fields.forEach((field) => {
            field.disabled = false;
            field.readOnly = false;
        });

        if (specializationSearch) {
            specializationSearch.value = '';
            specializationOptions.forEach((button) => {
                button.hidden = false;
            });
        }

        modal.dataset.mode = 'edit';
        updateBusPicker();
        if (viewNotice) viewNotice.hidden = true;
        setSpecializationReadOnly(false);
        setSpecializations('');
        submitButton.hidden = false;
        submitButton.style.removeProperty('display');
        if (modalActions) {
            modalActions.hidden = false;
            modalActions.style.removeProperty('display');
        }
    };

    const fillForm = (record) => {
        fields.forEach((field) => {
            if (field.multiple) {
                const selected = new Set((record.bus_ids || []).map(String));
                [...field.options].forEach((option) => {
                    option.selected = selected.has(option.value);
                });
            } else if (field.type === 'checkbox' && field.name === 'bus_ids[]') {
                // Preserve numeric checkbox values; the selection is restored below.
                return;
            } else {
                field.value = record[field.name] ?? '';
            }
        });

        if (busChecks.length) {
            const selectedIds = new Set((record.bus_ids || []).map(String));
            busChecks.forEach((check) => { check.checked = selectedIds.has(check.value); });
            updateBusPicker();
        }
        setSpecializations(record.specialization ?? '');
    };

    const handlePersonnelAction = (button) => {
        const mode = button.dataset.personnelAction;
        resetForm();

        if (mode === 'add') {
            modal.dataset.mode = 'add';
            if (headerIcon) headerIcon.className = 'fa-solid fa-id-card';
            title.textContent = `Add New ${entity}`;
            subtitle.textContent = `Fill in the details below to create a ${entity.toLowerCase()} profile. Attendance is recorded separately.`;
            submitButton.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Save ${entity}`;
            openModal();
            return;
        }

        let record = {};
        try {
            record = JSON.parse(button.dataset.record || '{}');
        } catch (error) {
            console.error('Unable to read personnel record.', error);
            return;
        }

        fillForm(record);

        if (mode === 'edit') {
            modal.dataset.mode = 'edit';
            if (headerIcon) headerIcon.className = 'fa-solid fa-pen-to-square';
            form.action = button.dataset.updateUrl;
            methodField.value = 'PUT';
            if (editingPersonnelId) {
                editingPersonnelId.value = button.dataset.recordId || '';
            }
            refreshBusAuthorizationOptions();
            // Preserve identifiers referenced by historical attendance and trips.
            if (entity === 'Driver' && record.identity_locked) {
                for (const name of ['driver_id', 'driver_name']) {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input) {
                        input.readOnly = true;
                        input.title = 'Locked to preserve historical attendance and trip records';
                    }
                }
            }
            title.textContent = `Edit ${entity}`;
            subtitle.textContent = `Update permanent ${entity.toLowerCase()} information without changing attendance history.`;
            submitButton.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Update ${entity}`;
        } else {
            modal.dataset.mode = 'view';
            if (headerIcon) headerIcon.className = 'fa-solid fa-eye';
            if (viewNotice) viewNotice.hidden = false;
            title.textContent = `${entity} Details`;
            subtitle.textContent = `View the permanent ${entity.toLowerCase()} profile.`;

            fields.forEach((field) => {
                field.disabled = true;
                field.readOnly = true;
            });

            setSpecializationReadOnly(true);
            if (busTrigger) busTrigger.disabled = true;
            busChecks.forEach((check) => { check.disabled = true; });
            closeBusPicker();
            updateBusPicker();

            submitButton.hidden = true;
            submitButton.style.display = 'none';

            if (modalActions) {
                modalActions.hidden = true;
                modalActions.style.display = 'none';
            }
        }

        openModal();
    };

    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-personnel-action]');
        if (!button || !page.contains(button)) return;

        handlePersonnelAction(button);
    });

    modal.querySelectorAll('[data-close-personnel-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && busPanel && !busPanel.hidden) {
            closeBusPicker();
            busTrigger?.focus();
            return;
        }
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    form.addEventListener('submit', () => {
        if (specializationValue) {
            specializationValue.value = selectedSpecializations.join(', ');
        }
    });

    if (modal.dataset.openOnError === 'true') {
        const editingId = String(editingPersonnelId?.value || '').trim();
        const editButton = editingId
            ? page.querySelector(
                `[data-personnel-action="edit"][data-record-id="${CSS.escape(editingId)}"]`
            )
            : null;

        if (editButton) {
            modal.dataset.mode = 'edit';
            form.action = editButton.dataset.updateUrl;
            methodField.value = 'PUT';
            if (headerIcon) headerIcon.className = 'fa-solid fa-pen-to-square';
            title.textContent = `Edit ${entity}`;
            subtitle.textContent = `Correct the highlighted fields for this ${entity.toLowerCase()} record.`;
            submitButton.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Update ${entity}`;
        }

        refreshBusAuthorizationOptions();
        setSpecializations(specializationValue?.value || '');
        openModal();
    } else {
        setSpecializations('');
    }
});
