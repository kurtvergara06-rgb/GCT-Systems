document.addEventListener('DOMContentLoaded', function () {
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

  function parsePartsNeeded(partNeeded) {
    if (!partNeeded) return [];

    return partNeeded
      .split(',')
      .map((part) => part.trim())
      .filter((part) => part !== '')
      .map((part) => {
        if (part.includes(' - Qty:')) {
          const [name, quantity] = part.split(' - Qty:');

          return {
            name: name ? name.trim() : '',
            quantity: quantity ? quantity.trim() : '',
          };
        }

        return {
          name: part,
          quantity: '',
        };
      });
  }

  function getFirstPart(partNeeded) {
    const parts = parsePartsNeeded(partNeeded);

    if (parts.length === 0) {
      return {
        name: '',
        quantity: '',
      };
    }

    return {
      name: parts[0].name || '',
      quantity: parts[0].quantity || '',
    };
  }

  /*
  |--------------------------------------------------------------------------
  | View Only Mode
  |--------------------------------------------------------------------------
  | Draft / Submitted = editable
  | Approved / Rejected / For Purchase / Pending Purchase / Delivering /
  | Delivered / Issued = view only
  |--------------------------------------------------------------------------
  */
  function setReadonlyMode(isReadonly) {
    const editableFields = [
      'edit_job_order_no',
      'edit_bus_no',
      'edit_item',
      'edit_quantity',
      'edit_remarks',
    ];

    editableFields.forEach((id) => {
      const field = document.getElementById(id);

      if (field) {
        field.disabled = isReadonly;
        field.readOnly = isReadonly;
      }
    });

    const editPrMainActions = document.getElementById('editPrMainActions');
    const viewOnlyActions = document.getElementById('viewOnlyActions');

    if (editPrMainActions) {
      editPrMainActions.style.display = isReadonly ? 'none' : 'flex';
    }

    if (viewOnlyActions) {
      viewOnlyActions.style.display = isReadonly ? 'flex' : 'none';
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Feedback Modal
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.close-feedback-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.success-modal-overlay');
      closeModal(modal);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | New PR Modal
  |--------------------------------------------------------------------------
  */
  const prModal = document.getElementById('prModal');
  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');
  const partInput = document.getElementById('partInput');
  const quantityInput = document.getElementById('quantityInput');

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

  if (jobOrderSelect) {
    jobOrderSelect.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];

      const busNo = selectedOption.dataset.bus || '';
      const partNeeded = selectedOption.dataset.parts || '';
      const firstPart = getFirstPart(partNeeded);

      if (busNoInput) {
        busNoInput.value = busNo;
      }

      if (partInput) {
        partInput.value = firstPart.name;
      }

      if (quantityInput) {
        quantityInput.value = firstPart.quantity;
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Edit / View PR Modal
  |--------------------------------------------------------------------------
  */
  const editPrModal = document.getElementById('editPrModal');
  const editPrForm = document.getElementById('editPrForm');

  const closeEditPrModal = document.getElementById('closeEditPrModal');
  const cancelEditPrModal = document.getElementById('cancelEditPrModal');
  const closeViewOnlyPr = document.getElementById('closeViewOnlyPr');

  const editPrNo = document.getElementById('edit_pr_no');
  const editJobOrderNo = document.getElementById('edit_job_order_no');
  const editBusNo = document.getElementById('edit_bus_no');
  const editStatusDisplay = document.getElementById('edit_status_display');
  const editItem = document.getElementById('edit_item');
  const editQuantity = document.getElementById('edit_quantity');
  const editRemarks = document.getElementById('edit_remarks');

  const approvePrForm = document.getElementById('approvePrForm');
  const rejectPrForm = document.getElementById('rejectPrForm');
  const issuePrForm = document.getElementById('issuePrForm');
  const forPurchasePrForm = document.getElementById('forPurchasePrForm');
  const pendingPurchasePrForm = document.getElementById('pendingPurchasePrForm');
  const deliveringPrForm = document.getElementById('deliveringPrForm');
  const deliveredPrForm = document.getElementById('deliveredPrForm');

  const forPurchaseModal = document.getElementById('forPurchaseModal');
  const openForPurchaseModal = document.getElementById('openForPurchaseModal');
  const forPurchasePrNo = document.getElementById('forPurchasePrNo');
  const cancelForPurchase = document.getElementById('cancelForPurchase');
  const confirmForPurchase = document.getElementById('confirmForPurchase');

  const prApprovalActions = document.getElementById('prApprovalActions');
  const warehouseActions = document.getElementById('warehouseActions');
  const purchaseActions = document.getElementById('purchaseActions');

  document.querySelectorAll('.open-edit-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const status = button.dataset.status || '';

      const isViewOnly =
        status === 'Approved' ||
        status === 'Rejected' ||
        status === 'For Purchase' ||
        status === 'Pending Purchase' ||
        status === 'Delivering' ||
        status === 'Delivered' ||
        status === 'Issued';

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

      if (editItem) {
        editItem.value = button.dataset.item || '';
      }

      if (editQuantity) {
        editQuantity.value = button.dataset.quantity || '';
      }

      if (editRemarks) {
        editRemarks.value = button.dataset.remarks || '';
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

      if (pendingPurchasePrForm) {
        pendingPurchasePrForm.action = button.dataset.pendingPurchaseUrl || '#';
      }

      if (deliveringPrForm) {
        deliveringPrForm.action = button.dataset.deliveringUrl || '#';
      }

      if (deliveredPrForm) {
        deliveredPrForm.action = button.dataset.deliveredUrl || '#';
      }

      /*
      |--------------------------------------------------------------------------
      | Hide edit buttons if approved/processed
      |--------------------------------------------------------------------------
      */
      setReadonlyMode(isViewOnly);

      /*
      |--------------------------------------------------------------------------
      | Approval buttons only show when PR is Submitted
      |--------------------------------------------------------------------------
      */
      if (prApprovalActions) {
        prApprovalActions.style.display = status === 'Submitted' ? 'flex' : 'none';
      }

      /*
      |--------------------------------------------------------------------------
      | Hide warehouse/purchase action buttons on Maintenance PR page
      |--------------------------------------------------------------------------
      */
      if (warehouseActions) {
        warehouseActions.style.display = 'none';
      }

      if (purchaseActions) {
        purchaseActions.style.display = 'none';
      }

      openModal(editPrModal);
    });
  });

  if (editJobOrderNo) {
    editJobOrderNo.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];

      const busNo = selectedOption.dataset.bus || '';
      const partNeeded = selectedOption.dataset.parts || '';
      const firstPart = getFirstPart(partNeeded);

      if (editBusNo) {
        editBusNo.value = busNo;
      }

      if (editItem) {
        editItem.value = firstPart.name;
      }

      if (editQuantity) {
        editQuantity.value = firstPart.quantity;
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

  if (openForPurchaseModal) {
    openForPurchaseModal.addEventListener('click', () => {
      if (forPurchasePrNo) {
        forPurchasePrNo.textContent = editPrNo && editPrNo.value
          ? editPrNo.value
          : 'this purchase request';
      }

      openModal(forPurchaseModal);
    });
  }

  if (cancelForPurchase) {
    cancelForPurchase.addEventListener('click', () => {
      closeModal(forPurchaseModal);
    });
  }

  if (confirmForPurchase) {
    confirmForPurchase.addEventListener('click', () => {
      if (forPurchasePrForm) {
        forPurchasePrForm.submit();
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

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      selectedDeleteForm = document.getElementById(`deletePrForm-${button.dataset.id}`);

      if (deletePrNo) {
        deletePrNo.textContent = button.dataset.prNo || 'this purchase request';
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
    confirmDeletePr.addEventListener('click', () => {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Close When Clicking Outside
  |--------------------------------------------------------------------------
  */
  document
    .querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay')
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
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
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(prModal);
      closeModal(editPrModal);
      closeModal(deletePrModal);
      closeModal(forPurchaseModal);
    }
  });
});
