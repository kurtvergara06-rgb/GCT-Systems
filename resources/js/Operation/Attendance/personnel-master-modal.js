window.GCTPartialNavigation.registerInitializer('operation-personnel-master', '.personnel-master-page', () => {
    const page = document.querySelector('.personnel-master-page');
    const modal = document.querySelector('[data-personnel-modal]');
    const form = modal?.querySelector('[data-personnel-form]');

    if (!page || !modal || !form) return;

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

    const resetForm = () => {
        form.reset();
        form.action = form.dataset.storeUrl;
        methodField.value = 'POST';
        if (editingPersonnelId) editingPersonnelId.value = '';

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
            field.value = record[field.name] ?? '';
        });

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

        setSpecializations(specializationValue?.value || '');
        openModal();
    } else {
        setSpecializations('');
    }
});
