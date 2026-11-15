document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-personnel-modal]');
    const form = modal?.querySelector('[data-personnel-form]');

    if (!modal || !form) return;

    const title = modal.querySelector('[data-modal-title]');
    const subtitle = modal.querySelector('[data-modal-subtitle]');
    const methodField = form.querySelector('[data-method-field]');
    const submitButton = form.querySelector('[data-submit-button]');
    const fields = [...form.querySelectorAll('input[name]:not([name="_token"]):not([name="_method"]), select[name]')];
    const entity = form.querySelector('[name="driver_id"]') ? 'Driver' : 'Mechanic';

    const openModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('personnel-modal-open');
        window.setTimeout(() => fields[0]?.focus(), 50);
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('personnel-modal-open');
    };

    const resetForm = () => {
        form.reset();
        form.action = form.dataset.storeUrl;
        methodField.value = 'POST';
        fields.forEach((field) => {
            field.disabled = false;
            field.readOnly = false;
        });
        submitButton.hidden = false;
    };

    const fillForm = (record) => {
        fields.forEach((field) => {
            field.value = record[field.name] ?? '';
        });
    };

    document.querySelectorAll('[data-personnel-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.dataset.personnelAction;
            resetForm();

            if (mode === 'add') {
                title.textContent = `Add New ${entity}`;
                subtitle.textContent = `Create a permanent ${entity.toLowerCase()} profile. Attendance is recorded separately.`;
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
                form.action = button.dataset.updateUrl;
                methodField.value = 'PUT';
                title.textContent = `Edit ${entity}`;
                subtitle.textContent = `Update permanent ${entity.toLowerCase()} information without changing attendance history.`;
                submitButton.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Update ${entity}`;
            } else {
                title.textContent = `${entity} Details`;
                subtitle.textContent = `View the permanent ${entity.toLowerCase()} profile.`;
                fields.forEach((field) => {
                    if (field.tagName === 'SELECT') field.disabled = true;
                    else field.readOnly = true;
                });
                submitButton.hidden = true;
            }

            openModal();
        });
    });

    modal.querySelectorAll('[data-close-personnel-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    if (modal.dataset.openOnError === 'true') openModal();
});
