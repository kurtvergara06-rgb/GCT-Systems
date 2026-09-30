window.GCTPartialNavigation.registerInitializer('account-management', '.account-main', () => {
    const avatarForm = document.querySelector('[data-avatar-upload-form]');
    const avatarTrigger = avatarForm?.querySelector('[data-avatar-trigger]');
    const avatarInput = avatarForm?.querySelector('[data-avatar-input]');
    const avatarPreview = avatarForm?.querySelector('[data-avatar-preview]');
    const avatarError = document.querySelector('[data-avatar-client-error]');
    const cropModal = document.querySelector('[data-avatar-crop-modal]');
    const cropViewport = cropModal?.querySelector('[data-avatar-crop-viewport]');
    const cropImage = cropModal?.querySelector('[data-avatar-crop-image]');
    const cropZoom = cropModal?.querySelector('[data-avatar-crop-zoom]');
    const cropApply = cropModal?.querySelector('[data-avatar-crop-apply]');
    const cropCancelButtons = cropModal?.querySelectorAll('[data-avatar-crop-cancel]') ?? [];
    let cropObjectUrl = null;
    let cropLastFocus = null;
    let cropPointerId = null;
    let cropStartPoint = null;
    let cropState = {
        naturalWidth: 0,
        naturalHeight: 0,
        baseScale: 1,
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
    };

    const setAvatarError = (message = '') => {
        if (!avatarError) return;
        avatarError.textContent = message;
        avatarError.hidden = !message;
    };

    const cropBounds = () => {
        const viewportSize = cropViewport?.clientWidth ?? 0;
        const renderedWidth = cropState.naturalWidth * cropState.baseScale * cropState.zoom;
        const renderedHeight = cropState.naturalHeight * cropState.baseScale * cropState.zoom;

        return {
            x: Math.max(0, (renderedWidth - viewportSize) / 2),
            y: Math.max(0, (renderedHeight - viewportSize) / 2),
        };
    };

    const renderCrop = () => {
        if (!cropImage) return;
        const bounds = cropBounds();
        cropState.offsetX = Math.max(-bounds.x, Math.min(bounds.x, cropState.offsetX));
        cropState.offsetY = Math.max(-bounds.y, Math.min(bounds.y, cropState.offsetY));

        cropImage.style.width = `${cropState.naturalWidth * cropState.baseScale * cropState.zoom}px`;
        cropImage.style.height = `${cropState.naturalHeight * cropState.baseScale * cropState.zoom}px`;
        cropImage.style.transform = `translate(-50%, -50%) translate(${cropState.offsetX}px, ${cropState.offsetY}px)`;
    };

    const closeCrop = ({ resetInput = true } = {}) => {
        if (!cropModal) return;
        cropModal.hidden = true;
        document.body.classList.remove('account-crop-open');
        cropViewport?.classList.remove('is-dragging');
        if (resetInput && avatarInput) avatarInput.value = '';
        if (cropObjectUrl) URL.revokeObjectURL(cropObjectUrl);
        cropObjectUrl = null;
        cropLastFocus?.focus();
    };

    const openCrop = (file) => {
        if (!cropModal || !cropImage || !cropViewport || !cropZoom) return;
        cropLastFocus = document.activeElement;
        cropObjectUrl = URL.createObjectURL(file);
        cropModal.hidden = false;
        document.body.classList.add('account-crop-open');
        cropZoom.value = '1';
        cropImage.onload = () => {
            const viewportSize = cropViewport.clientWidth;
            cropState = {
                naturalWidth: cropImage.naturalWidth,
                naturalHeight: cropImage.naturalHeight,
                baseScale: Math.max(
                    viewportSize / cropImage.naturalWidth,
                    viewportSize / cropImage.naturalHeight,
                ),
                zoom: 1,
                offsetX: 0,
                offsetY: 0,
            };
            renderCrop();
            cropZoom.focus();
        };
        cropImage.onerror = () => {
            setAvatarError('The selected image could not be opened. Please try another file.');
            closeCrop();
        };
        cropImage.src = cropObjectUrl;
    };

    avatarTrigger?.addEventListener('click', () => avatarInput?.click());

    avatarInput?.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file) return;

        setAvatarError();
        const supportedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!supportedTypes.includes(file.type)) {
            setAvatarError('Choose a JPG, PNG, or WebP image.');
            avatarInput.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            setAvatarError('The selected image must not exceed 2 MB.');
            avatarInput.value = '';
            return;
        }

        openCrop(file);
    });

    cropZoom?.addEventListener('input', () => {
        cropState.zoom = Number(cropZoom.value);
        renderCrop();
    });

    cropViewport?.addEventListener('pointerdown', (event) => {
        cropPointerId = event.pointerId;
        cropStartPoint = {
            x: event.clientX,
            y: event.clientY,
            offsetX: cropState.offsetX,
            offsetY: cropState.offsetY,
        };
        cropViewport.setPointerCapture(event.pointerId);
        cropViewport.classList.add('is-dragging');
    });

    cropViewport?.addEventListener('pointermove', (event) => {
        if (cropPointerId !== event.pointerId || !cropStartPoint) return;
        cropState.offsetX = cropStartPoint.offsetX + event.clientX - cropStartPoint.x;
        cropState.offsetY = cropStartPoint.offsetY + event.clientY - cropStartPoint.y;
        renderCrop();
    });

    const stopCropDrag = (event) => {
        if (cropPointerId !== event.pointerId) return;
        cropPointerId = null;
        cropStartPoint = null;
        cropViewport?.classList.remove('is-dragging');
    };

    cropViewport?.addEventListener('pointerup', stopCropDrag);
    cropViewport?.addEventListener('pointercancel', stopCropDrag);

    cropCancelButtons.forEach((button) => button.addEventListener('click', () => closeCrop()));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && cropModal && !cropModal.hidden) closeCrop();
    });

    cropApply?.addEventListener('click', async () => {
        if (!avatarForm || !avatarInput || !cropImage || !cropViewport || !cropApply) return;

        cropApply.disabled = true;
        const originalContent = cropApply.innerHTML;
        cropApply.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing photo';

        const viewportSize = cropViewport.clientWidth;
        const renderedScale = cropState.baseScale * cropState.zoom;
        const sourceSize = viewportSize / renderedScale;
        const sourceX = (cropState.naturalWidth - sourceSize) / 2 - cropState.offsetX / renderedScale;
        const sourceY = (cropState.naturalHeight - sourceSize) / 2 - cropState.offsetY / renderedScale;
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 512;
        const context = canvas.getContext('2d');

        if (!context) {
            cropApply.disabled = false;
            cropApply.innerHTML = originalContent;
            setAvatarError('Your browser could not prepare this image. Please try another file.');
            closeCrop();
            return;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(
            cropImage,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            canvas.width,
            canvas.height,
        );

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
        if (!blob) {
            cropApply.disabled = false;
            cropApply.innerHTML = originalContent;
            setAvatarError('The cropped image could not be created. Please try again.');
            closeCrop();
            return;
        }

        const croppedFile = new File([blob], `profile-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
        const transfer = new DataTransfer();
        transfer.items.add(croppedFile);
        avatarInput.files = transfer.files;

        if (avatarPreview) {
            const previewImage = document.createElement('img');
            previewImage.src = URL.createObjectURL(blob);
            previewImage.alt = 'Cropped profile photo preview';
            previewImage.addEventListener('load', () => URL.revokeObjectURL(previewImage.src), { once: true });
            avatarPreview.replaceChildren(previewImage);
        }

        closeCrop({ resetInput: false });
        avatarForm.classList.add('is-uploading');
        avatarForm.setAttribute('aria-busy', 'true');

        const editIcon = avatarForm.querySelector('.account-avatar-edit i');
        if (editIcon) {
            editIcon.className = 'fa-solid fa-spinner fa-spin';
        }

        avatarForm.requestSubmit();
    });

    // 1. Password Visibility Toggles
    const toggleButtons = document.querySelectorAll('.account-pw-toggle');
    toggleButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-target');
            const input = inputId ? document.getElementById(inputId) : btn.closest('.account-input-wrap')?.querySelector('input');
            if (!input) return;

            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                btn.setAttribute('aria-label', 'Hide password');
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                btn.setAttribute('aria-label', 'Show password');
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    });

    // 2. Password Strength & Requirements Validation
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('pwStrengthBar');
    const strengthLabel = document.getElementById('pwStrengthLabel');
    const matchFeedback = document.getElementById('pwMatchFeedback');

    const reqLength = document.getElementById('req-length');
    const reqCase = document.getElementById('req-case');
    const reqNumber = document.getElementById('req-number');

    function updateRequirementItem(el, isMet) {
        if (!el) return;
        if (isMet) {
            el.classList.add('is-met');
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-circle-check';
            }
        } else {
            el.classList.remove('is-met');
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = 'fa-regular fa-circle';
            }
        }
    }

    function evaluatePassword(password) {
        if (!password) {
            return { score: 0, label: 'Not entered', class: '' };
        }

        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasMixedCase = hasUpper && hasLower;
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        updateRequirementItem(reqLength, hasLength);
        updateRequirementItem(reqCase, hasMixedCase);
        updateRequirementItem(reqNumber, hasNumber || hasSpecial);

        let score = 0;
        if (hasLength) score++;
        if (password.length >= 12) score++;
        if (hasMixedCase) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        if (score <= 1) {
            return { score: 1, label: 'Weak', class: 'is-weak' };
        } else if (score <= 3) {
            return { score: 2, label: 'Moderate', class: 'is-moderate' };
        } else {
            return { score: 3, label: 'Strong', class: 'is-strong' };
        }
    }

    function checkMatch() {
        if (!confirmPasswordInput || !matchFeedback) return;
        const newPass = newPasswordInput ? newPasswordInput.value : '';
        const confirmPass = confirmPasswordInput.value;

        if (!confirmPass) {
            matchFeedback.textContent = '';
            matchFeedback.className = 'account-pw-match-feedback';
            return;
        }

        if (newPass === confirmPass) {
            matchFeedback.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
            matchFeedback.className = 'account-pw-match-feedback is-match';
        } else {
            matchFeedback.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
            matchFeedback.className = 'account-pw-match-feedback is-mismatch';
        }
    }

    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', () => {
            const val = newPasswordInput.value;
            const evalResult = evaluatePassword(val);

            if (strengthBar && strengthLabel) {
                strengthBar.className = 'account-pw-bar ' + evalResult.class;
                strengthLabel.textContent = val ? evalResult.label : 'Password strength';
            }

            checkMatch();
        });
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkMatch);
    }
});
