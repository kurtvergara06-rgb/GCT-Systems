document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
  }

  function setField(id, value, fallback = '—') {
    const element = document.getElementById(id);

    if (!element) return;

    const finalValue =
      value === undefined || value === null || value === '' || value === 'null'
        ? fallback
        : value;

    if (
      element.tagName === 'INPUT' ||
      element.tagName === 'TEXTAREA' ||
      element.tagName === 'SELECT'
    ) {
      element.value = finalValue;
    } else {
      element.textContent = finalValue;
    }
  }

  const restockViewModal = document.getElementById('restockViewModal');
  const closeRestockViewModal = document.getElementById('closeRestockViewModal');
  const closeRestockViewModalBottom = document.getElementById('closeRestockViewModalBottom');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-restock-view-modal');

    if (!button) return;

    event.preventDefault();

    setField('viewRestockNo', button.dataset.restockNo);
    setField('viewRestockSource', button.dataset.sourceType);
    setField('viewRestockStatus', button.dataset.status);
    setField('viewRestockCreated', button.dataset.created);
    setField('viewRestockItem', button.dataset.item);
    setField('viewRestockQuantity', button.dataset.quantity);
    setField('viewRestockUnit', button.dataset.unit);
    setField('viewRestockRemarks', button.dataset.remarks, 'No remarks');

    openModal(restockViewModal);
  });

  if (closeRestockViewModal) {
    closeRestockViewModal.addEventListener('click', function () {
      closeModal(restockViewModal);
    });
  }

  if (closeRestockViewModalBottom) {
    closeRestockViewModalBottom.addEventListener('click', function () {
      closeModal(restockViewModal);
    });
  }

  if (restockViewModal) {
    restockViewModal.addEventListener('click', function (event) {
      if (event.target === restockViewModal) {
        closeModal(restockViewModal);
      }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(restockViewModal);
    }
  });
});