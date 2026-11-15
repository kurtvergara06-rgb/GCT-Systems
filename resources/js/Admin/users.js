document.addEventListener('DOMContentLoaded', function () {
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
  const userPasswordInput = document.getElementById('userPasswordInput');
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

  function resetUserForm() {
    if (userForm) {
      userForm.action = '/admin/users';
    }

    if (userFormMethod) {
      userFormMethod.value = 'POST';
    }

    setInput(userNameInput, '');
    setInput(userEmailInput, '');
    setInput(userPasswordInput, '');
    setInput(userDepartmentInput, '');
    setInput(userRoleInput, '');
    setInput(userStatusInput, 'Active');
  }

  if (openAddUserModal) {
    openAddUserModal.addEventListener('click', function () {
      resetUserForm();

      if (userFormModalTitle) {
        userFormModalTitle.textContent = 'Add User Account';
      }

      if (userFormModalSubtitle) {
        userFormModalSubtitle.textContent =
          'Create a new system account and assign role access.';
      }

      if (userFormSaveBtn) {
        userFormSaveBtn.textContent = 'Save User';
      }

      if (userPasswordInput) {
        userPasswordInput.required = true;
        userPasswordInput.placeholder = 'Enter password';
      }

      openModal(userFormModal);
    });
  }

  document.addEventListener('click', function (event) {
    const editButton = event.target.closest('.open-edit-user-modal');

    if (!editButton) return;

    if (userForm) {
      userForm.action = editButton.dataset.updateUrl;
    }

    if (userFormMethod) {
      userFormMethod.value = 'PUT';
    }

    setInput(userNameInput, editButton.dataset.name);
    setInput(userEmailInput, editButton.dataset.email);
    setInput(userPasswordInput, '');
    setInput(userDepartmentInput, editButton.dataset.department);
    setInput(userRoleInput, normalizeRole(editButton.dataset.role));
    setInput(userStatusInput, editButton.dataset.status);

    if (userFormModalTitle) {
      userFormModalTitle.textContent = 'Edit User Account';
    }

    if (userFormModalSubtitle) {
      userFormModalSubtitle.textContent =
        'Update account details, department, role, and status.';
    }

    if (userFormSaveBtn) {
      userFormSaveBtn.textContent = 'Update User';
    }

    if (userPasswordInput) {
      userPasswordInput.required = false;
      userPasswordInput.placeholder = 'Leave blank to keep current password';
    }

    openModal(userFormModal);
  });

  document.addEventListener('click', function (event) {
    const viewButton = event.target.closest('.open-view-user-modal');

    if (!viewButton) return;

    setText('viewUserInitials', viewButton.dataset.initials);
    setText('viewUserName', viewButton.dataset.name);
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
    }

    if (resetPasswordSubtitle) {
      resetPasswordSubtitle.textContent =
        `Set a new password for ${resetButton.dataset.name}.`;
    }

    setInput(resetPasswordInput, '');
    setInput(resetPasswordConfirmInput, '');

    openModal(resetPasswordModal);
  });

  if (closeUserFormModal) {
    closeUserFormModal.addEventListener('click', function () {
      closeModal(userFormModal);
    });
  }

  if (cancelUserFormModal) {
    cancelUserFormModal.addEventListener('click', function () {
      closeModal(userFormModal);
    });
  }

  if (closeViewUserModal) {
    closeViewUserModal.addEventListener('click', function () {
      closeModal(viewUserModal);
    });
  }

  if (closeViewUserModalBottom) {
    closeViewUserModalBottom.addEventListener('click', function () {
      closeModal(viewUserModal);
    });
  }

  if (closeResetPasswordModal) {
    closeResetPasswordModal.addEventListener('click', function () {
      closeModal(resetPasswordModal);
    });
  }

  if (cancelResetPasswordModal) {
    cancelResetPasswordModal.addEventListener('click', function () {
      closeModal(resetPasswordModal);
    });
  }

  document.addEventListener('click', function (event) {
    const okButton = event.target.closest(
      '#closeFeedbackModal, .feedback-ok-btn, [data-close-feedback]'
    );

    if (!okButton) return;

    const feedbackModal = okButton.closest('.feedback-modal-overlay');

    if (feedbackModal) {
      feedbackModal.classList.remove('show');
    }
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

  const moduleName = String(data.module || '')
    .trim()
    .toLowerCase();

  const entityName = String(data.entity || '')
    .trim()
    .toLowerCase();

  const actionName = String(data.action || '')
    .trim()
    .toLowerCase();

  const isUserLoginEvent =
    moduleName === 'admin' &&
    entityName === 'user' &&
    actionName === 'login';

  console.log('User Management Reverb event:', {
    rawData,
    moduleName,
    entityName,
    actionName,
    isUserLoginEvent,
  });

  if (!isUserLoginEvent) {
    return;
  }

  window.location.reload();
});