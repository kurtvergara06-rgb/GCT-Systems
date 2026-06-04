document.addEventListener('DOMContentLoaded', function () {
  const jobModal = document.getElementById('jobModal');
  const editJobModal = document.getElementById('editJobModal');
  const deleteJobModal = document.getElementById('deleteJobModal');
  const successModal = document.getElementById('successModal');

  const openJobModal = document.getElementById('openJobModal');
  const closeJobModal = document.getElementById('closeJobModal');
  const cancelJobModal = document.getElementById('cancelJobModal');

  const closeEditJobModal = document.getElementById('closeEditJobModal');
  const cancelEditJobModal = document.getElementById('cancelEditJobModal');

  const cancelDeleteJob = document.getElementById('cancelDeleteJob');
  const confirmDeleteJob = document.getElementById('confirmDeleteJob');

  const closeSuccessModal = document.getElementById('closeSuccessModal');

  let selectedDeleteForm = null;

  function openModal(modal) {
    if (modal) modal.classList.add('show');
  }

  function closeModal(modal) {
    if (modal) modal.classList.remove('show');
  }

  if (openJobModal) {
    openJobModal.addEventListener('click', () => openModal(jobModal));
  }

  if (closeJobModal) {
    closeJobModal.addEventListener('click', () => closeModal(jobModal));
  }

  if (cancelJobModal) {
    cancelJobModal.addEventListener('click', () => closeModal(jobModal));
  }

  if (closeEditJobModal) {
    closeEditJobModal.addEventListener('click', () => closeModal(editJobModal));
  }

  if (cancelEditJobModal) {
    cancelEditJobModal.addEventListener('click', () => closeModal(editJobModal));
  }

  document.querySelectorAll('.open-edit-modal').forEach(button => {
    button.addEventListener('click', function () {
      const id = this.dataset.id;

      document.getElementById('editJobForm').action = `/job-orders/${id}`;

      document.getElementById('edit_job_order_no').value = this.dataset.jobOrderNo || '';
      document.getElementById('edit_bus_no').value = this.dataset.busNo || '';
      document.getElementById('edit_problem_issue').value = this.dataset.problemIssue || '';
      document.getElementById('edit_maintenance_type').value = this.dataset.maintenanceType || '';
      document.getElementById('edit_assigned_mechanic').value = this.dataset.assignedMechanic || '';
      document.getElementById('edit_start_date').value = this.dataset.startDate || '';
      document.getElementById('edit_completion_date').value = this.dataset.completionDate || '';
      document.getElementById('edit_status').value = this.dataset.status || 'On Hold';

      openModal(editJobModal);
    });
  });

  document.querySelectorAll('.open-delete-modal').forEach(button => {
    button.addEventListener('click', function () {
      const id = this.dataset.id;
      const joNo = this.dataset.joNo;

      selectedDeleteForm = document.getElementById(`deleteForm-${id}`);

      const deleteJoNo = document.getElementById('deleteJoNo');
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

  if (closeSuccessModal) {
    closeSuccessModal.addEventListener('click', () => closeModal(successModal));
  }

  if (successModal) {
    setTimeout(() => {
      closeModal(successModal);
    }, 3000);
  }

  window.addEventListener('click', function (event) {
    if (event.target === jobModal) closeModal(jobModal);
    if (event.target === editJobModal) closeModal(editJobModal);
    if (event.target === deleteJobModal) closeModal(deleteJobModal);
    if (event.target === successModal) closeModal(successModal);
  });
});