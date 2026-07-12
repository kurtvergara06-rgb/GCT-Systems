document.addEventListener('DOMContentLoaded', () => {
  function openModal(modal) {
    if (modal) {
      modal.classList.add('show');
      modal.style.display = 'flex';
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('show');
      modal.classList.remove('active');
      modal.style.display = '';
    }
  }

  function escapeInputValue(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

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

    let html = '<option value="">Unit</option>';

    units.forEach((unit) => {
      html += `
        <option value="${unit}" ${selectedUnit === unit ? 'selected' : ''}>
          ${unit}
        </option>
      `;
    });

    return html;
  }

  function parseParts(partText) {
    if (!partText) {
      return [];
    }

    return String(partText)
      .split(',')
      .map((part) => {
        const cleanPart = part.trim();

        if (!cleanPart) {
          return null;
        }

        if (cleanPart.includes(' - Qty:')) {
          const pieces = cleanPart.split(' - Qty:');
          const name = pieces[0]?.trim() || '';
          const quantityWithUnit = pieces[1]?.trim() || '';
          const match = quantityWithUnit.match(/^(\d+)\s*(.*)$/);

          return {
            name,
            quantity: match ? match[1] : '',
            unit: match && match[2] ? match[2].trim() : '',
          };
        }

        return {
          name: cleanPart,
          quantity: '',
          unit: '',
        };
      })
      .filter(Boolean);
  }

  function createPartRow(index, part = {}, isReadonly = false) {
    const row = document.createElement('div');

    row.className = 'part-needed-row';

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
        ${isReadonly ? 'disabled' : ''}
      >
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    return row;
  }

  function refreshPartIndexes(container) {
    if (!container) {
      return;
    }

    container.querySelectorAll('.part-needed-row').forEach((row, index) => {
      const nameInput = row.querySelector('input[name*="[name]"]');
      const quantityInput = row.querySelector('input[name*="[quantity]"]');
      const unitSelect = row.querySelector('select[name*="[unit]"]');

      if (nameInput) {
        nameInput.name = `parts[${index}][name]`;
      }

      if (quantityInput) {
        quantityInput.name = `parts[${index}][quantity]`;
      }

      if (unitSelect) {
        unitSelect.name = `parts[${index}][unit]`;
      }
    });
  }

  function renderParts(container, partText, isReadonly = false) {
    if (!container) {
      return;
    }

    const parts = parseParts(partText);

    const rows = parts.length
      ? parts
      : [{ name: '', quantity: '', unit: '' }];

    container.innerHTML = '';

    rows.forEach((part, index) => {
      container.appendChild(createPartRow(index, part, isReadonly));
    });

    refreshPartIndexes(container);
  }

  function setPartsReadonly(container, isReadonly) {
    if (!container) {
      return;
    }

    container.querySelectorAll('input, select, button').forEach((element) => {
      element.disabled = isReadonly;
    });
  }

  function isLockedPrStatus(status) {
    return [
      'Approved',
      'For Purchase',
      'Ordered',
      'For Pick-up',
      'For Delivery',
      'Delivered',
      'Picked Up',
      'Issued',
    ].includes(status);
  }

  function setEditPrMode(mode, status, canApprove) {
    const isView = mode === 'view';
    const isLocked = isLockedPrStatus(status);
    const isReadonly = isView || isLocked;

    const editPrDescription = document.getElementById('editPrDescription');
    const editPrMainActions = document.getElementById('editPrMainActions');
    const viewOnlyActions = document.getElementById('viewOnlyActions');
    const addEditPrPartBtn = document.getElementById('addEditPrPartBtn');
    const prApprovalActions = document.getElementById('prApprovalActions');
    const editPrPartsContainer = document.getElementById(
      'editPrPartsContainer'
    );
    const editRemarks = document.getElementById('edit_remarks');

    if (editRemarks) {
      editRemarks.disabled = isReadonly;
    }

    setPartsReadonly(editPrPartsContainer, isReadonly);

    if (addEditPrPartBtn) {
      addEditPrPartBtn.style.display = isReadonly
        ? 'none'
        : 'inline-flex';
    }

    if (editPrMainActions) {
      editPrMainActions.style.display = isReadonly
        ? 'none'
        : 'flex';
    }

    if (viewOnlyActions) {
      viewOnlyActions.style.display = isReadonly
        ? 'flex'
        : 'none';
    }

    if (prApprovalActions) {
      const showApprovalButtons =
        !isView &&
        status === 'Submitted' &&
        canApprove;

      prApprovalActions.classList.remove('hidden');

      prApprovalActions.style.display = showApprovalButtons
        ? 'flex'
        : 'none';
    }

    if (editPrDescription) {
      if (isView) {
        editPrDescription.textContent =
          'This purchase request is being viewed only.';
      } else if (isLocked) {
        editPrDescription.textContent =
          'This purchase request can no longer be edited because it is already approved or being processed.';
      } else {
        editPrDescription.textContent =
          'You can edit this purchase request information.';
      }
    }
  }

  const prModal = document.getElementById('prModal');
  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');
  const newPrPartsContainer = document.getElementById(
    'newPrPartsContainer'
  );
  const addNewPrPartBtn = document.getElementById('addNewPrPartBtn');

  if (openPrModal) {
    openPrModal.addEventListener('click', () => {
      openModal(prModal);
    });
  }

  if (closePrModal) {
    closePrModal.addEventListener('click', () => {
      closeModal(prModal);
    });
  }

  if (cancelPrModal) {
    cancelPrModal.addEventListener('click', () => {
      closeModal(prModal);
    });
  }

  if (newPrPartsContainer) {
    const initialParts =
      newPrPartsContainer.dataset.initialParts || '';

    renderParts(newPrPartsContainer, initialParts, false);

    newPrPartsContainer.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (!removeButton) {
        return;
      }

      const row = removeButton.closest('.part-needed-row');

      if (row) {
        row.remove();
        refreshPartIndexes(newPrPartsContainer);
      }
    });
  }

  if (addNewPrPartBtn && newPrPartsContainer) {
    addNewPrPartBtn.addEventListener('click', () => {
      const index =
        newPrPartsContainer.querySelectorAll('.part-needed-row').length;

      newPrPartsContainer.appendChild(
        createPartRow(index, {}, false)
      );

      refreshPartIndexes(newPrPartsContainer);
    });
  }

  if (jobOrderSelect) {
    jobOrderSelect.addEventListener('change', () => {
      const selectedOption =
        jobOrderSelect.options[jobOrderSelect.selectedIndex];

      const busNo = selectedOption?.dataset.bus || '';
      const parts = selectedOption?.dataset.parts || '';

      if (busNoInput) {
        busNoInput.value = busNo;
      }

      if (newPrPartsContainer) {
        renderParts(newPrPartsContainer, parts, false);
      }
    });
  }

  const editPrModal = document.getElementById('editPrModal');
  const editPrForm = document.getElementById('editPrForm');

  const editPrNo = document.getElementById('edit_pr_no');
  const editJobOrderNo = document.getElementById(
    'edit_job_order_no'
  );
  const editBusNo = document.getElementById('edit_bus_no');
  const editStatusDisplay = document.getElementById(
    'edit_status_display'
  );
  const editRemarks = document.getElementById('edit_remarks');
  const editPrPartsContainer = document.getElementById(
    'editPrPartsContainer'
  );

  const closeEditPrModal = document.getElementById(
    'closeEditPrModal'
  );
  const cancelEditPrModal = document.getElementById(
    'cancelEditPrModal'
  );
  const closeViewOnlyPr = document.getElementById(
    'closeViewOnlyPr'
  );
  const addEditPrPartBtn = document.getElementById(
    'addEditPrPartBtn'
  );

  let selectedApproveUrl = '';
  let selectedRejectUrl = '';

  function openPrDetails(button, mode) {
    const status = button.dataset.status || 'Submitted';
    const canApprove = button.dataset.canApprove === '1';

    if (editPrForm) {
      editPrForm.action = button.dataset.updateUrl || '#';
    }

    if (editPrNo) {
      editPrNo.value = button.dataset.prNo || '';
    }

    if (editJobOrderNo) {
      editJobOrderNo.value = button.dataset.jobOrderNo || '';
    }

    if (editBusNo) {
      editBusNo.value = button.dataset.busNo || '';
    }

    if (editStatusDisplay) {
      editStatusDisplay.value = status;
    }

    if (editRemarks) {
      editRemarks.value = button.dataset.remarks || '';
    }

    if (editPrPartsContainer) {
      renderParts(
        editPrPartsContainer,
        button.dataset.item || '',
        mode === 'view' || isLockedPrStatus(status)
      );
    }

    selectedApproveUrl = button.dataset.approveUrl || '';
    selectedRejectUrl = button.dataset.rejectUrl || '';

    setEditPrMode(mode, status, canApprove);

    openModal(editPrModal);
  }

  document.querySelectorAll('.open-view-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      openPrDetails(button, 'view');
    });
  });

  document.querySelectorAll('.open-edit-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const status = button.dataset.status || 'Submitted';

      if (isLockedPrStatus(status)) {
        return;
      }

      openPrDetails(button, 'edit');
    });
  });

  if (addEditPrPartBtn && editPrPartsContainer) {
    addEditPrPartBtn.addEventListener('click', () => {
      const index =
        editPrPartsContainer.querySelectorAll('.part-needed-row')
          .length;

      editPrPartsContainer.appendChild(
        createPartRow(index, {}, false)
      );

      refreshPartIndexes(editPrPartsContainer);
    });

    editPrPartsContainer.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (!removeButton) {
        return;
      }

      const row = removeButton.closest('.part-needed-row');

      if (row) {
        row.remove();
        refreshPartIndexes(editPrPartsContainer);
      }
    });
  }

  if (closeEditPrModal) {
    closeEditPrModal.addEventListener('click', () => {
      closeModal(editPrModal);
    });
  }

  if (cancelEditPrModal) {
    cancelEditPrModal.addEventListener('click', () => {
      closeModal(editPrModal);
    });
  }

  if (closeViewOnlyPr) {
    closeViewOnlyPr.addEventListener('click', () => {
      closeModal(editPrModal);
    });
  }

  const approvePrBtn = document.getElementById('approvePrBtn');
  const rejectPrBtn = document.getElementById('rejectPrBtn');
  const approvePrForm = document.getElementById('approvePrForm');
  const rejectPrForm = document.getElementById('rejectPrForm');

  if (approvePrBtn && approvePrForm) {
    approvePrBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (!selectedApproveUrl) {
        return;
      }

      approvePrBtn.disabled = true;
      approvePrBtn.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Approving...';

      approvePrForm.action = selectedApproveUrl;
      approvePrForm.requestSubmit();
    });
  }

  if (rejectPrBtn && rejectPrForm) {
    rejectPrBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (!selectedRejectUrl) {
        return;
      }

      rejectPrBtn.disabled = true;
      rejectPrBtn.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Rejecting...';

      rejectPrForm.action = selectedRejectUrl;
      rejectPrForm.requestSubmit();
    });
  }

  const deletePrModal = document.getElementById('deletePrModal');
  const deletePrNo = document.getElementById('deletePrNo');
  const cancelDeletePr = document.getElementById('cancelDeletePr');
  const confirmDeletePr = document.getElementById('confirmDeletePr');

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const prNo = button.dataset.prNo;

      selectedDeleteForm = document.getElementById(
        `deletePrForm-${id}`
      );

      if (deletePrNo) {
        deletePrNo.textContent =
          prNo || 'this purchase request';
      }

      openModal(deletePrModal);
    });
  });

  if (cancelDeletePr) {
    cancelDeletePr.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deletePrModal);
    });
  }

  if (confirmDeletePr) {
    confirmDeletePr.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (!selectedDeleteForm) {
        return;
      }

      confirmDeletePr.disabled = true;
      confirmDeletePr.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';

      selectedDeleteForm.requestSubmit();
    });
  }

  const validationErrorModal = document.getElementById(
    'validationErrorModal'
  );

  const closeValidationErrorModal = document.getElementById(
    'closeValidationErrorModal'
  );

  if (closeValidationErrorModal && validationErrorModal) {
    closeValidationErrorModal.addEventListener('click', () => {
      closeModal(validationErrorModal);
    });
  }

  const prStatusFilter = document.getElementById('prStatusFilter');

  if (prStatusFilter) {
    function slugStatus(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/\//g, '-')
        .replace(/\s+/g, '-');
    }

    function updatePrStatusFilterColor() {
      prStatusFilter.className = 'pr-status-select';

      const value = prStatusFilter.value;

      if (value && value !== 'All Statuses') {
        prStatusFilter.classList.add(slugStatus(value));
      }
    }

    updatePrStatusFilterColor();

    prStatusFilter.addEventListener('change', () => {
      updatePrStatusFilterColor();
    });
  }

  document
    .querySelectorAll(
      '.success-modal-overlay button, ' +
      '.success-modal-overlay .feedback-ok-btn, ' +
      '.success-modal-overlay .close-feedback-modal'
    )
    .forEach((button) => {
      button.addEventListener('click', () => {
        const modal = button.closest('.success-modal-overlay');

        if (modal) {
          closeModal(modal);
        }
      });
    });

  document
    .querySelectorAll(
      '.modal-overlay, ' +
      '.delete-modal-overlay, ' +
      '.success-modal-overlay'
    )
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });
});