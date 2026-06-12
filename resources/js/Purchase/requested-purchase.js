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

  function setText(id, value, fallback = '—') {
    const element = document.getElementById(id);

    if (!element) return;

    if (value === undefined || value === null || value === '' || value === 'null') {
      element.textContent = fallback;
      return;
    }

    element.textContent = value;
  }

  function parseRequestedParts(rawItem, rawQuantity) {
    const itemText = String(rawItem || '').trim();
    const fallbackQuantity = parseInt(rawQuantity || '1', 10) || 1;

    if (!itemText) return [];

    return itemText
      .split(',')
      .map(function (part, index) {
        const cleanPart = part.trim();

        if (!cleanPart) return null;

        /*
          Supported format:
          tire - Qty: 8, oil - Qty: 2
        */
        if (cleanPart.toLowerCase().includes(' - qty:')) {
          const splitParts = cleanPart.split(/ - qty:/i);

          return {
            name: splitParts[0] ? splitParts[0].trim() : '',
            quantity: splitParts[1] ? parseInt(splitParts[1].trim(), 10) || 1 : 1,
          };
        }

        /*
          Fallback format:
          tire, oil
          First item gets main quantity. Other items default to 1.
        */
        return {
          name: cleanPart,
          quantity: index === 0 ? fallbackQuantity : 1,
        };
      })
      .filter(function (part) {
        return part && part.name;
      });
  }

  function renderRequestedParts(rawItem, rawQuantity) {
    const container = document.getElementById('viewRequestedPartsContainer');

    if (!container) return;

    const parts = parseRequestedParts(rawItem, rawQuantity);

    container.innerHTML = '';

    if (parts.length === 0) {
      container.innerHTML = `
        <div class="requested-part-row">
          <input type="text" value="—" readonly>
          <input type="number" value="0" readonly>
        </div>
      `;
      return;
    }

    parts.forEach(function (part) {
      const row = document.createElement('div');
      row.className = 'requested-part-row';

      const itemInput = document.createElement('input');
      itemInput.type = 'text';
      itemInput.value = part.name;
      itemInput.readOnly = true;

      const qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.value = part.quantity;
      qtyInput.readOnly = true;

      row.appendChild(itemInput);
      row.appendChild(qtyInput);

      container.appendChild(row);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Sidebar Dropdown
  |--------------------------------------------------------------------------
  */
  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = button.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | Requested Purchase View Modal
  |--------------------------------------------------------------------------
  */
  const viewRequestedPrModal = document.getElementById('viewRequestedPrModal');
  const closeRequestedPrModal = document.getElementById('closeRequestedPrModal');
  const closeRequestedPrModalBottom = document.getElementById('closeRequestedPrModalBottom');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-requested-pr-modal');

    if (!button) return;

    event.preventDefault();

    setText('viewRequestedPrNo', button.dataset.prNo);
    setText('viewRequestedJoNo', button.dataset.jobOrderNo);
    setText('viewRequestedBusNo', button.dataset.busNo);
    setText('viewRequestedStatus', button.dataset.status);
    setText('viewRequestedCreated', button.dataset.created);
    setText('viewRequestedRemarks', button.dataset.remarks, 'No remarks');

    renderRequestedParts(button.dataset.item, button.dataset.quantity);

    openModal(viewRequestedPrModal);
  });

  if (closeRequestedPrModal) {
    closeRequestedPrModal.addEventListener('click', function () {
      closeModal(viewRequestedPrModal);
    });
  }

  if (closeRequestedPrModalBottom) {
    closeRequestedPrModalBottom.addEventListener('click', function () {
      closeModal(viewRequestedPrModal);
    });
  }

  if (viewRequestedPrModal) {
    viewRequestedPrModal.addEventListener('click', function (event) {
      if (event.target === viewRequestedPrModal) {
        closeModal(viewRequestedPrModal);
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | Success / Error Modal Close
  |--------------------------------------------------------------------------
  */
  document.addEventListener('click', function (event) {
    const button = event.target.closest(
      'button, [data-close-modal], [data-close-feedback]'
    );

    if (!button) return;

    const buttonText = button.textContent.trim().toLowerCase();

    const isCloseButton =
      buttonText === 'okay' ||
      buttonText === 'ok' ||
      buttonText === 'close' ||
      button.classList.contains('success-ok-btn') ||
      button.classList.contains('error-ok-btn') ||
      button.classList.contains('feedback-ok-btn') ||
      button.classList.contains('btn-ok') ||
      button.hasAttribute('data-close-modal') ||
      button.hasAttribute('data-close-feedback');

    if (!isCloseButton) return;

    const modal =
      button.closest('.modal-overlay') ||
      button.closest('.delete-modal-overlay') ||
      button.closest('.success-modal-overlay') ||
      button.closest('.error-modal-overlay') ||
      button.closest('.feedback-modal-overlay') ||
      button.closest('.action-modal-overlay') ||
      button.closest('[class*="modal-overlay"]');

    closeModal(modal);
  });

  /*
  |--------------------------------------------------------------------------
  | Escape Key Close
  |--------------------------------------------------------------------------
  */
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(viewRequestedPrModal);

      document
        .querySelectorAll(
          '.modal-overlay.show, .delete-modal-overlay.show, .success-modal-overlay.show, .error-modal-overlay.show, .feedback-modal-overlay.show, .action-modal-overlay.show'
        )
        .forEach(function (modal) {
          closeModal(modal);
        });
    }
  });
});