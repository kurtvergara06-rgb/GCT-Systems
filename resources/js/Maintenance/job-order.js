window.GCTPartialNavigation.registerInitializer('maintenance-job-orders', '.jo-page', () => {
  const jobOrderPage = document.querySelector('.jo-page');

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

    if (window.GCTModalBackdrop?.sync) {
      window.GCTModalBackdrop.sync();
    }

    window.GCTSystemAnimations?.animateModalOpen?.(modal);
  }


  function closeModal(modal) {

    if (!modal) {
      return;
    }

    const finishClose = () => {
      modal.classList.remove(
        'show',
        'active'
      );
      modal.style.display = '';

      if (window.GCTModalBackdrop?.sync) {
        window.GCTModalBackdrop.sync();
      }
    };

    if (!window.GCTSystemAnimations?.animateModalClose?.(modal, finishClose)) {
      finishClose();
    }
  }



  // Initialize the four JO sections for both create and edit dialogs.
  // This intentionally does not disable inputs inside collapsed sections.
  function initializeJoSectionProgress(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal || modal.dataset.joSectionsInitialized === 'true') return;
    modal.dataset.joSectionsInitialized = 'true';
    const sections = [...modal.querySelectorAll('[data-jo-collapsible]')];
    const steps = [...modal.querySelectorAll('.jo-new-stepper .jo-new-step')];
    const scrollArea = modal.querySelector('.ui-form-modal-scrollable');

    function isPopulated(control) {
      if (control.disabled || control.closest('[hidden]')) return true;
      if (control.tagName === 'SELECT' && control.multiple) return control.selectedOptions.length > 0;
      if (control.type === 'checkbox' || control.type === 'radio') return control.checked;
      return String(control.value || '').trim() !== '' && control.checkValidity();
    }

    function sectionComplete(section, index) {
      if (index === 3) {
        const names = [...section.querySelectorAll('.jo-part-row input[name$="[name]"]')];
        const hasParts = names.some(input => input.value.trim() !== '');
        if (!hasParts) return { state: 'optional', label: 'Optional' };
        const validRows = [...section.querySelectorAll('.jo-part-row')].every(row => {
          const name = row.querySelector('input[name$="[name]"]');
          const qty = row.querySelector('input[name$="[quantity]"]');
          const unit = row.querySelector('select[name$="[unit]"]');
          return !name || (!name.value.trim() && !qty?.value) ||
            (name.value.trim() && Number(qty?.value) > 0 && Boolean(unit?.value));
        });
        return { state: validRows ? 'completed' : 'incomplete', label: validRows ? 'Complete' : 'Incomplete' };
      }
      const required = [...section.querySelectorAll('[required]')].filter(el => !el.closest('[hidden]'));
      const complete = required.length > 0 && required.every(isPopulated);
      return { state: complete ? 'completed' : 'incomplete', label: complete ? 'Complete' : 'Incomplete' };
    }

    function update() {
      sections.forEach((section, index) => {
        const { state, label } = sectionComplete(section, index);
        const badge = section.querySelector(':scope > .jo-create-section-header > .jo-section-progress');
        if (badge) { badge.dataset.state = state; badge.textContent = label; }
        if (steps[index]) {
          steps[index].classList.toggle('is-complete', state === 'completed');
          steps[index].classList.toggle('is-current', state !== 'completed' &&
            sections.slice(0,index).every((s,i) => sectionComplete(s,i).state !== 'incomplete'));
          steps[index].setAttribute('aria-label', (index + 1) + '. ' + label);
        }
      });
    }

    sections.forEach((section, index) => {
      const header = section.querySelector(':scope > .jo-create-section-header');
      if (!header) return;
      const badge = document.createElement('span');
      badge.className = 'jo-section-progress';
      badge.setAttribute('aria-live', 'polite');
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'jo-collapse-toggle';
      toggle.setAttribute('aria-label', 'Collapse section ' + (index + 1));
      toggle.setAttribute('aria-expanded', 'true');
      toggle.innerHTML = '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i>';
      header.append(badge, toggle);
      toggle.addEventListener('click', () => {
        const collapsed = section.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', (collapsed ? 'Expand' : 'Collapse') + ' section ' + (index + 1));
        toggle.querySelector('i').className = 'fa-solid ' + (collapsed ? 'fa-chevron-down' : 'fa-chevron-up');
        if (!collapsed && scrollArea) {
          requestAnimationFrame(() => {
            const scrollRect = scrollArea.getBoundingClientRect();
            const rect = header.getBoundingClientRect();
            scrollArea.scrollTop += rect.top - scrollRect.top - 12;
          });
        }
      });
    });

    modal.addEventListener('input', update);
    modal.addEventListener('change', update);
    // Other application scripts populate maintenance work/mechanics asynchronously.
    modal.addEventListener('click', () => requestAnimationFrame(update));
    update();
  }
  initializeJoSectionProgress('jobModal');
  initializeJoSectionProgress('editJobModal');


  // Warehouse inventory is the authoritative catalogue for requested JO parts.
  // Preserve existing part[name] and part[unit] fields for the backend parser.
  // No numeric stock quantity is disclosed here; status is advisory until reservation.
  const inventoryCatalogUrl = '/job-orders/inventory-options';
  let joInventoryCatalog = [];
  const joInventoryWrappers = ['partsNeededWrapper', 'editPartsNeededWrapper'];

  function enhanceJoPartRow(row) {
    if (row.dataset.inventoryEnhanced === '1') return;
    const name = row.querySelector('input[name*="[name]"]');
    const qty = row.querySelector('input[name*="[quantity]"]');
    const unit = row.querySelector('select[name*="[unit]"]');
    if (!name || !qty || !unit) return;
    row.dataset.inventoryEnhanced = '1';
    const inventoryId = document.createElement('input');
    inventoryId.type = 'hidden';
    inventoryId.name = name.name.replace('[name]', '[inventory_item_id]');
    inventoryId.className = 'jo-inventory-id';
    const selector = document.createElement('select');
    selector.className = 'jo-inventory-picker';
    selector.setAttribute('aria-label', 'Select Warehouse inventory part');
    const stock = document.createElement('span');
    stock.className = 'jo-inventory-stock-status';
    stock.setAttribute('aria-live', 'polite');
    row.insertBefore(selector, name);
    name.classList.add('jo-inventory-name-field');
    name.tabIndex = -1;
    row.insertBefore(inventoryId, qty);
    row.insertBefore(stock, row.querySelector('.remove-part-btn'));
    unit.classList.add('jo-auto-unit-select');
    unit.setAttribute('aria-label','Unit from inventory');
    unit.tabIndex = -1;
    const update = () => {
      const selected = joInventoryCatalog.find(item => String(item.id) === selector.value);
      inventoryId.value = selected ? String(selected.id) : '';
      if (selected) {
        name.value = selected.name;
        unit.value = selected.unit;
      } else if (!name.value.trim()) {
        unit.value = '';
      }
      const requested = Number(qty.value);
      stock.textContent = !selected ? 'Select part' : selected.in_stock ? 'In Stock' : 'Out of Stock';
      stock.dataset.state = !selected ? 'unknown' : selected.in_stock ? 'available' : 'unavailable';
      stock.title = selected ? 'Warehouse availability is checked again when submitting and approving.' : '';
      if (selected && requested > 0) stock.title += ' Requested: ' + requested + ' ' + selected.unit + '.';
      row.dispatchEvent(new Event('change', { bubbles: false }));
    };
    const render = () => {
      const priorName = name.value.trim();
      const priorId = inventoryId.value || (priorName.match(/\\[item:\\s*(\\d+)\\]/i)?.[1] || '');
      selector.replaceChildren(new Option('Select inventory part', ''));
      joInventoryCatalog.forEach(item => selector.add(new Option(item.name + (item.code ? ' · ' + item.code : ''), String(item.id))));
      const matched = joInventoryCatalog.find(item => String(item.id) === priorId) ||
        joInventoryCatalog.find(item => item.name.toLowerCase() === priorName.toLowerCase());
      if (matched) selector.value = String(matched.id);
      else if (priorName) selector.add(new Option(priorName + ' (legacy part — reselect)', '', true, true));
      update();
    };
    selector.addEventListener('change', () => { name.value = ''; update(); });
    qty.addEventListener('input', update);
    render();
  }

  function enhanceJoPartRows() {
    joInventoryWrappers.forEach(id => {
      const wrapper = document.getElementById(id);
      if (!wrapper) return;
      wrapper.querySelectorAll('.part-needed-row').forEach(enhanceJoPartRow);
      if (wrapper.dataset.joInventoryObserver !== '1') {
        wrapper.dataset.joInventoryObserver = '1';
        new MutationObserver(() => wrapper.querySelectorAll('.part-needed-row').forEach(enhanceJoPartRow))
          .observe(wrapper, { childList: true });
      }
    });
  }
  enhanceJoPartRows();
  fetch(inventoryCatalogUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(response => { if (!response.ok) throw new Error('Inventory catalogue unavailable'); return response.json(); })
    .then(items => {
      joInventoryCatalog = Array.isArray(items) ? items.filter(item => item.id && item.name && item.unit) : [];
      joInventoryWrappers.forEach(id => {
        document.getElementById(id)?.querySelectorAll('.part-needed-row').forEach(row => {
          row.dataset.inventoryEnhanced = '';
        });
      });
      // Update existing selectors without losing typed quantities or selected parts.
      joInventoryWrappers.forEach(id => {
        document.getElementById(id)?.querySelectorAll('.part-needed-row').forEach(row => {
          const select = row.querySelector('.jo-inventory-picker');
          if (select) { select.remove(); row.querySelector('.jo-inventory-id')?.remove(); row.querySelector('.jo-inventory-stock-status')?.remove(); }
          enhanceJoPartRow(row);
        });
      });
    })
    .catch(() => {
      joInventoryWrappers.forEach(id => document.getElementById(id)?.querySelectorAll('.jo-inventory-stock-status').forEach(el => { el.textContent = 'Stock unavailable'; el.dataset.state = 'unknown'; }));
    });

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

          const inventoryId = row.querySelector('.jo-inventory-id');
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
        void refreshAvailableMechanicsDropdown();

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

    const hasMechanic =
      Boolean(jobAssignedMechanic?.selectedOptions.length);


    /*
     * Add Part
     */
    if (addPartBtn) {
      // Requested Parts is optional. Mechanic selection is validated at submission,
      // but must not prevent the user from entering parts ahead of time.
      addPartBtn.disabled = false;
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

          // Parts may be prepared before mechanic assignment.
          field.disabled = false;

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
          false
        );
    }


    /*
     * Lock message
     */
    if (newPartsLockedNotice) {

      newPartsLockedNotice.style.display =
        'none';
    }


    /*
     * No mechanic = no requested parts
     */
    // Do not clear entered parts when mechanic availability changes.
    // Existing server-side mechanic validation remains authoritative.

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

  function setEditBusOption(busNo) {
    if (!editBusNo || !busNo) {
      return;
    }

    editBusNo.querySelectorAll('option[data-current-job-bus="true"]')
      .forEach((option) => option.remove());

    let option = Array.from(editBusNo.options)
      .find((candidate) => candidate.value === busNo);

    if (!option) {
      option = document.createElement('option');
      option.value = busNo;
      option.textContent = `${busNo} (Current Job Order)`;
      option.dataset.currentJobBus = 'true';
      editBusNo.appendChild(option);
    }

    editBusNo.dataset.currentJobBus = busNo;
    editBusNo.value = busNo;
  }

  function setEditMechanicOptions(
    currentMechanicIds = '',
    currentMechanicNames = ''
  ) {

    if (!editAssignedMechanic) {
      return;
    }


    const mechanicIds = String(currentMechanicIds || '')
      .split(',')
      .map((id) => id.trim())
      .filter(Boolean);
    const mechanicNames = String(currentMechanicNames || '')
      .split(',')
      .map((name) => name.trim())
      .filter(Boolean);

    editAssignedMechanic.querySelectorAll('option[data-current-assignment="true"]')
      .forEach((option) => option.remove());

    mechanicIds.forEach((mechanicId, index) => {
      let option = Array.from(editAssignedMechanic.options)
        .find((candidate) => candidate.value === mechanicId);

      if (!option) {
        option = document.createElement('option');
        option.value = mechanicId;
        option.textContent = `${mechanicNames[index] || 'Assigned mechanic'} (Current assignment)`;
        option.dataset.currentAssignment = 'true';
        editAssignedMechanic.appendChild(option);
      }

      option.selected = true;
    });

    Array.from(editAssignedMechanic.options)
      .filter((option) => !mechanicIds.includes(option.value))
      .forEach((option) => {
        option.selected = false;
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


    const hasMechanic =
      Boolean(editAssignedMechanic.selectedOptions.length);


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

  jobOrderPage.addEventListener('click', (event) => {
    const button = event.target.closest('.open-edit-modal');
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


          setEditBusOption(button.dataset.busNo || '');


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
            button.dataset.mechanicIds || '',
            button.dataset.assignedMechanic || ''
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


  jobOrderPage.addEventListener('click', (event) => {
    const button = event.target.closest('.open-delete-modal');
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
     AVAILABLE MECHANICS REFRESH
  ========================================================= */

  async function refreshAvailableMechanicsDropdown() {

    const newJobMechanicSelect =
      document.getElementById('jobAssignedMechanics');


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

        const selectedMechanicIds = new Set(
          Array.from(newJobMechanicSelect.selectedOptions)
            .map((option) => option.value)
        );
        newJobMechanicSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.disabled = true;
        defaultOption.textContent = mechanics.length
          ? 'Select available mechanics'
          : 'No available mechanics';
        newJobMechanicSelect.appendChild(defaultOption);

        mechanics.forEach((mechanic) => {
          const option = document.createElement('option');
          option.value = String(mechanic.id);
          option.textContent = mechanic.specializations?.length
            ? `${mechanic.mechanic_name} · ${mechanic.specializations.join(', ')}`
            : mechanic.mechanic_name;
          option.dataset.specializations = (mechanic.specializations || []).join(', ');
          option.selected = selectedMechanicIds.has(option.value);
          newJobMechanicSelect.appendChild(option);
        });

        newJobMechanicSelect.dispatchEvent(new Event('change', { bubbles: true }));
        updateNewJoPartsState();
      }


      /* =====================================================
         EDIT SELECT
      ====================================================== */

      if (editAssignedMechanic) {

        const selectedMechanicIds = new Set(
          Array.from(editAssignedMechanic.selectedOptions)
            .map((option) => option.value)
        );
        const existingOptions = new Map(
          Array.from(editAssignedMechanic.options)
            .map((option) => [option.value, option])
        );
        editAssignedMechanic.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.disabled = true;
        placeholder.textContent = 'Select assigned mechanics';
        editAssignedMechanic.appendChild(placeholder);

        mechanics.forEach(
          (mechanic) => {
            const option = existingOptions.get(String(mechanic.id))
              || document.createElement('option');
            option.value = String(mechanic.id);
            option.textContent = mechanic.specializations?.length
              ? `${mechanic.mechanic_name} · ${mechanic.specializations.join(', ')}`
              : mechanic.mechanic_name;
            option.selected = selectedMechanicIds.has(option.value);
            option.dataset.specializations = (mechanic.specializations || []).join(', ');
            editAssignedMechanic.appendChild(option);
          }
        );

        selectedMechanicIds.forEach((mechanicId) => {
          if (Array.from(editAssignedMechanic.options).some((option) => option.value === mechanicId)) {
            return;
          }

          const option = existingOptions.get(mechanicId);
          if (option) {
            option.selected = true;
            editAssignedMechanic.appendChild(option);
          }
        });

        editAssignedMechanic.dispatchEvent(new Event('change', { bubbles: true }));
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

  window.GCTRefreshJobOrderMechanics = refreshAvailableMechanicsDropdown;

  if (!window.GCTJobOrderAttendanceRefreshBound) {
    window.addEventListener('system-data-updated', async (event) => {
      const payload = event.detail;
      if (payload?.module === 'Operation' && payload?.entity === 'Attendance') {
        await window.GCTRefreshJobOrderMechanics();
      }
    });
    window.GCTJobOrderAttendanceRefreshBound = true;
  }

});

/* =========================================================
   CONSOLIDATED: resources/js/Maintenance/job-order-finish-guard.js
========================================================= */
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-edit-combobox', '.jo-page', () => {
  const jobOrderPage = document.querySelector('.jo-page');
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
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-new-combobox', '.jo-page', () => {
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
    window.removeEventListener('resize', positionBusMenu);
    window.removeEventListener('scroll', positionBusMenu, true);
    document.removeEventListener('click', handleOutsideMenuClick);
  };

  const positionBusMenu = () => {
    if (!config.portal || menu.hidden || !trigger.isConnected) {
      return;
    }

    const bounds = trigger.getBoundingClientRect();
    Object.assign(menu.style, {
      position: 'fixed',
      left: `${bounds.left}px`,
      top: `${bounds.bottom + 5}px`,
      width: `${bounds.width}px`,
      zIndex: '110500',
    });
  };

  const handleOutsideMenuClick = (event) => {
    if (!combobox.contains(event.target) && !menu.contains(event.target)) {
      closeMenu();
    }
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

  jobOrderPage.addEventListener('click', (event) => {
    if (!combobox.contains(event.target)) {
      closeMenu();
    }
  });

  jobOrderPage.addEventListener('click', (event) => {
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
window.GCTPartialNavigation.registerInitializer('maintenance-job-order-final-actions', '.jo-page', () => {
  const modal = document.getElementById('jobModal');
  const busSelect = document.getElementById('jobBusNo');
  const mechanicSelect = document.getElementById('jobAssignedMechanics');
  const jobOrderPage = document.querySelector('.jo-page');

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
    combobox.className = config.className || 'jo-mechanic-combobox jo-new-combobox';

    const chips = config.multiple ? document.createElement('div') : null;
    if (chips) {
      chips.className = 'jo-mechanic-chips';
      chips.setAttribute('aria-label', 'Selected mechanics');
      combobox.appendChild(chips);
    }

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

    const getDisplayText = (option) => {
      const originalText = option?.textContent?.trim() || '';

      return typeof config.formatOptionText === 'function'
        ? config.formatOptionText(originalText, option?.value || '')
        : originalText;
    };

    const positionBusMenu = () => {
      if (!config.portal || menu.hidden || !trigger.isConnected) return;
      const bounds = trigger.getBoundingClientRect();
      const below = window.innerHeight - bounds.bottom;
      const above = bounds.top;
      const menuHeight = Math.min(270, Math.max(130, below - 12));
      const openAbove = below < 180 && above > below;
      Object.assign(menu.style, {
        position: 'fixed',
        left: Math.max(8, bounds.left) + 'px',
        top: openAbove ? 'auto' : (bounds.bottom + 5) + 'px',
        bottom: openAbove ? Math.max(8, window.innerHeight - bounds.top + 5) + 'px' : 'auto',
        width: bounds.width + 'px',
        maxHeight: (openAbove ? Math.min(270, above - 12) : menuHeight) + 'px',
        zIndex: '110500',
      });
    };

    const handleOutsideMenuClick = (event) => {
      if (!combobox.contains(event.target) && !menu.contains(event.target)) closeMenu();
    };

    const closeMenu = () => {
      menu.hidden = true;
      combobox.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      window.removeEventListener('resize', positionBusMenu);
      window.removeEventListener('scroll', positionBusMenu, true);
      document.removeEventListener('click', handleOutsideMenuClick);
    };

    const updateLabel = () => {
      if (config.multiple) {
        const selected = Array.from(select.selectedOptions)
          .filter((option) => option.value);
        label.textContent = selected.length
          ? `${selected.length} mechanic${selected.length === 1 ? '' : 's'} selected`
          : config.placeholder;
        label.classList.toggle('placeholder', selected.length === 0);

        chips.replaceChildren();
        selected.forEach((option) => {
          const chip = document.createElement('span');
          chip.className = 'jo-mechanic-chip';
          const chipLabel = document.createElement('span');
          chipLabel.textContent = option.textContent.trim();
          const removeButton = document.createElement('button');
          removeButton.type = 'button';
          removeButton.className = 'jo-mechanic-chip-remove';
          removeButton.setAttribute('aria-label', `Remove ${option.textContent.trim()}`);
          removeButton.textContent = '×';
          removeButton.addEventListener('click', () => {
            option.selected = false;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updateLabel();
            renderOptions();
          });
          chip.append(chipLabel, removeButton);
          chips.appendChild(chip);
        });
        return;
      }

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
      if (config.multiple) {
        const option = Array.from(select.options)
          .find((candidate) => candidate.value === value);
        if (option) {
          option.selected = !option.selected;
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
        updateLabel();
        renderOptions();
        return;
      }

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

        if (config.multiple ? option.selected : option.value === select.value) {
          button.classList.add('is-selected');
        }

        if (config.multiple) {
          // A button remains the single accessible selection control: avoid nesting
          // an actual checkbox inside another interactive element.
          button.classList.add('jo-mechanic-structured-option');
          button.setAttribute('aria-pressed', option.selected ? 'true' : 'false');
          const checkbox = document.createElement('span');
          checkbox.className = 'jo-mechanic-option-check';
          checkbox.setAttribute('aria-hidden', 'true');
          checkbox.textContent = option.selected ? '✓' : '';

          const person = document.createElement('span');
          person.className = 'jo-mechanic-option-person';
          const name = originalText.split(' · ')[0].trim();
          const avatar = document.createElement('span');
          avatar.className = 'jo-mechanic-option-avatar';
          avatar.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase();
          const nameBlock = document.createElement('span');
          nameBlock.className = 'jo-mechanic-option-identity';
          const title = document.createElement('strong');
          title.textContent = name;
          const subtitle = document.createElement('small');
          const rawSkills = (option.dataset.specializations || '').trim();
          subtitle.textContent = rawSkills || 'Specialization not recorded';
          nameBlock.append(title, subtitle);
          person.append(avatar, nameBlock);

          const skillsBox = document.createElement('span');
          skillsBox.className = 'jo-mechanic-option-skills';
          const requirements = Array.from(select.closest('form')?.querySelectorAll('[data-jo-skill-coverage] .jo-coverage-skill') || [])
            .map(item => item.textContent.trim().toLocaleLowerCase());
          const known = requirements.length > 0;
          const skillNames = rawSkills.split(/[,;|]/).map(item => item.trim()).filter(Boolean);
          const matched = requirements.filter(skill => skillNames.some(name => name.toLocaleLowerCase() === skill));
          skillNames.forEach(skill => {
            const chip = document.createElement('span');
            chip.className = 'jo-mechanic-option-skill' + (requirements.includes(skill.toLocaleLowerCase()) ? ' is-matched' : '');
            chip.textContent = skill;
            skillsBox.append(chip);
          });
          if (!skillNames.length) skillsBox.textContent = 'No skills recorded';
          button.dataset.search += ' ' + rawSkills.toLowerCase();
          const score = known ? matched.length : -1;
          button.dataset.matchScore = String(score);

          const availability = document.createElement('span');
          availability.className = 'jo-mechanic-option-availability';
          // Do not infer attendance from the legacy "Available" option attribute.
          availability.textContent = option.disabled ? 'Unavailable' : 'Eligible for selection';

          const outcome = document.createElement('span');
          outcome.className = 'jo-mechanic-option-outcome';
          const badge = document.createElement('span');
          badge.className = 'jo-mechanic-match-badge';
          badge.dataset.match = !known ? 'unknown' : score === requirements.length ? 'full' : score ? 'partial' : 'none';
          badge.textContent = !known ? 'Not Evaluated' : score === requirements.length ? 'Best Match' : score ? 'Partial Match' : 'No Match';
          const explanation = document.createElement('small');
          explanation.textContent = !known ? 'Skills not configured' : score === requirements.length ? 'Mapped skills covered' : 'Missing: ' + requirements.filter(skill => !skillNames.some(name => name.toLocaleLowerCase() === skill)).join(', ');
          outcome.append(badge, explanation);
          button.append(checkbox, person, skillsBox, availability, outcome);
          button.disabled = option.disabled;
        } else {
          const details = document.createElement('span');
          const title = document.createElement('strong');
          const hint = document.createElement('small');
          title.textContent = displayText;
          hint.textContent = config.optionHint;
          details.append(title, hint);
          const check = document.createElement('i');
          check.className = 'fa-solid fa-check';
          check.setAttribute('aria-hidden', 'true');
          button.append(details, check);
        }
        button.addEventListener('click', () => selectOption(option.value));
        optionsContainer.appendChild(button);
      });

      if (config.multiple) {
        const ordered = Array.from(optionsContainer.querySelectorAll('.jo-mechanic-combobox-option'));
        ordered.sort((a, b) => Number(b.dataset.matchScore || -1) - Number(a.dataset.matchScore || -1));
        ordered.forEach(element => optionsContainer.append(element));
      }
      updateLabel();
    };

    const openMenu = () => {
      if (select.disabled) {
        return;
      }

      // Refresh match labels against the latest maintenance-job selection.
      if (config.multiple) renderOptions();
      menu.hidden = false;
      combobox.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      positionBusMenu();
      window.addEventListener('resize', positionBusMenu);
      window.addEventListener('scroll', positionBusMenu, true);
      document.addEventListener('click', handleOutsideMenuClick);
      searchInput.value = '';
      filterOptions();
      window.setTimeout(() => searchInput.focus(), 0);
    };

    trigger.addEventListener('click', () => {
      menu.hidden ? openMenu() : closeMenu();
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

    if (config.portal) {
      menu.classList.add('jo-bus-menu-portal');
      document.body.appendChild(menu);
    }

    jobOrderPage.addEventListener('click', (event) => {
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
  }

  setupSearchableSelect(busSelect, {
    className: 'jo-bus-combobox',
    portal: true,
    icon: 'fa-bus',
    placeholder: 'Select Bus',
    searchPlaceholder: 'Search bus ID or plate number...',
    emptyMessage: 'No bus matches your search.',
    optionHint: 'Bus ID · Plate number',
    formatOptionText: (text) => text,
  });

  function setupMechanicMultiSelect(select) {
    setupSearchableSelect(select, {
      className: 'jo-mechanic-combobox jo-new-combobox jo-multi-mechanic-combobox',
      multiple: true,
      icon: 'fa-user-gear',
      placeholder: 'Select available mechanics',
      searchPlaceholder: 'Search mechanic name...',
      emptyMessage: 'No mechanic matches your search.',
      optionHint: 'Available mechanic',
    });
  }

  setupMechanicMultiSelect(mechanicSelect);

  const busDriverMapElement = document.getElementById('jobBusDriverMap');
  let busDriverMap = {};
  try {
    busDriverMap = JSON.parse(busDriverMapElement?.textContent || '{}');
  } catch (error) {
    console.error('Unable to read the Job Order bus-driver map.', error);
  }

  const busDriverPreview = document.getElementById('jobLinkedDriverPreview');
  const linkedDriverName = document.getElementById('jobLinkedDriver');
  const updateLinkedDriver = () => {
    const driverName = busDriverMap[busSelect?.value] || '';
    if (linkedDriverName) {
      linkedDriverName.textContent = driverName || 'No authorized driver assigned';
    }
    if (busDriverPreview) {
      busDriverPreview.hidden = !busSelect?.value;
    }
  };

  busSelect?.addEventListener('change', updateLinkedDriver);
  updateLinkedDriver();

  const newJobOrderForm = document.getElementById('newJobOrderForm');
  function validateNewJobOrderForm(form) {
    const requiredChecks = [
      [busSelect?.value, 'Please select a Bus.'],
      [form.querySelector('[name="problem_issue"]')?.value.trim(), 'Please enter the Problem / Issue.'],
      [
        form.querySelector('[name="maintenance_jobs[]"], [name="maintenance_job"]')?.value
          || form.querySelector('[name="custom_maintenance_job"]')?.value.trim(),
        'Please select a Maintenance Job.',
      ],
      [mechanicSelect?.selectedOptions.length, 'Please assign at least one mechanic.'],
    ];

    const failedCheck = requiredChecks.find(([value]) => !value);
    if (failedCheck) {
      if (window.showSystemToast) {
        window.showSystemToast(failedCheck[1], 'error', 'Validation Error');
      }
      form.querySelector(':invalid')?.scrollIntoView?.({
        behavior: 'smooth',
        block: 'center',
      });
      return false;
    }

    const durationValue = Number(form.querySelector('[name="estimated_duration_value"]')?.value);
    if (!Number.isFinite(durationValue) || durationValue <= 0) {
      if (window.showSystemToast) {
        window.showSystemToast('The historical estimated duration is invalid. Please refresh.', 'error', 'Validation Error');
      }
      form.querySelector('[name="estimated_duration_value"]')?.scrollIntoView?.({
        behavior: 'smooth',
        block: 'center',
      });
      return false;
    }

    return true;
  }

  newJobOrderForm?.addEventListener('submit', (event) => {
    if (!validateNewJobOrderForm(newJobOrderForm)) {
      event.preventDefault();
    }
  });

  const isPmsCreateFlow = modal.dataset.pmsCreate === 'true';
  function cleanPmsCreateUrl() {
    const url = new URL(window.location.href);
    ['pms_create', 'pms_schedule_id', 'bus_no', 'problem_issue'].forEach((key) => {
      url.searchParams.delete(key);
    });
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
  }
  function cancelNewJobOrder() {
    if (isPmsCreateFlow) {
      cleanPmsCreateUrl();
    }
  }

  document.getElementById('cancelJobModal')?.addEventListener('click', cancelNewJobOrder);
  document.getElementById('closeJobModal')?.addEventListener('click', cancelNewJobOrder);

  if (isPmsCreateFlow) {
    window.requestAnimationFrame(() => {
      openModal(modal);
      updateLinkedDriver();
    });
  }
});
