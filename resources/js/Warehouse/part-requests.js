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
  | VIEW PR DETAILS MODAL
  |--------------------------------------------------------------------------
  */
  const viewPrModal = document.getElementById('viewPrModal');
  const closeViewPrModal = document.getElementById('closeViewPrModal');
  const closeViewPrModalBottom = document.getElementById('closeViewPrModalBottom');

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

  document.querySelectorAll('.open-view-pr-modal').forEach(button => {
    button.addEventListener('click', function () {
      document.getElementById('view_pr_no').textContent = this.dataset.prNo || '—';
      document.getElementById('view_job_order_no').textContent = this.dataset.jobOrderNo || '—';
      document.getElementById('view_bus_no').textContent = this.dataset.busNo || '—';
      document.getElementById('view_item').textContent = this.dataset.item || '—';
      document.getElementById('view_quantity').textContent = this.dataset.quantity || '—';
      document.getElementById('view_status').textContent = this.dataset.status || '—';
      document.getElementById('view_remarks').textContent = this.dataset.remarks || 'No remarks';
      document.getElementById('view_created').textContent = this.dataset.created || '—';

      openModal(viewPrModal);
    });
  });

  if (closeViewPrModal) {
    closeViewPrModal.addEventListener('click', function () {
      closeModal(viewPrModal);
    });
  }

  if (closeViewPrModalBottom) {
    closeViewPrModalBottom.addEventListener('click', function () {
      closeModal(viewPrModal);
    });
  }

  window.addEventListener('click', function (event) {
    if (event.target === viewPrModal) {
      closeModal(viewPrModal);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(viewPrModal);
    }
  });
});