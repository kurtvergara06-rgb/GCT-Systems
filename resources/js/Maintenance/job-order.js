document.addEventListener('DOMContentLoaded', function () {
  /*
  |--------------------------------------------------------------------------
  | NEW JOB ORDER MODAL
  |--------------------------------------------------------------------------
  */

  const openJobModal = document.getElementById('openJobModal');
  const closeJobModal = document.getElementById('closeJobModal');
  const cancelJobModal = document.getElementById('cancelJobModal');
  const jobModal = document.getElementById('jobModal');

  if (openJobModal && jobModal) {
    openJobModal.addEventListener('click', function () {
      jobModal.classList.add('show');
    });
  }

  if (closeJobModal && jobModal) {
    closeJobModal.addEventListener('click', function () {
      jobModal.classList.remove('show');
    });
  }

  if (cancelJobModal && jobModal) {
    cancelJobModal.addEventListener('click', function () {
      jobModal.classList.remove('show');
    });
  }

  /*
  |--------------------------------------------------------------------------
  | EDIT / DETAILS JOB ORDER MODAL
  |--------------------------------------------------------------------------
  */

  const editJobModal = document.getElementById('editJobModal');
  const closeEditJobModal = document.getElementById('closeEditJobModal');
  const cancelEditJobModal = document.getElementById('cancelEditJobModal');
  const editJobForm = document.getElementById('editJobForm');

  document.querySelectorAll('.open-edit-modal').forEach(button => {
    button.addEventListener('click', function () {
      const id = this.dataset.id;

      if (!editJobForm || !editJobModal) {
        return;
      }

      editJobForm.action = `/job-orders/${id}`;

      document.getElementById('edit_job_order_no').value = this.dataset.jobOrderNo || '';
      document.getElementById('edit_bus_no').value = this.dataset.busNo || '';
      document.getElementById('edit_service').value = this.dataset.service || '';
      document.getElementById('edit_type').value = this.dataset.type || '';
      document.getElementById('edit_assigned_mechanic').value = this.dataset.assignedMechanic || '';
      document.getElementById('edit_status').value = this.dataset.status || '';
      document.getElementById('edit_start_time').value = this.dataset.startTime || '';
      document.getElementById('edit_end_time').value = this.dataset.endTime || '';
      document.getElementById('edit_date_reported').value = this.dataset.dateReported || '';

      editJobModal.classList.add('show');
    });
  });

  if (closeEditJobModal && editJobModal) {
    closeEditJobModal.addEventListener('click', function () {
      editJobModal.classList.remove('show');
    });
  }

  if (cancelEditJobModal && editJobModal) {
    cancelEditJobModal.addEventListener('click', function () {
      editJobModal.classList.remove('show');
    });
  }

  /*
  |--------------------------------------------------------------------------
  | SUCCESS POPUP - AUTO FADE AFTER 3 SECONDS
  |--------------------------------------------------------------------------
  */

  const successModal = document.getElementById('successModal');
  const closeSuccessModal = document.getElementById('closeSuccessModal');

  if (successModal) {
    setTimeout(function () {
      successModal.classList.remove('show');
    }, 3000);
  }

  if (closeSuccessModal && successModal) {
    closeSuccessModal.addEventListener('click', function () {
      successModal.classList.remove('show');
    });
  }

  /*
  |--------------------------------------------------------------------------
  | DELETE CONFIRMATION POPUP
  |--------------------------------------------------------------------------
  */

  const deleteJobModal = document.getElementById('deleteJobModal');
  const cancelDeleteJob = document.getElementById('cancelDeleteJob');
  const confirmDeleteJob = document.getElementById('confirmDeleteJob');
  const deleteJoNo = document.getElementById('deleteJoNo');

  let selectedDeleteForm = null;

  document.querySelectorAll('.open-delete-modal').forEach(button => {
    button.addEventListener('click', function () {
      const id = this.dataset.id;
      const joNo = this.dataset.joNo;

      selectedDeleteForm = document.getElementById(`deleteForm-${id}`);

      if (deleteJoNo) {
        deleteJoNo.textContent = joNo || 'this job order';
      }

      if (deleteJobModal) {
        deleteJobModal.classList.add('show');
      }
    });
  });

  if (cancelDeleteJob && deleteJobModal) {
    cancelDeleteJob.addEventListener('click', function () {
      deleteJobModal.classList.remove('show');
      selectedDeleteForm = null;
    });
  }

  if (confirmDeleteJob) {
    confirmDeleteJob.addEventListener('click', function () {
      if (selectedDeleteForm) {
        selectedDeleteForm.submit();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | CLOSE WHEN CLICKING OUTSIDE
  |--------------------------------------------------------------------------
  */

  window.addEventListener('click', function (event) {
    if (jobModal && event.target === jobModal) {
      jobModal.classList.remove('show');
    }

    if (editJobModal && event.target === editJobModal) {
      editJobModal.classList.remove('show');
    }

    if (successModal && event.target === successModal) {
      successModal.classList.remove('show');
    }

    if (deleteJobModal && event.target === deleteJobModal) {
      deleteJobModal.classList.remove('show');
      selectedDeleteForm = null;
    }
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE USING ESC KEY
  |--------------------------------------------------------------------------
  */

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (jobModal) jobModal.classList.remove('show');
      if (editJobModal) editJobModal.classList.remove('show');
      if (successModal) successModal.classList.remove('show');

      if (deleteJobModal) {
        deleteJobModal.classList.remove('show');
        selectedDeleteForm = null;
      }
    }
  });
});