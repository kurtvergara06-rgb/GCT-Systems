document.addEventListener('DOMContentLoaded', () => {
  const poModal = document.getElementById('poModal');
  const poForm = document.getElementById('poForm');
  const itemsContainer = document.getElementById('poItemsContainer');
  const openPoModalBtn = document.getElementById('openPoModal');
  const closePoModalBtn = document.getElementById('closePoModal');
  const cancelPoModalBtn = document.getElementById('cancelPoModal');
  const closeViewPoModalBtn = document.getElementById('closeViewPoModal');
  const addPoItemBtn = document.getElementById('addPoItemBtn');
  const poRequestReference = document.getElementById('poRequestReference');

  if (!poModal || !poForm || !itemsContainer) {
    return;
  }

  const money = (value) => {
    const amount = Number.parseFloat(value || 0) || 0;

    return '₱' + amount.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');

  const field = (id) => document.getElementById(id);

  const setValue = (id, value) => {
    const target = field(id);

    if (target) {
      target.value = value ?? '';
    }
  };

  const getValue = (id) => field(id)?.value ?? '';

  const openModal = (modal) => {
    if (!modal) return;

    modal.classList.add('show', 'active');
    modal.style.display = 'flex';
  };

  const closeModal = (modal) => {
    if (!modal) return;

    modal.classList.remove('show', 'active');
    modal.style.display = 'none';
  };

  const syncRequestReference = () => {
    if (!poRequestReference) return;

    const hasRequest = Boolean(String(getValue('main_pr_no')).trim());
    poRequestReference.style.display = hasRequest ? '' : 'none';
  };

  const calculateTotal = () => {
    let total = 0;

    itemsContainer.querySelectorAll('.po-item-row').forEach((row) => {
      const quantity = Number.parseFloat(row.querySelector('.item-qty')?.value || 0) || 0;
      const cost = Number.parseFloat(row.querySelector('.item-cost')?.value || 0) || 0;
      const amount = quantity * cost;
      const amountField = row.querySelector('.item-amount');

      if (amountField) {
        amountField.value = money(amount);
      }

      total += amount;
    });

    setValue('net_amount_display', money(total));
  };

  const syncRequestNoToItems = () => {
    const requestNo = getValue('main_pr_no');

    itemsContainer.querySelectorAll('.item-pr-no').forEach((input) => {
      input.value = requestNo;
    });
  };

  const reindexRows = () => {
    itemsContainer.querySelectorAll('.po-item-row').forEach((row, index) => {
      row.querySelectorAll('[name]').forEach((input) => {
        const name = input.getAttribute('name');

        if (name) {
          input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${index}]`));
        }
      });
    });
  };

  const createItemRow = (item = {}, index = 0) => {
    const requestNo = item.pr_no || getValue('main_pr_no');
    const description = item.item_description || item.item || item.name || '';
    const quantity = item.quantity || item.qty || 1;
    const unit = item.unit || 'PC';
    const cost = item.cost ?? 0;
    const row = document.createElement('div');

    row.className = 'po-item-row';
    row.innerHTML = `
      <input type="hidden" name="items[${index}][pr_no]" class="item-pr-no" value="${escapeHtml(requestNo)}">

      <input
        type="text"
        name="items[${index}][item_description]"
        class="item-description"
        value="${escapeHtml(description)}"
        placeholder="Item description"
        required
      >

      <input
        type="number"
        name="items[${index}][quantity]"
        class="item-qty"
        value="${escapeHtml(quantity)}"
        min="1"
        step="1"
        placeholder="Qty"
        required
      >

      <input
        type="text"
        name="items[${index}][unit]"
        class="item-unit"
        value="${escapeHtml(unit)}"
        placeholder="Unit"
      >

      <input
        type="number"
        name="items[${index}][cost]"
        class="item-cost"
        value="${escapeHtml(cost)}"
        min="0"
        step="0.01"
        placeholder="0.00"
        required
      >

      <input type="text" class="item-amount" value="₱0.00" readonly>

      <button type="button" class="remove-po-item-btn" title="Remove item" aria-label="Remove item">
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    itemsContainer.appendChild(row);

    row.querySelector('.item-qty')?.addEventListener('input', calculateTotal);
    row.querySelector('.item-cost')?.addEventListener('input', calculateTotal);

    row.querySelector('.remove-po-item-btn')?.addEventListener('click', () => {
      const rows = itemsContainer.querySelectorAll('.po-item-row');

      if (rows.length <= 1) {
        row.querySelector('.item-description').value = '';
        row.querySelector('.item-qty').value = '1';
        row.querySelector('.item-unit').value = 'PC';
        row.querySelector('.item-cost').value = '0';
      } else {
        row.remove();
      }

      reindexRows();
      calculateTotal();
    });
  };

  const renderItems = (items = []) => {
    itemsContainer.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
      createItemRow({}, 0);
    } else {
      items.forEach((item, index) => createItemRow(item, index));
    }

    syncRequestNoToItems();
    calculateTotal();
  };

  const setViewMode = (viewOnly) => {
    poForm.querySelectorAll('input, select, textarea').forEach((input) => {
      if (input.type !== 'hidden') {
        input.disabled = viewOnly;
      }
    });

    itemsContainer.querySelectorAll('.remove-po-item-btn').forEach((button) => {
      button.style.display = viewOnly ? 'none' : 'inline-flex';
      button.disabled = viewOnly;
    });

    if (addPoItemBtn) {
      addPoItemBtn.style.display = viewOnly ? 'none' : 'inline-flex';
      addPoItemBtn.disabled = viewOnly;
    }

    const editActions = field('poEditActions');
    const viewActions = field('poViewActions');

    if (editActions) {
      editActions.style.display = viewOnly ? 'none' : 'flex';
    }

    if (viewActions) {
      viewActions.style.display = viewOnly ? 'flex' : 'none';
    }
  };

  const configureCreateForm = () => {
    poForm.action = poForm.dataset.storeUrl || '/purchase-orders';
    poForm.reset();
    poForm.dataset.confirmTitle = 'Create Purchase Order?';
    poForm.dataset.confirmMessage = 'Are you sure you want to create this Purchase Order?';
    poForm.dataset.confirmButton = 'Yes, Create PO';
    poForm.dataset.confirmType = 'create';

    setValue('poFormMethod', 'POST');
    setValue('purchase_request_id', '');
    setValue('supplier_name', 'N/A');
    setValue('po_status', 'Ordered');
    setValue('main_pr_no', '');
    setValue('net_amount_display', money(0));

    const title = field('poModalTitle');
    if (title) {
      title.textContent = 'New Purchase Order';
    }

    renderItems([]);
    syncRequestReference();
    setViewMode(false);
  };

  const configurePrefilledCreateForm = (prefill) => {
    configureCreateForm();

    setValue('purchase_request_id', prefill?.id || '');
    setValue('main_pr_no', prefill?.pr_no || '');
    renderItems(prefill?.items || []);
    syncRequestReference();
  };

  const parseItems = (button) => {
    try {
      return JSON.parse(button.dataset.items || '[]');
    } catch (error) {
      return [];
    }
  };

  const configureExistingForm = (button, mode) => {
    const status = button.dataset.status || 'Ordered';
    const isViewOnly = mode === 'view' || status.toLowerCase() !== 'draft';
    const items = parseItems(button);
    const firstItem = items[0] || {};

    poForm.action = button.dataset.updateUrl || '#';
    poForm.dataset.confirmTitle = 'Save Purchase Order Changes?';
    poForm.dataset.confirmMessage = 'Are you sure you want to save changes to this Purchase Order?';
    poForm.dataset.confirmButton = 'Yes, Save Changes';
    poForm.dataset.confirmType = 'update';

    setValue('poFormMethod', 'PUT');
    setValue('po_no', button.dataset.poNo || '');
    setValue('po_date', button.dataset.poDate || '');
    setValue('supplier_name', button.dataset.supplierName || 'N/A');
    setValue('po_status', status);
    setValue('main_pr_no', firstItem.pr_no || '');

    const title = field('poModalTitle');
    if (title) {
      title.textContent = isViewOnly ? 'Purchase Order Details' : 'Edit Purchase Order';
    }

    renderItems(items);
    syncRequestReference();
    setViewMode(isViewOnly);
  };

  poForm.dataset.storeUrl = poForm.dataset.storeUrl || poForm.action;

  openPoModalBtn?.addEventListener('click', () => {
    configureCreateForm();
    openModal(poModal);
  });

  closePoModalBtn?.addEventListener('click', () => closeModal(poModal));
  cancelPoModalBtn?.addEventListener('click', () => closeModal(poModal));
  closeViewPoModalBtn?.addEventListener('click', () => closeModal(poModal));

  addPoItemBtn?.addEventListener('click', () => {
    createItemRow({}, itemsContainer.querySelectorAll('.po-item-row').length);
    reindexRows();
    syncRequestNoToItems();
    calculateTotal();
  });

  field('main_pr_no')?.addEventListener('input', () => {
    syncRequestNoToItems();
    syncRequestReference();
  });

  document.addEventListener('click', (event) => {
    const editButton = event.target.closest('.open-edit-po-modal');
    const viewButton = event.target.closest('.open-view-po-modal');
    const button = editButton || viewButton;

    if (!button) return;

    event.preventDefault();
    configureExistingForm(button, viewButton ? 'view' : 'edit');
    openModal(poModal);
  });

  /* Controlled PO status workflow */
  const poStatusModal = field('poStatusModal');
  const poStatusForm = field('poStatusForm');
  const poStatusChoiceList = field('poStatusChoiceList');
  const poStatusValue = field('poStatusValue');
  const poStatusCurrentValue = field('poStatusCurrentValue');
  const poStatusModalPoNo = field('poStatusModalPoNo');
  const confirmPoStatusBtn = field('confirmPoStatusBtn');
  const cancelPoStatusModal = field('cancelPoStatusModal');

  const closePoStatusModal = () => {
    if (poStatusForm) {
      poStatusForm.action = '';
      poStatusForm.dataset.confirmMessage = 'Are you sure you want to update this purchase order status?';
    }

    if (poStatusChoiceList) {
      poStatusChoiceList.innerHTML = '';
    }

    if (poStatusValue) {
      poStatusValue.value = '';
    }

    if (confirmPoStatusBtn) {
      confirmPoStatusBtn.disabled = true;
    }

    closeModal(poStatusModal);
  };

  const choosePoStatus = (button, status) => {
    if (!poStatusChoiceList || !poStatusValue || !confirmPoStatusBtn) return;

    poStatusChoiceList.querySelectorAll('.po-status-choice').forEach((choice) => {
      choice.classList.toggle('is-selected', choice === button);
    });

    poStatusValue.value = status;
    confirmPoStatusBtn.disabled = false;

    if (poStatusForm) {
      const poNo = poStatusModalPoNo?.textContent || 'this purchase order';
      poStatusForm.dataset.confirmMessage = `Change ${poNo} status to ${status}?`;
    }
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.open-po-status-modal');

    if (!button) return;

    event.preventDefault();

    let nextStatuses = [];

    try {
      nextStatuses = JSON.parse(button.dataset.nextStatuses || '[]');
    } catch (error) {
      nextStatuses = [];
    }

    if (!poStatusModal || !poStatusForm || !poStatusChoiceList || nextStatuses.length === 0) {
      return;
    }

    poStatusForm.action = button.dataset.statusUrl || '';

    if (poStatusModalPoNo) {
      poStatusModalPoNo.textContent = button.dataset.poNo || 'this purchase order';
    }

    if (poStatusCurrentValue) {
      poStatusCurrentValue.textContent = button.dataset.currentStatus || '—';
    }

    if (poStatusValue) {
      poStatusValue.value = '';
    }

    if (confirmPoStatusBtn) {
      confirmPoStatusBtn.disabled = true;
    }

    poStatusChoiceList.innerHTML = nextStatuses.map((status) => `
      <button type="button" class="po-status-choice" data-next-po-status="${escapeHtml(status)}">
        <span>${escapeHtml(status)}</span>
        <i class="fa-solid fa-arrow-right"></i>
      </button>
    `).join('');

    poStatusChoiceList.querySelectorAll('[data-next-po-status]').forEach((choice) => {
      choice.addEventListener('click', () => {
        choosePoStatus(choice, choice.dataset.nextPoStatus || '');
      });
    });

    openModal(poStatusModal);
  });

  cancelPoStatusModal?.addEventListener('click', closePoStatusModal);

  poStatusModal?.addEventListener('click', (event) => {
    if (event.target === poStatusModal) {
      closePoStatusModal();
    }
  });

  const deletePoModal = field('deletePoModal');
  const deletePoNo = field('deletePoNo');
  const cancelDeletePo = field('cancelDeletePo');
  const confirmDeletePo = field('confirmDeletePo');
  let selectedDeleteForm = null;

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.open-delete-po-modal');

    if (!button) return;

    event.preventDefault();
    selectedDeleteForm = field('deletePoForm-' + button.dataset.id);

    if (deletePoNo) {
      deletePoNo.textContent = button.dataset.poNo || 'this purchase order';
    }

    openModal(deletePoModal);
  });

  cancelDeletePo?.addEventListener('click', () => {
    selectedDeleteForm = null;
    closeModal(deletePoModal);
  });

  confirmDeletePo?.addEventListener('click', () => {
    selectedDeleteForm?.requestSubmit();
  });

  poModal.addEventListener('click', (event) => {
    if (event.target === poModal) {
      closeModal(poModal);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(poModal);
      closePoStatusModal();
      closeModal(deletePoModal);
    }
  });

  if (window.purchaseOrderShouldOpen && window.purchaseOrderPrefill) {
    configurePrefilledCreateForm(window.purchaseOrderPrefill);
    openModal(poModal);
  } else {
    configureCreateForm();
  }
});
