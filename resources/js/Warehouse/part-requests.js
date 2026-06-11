document.addEventListener('DOMContentLoaded', function () {
  /*
  |--------------------------------------------------------------------------
  | Helpers
  |--------------------------------------------------------------------------
  */
  function openModal(modal) {
    if (modal) {
      modal.classList.add('show');
      modal.style.display = 'flex';
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('show');
      modal.style.display = 'none';
    }
  }

  function setText(id, value, fallback = '—') {
    const element = document.getElementById(id);

    if (element) {
      element.textContent = value || fallback;
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Sidebar Dropdown
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = this.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | View PR Details Modal
  |--------------------------------------------------------------------------
  */
  const viewPrModal = document.getElementById('viewPrModal');
  const closeViewPrModal = document.getElementById('closeViewPrModal');
  const closeViewPrModalBottom = document.getElementById('closeViewPrModalBottom');

  document.querySelectorAll('.open-view-pr-modal').forEach(function (button) {
    button.addEventListener('click', function () {
      setText('view_pr_no', this.dataset.prNo);
      setText('view_job_order_no', this.dataset.jobOrderNo);
      setText('view_bus_no', this.dataset.busNo);
      setText('view_item', this.dataset.item);
      setText('view_quantity', this.dataset.quantity);
      setText('view_status', this.dataset.status);
      setText('view_created', this.dataset.created);
      setText('view_remarks', this.dataset.remarks, 'No remarks');

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

  if (viewPrModal) {
    viewPrModal.addEventListener('click', function (event) {
      if (event.target === viewPrModal) {
        closeModal(viewPrModal);
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Feedback Modal Fallback
  |--------------------------------------------------------------------------
  */
  const feedbackModal = document.getElementById('feedbackModal');
  const closeFeedbackModal = document.getElementById('closeFeedbackModal');
  const closeFeedbackModalBottom = document.getElementById('closeFeedbackModalBottom');

  window.openFeedbackModal = function (data = {}) {
    setText(
      'feedback_reference_no',
      data.referenceNo || data.prNo || data.jobOrderNo
    );

    setText('feedback_status', data.status);
    setText('feedback_message', data.message, 'No feedback available.');
    setText('feedback_remarks', data.remarks);

    openModal(feedbackModal);
  };

  function closeFeedback() {
    closeModal(feedbackModal);
  }

  if (closeFeedbackModal) {
    closeFeedbackModal.addEventListener('click', closeFeedback);
  }

  if (closeFeedbackModalBottom) {
    closeFeedbackModalBottom.addEventListener('click', closeFeedback);
  }

  if (feedbackModal) {
    feedbackModal.addEventListener('click', function (event) {
      if (event.target === feedbackModal) {
        closeFeedback();
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Close Modals With Escape Key
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(viewPrModal);
      closeModal(feedbackModal);
    }
  });

  
});

/* ========================================
   WAREHOUSE STATUS FILTER COLOR
======================================== */
document.addEventListener('DOMContentLoaded', function () {
  const warehouseStatusFilter = document.getElementById('warehouseStatusFilter');

  function slugStatus(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\//g, '-')
      .replace(/\s+/g, '-');
  }

  function updateWarehouseStatusFilterColor() {
    if (!warehouseStatusFilter) {
      return;
    }

    warehouseStatusFilter.classList.remove(
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

    if (warehouseStatusFilter.value && warehouseStatusFilter.value !== 'All Statuses') {
      warehouseStatusFilter.classList.add(slugStatus(warehouseStatusFilter.value));
    }
  }

  updateWarehouseStatusFilterColor();

  if (warehouseStatusFilter) {
    warehouseStatusFilter.addEventListener('change', updateWarehouseStatusFilterColor);
  }
});