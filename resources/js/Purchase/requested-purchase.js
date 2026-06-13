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

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
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

        if (cleanPart.toLowerCase().includes(' - qty:')) {
          const splitParts = cleanPart.split(/ - qty:/i);
          const quantityWithUnit = String(splitParts[1] || '').trim();

          return {
            name: String(splitParts[0] || '').trim(),
            quantity: quantityWithUnit || '1',
          };
        }

        if (cleanPart.match(/\(\d+\s*[^)]*\)$/)) {
          const match = cleanPart.match(/^(.*?)\s*\((\d+\s*[^)]*)\)$/);

          return {
            name: match ? String(match[1] || '').trim() : cleanPart,
            quantity: match ? String(match[2] || '').trim() : '1',
          };
        }

        return {
          name: cleanPart,
          quantity: index === 0 ? String(fallbackQuantity) : '1',
        };
      })
      .filter(function (part) {
        return part && part.name;
      });
  }

  function renderRequestedPartsFromDataset(button) {
    const container = document.getElementById('viewRequestedPartsContainer');

    if (!container) return;

    let parts = [];

    try {
      parts = JSON.parse(button.dataset.parts || '[]');
    } catch (error) {
      parts = [];
    }

    if (!Array.isArray(parts) || parts.length === 0) {
      parts = parseRequestedParts(button.dataset.item, button.dataset.quantity);
    }

    if (!Array.isArray(parts) || parts.length === 0) {
      container.innerHTML = `
        <div class="requested-pr-breakdown-row">
          <span>No parts found.</span>
          <span>0</span>
        </div>
      `;
      return;
    }

    let html = '';

    parts.forEach(function (part) {
      const name = escapeHtml(part.name || '—');
      const quantity = escapeHtml(
        part.quantity_display ||
        part.needed_display ||
        part.quantity ||
        part.needed ||
        '0'
      );

      html += `
        <div class="requested-pr-breakdown-row">
          <span>${name}</span>
          <span>${quantity}</span>
        </div>
      `;
    });

    container.innerHTML = html;
  }

  document.querySelectorAll('.dropdown-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const dropdown = button.closest('.menu-dropdown');

      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  const viewRequestedPrModal = document.getElementById('viewRequestedPrModal');
  const closeRequestedPrModal = document.getElementById('closeRequestedPrModal');
  const closeRequestedPrModalBottom = document.getElementById('closeRequestedPrModalBottom');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-requested-pr-modal');

    if (!button) return;

    event.preventDefault();

    setField('viewRequestedPrNo', button.dataset.prNo);
    setField('viewRequestedJoNo', button.dataset.jobOrderNo);
    setField('viewRequestedBusNo', button.dataset.busNo);
    setField('viewRequestedStatus', button.dataset.status);
    setField('viewRequestedCreated', button.dataset.created);
    setField('viewRequestedRemarks', button.dataset.remarks, 'No remarks');

    renderRequestedPartsFromDataset(button);

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

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
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