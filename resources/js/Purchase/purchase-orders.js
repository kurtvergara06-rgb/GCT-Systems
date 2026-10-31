window.GCTPartialNavigation.registerInitializer('purchase-orders', '.purchase-orders-page', () => {
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

  // Render item breakdown above table overflow rather than inside a clipped cell.
  let activeItemsPopover = null;
  let activeItemsTrigger = null;
  const closeItemsPopover = () => {
    activeItemsPopover?.remove();
    activeItemsPopover = null;
    activeItemsTrigger?.setAttribute('aria-expanded', 'false');
    activeItemsTrigger = null;
  };
  const positionItemsPopover = () => {
    if (!activeItemsPopover || !activeItemsTrigger) return;
    const rect = activeItemsTrigger.getBoundingClientRect();
    const width = Math.min(340, window.innerWidth - 24);
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
    const below = window.innerHeight - rect.bottom >= 140;
    activeItemsPopover.style.position = 'fixed';
    activeItemsPopover.style.width = width + 'px';
    activeItemsPopover.style.left = left + 'px';
    activeItemsPopover.style.maxHeight = Math.min(280, window.innerHeight - 24) + 'px';
    if (below) {
      activeItemsPopover.style.top = Math.min(rect.bottom + 6, window.innerHeight - 12) + 'px';
      activeItemsPopover.style.bottom = 'auto';
    } else {
      activeItemsPopover.style.top = 'auto';
      activeItemsPopover.style.bottom = Math.max(12, window.innerHeight - rect.top + 6) + 'px';
    }
  };
  const onItemTrigger = (event) => {
    const trigger = event.target.closest('.po-items-more-trigger');
    if (!trigger) {
      if (activeItemsPopover && !event.target.closest('.po-items-floating-popover')) closeItemsPopover();
      return;
    }
    event.preventDefault();
    if (trigger === activeItemsTrigger) {
      closeItemsPopover();
      return;
    }
    closeItemsPopover();
    const source = trigger.parentElement?.querySelector('.po-items-more-source');
    if (!source) return;
    activeItemsPopover = document.createElement('div');
    activeItemsPopover.className = 'po-items-floating-popover';
    activeItemsPopover.setAttribute('role', 'dialog');
    activeItemsPopover.setAttribute('aria-label', 'Purchase order item details');
    activeItemsPopover.innerHTML = source.innerHTML;
    document.body.appendChild(activeItemsPopover);
    activeItemsTrigger = trigger;
    trigger.setAttribute('aria-expanded', 'true');
    positionItemsPopover();
  };
  const page = document.querySelector('.purchase-orders-page');
  page?.addEventListener('click', onItemTrigger);
  document.addEventListener('click', (event) => {
    if (activeItemsPopover && !event.target.closest('.po-items-floating-popover') && !event.target.closest('.po-items-more-trigger')) closeItemsPopover();
  });
  window.addEventListener('scroll', closeItemsPopover, { passive: true });
  window.addEventListener('resize', closeItemsPopover);
  window.addEventListener('gct:navigation-before', closeItemsPopover);

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

  const setText = (id, value) => {
    const target = field(id);

    if (target) {
      target.textContent = value ?? '';
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
    let subtotal = 0;

    itemsContainer.querySelectorAll('.po-item-row').forEach((row) => {
      const quantity = Number.parseFloat(row.querySelector('.item-qty')?.value || 0) || 0;
      const cost = Number.parseFloat(row.querySelector('.item-cost')?.value || 0) || 0;
      const amount = quantity * cost;
      const amountField = row.querySelector('.item-amount');

      if (amountField) {
        amountField.value = money(amount);
      }

      subtotal += amount;
    });

    setText('po_subtotal_display', money(subtotal));
    const deliveryFee = Number.parseFloat(getValue('delivery_fee')) || 0;
    const discount = Number.parseFloat(getValue('discount')) || 0;
    const vat = Number.parseFloat(getValue('vat')) || 0;
    setValue('net_amount_display', money(Math.max(0, subtotal + deliveryFee + vat - discount)));
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
    const cost = item.cost ?? '';
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
      if (field('purchase_request_id')?.value) return;
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

  // A linked PR determines the line items; Purchasing may edit pricing, not demand.
  const lockLinkedPrItems = () => {
    const linked = Boolean(field('purchase_request_id')?.value);
    itemsContainer.querySelectorAll('.po-item-row').forEach((row) => {
      row.querySelectorAll('.item-description, .item-qty, .item-unit').forEach((input) => {
        input.readOnly = linked;
      });
      const remove = row.querySelector('.remove-po-item-btn');
      if (remove) {
        remove.hidden = linked;
        remove.style.display = linked ? 'none' : '';
        remove.disabled = linked;
      }
    });
    if (addPoItemBtn) {
      addPoItemBtn.hidden = linked;
      addPoItemBtn.style.display = linked ? 'none' : '';
      addPoItemBtn.disabled = linked;
    }
    itemsContainer.classList.toggle('po-items-pr-locked', linked);
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
    setValue('supplier_name', '');
    setValue('po_status', 'For Review');
    setValue('main_pr_no', '');
    setValue('net_amount_display', money(0));

    setText('poModalTitle', 'New Purchase Order');
    setText('poModalSubtitle', 'Create and review a supplier purchase order.');
    setText('poSaveButtonLabel', 'Create Purchase Order');
    setText('poActionHint', 'Supplier details and unit prices are required before submission.');

    renderItems([]);
    syncRequestReference();
    setViewMode(false);
    lockLinkedPrItems();
  };

  const configurePrefilledCreateForm = (prefill) => {
    configureCreateForm();

    setValue('purchase_request_id', prefill?.id || '');
    setValue('main_pr_no', prefill?.pr_no || '');
    renderItems(prefill?.items || []);
    syncRequestReference();
    lockLinkedPrItems();
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
    const isViewOnly = mode === 'view';
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
    setValue('purchase_request_id', button.dataset.purchaseRequestId || '');
    setValue('supplier_name', button.dataset.supplierName || 'N/A');
    setValue('po_status', status);
    setValue('main_pr_no', firstItem.pr_no || '');

    setValue('supplier_address_tel', button.dataset.supplierAddressTel || '');
    setValue('terms', button.dataset.terms || '');
    setValue('terms_of_payment', button.dataset.termsOfPayment || '');
    setValue('purpose', button.dataset.purpose || '');
    setValue('delivery_fee', button.dataset.deliveryFee || '0');
    setValue('discount', button.dataset.discount || '0');
    setValue('vat', button.dataset.vat || '0');

    setText('poModalTitle', isViewOnly ? 'Purchase Order Details' : 'Edit Purchase Order');
    setText('poModalSubtitle', isViewOnly
      ? 'Review the saved supplier purchase order.'
      : 'Update the supplier order before procurement is received.');
    setText('poSaveButtonLabel', 'Save Changes');
    setText('poActionHint', 'Verify supplier, line items, and financial totals before saving.');

    renderItems(items);
    syncRequestReference();
    setViewMode(isViewOnly);
    lockLinkedPrItems();
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
    if (field('purchase_request_id')?.value) return;
    createItemRow({}, itemsContainer.querySelectorAll('.po-item-row').length);
    reindexRows();
    syncRequestNoToItems();
    calculateTotal();
  });

  field('main_pr_no')?.addEventListener('input', () => {
    syncRequestNoToItems();
    syncRequestReference();
  });

  ['delivery_fee', 'discount', 'vat'].forEach((id) => {
    field(id)?.addEventListener('input', calculateTotal);
  });

  document.addEventListener('click', (event) => {
    const editButton = event.target.closest('.open-edit-po-modal');
    const viewButton = event.target.closest('.open-view-po-modal');
    const button = editButton || viewButton;

    if (!button) return;

    event.preventDefault();
    if (viewButton && document.getElementById('poDetailOverlay')) {
      const overlay=document.getElementById('poDetailOverlay');
      const put=(id,value)=>{document.getElementById(id).textContent=value||'—'};
      const cash=(x)=>'₱'+Number(x||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
      put('poDetailNo',button.dataset.poNo);put('poDetailPr',button.dataset.detailPr);
      const rawDate=button.dataset.poDate||'';const parsedDate=/^\d{4}-\d{2}-\d{2}/.test(rawDate)?new Date(rawDate.slice(0,10)+'T12:00:00'):null;
      put('poDetailDate',parsedDate&&!Number.isNaN(parsedDate.getTime())?parsedDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}):rawDate);
      put('poDetailStatus',button.dataset.status);
      put('poDetailSupplier',button.dataset.supplierName);put('poDetailJo',button.dataset.detailJo);
      put('poDetailPurpose',button.dataset.purpose);put('poDetailTotal',cash(button.dataset.detailTotal));
      const isRestock = button.dataset.detailIsRestock === '1';
      const sourceTitle = document.getElementById('poDetailSourceSignatureTitle');
      if (sourceTitle) sourceTitle.textContent = isRestock ? 'Warehouse Head — PR Approval' : 'Maintenance Head — JO Approval';
      const sourceImage = document.getElementById('poDetailJoSignature');
      if (sourceImage) sourceImage.alt = isRestock ? 'Warehouse Head purchase request approval' : 'Maintenance Head job order approval';
      const sourceMissing = document.getElementById('poDetailJoMissing');
      if (sourceMissing) sourceMissing.textContent = isRestock ? 'No approved Warehouse PR signature available' : 'No approved JO signature available';
      put('poDetailJoApprover',button.dataset.detailJoApprover);put('poDetailJoDate',button.dataset.detailJoDate);
      put('poDetailPoApprover',button.dataset.detailPoApprover);put('poDetailPoDate',button.dataset.detailPoDate);
      for(const kind of ['Jo','Po']){
        const img=document.getElementById('poDetail'+kind+'Signature'),missing=document.getElementById('poDetail'+kind+'Missing');
        img.hidden=true;img.removeAttribute('src');missing.hidden=false;
        const url=button.dataset['detail'+kind+'Signature'];
        if(url){img.onload=()=>{img.hidden=false;missing.hidden=true};img.onerror=()=>{img.hidden=true;missing.hidden=false};img.src=url}
      }
      const tbody=document.getElementById('poDetailItems');tbody.replaceChildren();
      parseItems(button).forEach((item,i)=>{
        const tr=document.createElement('tr');
        const qty=Number(item.quantity||0),price=Number(item.cost??item.unit_cost??item.unit_price??0);
        [i+1,item.item_description||item.item||'—',qty,item.unit||'—',cash(price),cash(Number(item.amount??(qty*price)))].forEach(v=>{const td=document.createElement('td');td.textContent=String(v);tr.appendChild(td)});
        tbody.appendChild(tr);
      });
      overlay.style.display='flex';overlay.classList.add('show','active');return;
    }
    configureExistingForm(button, viewButton ? 'view' : 'edit');
    openModal(poModal);
  });

  const detailOverlay=document.getElementById('poDetailOverlay');
  const closePoDetails=()=>{if(detailOverlay){detailOverlay.classList.remove('show','active');detailOverlay.style.display='none'}};
  document.getElementById('poDetailCloseTop')?.addEventListener('click',closePoDetails);
  document.getElementById('poDetailCloseBottom')?.addEventListener('click',closePoDetails);
  /* Controlled PO status workflow */
  const poStatusModal = field('poStatusModal');
  const poStatusForm = field('poStatusForm');
  const poStatusChoiceList = field('poStatusChoiceList');
  const poStatusValue = field('poStatusValue');
  const poStatusCurrentValue = field('poStatusCurrentValue');
  const poStatusModalPoNo = field('poStatusModalPoNo');
  const confirmPoStatusBtn = field('confirmPoStatusBtn');
  const cancelPoStatusModal = field('cancelPoStatusModal');

  const allowedNextStatuses = (currentStatus) => {
    switch (currentStatus) {
      case 'Ordered':
        return ['For Pick-up', 'For Delivery'];
      case 'For Pick-up':
        return ['Picked Up'];
      case 'For Delivery':
        return ['Delivered'];
      default:
        return [];
    }
  };

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
    event.stopPropagation();

    const currentStatus = button.dataset.currentStatus || '';
    const nextStatuses = allowedNextStatuses(currentStatus);

    if (!poStatusModal || !poStatusForm || !poStatusChoiceList || nextStatuses.length === 0) {
      return;
    }

    poStatusForm.action = button.dataset.statusUrl || '';

    if (poStatusModalPoNo) {
      poStatusModalPoNo.textContent = button.dataset.poNo || 'this purchase order';
    }

    if (poStatusCurrentValue) {
      poStatusCurrentValue.textContent = currentStatus || '—';
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
  }, true);

  cancelPoStatusModal?.addEventListener('click', closePoStatusModal);

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
