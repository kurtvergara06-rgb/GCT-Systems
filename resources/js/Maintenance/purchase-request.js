document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
  }

  function closeAllModals() {
    document
      .querySelectorAll(
        '.modal-overlay, .delete-modal-overlay, .success-modal-overlay, .error-modal-overlay, .feedback-modal-overlay, .action-modal-overlay'
      )
      .forEach(function (modal) {
        closeModal(modal);
      });
  }

  function setValue(id, value) {
    const element = document.getElementById(id);

    if (!element) return;

    if (value === undefined || value === null || value === 'null') {
      element.value = '';
      return;
    }

    element.value = value;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function normalizeUnit(unit) {
    const cleanUnit = String(unit || '').trim().toLowerCase();

    if (cleanUnit === 'liters') return 'liter';
    if (cleanUnit === 'ltr') return 'liter';
    if (cleanUnit === 'ltrs') return 'liter';
    if (cleanUnit === 'piece') return 'pcs';
    if (cleanUnit === 'pieces') return 'pcs';
    if (cleanUnit === 'pc') return 'pcs';
    if (cleanUnit === 'sets') return 'set';
    if (cleanUnit === 'bottles') return 'bottle';
    if (cleanUnit === 'boxes') return 'box';
    if (cleanUnit === 'packs') return 'pack';
    if (cleanUnit === 'pairs') return 'pair';
    if (cleanUnit === 'rolls') return 'roll';
    if (cleanUnit === 'tubes') return 'tube';

    return cleanUnit;
  }

  function unitOptions(selectedUnit = '') {
    const normalizedSelectedUnit = normalizeUnit(selectedUnit);

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

    units.forEach(function (unit) {
      html += `<option value="${unit}" ${normalizedSelectedUnit === unit ? 'selected' : ''}>${unit}</option>`;
    });

    return html;
  }

  function parsePrParts(rawItem, rawQuantity) {
    const itemText = String(rawItem || '').trim();
    const fallbackQuantity = parseInt(rawQuantity || '1', 10) || 1;

    if (!itemText) {
      return [];
    }

    return itemText
      .split(',')
      .map(function (part, index) {
        const cleanPart = part.trim();

        if (!cleanPart) return null;

        if (cleanPart.includes(' - Qty:')) {
          const splitParts = cleanPart.split(' - Qty:');
          const name = splitParts[0] ? splitParts[0].trim() : '';
          const quantityWithUnit = splitParts[1] ? splitParts[1].trim() : '';

          const match = quantityWithUnit.match(/^(\d+)\s*(.*)$/);

          return {
            name: name,
            quantity: match ? match[1] : '1',
            unit: match && match[2] ? normalizeUnit(match[2]) : '',
          };
        }

        const oldFormatMatch = cleanPart.match(/^(.*?)\s*\((\d+)\s*([^)]+)\)$/);

        if (oldFormatMatch) {
          return {
            name: oldFormatMatch[1] ? oldFormatMatch[1].trim() : '',
            quantity: oldFormatMatch[2] ? oldFormatMatch[2].trim() : '1',
            unit: oldFormatMatch[3] ? normalizeUnit(oldFormatMatch[3]) : '',
          };
        }

        return {
          name: cleanPart,
          quantity: index === 0 ? fallbackQuantity : 1,
          unit: '',
        };
      })
      .filter(function (part) {
        return part && part.name;
      });
  }

  function refreshPartIndexes(container, prefix) {
    const rows = container.querySelectorAll('.pr-part-row');

    rows.forEach(function (row, index) {
      const nameInput = row.querySelector('.pr-part-name');
      const qtyInput = row.querySelector('.pr-part-qty');
      const unitSelect = row.querySelector('.pr-part-unit');

      if (nameInput) {
        nameInput.name = `${prefix}[${index}][name]`;
      }

      if (qtyInput) {
        qtyInput.name = `${prefix}[${index}][quantity]`;
      }

      if (unitSelect) {
        unitSelect.name = `${prefix}[${index}][unit]`;
      }
    });
  }

  function createPartRow(part, index, prefix, isReadOnly, container) {
    const row = document.createElement('div');
    row.className = 'pr-part-row';

    const name = part && part.name ? part.name : '';
    const quantity = part && part.quantity ? part.quantity : '1';
    const unit = part && part.unit ? part.unit : '';

    row.innerHTML = `
      <input
        type="text"
        name="${prefix}[${index}][name]"
        class="pr-part-name"
        value="${escapeHtml(name)}"
        placeholder="Part name"
        ${isReadOnly ? 'readonly disabled' : ''}
      >

      <input
        type="number"
        name="${prefix}[${index}][quantity]"
        class="pr-part-qty"
        min="1"
        value="${escapeHtml(quantity)}"
        placeholder="Qty"
        ${isReadOnly ? 'readonly disabled' : ''}
      >

      <select
        name="${prefix}[${index}][unit]"
        class="pr-part-unit"
        ${isReadOnly ? 'disabled' : ''}
      >
        ${unitOptions(unit)}
      </select>

      <button
        type="button"
        class="remove-part-btn"
        title="Remove Part"
        ${isReadOnly ? 'style="display: none;"' : ''}
      >
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    const removeBtn = row.querySelector('.remove-part-btn');

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        row.remove();
        refreshPartIndexes(container, prefix);
      });
    }

    return row;
  }

  function renderPartsRows(containerId, rawItem, rawQuantity, options = {}) {
    const container = document.getElementById(containerId);

    if (!container) return;

    const isReadOnly = options.isReadOnly || false;
    const prefix = options.prefix || 'parts';
    const parts = parsePrParts(rawItem, rawQuantity);

    container.innerHTML = '';

    if (isReadOnly) {
      container.classList.add('view-only');
    } else {
      container.classList.remove('view-only');
    }

    if (parts.length === 0) {
      const emptyRow = createPartRow(
        {
          name: '',
          quantity: '1',
          unit: '',
        },
        0,
        prefix,
        isReadOnly,
        container
      );

      container.appendChild(emptyRow);
      return;
    }

    parts.forEach(function (part, index) {
      const row = createPartRow(part, index, prefix, isReadOnly, container);
      container.appendChild(row);
    });
  }

  const prModal = document.getElementById('prModal');
  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

  if (openPrModal && prModal) {
    openPrModal.addEventListener('click', function () {
      openModal(prModal);
    });
  }

  if (closePrModal && prModal) {
    closePrModal.addEventListener('click', function () {
      closeModal(prModal);
    });
  }

  if (cancelPrModal && prModal) {
    cancelPrModal.addEventListener('click', function () {
      closeModal(prModal);
    });
  }

  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');
  const newPrPartsContainer = document.getElementById('newPrPartsContainer');

  if (newPrPartsContainer) {
    const initialParts = newPrPartsContainer.dataset.initialParts || '';

    renderPartsRows('newPrPartsContainer', initialParts, 1, {
      isReadOnly: false,
      prefix: 'parts',
    });
  }

  if (jobOrderSelect) {
    jobOrderSelect.addEventListener('change', function () {
      const selected = jobOrderSelect.options[jobOrderSelect.selectedIndex];

      if (!selected) return;

      if (busNoInput) {
        busNoInput.value = selected.dataset.bus || '';
      }

      renderPartsRows('newPrPartsContainer', selected.dataset.parts || '', 1, {
        isReadOnly: false,
        prefix: 'parts',
      });
    });
  }

  const editPrModal = document.getElementById('editPrModal');
  const editPrForm = document.getElementById('editPrForm');
  const closeEditPrModal = document.getElementById('closeEditPrModal');
  const cancelEditPrModal = document.getElementById('cancelEditPrModal');
  const closeViewOnlyPr = document.getElementById('closeViewOnlyPr');

  const editPrDescription = document.getElementById('editPrDescription');
  const editPrMainActions = document.getElementById('editPrMainActions');
  const viewOnlyActions = document.getElementById('viewOnlyActions');
  const prApprovalActions = document.getElementById('prApprovalActions');

  const approvePrForm = document.getElementById('approvePrForm');
  const rejectPrForm = document.getElementById('rejectPrForm');

  function setPrModalMode(mode, status, canApprove) {
    const isView = mode === 'view';
    const isSubmitted = status === 'Submitted';

    if (editPrDescription) {
      editPrDescription.textContent = isView
        ? 'This purchase request is view only.'
        : 'You can edit this purchase request information.';
    }

    if (editPrMainActions) {
      editPrMainActions.style.display = isView ? 'none' : 'flex';
    }

    if (viewOnlyActions) {
      viewOnlyActions.style.display = isView ? 'flex' : 'none';
    }

    if (prApprovalActions) {
      prApprovalActions.style.display =
        !isView && isSubmitted && canApprove ? 'flex' : 'none';
    }

    const remarks = document.getElementById('edit_remarks');

    if (remarks) {
      remarks.readOnly = isView;
    }

    document
      .querySelectorAll('#editPrPartsContainer input, #editPrPartsContainer select')
      .forEach(function (field) {
        if (isView) {
          field.setAttribute('disabled', 'disabled');
          field.setAttribute('readonly', 'readonly');
        } else {
          field.removeAttribute('disabled');
          field.removeAttribute('readonly');
        }
      });

    document
      .querySelectorAll('#editPrPartsContainer .remove-part-btn')
      .forEach(function (button) {
        button.style.display = isView ? 'none' : 'inline-flex';
      });
  }

  function fillPrModal(button, mode) {
    const status = button.dataset.status || 'Submitted';
    const canApprove = button.dataset.canApprove === '1';

    if (editPrForm) {
      editPrForm.action = button.dataset.updateUrl || '#';
    }

    if (approvePrForm) {
      approvePrForm.action = button.dataset.approveUrl || '#';
    }

    if (rejectPrForm) {
      rejectPrForm.action = button.dataset.rejectUrl || '#';
    }

    setValue('edit_pr_no', button.dataset.prNo);
    setValue('edit_job_order_no', button.dataset.jobOrderNo);
    setValue('edit_bus_no', button.dataset.busNo);
    setValue('edit_status_display', status);
    setValue('edit_remarks', button.dataset.remarks);

    renderPartsRows('editPrPartsContainer', button.dataset.item, button.dataset.quantity, {
      isReadOnly: mode === 'view',
      prefix: 'parts',
    });

    setPrModalMode(mode, status, canApprove);

    openModal(editPrModal);
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-pr-modal');

    if (!button) return;

    event.preventDefault();
    fillPrModal(button, 'view');
  });

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-edit-pr-modal');

    if (!button) return;

    event.preventDefault();
    fillPrModal(button, 'edit');
  });

  if (closeEditPrModal && editPrModal) {
    closeEditPrModal.addEventListener('click', function () {
      closeModal(editPrModal);
    });
  }

  if (cancelEditPrModal && editPrModal) {
    cancelEditPrModal.addEventListener('click', function () {
      closeModal(editPrModal);
    });
  }

  if (closeViewOnlyPr && editPrModal) {
    closeViewOnlyPr.addEventListener('click', function () {
      closeModal(editPrModal);
    });
  }

  const deletePrModal = document.getElementById('deletePrModal');
  const deletePrNo = document.getElementById('deletePrNo');
  const cancelDeletePr = document.getElementById('cancelDeletePr');
  const confirmDeletePr = document.getElementById('confirmDeletePr');

  let selectedDeleteForm = null;

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-delete-pr-modal');

    if (!button) return;

    event.preventDefault();

    const id = button.dataset.id;

    selectedDeleteForm = document.getElementById('deletePrForm-' + id);

    if (deletePrNo) {
      deletePrNo.textContent = button.dataset.prNo || 'this purchase request';
    }

    openModal(deletePrModal);
  });

  if (cancelDeletePr && deletePrModal) {
    cancelDeletePr.addEventListener('click', function () {
      selectedDeleteForm = null;
      closeModal(deletePrModal);
    });
  }

  if (confirmDeletePr) {
    confirmDeletePr.addEventListener('click', function () {
      if (selectedDeleteForm) {
        confirmDeletePr.disabled = true;
        confirmDeletePr.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        selectedDeleteForm.submit();
      }
    });
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest(
      'button, [data-close-modal], [data-close-feedback]'
    );

    if (!button) return;

    const buttonText = button.textContent.trim().toLowerCase();

    const isCloseButton =
      buttonText === 'okay' ||
      buttonText === 'ok' ||
      buttonText === 'close' ||
      button.classList.contains('success-ok-btn') ||
      button.classList.contains('error-ok-btn') ||
      button.classList.contains('feedback-ok-btn') ||
      button.classList.contains('btn-ok') ||
      button.hasAttribute('data-close-modal') ||
      button.hasAttribute('data-close-feedback');

    if (!isCloseButton) return;

    const modal =
      button.closest('.modal-overlay') ||
      button.closest('.delete-modal-overlay') ||
      button.closest('.success-modal-overlay') ||
      button.closest('.error-modal-overlay') ||
      button.closest('.feedback-modal-overlay') ||
      button.closest('.action-modal-overlay') ||
      button.closest('[class*="modal-overlay"]');

    closeModal(modal);
  });

  document
    .querySelectorAll(
      '.modal-overlay, .delete-modal-overlay, .success-modal-overlay, .error-modal-overlay, .feedback-modal-overlay, .action-modal-overlay'
    )
    .forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});