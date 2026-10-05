import gsap from 'gsap';

window.GCTPartialNavigation.registerInitializer('maintenance-job-orders', '.jo-page', () => {

  /* =========================================================
     GSAP MOTION HELPERS
  ========================================================= */

  const prefersReducedMotion = () => {
    if (
      window.__GCT_FORCE_MOTION__ === true
      || window.__GCT_ENABLE_MOTION__ === true
      || document.documentElement.dataset.gctMotion === 'enabled'
    ) {
      return false;
    }

    return (
      window.matchMedia?.(
        '(prefers-reduced-motion: reduce)'
      )?.matches ?? false
    );
  };


  function getModalSurface(modal) {

    return modal
      ?.querySelector(
        '.ui-form-modal, ' +
        '.delete-modal-box, ' +
        '.modal-card, ' +
        '.success-modal-box'
      )
      || null;
  }


  function resetModalAnimationState(
    modal,
    surface = null
  ) {

    const targets = [
      modal,
      surface,
    ].filter(Boolean);


    if (targets.length) {

      gsap.set(
        targets,
        {
          clearProps:
            'opacity,transform',
        }
      );
    }


    if (modal) {

      delete modal.dataset
        .gsapClosing;
    }
  }


  function animatePartRowIn(row) {
    if (!row) {
      return;
    }

    const isReduced = typeof prefersReducedMotion === 'function'
      ? prefersReducedMotion()
      : Boolean(prefersReducedMotion);

    const xOffset = isReduced ? -6 : -16;

    gsap.fromTo(
      row,
      {
        opacity: 0,
        x: xOffset,
        y: -6,
      },
      {
        opacity: 1,
        x: 0,
        y: 0,
        duration: isReduced ? 0.22 : 0.30,
        ease: 'power3.out',
        clearProps:
          'opacity,transform',
      }
    );
  }


  function animatePartRowOut(
    row,
    onComplete
  ) {

    if (!row) {
      onComplete?.();
      return;
    }


    const isReduced = typeof prefersReducedMotion === 'function'
      ? prefersReducedMotion()
      : Boolean(prefersReducedMotion);


    gsap.killTweensOf(row);


    gsap.to(
      row,
      {
        opacity: 0,
        x: isReduced ? 10 : 24,
        duration: isReduced ? 0.16 : 0.22,
        ease: 'power2.in',
        onComplete: () => {

          gsap.set(
            row,
            {
              clearProps:
                'opacity,transform',
            }
          );


          onComplete?.();
        },
      }
    );
  }


  /*
   * Initial Job Order page reveal is owned by maintenance-animations.js.
   * Keeping the cards/table at their final layout prevents users from seeing
   * the page assemble after the navigation loader disappears.
   */


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


    modal.style.display = '';


    delete modal.dataset
      .gsapClosing;


    window.GCTSystemAnimations
      ?.animateModalOpen?.(modal);
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

    const surface =
      getModalSurface(modal);

    const finishClose = () => {
      modal.classList.remove(
        'show',
        'active'
      );

      modal.style.display = '';

      resetModalAnimationState(
        modal,
        surface
      );
    };

    if (
      !window.GCTSystemAnimations
        ?.animateModalClose?.(
          modal,
          finishClose
        )
    ) {
      finishClose();
    }
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


        const row =
          createPartRow(index);


        wrapper.appendChild(
          row
        );


        refreshPartIndexes(
          wrapper
        );


        animatePartRowIn(
          row
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


        animatePartRowOut(
          row,
          () => {

            row.remove();


            refreshPartIndexes(
              wrapper
            );
          }
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


  const isPmsCreateFlow =
    jobModal?.dataset
      .pmsCreate ===
    'true';


  function cleanPmsCreateUrl() {

    const cleanUrl =
      new URL(
        window.location.href
      );


    [
      'create_pms',
      'pms_schedule_id',
      'bus_no',
      'maintenance_type',
      'problem_issue',
    ].forEach(
      (key) =>
        cleanUrl.searchParams
          .delete(key)
    );


    return cleanUrl.href;
  }


  function cancelNewJobOrder() {

    closeModal(jobModal);


    if (!isPmsCreateFlow) {
      return;
    }


    if (jobModal) {
      jobModal.dataset.pmsCreate =
        'false';
    }


    const cleanUrl =
      cleanPmsCreateUrl();


    /*
     * Replace the PMS-prefill URL immediately so refresh/back/initializer
     * re-runs cannot reopen a cancelled Job Order modal.
     */
    window.history.replaceState(
      {
        ...window.history.state,
      },
      '',
      cleanUrl
    );


    window.setTimeout(
      () => {

        if (
          window
            .GCTPartialNavigation
            ?.navigate
        ) {
          void window
            .GCTPartialNavigation
            .navigate(
              cleanUrl,
              {
                push: false,
              }
            );

          return;
        }


        window.location.replace(
          cleanUrl
        );
      },
      prefersReducedMotion
        ? 0
        : 240
    );
  }


  if (openJobModal) {

    openJobModal.addEventListener(
      'click',
      () => {

        /*
         * Always pull the latest attendance state before the New JO modal
         * is used. This keeps newly recorded Present/Late mechanics
         * selectable even when Reverb was temporarily disconnected.
         */
        void refreshAvailableMechanicsDropdown();

        openModal(jobModal);

      }
    );
  }


  if (closeJobModal) {

    closeJobModal.addEventListener(
      'click',
      () => {

        cancelNewJobOrder();

      }
    );
  }


  if (cancelJobModal) {

    cancelJobModal.addEventListener(
      'click',
      () => {

        cancelNewJobOrder();

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


  /*
   * PMS-created Job Orders are opened only after all page initializers
   * have had a chance to build the standard JO layout/comboboxes.
   * This avoids the old inline script opening the raw modal too early.
   */
  if (isPmsCreateFlow) {

    window.requestAnimationFrame(
      () => {

        void refreshAvailableMechanicsDropdown();

        openModal(jobModal);
      }
    );
  }


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
     CURRENT JOB ORDER BUS
  ========================================================= */

  function setEditBusOption(
    currentBus = ''
  ) {

    if (!editBusNo) {
      return;
    }


    const busValue =
      String(
        currentBus || ''
      ).trim();


    editBusNo
      .querySelectorAll(
        'option[data-current-job-bus="true"]'
      )
      .forEach(
        option =>
          option.remove()
      );


    if (!busValue) {

      editBusNo.value = '';
      return;
    }


    const existingOption =
      Array.from(
        editBusNo.options
      )
      .find(
        option =>
          String(option.value) ===
          busValue
      );


    if (!existingOption) {

      const currentOption =
        document.createElement(
          'option'
        );


      currentOption.value =
        busValue;


      currentOption.textContent =
        busValue;


      currentOption.dataset
        .currentJobBus =
        'true';


      editBusNo.appendChild(
        currentOption
      );

    }


    editBusNo.value =
      busValue;


    editBusNo.dataset
      .lockedValue =
      busValue;

  }


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

  document.addEventListener(
    'click',
    (event) => {

      const button =
        event.target.closest(
          '.open-edit-modal'
        );


      if (!button) {
        return;
      }


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
          button.dataset.updateUrl || '';
      }


      if (editJobOrderNo) {

        editJobOrderNo.value =
          button.dataset
            .jobOrderNo ||
          '';
      }


      setEditBusOption(
        button.dataset
          .busNo ||
        ''
      );


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


          const row =
            createPartRow(index);


          editPartsNeededWrapper
            .appendChild(
              row
            );


          refreshPartIndexes(
            editPartsNeededWrapper
          );


          animatePartRowIn(
            row
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


          animatePartRowOut(
            row,
            () => {

              row.remove();


              refreshPartIndexes(
                editPartsNeededWrapper
              );
            }
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


  const deleteJobTitle =
    deleteJobModal
      ?.querySelector('h2')
    || null;


  const deleteJobMessage =
    deleteJobModal
      ?.querySelector('p')
    || null;


  const rejectedPrDeleteWarning =
    document.createElement(
      'span'
    );


  rejectedPrDeleteWarning.textContent =
    ' The rejected Purchase Request and its related notifications will also be removed.';


  rejectedPrDeleteWarning.hidden =
    true;


  deleteJobMessage
    ?.appendChild(
      rejectedPrDeleteWarning
    );


  let selectedDeleteForm =
    null;


  document.addEventListener(
    'click',
    (event) => {

      const button =
        event.target.closest(
          '.open-delete-modal'
        );


      if (!button) {
        return;
      }


      event.preventDefault();
      event.stopPropagation();


      if (button.disabled) {
        return;
      }


      const id =
        button.dataset.id;


      const joNo =
        button.dataset.joNo;


      const hasRejectedPr =
        button.dataset
          .rejectedPr ===
        '1';


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


      if (deleteJobTitle) {

        deleteJobTitle.textContent =
          hasRejectedPr
            ? 'Delete Rejected Job Order?'
            : 'Delete Job Order?';
      }


      rejectedPrDeleteWarning.hidden =
        !hasRejectedPr;


      if (confirmDeleteJob) {

        confirmDeleteJob.disabled =
          false;


        confirmDeleteJob.innerHTML =
          hasRejectedPr
            ? 'Yes, Delete JO + PR'
            : 'Yes, Delete';
      }


      openModal(
        deleteJobModal
      );

    }
  );


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
              Completing...
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


  /*
   * The searchable mechanic combobox is initialized separately below.
   * Expose this refresh hook so opening that combobox can always fetch
   * the latest attendance state instead of relying only on page load.
   */
  window.GCTRefreshJobOrderMechanics =
    refreshAvailableMechanicsDropdown;


  /*
   * When attendance is recorded from another browser tab, the JO tab may
   * have missed the broadcast while it was in the background. Refresh the
   * open modal as soon as this tab becomes active again.
   */
  if (window.GCTJobOrderMechanicFocusHandler) {
    window.removeEventListener(
      'focus',
      window.GCTJobOrderMechanicFocusHandler
    );
  }

  window.GCTJobOrderMechanicFocusHandler =
    () => {
      if (
        jobModal?.classList.contains('show') ||
        jobModal?.classList.contains('active')
      ) {
        void refreshAvailableMechanicsDropdown();
      }
    };

  window.addEventListener(
    'focus',
    window.GCTJobOrderMechanicFocusHandler
  );


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

/* =========================================================
   CONSOLIDATED: resources/js/Maintenance/job-order-finish-guard.js
========================================================= */
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-finish-guard', '.jo-page', () => {
  document
    .querySelectorAll('.job-orders-table tbody tr')
    .forEach((row) => {
      const rejectedPartStatus = row.querySelector(
        '.part-status-badge.rejected'
      );

      const finishButton = row.querySelector(
        '.finish-btn.open-finish-modal'
      );

      if (!rejectedPartStatus || !finishButton) {
        return;
      }

      finishButton.classList.remove(
        'open-finish-modal'
      );

      finishButton.classList.add(
        'locked-finish-btn'
      );

      finishButton.disabled = true;
      finishButton.type = 'button';
      finishButton.title =
        'Cannot complete until required parts are issued by warehouse.';

      finishButton.innerHTML = `
        <i class="fa-solid fa-lock"></i>
        Complete
      `;
    });
});


/* =========================================================
   CONSOLIDATED: resources/js/Maintenance/job-order-edit-combobox.js
========================================================= */
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-edit-combobox', '.jo-page', () => {
  const editModal = document.getElementById('editJobModal');
  const editForm = document.getElementById('editJobForm');
  const busSelect = document.getElementById('edit_bus_no');
  const mechanicSelect = document.getElementById('edit_assigned_mechanic');

  if (!editModal || !editForm || !busSelect || !mechanicSelect) {
    return;
  }

  /*
   * Keep the bus value submitted, but prevent users from changing it
   * while editing an existing Job Order.
   */
  busSelect.classList.add('jo-edit-locked-select');
  busSelect.setAttribute('aria-readonly', 'true');
  busSelect.setAttribute('tabindex', '-1');

  let lockedBusValue = '';

  const lockCurrentBus = () => {
    lockedBusValue = busSelect.value;
    busSelect.dataset.lockedValue = lockedBusValue;
  };

  busSelect.addEventListener('mousedown', (event) => {
    event.preventDefault();
  });

  busSelect.addEventListener('keydown', (event) => {
    event.preventDefault();
  });

  busSelect.addEventListener('change', () => {
    if (busSelect.dataset.lockedValue) {
      busSelect.value = busSelect.dataset.lockedValue;
    }
  });

  editForm.addEventListener('submit', () => {
    if (lockedBusValue) {
      busSelect.value = lockedBusValue;
    }
  });

  /*
   * Build a searchable mechanic combobox while retaining the original
   * select as the submitted form control.
   */
  const group = mechanicSelect.closest('.ui-form-group');
  const inputWrap = mechanicSelect.closest('.ui-input-wrap');

  if (!group || !inputWrap) {
    return;
  }

  const combobox = document.createElement('div');
  combobox.className = 'jo-mechanic-combobox';

  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'jo-mechanic-combobox-trigger';
  trigger.setAttribute('aria-expanded', 'false');
  trigger.innerHTML = `
    <span class="jo-mechanic-combobox-icon">
      <i class="fa-solid fa-user-gear"></i>
    </span>
    <span class="jo-mechanic-combobox-label placeholder">
      Select available mechanic
    </span>
    <i class="fa-solid fa-chevron-down jo-mechanic-combobox-chevron"></i>
  `;

  const menu = document.createElement('div');
  menu.className = 'jo-mechanic-combobox-menu';
  menu.hidden = true;
  menu.innerHTML = `
    <div class="jo-mechanic-combobox-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input
        type="search"
        placeholder="Search mechanic name..."
        autocomplete="off"
      >
    </div>
    <div class="jo-mechanic-combobox-options"></div>
  `;

  combobox.append(trigger, menu);
  inputWrap.insertAdjacentElement('afterend', combobox);
  inputWrap.classList.add('jo-native-mechanic-select');

  const label = trigger.querySelector('.jo-mechanic-combobox-label');
  const searchInput = menu.querySelector('input');
  const optionsContainer = menu.querySelector('.jo-mechanic-combobox-options');

  const closeMenu = () => {
    menu.hidden = true;
    combobox.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  const updateLabel = () => {
    const selectedOption = mechanicSelect.options[mechanicSelect.selectedIndex];
    const selectedText = selectedOption?.value
      ? selectedOption.textContent.trim()
      : 'No mechanic assigned';

    label.textContent = selectedText;
    label.classList.toggle('placeholder', !selectedOption?.value);
  };

  const selectMechanic = (value) => {
    mechanicSelect.value = value;
    mechanicSelect.dispatchEvent(new Event('change', { bubbles: true }));
    updateLabel();
    renderOptions();
    closeMenu();
  };

  const renderOptions = () => {
    optionsContainer.innerHTML = '';

    const options = Array.from(mechanicSelect.options);

    options.forEach((option) => {
      const optionButton = document.createElement('button');
      optionButton.type = 'button';
      optionButton.className = 'jo-mechanic-combobox-option';
      optionButton.dataset.value = option.value;
      optionButton.dataset.search = option.textContent.trim().toLowerCase();

      if (option.value === mechanicSelect.value) {
        optionButton.classList.add('is-selected');
      }

      const displayText = option.value
        ? option.textContent.trim()
        : 'No mechanic assigned';

      optionButton.innerHTML = `
        <span>
          <strong>${displayText}</strong>
          <small>${option.value ? 'Available mechanic' : 'Keep Job Order on hold'}</small>
        </span>
        <i class="fa-solid fa-check"></i>
      `;

      optionButton.addEventListener('click', () => {
        selectMechanic(option.value);
      });

      optionsContainer.appendChild(optionButton);
    });

    if (!options.length) {
      optionsContainer.innerHTML = `
        <p class="jo-mechanic-combobox-empty">
          No available mechanics found.
        </p>
      `;
    }

    updateLabel();
  };

  const filterOptions = (query) => {
    const normalizedQuery = String(query || '').trim().toLowerCase();
    let visibleCount = 0;

    optionsContainer
      .querySelectorAll('.jo-mechanic-combobox-option')
      .forEach((optionButton) => {
        const searchText = optionButton.dataset.search || '';
        const matches = searchText.includes(normalizedQuery);

        optionButton.hidden = !matches;
        optionButton.style.display = matches ? '' : 'none';
        optionButton.setAttribute('aria-hidden', matches ? 'false' : 'true');

        if (matches) {
          visibleCount += 1;
        }
      });

    let emptyResult = optionsContainer.querySelector('.jo-mechanic-search-empty');

    if (!visibleCount && normalizedQuery) {
      if (!emptyResult) {
        emptyResult = document.createElement('p');
        emptyResult.className = 'jo-mechanic-combobox-empty jo-mechanic-search-empty';
        emptyResult.textContent = 'No mechanic matches your search.';
        optionsContainer.appendChild(emptyResult);
      }
    } else {
      emptyResult?.remove();
    }
  };

  const openMenu = () => {
    if (mechanicSelect.disabled) {
      return;
    }

    menu.hidden = false;
    combobox.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
    searchInput.value = '';
    filterOptions('');
    window.setTimeout(() => searchInput.focus(), 0);
  };

  const syncDisabledState = () => {
    trigger.disabled = mechanicSelect.disabled;
    combobox.classList.toggle('is-disabled', mechanicSelect.disabled);

    if (mechanicSelect.disabled) {
      closeMenu();
    }
  };

  trigger.addEventListener('click', () => {
    if (menu.hidden) {
      openMenu();
    } else {
      closeMenu();
    }
  });

  searchInput.addEventListener('input', () => {
    filterOptions(searchInput.value);
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      trigger.focus();
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();

      const firstVisibleOption = Array.from(
        optionsContainer.querySelectorAll('.jo-mechanic-combobox-option')
      ).find((optionButton) => optionButton.style.display !== 'none');

      firstVisibleOption?.click();
    }
  });

  document.addEventListener('click', (event) => {
    if (!combobox.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.open-edit-modal')) {
      return;
    }

    window.setTimeout(() => {
      lockCurrentBus();
      renderOptions();
      syncDisabledState();
    }, 0);
  });

  const mechanicObserver = new MutationObserver(() => {
    renderOptions();
    syncDisabledState();
  });

  mechanicObserver.observe(mechanicSelect, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled'],
  });

  renderOptions();
  syncDisabledState();
});


/* =========================================================
   CONSOLIDATED: resources/js/Maintenance/job-order-new-combobox.js
========================================================= */
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-new-combobox', '.jo-page', () => {
  const modal = document.getElementById('jobModal');
  const busSelect = document.getElementById('jobBusNo');
  const mechanicSelect = document.getElementById('jobAssignedMechanic');

  if (!modal) {
    return;
  }

  function setupSearchableSelect(select, config) {
    if (!select || select.dataset.searchableReady === 'true') {
      return;
    }

    const inputWrap = select.closest('.ui-input-wrap');

    if (!inputWrap) {
      return;
    }

    select.dataset.searchableReady = 'true';
    inputWrap.classList.add('jo-native-mechanic-select');

    const combobox = document.createElement('div');
    combobox.className = [
      'jo-mechanic-combobox',
      'jo-new-combobox',
      config.className || '',
    ].filter(Boolean).join(' ');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jo-mechanic-combobox-trigger';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = `
      <span class="jo-mechanic-combobox-icon">
        <i class="fa-solid ${config.icon}"></i>
      </span>
      <span class="jo-mechanic-combobox-label placeholder">
        ${config.placeholder}
      </span>
      <i class="fa-solid fa-chevron-down jo-mechanic-combobox-chevron"></i>
    `;

    const menu = document.createElement('div');
    menu.className = 'jo-mechanic-combobox-menu';
    menu.hidden = true;
    menu.innerHTML = `
      <div class="jo-mechanic-combobox-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
          type="search"
          placeholder="${config.searchPlaceholder}"
          autocomplete="off"
        >
      </div>
      <div class="jo-mechanic-combobox-options"></div>
    `;

    combobox.append(trigger, menu);
    inputWrap.insertAdjacentElement('afterend', combobox);

    const label = trigger.querySelector('.jo-mechanic-combobox-label');
    const searchInput = menu.querySelector('input');
    const optionsContainer = menu.querySelector('.jo-mechanic-combobox-options');
    const hostSection = select.closest('.jo-create-section');

    const getDisplayText = (option) => {
      const originalText = option?.textContent?.trim() || '';

      return typeof config.formatOptionText === 'function'
        ? config.formatOptionText(originalText, option?.value || '')
        : originalText;
    };

    const closeMenu = () => {
      menu.hidden = true;
      combobox.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');

      if (config.className === 'jo-bus-combobox') {
        hostSection?.classList.remove('has-open-bus-menu');
      }
    };

    const updateLabel = () => {
      const option = select.options[select.selectedIndex];
      const hasValue = Boolean(option?.value);

      label.textContent = hasValue
        ? getDisplayText(option)
        : config.placeholder;
      label.classList.toggle('placeholder', !hasValue);
    };

    const filterOptions = () => {
      const query = searchInput.value.trim().toLowerCase();
      let visible = 0;

      optionsContainer
        .querySelectorAll('.jo-mechanic-combobox-option')
        .forEach((button) => {
          const matches = button.dataset.search.includes(query);
          button.style.display = matches ? '' : 'none';

          if (matches) {
            visible += 1;
          }
        });

      let empty = optionsContainer.querySelector('.jo-mechanic-search-empty');

      if (!visible && query) {
        if (!empty) {
          empty = document.createElement('p');
          empty.className = 'jo-mechanic-combobox-empty jo-mechanic-search-empty';
          empty.textContent = config.emptyMessage;
          optionsContainer.appendChild(empty);
        }
      } else {
        empty?.remove();
      }
    };

    const selectOption = (value) => {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      updateLabel();
      renderOptions();
      closeMenu();
    };

    const renderOptions = () => {
      optionsContainer.innerHTML = '';

      Array.from(select.options).forEach((option) => {
        if (!option.value) {
          return;
        }

        const originalText = option.textContent.trim();
        const displayText = getDisplayText(option);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'jo-mechanic-combobox-option';
        button.dataset.value = option.value;
        button.dataset.search = `${option.value} ${originalText}`.toLowerCase();

        if (option.value === select.value) {
          button.classList.add('is-selected');
        }

        button.innerHTML = `
          <span>
            <strong>${displayText}</strong>
            <small>${config.optionHint}</small>
          </span>
          <i class="fa-solid fa-check"></i>
        `;

        button.addEventListener('click', () => selectOption(option.value));
        optionsContainer.appendChild(button);
      });

      updateLabel();
    };

    const openMenu = async () => {
      if (select.disabled) {
        return;
      }

      /*
       * Attendance can be recorded in another tab while Job Orders stays
       * open. Refresh immediately before showing the mechanic list so the
       * user never has to reload the whole JO page.
       */
      if (
        select === mechanicSelect &&
        typeof window.GCTRefreshJobOrderMechanics === 'function'
      ) {
        await window.GCTRefreshJobOrderMechanics();
        renderOptions();
      }

      menu.hidden = false;
      combobox.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');

      if (config.className === 'jo-bus-combobox') {
        hostSection?.classList.add('has-open-bus-menu');
      }

      searchInput.value = '';
      filterOptions();
      window.setTimeout(() => searchInput.focus(), 0);
    };

    trigger.addEventListener('click', () => {
      if (menu.hidden) {
        void openMenu();
      } else {
        closeMenu();
      }
    });

    searchInput.addEventListener('input', filterOptions);

    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMenu();
      }

      if (event.key === 'Enter') {
        const firstVisible = Array.from(
          optionsContainer.querySelectorAll('.jo-mechanic-combobox-option')
        ).find((button) => button.style.display !== 'none');

        if (firstVisible) {
          event.preventDefault();
          selectOption(firstVisible.dataset.value);
        }
      }
    });

    document.addEventListener('click', (event) => {
      if (!combobox.contains(event.target)) {
        closeMenu();
      }
    });

    const observer = new MutationObserver(() => {
      renderOptions();
      trigger.disabled = select.disabled;
      combobox.classList.toggle('is-disabled', select.disabled);
    });

    observer.observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled'],
    });

    renderOptions();
  }

  setupSearchableSelect(busSelect, {
    className: 'jo-bus-combobox',
    icon: 'fa-bus',
    placeholder: 'Select Bus',
    searchPlaceholder: 'Search bus number or plate...',
    emptyMessage: 'No bus matches your search.',
    optionHint: 'Available bus',
    formatOptionText: (text) => {
      const parts = text
        .split(' - ')
        .map((part) => part.trim())
        .filter(Boolean);

      if (
        parts.length === 2 &&
        parts[0].toLowerCase() === parts[1].toLowerCase()
      ) {
        return parts[0];
      }

      return text;
    },
  });

  setupSearchableSelect(mechanicSelect, {
    icon: 'fa-user-gear',
    placeholder: 'Select Available Mechanic',
    searchPlaceholder: 'Search mechanic name...',
    emptyMessage: 'No mechanic matches your search.',
    optionHint: 'Available mechanic',
  });
});
