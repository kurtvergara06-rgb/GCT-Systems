document.addEventListener('DOMContentLoaded', () => {
  /*
  |--------------------------------------------------------------------------
  | Feedback Modal
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.close-feedback-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.success-modal-overlay');

      if (modal) {
        modal.classList.remove('show');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | Create PR Modal
  |--------------------------------------------------------------------------
  */
  const prModal = document.getElementById('prModal');
  const openPrModal = document.getElementById('openPrModal');
  const closePrModal = document.getElementById('closePrModal');
  const cancelPrModal = document.getElementById('cancelPrModal');

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

  if (openPrModal) {
    openPrModal.addEventListener('click', () => openModal(prModal));
  }

  if (closePrModal) {
    closePrModal.addEventListener('click', () => closeModal(prModal));
  }

  if (cancelPrModal) {
    cancelPrModal.addEventListener('click', () => closeModal(prModal));
  }

  /*
  |--------------------------------------------------------------------------
  | Auto-fill Bus Number in Create PR
  |--------------------------------------------------------------------------
  */
  const jobOrderSelect = document.getElementById('jobOrderSelect');
  const busNoInput = document.getElementById('busNoInput');

  if (jobOrderSelect && busNoInput) {
    jobOrderSelect.addEventListener('change', () => {
      const selectedOption = jobOrderSelect.options[jobOrderSelect.selectedIndex];
      busNoInput.value = selectedOption.dataset.bus || '';
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Edit PR Modal
  |--------------------------------------------------------------------------
  */
  const editPrModal = document.getElementById('editPrModal');
  const closeEditPrModal = document.getElementById('closeEditPrModal');
  const cancelEditPrModal = document.getElementById('cancelEditPrModal');

  const editPrForm = document.getElementById('editPrForm');
  const editPrNo = document.getElementById('edit_pr_no');
  const editJobOrderNo = document.getElementById('edit_job_order_no');
  const editBusNo = document.getElementById('edit_bus_no');
  const editStatusDisplay = document.getElementById('edit_status_display');
  const editItem = document.getElementById('edit_item');
  const editQuantity = document.getElementById('edit_quantity');
  const editRemarks = document.getElementById('edit_remarks');

  const saveDraftEditBtn = document.getElementById('saveDraftEditBtn');
  const submitEditBtn = document.getElementById('submitEditBtn');

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

  function hideWorkflowActions() {
    if (prApprovalActions) prApprovalActions.style.display = 'none';
    if (warehouseActions) warehouseActions.style.display = 'none';
    if (purchaseActions) purchaseActions.style.display = 'none';
  }

  function enableEditFields() {
    if (editPrNo) editPrNo.disabled = false;
    if (editJobOrderNo) editJobOrderNo.disabled = false;
    if (editItem) editItem.disabled = false;
    if (editQuantity) editQuantity.disabled = false;
    if (editRemarks) editRemarks.disabled = false;
    if (saveDraftEditBtn) saveDraftEditBtn.style.display = 'inline-flex';
    if (submitEditBtn) submitEditBtn.style.display = 'inline-flex';
  }

  function disableEditFields() {
    if (editPrNo) editPrNo.disabled = true;
    if (editJobOrderNo) editJobOrderNo.disabled = true;
    if (editItem) editItem.disabled = true;
    if (editQuantity) editQuantity.disabled = true;
    if (editRemarks) editRemarks.disabled = true;
    if (saveDraftEditBtn) saveDraftEditBtn.style.display = 'none';
    if (submitEditBtn) submitEditBtn.style.display = 'none';
  }

  function setFormAction(form, url) {
    if (form && url) {
      form.action = url;
    }
  }

  document.querySelectorAll('.open-edit-pr-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const status = button.dataset.status;

      if (editPrForm) {
        editPrForm.action = button.dataset.updateUrl;
      }

      if (editPrNo) editPrNo.value = button.dataset.prNo || '';
      if (editJobOrderNo) editJobOrderNo.value = button.dataset.jobOrderNo || '';
      if (editBusNo) editBusNo.value = button.dataset.busNo || '';
      if (editStatusDisplay) editStatusDisplay.value = status || '';
      if (editItem) editItem.value = button.dataset.item || '';
      if (editQuantity) editQuantity.value = button.dataset.quantity || '';
      if (editRemarks) editRemarks.value = button.dataset.remarks || '';

      setFormAction(approvePrForm, button.dataset.approveUrl);
      setFormAction(rejectPrForm, button.dataset.rejectUrl);
      setFormAction(issuePrForm, button.dataset.issueUrl);
      setFormAction(forPurchasePrForm, button.dataset.forPurchaseUrl);
      setFormAction(pendingPurchasePrForm, button.dataset.pendingPurchaseUrl);
      setFormAction(deliveringPrForm, button.dataset.deliveringUrl);
      setFormAction(deliveredPrForm, button.dataset.deliveredUrl);

      hideWorkflowActions();

      if (status === 'Draft') {
        enableEditFields();
        if (submitEditBtn) submitEditBtn.style.display = 'inline-flex';
      } else if (status === 'Submitted') {
        disableEditFields();
        if (prApprovalActions) prApprovalActions.style.display = 'flex';
      } else if (status === 'Approved') {
        disableEditFields();
        if (warehouseActions) warehouseActions.style.display = 'flex';
      } else if (status === 'For Purchase') {
        disableEditFields();
        if (purchaseActions) {
          purchaseActions.style.display = 'flex';
          if (pendingPurchasePrForm) pendingPurchasePrForm.style.display = 'inline-flex';
          if (deliveringPrForm) deliveringPrForm.style.display = 'none';
          if (deliveredPrForm) deliveredPrForm.style.display = 'none';
        }
      } else if (status === 'Pending Purchase') {
        disableEditFields();
        if (purchaseActions) {
          purchaseActions.style.display = 'flex';
          if (pendingPurchasePrForm) pendingPurchasePrForm.style.display = 'none';
          if (deliveringPrForm) deliveringPrForm.style.display = 'inline-flex';
          if (deliveredPrForm) deliveredPrForm.style.display = 'none';
        }
      } else if (status === 'Delivering') {
        disableEditFields();
        if (purchaseActions) {
          purchaseActions.style.display = 'flex';
          if (pendingPurchasePrForm) pendingPurchasePrForm.style.display = 'none';
          if (deliveringPrForm) deliveringPrForm.style.display = 'none';
          if (deliveredPrForm) deliveredPrForm.style.display = 'inline-flex';
        }
      } else {
        disableEditFields();
      }

      openModal(editPrModal);
    });
  });

  if (closeEditPrModal) {
    closeEditPrModal.addEventListener('click', () => closeModal(editPrModal));
  }

  if (cancelEditPrModal) {
    cancelEditPrModal.addEventListener('click', () => closeModal(editPrModal));
  }

  /*
  |--------------------------------------------------------------------------
  | Auto-fill Bus Number in Edit PR
  |--------------------------------------------------------------------------
  */
  if (editJobOrderNo && editBusNo) {
    editJobOrderNo.addEventListener('change', () => {
      const selectedOption = editJobOrderNo.options[editJobOrderNo.selectedIndex];
      editBusNo.value = selectedOption.dataset.bus || '';
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
      const prId = button.dataset.id;
      const prNo = button.dataset.prNo;

      selectedDeleteForm = document.getElementById(`deletePrForm-${prId}`);

      if (deletePrNo) {
        deletePrNo.textContent = prNo || 'this purchase request';
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
  | Close modal when clicking outside
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.classList.remove('show');
      }
    });
  });
  

});