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

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  /*
  |--------------------------------------------------------------------------
  | Parts Helpers
  |--------------------------------------------------------------------------
  */
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

  function createPartRow(index, name = '', quantity = '', canRemove = true, disabled = false) {
    const row = document.createElement('div');
    row.className = 'part-needed-row';

    row.innerHTML = `
      <input
        type="text"
        name="parts[${index}][name]"
        value="${escapeHtml(name)}"
        placeholder="Part name"
        ${disabled ? 'disabled' : ''}
      >

      <input
        type="number"
        name="parts[${index}][quantity]"
        value="${escapeHtml(quantity)}"
        min="1"
        placeholder="Quantity"
        ${disabled ? 'disabled' : ''}
      >

      <button
        type="button"
        class="remove-part-btn"
        style="${canRemove && !disabled ? '' : 'display: none;'}"
      >
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    return row;
  }

  function refreshPartRowNames(wrapper) {
    const rows = wrapper.querySelectorAll('.part-needed-row');

    rows.forEach((row, index) => {
      const nameInput = row.querySelector('input[type="text"]');
      const quantityInput = row.querySelector('input[type="number"]');

      if (nameInput) {
        nameInput.name = `parts[${index}][name]`;
      }

      if (quantityInput) {
        quantityInput.name = `parts[${index}][quantity]`;
      }
    });
  }

  function updateRemoveButtons(wrapper) {
    const rows = wrapper.querySelectorAll('.part-needed-row');

    rows.forEach((row) => {
      const removeButton = row.querySelector('.remove-part-btn');

      if (removeButton) {
        removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
      }
    });
  }

  function setupPartsRepeater(wrapperId, addButtonId) {
    const wrapper = document.getElementById(wrapperId);
    const addButton = document.getElementById(addButtonId);

    if (!wrapper || !addButton) return;

    addButton.addEventListener('click', () => {
      const index = wrapper.querySelectorAll('.part-needed-row').length;

      wrapper.appendChild(createPartRow(index, '', '', true, false));
      refreshPartRowNames(wrapper);
      updateRemoveButtons(wrapper);
    });

    wrapper.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (!removeButton) return;

      const row = removeButton.closest('.part-needed-row');

      if (row) {
        row.remove();
      }

      refreshPartRowNames(wrapper);
      updateRemoveButtons(wrapper);
    });

    refreshPartRowNames(wrapper);
    updateRemoveButtons(wrapper);
  }

  function loadEditParts(partNeeded, isViewOnly = false) {
    const wrapper = document.getElementById('editPartsNeededWrapper');

    if (!wrapper) return;

    wrapper.innerHTML = '';

    const parts = parsePartsNeeded(partNeeded);

    if (parts.length === 0) {
      wrapper.appendChild(createPartRow(0, '', '', false, isViewOnly));
      return;
    }

    parts.forEach((part, index) => {
      wrapper.appendChild(
        createPartRow(index, part.name, part.quantity, parts.length > 1, isViewOnly)
      );
    });

    refreshPartRowNames(wrapper);

    if (!isViewOnly) {
      updateRemoveButtons(wrapper);
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Completed JO View Only Mode
  |--------------------------------------------------------------------------
  */
  function setJobReadonlyMode(isViewOnly) {
    const editableFields = [
      'edit_bus_no',
      'edit_problem_issue',
      'edit_maintenance_type',
      'edit_status',
      'edit_assigned_mechanic',
    ];

    editableFields.forEach((id) => {
      const field = document.getElementById(id);

      if (field) {
        field.disabled = isViewOnly;
        field.readOnly = isViewOnly;
      }
    });

    const editAddPartBtn = document.getElementById('editAddPartBtn');
    const editJobMainActions = document.getElementById('editJobMainActions');
    const viewOnlyJobActions = document.getElementById('viewOnlyJobActions');

    if (editAddPartBtn) {
      editAddPartBtn.style.display = isViewOnly ? 'none' : 'inline-flex';
    }

    if (editJobMainActions) {
      editJobMainActions.style.display = isViewOnly ? 'none' : 'flex';
    }

    if (viewOnlyJobActions) {
      viewOnlyJobActions.style.display = isViewOnly ? 'flex' : 'none';
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
  | New JO Modal
  |--------------------------------------------------------------------------
  */
  const jobModal = document.getElementById('jobModal');
  const openJobModal = document.getElementById('openJobModal');
  const closeJobModal = document.getElementById('closeJobModal');
  const cancelJobModal = document.getElementById('cancelJobModal');

  if (openJobModal) {
    openJobModal.addEventListener('click', () => {
      openModal(jobModal);
    });
  }

  if (closeJobModal) {
    closeJobModal.addEventListener('click', () => {
      closeModal(jobModal);
    });
  }

  if (cancelJobModal) {
    cancelJobModal.addEventListener('click', () => {
      closeModal(jobModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Edit / View JO Modal
  |--------------------------------------------------------------------------
  */
  const editJobModal = document.getElementById('editJobModal');
  const editJobForm = document.getElementById('editJobForm');

  const closeEditJobModal = document.getElementById('closeEditJobModal');
  const cancelEditJobModal = document.getElementById('cancelEditJobModal');
  const closeViewOnlyJob = document.getElementById('closeViewOnlyJob');

  const editJobOrderNo = document.getElementById('edit_job_order_no');
  const editBusNo = document.getElementById('edit_bus_no');
  const editProblemIssue = document.getElementById('edit_problem_issue');
  const editMaintenanceType = document.getElementById('edit_maintenance_type');
  const editStatus = document.getElementById('edit_status');
  const editAssignedMechanic = document.getElementById('edit_assigned_mechanic');

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const status = button.dataset.status || '';
      const isViewOnly = status === 'Completed';

      if (editJobForm) {
        editJobForm.action = `/job-orders/${id}`;
      }

      if (editJobOrderNo) editJobOrderNo.value = button.dataset.jobOrderNo || '';
      if (editBusNo) editBusNo.value = button.dataset.busNo || '';
      if (editProblemIssue) editProblemIssue.value = button.dataset.problemIssue || '';
      if (editMaintenanceType) editMaintenanceType.value = button.dataset.maintenanceType || '';
      if (editStatus) editStatus.value = status || 'On Going';
      if (editAssignedMechanic) editAssignedMechanic.value = button.dataset.assignedMechanic || '';

      loadEditParts(button.dataset.partNeeded || '', isViewOnly);
      setJobReadonlyMode(isViewOnly);

      openModal(editJobModal);
    });
  });

  if (closeEditJobModal) {
    closeEditJobModal.addEventListener('click', () => {
      closeModal(editJobModal);
    });
  }

  if (cancelEditJobModal) {
    cancelEditJobModal.addEventListener('click', () => {
      closeModal(editJobModal);
    });
  }

  if (closeViewOnlyJob) {
    closeViewOnlyJob.addEventListener('click', () => {
      closeModal(editJobModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Multiple Parts Needed
  |--------------------------------------------------------------------------
  */
  setupPartsRepeater('partsNeededWrapper', 'addPartBtn');
  setupPartsRepeater('editPartsNeededWrapper', 'editAddPartBtn');

  /*
  |--------------------------------------------------------------------------
  | Delete JO Modal
  |--------------------------------------------------------------------------
  */
  const deleteJobModal = document.getElementById('deleteJobModal');
  const deleteJoNo = document.getElementById('deleteJoNo');
  const cancelDeleteJob = document.getElementById('cancelDeleteJob');
  const confirmDeleteJob = document.getElementById('confirmDeleteJob');

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const joNo = button.dataset.joNo;

      selectedDeleteForm = document.getElementById(`deleteForm-${id}`);

      if (deleteJoNo) {
        deleteJoNo.textContent = joNo || 'this job order';
      }

      openModal(deleteJobModal);
    });
  });

  if (cancelDeleteJob) {
    cancelDeleteJob.addEventListener('click', () => {
      selectedDeleteForm = null;
      closeModal(deleteJobModal);
    });
  }

  if (confirmDeleteJob) {
    confirmDeleteJob.addEventListener('click', () => {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Close Modal When Clicking Outside
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
  | Close Modal With Escape Key
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(jobModal);
      closeModal(editJobModal);
      closeModal(deleteJobModal);

      document.querySelectorAll('.success-modal-overlay.show').forEach((modal) => {
        closeModal(modal);
      });
    }
  });
});