document.addEventListener('DOMContentLoaded', () => {

  /* =========================================================
     MODAL HELPERS
  ========================================================= */

  function openModal(modal) {

    if (!modal) {
      return;
    }

    modal.classList.add(
      'show',
      'active'
    );
  }


  function closeModal(modal) {

    if (!modal) {
      return;
    }

    modal.classList.remove(
      'show',
      'active'
    );

    modal.style.display = '';
  }


  /* =========================================================
     PART UNITS
  ========================================================= */

  function unitOptions(selectedUnit = '') {

    const units = [
      'pcs',
      'set',
      'liter',
      'gallon',
      'bottle',
      'box',
      'meter',
      'kg',
      'pack',
      'pair',
      'roll',
      'tube',
    ];


    let html =
      '<option value="">Unit</option>';


    units.forEach((unit) => {

      html += `
        <option
          value="${unit}"
          ${selectedUnit === unit ? 'selected' : ''}
        >
          ${unit}
        </option>
      `;

    });


    return html;
  }


  /* =========================================================
     HTML ESCAPE
  ========================================================= */

  function escapeInputValue(value) {

    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }


  /* =========================================================
     PART INDEXES
  ========================================================= */

  function refreshPartIndexes(wrapper) {

    if (!wrapper) {
      return;
    }


    wrapper
      .querySelectorAll(
        '.part-needed-row'
      )
      .forEach(
        (row, index) => {

          const nameInput =
            row.querySelector(
              'input[name*="[name]"]'
            );


          const quantityInput =
            row.querySelector(
              'input[name*="[quantity]"]'
            );


          const unitSelect =
            row.querySelector(
              'select[name*="[unit]"]'
            );


          if (nameInput) {

            nameInput.name =
              `parts[${index}][name]`;

          }


          if (quantityInput) {

            quantityInput.name =
              `parts[${index}][quantity]`;

          }


          if (unitSelect) {

            unitSelect.name =
              `parts[${index}][unit]`;

          }

        }
      );
  }


  /* =========================================================
     CREATE PART ROW
  ========================================================= */

  function createPartRow(
    index,
    part = {},
    isReadonly = false
  ) {

    const row =
      document.createElement('div');


    row.className =
      'jo-part-row part-needed-row';


    row.innerHTML = `
      <input
        type="text"
        name="parts[${index}][name]"
        placeholder="Part name"
        value="${escapeInputValue(part.name || '')}"
        ${isReadonly ? 'disabled' : ''}
      >

      <input
        type="number"
        name="parts[${index}][quantity]"
        min="1"
        placeholder="Qty"
        value="${escapeInputValue(part.quantity || '')}"
        ${isReadonly ? 'disabled' : ''}
      >

      <select
        name="parts[${index}][unit]"
        ${isReadonly ? 'disabled' : ''}
      >
        ${unitOptions(part.unit || '')}
      </select>

      <button
        type="button"
        class="remove-part-btn"
        title="Remove Part"
        ${isReadonly ? 'disabled' : ''}
      >
        <i class="fa-solid fa-trash"></i>
      </button>
    `;


    return row;
  }


  /* =========================================================
     CLEAR PART INPUTS
  ========================================================= */

  function clearPartInputs(wrapper) {

    if (!wrapper) {
      return;
    }


    const rows =
      wrapper.querySelectorAll(
        '.part-needed-row'
      );


    rows.forEach(
      (row, index) => {

        /*
         * Keep one empty row.
         */
        if (index > 0) {

          row.remove();

          return;
        }


        row
          .querySelectorAll('input')
          .forEach((input) => {

            input.value = '';

          });


        row
          .querySelectorAll('select')
          .forEach((select) => {

            select.value = '';

          });

      }
    );


    refreshPartIndexes(wrapper);
  }


  /* =========================================================
     GENERIC PART REPEATER
  ========================================================= */

  function setupPartsRepeater(
    wrapperId,
    addButtonId
  ) {

    const wrapper =
      document.getElementById(
        wrapperId
      );


    const addButton =
      document.getElementById(
        addButtonId
      );


    if (
      !wrapper ||
      !addButton
    ) {
      return;
    }


    addButton.addEventListener(
      'click',
      () => {

        if (addButton.disabled) {
          return;
        }


        const index =
          wrapper.querySelectorAll(
            '.part-needed-row'
          ).length;


        wrapper.appendChild(
          createPartRow(index)
        );


        refreshPartIndexes(
          wrapper
        );
      }
    );


    wrapper.addEventListener(
      'click',
      (event) => {

        const removeButton =
          event.target.closest(
            '.remove-part-btn'
          );


        if (
          !removeButton ||
          removeButton.disabled
        ) {
          return;
        }


        const row =
          removeButton.closest(
            '.part-needed-row'
          );


        if (!row) {
          return;
        }


        const rows =
          wrapper.querySelectorAll(
            '.part-needed-row'
          );


        /*
         * Keep at least one row.
         */
        if (rows.length === 1) {

          row
            .querySelectorAll('input')
            .forEach((input) => {

              input.value = '';

            });


          row
            .querySelectorAll('select')
            .forEach((select) => {

              select.value = '';

            });


          return;
        }


        row.remove();


        refreshPartIndexes(
          wrapper
        );
      }
    );


    refreshPartIndexes(
      wrapper
    );
  }


  /* =========================================================
     NEW JO MODAL
  ========================================================= */

  const jobModal =
    document.getElementById(
      'jobModal'
    );


  const openJobModal =
    document.getElementById(
      'openJobModal'
    );


  const closeJobModal =
    document.getElementById(
      'closeJobModal'
    );


  const cancelJobModal =
    document.getElementById(
      'cancelJobModal'
    );


  if (openJobModal) {

    openJobModal.addEventListener(
      'click',
      () => {

        openModal(jobModal);

      }
    );
  }


  if (closeJobModal) {

    closeJobModal.addEventListener(
      'click',
      () => {

        closeModal(jobModal);

      }
    );
  }


  if (cancelJobModal) {

    cancelJobModal.addEventListener(
      'click',
      () => {

        closeModal(jobModal);

      }
    );
  }


  setupPartsRepeater(
    'partsNeededWrapper',
    'addPartBtn'
  );


  /* =========================================================
     NEW JO
     MECHANIC → REQUESTED PARTS
  ========================================================= */

  const jobAssignedMechanic =
    document.getElementById(
      'jobAssignedMechanic'
    );


  const partsNeededWrapper =
    document.getElementById(
      'partsNeededWrapper'
    );


  const addPartBtn =
    document.getElementById(
      'addPartBtn'
    );


  const newRequestedPartsSection =
    document.getElementById(
      'newRequestedPartsSection'
    );


  const newPartsLockedNotice =
    document.getElementById(
      'newPartsLockedNotice'
    );


  function updateNewJoPartsState() {

    const hasMechanic =
      Boolean(
        jobAssignedMechanic
          ?.value
          ?.trim()
      );


    /*
     * Add Part
     */
    if (addPartBtn) {

      addPartBtn.disabled =
        !hasMechanic;
    }


    /*
     * All current part fields
     */
    if (partsNeededWrapper) {

      partsNeededWrapper
        .querySelectorAll(
          'input, select, button'
        )
        .forEach((field) => {

          field.disabled =
            !hasMechanic;

        });
    }


    /*
     * Visual lock state
     */
    if (newRequestedPartsSection) {

      newRequestedPartsSection
        .classList
        .toggle(
          'is-locked',
          !hasMechanic
        );
    }


    /*
     * Lock message
     */
    if (newPartsLockedNotice) {

      newPartsLockedNotice.style.display =
        hasMechanic
          ? 'none'
          : 'flex';
    }


    /*
     * No mechanic = no requested parts
     */
    if (
      !hasMechanic &&
      partsNeededWrapper
    ) {

      clearPartInputs(
        partsNeededWrapper
      );


      partsNeededWrapper
        .querySelectorAll(
          'input, select, button'
        )
        .forEach((field) => {

          field.disabled = true;

        });
    }

  }


  if (jobAssignedMechanic) {

    jobAssignedMechanic.addEventListener(
      'change',
      updateNewJoPartsState
    );
  }


  updateNewJoPartsState();


  /* =========================================================
     EDIT / VIEW MODAL REFERENCES
  ========================================================= */

  const editJobModal =
    document.getElementById(
      'editJobModal'
    );


  const editJobForm =
    document.getElementById(
      'editJobForm'
    );


  const editJobOrderNo =
    document.getElementById(
      'edit_job_order_no'
    );


  const editBusNo =
    document.getElementById(
      'edit_bus_no'
    );


  const editProblemIssue =
    document.getElementById(
      'edit_problem_issue'
    );


  const editMaintenanceType =
    document.getElementById(
      'edit_maintenance_type'
    );


  const editStatus =
    document.getElementById(
      'edit_status'
    );


  const editAssignedMechanic =
    document.getElementById(
      'edit_assigned_mechanic'
    );


  const editPartsNeededWrapper =
    document.getElementById(
      'editPartsNeededWrapper'
    );


  const editRequestedPartsSection =
    document.getElementById(
      'editRequestedPartsSection'
    );


  const editPartsLockedNotice =
    document.getElementById(
      'editPartsLockedNotice'
    );


  const editJobMainActions =
    document.getElementById(
      'editJobMainActions'
    );


  const viewOnlyJobActions =
    document.getElementById(
      'viewOnlyJobActions'
    );


  const editAddPartBtn =
    document.getElementById(
      'editAddPartBtn'
    );


  const closeEditJobModal =
    document.getElementById(
      'closeEditJobModal'
    );


  const cancelEditJobModal =
    document.getElementById(
      'cancelEditJobModal'
    );


  const closeViewOnlyJob =
    document.getElementById(
      'closeViewOnlyJob'
    );


  const editModalSubtitle =
    document.getElementById(
      'editModalSubtitle'
    );


  const editModeDescription =
    document.getElementById(
      'editModeDescription'
    );


  let currentEditIsViewOnly =
    false;


  /* =========================================================
     EDIT READONLY MODE
  ========================================================= */

  function setEditModalReadonly(
    isReadonly
  ) {

    currentEditIsViewOnly =
      isReadonly;


    [
      editBusNo,
      editProblemIssue,
      editMaintenanceType,
      editStatus,
      editAssignedMechanic,
    ].forEach((field) => {

      if (field) {

        field.disabled =
          isReadonly;
      }
    });


    if (editPartsNeededWrapper) {

      editPartsNeededWrapper
        .querySelectorAll(
          'input, select, button'
        )
        .forEach((field) => {

          field.disabled =
            isReadonly;

        });
    }


    if (editAddPartBtn) {

      editAddPartBtn.style.display =
        isReadonly
          ? 'none'
          : 'inline-flex';
    }


    if (editJobMainActions) {

      editJobMainActions.style.display =
        isReadonly
          ? 'none'
          : 'flex';
    }


    if (viewOnlyJobActions) {

      viewOnlyJobActions.style.display =
        isReadonly
          ? 'flex'
          : 'none';
    }


    if (editModalSubtitle) {

      editModalSubtitle.textContent =
        isReadonly
          ? 'View the selected job order details.'
          : 'Review and update the selected job order.';
    }


    if (editModeDescription) {

      editModeDescription.textContent =
        isReadonly
          ? 'This Job Order is view-only because it is completed or its Purchase Request is already being processed.'
          : 'Review and update the selected job order.';
    }
  }


  /* =========================================================
     PARSE SAVED PARTS
  ========================================================= */

  function parseParts(partNeeded) {

    if (!partNeeded) {
      return [];
    }


    return String(partNeeded)
      .split(',')
      .map((part) => {

        const cleanPart =
          part.trim();


        if (!cleanPart) {
          return null;
        }


        if (
          cleanPart.includes(
            ' - Qty:'
          )
        ) {

          const pieces =
            cleanPart.split(
              ' - Qty:'
            );


          const name =
            pieces[0]?.trim() || '';


          const quantityWithUnit =
            pieces[1]?.trim() || '';


          const match =
            quantityWithUnit.match(
              /^(\d+)\s*(.*)$/
            );


          return {

            name,

            quantity:
              match
                ? match[1]
                : '',

            unit:
              match && match[2]
                ? match[2].trim()
                : '',

          };
        }


        return {

          name:
            cleanPart,

          quantity:
            '',

          unit:
            '',

        };
      })
      .filter(Boolean);
  }


  /* =========================================================
     RENDER EDIT PARTS
  ========================================================= */

  function renderEditParts(
    partNeeded,
    isReadonly
  ) {

    if (!editPartsNeededWrapper) {
      return;
    }


    const parts =
      parseParts(
        partNeeded
      );


    const rows =
      parts.length
        ? parts
        : [
            {
              name: '',
              quantity: '',
              unit: '',
            },
          ];


    editPartsNeededWrapper.innerHTML =
      '';


    rows.forEach(
      (part, index) => {

        editPartsNeededWrapper
          .appendChild(
            createPartRow(
              index,
              part,
              isReadonly
            )
          );
      }
    );


    refreshPartIndexes(
      editPartsNeededWrapper
    );
  }


  /* =========================================================
     CURRENT MECHANIC OPTION
  ========================================================= */

  function setEditMechanicOptions(
    currentMechanic = ''
  ) {

    if (!editAssignedMechanic) {
      return;
    }


    const currentValue =
      String(
        currentMechanic || ''
      ).trim();


    const oldCurrentOption =
      Array.from(
        editAssignedMechanic.options
      )
      .find(
        (option) =>
          option.dataset
            .currentAssignment ===
          'true'
      );


    if (oldCurrentOption) {

      oldCurrentOption.remove();

    }


    const alreadyExists =
      Array.from(
        editAssignedMechanic.options
      )
      .some(
        (option) =>
          option.value ===
          currentValue
      );


    if (
      currentValue &&
      !alreadyExists
    ) {

      const currentOption =
        document.createElement(
          'option'
        );


      currentOption.value =
        currentValue;


      currentOption.textContent =
        `${currentValue} (Current assignment)`;


      currentOption.dataset
        .currentAssignment =
        'true';


      editAssignedMechanic
        .insertBefore(
          currentOption,
          editAssignedMechanic.options[1]
            || null
        );
    }


    editAssignedMechanic.value =
      currentValue;
  }


  /* =========================================================
     EDIT
     MECHANIC → STATUS + PARTS
  ========================================================= */

  function updateEditJoPartsState() {

    if (
      !editAssignedMechanic ||
      !editPartsNeededWrapper
    ) {
      return;
    }


    /*
     * View only stays completely locked.
     */
    if (currentEditIsViewOnly) {

      return;
    }


    const hasMechanic =
      Boolean(
        editAssignedMechanic
          .value
          .trim()
      );


    /*
     * Status becomes automatic.
     */
    if (editStatus) {

      editStatus.value =
        hasMechanic
          ? 'On Going'
          : 'On Hold';
    }


    /*
     * Status should not be manually changed.
     */
    if (editStatus) {

      editStatus.disabled =
        true;
    }


    /*
     * Add Part button
     */
    if (editAddPartBtn) {

      editAddPartBtn.disabled =
        !hasMechanic;
    }


    /*
     * Part fields
     */
    editPartsNeededWrapper
      .querySelectorAll(
        'input, select, button'
      )
      .forEach((field) => {

        field.disabled =
          !hasMechanic;

      });


    /*
     * Visual lock
     */
    if (editRequestedPartsSection) {

      editRequestedPartsSection
        .classList
        .toggle(
          'is-locked',
          !hasMechanic
        );
    }


    /*
     * Lock notice
     */
    if (editPartsLockedNotice) {

      editPartsLockedNotice
        .style
        .display =
          hasMechanic
            ? 'none'
            : 'flex';
    }


    /*
     * Mechanic removed = remove parts.
     */
    if (!hasMechanic) {

      clearPartInputs(
        editPartsNeededWrapper
      );


      editPartsNeededWrapper
        .querySelectorAll(
          'input, select, button'
        )
        .forEach((field) => {

          field.disabled = true;

        });
    }
  }


  if (editAssignedMechanic) {

    editAssignedMechanic
      .addEventListener(
        'change',
        updateEditJoPartsState
      );
  }


  /* =========================================================
     OPEN EDIT / VIEW
  ========================================================= */

  document
    .querySelectorAll(
      '.open-edit-modal'
    )
    .forEach((button) => {

      button.addEventListener(
        'click',
        (event) => {

          event.preventDefault();


          const id =
            button.dataset.id;


          const status =
            button.dataset.status ||
            'On Going';


          const isCompleted =
            status === 'Completed';


          const isViewOnly =
            button.dataset
              .viewOnly ===
            '1';


          const shouldBeViewOnly =
            isCompleted ||
            isViewOnly;


          if (editJobForm) {

            editJobForm.action =
              `/job-orders/${id}`;
          }


          if (editJobOrderNo) {

            editJobOrderNo.value =
              button.dataset
                .jobOrderNo ||
              '';
          }


          if (editBusNo) {

            editBusNo.value =
              button.dataset
                .busNo ||
              '';
          }


          if (editProblemIssue) {

            editProblemIssue.value =
              button.dataset
                .problemIssue ||
              '';
          }


          if (editMaintenanceType) {

            editMaintenanceType.value =
              button.dataset
                .maintenanceType ||
              '';
          }


          if (editStatus) {

            editStatus.value =
              status;
          }


          setEditMechanicOptions(
            button.dataset
              .assignedMechanic ||
            ''
          );


          renderEditParts(
            button.dataset
              .partNeeded ||
            '',
            shouldBeViewOnly
          );


          setEditModalReadonly(
            shouldBeViewOnly
          );


          if (!shouldBeViewOnly) {

            updateEditJoPartsState();

          }


          openModal(
            editJobModal
          );

        }
      );
    });


  /* =========================================================
     EDIT PART REPEATER
  ========================================================= */

  if (
    editAddPartBtn &&
    editPartsNeededWrapper
  ) {

    editAddPartBtn
      .addEventListener(
        'click',
        () => {

          if (
            editAddPartBtn.disabled
          ) {
            return;
          }


          const index =
            editPartsNeededWrapper
              .querySelectorAll(
                '.part-needed-row'
              ).length;


          editPartsNeededWrapper
            .appendChild(
              createPartRow(index)
            );


          refreshPartIndexes(
            editPartsNeededWrapper
          );

        }
      );


    editPartsNeededWrapper
      .addEventListener(
        'click',
        (event) => {

          const removeButton =
            event.target.closest(
              '.remove-part-btn'
            );


          if (
            !removeButton ||
            removeButton.disabled
          ) {
            return;
          }


          const row =
            removeButton.closest(
              '.part-needed-row'
            );


          if (!row) {
            return;
          }


          const rows =
            editPartsNeededWrapper
              .querySelectorAll(
                '.part-needed-row'
              );


          /*
           * Keep one empty row.
           */
          if (
            rows.length === 1
          ) {

            row
              .querySelectorAll('input')
              .forEach(
                (input) => {

                  input.value =
                    '';

                }
              );


            row
              .querySelectorAll('select')
              .forEach(
                (select) => {

                  select.value =
                    '';

                }
              );


            return;
          }


          row.remove();


          refreshPartIndexes(
            editPartsNeededWrapper
          );

        }
      );
  }


  /* =========================================================
     EDIT MODAL CLOSE
  ========================================================= */

  if (closeEditJobModal) {

    closeEditJobModal
      .addEventListener(
        'click',
        () => {

          closeModal(
            editJobModal
          );

        }
      );
  }


  if (cancelEditJobModal) {

    cancelEditJobModal
      .addEventListener(
        'click',
        () => {

          closeModal(
            editJobModal
          );

        }
      );
  }


  if (closeViewOnlyJob) {

    closeViewOnlyJob
      .addEventListener(
        'click',
        () => {

          closeModal(
            editJobModal
          );

        }
      );
  }


  /* =========================================================
     DELETE MODAL
  ========================================================= */

  const deleteJobModal =
    document.getElementById(
      'deleteJobModal'
    );


  const deleteJoNo =
    document.getElementById(
      'deleteJoNo'
    );


  const cancelDeleteJob =
    document.getElementById(
      'cancelDeleteJob'
    );


  const confirmDeleteJob =
    document.getElementById(
      'confirmDeleteJob'
    );


  let selectedDeleteForm =
    null;


  document
    .querySelectorAll(
      '.open-delete-modal'
    )
    .forEach((button) => {

      button.addEventListener(
        'click',
        (event) => {

          event.preventDefault();
          event.stopPropagation();


          if (button.disabled) {
            return;
          }


          const id =
            button.dataset.id;


          const joNo =
            button.dataset.joNo;


          selectedDeleteForm =
            document.getElementById(
              `deleteForm-${id}`
            );


          if (!selectedDeleteForm) {

            console.error(
              `Delete form deleteForm-${id} was not found.`
            );

            return;
          }


          if (deleteJoNo) {

            deleteJoNo.textContent =
              joNo ||
              'this job order';
          }


          if (confirmDeleteJob) {

            confirmDeleteJob.disabled =
              false;


            confirmDeleteJob.innerHTML =
              'Yes, Delete';
          }


          openModal(
            deleteJobModal
          );

        }
      );
    });


  if (cancelDeleteJob) {

    cancelDeleteJob
      .addEventListener(
        'click',
        (event) => {

          event.preventDefault();
          event.stopPropagation();


          selectedDeleteForm =
            null;


          closeModal(
            deleteJobModal
          );

        }
      );
  }


  if (confirmDeleteJob) {

    confirmDeleteJob
      .addEventListener(
        'click',
        (event) => {

          event.preventDefault();
          event.stopPropagation();


          if (!selectedDeleteForm) {

            console.error(
              'No Job Order delete form was selected.'
            );

            return;
          }


          confirmDeleteJob.disabled =
            true;


          confirmDeleteJob.innerHTML =
            `
              <i
                class="
                  fa-solid
                  fa-spinner
                  fa-spin
                "
              ></i>
              Deleting...
            `;


          selectedDeleteForm
            .requestSubmit();

        }
      );
  }


  /* =========================================================
     FINISH JOB ORDER
  ========================================================= */

  const finishJobModal =
    document.getElementById(
      'finishJobModal'
    );


  const finishJoNo =
    document.getElementById(
      'finishJoNo'
    );


  const cancelFinishJob =
    document.getElementById(
      'cancelFinishJob'
    );


  const confirmFinishJob =
    document.getElementById(
      'confirmFinishJob'
    );


  let selectedFinishForm =
    null;


  document
    .querySelectorAll(
      '.open-finish-modal'
    )
    .forEach((button) => {

      button.addEventListener(
        'click',
        () => {

          const id =
            button.dataset.id;


          const joNo =
            button.dataset.joNo;


          selectedFinishForm =
            document.getElementById(
              `finishForm-${id}`
            );


          if (!selectedFinishForm) {

            console.error(
              `Finish form finishForm-${id} was not found.`
            );

            return;
          }


          if (finishJoNo) {

            finishJoNo.textContent =
              joNo ||
              'this job order';
          }


          openModal(
            finishJobModal
          );

        }
      );
    });


  if (cancelFinishJob) {

    cancelFinishJob
      .addEventListener(
        'click',
        () => {

          selectedFinishForm =
            null;


          closeModal(
            finishJobModal
          );

        }
      );
  }


  if (confirmFinishJob) {

    confirmFinishJob
      .addEventListener(
        'click',
        () => {

          if (!selectedFinishForm) {
            return;
          }


          confirmFinishJob.disabled =
            true;


          confirmFinishJob.innerHTML =
            `
              <i
                class="
                  fa-solid
                  fa-spinner
                  fa-spin
                "
              ></i>
              Finishing...
            `;


          selectedFinishForm
            .requestSubmit();

        }
      );
  }


  /* =========================================================
     PART STATUS FILTER
  ========================================================= */

  const partStatusFilter =
    document.getElementById(
      'partStatusFilter'
    );


  if (partStatusFilter) {

    const classes = [
      'not-requested',
      'submitted',
      'approved',
      'rejected',
      'for-purchase',
      'ordered',
      'for-pick-up',
      'for-delivery',
      'delivered',
      'picked-up',
      'issued',
      'no-parts-needed',
    ];


    function slugStatus(value) {

      return String(value || '')
        .toLowerCase()
        .replace(/\//g, '-')
        .replace(/\s+/g, '-');
    }


    function updatePartStatusFilterColor() {

      partStatusFilter
        .classList
        .remove(
          ...classes
        );


      const value =
        partStatusFilter.value;


      if (
        value &&
        value !==
          'All Part Statuses'
      ) {

        partStatusFilter
          .classList
          .add(
            slugStatus(value)
          );
      }
    }


    updatePartStatusFilterColor();


    partStatusFilter
      .addEventListener(
        'change',
        updatePartStatusFilterColor
      );
  }


  /* =========================================================
     VALIDATION / FEEDBACK MODALS
  ========================================================= */

  document
    .querySelectorAll(
      '.success-modal-overlay button, ' +
      '.success-modal-overlay .feedback-ok-btn, ' +
      '.success-modal-overlay .close-feedback-modal'
    )
    .forEach((button) => {

      button.addEventListener(
        'click',
        () => {

          const modal =
            button.closest(
              '.success-modal-overlay'
            );


          if (modal) {

            closeModal(
              modal
            );
          }

        }
      );
    });


  const closeValidationErrorModal =
    document.getElementById(
      'closeValidationErrorModal'
    );


  if (closeValidationErrorModal) {

    closeValidationErrorModal
      .addEventListener(
        'click',
        () => {

          const modal =
            document.getElementById(
              'validationErrorModal'
            );


          closeModal(modal);

        }
      );
  }


  /* =========================================================
     BACKDROP CLOSE
  ========================================================= */

  document
    .querySelectorAll(
      '.modal-overlay:not(.global-confirmation-overlay), ' +
      '.delete-modal-overlay, ' +
      '.success-modal-overlay, ' +
      '.ui-form-overlay'
    )
    .forEach((modal) => {

      modal.addEventListener(
        'click',
        (event) => {

          if (
            event.target === modal
          ) {

            closeModal(
              modal
            );
          }

        }
      );
    });


  /* =========================================================
     AVAILABLE MECHANICS REFRESH
  ========================================================= */

  async function refreshAvailableMechanicsDropdown() {

    const newJobMechanicSelect =
      document.querySelector(
        '#jobModal select[name="assigned_mechanic"]'
      );


    try {

      const response =
        await fetch(
          '/job-orders/available-mechanics',
          {
            method: 'GET',

            headers: {
              Accept:
                'application/json',

              'X-Requested-With':
                'XMLHttpRequest',
            },
          }
        );


      if (!response.ok) {

        throw new Error(
          'Unable to load available mechanics.'
        );
      }


      const mechanics =
        await response.json();


      /* =====================================================
         NEW JO SELECT
      ====================================================== */

      if (newJobMechanicSelect) {

        const selectedMechanic =
          newJobMechanicSelect.value;


        newJobMechanicSelect
          .innerHTML =
          '';


        const defaultOption =
          document.createElement(
            'option'
          );


        defaultOption.value =
          '';


        defaultOption.textContent =
          mechanics.length
            ? 'Select Available Mechanic'
            : 'No available mechanic - JO will be On Hold';


        newJobMechanicSelect
          .appendChild(
            defaultOption
          );


        mechanics.forEach(
          (mechanic) => {

            const option =
              document.createElement(
                'option'
              );


            option.value =
              mechanic.mechanic_name;


            option.textContent =
              mechanic.mechanic_name;


            if (
              mechanic.mechanic_name ===
              selectedMechanic
            ) {

              option.selected =
                true;
            }


            newJobMechanicSelect
              .appendChild(
                option
              );

          }
        );


        updateNewJoPartsState();
      }


      /* =====================================================
         EDIT SELECT
      ====================================================== */

      if (editAssignedMechanic) {

        const currentAssigned =
          editAssignedMechanic.value;


        Array.from(
          editAssignedMechanic.options
        )
          .filter(
            (option) =>
              option.value !== '' &&
              option.dataset
                .currentAssignment !==
                'true'
          )
          .forEach(
            (option) =>
              option.remove()
          );


        mechanics.forEach(
          (mechanic) => {

            const exists =
              Array.from(
                editAssignedMechanic.options
              )
              .some(
                (option) =>
                  option.value ===
                  mechanic.mechanic_name
              );


            if (!exists) {

              const option =
                document.createElement(
                  'option'
                );


              option.value =
                mechanic.mechanic_name;


              option.textContent =
                mechanic.mechanic_name;


              editAssignedMechanic
                .appendChild(
                  option
                );
            }

          }
        );


        setEditMechanicOptions(
          currentAssigned
        );
      }

    } catch (error) {

      console.error(
        'Unable to update available mechanic dropdown:',
        error
      );
    }
  }


  /* =========================================================
     REAL-TIME ATTENDANCE UPDATE
  ========================================================= */

  window.addEventListener(
    'system-data-updated',
    (event) => {

      const payload =
        event.detail;


      if (
        payload &&
        payload.module ===
          'Operation' &&
        payload.entity ===
          'Attendance'
      ) {

        refreshAvailableMechanicsDropdown();

      }

    }
  );

});