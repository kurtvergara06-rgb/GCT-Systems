import gsap from 'gsap';

window.GCTPartialNavigation.registerInitializer('maintenance-job-orders', '.jo-page', () => {

  // Maintenance Head review: reveal authorized on-hand quantities and shortages.
  document.querySelectorAll('.jo-review-parts-list').forEach((list) => {
    const reviewDetails = list.closest('details.jo-review-actions');
    const loadStatuses = async () => {
      if (list.dataset.checking === '1') return;
      let parts;
      try { parts = JSON.parse(list.dataset.parts || '[]'); } catch { parts = []; }
      if (!Array.isArray(parts) || !parts.length) return;
      list.dataset.checking = '1';
      const badges = [...list.querySelectorAll('.jo-review-part-availability')];
      badges.forEach((badge) => {
        badge.textContent = 'Checking availability';
        badge.className = 'jo-review-part-availability is-checking';
      });
      try {
        const response = await fetch('/job-orders/head-review-inventory-availability', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ parts }),
        });
        if (!response.ok) throw new Error('Lookup failed');
        const result = await response.json();
        badges.forEach((badge, index) => {
          const row = badge.closest('.jo-review-part-item');
          const stock = row?.querySelector('.jo-review-part-stock');
          const shortage = row?.querySelector('.jo-review-part-shortage');
          const part = result.parts?.[index];
          if (!part) {
            if (stock) stock.textContent = '—';
            if (shortage) shortage.textContent = '—';
            badge.className = 'jo-review-part-availability is-unknown';
            badge.textContent = 'Unable to check';
            return;
          }
          const status = part.status || 'Unavailable';
          badge.className = 'jo-review-part-availability ' + (status === 'Available' ? 'is-available' : status === 'Partially Available' ? 'is-partial' : 'is-unavailable');
          if (stock) stock.textContent = String(part.available);
          if (shortage) shortage.textContent = String(part.shortage);
          badge.textContent = status;
        });
      } catch {
        badges.forEach((badge) => {
          const row = badge.closest('.jo-review-part-item');
          const stock = row?.querySelector('.jo-review-part-stock');
          const shortage = row?.querySelector('.jo-review-part-shortage');
          if (stock) stock.textContent = '—';
          if (shortage) shortage.textContent = '—';
          badge.className = 'jo-review-part-availability is-unknown';
          badge.textContent = 'Unable to check';
        });
      } finally {
        list.dataset.checking = '0';
      }
    };
    // The Head Review is a custom dialog: the native <details> never stays open.
    // Listen for the actual dialog activation rather than waiting on its toggle.
    list.addEventListener('jo-head-review-opened', () => void loadStatuses());
    if (reviewDetails?.open) void loadStatuses();
  });

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
          const inventoryId = row.querySelector('input[name$="[inventory_item_id]"]');
          if (inventoryId) inventoryId.name = `parts[${index}][inventory_item_id]`;

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
         * is used. This keeps newly recorded attended/free mechanics
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
     NEW JO REQUIRED-FIELD FEEDBACK
     Custom dropdowns hide native required selects, so validate
     before the browser silently blocks submission.
  ========================================================= */

  const newJobOrderForm =
    document.getElementById(
      'newJobOrderForm'
    );


  const saveJobOrderBtn =
    document.getElementById(
      'saveJobOrderBtn'
    );


  function clearNewJoValidationState() {

    jobModal
      ?.querySelectorAll(
        '.jo-validation-invalid'
      )
      .forEach((element) => {

        element.classList.remove(
          'jo-validation-invalid'
        );

      });
  }


  function showNewJoValidationError(
    field,
    message,
    focusSelector = null
  ) {

    clearNewJoValidationState();


    const group =
      field?.closest(
        '.ui-form-group'
      );


    group?.classList.add(
      'jo-validation-invalid'
    );


    const focusTarget =
      focusSelector
        ? jobModal?.querySelector(
            focusSelector
          )
        : field;


    const scrollTarget =
      group ||
      focusTarget ||
      field;


    scrollTarget
      ?.scrollIntoView?.({
        behavior: 'smooth',
        block: 'center',
      });


    window.setTimeout(
      () => {

        focusTarget?.focus?.();

      },
      180
    );


    if (
      typeof window
        .showSystemToast ===
      'function'
    ) {

      window.showSystemToast(
        message,
        'error',
        'Validation Error',
        {
          timeout: 5000,
        }
      );

      return;
    }


    window.alert?.(message);
  }


  function validateNewJobOrderForm() {

    if (!newJobOrderForm) {
      return true;
    }


    const busField =
      newJobOrderForm.querySelector(
        '#jobBusNo'
      );


    const problemField =
      newJobOrderForm.querySelector(
        '#jobProblemIssue'
      );


    const maintenanceJobField =
      newJobOrderForm.querySelector(
        '#newJoMaintenanceJob'
      );


    const estimatedTimeField =
      newJobOrderForm.querySelector(
        'input[name="estimated_duration_value"]'
      );


    const mechanicsField = newJobOrderForm.querySelector('#jobAssignedMechanics');

    const validations = [
      {
        field: busField,
        invalid:
          !busField?.value?.trim(),
        message:
          'Please select a Bus.',
        focusSelector:
          '.jo-bus-combobox .jo-mechanic-combobox-trigger',
      },
      {
        field: problemField,
        invalid:
          !problemField?.value?.trim(),
        message:
          'Please enter the Problem / Issue.',
      },
      {
        field: maintenanceJobField,
        invalid:
          !maintenanceJobField?.value?.trim(),
        message:
          'Please select a Maintenance Job.',
        focusSelector:
          '.jo-maintenance-job-trigger',
      },
      {
        field: mechanicsField,
        invalid: !mechanicsField || mechanicsField.selectedOptions.length === 0,
        message: 'Please assign at least one mechanic.',
      },
      {
        field: estimatedTimeField,
        invalid:
          !!estimatedTimeField?.value &&
          Number(estimatedTimeField.value) <= 0,
        message:
          'The historical estimated duration is invalid. Please refresh.',
      },
    ];


    const firstInvalid =
      validations.find(
        (entry) => entry.invalid
      );


    if (!firstInvalid) {

      clearNewJoValidationState();
      return true;
    }


    showNewJoValidationError(
      firstInvalid.field,
      firstInvalid.message,
      firstInvalid.focusSelector
    );


    return false;
  }


  if (
    newJobOrderForm &&
    saveJobOrderBtn
  ) {

    saveJobOrderBtn.addEventListener(
      'click',
      (event) => {

        if (
          validateNewJobOrderForm()
        ) {
          return;
        }


        event.preventDefault();
        event.stopImmediatePropagation();

      },
      true
    );


    newJobOrderForm.addEventListener(
      'input',
      (event) => {

        event.target
          ?.closest(
            '.ui-form-group'
          )
          ?.classList
          .remove(
            'jo-validation-invalid'
          );

      }
    );


    newJobOrderForm.addEventListener(
      'change',
      (event) => {

        event.target
          ?.closest(
            '.ui-form-group'
          )
          ?.classList
          .remove(
            'jo-validation-invalid'
          );

      }
    );


    newJobOrderForm.addEventListener(
      'invalid',
      (event) => {

        event.preventDefault();

        const field =
          event.target;


        const fieldMap = {
          jobBusNo: [
            'Please select a Bus.',
            '.jo-bus-combobox .jo-mechanic-combobox-trigger',
          ],
          jobProblemIssue: [
            'Please enter the Problem / Issue.',
            null,
          ],
          newJoMaintenanceJob: [
            'Please select a Maintenance Job.',
            '.jo-maintenance-job-trigger',
          ],
        };


        const mapped =
          fieldMap[field?.id];


        if (mapped) {

          showNewJoValidationError(
            field,
            mapped[0],
            mapped[1]
          );
        }

      },
      true
    );
  }


  /* =========================================================
     NEW JO
     MECHANIC → REQUESTED PARTS
  ========================================================= */

  const jobAssignedMechanic =
    document.getElementById(
      'jobAssignedMechanics'
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

    // Parts can be declared before mechanic assignment. Review approval remains
    // the workflow gate, so this section must stay usable for unassigned JOs.
    const hasMechanic = true;


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
    editJobForm?.querySelector(
      '.ui-form-actions'
    );


  const viewOnlyJobActions = null;


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
    editBusNo.dispatchEvent(new Event('change', { bubbles: true }));


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


    editJobForm?.querySelectorAll(
      '.ui-form-modal-scrollable input:not([type="hidden"]), .ui-form-modal-scrollable select, .ui-form-modal-scrollable textarea, .ui-form-modal-scrollable button'
    ).forEach((field) => {

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


    if (editJobMainActions) editJobMainActions.style.display = 'flex';

    const updateButton = document.getElementById('updateJobOrderBtn');
    if (updateButton) updateButton.style.display = isReadonly ? 'none' : 'inline-flex';
    if (cancelEditJobModal) cancelEditJobModal.textContent = isReadonly ? 'Close' : 'Cancel';


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
    currentMechanicIds = ''
  ) {

    if (!editAssignedMechanic) {
      return;
    }


    const selectedIds = new Set(String(currentMechanicIds || '').split(',').map((value) => value.trim()).filter(Boolean));
    Array.from(editAssignedMechanic.options).forEach((option) => {
      option.selected = selectedIds.has(String(option.value));
    });
    editAssignedMechanic.dispatchEvent(new Event('change', { bubbles: true }));
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


    const hasMechanic = true;


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
          .mechanicIds ||
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

      editJobModal?.querySelectorAll('[data-jo-collapsible]').forEach((section, index) => {
        const shouldCollapse = index !== 0;
        section.classList.toggle('is-collapsed', shouldCollapse);
        const toggle = section.querySelector('.jo-collapse-toggle');
        toggle?.setAttribute('aria-expanded', String(!shouldCollapse));
        toggle?.querySelector('i')?.classList.toggle('fa-chevron-up', !shouldCollapse);
        toggle?.querySelector('i')?.classList.toggle('fa-chevron-down', shouldCollapse);
      });
      // Refresh the shared searchable controls after loading the JO values.
      editBusNo?.dispatchEvent(new Event('change', { bubbles: true }));
      editAssignedMechanic?.dispatchEvent(new Event('change', { bubbles: true }));


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
      '.success-modal-overlay'
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

  let mechanicLookupSequence = 0;
  async function refreshAvailableMechanicsDropdown() {
    const lookupSequence = ++mechanicLookupSequence;

    const newJobMechanicSelect =
      document.querySelector(
        '#jobModal select[name="mechanic_ids[]"]'
      );


    // Snapshot the selection BEFORE clearing stale choices. Otherwise opening
    // the combobox again removes the first mechanic from a multi-assignment.
    const selectedMechanics = new Set(
      Array.from(newJobMechanicSelect?.selectedOptions || []).map(option => String(option.value))
    );
    if (newJobMechanicSelect) {
      newJobMechanicSelect.dataset.lookupStatus = 'loading';
      newJobMechanicSelect.replaceChildren();
    }

    try {

      const mechanicParams = new URLSearchParams({
        maintenance_type: document.getElementById('jobMaintenanceType')?.value || '',
        maintenance_job: document.getElementById('newJoMaintenanceJob')?.value || '',
        problem_issue: document.getElementById('jobProblemIssue')?.value || '',
          });
      document.getElementById('newJoMaintenanceJob')?.getSelectedJobs?.().forEach((job) => {
        mechanicParams.append('maintenance_jobs[]', job);
      });
      document.querySelectorAll('#newJoCustomSpecializations input:checked').forEach((input) => {
        mechanicParams.append('specializations[]', input.value);
      });
      const response =
        await fetch(
          `/job-orders/available-mechanics?${mechanicParams.toString()}`,
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
      if (lookupSequence !== mechanicLookupSequence) return;
      if (newJobMechanicSelect) newJobMechanicSelect.dataset.lookupStatus = 'ready';

      /* =====================================================
         NEW JO SELECT
      ====================================================== */

      if (newJobMechanicSelect) {

        newJobMechanicSelect
          .innerHTML =
          '';


        mechanics.forEach(
          (mechanic) => {

            const option =
              document.createElement(
                'option'
              );


            option.value =
              String(mechanic.id);


            option.textContent = `${mechanic.mechanic_name}${mechanic.qualified_jobs?.length ? ` · Qualified: ${mechanic.qualified_jobs.join(', ')}` : (mechanic.specializations?.length ? ` · ${mechanic.specializations.join(', ')}` : '')}`;
            option.dataset.specializations = mechanic.specializations?.join(', ') || '';
            option.dataset.availability = mechanic.availability || 'Available';
            option.dataset.skillMatches = mechanic.skill_matches?.join(', ') || '';
            option.dataset.qualifiedJobs = mechanic.qualified_jobs?.join(', ') || '';


            if (
              selectedMechanics.has(String(mechanic.id))
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


        // Notify the UI once, after all eligible options and retained selections
        // have been restored. This also removes previously selected mechanics who
        // no longer pass attendance or specialization validation.
        newJobMechanicSelect.dispatchEvent(new Event('change', { bubbles: true }));
        updateNewJoPartsState();
      }


      /* =====================================================
         EDIT SELECT
      ====================================================== */

      if (editAssignedMechanic) {

        const currentAssigned = Array.from(editAssignedMechanic.selectedOptions).map((option) => option.value).join(',');


        mechanics.forEach(
          (mechanic) => {

            const exists =
              Array.from(
                editAssignedMechanic.options
              )
              .some(
                (option) =>
                  option.value ===
                  String(mechanic.id)
              );


            if (!exists) {

              const option =
                document.createElement(
                  'option'
                );


              option.value =
                String(mechanic.id);


              option.textContent = `${mechanic.mechanic_name}${mechanic.qualified_jobs?.length ? ` · Qualified: ${mechanic.qualified_jobs.join(', ')}` : (mechanic.specializations?.length ? ` · ${mechanic.specializations.join(', ')}` : '')}`;


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

      if (lookupSequence !== mechanicLookupSequence) return;
      if (newJobMechanicSelect) {
        newJobMechanicSelect.dataset.lookupStatus = 'error';
        newJobMechanicSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
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

window.GCTPartialNavigation.registerInitializer('maintenance-job-order-review-workflow', '.jo-page', () => {
  let mechanicRefreshTimer;
  ['jobMaintenanceType', 'newJoMaintenanceJob', 'jobProblemIssue'].forEach((id) => {
    const field = document.getElementById(id);
    const refresh = () => {
      clearTimeout(mechanicRefreshTimer);
      mechanicRefreshTimer = setTimeout(() => window.GCTRefreshJobOrderMechanics?.(), 300);
    };
    field?.addEventListener('input', refresh);
    if (id === 'newJoMaintenanceJob') field?.addEventListener('change', refresh);
  });

  document.querySelectorAll('#jobModal [data-jo-collapsible], #editJobModal [data-jo-collapsible]').forEach((section) => {
    const header = section.querySelector('.jo-create-section-header');
    if (!header || header.querySelector('.jo-collapse-toggle')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'jo-collapse-toggle';
    const startsCollapsed = [...section.parentElement.querySelectorAll('[data-jo-collapsible]')].indexOf(section) !== 0;
    section.classList.toggle('is-collapsed', startsCollapsed);
    button.setAttribute('aria-expanded', String(!startsCollapsed));
    button.innerHTML = `<i class="fa-solid ${startsCollapsed ? 'fa-chevron-down' : 'fa-chevron-up'}"></i><span class="sr-only">${startsCollapsed ? 'Expand' : 'Collapse'} section</span>`;
    button.addEventListener('click', () => {
      const collapsed = section.classList.toggle('is-collapsed');
      button.setAttribute('aria-expanded', String(!collapsed));
      button.querySelector('i')?.classList.toggle('fa-chevron-up', !collapsed);
      button.querySelector('i')?.classList.toggle('fa-chevron-down', collapsed);
      const accessibleLabel = button.querySelector('.sr-only');
      if (accessibleLabel) accessibleLabel.textContent = `${collapsed ? 'Expand' : 'Collapse'} section`;
    });
    header.appendChild(button);
  });

  // Reflect the current form values in each accordion header, even when collapsed.
  const joForm = document.getElementById('newJobOrderForm');
  const joSections = [...document.querySelectorAll('#jobModal [data-jo-collapsible]')];
  // Keep a single step open during automatic progression. Manual toggles remain available.
  const setJoSectionOpen = (index) => {
    joSections.forEach((section, sectionIndex) => {
      const shouldCollapse = sectionIndex !== index;
      if (section.classList.contains('is-collapsed') !== shouldCollapse) {
        section.querySelector('.jo-collapse-toggle')?.click();
      }
    });
  };
  let previousJoStates = null;
  const joValue = (selector) => joForm?.querySelector(selector)?.value?.trim() || '';
  const joDurationValid = () => {
    const field = joForm?.querySelector('[name="estimated_duration_value"]');
    return field && field.value !== '' && field.checkValidity() && Number(field.value) > 0
      && !!joValue('[name="estimated_duration_unit"]');
  };
  const joPartsStatus = () => {
    const rows = [...(joForm?.querySelectorAll('#partsNeededWrapper .part-needed-row') || [])];
    const hasAnyEntry = rows.some((row) =>
      [...row.querySelectorAll('input, select')].some((field) => String(field.value || '').trim() !== '')
    );
    if (!hasAnyEntry) return 'optional';
    return rows.every((row) => {
      const name = row.querySelector('select[name$="[name]"], input[name$="[name]"]')?.value?.trim();
      const quantity = row.querySelector('input[name$="[quantity]"]');
      const unit = row.querySelector('select[name$="[unit]"]')?.value;
      return name && quantity?.value && quantity.checkValidity() && Number(quantity.value) >= 1 && unit;
    }) ? 'completed' : 'incomplete';
  };
  const updateJoSectionIndicators = () => {
    if (!joForm) return;
    const selectedMechanics = [...(joForm.querySelector('#jobAssignedMechanics')?.selectedOptions || [])].length;
    const results = [
      !!joValue('[name="bus_no"]') && !!joValue('#newJoMaintenanceJob')
        && joValue('[name="problem_issue"]').length > 0
        && (joValue('#newJoMaintenanceJob') !== 'Other'
          || (joValue('#newJoCustomWorkName').length >= 3
            && !!joForm.querySelector('#newJoCustomSpecializations input:checked')))
        ? 'completed' : 'incomplete',
      selectedMechanics && (() => {
        const jobs = document.getElementById('newJoMaintenanceJob')?.getSelectedJobs?.() || [];
        const options = [...(joForm.querySelector('#jobAssignedMechanics')?.selectedOptions || [])];
        return jobs.length > 0 && jobs.every((job) => options.some((option) =>
          (option.dataset.qualifiedJobs || '').split(',').map((value) => value.trim()).includes(job)));
      })() ? 'completed' : 'incomplete',
      joDurationValid() ? 'completed' : 'incomplete',
      joPartsStatus(),
    ];
    joSections.forEach((section, index) => {
      const header = section.querySelector('.jo-create-section-header');
      if (!header) return;
      let badge = header.querySelector('.jo-section-progress');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'jo-section-progress';
        badge.setAttribute('role', 'status');
        badge.setAttribute('aria-live', 'polite');
        header.insertBefore(badge, header.querySelector('.jo-collapse-toggle'));
      }
      const state = results[index] || 'incomplete';
      badge.dataset.state = state;
      badge.textContent = state === 'completed' ? '✓ Completed' : state === 'optional' ? 'Optional / Skipped' : state === 'head-review' ? 'Head to Verify' : 'Incomplete';
    });
    // Show completion immediately; do not collapse fields while someone is typing.
    previousJoStates = results;
  };
  if (joForm) {
    joForm.addEventListener('input', updateJoSectionIndicators);
    joForm.addEventListener('change', updateJoSectionIndicators);
    // Completion only updates the badge. Never auto-collapse Job Details
    // when a maintenance job is selected or an input loses focus.
    // Users control section expansion explicitly using the header toggles.
    // Keep Mechanic Assignment expanded as the user adds multiple mechanics.
    // Completion badge updates on every selection, but moving to Duration is manual.
    // Covers dynamically added/removed part rows and programmatic dropdown updates.
    const observer = new MutationObserver(() => updateJoSectionIndicators());
    const partsArea = joForm.querySelector('#partsNeededWrapper');
    if (partsArea) observer.observe(partsArea, { childList: true, subtree: true });
    document.getElementById('openJobModal')?.addEventListener('click', () => {
      previousJoStates = null;
      setJoSectionOpen(0);
      requestAnimationFrame(updateJoSectionIndicators);
    });
    updateJoSectionIndicators();
    window.GCTUpdateJoSectionIndicators = updateJoSectionIndicators;
  }

  document.getElementById('addPartBtn')?.addEventListener('click', () => {
    const section = document.getElementById('newRequestedPartsSection');
    const toggle = section?.querySelector('.jo-collapse-toggle');
    if (section?.classList.contains('is-collapsed')) toggle?.click();
  });

  const type = document.getElementById('jobMaintenanceType');
  const durationValue = document.querySelector('#newJobOrderForm [name="estimated_duration_value"]');
  const durationUnit = document.querySelector('#newJobOrderForm [name="estimated_duration_unit"]');
  const durationHelp = document.getElementById('jobDurationSuggestion');
  // A suggestion is advisory only. Never overwrite the user's manual estimate.
  let durationRequest = 0;
  const loadDuration = async () => {
    const requestId = ++durationRequest;
    const job = document.getElementById('newJoMaintenanceJob')?.value?.trim() || '';
    const custom = document.querySelector('#newJobOrderForm [name="custom_maintenance_job"]')?.value?.trim() || '';
    const actualJob = job === 'Other' ? custom : job;
    if (durationValue) {
      durationValue.readOnly = false;
      durationValue.required = true;
      durationValue.removeAttribute('aria-readonly');
    }
    if (durationUnit) {
      durationUnit.style.pointerEvents = '';
      durationUnit.removeAttribute('tabindex');
      durationUnit.removeAttribute('aria-readonly');
    }
    const suggestionText = durationHelp?.querySelector('span');
    if (durationHelp) durationHelp.dataset.status = 'unavailable';
    if (!type?.value || !actualJob) {
      if (suggestionText) suggestionText.textContent = 'Select specific maintenance work to check historical duration.';
      return;
    }
    if (suggestionText) suggestionText.textContent = 'Checking matching completed Job Orders...';
    const params = new URLSearchParams({
      maintenance_type: type.value,
      maintenance_job: job,
      custom_maintenance_job: custom,
    });
    try {
      const response = await fetch('/job-orders/duration-suggestion?' + params.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!response.ok) throw new Error('Suggestion request failed');
      const data = await response.json();
      if (requestId !== durationRequest) return;
      if (data.available && data.suggestion) {
        if (durationHelp) durationHelp.dataset.status = 'available';
        if (suggestionText) suggestionText.textContent = 'Suggested: ' + data.suggestion.value + ' ' + data.suggestion.unit
          + ' · based on ' + data.suggestion.sample_size + ' matching completed JOs. You may enter your own estimate.';
      } else if (suggestionText) {
        suggestionText.textContent = 'Not enough matching completed Job Orders. Enter your own estimate.';
      }
    } catch (_) {
      if (requestId === durationRequest && suggestionText) suggestionText.textContent = 'History unavailable. Enter your own estimate.';
    }
    window.GCTUpdateJoSectionIndicators?.();
  };
  type?.addEventListener('change', loadDuration);
  // Delegation also handles job selectors inserted after the page initializes.
  document.getElementById('newJobOrderForm')?.addEventListener('change', (event) => {
    if (event.target?.matches?.('[name="maintenance_job"], [name="custom_maintenance_job"], #newJoMaintenanceJob')) void loadDuration();
  });
  let customHistoryDelay;
  document.getElementById('newJobOrderForm')?.addEventListener('input', (event) => {
    if (!event.target?.matches?.('[name="custom_maintenance_job"]')) return;
    clearTimeout(customHistoryDelay);
    customHistoryDelay = setTimeout(() => void loadDuration(), 350);
  });
  void loadDuration();

  let editDurationRequest = 0;
  const loadEditDuration = async () => {
    const requestId = ++editDurationRequest;
    const form = document.getElementById('editJobForm');
    const typeField = form?.querySelector('[name="maintenance_type"]');
    const job = document.getElementById('editJoMaintenanceJob')?.value?.trim() || '';
    const custom = form?.querySelector('[name="custom_maintenance_job"]')?.value?.trim() || '';
    const actualJob = job === 'Other' ? custom : job;
    const help = document.getElementById('editJobDurationSuggestion');
    const helpText = help?.querySelector('span');

    if (help) help.dataset.status = 'unavailable';
    if (!typeField?.value || !actualJob) {
      if (helpText) helpText.textContent = 'Select specific maintenance work to check historical duration.';
      return;
    }

    if (helpText) helpText.textContent = 'Checking matching completed Job Orders...';
    const params = new URLSearchParams({
      maintenance_type: typeField.value,
      maintenance_job: job,
      custom_maintenance_job: custom,
    });

    try {
      const response = await fetch('/job-orders/duration-suggestion?' + params.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!response.ok) throw new Error('Suggestion request failed');
      const data = await response.json();
      if (requestId !== editDurationRequest) return;
      if (data.available && data.suggestion) {
        if (help) help.dataset.status = 'available';
        if (helpText) helpText.textContent = 'Suggested: ' + data.suggestion.value + ' ' + data.suggestion.unit
          + ' · based on ' + data.suggestion.sample_size + ' matching completed JOs. You may enter your own estimate.';
      } else if (helpText) {
        helpText.textContent = 'Not enough matching completed Job Orders. Enter your own estimate.';
      }
    } catch (_) {
      if (requestId === editDurationRequest && helpText) helpText.textContent = 'History unavailable. Enter your own estimate.';
    }
  };
  window.GCTLoadEditDurationSuggestion = loadEditDuration;
  document.getElementById('editJobForm')?.addEventListener('change', (event) => {
    if (event.target?.matches?.('[name="maintenance_job"], [name="custom_maintenance_job"], #editJoMaintenanceJob')) {
      void loadEditDuration();
    }
  });
  let editCustomHistoryDelay;
  document.getElementById('editJobForm')?.addEventListener('input', (event) => {
    if (!event.target?.matches?.('[name="custom_maintenance_job"]')) return;
    clearTimeout(editCustomHistoryDelay);
    editCustomHistoryDelay = setTimeout(() => void loadEditDuration(), 350);
  });

  const enhanceJobOrderParts = (partsWrapper) => {
  // Stocked parts are selected by inventory ID; units come from Warehouse data.
  if (partsWrapper) {
    fetch('/job-orders/inventory-options', { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('Inventory unavailable'); return response.json(); })
      .then((items) => {
        // A Warehouse item can be requested only once per JO. Removing or
        // changing its row makes it selectable again in the other rows.
        const selectedPartIds = () => new Set(
          Array.from(partsWrapper.querySelectorAll('.part-needed-row input[name$="[inventory_item_id]"]'))
            .map((input) => String(input.value || ''))
            .filter(Boolean)
        );
        const notifyPartSelectionChanged = () => {
          partsWrapper.dispatchEvent(new Event('jo-part-selection-changed'));
        };
        const enhanceRow = (row) => {
          if (row.dataset.inventoryPicker === '1') return;
          const nameInput = row.querySelector('select[name$="[name]"], input[name$="[name]"]');
          const unitSelect = row.querySelector('select[name$="[unit]"]');
          if (!nameInput || !unitSelect) return;
          const name = nameInput.name;
          const existingName = nameInput.value;
          const picker = document.createElement('select');
          picker.name = name;
          picker.className = 'jo-warehouse-part-picker';
          const initial = document.createElement('option');
          initial.value = '';
          initial.textContent = 'Select warehouse item';
          picker.append(initial);
          items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.name;
            option.textContent = item.name;
            option.dataset.itemId = item.id;
            option.dataset.unit = item.unit;
            option.disabled = !item.unit;
            picker.append(option);
          });
          const itemId = document.createElement('input');
          itemId.type = 'hidden';
          itemId.name = name.replace('[name]', '[inventory_item_id]');
          nameInput.replaceWith(picker);
          picker.insertAdjacentElement('afterend', itemId);
          unitSelect.classList.add('jo-auto-unit-select');
          unitSelect.tabIndex = -1;
          unitSelect.setAttribute('aria-label', 'Unit from Warehouse inventory');
          const updateItem = () => {
            const option = picker.selectedOptions[0];
            const unit = option?.dataset.unit || '';
            itemId.value = option?.dataset.itemId || '';
            unitSelect.replaceChildren();
            const unitOption = new Option(unit || 'Unit not configured', unit);
            unitSelect.append(unitOption);
            unitSelect.value = unit;
            picker.dispatchEvent(new Event('jo-item-change', { bubbles: true }));
            notifyPartSelectionChanged();
          };
          picker.addEventListener('change', updateItem);
          // Searchable single-select, with the native select retained for form submission.
          picker.classList.add('jo-native-inventory-picker');
          const searchWrap = document.createElement('div');
          searchWrap.className = 'jo-inventory-search-wrap';
          const trigger = document.createElement('button');
          trigger.type = 'button';
          trigger.className = 'jo-inventory-search-trigger';
          trigger.textContent = 'Search warehouse items...';
          trigger.setAttribute('aria-haspopup', 'listbox');
          trigger.setAttribute('aria-expanded', 'false');
          searchWrap.append(trigger);
          picker.insertAdjacentElement('afterend', searchWrap);
          const menu = document.createElement('div');
          menu.className = 'jo-inventory-search-menu';
          menu.hidden = true;
          const search = document.createElement('input');
          search.type = 'search';
          search.placeholder = 'Search part name...';
          search.setAttribute('aria-label', 'Search warehouse items');
          const results = document.createElement('div');
          results.className = 'jo-inventory-search-results';
          results.setAttribute('role', 'listbox');
          menu.append(search, results);
          searchWrap.append(menu);
          const positionMenu = () => {
            if (menu.hidden) return;
            const rect = trigger.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom;
            const above = rect.top;
            const upward = below < 230 && above > below;
            Object.assign(menu.style, {
              position: 'fixed', left: rect.left + 'px', width: rect.width + 'px',
              top: upward ? 'auto' : (rect.bottom + 5) + 'px',
              bottom: upward ? (window.innerHeight - rect.top + 5) + 'px' : 'auto',
              maxHeight: Math.min(280, Math.max(110, (upward ? above : below) - 20)) + 'px',
              zIndex: '110500'
            });
          };
          const close = () => {
            if (menu.hidden) return;
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            window.removeEventListener('scroll', positionMenu, true);
            window.removeEventListener('resize', positionMenu);
            searchWrap.append(menu);
            menu.removeAttribute('style');
          };
          const render = () => {
            results.replaceChildren();
            const query = search.value.trim().toLowerCase();
            const taken = selectedPartIds();
            const currentId = String(itemId.value || '');
            const matches = items.filter((item) =>
              item.name.toLowerCase().includes(query)
              && (!taken.has(String(item.id)) || String(item.id) === currentId)
            ).slice(0, 80);
            if (!matches.length) {
              const empty = document.createElement('p');
              empty.textContent = 'No matching warehouse items';
              results.append(empty);
            }
            matches.forEach((item) => {
              const button = document.createElement('button');
              button.type = 'button';
              button.className = 'jo-inventory-search-option';
              button.setAttribute('role', 'option');
              button.disabled = !item.unit;
              const label = document.createElement('span');
              label.textContent = item.name;
              const hint = document.createElement('small');
              hint.textContent = item.unit || 'Unit not configured';
              button.append(label, hint);
              button.addEventListener('click', () => {
                const choice = Array.from(picker.options).find((option) => option.dataset.itemId === String(item.id));
                if (choice) {
                  picker.value = choice.value;
                  picker.dispatchEvent(new Event('change', { bubbles: true }));
                  trigger.textContent = item.name;
                }
                close();
              });
              results.append(button);
            });
          };
          trigger.addEventListener('click', () => {
            if (!menu.hidden) { close(); return; }
            document.body.append(menu);
            menu.hidden = false;
            search.value = '';
            render();
            positionMenu();
            window.addEventListener('scroll', positionMenu, true);
            window.addEventListener('resize', positionMenu);
            search.focus();
          });
          search.addEventListener('input', render);
          partsWrapper.addEventListener('jo-part-selection-changed', () => {
            if (!menu.hidden) render();
          });
          search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { close(); trigger.focus(); }
            if (event.key === 'Enter') {
              const first = results.querySelector('button:not(:disabled)');
              if (first) { event.preventDefault(); first.click(); }
            }
          });
          document.addEventListener('pointerdown', (event) => {
            if (!searchWrap.contains(event.target) && !menu.contains(event.target)) close();
          });
          picker.value = existingName;
          row.dataset.inventoryPicker = '1';
          updateItem();
        };
        partsWrapper.querySelectorAll('.part-needed-row').forEach(enhanceRow);
        const rowObserver = new MutationObserver(() => {
          partsWrapper.querySelectorAll('.part-needed-row').forEach(enhanceRow);
          notifyPartSelectionChanged();
        });
        rowObserver.observe(partsWrapper, { childList: true });
      })
      .catch(() => {
        // Keep the legacy parts form available if the Warehouse lookup is offline.
      });
  }
  // One compact stock indicator alongside each Warehouse part selector.
  // Row matching uses original row indices, not part name (duplicates are valid).
  let availabilityTimer;
  let availabilitySequence = 0;
  const badgeFor = (row) => {
    let badge = row.querySelector('.jo-row-stock-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'jo-row-stock-badge';
      badge.setAttribute('role', 'status');
    }
    // Place stock in its own column, directly after Unit and before Actions.
    const removeButton = row.querySelector('.remove-part-btn');
    if (removeButton) {
      row.insertBefore(badge, removeButton);
    } else {
      row.append(badge);
    }
    return badge;
  };
  const paintStock = (row, part, hasQty = true) => {
    const badge = badgeFor(row);
    if (!badge) return;
    if (!part) {
      badge.textContent = '';
      badge.hidden = true;
      badge.removeAttribute('title');
      return;
    }
    // Maintenance Staff only needs a yes/no availability indication.
    // Never expose Warehouse on-hand quantities or shortages in labels/tooltips.
    const isAvailable = hasQty ? part.fulfillable === true : part.in_stock === true;
    badge.hidden = false;
    badge.className = 'jo-row-stock-badge ' + (isAvailable ? 'is-available' : 'is-out');
    badge.textContent = isAvailable ? 'Available' : 'Unavailable';
    badge.title = isAvailable
      ? (hasQty ? 'Warehouse can fulfill the requested quantity.' : 'Item is in stock; enter quantity to confirm fulfillment.')
      : (hasQty ? 'Warehouse cannot fulfill the entire requested quantity.' : 'Item is currently unavailable.');
  };
  const checkAvailability = () => {
    clearTimeout(availabilityTimer);
    const sequence = ++availabilitySequence;
    availabilityTimer = setTimeout(async () => {
      if (!partsWrapper) return;
      const rows = [...partsWrapper.querySelectorAll('.part-needed-row')];
      const selected = [];
      rows.forEach((row) => {
        const name = row.querySelector('select[name$="[name]"], input[name$="[name]"]')?.value?.trim();
        const rawQty = row.querySelector('input[name$="[quantity]"]')?.value;
        const quantity = Number(rawQty);
        paintStock(row, null);
        if (name) {
          const hasQty = rawQty !== '' && Number.isFinite(quantity) && quantity > 0;
          selected.push({ row, name, hasQty, quantity: hasQty ? quantity : 1, unit: row.querySelector('select[name$="[unit]"]')?.value || '' });
        }
      });
      if (!selected.length) return;
      try {
        const response = await fetch('/job-orders/inventory-availability', {
          method: 'POST',
          headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ parts: selected.map(({ name, quantity, unit }) => ({ name, quantity, unit })) }),
        });
        if (!response.ok) throw new Error('Stock lookup failed');
        const data = await response.json();
        if (sequence !== availabilitySequence) return;
        selected.forEach(({ row, hasQty }, index) => {
          if (row.isConnected) paintStock(row, data.parts?.[index] || null, hasQty);
        });
      } catch {
        if (sequence !== availabilitySequence) return;
        selected.forEach(({ row }) => {
          const badge = badgeFor(row);
          if (!badge) return;
          badge.hidden = false;
          badge.className = 'jo-row-stock-badge is-unknown';
          badge.textContent = 'Stock unavailable';
          badge.title = 'Warehouse stock could not be checked; submission checks again.';
        });
      }
    }, 350);
  };
  partsWrapper?.addEventListener('input', checkAvailability);
  partsWrapper?.addEventListener('change', checkAvailability);
  partsWrapper?.addEventListener('jo-item-change', checkAvailability);
  if (partsWrapper) {
    new MutationObserver(() => {
      // Refresh badges for newly added and removed rows without listening to badge changes.
      const rows = partsWrapper.querySelectorAll('.part-needed-row');
      if (rows.length !== Number(partsWrapper.dataset.stockRows || -1)) {
        partsWrapper.dataset.stockRows = String(rows.length);
        checkAvailability();
      }
    }).observe(partsWrapper, { childList: true, subtree: false });
  }
  };

  ['partsNeededWrapper', 'editPartsNeededWrapper'].forEach((id) => {
    enhanceJobOrderParts(document.getElementById(id));
  });

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

  // Edit uses the same shared searchable multi-select component as Create.
  // Keep only the existing immutable-bus guard from this legacy initializer.
  return;

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
  const mechanicSelect = document.getElementById('jobAssignedMechanics');
  const linkedDriver = document.getElementById('jobLinkedDriver');
  const linkedDriverPreview = document.getElementById('jobLinkedDriverPreview');
  const busDriverMapSource = document.getElementById('jobBusDriverMap');
  const editModal = document.getElementById('editJobModal');
  const editBusSelect = document.getElementById('edit_bus_no');
  const editMechanicSelect = document.getElementById('edit_assigned_mechanic');
  const editLinkedDriver = document.getElementById('editJobLinkedDriver');
  const editLinkedDriverPreview = document.getElementById('editJobLinkedDriverPreview');
  const editBusDriverMapSource = document.getElementById('editJobBusDriverMap');

  if (!modal) {
    return;
  }

  let busDriverMap = {};

  try {
    busDriverMap = JSON.parse(busDriverMapSource?.textContent || '{}');
  } catch {
    busDriverMap = {};
  }

  const updateLinkedDriver = () => {
    if (!linkedDriver) {
      return;
    }

    const driverName = busDriverMap[busSelect?.value] || '';

    linkedDriver.textContent = driverName;

    if (linkedDriverPreview) {
      linkedDriverPreview.hidden = driverName === '';
    }
  };

  busSelect?.addEventListener('change', updateLinkedDriver);
  updateLinkedDriver();

  let editBusDriverMap = {};
  try {
    editBusDriverMap = JSON.parse(editBusDriverMapSource?.textContent || '{}');
  } catch {
    editBusDriverMap = {};
  }
  const updateEditLinkedDriver = () => {
    const driverName = editBusDriverMap[editBusSelect?.value] || '';
    if (editLinkedDriver) editLinkedDriver.textContent = driverName;
    if (editLinkedDriverPreview) editLinkedDriverPreview.hidden = driverName === '';
  };
  editBusSelect?.addEventListener('change', updateEditLinkedDriver);

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
    const isBusCombobox = config.className === 'jo-bus-combobox';
    const modalScroller = select.closest('.ui-form-overlay')?.querySelector('.ui-form-modal-scrollable');

    if (isBusCombobox) {
      menu.classList.add('jo-bus-menu-portal');
    }

    const positionBusMenu = () => {
      if (!isBusCombobox || menu.hidden || !document.body.contains(menu)) {
        return;
      }

      const triggerRect = trigger.getBoundingClientRect();
      const gap = 7;
      const availableBelow = Math.max(
        180,
        window.innerHeight - triggerRect.bottom - gap - 12
      );

      Object.assign(menu.style, {
        position: 'fixed',
        top: `${Math.round(triggerRect.bottom + gap)}px`,
        left: `${Math.round(triggerRect.left)}px`,
        right: 'auto',
        width: `${Math.round(triggerRect.width)}px`,
        maxHeight: `${Math.min(270, availableBelow)}px`,
        // The shared modal backdrop is 99999. Because this menu is portalled
        // to <body>, it must sit above that backdrop instead of behind it.
        zIndex: '110500',
      });
    };

    const mountBusMenu = () => {
      if (!isBusCombobox) {
        return;
      }

      document.body.appendChild(menu);
      positionBusMenu();

      window.addEventListener('resize', positionBusMenu);
      modalScroller?.addEventListener('scroll', positionBusMenu, { passive: true });
    };

    const restoreBusMenu = () => {
      if (!isBusCombobox) {
        return;
      }

      window.removeEventListener('resize', positionBusMenu);
      modalScroller?.removeEventListener('scroll', positionBusMenu);

      if (menu.parentElement !== combobox) {
        combobox.appendChild(menu);
      }

      menu.removeAttribute('style');
    };

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
      restoreBusMenu();
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
      if (select.disabled || config.readonly) {
        return;
      }

      /*
       * Attendance can be recorded in another tab while Job Orders stays
       * open. Refresh immediately before showing the mechanic list so the
       * user never has to reload the whole JO page.
       */
      if (
        select.id === 'jobAssignedMechanics' &&
        select === mechanicSelect &&
        typeof window.GCTRefreshJobOrderMechanics === 'function'
      ) {
        await window.GCTRefreshJobOrderMechanics();
        renderOptions();
      }

      menu.hidden = false;
      combobox.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');

      if (isBusCombobox) {
        mountBusMenu();
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
      if (!combobox.contains(event.target) && !menu.contains(event.target)) {
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
    select.addEventListener('change', renderOptions);
  }

  function setupMechanicMultiSelect(select) {
    if (!select || select.dataset.multiSelectReady === 'true') {
      return;
    }

    const inputWrap = select.closest('.ui-input-wrap');
    if (!inputWrap) {
      return;
    }

    select.dataset.multiSelectReady = 'true';
    inputWrap.classList.add('jo-native-mechanic-select');

    const combobox = document.createElement('div');
    combobox.className = 'jo-mechanic-combobox jo-mechanic-multi-select';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jo-mechanic-combobox-trigger jo-mechanic-multi-trigger';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.innerHTML = `
      <span class="jo-mechanic-combobox-icon"><i class="fa-solid fa-users-gear"></i></span>
      <span class="jo-mechanic-combobox-label placeholder">Select one or more mechanics</span>
      <i class="fa-solid fa-chevron-down jo-mechanic-combobox-chevron"></i>
    `;

    const menu = document.createElement('div');
    menu.className = 'jo-mechanic-combobox-menu jo-mechanic-multi-menu';
    menu.hidden = true;
    menu.innerHTML = `
      <div class="jo-mechanic-combobox-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" placeholder="Search name or specialization..." autocomplete="off">
      </div>
      <div class="jo-mechanic-combobox-options" role="listbox" aria-multiselectable="true"></div>
    `;

    combobox.append(trigger, menu);
    inputWrap.insertAdjacentElement('afterend', combobox);

    const label = trigger.querySelector('.jo-mechanic-combobox-label');
    const searchInput = menu.querySelector('input');
    const optionsContainer = menu.querySelector('.jo-mechanic-combobox-options');
    const modalScroller = select.closest('.ui-form-overlay')?.querySelector('.ui-form-modal-scrollable');

    const selectedOptions = () => Array.from(select.options).filter((option) => option.selected);
    const mechanicName = (option) => option.textContent.split('·')[0].trim();
    const mechanicSpecializations = (option) => option.dataset.specializations
      || option.textContent.split('·').slice(1).join('·').trim()
      || 'Not specified';

    // Qualified jobs come from the Laravel available-mechanics endpoint.
    // Never infer qualifications from job names in the browser.
    const coverage = document.createElement('div');
    coverage.className = 'jo-mechanic-coverage';
    coverage.setAttribute('role', 'status');
    coverage.setAttribute('aria-live', 'polite');
    combobox.insertAdjacentElement('afterend', coverage);
    const renderCoverage = () => {
      if (select.id !== 'jobAssignedMechanics') {
        coverage.hidden = true;
        return;
      }
      const selectedJobs = document.getElementById('newJoMaintenanceJob')?.getSelectedJobs?.() || [];
      const chosen = selectedOptions();
      const coveringMechanic = (job) => chosen.find((option) =>
        (option.dataset.qualifiedJobs || '').split(',').map((name) => name.trim()).includes(job));
      const uncovered = selectedJobs.filter((job) => !coveringMechanic(job));
      coverage.hidden = !selectedJobs.length || !chosen.length;
      coverage.replaceChildren();
      if (!coverage.hidden) {
        const heading = document.createElement('div');
        heading.className = 'jo-coverage-heading';
        const title = document.createElement('strong');
        title.textContent = 'Qualification Coverage';
        const count = document.createElement('span');
        count.textContent = uncovered.length ? `${uncovered.length} uncovered` : 'All covered';
        count.className = uncovered.length ? 'jo-coverage-count is-warning' : 'jo-coverage-count is-success';
        heading.append(title, count);
        coverage.appendChild(heading);
        selectedJobs.forEach((job) => {
          const owner = coveringMechanic(job);
          const row = document.createElement('div');
          row.className = `jo-coverage-row ${owner ? 'is-covered' : 'is-uncovered'}`;
          const symbol = document.createElement('span');
          symbol.textContent = owner ? '✓' : '!';
          symbol.setAttribute('aria-hidden', 'true');
          const description = document.createElement('span');
          const jobTitle = document.createElement('strong');
          jobTitle.textContent = job;
          const note = document.createElement('small');
          note.textContent = owner ? `Covered by ${mechanicName(owner)}` : 'Assign a qualified, available mechanic';
          description.append(jobTitle, note);
          row.append(symbol, description);
          coverage.appendChild(row);
        });
      }
      coverage.dataset.state = uncovered.length ? 'incomplete' : 'completed';
      coverage.style.cssText = 'margin-top:8px;padding:11px 12px;border:1px solid #dce5f1;border-radius:10px;background:#f7f9fc;font-size:12px;';
      coverage.querySelectorAll('.jo-coverage-heading').forEach((node) => {
        node.style.cssText = 'display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;';
      });
      coverage.querySelectorAll('.jo-coverage-count').forEach((node) => {
        node.style.color = uncovered.length ? '#a45a08' : '#237348';
      });
      coverage.querySelectorAll('.jo-coverage-row').forEach((node) => {
        node.style.cssText = 'display:flex;align-items:flex-start;gap:9px;padding:5px 0;';
        node.firstChild.style.cssText = `font-weight:700;color:${node.classList.contains('is-covered') ? '#237348' : '#a45a08'}`;
        node.children[1].style.cssText = 'display:flex;flex-direction:column;gap:2px;';
        node.querySelector('small').style.cssText = 'color:#64748b;font-size:11px;';
      });
      window.GCTUpdateJoSectionIndicators?.();
    };
    select.addEventListener('change', renderCoverage);
    document.getElementById('newJoMaintenanceJob')?.addEventListener('change', renderCoverage);

    const positionMenu = () => {
      if (menu.hidden || !document.body.contains(menu)) return;
      const rect = trigger.getBoundingClientRect();
      const gap = 7;
      const roomBelow = window.innerHeight - rect.bottom - gap - 12;
      const openAbove = roomBelow < 250 && rect.top > roomBelow;
      const menuHeight = Math.min(300, Math.max(190, openAbove ? rect.top - gap - 12 : roomBelow));
      Object.assign(menu.style, {
        position: 'fixed',
        top: openAbove ? 'auto' : `${Math.round(rect.bottom + gap)}px`,
        bottom: openAbove ? `${Math.round(window.innerHeight - rect.top + gap)}px` : 'auto',
        left: `${Math.round(rect.left)}px`,
        right: 'auto',
        width: `${Math.round(rect.width)}px`,
        maxHeight: `${menuHeight}px`,
        zIndex: '110500',
      });
    };

    const mountMenu = () => {
      document.body.appendChild(menu);
      positionMenu();
      window.addEventListener('resize', positionMenu);
      modalScroller?.addEventListener('scroll', positionMenu, { passive: true });
    };

    const restoreMenu = () => {
      window.removeEventListener('resize', positionMenu);
      modalScroller?.removeEventListener('scroll', positionMenu);
      if (menu.parentElement !== combobox) combobox.appendChild(menu);
      menu.removeAttribute('style');
    };

    const closeMenu = () => {
      menu.hidden = true;
      combobox.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      restoreMenu();
    };

    const renderLabel = () => {
      const selected = selectedOptions();
      label.replaceChildren();
      label.classList.toggle('placeholder', selected.length === 0);

      if (!selected.length) {
        renderCoverage();
        const availableCount = Array.from(select.options).filter((option) => option.value).length;
        label.textContent = select.dataset.lookupStatus === 'loading'
          ? 'Checking available mechanics...'
          : select.dataset.lookupStatus === 'error'
            ? 'Unable to load mechanics — retry'
            : availableCount
              ? `${availableCount} mechanics available`
              : '0 qualified, on-duty mechanics';
        return;
      }

      label.setAttribute('aria-label', `${selected.length} mechanics selected`);
      selected.forEach((option) => {
        const chip = document.createElement('span');
        chip.className = 'jo-mechanic-chip';
        chip.append(document.createTextNode(mechanicName(option)));
        chip.title = `${mechanicName(option)} · ${mechanicSpecializations(option)}`;
        const remove = document.createElement('span');
        remove.className = 'jo-mechanic-chip-remove';
        remove.textContent = '×';
        remove.title = 'Remove ' + mechanicName(option);
        remove.setAttribute('role', 'button');
        remove.tabIndex = 0;
        remove.setAttribute('aria-label', 'Remove ' + mechanicName(option));
        const removeMechanic = (event) => {
          event.stopPropagation();
          event.preventDefault();
          option.selected = false;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          renderLabel();
          if (!menu.hidden) renderOptions();
        };
        remove.addEventListener('click', removeMechanic);
        remove.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') removeMechanic(event);
        });
        chip.appendChild(remove);
        label.appendChild(chip);
      });
      renderCoverage();
    };

    const filterOptions = () => {
      const query = searchInput.value.trim().toLowerCase();
      let visible = 0;
      optionsContainer.querySelectorAll('.jo-mechanic-combobox-option').forEach((button) => {
        const matches = button.dataset.search.includes(query);
        button.hidden = !matches;
        button.style.display = matches ? '' : 'none';
        if (matches) visible += 1;
      });

      optionsContainer.querySelector('.jo-mechanic-search-empty')?.remove();
      if (!visible) {
        const empty = document.createElement('p');
        empty.className = 'jo-mechanic-combobox-empty jo-mechanic-search-empty';
        empty.textContent = query ? 'No mechanic matches your search.' : 'No available mechanics found.';
        optionsContainer.appendChild(empty);
      }
    };

    const renderOptions = () => {
      optionsContainer.replaceChildren();
      Array.from(select.options).forEach((option) => {
        if (!option.value) return;

        const name = mechanicName(option);
        const specializations = mechanicSpecializations(option);
        const availability = option.dataset.availability || 'Available';
        const matches = option.dataset.skillMatches || '';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'jo-mechanic-combobox-option jo-mechanic-multi-option';
        button.dataset.value = option.value;
        button.dataset.search = `${name} ${specializations} ${availability} ${matches}`.toLowerCase();
        button.classList.toggle('is-selected', option.selected);
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', String(option.selected));

        const copy = document.createElement('span');
        const title = document.createElement('strong');
        const details = document.createElement('small');
        title.textContent = name;
        const qualified = option.dataset.qualifiedJobs || '';
        details.textContent = `${specializations} · ${availability}${qualified ? ' · Covers: ' + qualified : ''}`;
        copy.append(title, details);

        const check = document.createElement('i');
        check.className = 'fa-solid fa-check';
        button.append(copy, check);
        button.addEventListener('click', () => {
          option.selected = !option.selected;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          renderLabel();
          renderOptions();
          filterOptions();
        });
        optionsContainer.appendChild(button);
      });
      renderLabel();
      filterOptions();
    };

    const openMenu = async () => {
      if (select.disabled) return;
      if (select.id === 'jobAssignedMechanics' && typeof window.GCTRefreshJobOrderMechanics === 'function') {
        await window.GCTRefreshJobOrderMechanics();
      }
      renderOptions();
      menu.hidden = false;
      combobox.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      mountMenu();
      searchInput.value = '';
      filterOptions();
      window.setTimeout(() => searchInput.focus(), 0);
    };

    trigger.addEventListener('click', () => {
      if (menu.hidden) void openMenu();
      else closeMenu();
    });
    searchInput.addEventListener('input', filterOptions);
    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMenu();
        trigger.focus();
      }
    });
    document.addEventListener('click', (event) => {
      if (!combobox.contains(event.target) && !menu.contains(event.target)) closeMenu();
    });

    const observer = new MutationObserver(() => {
      renderOptions();
      trigger.disabled = select.disabled;
      combobox.classList.toggle('is-disabled', select.disabled);
    });
    observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
    renderOptions();
  }

  setupSearchableSelect(busSelect, {
    className: 'jo-bus-combobox',
    icon: 'fa-bus',
    placeholder: 'Select bus',
    searchPlaceholder: 'Search bus ID or plate number...',
    emptyMessage: 'No bus matches your search.',
    optionHint: 'Bus ID · Plate number',
    // The native value remains bus_no for submission. The visible label shows
    // both identifiers so Maintenance can confirm the correct physical bus.
    formatOptionText: (text) => text,
  });

  setupMechanicMultiSelect(mechanicSelect);
  setupSearchableSelect(editBusSelect, {
    className: 'jo-bus-combobox',
    icon: 'fa-bus',
    placeholder: 'Select bus',
    searchPlaceholder: 'Search bus ID or plate number...',
    emptyMessage: 'No bus matches your search.',
    optionHint: 'Current Job Order bus',
    formatOptionText: (text) => text,
    readonly: true,
  });
  setupMechanicMultiSelect(editMechanicSelect);

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.open-edit-modal')) return;
    window.setTimeout(updateEditLinkedDriver, 0);
  });
});

/* Maintenance Head review: accessible centered modal instead of a table popover. */
window.GCTPartialNavigation.registerInitializer('maintenance-jo-head-review-dialog', '.jo-page', () => {
  document.querySelectorAll('.jo-review-actions').forEach((details) => {
    if (details.dataset.reviewDialogReady === '1') return;
    details.dataset.reviewDialogReady = '1';
    const trigger = details.querySelector('summary');
    const panel = details.querySelector('.jo-review-panel');
    if (!trigger || !panel) return;
    trigger.setAttribute('role', 'button');
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    const overlay = document.createElement('div');
    overlay.className = 'jo-head-review-overlay';
    overlay.hidden = true;
    const dialog = document.createElement('section');
    dialog.className = 'jo-head-review-dialog';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    const titleId = 'jo-review-title-' + (details.closest('tr')?.querySelector('[data-id]')?.dataset.id || Math.random().toString(36).slice(2));
    dialog.setAttribute('aria-labelledby', titleId);
    const heading = document.createElement('div');
    heading.className = 'jo-head-review-heading';
    const title = document.createElement('div');
    title.innerHTML = '<strong id="' + titleId + '">Review Job Order</strong><small>Verify details, confirm duration, and authorize the Job Order.</small>';
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'jo-head-review-close';
    closeButton.setAttribute('aria-label', 'Close review');
    closeButton.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    heading.append(title, closeButton);
    const body = document.createElement('div');
    body.className = 'jo-head-review-body';
    dialog.append(heading, body);
    overlay.append(dialog);
    let previousFocus = null;
    const close = () => {
      if (overlay.hidden) return;
      overlay.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      details.append(panel);
      document.body.classList.remove('jo-head-review-open');
      previousFocus?.focus?.();
    };
    const open = () => {
      document.querySelectorAll('.jo-head-review-overlay:not([hidden]) .jo-head-review-close').forEach((b) => b.click());
      previousFocus = document.activeElement;
      details.open = false;
      body.append(panel);
      document.body.append(overlay);
      overlay.hidden = false;
      document.body.classList.add('jo-head-review-open');
      // Refresh all review-part badges whenever this dialog becomes visible.
      panel.querySelector('.jo-review-parts-list')?.dispatchEvent(new Event('jo-head-review-opened'));
      trigger.setAttribute('aria-expanded', 'true');
      closeButton.focus();
    };
    trigger.addEventListener('click', (event) => { event.preventDefault(); open(); });
    closeButton.addEventListener('click', close);
    overlay.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { event.preventDefault(); close(); }
      if (event.key === 'Tab') {
        const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href]')].filter((el) => el.getClientRects().length);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    });
    const signatureFile = panel.querySelector('.jo-signature-file');
    const signatureInput = panel.querySelector('.jo-signature-image');
    const signatureChoose = panel.querySelector('.jo-signature-choose');
    const signatureRemove = panel.querySelector('.jo-signature-remove');
    const signaturePreview = panel.querySelector('.jo-signature-preview');
    const signaturePlaceholder = panel.querySelector('.jo-signature-upload-placeholder');
    const signatureFeedback = panel.querySelector('.jo-signature-feedback');
    const approveForm = panel.querySelector('.jo-review-approve-form');
    const approveButton = panel.querySelector('.jo-review-approve-submit');
    const syncApprovalButton = () => {
      const ready = Boolean(signatureInput?.value);
      if (approveButton) {
        approveButton.disabled = !ready;
        approveButton.setAttribute('aria-disabled', String(!ready));
        approveButton.title = ready ? 'Approve and sign this Job Order' : 'Upload and crop a signature to enable approval';
      }
    };
    const setSignatureError = (message) => {
      if (!signatureFeedback) return;
      signatureFeedback.textContent = message;
      signatureFeedback.classList.toggle('jo-signature-error', Boolean(message));
    };
    const clearSignature = () => {
      if (signatureInput) signatureInput.value = '';
      syncApprovalButton();
      if (signatureFile) signatureFile.value = '';
      if (signaturePreview) { signaturePreview.hidden = true; signaturePreview.removeAttribute('src'); }
      if (signaturePlaceholder) signaturePlaceholder.hidden = false;
      if (signatureRemove) signatureRemove.hidden = true;
      if (signatureChoose) signatureChoose.innerHTML = '<i class="fa-regular fa-image" aria-hidden="true"></i> Choose file';
      setSignatureError('Your uploaded signature will appear here before approval.');
      signatureFeedback?.classList.remove('jo-signature-error');
    };
    signatureChoose?.addEventListener('click', () => signatureFile?.click());
    signatureRemove?.addEventListener('click', clearSignature);
    const cropModal = panel.querySelector('.jo-signature-crop-modal');
    const cropImage = panel.querySelector('.jo-signature-crop-image');
    const cropViewport = panel.querySelector('.jo-signature-crop-viewport');
    const cropZoom = panel.querySelector('.jo-signature-crop-zoom');
    const cropApply = panel.querySelector('.jo-signature-crop-apply');
    let cropUrl = null;
    let cropFocus = null;
    let cropState = null;
    const cropRender = () => {
      if (!cropState || !cropImage || !cropViewport) return;
      const w = cropViewport.clientWidth, h = cropViewport.clientHeight;
      const scale = Math.max(w / cropState.width, h / cropState.height) * cropState.zoom;
      const renderedWidth = cropState.width * scale, renderedHeight = cropState.height * scale;
      cropState.x = Math.max((w - renderedWidth) / 2, Math.min((renderedWidth - w) / 2, cropState.x));
      cropState.y = Math.max((h - renderedHeight) / 2, Math.min((renderedHeight - h) / 2, cropState.y));
      cropImage.style.width = renderedWidth + 'px';
      cropImage.style.height = renderedHeight + 'px';
      cropImage.style.transform = 'translate(-50%, -50%) translate(' + cropState.x + 'px,' + cropState.y + 'px)';
    };
    const closeCrop = () => {
      if (cropModal) cropModal.hidden = true;
      if (cropUrl) URL.revokeObjectURL(cropUrl);
      cropUrl = null;
      cropState = null;
      if (signatureFile) signatureFile.value = '';
      cropFocus?.focus();
    };
    panel.querySelectorAll('.jo-signature-crop-cancel, .jo-signature-crop-back')
      .forEach((button) => button.addEventListener('click', closeCrop));
    let cropDrag = null;
    cropViewport?.addEventListener('pointerdown', (event) => {
      if (!cropState) return;
      cropDrag = { id: event.pointerId, x: event.clientX, y: event.clientY, sx: cropState.x, sy: cropState.y };
      cropViewport.setPointerCapture(event.pointerId);
    });
    cropViewport?.addEventListener('pointermove', (event) => {
      if (!cropDrag || cropDrag.id !== event.pointerId || !cropState) return;
      cropState.x = cropDrag.sx + event.clientX - cropDrag.x;
      cropState.y = cropDrag.sy + event.clientY - cropDrag.y;
      cropRender();
    });
    cropViewport?.addEventListener('pointerup', () => { cropDrag = null; });
    cropViewport?.addEventListener('pointercancel', () => { cropDrag = null; });
    cropZoom?.addEventListener('input', () => {
      if (!cropState) return;
      cropState.zoom = Number(cropZoom.value);
      cropRender();
    });
    signatureFile?.addEventListener('change', () => {
      const file = signatureFile.files?.[0];
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
        signatureFile.value = '';
        setSignatureError('Choose a JPG, PNG or WebP signature image no larger than 2 MB.');
        return;
      }
      cropFocus = document.activeElement;
      cropUrl = URL.createObjectURL(file);
      cropImage.onload = () => {
        if (!cropUrl) return;
        cropState = { width: cropImage.naturalWidth, height: cropImage.naturalHeight, zoom: 1, x: 0, y: 0 };
        cropModal.hidden = false;
        cropZoom.value = '1';
        cropRender();
        cropZoom.focus();
      };
      cropImage.onerror = () => {
        closeCrop();
        setSignatureError('The selected image cannot be opened.');
      };
      cropImage.src = cropUrl;
    });
    cropApply?.addEventListener('click', () => {
      if (!cropState || !cropViewport || !signatureInput) return;
      try {
        const canvas = document.createElement('canvas');
        canvas.width = 660;
        canvas.height = 210;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas unavailable');
        const viewWidth = cropViewport.clientWidth, viewHeight = cropViewport.clientHeight;
        const scale = Math.max(viewWidth / cropState.width, viewHeight / cropState.height) * cropState.zoom;
        const sourceWidth = viewWidth / scale, sourceHeight = viewHeight / scale;
        const sourceX = (cropState.width - sourceWidth) / 2 - cropState.x / scale;
        const sourceY = (cropState.height - sourceHeight) / 2 - cropState.y / scale;
        context.drawImage(cropImage, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, 660, 210);
        const png = canvas.toDataURL('image/png');
        if (png.length > 550000) throw new Error('Signature too large');
        signatureInput.value = png;
        syncApprovalButton();
        signaturePreview.src = png;
        signaturePreview.hidden = false;
        signaturePlaceholder.hidden = true;
        signatureRemove.hidden = false;
        signatureChoose.innerHTML = '<i class="fa-regular fa-image" aria-hidden="true"></i> Change';
        setSignatureError('');
        closeCrop();
      } catch {
        setSignatureError('Unable to prepare this signature crop. Please choose a simpler image.');
      }
    });

    approveForm?.addEventListener('submit', (event) => {
      if (!signatureInput?.value) {
        event.preventDefault();
        setSignatureError('Upload a signature image before approving this Job Order.');
        signatureChoose?.focus();
      }
    });
    clearSignature();

    const forms = {
      approve: panel.querySelector('.jo-review-approve-form'),
      revision: panel.querySelector('.jo-review-revision-form'),
      reject: panel.querySelector('.jo-review-reject-form'),
    };
    const footer = panel.querySelector('.jo-review-footer');
    const back = panel.querySelector('.jo-review-back');
    const revisionTrigger = panel.querySelector('.jo-review-revision-trigger');
    const rejectTrigger = panel.querySelector('.jo-review-reject-trigger');
    const decision = panel.querySelector('select[name="duration_decision"]');
    const adjust = panel.querySelector('.jo-duration-adjust-fields');
    const syncDuration = () => {
      const active = decision?.value === 'adjust';
      if (adjust) adjust.hidden = !active;
      adjust?.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !active;
        field.required = active;
      });
    };
    decision?.addEventListener('change', syncDuration);
    syncDuration();
    const setMode = (mode) => {
      Object.entries(forms).forEach(([key, form]) => {
        if (!form) return;
        form.hidden = key !== mode;
        form.querySelectorAll('input, select, textarea').forEach((input) => {
          input.disabled = key !== mode;
        });
      });
      if (mode === 'approve') syncDuration();
      if (back) back.hidden = mode === 'approve';
      if (revisionTrigger) revisionTrigger.hidden = mode !== 'approve';
      if (rejectTrigger) rejectTrigger.hidden = mode !== 'approve';
      if (footer) {
        footer.dataset.mode = mode;
        footer.querySelectorAll('.jo-review-footer-submit').forEach((button) => {
          button.hidden = !button.classList.contains('jo-review-' + mode + '-submit');
        });
      }
      const modeName = mode === 'revision' ? 'Return for Revision' : mode === 'reject' ? 'Reject Job Order' : 'Review Job Order';
      const headingTitle = heading.querySelector('strong');
      if (headingTitle) headingTitle.textContent = modeName;
    };
    revisionTrigger?.addEventListener('click', () => setMode('revision'));
    rejectTrigger?.addEventListener('click', () => setMode('reject'));
    back?.addEventListener('click', () => setMode('approve'));
    trigger.addEventListener('click', () => setMode('approve'));
    // Backdrop clicks do not close modals (system-wide requirement).
  });
});


/* Independent read-only details dialog; approved JOs never enter the edit form. */
if (!window.__gctJobOrderViewDelegated) {
  window.__gctJobOrderViewDelegated = true;
  let priorViewFocus = null;
  const dismissJobOrderView = () => {
    const overlay = document.getElementById('joReadonlyOverlay');
    if (!overlay || overlay.hidden) return;
    overlay.hidden = true;
    document.body.classList.remove('jo-readonly-open');
    overlay.querySelector('#joReadonlyBody')?.replaceChildren();
    priorViewFocus?.focus?.();
  };
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.jo-open-view');
    if (trigger) {
      event.preventDefault();
      const overlay = document.getElementById('joReadonlyOverlay');
      const template = trigger.closest('tr')?.querySelector('template.jo-view-content');
      const body = overlay?.querySelector('#joReadonlyBody');
      if (!overlay || !template || !body) return;
      priorViewFocus = trigger;
      body.replaceChildren(template.content.cloneNode(true));
      overlay.hidden = false;
      document.body.classList.add('jo-readonly-open');
      overlay.querySelector('.jo-view-close')?.focus();
      return;
    }
    if (event.target.closest('.jo-view-close')) dismissJobOrderView();
  });
  document.addEventListener('keydown', (event) => {
    const overlay = document.getElementById('joReadonlyOverlay');
    if (!overlay || overlay.hidden) return;
    if (event.key === 'Escape') { event.preventDefault(); dismissJobOrderView(); }
    if (event.key !== 'Tab') return;
    const nodes = [...overlay.querySelectorAll('button:not(:disabled), summary, a[href]')].filter(el => el.getClientRects().length);
    if (!nodes.length) return;
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
}


/* Keep JO mechanic +N popovers outside the table's clipped scroll viewport. */
window.GCTPartialNavigation.registerInitializer('maintenance-jo-mechanic-more-portal', '.jo-page', () => {
  document.querySelectorAll('.jo-mechanic-more').forEach((details) => {
    if (details.dataset.portalReady === '1') return;
    details.dataset.portalReady = '1';
    const trigger = details.querySelector('summary');
    const panel = details.querySelector('.jo-mechanic-more-panel');
    if (!trigger || !panel) return;

    let open = false;
    const position = () => {
      if (!open) return;
      const rect = trigger.getBoundingClientRect();
      const width = Math.max(230, Math.min(300, window.innerWidth - 24));
      const estimatedHeight = Math.min(panel.scrollHeight || 210, 225);
      const roomBelow = window.innerHeight - rect.bottom - 12;
      const above = roomBelow < estimatedHeight && rect.top > roomBelow;
      const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
      panel.style.position = 'fixed';
      panel.style.zIndex = '110500';
      panel.style.width = width + 'px';
      panel.style.left = left + 'px';
      panel.style.top = above ? 'auto' : (rect.bottom + 6) + 'px';
      panel.style.bottom = above ? (window.innerHeight - rect.top + 6) + 'px' : 'auto';
      panel.style.maxHeight = Math.min(225, Math.max(90, above ? rect.top - 18 : roomBelow)) + 'px';
    };
    const close = () => {
      if (!open) return;
      open = false;
      details.open = false;
      panel.hidden = true;
      panel.removeAttribute('style');
      details.appendChild(panel);
      window.removeEventListener('resize', position);
      window.removeEventListener('scroll', position, true);
    };
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      if (open) { close(); return; }
      document.querySelectorAll('.jo-mechanic-more[data-portal-ready="1"][open] summary')
        .forEach((other) => { if (other !== trigger) other.click(); });
      open = true;
      details.open = true;
      panel.hidden = false;
      document.body.appendChild(panel);
      position();
      window.addEventListener('resize', position);
      window.addEventListener('scroll', position, true);
    });
    document.addEventListener('click', (event) => {
      if (open && !details.contains(event.target) && !panel.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
      if (open && event.key === 'Escape') { close(); trigger.focus(); }
    });
  });
});

