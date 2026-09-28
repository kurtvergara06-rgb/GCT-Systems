document.addEventListener('DOMContentLoaded', () => {
    const avatarForm = document.querySelector('[data-avatar-upload-form]');
    const avatarTrigger = avatarForm?.querySelector('[data-avatar-trigger]');
    const avatarInput = avatarForm?.querySelector('[data-avatar-input]');
    const avatarPreview = avatarForm?.querySelector('[data-avatar-preview]');

    avatarTrigger?.addEventListener('click', () => avatarInput?.click());

    avatarInput?.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file || !avatarForm) return;

        if (avatarPreview && file.type.startsWith('image/')) {
            const previewImage = document.createElement('img');
            previewImage.src = URL.createObjectURL(file);
            previewImage.alt = 'Selected profile photo preview';
            previewImage.addEventListener('load', () => URL.revokeObjectURL(previewImage.src), { once: true });
            avatarPreview.replaceChildren(previewImage);
        }

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
