document.addEventListener('DOMContentLoaded', function () {
  /*
  |--------------------------------------------------------------------------
  | SIDEBAR DROPDOWN
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function () {
      const dropdown = this.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | MODALS
  |--------------------------------------------------------------------------
  */
  const prModal = document.getElementById('prModal');
  const editPrModal = document.getElementById('editPrModal');
  const deletePrModal = document.getElementById('deletePrModal');
  const successModal = document.getElementById('successModal');

  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

  const closeEditPrModal = document.getElementById('closeEditPrModal');
  const cancelEditPrModal = document.getElementById('cancelEditPrModal');

  const cancelDeletePr = document.getElementById('cancelDeletePr');
  const confirmDeletePr = document.getElementById('confirmDeletePr');

  const closeSuccessModal = document.getElementById('closeSuccessModal');

  /*
  |--------------------------------------------------------------------------
  | NEW PR FORM ELEMENTS
  |--------------------------------------------------------------------------
  */
  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');

  /*
  |--------------------------------------------------------------------------
  | EDIT PR FORM ELEMENTS
  |--------------------------------------------------------------------------
  */
  const editPrForm = document.getElementById('editPrForm');

  const editPrNo = document.getElementById('edit_pr_no');
  const editJobOrderSelect = document.getElementById('edit_job_order_no');
  const editBusNoInput = document.getElementById('edit_bus_no');
  const editItem = document.getElementById('edit_item');
  const editQuantity = document.getElementById('edit_quantity');
  const editRemarks = document.getElementById('edit_remarks');
  const editStatusDisplay = document.getElementById('edit_status_display');

  const saveDraftEditBtn = document.getElementById('saveDraftEditBtn');
  const submitEditBtn = document.getElementById('submitEditBtn');

  /*
  |--------------------------------------------------------------------------
  | WORKFLOW ACTION FORMS
  |--------------------------------------------------------------------------
  */
  const prApprovalActions = document.getElementById('prApprovalActions');
  const warehouseActions = document.getElementById('warehouseActions');
  const purchaseActions = document.getElementById('purchaseActions');

  const approvePrForm = document.getElementById('approvePrForm');
  const rejectPrForm = document.getElementById('rejectPrForm');
  const issuePrForm = document.getElementById('issuePrForm');
  const forPurchasePrForm = document.getElementById('forPurchasePrForm');
  const pendingPurchasePrForm = document.getElementById('pendingPurchasePrForm');
  const deliveringPrForm = document.getElementById('deliveringPrForm');
  const deliveredPrForm = document.getElementById('deliveredPrForm');

  let selectedDeleteForm = null;

  /*
  |--------------------------------------------------------------------------
  | TEMP ROLE
  |--------------------------------------------------------------------------
  | Later, replace this with real logged-in role from database/session.
  | Example roles: staff, sub_admin, warehouse, purchase
  */
  const currentRole = 'sub_admin';

  /*
  |--------------------------------------------------------------------------
  | REUSABLE FUNCTIONS
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

  function setFormAction(form, url) {
    if (form) {
      form.action = url || '#';
    }
  }

  function hideWorkflowActions() {
    if (prApprovalActions) prApprovalActions.style.display = 'none';
    if (warehouseActions) warehouseActions.style.display = 'none';
    if (purchaseActions) purchaseActions.style.display = 'none';
  }

  function setEditMode(isEditable, status) {
    const editableFields = [
      editPrNo,
      editJobOrderSelect,
      editItem,
      editQuantity,
      editRemarks
    ];

    editableFields.forEach(field => {
      if (field) {
        field.disabled = !isEditable;
      }
    });

    if (saveDraftEditBtn) {
      saveDraftEditBtn.style.display = isEditable ? 'inline-flex' : 'none';
    }

    if (submitEditBtn) {
      submitEditBtn.style.display =
        isEditable && status === 'Draft' ? 'inline-flex' : 'none';
    }
  }

  /*
  |--------------------------------------------------------------------------
  | NEW PR: AUTO-FILL BUS NUMBER FROM JO
  |--------------------------------------------------------------------------
  */
  if (jobOrderSelect && busNoInput) {
    jobOrderSelect.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];
      const busNo = selectedOption.getAttribute('data-bus') || '';

      busNoInput.value = busNo;
    });
  }

  /*
  |--------------------------------------------------------------------------
  | EDIT PR: AUTO-FILL BUS NUMBER FROM JO
  |--------------------------------------------------------------------------
  */
  if (editJobOrderSelect && editBusNoInput) {
    editJobOrderSelect.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];
      const busNo = selectedOption.getAttribute('data-bus') || '';

      editBusNoInput.value = busNo;
    });
  }

  /*
  |--------------------------------------------------------------------------
  | OPEN / CLOSE NEW PR MODAL
  |--------------------------------------------------------------------------
  */
  if (openPrModal) {
    openPrModal.addEventListener('click', function () {
      openModal(prModal);
    });
  }

  if (closePrModal) {
    closePrModal.addEventListener('click', function () {
      closeModal(prModal);
    });
  }

  if (cancelPrModal) {
    cancelPrModal.addEventListener('click', function () {
      closeModal(prModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | OPEN EDIT / VIEW PR MODAL
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.open-edit-pr-modal').forEach(button => {
    button.addEventListener('click', function () {
      const status = this.dataset.status || 'Draft';

      if (editPrForm) {
        editPrForm.action = this.dataset.updateUrl || '#';
      }

      if (editPrNo) editPrNo.value = this.dataset.prNo || '';
      if (editJobOrderSelect) editJobOrderSelect.value = this.dataset.jobOrderNo || '';
      if (editBusNoInput) editBusNoInput.value = this.dataset.busNo || '';
      if (editItem) editItem.value = this.dataset.item || '';
      if (editQuantity) editQuantity.value = this.dataset.quantity || '';
      if (editRemarks) editRemarks.value = this.dataset.remarks || '';
      if (editStatusDisplay) editStatusDisplay.value = status;

      setFormAction(approvePrForm, this.dataset.approveUrl);
      setFormAction(rejectPrForm, this.dataset.rejectUrl);
      setFormAction(issuePrForm, this.dataset.issueUrl);
      setFormAction(forPurchasePrForm, this.dataset.forPurchaseUrl);
      setFormAction(pendingPurchasePrForm, this.dataset.pendingPurchaseUrl);
      setFormAction(deliveringPrForm, this.dataset.deliveringUrl);
      setFormAction(deliveredPrForm, this.dataset.deliveredUrl);

      hideWorkflowActions();

      /*
      |--------------------------------------------------------------------------
      | EDIT RULES
      |--------------------------------------------------------------------------
      | Rejected = view only, cannot edit
      | Issued = view only, cannot edit
      | Delivered = view only for Maintenance, but Warehouse can issue
      */
      if (status === 'Rejected' || status === 'Issued') {
        setEditMode(false, status);
      } else {
        setEditMode(true, status);
      }

      /*
      |--------------------------------------------------------------------------
      | SUB ADMIN ACTIONS
      |--------------------------------------------------------------------------
      | Submitted PR can be approved/rejected by sub admin.
      */
      if (
        currentRole === 'sub_admin' &&
        status === 'Submitted' &&
        prApprovalActions
      ) {
        prApprovalActions.style.display = 'flex';
      }

      /*
      |--------------------------------------------------------------------------
      | WAREHOUSE ACTIONS
      |--------------------------------------------------------------------------
      | Approved = Warehouse can Issue if stock is available
      | Approved = Warehouse can mark For Purchase if stock is unavailable
      | Delivered = Warehouse can Issue after supplier delivered parts
      */
      if (status === 'Approved' && warehouseActions) {
        warehouseActions.style.display = 'flex';

        if (forPurchasePrForm) {
          forPurchasePrForm.style.display = 'inline-flex';
        }

        if (issuePrForm) {
          issuePrForm.style.display = 'inline-flex';
        }
      }

      if (status === 'Delivered' && warehouseActions) {
        warehouseActions.style.display = 'flex';

        if (forPurchasePrForm) {
          forPurchasePrForm.style.display = 'none';
        }

        if (issuePrForm) {
          issuePrForm.style.display = 'inline-flex';
        }
      }

      /*
      |--------------------------------------------------------------------------
      | PURCHASE DEPARTMENT ACTIONS
      |--------------------------------------------------------------------------
      | For Purchase -> Pending Purchase
      | Pending Purchase -> Delivering
      | Delivering -> Delivered
      */
      if (
        ['For Purchase', 'Pending Purchase', 'Delivering'].includes(status) &&
        purchaseActions
      ) {
        purchaseActions.style.display = 'flex';

        if (pendingPurchasePrForm) {
          pendingPurchasePrForm.style.display =
            status === 'For Purchase' ? 'inline-flex' : 'none';
        }

        if (deliveringPrForm) {
          deliveringPrForm.style.display =
            status === 'Pending Purchase' ? 'inline-flex' : 'none';
        }

        if (deliveredPrForm) {
          deliveredPrForm.style.display =
            status === 'Delivering' ? 'inline-flex' : 'none';
        }
      }

      openModal(editPrModal);
    });
  });

  if (closeEditPrModal) {
    closeEditPrModal.addEventListener('click', function () {
      closeModal(editPrModal);
    });
  }

  if (cancelEditPrModal) {
    cancelEditPrModal.addEventListener('click', function () {
      closeModal(editPrModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | DELETE PR MODAL
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.open-delete-pr-modal').forEach(button => {
    button.addEventListener('click', function () {
      const id = this.dataset.id;
      const prNo = this.dataset.prNo;

      selectedDeleteForm = document.getElementById(`deletePrForm-${id}`);

      const deletePrNo = document.getElementById('deletePrNo');

      if (deletePrNo) {
        deletePrNo.textContent = prNo || 'this purchase request';
      }

      openModal(deletePrModal);
    });
  });

  if (cancelDeletePr) {
    cancelDeletePr.addEventListener('click', function () {
      selectedDeleteForm = null;
      closeModal(deletePrModal);
    });
  }

  if (confirmDeletePr) {
    confirmDeletePr.addEventListener('click', function () {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | SUCCESS / ERROR MODAL
  |--------------------------------------------------------------------------
  */
  if (closeSuccessModal) {
    closeSuccessModal.addEventListener('click', function () {
      closeModal(successModal);
    });
  }

  if (successModal) {
    setTimeout(function () {
      closeModal(successModal);
    }, 3000);
  }

  /*
  |--------------------------------------------------------------------------
  | CLICK OUTSIDE TO CLOSE
  |--------------------------------------------------------------------------
  */
  window.addEventListener('click', function (event) {
    if (event.target === prModal) {
      closeModal(prModal);
    }

    if (event.target === editPrModal) {
      closeModal(editPrModal);
    }

    if (event.target === deletePrModal) {
      closeModal(deletePrModal);
    }

    if (event.target === successModal) {
      closeModal(successModal);
    }
  });

  /*
  |--------------------------------------------------------------------------
  | ESC KEY TO CLOSE
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(prModal);
      closeModal(editPrModal);
      closeModal(deletePrModal);
      closeModal(successModal);
    }
  });
});