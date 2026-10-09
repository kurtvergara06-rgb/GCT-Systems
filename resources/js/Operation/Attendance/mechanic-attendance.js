window.GCTPartialNavigation.registerInitializer('operation-mechanic-attendance', '.mechanic-attendance-page', function () {
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

  const viewMechanicAttendanceModal = document.getElementById('viewMechanicAttendanceModal');
  const viewMechanicAttendanceContent = document.getElementById('viewMechanicAttendanceContent');
  const escapeDetail = (value) => String(value ?? '—')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  ['closeViewMechanicAttendanceModal', 'dismissViewMechanicAttendanceModal'].forEach((id) => {
    document.getElementById(id)?.addEventListener('click', () => closeModal(viewMechanicAttendanceModal));
  });

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
    ?.addEventListener('click', () => selectedDeleteForm?.requestSubmit());

  // Server-side search/filter replaces tbody rows. Bind actions on each newly
  // inserted row without duplicating handlers on existing buttons.
  const bindAttendanceRowActions = () => {
  document.querySelectorAll('.open-view-mechanic-attendance-modal').forEach((button) => {
      if (button.dataset.gctAttendanceActionBound) return;
      button.dataset.gctAttendanceActionBound = '1';
    button.addEventListener('click', () => {
      const fields = [
        ['Mechanic ID', button.dataset.mechanicId],
        ['Mechanic Name', button.dataset.mechanicName],
        ['Shift', button.dataset.shift],
        ['Assigned Job', button.dataset.assignedJob],
        ['Date', button.dataset.attendanceDate],
        ['Time-in', button.dataset.timeIn],
        ['Time-out', button.dataset.timeOut],
        ['Status', button.dataset.status],
      ];
      if (viewMechanicAttendanceContent) {
        viewMechanicAttendanceContent.innerHTML = fields.map(([label, value]) =>
          '<div class="mechanic-attendance-detail-card"><strong>' + escapeDetail(label) +
          '</strong><span>' + escapeDetail(value) + '</span></div>'
        ).join('');
      }
      openModal(viewMechanicAttendanceModal);
    });
  });


  document
    .querySelectorAll('.open-edit-attendance-modal')
    .forEach((button) => {
      if (button.dataset.gctAttendanceActionBound) return;
      button.dataset.gctAttendanceActionBound = '1';
      button.addEventListener('click', () => {
        editMechanicAttendanceForm?.setAttribute(
          'action',
          normalizeMechanicAttendancePath(
            button.dataset.updateUrl,
            `/mechanic-attendance/${button.dataset.id}`
          )
        );

        if (editMechanicId) editMechanicId.value = button.dataset.mechanicId || '';
        if (editMechanicName) editMechanicName.value = button.dataset.mechanicName || '';
        if (editShift) editShift.value = button.dataset.shift || 'Morning';
        if (editAssignedJob) editAssignedJob.value = button.dataset.assignedJob || '';
        if (editAttendanceDate) editAttendanceDate.value = button.dataset.attendanceDate || '';
        if (editTimeIn) editTimeIn.value = button.dataset.timeIn || '';
        if (editTimeOut) editTimeOut.value = button.dataset.timeOut || '';
        if (editStatus) editStatus.value = button.dataset.status || 'Present';

        openModal(editMechanicAttendanceModal);
      });
    });


  document
    .querySelectorAll('.open-delete-attendance-modal')
    .forEach((button) => {
      if (button.dataset.gctAttendanceActionBound) return;
      button.dataset.gctAttendanceActionBound = '1';
      button.addEventListener('click', (event) => {
        event.preventDefault();

        selectedDeleteForm = document.getElementById(
          `deleteAttendanceForm-${button.dataset.id}`
        );

        if (deleteAttendanceName) {
          deleteAttendanceName.textContent =
            button.dataset.mechanicName
            || button.dataset.mechanicId
            || 'this attendance record';
        }

        openModal(deleteAttendanceModal);
      });
    });

  };
  bindAttendanceRowActions();
  document.addEventListener('ajax:content-updated', bindAttendanceRowActions);

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    closeModal(editMechanicAttendanceModal);
    closeModal(viewMechanicAttendanceModal);
    closeModal(deleteAttendanceModal);
  });
});
