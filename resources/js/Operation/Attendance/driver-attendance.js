document.addEventListener('DOMContentLoaded', () => {
  const createAction = '/driver-attendance';


  /*
  |--------------------------------------------------------------------------
  | URL Helper
  |--------------------------------------------------------------------------
  */

  function normalizeDriverAttendancePath(
    value,
    fallback = '/driver-attendance'
  ) {
    const rawValue = String(value || '').trim();

    if (!rawValue) {
      return fallback;
    }

    if (
      rawValue.startsWith('/')
      && !rawValue.startsWith('//')
    ) {
      return rawValue;
    }

    try {
      const parsed = new URL(
        rawValue,
        window.location.origin
      );

      if (
        parsed.origin
        === window.location.origin
      ) {
        return (
          parsed.pathname
          + parsed.search
          + parsed.hash
        );
      }
    } catch (error) {
      console.warn(
        'Unable to parse Driver Attendance URL.',
        error
      );
    }

    const withoutScheme = rawValue
      .replace(/^https?:\/+/i, '')
      .replace(/^\/+/, '');

    const pathIndex =
      withoutScheme.indexOf(
        'driver-attendance'
      );

    return pathIndex >= 0
      ? `/${withoutScheme.slice(pathIndex)}`
      : fallback;
  }


  /*
  |--------------------------------------------------------------------------
  | Modal Helpers
  |--------------------------------------------------------------------------
  */

  function openModal(modal) {
    modal?.classList.add(
      'show',
      'active'
    );
  }


  function closeModal(modal) {
    modal?.classList.remove(
      'show',
      'active'
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Import Modal
  |--------------------------------------------------------------------------
  */

  const importModal =
    document.getElementById(
      'importDriverAttendanceModal'
    );


  document
    .getElementById(
      'openImportDriverAttendanceModal'
    )
    ?.addEventListener(
      'click',
      () => {
        openModal(importModal);
      }
    );


  [
    document.getElementById(
      'closeImportDriverAttendanceModal'
    ),

    document.getElementById(
      'cancelImportDriverAttendanceModal'
    ),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          closeModal(importModal);
        }
      );
    });


  /*
  |--------------------------------------------------------------------------
  | Shared Add / Edit Attendance Modal
  |--------------------------------------------------------------------------
  */

  const attendanceModal =
    document.getElementById(
      'driverAttendanceModal'
    );

  const attendanceForm =
    document.getElementById(
      'driverAttendanceForm'
    );

  const attendanceFormMethod =
    document.getElementById(
      'driverAttendanceFormMethod'
    );

  const modalTitle =
    document.getElementById(
      'driverAttendanceModalTitle'
    );

  const submitText =
    document.getElementById(
      'driverAttendanceSubmitText'
    );

  const driverId =
    document.getElementById(
      'driverAttendanceDriverId'
    );

  const driverName =
    document.getElementById(
      'driverAttendanceDriverName'
    );

  const shift =
    document.getElementById(
      'driverAttendanceShift'
    );

  const attendanceDate =
    document.getElementById(
      'driverAttendanceDate'
    );

  const timeIn =
    document.getElementById(
      'driverAttendanceTimeIn'
    );

  const timeOut =
    document.getElementById(
      'driverAttendanceTimeOut'
    );

  const status =
    document.getElementById(
      'driverAttendanceStatus'
    );


  /*
  |--------------------------------------------------------------------------
  | Searchable Bus Dropdown
  |--------------------------------------------------------------------------
  */

  const busCombobox =
    document.getElementById(
      'driverBusCombobox'
    );

  const busInput =
    document.getElementById(
      'driverBusAssignment'
    );

  const busTrigger =
    document.getElementById(
      'driverBusTrigger'
    );

  const busMenu =
    document.getElementById(
      'driverBusMenu'
    );

  const busLabel =
    document.getElementById(
      'driverBusLabel'
    );

  const busSearch =
    document.getElementById(
      'driverBusSearch'
    );

  const busHelp =
    document.getElementById(
      'driverBusHelp'
    );

  const busOptions =
    document.querySelectorAll(
      '.attendance-combobox-option'
    );


  function openBusDropdown() {
    if (
      !busMenu
      || !busTrigger
      || busTrigger.disabled
    ) {
      return;
    }

    busMenu.classList.add('show');

    busTrigger.setAttribute(
      'aria-expanded',
      'true'
    );

    window.setTimeout(() => {
      busSearch?.focus();
    }, 50);
  }


  function closeBusDropdown() {
    busMenu?.classList.remove('show');

    busTrigger?.setAttribute(
      'aria-expanded',
      'false'
    );
  }


  function filterBusOptions(value) {
    const searchValue =
      String(value || '')
        .trim()
        .toLowerCase();

    busOptions.forEach((option) => {
      const searchableText =
        String(
          option.dataset.search || ''
        ).toLowerCase();

      option.hidden =
        Boolean(searchValue)
        && !searchableText.includes(
          searchValue
        );
    });
  }


  function selectBus(value, label) {
    const normalizedValue =
      String(value || '');

    if (busInput) {
      busInput.value =
        normalizedValue;
    }

    if (busLabel) {
      busLabel.textContent =
        label || 'No bus assigned';

      busLabel.classList.toggle(
        'placeholder',
        !normalizedValue
      );
    }

    busOptions.forEach((option) => {
      const optionValue =
        String(
          option.dataset.value || ''
        );

      option.classList.toggle(
        'selected',
        optionValue === normalizedValue
      );
    });

    closeBusDropdown();
  }


  function selectBusByValue(value) {
    const normalizedValue =
      String(value || '').trim();

    const matchingOption =
      Array.from(busOptions)
        .find((option) => {
          const optionValue =
            String(
              option.dataset.value || ''
            ).trim();

          return (
            optionValue
            === normalizedValue
          );
        });

    if (matchingOption) {
      selectBus(
        matchingOption.dataset.value,
        matchingOption.dataset.label
      );

      return;
    }

    /*
     * Old records may contain BUS-001,
     * BUS-002, and similar values that
     * are no longer in Bus Master List.
     */

    if (normalizedValue) {
      if (busInput) {
        busInput.value = '';
      }

      if (busLabel) {
        busLabel.textContent =
          `${normalizedValue} — not found in Bus Master List`;

        busLabel.classList.remove(
          'placeholder'
        );
      }

      busOptions.forEach((option) => {
        option.classList.remove(
          'selected'
        );
      });

      return;
    }

    selectBus(
      '',
      'No bus assigned'
    );
  }


  function resetBusSearch() {
    if (busSearch) {
      busSearch.value = '';
    }

    filterBusOptions('');
  }


  function syncBusAvailability() {
    const unavailableStatuses = [
      'Absent',
      'On Leave',
    ];

    const unavailable =
      unavailableStatuses.includes(
        status?.value
      );

    if (busTrigger) {
      busTrigger.disabled =
        unavailable;
    }

    if (unavailable) {
      selectBus(
        '',
        'No bus assigned'
      );

      closeBusDropdown();

      if (busHelp) {
        busHelp.textContent =
          'Bus selection is disabled for Absent or On Leave drivers.';
      }

      return;
    }

    if (busHelp) {
      busHelp.textContent =
        'Only active buses from Bus Master List are shown.';
    }
  }


  busTrigger?.addEventListener(
    'click',
    () => {
      const isOpen =
        busMenu
          ?.classList
          .contains('show');

      if (isOpen) {
        closeBusDropdown();
      } else {
        openBusDropdown();
      }
    }
  );


  busSearch?.addEventListener(
    'input',
    () => {
      filterBusOptions(
        busSearch.value
      );
    }
  );


  busOptions.forEach((option) => {
    option.addEventListener(
      'click',
      () => {
        selectBus(
          option.dataset.value,
          option.dataset.label
        );
      }
    );
  });


  document.addEventListener(
    'click',
    (event) => {
      if (
        busCombobox
        && !busCombobox.contains(
          event.target
        )
      ) {
        closeBusDropdown();
      }
    }
  );


  status?.addEventListener(
    'change',
    syncBusAvailability
  );


  /*
  |--------------------------------------------------------------------------
  | Date Helper
  |--------------------------------------------------------------------------
  */

  function getLocalDate() {
    const now = new Date();

    const year =
      now.getFullYear();

    const month =
      String(
        now.getMonth() + 1
      ).padStart(2, '0');

    const day =
      String(
        now.getDate()
      ).padStart(2, '0');

    return `${year}-${month}-${day}`;
  }


  /*
  |--------------------------------------------------------------------------
  | Reset Form for Create
  |--------------------------------------------------------------------------
  */

  function resetCreateForm() {
    attendanceForm?.reset();

    attendanceForm?.setAttribute(
      'action',
      createAction
    );

    if (attendanceFormMethod) {
      attendanceFormMethod.disabled =
        true;
    }

    if (modalTitle) {
      modalTitle.textContent =
        'Add New Driver Attendance';
    }

    if (submitText) {
      submitText.textContent =
        'Save Record';
    }

    if (attendanceForm) {
      attendanceForm.dataset.confirmTitle =
        'Save Driver Attendance?';

      attendanceForm.dataset.confirmMessage =
        'Are you sure you want to save this driver attendance record?';

      attendanceForm.dataset.confirmButton =
        'Yes, Save Record';

      attendanceForm.dataset.confirmType =
        'create';
    }

    if (attendanceDate) {
      attendanceDate.value =
        getLocalDate();
    }

    if (status) {
      status.value =
        'Present';
    }

    selectBus(
      '',
      'No bus assigned'
    );

    resetBusSearch();
    syncBusAvailability();
  }


  /*
  |--------------------------------------------------------------------------
  | Open Create Modal
  |--------------------------------------------------------------------------
  */

  document
    .getElementById(
      'openDriverAttendanceModal'
    )
    ?.addEventListener(
      'click',
      () => {
        resetCreateForm();
        openModal(attendanceModal);
      }
    );


  /*
  |--------------------------------------------------------------------------
  | Close Add / Edit Modal
  |--------------------------------------------------------------------------
  */

  [
    document.getElementById(
      'closeDriverAttendanceModal'
    ),

    document.getElementById(
      'cancelDriverAttendanceModal'
    ),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          closeBusDropdown();
          closeModal(attendanceModal);
        }
      );
    });


  /*
  |--------------------------------------------------------------------------
  | Edit Attendance
  |--------------------------------------------------------------------------
  */

  document
    .querySelectorAll(
      '.open-edit-driver-attendance-modal'
    )
    .forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          if (!attendanceForm) {
            return;
          }

          const attendanceId =
            button.dataset.id;

          const fallbackUrl =
            `/driver-attendance/${attendanceId}`;

          attendanceForm.setAttribute(
            'action',
            normalizeDriverAttendancePath(
              button.dataset.updateUrl,
              fallbackUrl
            )
          );

          if (attendanceFormMethod) {
            attendanceFormMethod.disabled =
              false;

            attendanceFormMethod.value =
              'PUT';
          }

          if (modalTitle) {
            modalTitle.textContent =
              'Edit Driver Attendance';
          }

          if (submitText) {
            submitText.textContent =
              'Update Record';
          }

          attendanceForm.dataset.confirmTitle =
            'Update Driver Attendance?';

          attendanceForm.dataset.confirmMessage =
            'Are you sure you want to update this driver attendance record?';

          attendanceForm.dataset.confirmButton =
            'Yes, Update Record';

          attendanceForm.dataset.confirmType =
            'update';

          if (driverId) {
            driverId.value =
              button.dataset.driverId
              || '';
          }

          if (driverName) {
            driverName.value =
              button.dataset.driverName
              || '';
          }

          if (shift) {
            shift.value =
              button.dataset.shift
              || 'Morning';
          }

          if (attendanceDate) {
            attendanceDate.value =
              button.dataset.attendanceDate
              || '';
          }

          if (timeIn) {
            timeIn.value =
              button.dataset.timeIn
              || '';
          }

          if (timeOut) {
            timeOut.value =
              button.dataset.timeOut
              || '';
          }

          if (status) {
            status.value =
              button.dataset.status
              || 'Present';
          }

          selectBusByValue(
            button.dataset.busAssignment
            || ''
          );

          resetBusSearch();
          syncBusAvailability();
          openModal(attendanceModal);
        }
      );
    });


  /*
  |--------------------------------------------------------------------------
  | View Attendance Modal
  |--------------------------------------------------------------------------
  */

  const viewAttendanceModal =
    document.getElementById(
      'viewDriverAttendanceModal'
    );

  const viewAttendanceContent =
    document.getElementById(
      'viewDriverAttendanceContent'
    );


  document
    .querySelectorAll(
      '.open-view-driver-attendance-modal'
    )
    .forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          const statusValue =
            button.dataset.status
            || 'Present';

          const statusClass =
            getAttendanceStatusClass(
              statusValue
            );

          const details = [
            [
              'Driver ID',
              button.dataset.driverId,
            ],
            [
              'Driver Name',
              button.dataset.driverName,
            ],
            [
              'Role',
              'Driver',
            ],
            [
              'Shift',
              button.dataset.shift,
            ],
            [
              'Bus / Assignment',
              button.dataset.busAssignment
              || 'Unassigned',
            ],
            [
              'Date',
              button.dataset.attendanceDate,
            ],
            [
              'Time-in',
              button.dataset.timeIn,
            ],
            [
              'Time-out',
              button.dataset.timeOut,
            ],
          ];

          if (viewAttendanceContent) {
            viewAttendanceContent.innerHTML =
              details
                .map(
                  ([label, value]) => `
                    <div class="attendance-detail-card">
                      <label>
                        ${escapeAttendanceHtml(label)}
                      </label>

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

          openModal(
            viewAttendanceModal
          );
        }
      );
    });


  [
    document.getElementById(
      'closeViewDriverAttendanceModal'
    ),

    document.getElementById(
      'closeViewDriverAttendanceButton'
    ),
  ]
    .filter(Boolean)
    .forEach((button) => {
      button.addEventListener(
        'click',
        () => {
          closeModal(
            viewAttendanceModal
          );
        }
      );
    });


  function getAttendanceStatusClass(
    value
  ) {
    const normalizedValue =
      String(value || '')
        .trim()
        .toLowerCase();

    switch (normalizedValue) {
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
      .replaceAll(
        '&',
        '&amp;'
      )
      .replaceAll(
        '<',
        '&lt;'
      )
      .replaceAll(
        '>',
        '&gt;'
      )
      .replaceAll(
        '"',
        '&quot;'
      )
      .replaceAll(
        "'",
        '&#039;'
      );
  }


  /*
  |--------------------------------------------------------------------------
  | Delete Attendance Modal
  |--------------------------------------------------------------------------
  */

  const deleteModal =
    document.getElementById(
      'deleteDriverAttendanceModal'
    );

  const deleteName =
    document.getElementById(
      'deleteDriverAttendanceName'
    );

  let selectedDeleteForm = null;


  document
    .querySelectorAll(
      '.open-delete-driver-attendance-modal'
    )
    .forEach((button) => {
      button.addEventListener(
        'click',
        (event) => {
          event.preventDefault();

          const attendanceId =
            button.dataset.id;

          selectedDeleteForm =
            document.getElementById(
              `deleteDriverAttendanceForm-${attendanceId}`
            );

          if (deleteName) {
            deleteName.textContent =
              button.dataset.driverName
              || button.dataset.driverId
              || 'this driver attendance record';
          }

          openModal(deleteModal);
        }
      );
    });


  document
    .getElementById(
      'cancelDeleteDriverAttendance'
    )
    ?.addEventListener(
      'click',
      () => {
        selectedDeleteForm = null;
        closeModal(deleteModal);
      }
    );


  document
    .getElementById(
      'confirmDeleteDriverAttendance'
    )
    ?.addEventListener(
      'click',
      () => {
        selectedDeleteForm
          ?.requestSubmit();
      }
    );


  /*
  |--------------------------------------------------------------------------
  | Close Modal by Clicking Overlay
  |--------------------------------------------------------------------------
  */

  document
    .querySelectorAll(
      '.modal-overlay, '
      + '.ui-form-overlay, '
      + '.delete-modal-overlay, '
      + '.success-modal-overlay'
    )
    .forEach((modal) => {
      modal.addEventListener(
        'click',
        (event) => {
          if (
            event.target
            === modal
          ) {
            closeBusDropdown();
            closeModal(modal);
          }
        }
      );
    });


  /*
  |--------------------------------------------------------------------------
  | Escape Key
  |--------------------------------------------------------------------------
  */

  document.addEventListener(
    'keydown',
    (event) => {
      if (
        event.key
        !== 'Escape'
      ) {
        return;
      }

      closeBusDropdown();
      closeModal(importModal);
      closeModal(attendanceModal);
      closeModal(viewAttendanceModal);
      closeModal(deleteModal);
    }
  );
});