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

  const batchTimeOutModal = document.getElementById('batchMechanicTimeOutModal');
  const batchTimeOutForm = document.getElementById('batchMechanicTimeOutForm');
  if (batchTimeOutModal && batchTimeOutForm) {
    const rows = () => Array.from(batchTimeOutModal.querySelectorAll('.batch-timeout-row'));
    const update = () => {
      const chosen = rows().filter(row => row.querySelector('.batch-timeout-select').checked);
      document.getElementById('batchTimeOutSelectedCount').textContent = chosen.length + ' selected';
      document.getElementById('batchTimeOutSave').disabled = chosen.length === 0;
      const all = document.getElementById('batchTimeOutSelectAll');
      all.checked = rows().length > 0 && chosen.length === rows().length;
      all.indeterminate = chosen.length > 0 && chosen.length < rows().length;
    };
    document.getElementById('openBatchMechanicTimeOut')?.addEventListener('click', () => {
      openModal(batchTimeOutModal);
      update();
    });
    batchTimeOutModal.querySelectorAll('[data-close-batch-timeout]').forEach(button =>
      button.addEventListener('click', () => closeModal(batchTimeOutModal))
    );
    document.getElementById('batchTimeOutSelectAll')?.addEventListener('change', event => {
      rows().forEach(row => { row.querySelector('.batch-timeout-select').checked = event.target.checked; });
      update();
    });
    batchTimeOutModal.querySelectorAll('.batch-timeout-select').forEach(box => box.addEventListener('change', update));
    const localNow = () => {
      const date = new Date();
      return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
    };
    document.getElementById('batchTimeOutCurrent')?.addEventListener('click', () => {
      const time = localNow();
      document.getElementById('batchTimeOutShared').value = time;
      rows().filter(row => row.querySelector('.batch-timeout-select').checked).forEach(row => {
        row.querySelector('.batch-timeout-value').value = time;
      });
    });
    document.getElementById('batchTimeOutSame')?.addEventListener('click', () => {
      const value = document.getElementById('batchTimeOutShared').value;
      if (!value) return;
      rows().filter(row => row.querySelector('.batch-timeout-select').checked).forEach(row => {
        row.querySelector('.batch-timeout-value').value = value;
      });
    });
    batchTimeOutForm.addEventListener('submit', event => {
      batchTimeOutForm.querySelectorAll('input[data-generated-batch-timeout]').forEach(el => el.remove());
      const selected = rows().filter(row => row.querySelector('.batch-timeout-select').checked);
      if (!selected.length) { event.preventDefault(); return; }
      selected.forEach((row, index) => {
        const id = row.querySelector('.batch-timeout-select').value;
        const value = row.querySelector('.batch-timeout-value').value;
        for (const [name, val] of [['id', id], ['time_out', value]]) {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'rows[' + index + '][' + name + ']';
          hidden.value = val;
          hidden.dataset.generatedBatchTimeout = 'true';
          batchTimeOutForm.appendChild(hidden);
        }
      });
    });
    update();
  }

  const timeOutModal = document.getElementById('recordMechanicTimeOutModal');
  const timeOutForm = document.getElementById('recordMechanicTimeOutForm');
  document.addEventListener('click', (event) => {
    const button = event.target.closest('.open-mechanic-timeout-modal');
    if (button && timeOutModal && timeOutForm) {
      timeOutForm.action = button.dataset.action || '';
      document.getElementById('mechanicTimeOutName').textContent = button.dataset.name || '';
      document.getElementById('mechanicTimeOutIn').textContent = button.dataset.timeIn || '';
      const timeInput = document.getElementById('mechanicTimeOutValue');
      if (timeInput) timeInput.value = '';
      openModal(timeOutModal);
      timeInput?.focus();
    }
    if (event.target.closest('[data-close-mechanic-timeout]')) {
      closeModal(timeOutModal);
    }
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
        viewMechanicAttendanceContent.innerHTML = fields.map(([label, value]) => {
          const status = String(value ?? '').trim().toLowerCase();
          const content = label === 'Status'
            ? '<span class="attendance-status-pill" data-status="' + escapeDetail(status) + '">' + escapeDetail(value) + '</span>'
            : '<span>' + escapeDetail(value) + '</span>';
          return '<div class="mechanic-attendance-detail-card"><strong>' + escapeDetail(label) +
            '</strong>' + content + '</div>';
        }).join('');
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
        if (editAttendanceDate) {
          editAttendanceDate.value = button.dataset.attendanceDate || '';
          editAttendanceDate.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (editTimeIn) {
          editTimeIn.value = button.dataset.timeIn || '';
          editTimeIn.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (editTimeOut) {
          editTimeOut.value = button.dataset.timeOut || '';
          editTimeOut.dispatchEvent(new Event('input', { bubbles: true }));
        }
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
