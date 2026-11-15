document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (modal) modal.classList.add('show');
  }

  function closeModal(modal) {
    if (modal) modal.classList.remove('show');
  }

  function cleanCurrency(value) {
    return String(value || '')
      .replace(/[₱,\s]/g, '')
      .replace(/[^\d.]/g, '');
  }

  function formatPeso(value) {
    const cleaned = cleanCurrency(value);
    const number = Number(cleaned);

    if (!cleaned || isNaN(number)) {
      return '₱0.00';
    }

    return `₱${number.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  let purchaseRequestOptions = [];

  const purchaseRequestOptionsJson = document.getElementById('purchaseRequestOptionsJson');

  if (purchaseRequestOptionsJson) {
    try {
      purchaseRequestOptions = JSON.parse(purchaseRequestOptionsJson.textContent || '[]');
    } catch (error) {
      purchaseRequestOptions = [];
    }
  }

  function autofillPrDetails(input) {
    const row = input.closest('.po-item-row');

    if (!row) return;

    const selectedPr = purchaseRequestOptions.find((pr) => {
      return pr.pr_no === input.value;
    });

    const busInput = row.querySelector('.po-bus-no');
    const employeeInput = row.querySelector('.po-employee');

    if (selectedPr) {
      if (busInput) busInput.value = selectedPr.bus_no || '';
      if (employeeInput) employeeInput.value = selectedPr.employee || '';
    } else {
      if (busInput) busInput.value = '';
      if (employeeInput) employeeInput.value = '';
    }
  }

  function calculatePoTotals(wrapperId, grossId, deliveryId, discountId, vatId, netId) {
    const wrapper = document.getElementById(wrapperId);
    const grossDisplay = document.getElementById(grossId);
    const deliveryInput = document.getElementById(deliveryId);
    const discountInput = document.getElementById(discountId);
    const vatInput = document.getElementById(vatId);
    const netDisplay = document.getElementById(netId);

    if (!wrapper || !grossDisplay || !deliveryInput || !discountInput || !vatInput || !netDisplay) return;

    let gross = 0;

    wrapper.querySelectorAll('.po-item-row').forEach((row) => {
      const quantityInput = row.querySelector('.po-item-quantity');
      const costInput = row.querySelector('.po-item-cost');
      const amountInput = row.querySelector('.po-item-amount');

      const quantity = Number(quantityInput?.value || 0);
      const cost = Number(cleanCurrency(costInput?.value || 0));
      const amount = quantity * cost;

      if (amountInput) {
        amountInput.value = formatPeso(amount);
      }

      gross += amount;
    });

    const deliveryFee = Number(cleanCurrency(deliveryInput.value));
    const discount = Number(cleanCurrency(discountInput.value));
    const vat = Number(cleanCurrency(vatInput.value));

    const net = gross + deliveryFee - discount + vat;

    grossDisplay.value = formatPeso(gross);
    netDisplay.value = formatPeso(net);
  }

  function refreshPoItemNames(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    wrapper.querySelectorAll('.po-item-row').forEach((row, index) => {
      const prNo = row.querySelector('.po-pr-no');
      const busNo = row.querySelector('.po-bus-no');
      const employee = row.querySelector('.po-employee');
      const description = row.querySelector('.po-item-description');
      const quantity = row.querySelector('.po-item-quantity');
      const unit = row.querySelector('.po-item-unit');
      const cost = row.querySelector('.po-item-cost');

      if (prNo) prNo.name = `items[${index}][pr_no]`;
      if (busNo) busNo.name = `items[${index}][bus_no]`;
      if (employee) employee.name = `items[${index}][employee]`;
      if (description) description.name = `items[${index}][item_description]`;
      if (quantity) quantity.name = `items[${index}][quantity]`;
      if (unit) unit.name = `items[${index}][unit]`;
      if (cost) cost.name = `items[${index}][cost]`;
    });
  }

  function updateRemoveButtons(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    const rows = wrapper.querySelectorAll('.po-item-row');

    rows.forEach((row) => {
      const removeButton = row.querySelector('.remove-po-item-btn');

      if (removeButton) {
        removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
      }
    });
  }

  function createPoItemRow(index, item = {}) {
    const row = document.createElement('div');
    row.className = 'po-item-row';

    const quantity = item.quantity || '';
    const cost = item.cost || '';

    row.innerHTML = `
      <input
        type="text"
        class="po-pr-no"
        name="items[${index}][pr_no]"
        list="purchaseRequestList"
        placeholder="PR No."
        value="${escapeHtml(item.pr_no || '')}"
      >

      <input
        type="text"
        class="po-bus-no"
        name="items[${index}][bus_no]"
        placeholder="Bus No."
        value="${escapeHtml(item.bus_no || '')}"
      >

      <input
        type="text"
        class="po-employee"
        name="items[${index}][employee]"
        placeholder="Employee"
        value="${escapeHtml(item.employee || '')}"
 
      >

      <input
        type="text"
        class="po-item-description"
        name="items[${index}][item_description]"
        placeholder="Item description"
        value="${escapeHtml(item.item_description || '')}"
        required
      >

      <input
        type="number"
        class="po-item-quantity"
        name="items[${index}][quantity]"
        min="1"
        step="1"
        placeholder="Qty"
        value="${escapeHtml(quantity)}"
        required
      >

      <input
        type="text"
        class="po-item-unit"
        name="items[${index}][unit]"
        placeholder="Unit"
        value="${escapeHtml(item.unit || 'PC')}"
      >

      <input
        type="text"
        class="po-item-cost"
        name="items[${index}][cost]"
        placeholder="₱0.00"
        value="${cost ? formatPeso(cost) : ''}"
        required
      >

      <input
        type="text"
        class="po-item-amount"
        value="${item.amount ? formatPeso(item.amount) : '₱0.00'}"
        readonly
      >

      <button type="button" class="remove-po-item-btn">
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    return row;
  }

  function setupPoRepeater(config) {
    const wrapper = document.getElementById(config.wrapperId);
    const addButton = document.getElementById(config.addButtonId);

    if (!wrapper || !addButton) return;

    addButton.addEventListener('click', function () {
      const index = wrapper.querySelectorAll('.po-item-row').length;

      wrapper.appendChild(createPoItemRow(index));
      refreshPoItemNames(config.wrapperId);
      updateRemoveButtons(config.wrapperId);
      calculatePoTotals(
        config.wrapperId,
        config.grossId,
        config.deliveryId,
        config.discountId,
        config.vatId,
        config.netId
      );
    });

    wrapper.addEventListener('click', function (event) {
      const removeButton = event.target.closest('.remove-po-item-btn');

      if (!removeButton) return;

      const row = removeButton.closest('.po-item-row');

      if (row) row.remove();

      refreshPoItemNames(config.wrapperId);
      updateRemoveButtons(config.wrapperId);
      calculatePoTotals(
        config.wrapperId,
        config.grossId,
        config.deliveryId,
        config.discountId,
        config.vatId,
        config.netId
      );
    });

    wrapper.addEventListener('input', function (event) {
      if (event.target.classList.contains('po-pr-no')) {
        autofillPrDetails(event.target);
      }

      calculatePoTotals(
        config.wrapperId,
        config.grossId,
        config.deliveryId,
        config.discountId,
        config.vatId,
        config.netId
      );
    });

    wrapper.addEventListener('change', function (event) {
      if (event.target.classList.contains('po-pr-no')) {
        autofillPrDetails(event.target);
      }
    });

    wrapper.addEventListener('blur', function (event) {
      if (event.target.classList.contains('po-item-cost')) {
        event.target.value = formatPeso(event.target.value);
      }

      calculatePoTotals(
        config.wrapperId,
        config.grossId,
        config.deliveryId,
        config.discountId,
        config.vatId,
        config.netId
      );
    }, true);

    wrapper.addEventListener('focus', function (event) {
      if (event.target.classList.contains('po-item-cost')) {
        event.target.value = cleanCurrency(event.target.value);
      }
    }, true);

    [config.deliveryId, config.discountId, config.vatId].forEach((id) => {
      const input = document.getElementById(id);

      if (!input) return;

      input.addEventListener('input', function () {
        calculatePoTotals(
          config.wrapperId,
          config.grossId,
          config.deliveryId,
          config.discountId,
          config.vatId,
          config.netId
        );
      });

      input.addEventListener('blur', function () {
        input.value = formatPeso(input.value);

        calculatePoTotals(
          config.wrapperId,
          config.grossId,
          config.deliveryId,
          config.discountId,
          config.vatId,
          config.netId
        );
      });

      input.addEventListener('focus', function () {
        input.value = cleanCurrency(input.value);
      });
    });

    refreshPoItemNames(config.wrapperId);
    updateRemoveButtons(config.wrapperId);
    calculatePoTotals(
      config.wrapperId,
      config.grossId,
      config.deliveryId,
      config.discountId,
      config.vatId,
      config.netId
    );
  }

  function loadEditItems(items) {
    const wrapper = document.getElementById('editPoItemsWrapper');
    if (!wrapper) return;

    wrapper.innerHTML = '';

    if (!items || items.length === 0) {
      wrapper.appendChild(createPoItemRow(0));
    } else {
      items.forEach((item, index) => {
        wrapper.appendChild(createPoItemRow(index, item));
      });
    }

    refreshPoItemNames('editPoItemsWrapper');
    updateRemoveButtons('editPoItemsWrapper');

    calculatePoTotals(
      'editPoItemsWrapper',
      'edit_po_gross_display',
      'edit_po_delivery_fee',
      'edit_po_discount',
      'edit_po_vat',
      'edit_po_net_display'
    );
  }

  setupPoRepeater({
    wrapperId: 'poItemsWrapper',
    addButtonId: 'addPoItemBtn',
    grossId: 'po_gross_display',
    deliveryId: 'po_delivery_fee',
    discountId: 'po_discount',
    vatId: 'po_vat',
    netId: 'po_net_display',
  });

  setupPoRepeater({
    wrapperId: 'editPoItemsWrapper',
    addButtonId: 'editAddPoItemBtn',
    grossId: 'edit_po_gross_display',
    deliveryId: 'edit_po_delivery_fee',
    discountId: 'edit_po_discount',
    vatId: 'edit_po_vat',
    netId: 'edit_po_net_display',
  });

  const poModal = document.getElementById('poModal');
  const openPoModal = document.getElementById('openPoModal');
  const closePoModal = document.getElementById('closePoModal');
  const cancelPoModal = document.getElementById('cancelPoModal');

  if (openPoModal) openPoModal.addEventListener('click', () => openModal(poModal));
  if (closePoModal) closePoModal.addEventListener('click', () => closeModal(poModal));
  if (cancelPoModal) cancelPoModal.addEventListener('click', () => closeModal(poModal));

  const editPoModal = document.getElementById('editPoModal');
  const editPoForm = document.getElementById('editPoForm');

  document.querySelectorAll('.open-edit-po-modal').forEach((button) => {
    button.addEventListener('click', () => {
      if (editPoForm) {
        editPoForm.action = button.dataset.updateUrl || '#';
      }

      document.getElementById('edit_po_no').value = button.dataset.poNo || '';
      document.getElementById('edit_po_date').value = button.dataset.poDate || '';
      document.getElementById('edit_supplier_name').value = button.dataset.supplierName || '';
      document.getElementById('edit_supplier_address_tel').value = button.dataset.supplierAddressTel || '';
      document.getElementById('edit_terms').value = button.dataset.terms || '';
      document.getElementById('edit_terms_of_payment').value = button.dataset.termsOfPayment || '';
      document.getElementById('edit_purpose').value = button.dataset.purpose || '';
      document.getElementById('edit_status').value = button.dataset.status || '';

      document.getElementById('edit_po_delivery_fee').value = formatPeso(button.dataset.deliveryFee || 0);
      document.getElementById('edit_po_discount').value = formatPeso(button.dataset.discount || 0);
      document.getElementById('edit_po_vat').value = formatPeso(button.dataset.vat || 0);

      let items = [];

      try {
        items = JSON.parse(button.dataset.items || '[]');
      } catch (error) {
        items = [];
      }

      loadEditItems(items);
      openModal(editPoModal);
    });
  });

  const closeEditPoModal = document.getElementById('closeEditPoModal');
  const cancelEditPoModal = document.getElementById('cancelEditPoModal');

  if (closeEditPoModal) closeEditPoModal.addEventListener('click', () => closeModal(editPoModal));
  if (cancelEditPoModal) cancelEditPoModal.addEventListener('click', () => closeModal(editPoModal));

  const deletePoModal = document.getElementById('deletePoModal');
  const deletePoNo = document.getElementById('deletePoNo');
  const cancelDeletePo = document.getElementById('cancelDeletePo');
  const confirmDeletePo = document.getElementById('confirmDeletePo');

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-po-modal').forEach((button) => {
    button.addEventListener('click', () => {
      selectedDeleteForm = document.getElementById(`deletePoForm-${button.dataset.id}`);

      if (deletePoNo) {
        deletePoNo.textContent = button.dataset.poNo || 'this purchase order';
      }

      openModal(deletePoModal);
    });
  });

  if (cancelDeletePo) {
    cancelDeletePo.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deletePoModal);
    });
  }

  if (confirmDeletePo) {
    confirmDeletePo.addEventListener('click', () => {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  document
    .querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay')
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  // Close feedback modals when OK button is clicked
  document.querySelectorAll('.close-feedback-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.success-modal-overlay, .delete-modal-overlay');
      if (modal) {
        closeModal(modal);
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(poModal);
      closeModal(editPoModal);
      closeModal(deletePoModal);
    }
  });
});