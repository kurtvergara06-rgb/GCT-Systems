document.addEventListener('DOMContentLoaded', () => {
  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */
  function openModal(modal) {
    if (modal) {
      modal.classList.add('show');
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('show');
    }
  }

  function parsePartNeeded(partNeeded) {
    if (!partNeeded) {
      return {
        item: '',
        quantity: '',
      };
    }

    const firstPart = partNeeded.split(',')[0].trim();

    if (firstPart.includes(' - Qty:')) {
      const pieces = firstPart.split(' - Qty:');

      return {
        item: pieces[0] ? pieces[0].trim() : '',
        quantity: pieces[1] ? pieces[1].trim() : 1,
      };
    }

    return {
      item: firstPart,
      quantity: 1,
    };
  }

  function slugStatus(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\//g, '-')
      .replace(/\s+/g, '-');
  }

  /*
  |--------------------------------------------------------------------------
  | PR Status Filter Color
  |--------------------------------------------------------------------------
  */
  function updatePrStatusFilterColor() {
    const prStatusFilter = document.getElementById('prStatusFilter');

    if (!prStatusFilter) {
      return;
    }

    prStatusFilter.classList.remove(
      'submitted',
      'approved',
      'rejected',
      'for-purchase',
      'ordered',
      'for-pick-up',
      'for-delivery',
      'delivered',
      'picked-up',
      'issued'
    );

    if (prStatusFilter.value && prStatusFilter.value !== 'All Statuses') {
      prStatusFilter.classList.add(slugStatus(prStatusFilter.value));
    }
  }

  updatePrStatusFilterColor();

  const prStatusFilter = document.getElementById('prStatusFilter');

  if (prStatusFilter) {
    prStatusFilter.addEventListener('change', updatePrStatusFilterColor);
  }

  /*
  |--------------------------------------------------------------------------
  | Validation Error Modal
  |--------------------------------------------------------------------------
  */
  const validationErrorModal = document.getElementById('validationErrorModal');
  const closeValidationErrorModal = document.getElementById('closeValidationErrorModal');

  if (closeValidationErrorModal && validationErrorModal) {
    closeValidationErrorModal.addEventListener('click', () => {
      closeModal(validationErrorModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | New PR Modal
  |--------------------------------------------------------------------------
  */
  const prModal = document.getElementById('prModal');
  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

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

  /*
  |--------------------------------------------------------------------------
  | Auto Fill New PR From Job Order
  |--------------------------------------------------------------------------
  */
  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');
  const partInput = document.getElementById('partInput');
  const quantityInput = document.getElementById('quantityInput');

  if (jobOrderSelect) {
    jobOrderSelect.addEventListener('change', () => {
      const selected = jobOrderSelect.options[jobOrderSelect.selectedIndex];
      const busNo = selected.dataset.bus || '';
      const parts = selected.dataset.parts || '';
      const parsed = parsePartNeeded(parts);

      if (busNoInput) {
        busNoInput.value = busNo;
      }

      if (partInput) {
        partInput.value = parsed.item;
      }

      if (quantityInput) {
        quantityInput.value = parsed.quantity || 1;
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Edit / View PR Modal
  |--------------------------------------------------------------------------
  */
  const editPrModal = document.getElementById('editPrModal');
  const closeEditPrModal = document.getElementById('closeEditPrModal');
  const cancelEditPrModal = document.getElementById('cancelEditPrModal');
  const closeViewOnlyPr = document.getElementById('closeViewOnlyPr');

  const editPrForm = document.getElementById('editPrForm');
  const editPrNo = document.getElementById('edit_pr_no');
  const editJobOrderNo = document.getElementById('edit_job_order_no');
  const editBusNo = document.getElementById('edit_bus_no');
  const editStatusDisplay = document.getElementById('edit_status_display');
  const editItem = document.getElementById('edit_item');
  const editQuantity = document.getElementById('edit_quantity');
  const editRemarks = document.getElementById('edit_remarks');

  const editPrMainActions = document.getElementById('editPrMainActions');
  const viewOnlyActions = document.getElementById('viewOnlyActions');
  const prApprovalActions = document.getElementById('prApprovalActions');
  const warehouseActions = document.getElementById('warehouseActions');

  const approvePrForm = document.getElementById('approvePrForm');
  const rejectPrForm = document.getElementById('rejectPrForm');
  const issuePrForm = document.getElementById('issuePrForm');
  const forPurchasePrForm = document.getElementById('forPurchasePrForm');

  document.querySelectorAll('.open-edit-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const status = button.dataset.status || '';

      if (editPrForm) {
        editPrForm.action = button.dataset.updateUrl || '#';
      }

      if (approvePrForm) {
        approvePrForm.action = button.dataset.approveUrl || '#';
      }

      if (rejectPrForm) {
        rejectPrForm.action = button.dataset.rejectUrl || '#';
      }

      if (issuePrForm) {
        issuePrForm.action = button.dataset.issueUrl || '#';
      }

      if (forPurchasePrForm) {
        forPurchasePrForm.action = button.dataset.forPurchaseUrl || '#';
      }

      if (editPrNo) {
        editPrNo.value = button.dataset.prNo || '';
      }

      if (editBusNo) {
        editBusNo.value = button.dataset.busNo || '';
      }

      if (editStatusDisplay) {
        editStatusDisplay.value = status;
      }

      if (editItem) {
        editItem.value = button.dataset.item || '';
      }

      if (editQuantity) {
        editQuantity.value = button.dataset.quantity || '';
      }

      if (editRemarks) {
        editRemarks.value = button.dataset.remarks || '';
      }

      if (editJobOrderNo) {
        let optionExists = false;

        Array.from(editJobOrderNo.options).forEach((option) => {
          if (option.value === button.dataset.jobOrderNo) {
            optionExists = true;
          }
        });

        if (!optionExists && button.dataset.jobOrderNo) {
          const option = document.createElement('option');
          option.value = button.dataset.jobOrderNo;
          option.textContent = button.dataset.jobOrderNo;
          option.dataset.bus = button.dataset.busNo || '';
          option.dataset.parts = `${button.dataset.item || ''} - Qty: ${button.dataset.quantity || 1}`;
          editJobOrderNo.appendChild(option);
        }

        editJobOrderNo.value = button.dataset.jobOrderNo || '';
      }

      const canEdit = status === 'Submitted';
      const canApproveReject = status === 'Submitted';
      const canWarehouseAct = status === 'Approved';

      if (editPrMainActions) {
        editPrMainActions.style.display = canEdit ? 'flex' : 'none';
      }

      if (viewOnlyActions) {
        viewOnlyActions.style.display = canEdit ? 'none' : 'flex';
      }

      if (prApprovalActions) {
        prApprovalActions.style.display = canApproveReject ? 'flex' : 'none';
      }

      if (warehouseActions) {
        warehouseActions.style.display = canWarehouseAct ? 'flex' : 'none';
      }

      if (editRemarks) {
        editRemarks.readOnly = !canEdit;
      }

      if (editJobOrderNo) {
        editJobOrderNo.disabled = !canEdit;
      }

      openModal(editPrModal);
    });
  });

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

  /*
  |--------------------------------------------------------------------------
  | Auto Fill Edit PR From Job Order
  |--------------------------------------------------------------------------
  */
  if (editJobOrderNo) {
    editJobOrderNo.addEventListener('change', () => {
      const selected = editJobOrderNo.options[editJobOrderNo.selectedIndex];
      const busNo = selected.dataset.bus || '';
      const parts = selected.dataset.parts || '';
      const parsed = parsePartNeeded(parts);

      if (editBusNo) {
        editBusNo.value = busNo;
      }

      if (editItem) {
        editItem.value = parsed.item;
      }

      if (editQuantity) {
        editQuantity.value = parsed.quantity || 1;
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Delete PR Modal
  |--------------------------------------------------------------------------
  */
  const deletePrModal = document.getElementById('deletePrModal');
  const deletePrNo = document.getElementById('deletePrNo');
  const cancelDeletePr = document.getElementById('cancelDeletePr');
  const confirmDeletePr = document.getElementById('confirmDeletePr');

  let selectedDeletePrForm = null;

  document.querySelectorAll('.open-delete-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const prNo = button.dataset.prNo;

      selectedDeletePrForm = document.getElementById(`deletePrForm-${id}`);

      if (deletePrNo) {
        deletePrNo.textContent = prNo || 'this purchase request';
      }

      openModal(deletePrModal);
    });
  });

  if (cancelDeletePr) {
    cancelDeletePr.addEventListener('click', () => {
      selectedDeletePrForm = null;
      closeModal(deletePrModal);
    });
  }

  if (confirmDeletePr) {
    confirmDeletePr.addEventListener('click', () => {
      if (selectedDeletePrForm) {
        selectedDeletePrForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Close Modal By Clicking Outside
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        overlay.classList.remove('show');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | Escape Key Close
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay').forEach((overlay) => {
      overlay.classList.remove('show');
    });
  });
});