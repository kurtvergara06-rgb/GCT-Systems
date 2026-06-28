document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (modal) {
      modal.classList.add('show');
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('show');
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Feedback Modal
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.close-feedback-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.success-modal-overlay');
      closeModal(modal);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | Import Attendance Modal
  |--------------------------------------------------------------------------
  */
  const importAttendanceModal = document.getElementById('importAttendanceModal');
  const openImportAttendanceModal = document.getElementById('openImportAttendanceModal');
  const closeImportAttendanceModal = document.getElementById('closeImportAttendanceModal');
  const cancelImportAttendanceModal = document.getElementById('cancelImportAttendanceModal');

  if (openImportAttendanceModal) {
    openImportAttendanceModal.addEventListener('click', () => {
      openModal(importAttendanceModal);
    });
  }

  if (closeImportAttendanceModal) {
    closeImportAttendanceModal.addEventListener('click', () => {
      closeModal(importAttendanceModal);
    });
  }

  if (cancelImportAttendanceModal) {
    cancelImportAttendanceModal.addEventListener('click', () => {
      closeModal(importAttendanceModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | New Attendance Modal
  |--------------------------------------------------------------------------
  */
  const mechanicAttendanceModal = document.getElementById('mechanicAttendanceModal');
  const openMechanicAttendanceModal = document.getElementById('openMechanicAttendanceModal');
  const closeMechanicAttendanceModal = document.getElementById('closeMechanicAttendanceModal');
  const cancelMechanicAttendanceModal = document.getElementById('cancelMechanicAttendanceModal');

  if (openMechanicAttendanceModal) {
    openMechanicAttendanceModal.addEventListener('click', () => {
      openModal(mechanicAttendanceModal);
    });
  }

  if (closeMechanicAttendanceModal) {
    closeMechanicAttendanceModal.addEventListener('click', () => {
      closeModal(mechanicAttendanceModal);
    });
  }

  if (cancelMechanicAttendanceModal) {
    cancelMechanicAttendanceModal.addEventListener('click', () => {
      closeModal(mechanicAttendanceModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Edit Attendance Modal
  |--------------------------------------------------------------------------
  */
  const editMechanicAttendanceModal = document.getElementById(
    'editMechanicAttendanceModal'
  );

  const editMechanicAttendanceForm = document.getElementById(
    'editMechanicAttendanceForm'
  );

  const closeEditMechanicAttendanceModal = document.getElementById(
    'closeEditMechanicAttendanceModal'
  );

  const cancelEditMechanicAttendanceModal = document.getElementById(
    'cancelEditMechanicAttendanceModal'
  );

  const editMechanicId = document.getElementById('edit_mechanic_id');
  const editMechanicName = document.getElementById('edit_mechanic_name');
  const editShift = document.getElementById('edit_shift');
  const editAssignedJob = document.getElementById('edit_assigned_job');
  const editAttendanceDate = document.getElementById('edit_attendance_date');
  const editTimeIn = document.getElementById('edit_time_in');
  const editTimeOut = document.getElementById('edit_time_out');
  const editStatus = document.getElementById('edit_status');

  document
    .querySelectorAll('.open-edit-attendance-modal')
    .forEach((button) => {
      button.addEventListener('click', () => {
        if (editMechanicAttendanceForm) {
          editMechanicAttendanceForm.action = button.dataset.updateUrl || '#';
        }

        if (editMechanicId) {
          editMechanicId.value = button.dataset.mechanicId || '';
        }

        if (editMechanicName) {
          editMechanicName.value = button.dataset.mechanicName || '';
        }

        if (editShift) {
          editShift.value = button.dataset.shift || 'Morning';
        }

        if (editAssignedJob) {
          editAssignedJob.value = button.dataset.assignedJob || '';
        }

        if (editAttendanceDate) {
          editAttendanceDate.value = button.dataset.attendanceDate || '';
        }

        if (editTimeIn) {
          editTimeIn.value = button.dataset.timeIn || '';
        }

        if (editTimeOut) {
          editTimeOut.value = button.dataset.timeOut || '';
        }

        if (editStatus) {
          editStatus.value = button.dataset.status || 'Present';
        }

        openModal(editMechanicAttendanceModal);
      });
    });

  if (closeEditMechanicAttendanceModal) {
    closeEditMechanicAttendanceModal.addEventListener('click', () => {
      closeModal(editMechanicAttendanceModal);
    });
  }

  if (cancelEditMechanicAttendanceModal) {
    cancelEditMechanicAttendanceModal.addEventListener('click', () => {
      closeModal(editMechanicAttendanceModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Delete Attendance Modal
  |--------------------------------------------------------------------------
  */
  const deleteAttendanceModal = document.getElementById('deleteAttendanceModal');

  const deleteAttendanceName = document.getElementById('deleteAttendanceName');

  const cancelDeleteAttendance = document.getElementById(
    'cancelDeleteAttendance'
  );

  const confirmDeleteAttendance = document.getElementById(
    'confirmDeleteAttendance'
  );

  let selectedDeleteForm = null;

  document
    .querySelectorAll('.open-delete-attendance-modal')
    .forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();

        const attendanceId = button.dataset.id;

        selectedDeleteForm = document.getElementById(
          `deleteAttendanceForm-${attendanceId}`
        );

        if (deleteAttendanceName) {
          deleteAttendanceName.textContent =
            button.dataset.mechanicName ||
            button.dataset.mechanicId ||
            'this attendance record';
        }

        openModal(deleteAttendanceModal);
      });
    });

  if (cancelDeleteAttendance) {
    cancelDeleteAttendance.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deleteAttendanceModal);
    });
  }

  if (confirmDeleteAttendance) {
    confirmDeleteAttendance.addEventListener('click', () => {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Close Modal When Clicking Outside
  |--------------------------------------------------------------------------
  */
  document
    .querySelectorAll(
      '.modal-overlay, .delete-modal-overlay, .success-modal-overlay'
    )
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  /*
  |--------------------------------------------------------------------------
  | Close Modal Using Escape Key
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(importAttendanceModal);
      closeModal(mechanicAttendanceModal);
      closeModal(editMechanicAttendanceModal);
      closeModal(deleteAttendanceModal);
    }
  });
});