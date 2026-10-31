window.GCTPartialNavigation.registerInitializer('purchase-inventory-restock', '.inventory-restock-page', function () {
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

  // Position expanded item details above table clipping without changing row height.
  const positionRestockPopover = (details) => {
    const summary = details?.querySelector('summary');
    const popover = details?.querySelector('.restock-item-popover');
    if (!summary || !popover || !details.open) return;
    const rect = summary.getBoundingClientRect();
    const width = Math.min(340, Math.max(210, popover.offsetWidth || 210));
    const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
    const below = rect.bottom + 6;
    const height = popover.offsetHeight;
    const top = below + height > window.innerHeight - 8 && rect.top > height + 8
      ? rect.top - height - 6
      : Math.min(below, Math.max(8, window.innerHeight - height - 8));
    popover.style.left = left + 'px';
    popover.style.top = Math.max(8, top) + 'px';
  };

  const closeExpandedItems = () => {
    document.querySelectorAll('.inventory-restock-page .restock-item-more[open]').forEach((details) => {
      details.open = false;
    });
  };

  document.querySelectorAll('.inventory-restock-page .restock-item-more').forEach((details) => {
    details.addEventListener('toggle', () => {
      if (!details.open) return;
      document.querySelectorAll('.inventory-restock-page .restock-item-more[open]').forEach((other) => {
        if (other !== details) other.open = false;
      });
      positionRestockPopover(details);
    });
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.inventory-restock-page .restock-item-more')) closeExpandedItems();
  });
  window.addEventListener('resize', closeExpandedItems);
  window.addEventListener('scroll', closeExpandedItems, true);

  const reviewSignature = document.getElementById('viewRestockSignature');
  reviewSignature?.addEventListener('load', () => {
    reviewSignature.hidden = false;
    const unavailable = document.getElementById('viewRestockSignatureUnavailable');
    if (unavailable) unavailable.hidden = true;
  });
  reviewSignature?.addEventListener('error', () => {
    reviewSignature.hidden = true;
    const unavailable = document.getElementById('viewRestockSignatureUnavailable');
    if (unavailable) unavailable.hidden = false;
  });
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
    setField('viewRestockApprovedBy', button.dataset.approvedBy);
    setField('viewRestockApprovedAt', button.dataset.approvedAt);
    setField('viewRestockPurpose', button.dataset.purpose);
    setField('viewRestockApprovalNotes', button.dataset.approvalNotes, 'None');
    setField('viewRestockSignatureName', button.dataset.approvedBy);
    const signatureImage = document.getElementById('viewRestockSignature');
    const signatureUnavailable = document.getElementById('viewRestockSignatureUnavailable');
    if (signatureImage) {
      signatureImage.hidden = true;
      signatureImage.removeAttribute('src');
      if (signatureUnavailable) signatureUnavailable.hidden = false;
      if (button.dataset.signatureUrl) signatureImage.src = button.dataset.signatureUrl;
    }
    const createPoLink = document.getElementById('restockReviewCreatePo');
    if (createPoLink) {
      const createPoUrl = button.dataset.createPoUrl || '';
      createPoLink.hidden = !createPoUrl;
      if (createPoUrl) createPoLink.href = createPoUrl;
      else createPoLink.removeAttribute('href');
    }
    const breakdown = document.getElementById('viewRestockItems');
    if (breakdown) {
      let lines = [];
      try {
        const parsed = JSON.parse(button.dataset.items || '[]');
        if (Array.isArray(parsed)) lines = parsed;
      } catch (_) { /* Fall back to legacy single-item data. */ }
      if (!lines.length) {
        lines = [{ name: button.dataset.item, quantity: button.dataset.quantity, unit: button.dataset.unit }];
      }
      breakdown.replaceChildren();
      lines.forEach((line) => {
        const row = document.createElement('div');
        row.className = 'restock-breakdown-row';
        [line.name, line.quantity, line.unit].forEach((value) => {
          const cell = document.createElement('span');
          cell.textContent = value === undefined || value === null ? '—' : String(value);
          row.appendChild(cell);
        });
        breakdown.appendChild(row);
      });
    }

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
