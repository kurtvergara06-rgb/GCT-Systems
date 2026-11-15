document.addEventListener('DOMContentLoaded', () => {
  function normalizeDriverAttendancePath(
    value,
    fallback = '/driver-attendance'
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
        return parsed.pathname + parsed.search + parsed.hash;
      }
    } catch (error) {
      console.warn('Unable to parse Driver Attendance URL.', error);
    }

    const withoutScheme = rawValue
      .replace(/^https?:\/+/i, '')
      .replace(/^\/+/, '');

    const pathIndex = withoutScheme.indexOf('driver-attendance');

    return pathIndex >= 0
      ? `/${withoutScheme.slice(pathIndex)}`
      : fallback;
  }

  function openModal(modal) {
    modal?.classList.add('show', 'active');
  }

  function closeModal(modal) {
    modal?.classList.remove('show', 'active');
  }

  const importModal = document.getElementById('importDriverAttendanceModal');

  document
    .getElementById('openImportDriverAttendanceModal')
    ?.addEventListener('click', () => openModal(importModal));

  [
    document.getElementById('closeImportDriverAttendanceModal'),
    document.getElementById('cancelImportDriverAttendanceModal'),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener('click', () => closeModal(importModal));
    });

  const attendanceModal = document.getElementById('driverAttendanceModal');
  const attendanceForm = document.getElementById('driverAttendanceForm');
  const attendanceFormMethod = document.getElementById('driverAttendanceFormMethod');
  const modalTitle = document.getElementById('driverAttendanceModalTitle');
  const submitText = document.getElementById('driverAttendanceSubmitText');
  const driverId = document.getElementById('driverAttendanceDriverId');
  const driverName = document.getElementById('driverAttendanceDriverName');
  const shift = document.getElementById('driverAttendanceShift');
  const attendanceDate = document.getElementById('driverAttendanceDate');
  const timeIn = document.getElementById('driverAttendanceTimeIn');
  const timeOut = document.getElementById('driverAttendanceTimeOut');
  const status = document.getElementById('driverAttendanceStatus');

  [
    document.getElementById('closeDriverAttendanceModal'),
    document.getElementById('cancelDriverAttendanceModal'),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener('click', () => closeModal(attendanceModal));
    });

  document
    .querySelectorAll('.open-edit-driver-attendance-modal')
    .forEach((button) => {
      button.addEventListener('click', () => {
        if (!attendanceForm) {
          return;
        }

        const fallbackUrl = `/driver-attendance/${button.dataset.id}`;

        attendanceForm.setAttribute(
          'action',
          normalizeDriverAttendancePath(button.dataset.updateUrl, fallbackUrl)
        );

        if (attendanceFormMethod) {
          attendanceFormMethod.disabled = false;
          attendanceFormMethod.value = 'PUT';
        }

        if (modalTitle) modalTitle.textContent = 'Edit Driver Attendance';
        if (submitText) submitText.textContent = 'Update Record';

        attendanceForm.dataset.confirmTitle = 'Update Driver Attendance?';
        attendanceForm.dataset.confirmMessage =
          'Are you sure you want to update this driver attendance record?';
        attendanceForm.dataset.confirmButton = 'Yes, Update Record';
        attendanceForm.dataset.confirmType = 'update';

        if (driverId) driverId.value = button.dataset.driverId || '';
        if (driverName) driverName.value = button.dataset.driverName || '';
        if (shift) shift.value = button.dataset.shift || 'Morning';
        if (attendanceDate) attendanceDate.value = button.dataset.attendanceDate || '';
        if (timeIn) timeIn.value = button.dataset.timeIn || '';
        if (timeOut) timeOut.value = button.dataset.timeOut || '';
        if (status) status.value = button.dataset.status || 'Present';

        openModal(attendanceModal);
      });
    });

  const viewAttendanceModal = document.getElementById('viewDriverAttendanceModal');
  const viewAttendanceContent = document.getElementById('viewDriverAttendanceContent');

  function getAttendanceStatusClass(value) {
    switch (String(value || '').trim().toLowerCase()) {
      case 'late':
        return 'late';
      case 'absent':
        return 'absent';
      case 'on duty':
        return 'duty';
      case 'on leave':
        return 'leave';
      default:
        return 'present';
    }
  }

  function escapeAttendanceHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  document
    .querySelectorAll('.open-view-driver-attendance-modal')
    .forEach((button) => {
      button.addEventListener('click', () => {
        const statusValue = button.dataset.status || 'Present';
        const statusClass = getAttendanceStatusClass(statusValue);

        const details = [
          ['Driver ID', button.dataset.driverId],
          ['Driver Name', button.dataset.driverName],
          ['Role', 'Driver'],
          ['Shift', button.dataset.shift],
          ['Current Assignment', button.dataset.busAssignment || 'Unassigned'],
          ['Date', button.dataset.attendanceDate],
          ['Time-in', button.dataset.timeIn],
          ['Time-out', button.dataset.timeOut],
        ];

        if (viewAttendanceContent) {
          viewAttendanceContent.innerHTML =
            details
              .map(
                ([label, value]) => `
                  <div class="attendance-detail-card">
                    <label>${escapeAttendanceHtml(label)}</label>
                    <div class="attendance-detail-value">
                      ${escapeAttendanceHtml(value || '—')}
                    </div>
                  </div>
                `
              )
              .join('')
            + `
              <div class="attendance-detail-card">
                <label>Status</label>
                <div>
                  <span class="attendance-detail-status ${statusClass}">
                    ${escapeAttendanceHtml(statusValue)}
                  </span>
                </div>
              </div>
            `;
        }

        openModal(viewAttendanceModal);
      });
    });

  [
    document.getElementById('closeViewDriverAttendanceModal'),
    document.getElementById('closeViewDriverAttendanceButton'),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener('click', () => closeModal(viewAttendanceModal));
    });

  const deleteModal = document.getElementById('deleteDriverAttendanceModal');
  const deleteName = document.getElementById('deleteDriverAttendanceName');
  let selectedDeleteForm = null;

  document
    .querySelectorAll('.open-delete-driver-attendance-modal')
    .forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();

        selectedDeleteForm = document.getElementById(
          `deleteDriverAttendanceForm-${button.dataset.id}`
        );

        if (deleteName) {
          deleteName.textContent =
            button.dataset.driverName
            || button.dataset.driverId
            || 'this driver attendance record';
        }

        openModal(deleteModal);
      });
    });

  document
    .getElementById('cancelDeleteDriverAttendance')
    ?.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deleteModal);
    });

  document
    .getElementById('confirmDeleteDriverAttendance')
    ?.addEventListener('click', () => selectedDeleteForm?.requestSubmit());

  document
    .querySelectorAll(
      '.modal-overlay, .ui-form-overlay, .delete-modal-overlay, .success-modal-overlay'
    )
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

    closeModal(importModal);
    closeModal(attendanceModal);
    closeModal(viewAttendanceModal);
    closeModal(deleteModal);
  });
});
