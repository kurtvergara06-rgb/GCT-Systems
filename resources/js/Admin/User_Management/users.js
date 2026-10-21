window.GCTPartialNavigation.registerInitializer('admin-users', '.users-main', function () {
  const reviewModal = document.getElementById('applicationReviewModal');
  const reviewForm = document.getElementById('applicationReviewForm');
  document.querySelectorAll('[data-review-application]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!reviewModal || !reviewForm) return;
      reviewForm.action = button.dataset.reviewUrl;
      reviewForm.reset();
      const setReviewText = (id, value) => {
        const node = document.getElementById(id);
        if (node) node.textContent = value || '—';
      };
      setReviewText('applicationReviewName', button.dataset.name);
      setReviewText('applicationReviewEmail', button.dataset.email);
      setReviewText('applicationReviewApplied', button.dataset.applied);
      const verified = button.dataset.verified === '1';
      setReviewText('applicationReviewVerified', verified ? 'Email verified' : 'Email not yet verified — approval unavailable');
      const approve = document.getElementById('applicationReviewApprove');
      if (approve) approve.disabled = !verified;
      reviewModal.classList.add('show');
    });
  });
  reviewModal?.querySelectorAll('[data-close-application-review]').forEach((button) => {
    button.addEventListener('click', () => reviewModal.classList.remove('show'));
  });

  const userFormModal = document.getElementById('userFormModal');
  const viewUserModal = document.getElementById('viewUserModal');
  const resetPasswordModal = document.getElementById('resetPasswordModal');

  const userForm = document.getElementById('userForm');
  const userFormMethod = document.getElementById('userFormMethod');

  const resetPasswordForm = document.getElementById('resetPasswordForm');
  const resetPasswordSubtitle = document.getElementById('resetPasswordSubtitle');
  const resetPasswordInput = document.getElementById('resetPasswordInput');
  const resetPasswordConfirmInput = document.getElementById('resetPasswordConfirmInput');

  const openAddUserModal = document.getElementById('openAddUserModal');

  const closeUserFormModal = document.getElementById('closeUserFormModal');
  const cancelUserFormModal = document.getElementById('cancelUserFormModal');

  const closeViewUserModal = document.getElementById('closeViewUserModal');
  const closeViewUserModalBottom = document.getElementById('closeViewUserModalBottom');

  const closeResetPasswordModal = document.getElementById('closeResetPasswordModal');
  const cancelResetPasswordModal = document.getElementById('cancelResetPasswordModal');

  const userFormModalTitle = document.getElementById('userFormModalTitle');
  const userFormModalSubtitle = document.getElementById('userFormModalSubtitle');
  const userFormSaveBtn = document.getElementById('userFormSaveBtn');

  const userNameInput = document.getElementById('userNameInput');
  const userEmailInput = document.getElementById('userEmailInput');
  const accountCreateEmailField = document.getElementById('accountCreateEmailField');
  const accountCurrentEmailField = document.getElementById('accountCurrentEmailField');
  const accountCurrentVerifiedEmail = document.getElementById('accountCurrentVerifiedEmail');
  const accountNewEmailField = document.getElementById('accountNewEmailField');
  const accountNewEmailInput = document.getElementById('accountNewEmailInput');
  const accountPendingEmailPanel = document.getElementById('accountPendingEmailPanel');
  const accountPendingEmailAddress = document.getElementById('accountPendingEmailAddress');
  const accountPendingEmailExpiry = document.getElementById('accountPendingEmailExpiry');
  const accountPendingTitle = document.getElementById('accountPendingTitle');
  const accountPendingDescription = document.getElementById('accountPendingDescription');
  const accountPendingBadge = document.getElementById('accountPendingBadge');
  const accountPendingActions = document.getElementById('accountPendingActions');
  let savedPendingEmail = '';
  let savedPendingExpiry = '';

  function refreshEmailChangePanel() {
    if (!accountPendingEmailPanel || !accountNewEmailInput) return;
    const editing = userFormMethod?.value === 'PUT';
    const proposed = accountNewEmailInput.value.trim();
    const current = (accountCurrentVerifiedEmail?.value || '').trim();
    const isNewValid = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(proposed);
    const isDifferent = proposed.toLowerCase() !== current.toLowerCase();
    const isPreview = editing && isNewValid && isDifferent;
    const showSaved = editing && !isPreview && Boolean(savedPendingEmail);
    accountPendingEmailPanel.hidden = !(isPreview || showSaved);
    accountPendingEmailPanel.classList.toggle('is-preview', isPreview);
    if (!isPreview && !showSaved) return;
    if (accountPendingEmailAddress) accountPendingEmailAddress.textContent = isPreview ? proposed : savedPendingEmail;
    if (accountPendingTitle) accountPendingTitle.textContent = isPreview ? 'New Email Change Preview' : 'Pending Email Change';
    if (accountPendingDescription) accountPendingDescription.textContent = isPreview
      ? 'This change has not been submitted. Save the account to send a verification link.'
      : 'A new email address is awaiting confirmation.';
    if (accountPendingBadge) accountPendingBadge.textContent = isPreview ? 'Not yet submitted' : 'Awaiting Verification';
    if (accountPendingExpiry) accountPendingExpiry.textContent = isPreview
      ? 'The current verified email will stay active. No verification has been sent yet.'
      : (savedPendingExpiry ? 'Link expires: ' + savedPendingExpiry : '');
    if (accountPendingActions) accountPendingActions.hidden = isPreview;
  }

  accountNewEmailInput?.addEventListener('input', refreshEmailChangePanel);
  accountNewEmailInput?.addEventListener('change', refreshEmailChangePanel);
  const accountEmailResendForm = document.getElementById('accountEmailResendForm');
  const accountEmailCancelForm = document.getElementById('accountEmailCancelForm');
  accountEmailCancelForm?.addEventListener('submit', event => {
    if (!window.confirm('Cancel this pending email change?')) event.preventDefault();
  });
  const accountEditEmailNote = document.getElementById('accountEditEmailNote');
  if (accountEditEmailNote && userFormMethod) {
    const syncNotice = () => {
      accountEditEmailNote.hidden = userFormMethod.value !== 'PUT';
    };
    userFormMethod.addEventListener('change', syncNotice);
    userFormModal?.addEventListener('click', syncNotice);
  }
  const userPasswordInput = document.getElementById('userPasswordInput');
  const userPasswordGroup = userPasswordInput?.closest('.form-group');
  const userDepartmentInput = document.getElementById('userDepartmentInput');
  const userRoleInput = document.getElementById('userRoleInput');
  const userStatusInput = document.getElementById('userStatusInput');

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('show');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('show');
  }

  function closeAllModals() {
    document
      .querySelectorAll('.admin-modal-overlay, .feedback-modal-overlay')
      .forEach(function (modal) {
        closeModal(modal);
      });
  }

  function setInput(element, value) {
    if (!element) return;
    element.value = value || '';
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (!element) return;
    element.textContent = value || '—';
  }

  function setPasswordFieldVisible(visible) {
    if (userPasswordGroup) {
      userPasswordGroup.style.display = visible ? '' : 'none';
    }

    if (userPasswordInput) {
      userPasswordInput.disabled = !visible;
      userPasswordInput.required = visible;
      userPasswordInput.value = '';
    }
  }

  function normalizeRole(role) {
    role = String(role || '').trim().toLowerCase();
    role = role.replace(/[_-]/g, ' ');

    if (
      role === 'admin' ||
      role === 'system admin' ||
      role === 'maintenance admin' ||
      role === 'warehouse admin' ||
      role === 'purchase admin' ||
      role === 'operation admin'
    ) {
      return 'head';
    }

    if (
      role === 'head' ||
      role === 'maintenance head' ||
      role === 'warehouse head' ||
      role === 'purchase head' ||
      role === 'operation head'
    ) {
      return 'head';
    }

    if (
      role === 'staff' ||
      role === 'maintenance staff' ||
      role === 'warehouse staff' ||
      role === 'purchase staff' ||
      role === 'operation staff'
    ) {
      return 'staff';
    }

    return role;
  }

  function configureStatusActions(root = document) {
    root.querySelectorAll('.users-table tbody tr').forEach(function (row) {
      const status = row.querySelector('.status-pill')?.textContent.trim();
      const activateButton = row.querySelector('.action-activate');

      if (!activateButton) return;

      const form = activateButton.closest('form');
      const icon = activateButton.querySelector('i');

      if (status === 'Pending') {
        activateButton.title = 'Approve Account';
        activateButton.setAttribute('aria-label', 'Approve Account');

        if (icon) {
          icon.className = 'fa-solid fa-check';
        }

        if (form) {
          form.dataset.confirmTitle = 'Approve Account?';
          form.dataset.confirmMessage = 'Approve this pending account and allow the user to sign in?';
          form.dataset.confirmButton = 'Yes, Approve Account';
          form.dataset.confirmType = 'approve';
        }

        return;
      }

      activateButton.title = 'Activate Account';
      activateButton.setAttribute('aria-label', 'Activate Account');

      if (icon) {
        icon.className = 'fa-solid fa-user-check';
      }
    });
  }

  function configureProtectedAccounts(root = document) {
    root.querySelectorAll('.users-table tbody tr').forEach(function (row) {
      const role = row.querySelector('.role-pill')?.textContent.trim().toLowerCase();

      if (role !== 'system admin') return;

      row.querySelectorAll('.open-edit-user-modal, .action-activate, .action-deactivate, .action-delete')
        .forEach(function (button) {
          button.disabled = true;
          button.title = 'Protected System Admin';
          button.setAttribute('aria-label', 'Protected System Admin');
        });
    });
  }

  function refreshAccountActions(root = document) {
    configureStatusActions(root);
    configureProtectedAccounts(root);
  }

  function resetUserForm() {
    if (userForm) {
      userForm.action = userForm.dataset.storeUrl || '/admin/users';
      userForm.dataset.confirmTitle = 'Create Account?';
      userForm.dataset.confirmMessage =
        'Are you sure you want to create this system account?';
      userForm.dataset.confirmButton = 'Yes, Create Account';
      userForm.dataset.confirmType = 'create';
    }

    if (userFormMethod) {
      userFormMethod.value = 'POST';
    }

    if (accountEditEmailNote) accountEditEmailNote.hidden = true;
    if (accountCreateEmailField) accountCreateEmailField.hidden = false;
    if (accountCurrentEmailField) accountCurrentEmailField.hidden = true;
    if (accountNewEmailField) accountNewEmailField.hidden = true;
    savedPendingEmail = '';
    savedPendingExpiry = '';
    if (accountPendingEmailPanel) accountPendingEmailPanel.hidden = true;
    if (accountNewEmailInput) { accountNewEmailInput.disabled = true; accountNewEmailInput.value = ''; }
    if (userEmailInput) userEmailInput.disabled = false;
    setInput(userNameInput, '');
    setInput(userEmailInput, '');
    setInput(userDepartmentInput, '');
    setInput(userRoleInput, '');
    setInput(userStatusInput, 'Active');
    setPasswordFieldVisible(true);
  }

  refreshAccountActions();

  document.addEventListener('system:region-replaced', function (event) {
    refreshAccountActions(event.target || document);
  });

  if (openAddUserModal) {
    openAddUserModal.addEventListener('click', function () {
      resetUserForm();

      if (userFormModalTitle) {
        userFormModalTitle.textContent = 'Add System Account';
      }

      if (userFormModalSubtitle) {
        userFormModalSubtitle.textContent =
          'Create a new account and assign department and role access.';
      }

      if (userFormSaveBtn) {
        userFormSaveBtn.textContent = 'Save Account';
      }

      if (userPasswordInput) {
        userPasswordInput.placeholder = 'Enter password';
      }

      openModal(userFormModal);
    });
  }

  document.addEventListener('click', function (event) {
    const editButton = event.target.closest('.open-edit-user-modal');

    if (!editButton || editButton.disabled) return;

    if (userForm) {
      userForm.action = editButton.dataset.updateUrl;
      userForm.dataset.confirmTitle = 'Update Account?';
      userForm.dataset.confirmMessage =
        `Are you sure you want to update ${editButton.dataset.name}?`;
      userForm.dataset.confirmButton = 'Yes, Update Account';
      userForm.dataset.confirmType = 'update';
    }

    if (userFormMethod) {
      userFormMethod.value = 'PUT';
    }

    setInput(userNameInput, editButton.dataset.name);
    // Explicitly set the edit-mode state; hidden inputs do not dispatch change events.
    if (accountEditEmailNote) accountEditEmailNote.hidden = false;
    setInput(userEmailInput, editButton.dataset.email);
    if (accountCreateEmailField) accountCreateEmailField.hidden = true;
    if (accountCurrentEmailField) accountCurrentEmailField.hidden = false;
    if (accountNewEmailField) accountNewEmailField.hidden = false;
    if (accountCurrentVerifiedEmail) accountCurrentVerifiedEmail.value = editButton.dataset.email || '';
    if (accountNewEmailInput) { accountNewEmailInput.disabled = false; accountNewEmailInput.value = ''; }
    if (userEmailInput) userEmailInput.disabled = false;
    savedPendingEmail = editButton.dataset.pendingEmail || '';
    savedPendingExpiry = editButton.dataset.pendingExpiry || '';
    refreshEmailChangePanel();
    if (accountEmailResendForm) accountEmailResendForm.action = editButton.dataset.emailResendUrl || '';
    if (accountEmailCancelForm) accountEmailCancelForm.action = editButton.dataset.emailCancelUrl || '';
    setInput(userDepartmentInput, editButton.dataset.department);
    setInput(userRoleInput, normalizeRole(editButton.dataset.role));
    setInput(userStatusInput, editButton.dataset.status);
    setPasswordFieldVisible(false);

    if (userFormModalTitle) {
      userFormModalTitle.textContent = 'Edit System Account';
    }

    if (userFormModalSubtitle) {
      userFormModalSubtitle.textContent =
        'Update account details, department, role, and status.';
    }

    if (userFormSaveBtn) {
      userFormSaveBtn.textContent = 'Update Account';
    }

    openModal(userFormModal);
  });

  document.addEventListener('click', function (event) {
    const viewButton = event.target.closest('.open-view-user-modal');

    if (!viewButton) return;

    const viewAvatarImage = document.getElementById('viewUserAvatarImage');
    const viewAvatarInitials = document.getElementById('viewUserInitials');
    const avatarUrl = viewButton.dataset.avatarUrl || '';

    if (viewAvatarImage && viewAvatarInitials) {
      if (avatarUrl) {
        viewAvatarImage.src = avatarUrl;
        viewAvatarImage.alt = `${viewButton.dataset.name || 'User'} profile photo`;
        viewAvatarImage.hidden = false;
        viewAvatarInitials.hidden = true;
      } else {
        viewAvatarImage.removeAttribute('src');
        viewAvatarImage.alt = '';
        viewAvatarImage.hidden = true;
        viewAvatarInitials.hidden = false;
        viewAvatarInitials.textContent = viewButton.dataset.initials || 'U';
      }
    }
    setText('viewUserName', viewButton.dataset.name);
    setText('viewUserUserId', viewButton.dataset.userId || '—');
    setText('viewUserEmail', viewButton.dataset.email);
    setText('viewUserRole', viewButton.dataset.role);
    setText('viewUserDepartment', viewButton.dataset.department);
    setText('viewUserStatus', viewButton.dataset.status);
    setText('viewUserLastLogin', viewButton.dataset.lastLogin);

    openModal(viewUserModal);
  });

  document.addEventListener('click', function (event) {
    const resetButton = event.target.closest('.open-reset-password-modal');

    if (!resetButton) return;

    if (resetPasswordForm) {
      resetPasswordForm.action = resetButton.dataset.resetUrl;
      resetPasswordForm.dataset.confirmMessage =
        `Are you sure you want to reset the password for ${resetButton.dataset.name}?`;
    }

    if (resetPasswordSubtitle) {
      resetPasswordSubtitle.textContent =
        `Set a new password for ${resetButton.dataset.name}.`;
    }

    setInput(resetPasswordInput, '');
    setInput(resetPasswordConfirmInput, '');

    openModal(resetPasswordModal);
  });

  closeUserFormModal?.addEventListener('click', function () {
    closeModal(userFormModal);
  });

  cancelUserFormModal?.addEventListener('click', function () {
    closeModal(userFormModal);
  });

  closeViewUserModal?.addEventListener('click', function () {
    closeModal(viewUserModal);
  });

  closeViewUserModalBottom?.addEventListener('click', function () {
    closeModal(viewUserModal);
  });

  closeResetPasswordModal?.addEventListener('click', function () {
    closeModal(resetPasswordModal);
  });

  cancelResetPasswordModal?.addEventListener('click', function () {
    closeModal(resetPasswordModal);
  });

  document.addEventListener('click', function (event) {
    const okButton = event.target.closest(
      '#closeFeedbackModal, .feedback-ok-btn, [data-close-feedback]'
    );

    if (!okButton) return;

    const feedbackModal = okButton.closest('.feedback-modal-overlay');
    if (feedbackModal) feedbackModal.classList.remove('show');
  });

  document.addEventListener('click', function (event) {
    if (
      event.target.classList.contains('admin-modal-overlay') ||
      event.target.classList.contains('feedback-modal-overlay')
    ) {
      event.target.classList.remove('show');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});

window.addEventListener('system-data-updated', function (event) {
  const rawData = event.detail || {};
  const data = rawData.data || rawData;

  const moduleName = String(data.module || '').trim().toLowerCase();
  const entityName = String(data.entity || '').trim().toLowerCase();
  const actionName = String(data.action || '').trim().toLowerCase();

  const isAccountLoginEvent =
    moduleName === 'admin' &&
    entityName === 'user' &&
    actionName === 'login';

  if (!isAccountLoginEvent) {
    return;
  }

  if (window.GCTRegions?.replace) {
    window.GCTRegions.replace(['summary', 'records']);
  }
});
