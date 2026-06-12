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

          return {
            name: splitParts[0] ? splitParts[0].trim() : '',
            quantity: splitParts[1] ? parseInt(splitParts[1].trim(), 10) || 1 : 1,
          };
        }

        return {
          name: cleanPart,
          quantity: index === 0 ? fallbackQuantity : 1,
        };
      })
      .filter(function (part) {
        return part && part.name;
      });
  }

  function syncPrHiddenFields() {
    const container = document.getElementById('editPrPartsContainer');
    const hiddenItem = document.getElementById('edit_item');
    const hiddenQuantity = document.getElementById('edit_quantity');

    if (!container) return;

    const rows = container.querySelectorAll('.pr-part-row');
    const items = [];
    let totalQuantity = 0;

    rows.forEach(function (row) {
      const nameInput = row.querySelector('.pr-part-name');
      const qtyInput = row.querySelector('.pr-part-qty');

      const name = nameInput ? nameInput.value.trim() : '';
      const qty = qtyInput ? parseInt(qtyInput.value || '1', 10) || 1 : 1;

      if (name) {
        items.push(name);
        totalQuantity += qty;
      }
    });

    if (hiddenItem) {
      hiddenItem.value = items.join(', ');
    }

    if (hiddenQuantity) {
      hiddenQuantity.value = totalQuantity;
    }
  }

  function renderPrPartsRows(rawItem, rawQuantity, isReadOnly) {
    const container = document.getElementById('editPrPartsContainer');

    if (!container) return;

    const parts = parsePrParts(rawItem, rawQuantity);

    container.innerHTML = '';

    if (parts.length === 0) {
      const row = document.createElement('div');
      row.className = 'pr-part-row';

      row.innerHTML = `
        <input type="text" class="pr-part-name" value="" placeholder="No requested part" readonly>
        <input type="number" class="pr-part-qty" value="1" min="1" readonly>
      `;

      container.appendChild(row);
      syncPrHiddenFields();
      return;
    }

    parts.forEach(function (part) {
      const row = document.createElement('div');
      row.className = 'pr-part-row';

      const nameInput = document.createElement('input');
      nameInput.type = 'text';
      nameInput.className = 'pr-part-name';
      nameInput.value = part.name;
      nameInput.placeholder = 'Part name';
      nameInput.readOnly = isReadOnly;

      const qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.className = 'pr-part-qty';
      qtyInput.min = '1';
      qtyInput.value = part.quantity;
      qtyInput.placeholder = 'Qty';
      qtyInput.readOnly = isReadOnly;

      nameInput.addEventListener('input', syncPrHiddenFields);
      qtyInput.addEventListener('input', syncPrHiddenFields);

      row.appendChild(nameInput);
      row.appendChild(qtyInput);

      container.appendChild(row);
    });

    syncPrHiddenFields();
  }

  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = button.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

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
  const partInput = document.getElementById('partInput');
  const quantityInput = document.getElementById('quantityInput');

  function parseFirstPartNeeded(rawParts) {
    const firstPart = String(rawParts || '').split(',')[0].trim();

    if (!firstPart) {
      return {
        name: '',
        quantity: '1',
      };
    }

    if (firstPart.includes(' - Qty:')) {
      const parts = firstPart.split(' - Qty:');

      return {
        name: parts[0] ? parts[0].trim() : '',
        quantity: parts[1] ? parts[1].trim() : '1',
      };
    }

    return {
      name: firstPart,
      quantity: '1',
    };
  }

  if (jobOrderSelect) {
    jobOrderSelect.addEventListener('change', function () {
      const selected = jobOrderSelect.options[jobOrderSelect.selectedIndex];

      if (!selected) return;

      const parsed = parseFirstPartNeeded(selected.dataset.parts);

      if (busNoInput) {
        busNoInput.value = selected.dataset.bus || '';
      }

      if (partInput) {
        partInput.value = parsed.name || '';
      }

      if (quantityInput) {
        quantityInput.value = parsed.quantity || '1';
      }
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

  function setPrModalMode(mode, status) {
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
      prApprovalActions.style.display = !isView && isSubmitted ? 'flex' : 'none';
    }

    const remarks = document.getElementById('edit_remarks');

    if (remarks) {
      remarks.readOnly = isView;
    }

    document
      .querySelectorAll('#editPrPartsContainer input')
      .forEach(function (input) {
        input.readOnly = isView;
      });
  }

  function fillPrModal(button, mode) {
    const status = button.dataset.status || 'Submitted';

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

    renderPrPartsRows(
      button.dataset.item,
      button.dataset.quantity,
      mode === 'view'
    );

    setPrModalMode(mode, status);

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