document.addEventListener('DOMContentLoaded', () => {
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

  function unitOptions(selectedUnit = '') {
    const units = [
      'pcs',
      'set',
      'liter',
      'gallon',
      'bottle',
      'box',
      'meter',
      'kg',
      'pack',
      'pair',
      'roll',
      'tube',
    ];

    let html = '<option value="">Unit</option>';

    units.forEach((unit) => {
      html += `<option value="${unit}" ${selectedUnit === unit ? 'selected' : ''}>${unit}</option>`;
    });

    return html;
  }

  function escapeInputValue(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function refreshPartIndexes(wrapper) {
    if (!wrapper) return;

    wrapper.querySelectorAll('.part-needed-row').forEach((row, index) => {
      const nameInput = row.querySelector('input[name*="[name]"]');
      const quantityInput = row.querySelector('input[name*="[quantity]"]');
      const unitSelect = row.querySelector('select[name*="[unit]"]');

      if (nameInput) {
        nameInput.name = `parts[${index}][name]`;
      }

      if (quantityInput) {
        quantityInput.name = `parts[${index}][quantity]`;
      }

      if (unitSelect) {
        unitSelect.name = `parts[${index}][unit]`;
      }
    });
  }

  function createPartRow(index, part = {}, isReadonly = false) {
    const row = document.createElement('div');
    row.className = 'part-needed-row';

    row.innerHTML = `
      <input
        type="text"
        name="parts[${index}][name]"
        placeholder="Part name"
        value="${escapeInputValue(part.name || '')}"
        ${isReadonly ? 'disabled' : ''}
      >

      <input
        type="number"
        name="parts[${index}][quantity]"
        min="1"
        placeholder="Qty"
        value="${escapeInputValue(part.quantity || '')}"
        ${isReadonly ? 'disabled' : ''}
      >

      <select name="parts[${index}][unit]" ${isReadonly ? 'disabled' : ''}>
        ${unitOptions(part.unit || '')}
      </select>

      <button type="button" class="remove-part-btn" ${isReadonly ? 'disabled' : ''}>
        <i class="fa-solid fa-xmark"></i>
      </button>
    `;

    return row;
  }

  document.querySelectorAll('.close-feedback-modal, .feedback-ok-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest(
        '.success-modal-overlay, .delete-modal-overlay, .modal-overlay'
      );

      if (modal) {
        modal.classList.remove('show');
      }
    });
  });

  const jobModal = document.getElementById('jobModal');
  const openJobModal = document.getElementById('openJobModal');
  const closeJobModal = document.getElementById('closeJobModal');
  const cancelJobModal = document.getElementById('cancelJobModal');

  if (openJobModal) {
    openJobModal.addEventListener('click', () => openModal(jobModal));
  }

  if (closeJobModal) {
    closeJobModal.addEventListener('click', () => closeModal(jobModal));
  }

  if (cancelJobModal) {
    cancelJobModal.addEventListener('click', () => closeModal(jobModal));
  }

  function setupPartsRepeater(wrapperId, addButtonId) {
    const wrapper = document.getElementById(wrapperId);
    const addButton = document.getElementById(addButtonId);

    if (!wrapper || !addButton) return;

    addButton.addEventListener('click', () => {
      const partIndex = wrapper.querySelectorAll('.part-needed-row').length;
      const row = createPartRow(partIndex);

      wrapper.appendChild(row);
      refreshPartIndexes(wrapper);
    });

    wrapper.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (!removeButton) return;

      const row = removeButton.closest('.part-needed-row');

      if (row) {
        row.remove();
        refreshPartIndexes(wrapper);
      }
    });

    refreshPartIndexes(wrapper);
  }

  setupPartsRepeater('partsNeededWrapper', 'addPartBtn');

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

  const editPartsNeededWrapper = document.getElementById('editPartsNeededWrapper');
  const editJobMainActions = document.getElementById('editJobMainActions');
  const viewOnlyJobActions = document.getElementById('viewOnlyJobActions');
  const editAddPartBtn = document.getElementById('editAddPartBtn');
  const editModalSubtitle = document.getElementById('editModalSubtitle');
  const editModeDescription = document.getElementById('editModeDescription');

  function setEditModalReadonly(isReadonly) {
    const fields = [
      editBusNo,
      editProblemIssue,
      editMaintenanceType,
      editStatus,
      editAssignedMechanic,
    ];

    fields.forEach((field) => {
      if (field) {
        field.disabled = isReadonly;
      }
    });

    if (editPartsNeededWrapper) {
      editPartsNeededWrapper
        .querySelectorAll('input, select, button')
        .forEach((element) => {
          element.disabled = isReadonly;
        });
    }

    if (editAddPartBtn) {
      editAddPartBtn.style.display = isReadonly ? 'none' : 'inline-flex';
    }

    if (editJobMainActions) {
      editJobMainActions.style.display = isReadonly ? 'none' : 'flex';
    }

    if (viewOnlyJobActions) {
      viewOnlyJobActions.style.display = isReadonly ? 'flex' : 'none';
    }

    if (editModalSubtitle) {
      editModalSubtitle.textContent = isReadonly
        ? 'View the selected job order details.'
        : 'Review and update the selected job order.';
    }

    if (editModeDescription) {
      editModeDescription.textContent = isReadonly
        ? 'This job order is view only because it is completed or its purchase request is already approved / being processed.'
        : 'Review and update the selected job order.';
    }
  }

  function parseParts(partNeeded) {
    if (!partNeeded) return [];

    return partNeeded.split(',').map((part) => {
      const cleanPart = part.trim();

      if (cleanPart.includes(' - Qty:')) {
        const pieces = cleanPart.split(' - Qty:');
        const name = pieces[0] ? pieces[0].trim() : '';
        const quantityWithUnit = pieces[1] ? pieces[1].trim() : '';

        const match = quantityWithUnit.match(/^(\d+)\s*(.*)$/);

        return {
          name: name,
          quantity: match ? match[1] : quantityWithUnit,
          unit: match && match[2] ? match[2].trim() : '',
        };
      }

      return {
        name: cleanPart,
        quantity: '',
        unit: '',
      };
    });
  }

  function renderEditParts(partNeeded, isReadonly) {
    if (!editPartsNeededWrapper) return;

    const parts = parseParts(partNeeded);
    const rows = parts.length > 0
      ? parts
      : [{ name: '', quantity: '', unit: '' }];

    editPartsNeededWrapper.innerHTML = '';

    rows.forEach((part, index) => {
      const row = createPartRow(index, part, isReadonly);
      editPartsNeededWrapper.appendChild(row);
    });

    refreshPartIndexes(editPartsNeededWrapper);
  }

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const status = button.dataset.status || 'On Going';

      const isCompleted = status === 'Completed';
      const isViewOnly = button.dataset.viewOnly === '1';
      const shouldBeViewOnly = isCompleted || isViewOnly;

      if (editJobForm) {
        editJobForm.action = `/job-orders/${id}`;
      }

      if (editJobOrderNo) {
        editJobOrderNo.value = button.dataset.jobOrderNo || '';
      }

      if (editBusNo) {
        editBusNo.value = button.dataset.busNo || '';
      }

      if (editProblemIssue) {
        editProblemIssue.value = button.dataset.problemIssue || '';
      }

      if (editMaintenanceType) {
        editMaintenanceType.value = button.dataset.maintenanceType || '';
      }

      if (editStatus) {
        editStatus.value = status;
      }

      if (editAssignedMechanic) {
        editAssignedMechanic.value = button.dataset.assignedMechanic || '';
      }

      renderEditParts(
        button.dataset.partNeeded || '',
        shouldBeViewOnly
      );

      setEditModalReadonly(shouldBeViewOnly);

      openModal(editJobModal);
    });
  });

  if (editAddPartBtn && editPartsNeededWrapper) {
    editAddPartBtn.addEventListener('click', () => {
      const editPartIndex =
        editPartsNeededWrapper.querySelectorAll('.part-needed-row').length;

      const row = createPartRow(editPartIndex);

      editPartsNeededWrapper.appendChild(row);
      refreshPartIndexes(editPartsNeededWrapper);
    });

    editPartsNeededWrapper.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (!removeButton) return;

      const row = removeButton.closest('.part-needed-row');

      if (row) {
        row.remove();
        refreshPartIndexes(editPartsNeededWrapper);
      }
    });
  }

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

  const finishJobModal = document.getElementById('finishJobModal');
  const finishJoNo = document.getElementById('finishJoNo');
  const cancelFinishJob = document.getElementById('cancelFinishJob');
  const confirmFinishJob = document.getElementById('confirmFinishJob');

  let selectedFinishForm = null;

  document.querySelectorAll('.open-finish-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const joNo = button.dataset.joNo;

      selectedFinishForm = document.getElementById(`finishForm-${id}`);

      if (finishJoNo) {
        finishJoNo.textContent = joNo || 'this job order';
      }

      openModal(finishJobModal);
    });
  });

  if (cancelFinishJob) {
    cancelFinishJob.addEventListener('click', () => {
      selectedFinishForm = null;
      closeModal(finishJobModal);
    });
  }

  if (confirmFinishJob) {
    confirmFinishJob.addEventListener('click', () => {
      if (selectedFinishForm) {
        selectedFinishForm.submit();
      }
    });
  }

  document
    .querySelectorAll(
      '.modal-overlay, .delete-modal-overlay, .success-modal-overlay'
    )
    .forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          modal.classList.remove('show');
        }
      });
    });
});

document.addEventListener('DOMContentLoaded', function () {
  const partStatusFilter = document.getElementById('partStatusFilter');

  function slugStatus(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\//g, '-')
      .replace(/\s+/g, '-');
  }

  function updatePartStatusFilterColor() {
    if (!partStatusFilter) return;

    partStatusFilter.classList.remove(
      'not-requested',
      'submitted',
      'approved',
      'rejected',
      'for-purchase',
      'ordered',
      'for-pick-up',
      'for-delivery',
      'delivered',
      'picked-up',
      'issued',
      'no-parts-needed'
    );

    const value = partStatusFilter.value;

    if (!value || value === 'All Part Statuses') return;

    partStatusFilter.classList.add(slugStatus(value));
  }

  if (partStatusFilter) {
    updatePartStatusFilterColor();

    partStatusFilter.addEventListener('change', function () {
      updatePartStatusFilterColor();
    });
  }
});