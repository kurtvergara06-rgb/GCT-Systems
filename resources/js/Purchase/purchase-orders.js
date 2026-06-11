document.addEventListener('DOMContentLoaded', function () {
  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */
  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove('show');
    modal.style.display = 'none';
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

  function parseJsonScript(id, defaultValue = []) {
    const script = document.getElementById(id);

    if (!script) {
      return defaultValue;
    }

    try {
      return JSON.parse(script.textContent || JSON.stringify(defaultValue));
    } catch (error) {
      return defaultValue;
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Purchase Request Options
  |--------------------------------------------------------------------------
  */
  let purchaseRequestOptions = parseJsonScript('purchaseRequestOptionsJson', []);

  function findPurchaseRequestByPrNo(prNo) {
    return purchaseRequestOptions.find((pr) => {
      return String(pr.pr_no || '') === String(prNo || '');
    });
  }

  function autofillPrDetails(input) {
    const row = input.closest('.po-item-row');

    if (!row) return;

    const selectedPr = findPurchaseRequestByPrNo(input.value);

    const busInput = row.querySelector('.po-bus-no');
    const employeeInput = row.querySelector('.po-employee');
    const itemInput = row.querySelector('.po-item-description');
    const quantityInput = row.querySelector('.po-item-quantity');
    const unitInput = row.querySelector('.po-item-unit');

    if (selectedPr) {
      if (busInput) busInput.value = selectedPr.bus_no || '';
      if (employeeInput) employeeInput.value = selectedPr.employee || selectedPr.job_order_no || '';
      if (itemInput && selectedPr.item) itemInput.value = selectedPr.item || '';
      if (quantityInput && selectedPr.quantity) quantityInput.value = selectedPr.quantity || '';
      if (unitInput) unitInput.value = selectedPr.unit || 'PC';
    } else {
      if (busInput) busInput.value = '';
      if (employeeInput) employeeInput.value = '';
    }
  }

  /*
  |--------------------------------------------------------------------------
  | PO Totals
  |--------------------------------------------------------------------------
  */
  function calculatePoTotals(wrapperId, grossId, deliveryId, discountId, vatId, netId) {
    const wrapper = document.getElementById(wrapperId);
    const grossDisplay = document.getElementById(grossId);
    const deliveryInput = document.getElementById(deliveryId);
    const discountInput = document.getElementById(discountId);
    const vatInput = document.getElementById(vatId);
    const netDisplay = document.getElementById(netId);

    if (!wrapper || !grossDisplay || !deliveryInput || !discountInput || !vatInput || !netDisplay) {
      return;
    }

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

    const deliveryFee = Number(cleanCurrency(deliveryInput.value || 0));
    const discount = Number(cleanCurrency(discountInput.value || 0));
    const vat = Number(cleanCurrency(vatInput.value || 0));

    const net = gross + deliveryFee - discount + vat;

    grossDisplay.value = formatPeso(gross);
    netDisplay.value = formatPeso(net);
  }

  /*
  |--------------------------------------------------------------------------
  | PO Item Repeater
  |--------------------------------------------------------------------------
  */
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
        readonly
      >

      <input
        type="text"
        class="po-employee"
        name="items[${index}][employee]"
        placeholder="Employee"
        value="${escapeHtml(item.employee || item.job_order_no || '')}"
      >

      <input
        type="text"
        class="po-item-description"
        name="items[${index}][item_description]"
        placeholder="Item description"
        value="${escapeHtml(item.item_description || item.item || '')}"
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

      if (row) {
        row.remove();
      }

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

    wrapper.addEventListener(
      'blur',
      function (event) {
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
      },
      true
    );

    wrapper.addEventListener(
      'focus',
      function (event) {
        if (event.target.classList.contains('po-item-cost')) {
          event.target.value = cleanCurrency(event.target.value);
        }
      },
      true
    );

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

  /*
  |--------------------------------------------------------------------------
  | Load Edit Items
  |--------------------------------------------------------------------------
  */
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

  /*
  |--------------------------------------------------------------------------
  | Initialize Repeaters
  |--------------------------------------------------------------------------
  */
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

  /*
  |--------------------------------------------------------------------------
  | New PO Modal
  |--------------------------------------------------------------------------
  */
  const poModal = document.getElementById('poModal');
  const openPoModal = document.getElementById('openPoModal');
  const closePoModal = document.getElementById('closePoModal');
  const cancelPoModal = document.getElementById('cancelPoModal');

  if (openPoModal) {
    openPoModal.addEventListener('click', function () {
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

  /*
  |--------------------------------------------------------------------------
  | Auto Open New PO Modal From Requested Purchase
  |--------------------------------------------------------------------------
  | Blade should provide:
  | <script type="application/json" id="selectedPurchaseRequestJson">...</script>
  | and/or:
  | <input type="hidden" id="purchaseRequestIdInput" name="purchase_request_id">
  |--------------------------------------------------------------------------
  */
  function fillCreatePoFromPurchaseRequest(pr) {
    if (!pr) return;

    const purchaseRequestIdInput =
      document.getElementById('purchaseRequestIdInput') ||
      document.querySelector('input[name="purchase_request_id"]');

    if (purchaseRequestIdInput) {
      purchaseRequestIdInput.value = pr.id || '';
    }

    const wrapper = document.getElementById('poItemsWrapper');

    if (wrapper) {
      wrapper.innerHTML = '';

      wrapper.appendChild(
        createPoItemRow(0, {
          pr_no: pr.pr_no || '',
          bus_no: pr.bus_no || '',
          employee: pr.employee || pr.requested_by || pr.created_by || pr.job_order_no || '',
          job_order_no: pr.job_order_no || '',
          item_description: pr.item || pr.item_description || '',
          quantity: pr.quantity || 1,
          unit: pr.unit || 'PC',
          cost: '',
          amount: 0,
        })
      );

      refreshPoItemNames('poItemsWrapper');
      updateRemoveButtons('poItemsWrapper');
    }

    const purposeInput = document.querySelector('#poModal textarea[name="purpose"]');

    if (purposeInput && !purposeInput.value) {
      purposeInput.value = `Created from ${pr.pr_no || 'purchase request'}`;
    }

    calculatePoTotals(
      'poItemsWrapper',
      'po_gross_display',
      'po_delivery_fee',
      'po_discount',
      'po_vat',
      'po_net_display'
    );
  }

  const selectedPurchaseRequest = parseJsonScript('selectedPurchaseRequestJson', null);
  const openPoModalFlag = document.getElementById('openPoModalFlag');

  if ((openPoModalFlag || selectedPurchaseRequest) && poModal) {
    fillCreatePoFromPurchaseRequest(selectedPurchaseRequest);
    openModal(poModal);
  }

  /*
  |--------------------------------------------------------------------------
  | Edit PO Modal
  |--------------------------------------------------------------------------
  */
  const editPoModal = document.getElementById('editPoModal');
  const editPoForm = document.getElementById('editPoForm');

  document.querySelectorAll('.open-edit-po-modal').forEach((button) => {
    button.addEventListener('click', function () {
      if (editPoForm) {
        editPoForm.action = button.dataset.updateUrl || '#';
      }

      const editPoNo = document.getElementById('edit_po_no');
      const editPoDate = document.getElementById('edit_po_date');
      const editSupplierName = document.getElementById('edit_supplier_name');
      const editSupplierAddressTel = document.getElementById('edit_supplier_address_tel');
      const editTerms = document.getElementById('edit_terms');
      const editTermsOfPayment = document.getElementById('edit_terms_of_payment');
      const editPurpose = document.getElementById('edit_purpose');
      const editStatus = document.getElementById('edit_status');
      const editDeliveryFee = document.getElementById('edit_po_delivery_fee');
      const editDiscount = document.getElementById('edit_po_discount');
      const editVat = document.getElementById('edit_po_vat');

      if (editPoNo) editPoNo.value = button.dataset.poNo || '';
      if (editPoDate) editPoDate.value = button.dataset.poDate || '';
      if (editSupplierName) editSupplierName.value = button.dataset.supplierName || '';
      if (editSupplierAddressTel) editSupplierAddressTel.value = button.dataset.supplierAddressTel || '';
      if (editTerms) editTerms.value = button.dataset.terms || '';
      if (editTermsOfPayment) editTermsOfPayment.value = button.dataset.termsOfPayment || '';
      if (editPurpose) editPurpose.value = button.dataset.purpose || '';
      if (editStatus) editStatus.value = button.dataset.status || '';

      if (editDeliveryFee) editDeliveryFee.value = formatPeso(button.dataset.deliveryFee || 0);
      if (editDiscount) editDiscount.value = formatPeso(button.dataset.discount || 0);
      if (editVat) editVat.value = formatPeso(button.dataset.vat || 0);

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

  if (closeEditPoModal) {
    closeEditPoModal.addEventListener('click', function () {
      closeModal(editPoModal);
    });
  }

  if (cancelEditPoModal) {
    cancelEditPoModal.addEventListener('click', function () {
      closeModal(editPoModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Delete PO Modal
  |--------------------------------------------------------------------------
  */
  const deletePoModal = document.getElementById('deletePoModal');
  const deletePoNo = document.getElementById('deletePoNo');
  const cancelDeletePo = document.getElementById('cancelDeletePo');
  const confirmDeletePo = document.getElementById('confirmDeletePo');

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-po-modal').forEach((button) => {
    button.addEventListener('click', function () {
      selectedDeleteForm = document.getElementById(`deletePoForm-${button.dataset.id}`);

      if (deletePoNo) {
        deletePoNo.textContent = button.dataset.poNo || 'this purchase order';
      }

      openModal(deletePoModal);
    });
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

  /*
  |--------------------------------------------------------------------------
  | Feedback Modal Close
  |--------------------------------------------------------------------------
  */
  document
    .querySelectorAll(
      '.close-feedback-modal, .success-ok-btn, .btn-ok, [data-close-feedback], .success-modal-overlay button'
    )
    .forEach((button) => {
      button.addEventListener('click', function () {
        const modal =
          button.closest('.success-modal-overlay') ||
          button.closest('.delete-modal-overlay') ||
          button.closest('.modal-overlay') ||
          button.closest('[class*="modal-overlay"]');

        closeModal(modal);
      });
    });

  /*
  |--------------------------------------------------------------------------
  | Click Outside Modal Close
  |--------------------------------------------------------------------------
  */
  document
    .querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay')
    .forEach((modal) => {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  /*
  |--------------------------------------------------------------------------
  | Escape Key Close
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(poModal);
      closeModal(editPoModal);
      closeModal(deletePoModal);

      document
        .querySelectorAll('.success-modal-overlay')
        .forEach((modal) => closeModal(modal));
    }
  });
});