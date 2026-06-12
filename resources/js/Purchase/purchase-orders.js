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

  function money(value) {
    const number = parseFloat(value || 0) || 0;

    return '₱' + number.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function setValue(id, value) {
    const input = document.getElementById(id);

    if (!input) return;

    input.value = value ?? '';
  }

  function getValue(id) {
    const input = document.getElementById(id);

    return input ? input.value : '';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function setReadonlyMode(isViewOnly) {
    const poForm = document.getElementById('poForm');
    const editActions = document.getElementById('poEditActions');
    const viewActions = document.getElementById('poViewActions');
    const addBtn = document.getElementById('addPoItemBtn');

    if (!poForm) return;

    poForm.querySelectorAll('input, select, textarea').forEach(function (field) {
      if (field.type === 'hidden') return;

      field.disabled = isViewOnly;
    });

    poForm.querySelectorAll('.remove-po-item-btn').forEach(function (button) {
      button.style.display = isViewOnly ? 'none' : 'inline-flex';
      button.disabled = isViewOnly;
    });

    if (editActions) {
      editActions.style.display = isViewOnly ? 'none' : 'flex';
    }

    if (viewActions) {
      viewActions.style.display = isViewOnly ? 'flex' : 'none';
    }

    if (addBtn) {
      addBtn.style.display = isViewOnly ? 'none' : 'inline-flex';
      addBtn.disabled = isViewOnly;
    }
  }

  function calculateTotals() {
    let gross = 0;

    document.querySelectorAll('.po-item-row').forEach(function (row) {
      const qty = parseFloat(row.querySelector('.item-qty')?.value || 0) || 0;
      const cost = parseFloat(row.querySelector('.item-cost')?.value || 0) || 0;
      const amount = qty * cost;

      const amountInput = row.querySelector('.item-amount');

      if (amountInput) {
        amountInput.value = money(amount);
      }

      gross += amount;
    });

    const deliveryFee = parseFloat(getValue('delivery_fee') || 0) || 0;
    const discount = parseFloat(getValue('discount') || 0) || 0;
    const vat = parseFloat(getValue('vat') || 0) || 0;
    const net = gross + deliveryFee - discount + vat;

    setValue('gross_amount_display', money(gross));
    setValue('net_amount_display', money(net));
  }

  function syncHiddenAutoFields() {
    const prNo = getValue('main_pr_no');
    const busNo = getValue('main_bus_no');
    const employee = getValue('main_employee');

    document.querySelectorAll('.po-item-row').forEach(function (row) {
      const prInput = row.querySelector('.item-pr-no');
      const busInput = row.querySelector('.item-bus-no');
      const employeeInput = row.querySelector('.item-employee');

      if (prInput) prInput.value = prNo;
      if (busInput) busInput.value = busNo;
      if (employeeInput) employeeInput.value = employee;
    });
  }

  function createItemRow(item = {}, index = 0) {
    const container = document.getElementById('poItemsContainer');

    if (!container) return;

    const prNo = item.pr_no || getValue('main_pr_no');
    const busNo = item.bus_no || getValue('main_bus_no');
    const employee = item.employee || getValue('main_employee');
    const description = item.item_description || item.item || item.name || '';
    const quantity = item.quantity || item.qty || 1;
    const unit = item.unit || 'PC';
    const cost = item.cost || 0;

    const row = document.createElement('div');
    row.className = 'po-item-row';

    row.innerHTML = `
      <input type="hidden" name="items[${index}][pr_no]" class="item-pr-no" value="${escapeHtml(prNo)}">
      <input type="hidden" name="items[${index}][bus_no]" class="item-bus-no" value="${escapeHtml(busNo)}">
      <input type="hidden" name="items[${index}][employee]" class="item-employee" value="${escapeHtml(employee)}">

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
        placeholder="₱0.00"
        required
      >

      <input
        type="text"
        class="item-amount"
        value="₱0.00"
        readonly
      >

      <button type="button" class="remove-po-item-btn">
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    container.appendChild(row);

    row.querySelector('.item-qty')?.addEventListener('input', calculateTotals);
    row.querySelector('.item-cost')?.addEventListener('input', calculateTotals);

    row.querySelector('.remove-po-item-btn')?.addEventListener('click', function () {
      row.remove();
      reindexRows();
      calculateTotals();
    });

    calculateTotals();
  }

  function reindexRows() {
    document.querySelectorAll('.po-item-row').forEach(function (row, index) {
      row.querySelectorAll('input').forEach(function (input) {
        const name = input.getAttribute('name');

        if (!name) return;

        input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${index}]`));
      });
    });
  }

  function renderItems(items) {
    const container = document.getElementById('poItemsContainer');

    if (!container) return;

    container.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
      createItemRow({}, 0);
      syncHiddenAutoFields();
      calculateTotals();
      return;
    }

    const first = items[0] || {};

    setValue('main_pr_no', first.pr_no || '');
    setValue('main_bus_no', first.bus_no || '');
    setValue('main_employee', first.employee || '');

    items.forEach(function (item, index) {
      createItemRow(item, index);
    });

    syncHiddenAutoFields();
    calculateTotals();
  }

  function getItemsFromButton(button) {
    try {
      return JSON.parse(button.dataset.items || '[]');
    } catch (error) {
      return [];
    }
  }

  function resetPoFormForCreate() {
    const poForm = document.getElementById('poForm');
    const method = document.getElementById('poFormMethod');
    const title = document.getElementById('poModalTitle');

    if (poForm) {
      poForm.action = poForm.dataset.storeUrl || window.location.href;
      poForm.reset();
    }

    if (method) {
      method.value = 'POST';
    }

    if (title) {
      title.textContent = 'New Purchase Order';
    }

    setValue('po_status', 'Draft');
    setValue('delivery_fee', 0);
    setValue('discount', 0);
    setValue('vat', 0);
    setValue('gross_amount_display', money(0));
    setValue('net_amount_display', money(0));

    renderItems([]);
    setReadonlyMode(false);
  }

  function fillPoForm(button, mode) {
    const poForm = document.getElementById('poForm');
    const method = document.getElementById('poFormMethod');
    const title = document.getElementById('poModalTitle');

    const status = button.dataset.status || 'Draft';
    const isViewOnly = mode === 'view' || status.toLowerCase() !== 'draft';

    if (poForm) {
      poForm.action = button.dataset.updateUrl || '#';
    }

    if (method) {
      method.value = 'PUT';
    }

    if (title) {
      title.textContent = isViewOnly ? 'Purchase Order Details' : 'Edit Purchase Order';
    }

    setValue('po_no', button.dataset.poNo);
    setValue('po_date', button.dataset.poDate);
    setValue('supplier_name', button.dataset.supplierName);
    setValue('supplier_address_tel', button.dataset.supplierAddressTel);
    setValue('terms', button.dataset.terms);
    setValue('terms_of_payment', button.dataset.termsOfPayment);
    setValue('purpose', button.dataset.purpose);
    setValue('po_status', status);
    setValue('delivery_fee', button.dataset.deliveryFee || 0);
    setValue('discount', button.dataset.discount || 0);
    setValue('vat', button.dataset.vat || 0);

    const items = getItemsFromButton(button);

    renderItems(items);
    setReadonlyMode(isViewOnly);
    calculateTotals();
  }

  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = button.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  const poModal = document.getElementById('poModal');
  const poForm = document.getElementById('poForm');
  const openPoModal = document.getElementById('openPoModal');
  const closePoModal = document.getElementById('closePoModal');
  const cancelPoModal = document.getElementById('cancelPoModal');
  const closeViewPoModal = document.getElementById('closeViewPoModal');
  const addPoItemBtn = document.getElementById('addPoItemBtn');

  if (poForm) {
    poForm.dataset.storeUrl = poForm.action;
  }

  if (openPoModal) {
    openPoModal.addEventListener('click', function () {
      resetPoFormForCreate();
      openModal(poModal);
    });
  }

  if (closePoModal) {
    closePoModal.addEventListener('click', function () {
      closeModal(poModal);
    });
  }

  if (cancelPoModal) {
    cancelPoModal.addEventListener('click', function () {
      closeModal(poModal);
    });
  }

  if (closeViewPoModal) {
    closeViewPoModal.addEventListener('click', function () {
      closeModal(poModal);
    });
  }

  if (addPoItemBtn) {
    addPoItemBtn.addEventListener('click', function () {
      const index = document.querySelectorAll('.po-item-row').length;

      createItemRow({}, index);
      syncHiddenAutoFields();
      reindexRows();
      calculateTotals();
    });
  }

  ['main_bus_no', 'main_pr_no', 'main_employee'].forEach(function (id) {
    const field = document.getElementById(id);

    if (field) {
      field.addEventListener('input', syncHiddenAutoFields);
    }
  });

  ['delivery_fee', 'discount', 'vat'].forEach(function (id) {
    const field = document.getElementById(id);

    if (field) {
      field.addEventListener('input', calculateTotals);
    }
  });

  document.addEventListener('click', function (event) {
    const editButton = event.target.closest('.open-edit-po-modal');

    if (!editButton) return;

    event.preventDefault();

    const status = String(editButton.dataset.status || '').toLowerCase();

    if (status !== 'draft') {
      fillPoForm(editButton, 'view');
    } else {
      fillPoForm(editButton, 'edit');
    }

    openModal(poModal);
  });

  document.addEventListener('click', function (event) {
    const viewButton = event.target.closest('.open-view-po-modal');

    if (!viewButton) return;

    event.preventDefault();

    fillPoForm(viewButton, 'view');
    openModal(poModal);
  });

  const deletePoModal = document.getElementById('deletePoModal');
  const deletePoNo = document.getElementById('deletePoNo');
  const cancelDeletePo = document.getElementById('cancelDeletePo');
  const confirmDeletePo = document.getElementById('confirmDeletePo');

  let selectedDeleteForm = null;

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-delete-po-modal');

    if (!button) return;

    event.preventDefault();

    selectedDeleteForm = document.getElementById('deletePoForm-' + button.dataset.id);

    if (deletePoNo) {
      deletePoNo.textContent = button.dataset.poNo || 'this purchase order';
    }

    openModal(deletePoModal);
  });

  if (cancelDeletePo) {
    cancelDeletePo.addEventListener('click', function () {
      selectedDeleteForm = null;
      closeModal(deletePoModal);
    });
  }

  if (confirmDeletePo) {
    confirmDeletePo.addEventListener('click', function () {
      if (selectedDeleteForm) {
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

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(poModal);
      closeModal(deletePoModal);
    }
  });
});