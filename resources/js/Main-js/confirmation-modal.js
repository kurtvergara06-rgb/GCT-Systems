document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('globalConfirmationModal');
    const modalTitle = document.getElementById('globalConfirmationTitle');
    const modalMessage = document.getElementById('globalConfirmationMessage');
    const modalIcon = document.getElementById('globalConfirmationIcon');
    const confirmButton = document.getElementById('confirmGlobalAction');
    const cancelButton = document.getElementById('cancelGlobalConfirmation');

    if (!modal || !modalTitle || !modalMessage || !modalIcon || !confirmButton || !cancelButton) {
        return;
    }

    let pendingForm = null;
    let pendingCallback = null;
    let pendingSubmitter = null;
    let isSubmitting = false;

    function resetConfirmButton() {
        if (window.GCTLoading?.reset) {
            window.GCTLoading.reset(confirmButton);
        } else {
            confirmButton.disabled = false;
            confirmButton.removeAttribute('aria-busy');
        }

        confirmButton.classList.remove('is-loading');
    }

    function setConfirmLoading() {
        confirmButton.classList.add('is-loading');

        if (window.GCTLoading?.set) {
            window.GCTLoading.set(confirmButton);
            return;
        }

        confirmButton.disabled = true;
        confirmButton.setAttribute('aria-busy', 'true');
    }

    function openModal() {
        modal.style.display = 'flex';
        modal.classList.add('show', 'active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        window.setTimeout(() => confirmButton.focus(), 50);
    }

    function closeModal() {
        modal.classList.remove('show', 'active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');

        resetConfirmButton();

        pendingForm = null;
        pendingCallback = null;
        pendingSubmitter = null;
        isSubmitting = false;
        document.body.style.overflow = '';
    }

    function getFormMethod(form) {
        return String(form?.getAttribute('method') || 'GET').toUpperCase();
    }

    function shouldConfirmForm(form) {
        return Boolean(
            form &&
            form.matches('[data-confirm-form]') &&
            getFormMethod(form) !== 'GET'
        );
    }

    function getIcon(type) {
        switch (type) {
            case 'delete':
                return { className: 'danger', icon: 'fa-triangle-exclamation' };
            case 'create':
                return { className: 'create', icon: 'fa-plus' };
            case 'update':
                return { className: 'update', icon: 'fa-pen-to-square' };
            case 'approve':
                return { className: 'approve', icon: 'fa-check' };
            case 'reject':
                return { className: 'reject', icon: 'fa-xmark' };
            case 'issue':
                return { className: 'success', icon: 'fa-box-open' };
            case 'status':
                return { className: 'warning', icon: 'fa-arrows-rotate' };
            default:
                return { className: 'warning', icon: 'fa-triangle-exclamation' };
        }
    }

    function getConfirmButtonClasses(type) {
        switch (type) {
            case 'approve':
                return ['global-confirm-btn', 'approve-confirm-btn'];
            case 'reject':
            case 'delete':
                return ['global-confirm-btn', 'danger-btn'];
            default:
                return ['global-confirm-btn', 'primary-btn'];
        }
    }

    function configureModal(options = {}) {
        resetConfirmButton();

        const type = options.type || 'warning';
        const iconData = getIcon(type);

        modalTitle.textContent = options.title || 'Confirm Action';

        if (options.messageHtml) {
            modalMessage.innerHTML = options.messageHtml;
        } else {
            modalMessage.textContent = options.message || 'Are you sure you want to continue?';
        }

        confirmButton.textContent = options.button || 'Confirm';
        confirmButton.className = getConfirmButtonClasses(type).join(' ');
        confirmButton.dataset.loadingText = options.loadingText || '';

        modalIcon.className = `global-confirmation-icon ${iconData.className}`;
        modalIcon.innerHTML = `<i class="fa-solid ${iconData.icon}"></i>`;
    }

    function getFormConfirmationOptions(form) {
        return {
            title: form.dataset.confirmTitle || 'Confirm Action',
            message: form.dataset.confirmMessage || 'Are you sure you want to continue?',
            button: form.dataset.confirmButton || 'Confirm',
            type: form.dataset.confirmType || 'warning',
            loadingText: form.dataset.confirmLoadingText || '',
        };
    }

    function submitPendingForm() {
        if (!pendingForm || isSubmitting) {
            return;
        }

        if (!pendingForm.checkValidity()) {
            pendingForm.reportValidity?.();
            closeModal();
            return;
        }

        isSubmitting = true;
        setConfirmLoading();
        pendingForm.dataset.confirmed = 'true';

        const formToSubmit = pendingForm;
        const submitter = pendingSubmitter;

        if (submitter && typeof formToSubmit.requestSubmit === 'function') {
            formToSubmit.requestSubmit(submitter);
            return;
        }

        if (typeof formToSubmit.requestSubmit === 'function') {
            formToSubmit.requestSubmit();
            return;
        }

        formToSubmit.submit();
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest?.('[data-confirm-form]');

        if (!shouldConfirmForm(form)) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            form.dataset.confirmed = 'false';
            return;
        }

        event.preventDefault();

        pendingForm = form;
        pendingCallback = null;
        pendingSubmitter = event.submitter || null;
        isSubmitting = false;

        configureModal(getFormConfirmationOptions(form));
        openModal();
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest?.('[data-confirm-action]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const targetFormId = button.dataset.confirmFormId;
        const targetForm = targetFormId ? document.getElementById(targetFormId) : null;

        if (targetForm && !targetForm.checkValidity()) {
            targetForm.reportValidity?.();
            return;
        }

        pendingForm = targetForm;
        pendingSubmitter = null;
        isSubmitting = false;
        pendingCallback = !targetForm && button.href
            ? () => { window.location.href = button.href; }
            : null;

        configureModal({
            title: button.dataset.confirmTitle || 'Confirm Action',
            message: button.dataset.confirmMessage || 'Are you sure you want to continue?',
            button: button.dataset.confirmButton || 'Confirm',
            type: button.dataset.confirmType || 'warning',
            loadingText: button.dataset.confirmLoadingText || '',
        });

        openModal();
    });

    window.openSystemConfirmation = function (options = {}, callback = null) {
        pendingForm = null;
        pendingSubmitter = null;
        pendingCallback = typeof callback === 'function' ? callback : null;
        isSubmitting = false;

        configureModal(options);
        openModal();
    };

    confirmButton.addEventListener('click', function () {
        if (pendingForm) {
            submitPendingForm();
            return;
        }

        if (pendingCallback) {
            const callbackToRun = pendingCallback;
            pendingCallback = null;
            isSubmitting = true;
            setConfirmLoading();
            callbackToRun();
            return;
        }

        closeModal();
    });

    cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});
