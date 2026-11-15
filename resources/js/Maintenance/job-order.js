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

  document.querySelectorAll('.close-feedback-modal, .feedback-ok-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.success-modal-overlay, .delete-modal-overlay, .modal-overlay');

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

  const editJobModal = document.getElementById('editJobModal');
  const editJobForm = document.getElementById('editJobForm');

  const closeEditJobModal = document.getElementById('closeEditJobModal');
  const cancelEditJobModal = document.getElementById('cancelEditJobModal');

  const editJobOrderNo = document.getElementById('edit_job_order_no');
  const editBusNo = document.getElementById('edit_bus_no');
  const editProblemIssue = document.getElementById('edit_problem_issue');
  const editMaintenanceType = document.getElementById('edit_maintenance_type');
  const editStatus = document.getElementById('edit_status');
  const editAssignedMechanic = document.getElementById('edit_assigned_mechanic');

  const editPartsNeededWrapper = document.getElementById('editPartsNeededWrapper');

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
      editPartsNeededWrapper.querySelectorAll('input, button').forEach((element) => {
        element.disabled = isReadonly;
      });
    }

    const editAddPartBtn = document.getElementById('editAddPartBtn');

    if (editAddPartBtn) {
      editAddPartBtn.style.display = isReadonly ? 'none' : 'inline-flex';
    }
  }

  function escapeInputValue(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function parseParts(partNeeded) {
    if (!partNeeded) return [];

    return partNeeded.split(',').map((part) => {
      const cleanPart = part.trim();

      if (cleanPart.includes(' - Qty:')) {
        const pieces = cleanPart.split(' - Qty:');

        return {
          name: pieces[0] ? pieces[0].trim() : '',
          quantity: pieces[1] ? pieces[1].trim() : '',
        };
      }

      return {
        name: cleanPart,
        quantity: '',
      };
    });
  }

  function renderEditParts(partNeeded) {
    if (!editPartsNeededWrapper) return;

    const parts = parseParts(partNeeded);

    editPartsNeededWrapper.innerHTML = '';

    const rows = parts.length > 0 ? parts : [{ name: '', quantity: '' }];

    rows.forEach((part, index) => {
      const row = document.createElement('div');
      row.className = 'part-needed-row';

      row.innerHTML = `
        <input
          type="text"
          name="parts[${index}][name]"
          placeholder="Part name"
          value="${escapeInputValue(part.name)}"
        >

        <input
          type="number"
          name="parts[${index}][quantity]"
          min="1"
          placeholder="Quantity"
          value="${escapeInputValue(part.quantity)}"
        >

        <button type="button" class="remove-part-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      `;

      editPartsNeededWrapper.appendChild(row);
    });

    updateEditRemoveButtons();
  }

  function updateEditRemoveButtons() {
    if (!editPartsNeededWrapper) return;

    const rows = editPartsNeededWrapper.querySelectorAll('.part-needed-row');

    rows.forEach((row) => {
      const removeButton = row.querySelector('.remove-part-btn');

      if (removeButton) {
        removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
      }
    });
  }

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const status = button.dataset.status || 'On Going';
      const isCompleted = status === 'Completed';

      if (editJobForm) {
        editJobForm.action = `/job-orders/${id}`;
      }

      if (editJobOrderNo) editJobOrderNo.value = button.dataset.jobOrderNo || '';
      if (editBusNo) editBusNo.value = button.dataset.busNo || '';
      if (editProblemIssue) editProblemIssue.value = button.dataset.problemIssue || '';
      if (editMaintenanceType) editMaintenanceType.value = button.dataset.maintenanceType || '';
      if (editStatus) editStatus.value = status;
      if (editAssignedMechanic) editAssignedMechanic.value = button.dataset.assignedMechanic || '';

      renderEditParts(button.dataset.partNeeded || '');
      setEditModalReadonly(isCompleted);

      openModal(editJobModal);
    });
  });

  if (closeEditJobModal) {
    closeEditJobModal.addEventListener('click', () => closeModal(editJobModal));
  }

  if (cancelEditJobModal) {
    cancelEditJobModal.addEventListener('click', () => closeModal(editJobModal));
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

  document.querySelectorAll('.modal-overlay, .delete-modal-overlay, .success-modal-overlay').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.classList.remove('show');
      }
    });
  });

  function setupPartsRepeater(wrapperId, addButtonId) {
    const wrapper = document.getElementById(wrapperId);
    const addButton = document.getElementById(addButtonId);

    if (!wrapper || !addButton) return;

    let partIndex = wrapper.querySelectorAll('.part-needed-row').length;

    function updateRemoveButtons() {
      const rows = wrapper.querySelectorAll('.part-needed-row');

      rows.forEach((row) => {
        const removeButton = row.querySelector('.remove-part-btn');

        if (removeButton) {
          removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
        }
      });
    }

    addButton.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'part-needed-row';

      row.innerHTML = `
        <input
          type="text"
          name="parts[${partIndex}][name]"
          placeholder="Part name"
        >

        <input
          type="number"
          name="parts[${partIndex}][quantity]"
          min="1"
          placeholder="Quantity"
        >

        <button type="button" class="remove-part-btn">
          <i class="fa-solid fa-xmark"></i>
        </button>
      `;

      wrapper.appendChild(row);
      partIndex++;
      updateRemoveButtons();
    });

    wrapper.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-part-btn');

      if (removeButton) {
        removeButton.closest('.part-needed-row').remove();
        updateRemoveButtons();
      }
    });

    updateRemoveButtons();
  }

  setupPartsRepeater('partsNeededWrapper', 'addPartBtn');
  setupPartsRepeater('editPartsNeededWrapper', 'editAddPartBtn');
}); 

/* ========================================
   PART STATUS FILTER COLOR
======================================== */
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

        const value = partStatusFilter.value;

        if (!value) return;

        partStatusFilter.classList.add(slugStatus(value));
    }

    if (partStatusFilter) {
        updatePartStatusFilterColor();

        partStatusFilter.addEventListener('change', function () {
            updatePartStatusFilterColor();
        });
    }
});