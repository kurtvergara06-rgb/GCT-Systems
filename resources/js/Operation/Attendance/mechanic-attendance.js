document.addEventListener('DOMContentLoaded', function () {
  function normalizeMechanicAttendancePath(
    value,
    fallback = '/mechanic-attendance'
  ) {
    const rawValue = String(value || '').trim();

    if (!rawValue) {
      return fallback;
    }

    if (rawValue.startsWith('/') && !rawValue.startsWith('//')) {
      return rawValue;
    }

    try {
      const parsed = new URL(rawValue, window.location.origin);

      if (parsed.origin === window.location.origin) {
        return `${parsed.pathname}${parsed.search}${parsed.hash}`;
      }
    } catch (error) {
      // Continue with malformed URL cleanup.
    }

    const withoutScheme = rawValue
      .replace(/^https?:\/+/i, '')
      .replace(/^\/+/, '');

    const pathIndex = withoutScheme.indexOf('mechanic-attendance');

    if (pathIndex >= 0) {
      return `/${withoutScheme.slice(pathIndex)}`;
    }

    return fallback;
  }

  function openModal(modal) {
    modal?.classList.add('show');
  }

  function closeModal(modal) {
    modal?.classList.remove('show');
  }

  document
    .querySelectorAll('.close-feedback-modal')
    .forEach((button) => {
      button.addEventListener('click', () => {
        closeModal(button.closest('.success-modal-overlay'));
      });
    });

  const importAttendanceModal = document.getElementById('importAttendanceModal');

  document
    .getElementById('openImportAttendanceModal')
    ?.addEventListener('click', () => openModal(importAttendanceModal));

  document
    .getElementById('closeImportAttendanceModal')
    ?.addEventListener('click', () => closeModal(importAttendanceModal));

  document
    .getElementById('cancelImportAttendanceModal')
    ?.addEventListener('click', () => closeModal(importAttendanceModal));

  const editMechanicAttendanceModal = document.getElementById(
    'editMechanicAttendanceModal'
  );
  const editMechanicAttendanceForm = document.getElementById(
    'editMechanicAttendanceForm'
  );
  const editMechanicId = document.getElementById('edit_mechanic_id');
  const editMechanicName = document.getElementById('edit_mechanic_name');
  const editShift = document.getElementById('edit_shift');
  const editAssignedJob = document.getElementById('edit_assigned_job');
  const editAttendanceDate = document.getElementById('edit_attendance_date');
  const editTimeIn = document.getElementById('edit_time_in');
  const editTimeOut = document.getElementById('edit_time_out');
  const editStatus = document.getElementById('edit_status');

  document.addEventListener('click', (event) => {
    const editBtn = event.target.closest('.open-edit-attendance-modal');
    if (editBtn) {
      editMechanicAttendanceForm?.setAttribute(
        'action',
        normalizeMechanicAttendancePath(
          editBtn.dataset.updateUrl,
          `/mechanic-attendance/${editBtn.dataset.id}`
        )
      );

      if (editMechanicId) editMechanicId.value = editBtn.dataset.mechanicId || '';
      if (editMechanicName) editMechanicName.value = editBtn.dataset.mechanicName || '';
      if (editShift) editShift.value = editBtn.dataset.shift || 'Morning';
      if (editAssignedJob) editAssignedJob.value = editBtn.dataset.assignedJob || '';
      if (editAttendanceDate) editAttendanceDate.value = editBtn.dataset.attendanceDate || '';
      if (editTimeIn) editTimeIn.value = editBtn.dataset.timeIn || '';
      if (editTimeOut) editTimeOut.value = editBtn.dataset.timeOut || '';
      if (editStatus) editStatus.value = editBtn.dataset.status || 'Present';

      openModal(editMechanicAttendanceModal);
      return;
    }

    const deleteBtn = event.target.closest('.open-delete-attendance-modal');
    if (deleteBtn) {
      event.preventDefault();

      selectedDeleteForm = document.getElementById(
        `deleteAttendanceForm-${deleteBtn.dataset.id}`
      );

      if (deleteAttendanceName) {
        deleteAttendanceName.textContent =
          deleteBtn.dataset.mechanicName
          || deleteBtn.dataset.mechanicId
          || 'this attendance record';
      }

      openModal(deleteAttendanceModal);
      return;
    }
  });

  document
    .getElementById('closeEditMechanicAttendanceModal')
    ?.addEventListener('click', () => closeModal(editMechanicAttendanceModal));

  document
    .getElementById('cancelEditMechanicAttendanceModal')
    ?.addEventListener('click', () => closeModal(editMechanicAttendanceModal));

  const deleteAttendanceModal = document.getElementById('deleteAttendanceModal');
  const deleteAttendanceName = document.getElementById('deleteAttendanceName');
  let selectedDeleteForm = null;

  document
    .getElementById('cancelDeleteAttendance')
    ?.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deleteAttendanceModal);
    });

  document
    .getElementById('confirmDeleteAttendance')
    ?.addEventListener('click', () => {
      if (!selectedDeleteForm) return;

      if (window.GCTAjax) {
        window.GCTAjax.submitForm(selectedDeleteForm, {
          closeModal: () => closeModal(deleteAttendanceModal),
          refreshRegions: ['records', 'summary'],
        });
      } else {
        selectedDeleteForm.requestSubmit();
      }
    });

  document
    .querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay')
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    closeModal(importAttendanceModal);
    closeModal(editMechanicAttendanceModal);
    closeModal(deleteAttendanceModal);
  });
});
