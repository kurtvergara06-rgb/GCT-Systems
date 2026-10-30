window.GCTPartialNavigation.registerInitializer('purchase-maintenance-requests', '.purchase-maintenance-requests-page', function () {
  // Render parts breakdown above table clipping/stacking contexts, like the
  // Maintenance mechanic "+N more" popover. The row never expands.
  let partsPopover = null;
  let activePartsTrigger = null;

  function closePartsPopover() {
    partsPopover?.remove();
    partsPopover = null;
    if (activePartsTrigger) {
      activePartsTrigger.setAttribute('aria-expanded', 'false');
      activePartsTrigger = null;
    }
  }

  function placePartsPopover(trigger) {
    if (!partsPopover || !trigger.isConnected) {
      closePartsPopover();
      return;
    }
    const bounds = trigger.getBoundingClientRect();
    const width = Math.min(320, window.innerWidth - 24);
    const left = Math.max(12, Math.min(bounds.left, window.innerWidth - width - 12));
    const topBelow = bounds.bottom + 7;
    const availableBelow = window.innerHeight - topBelow - 12;
    const height = Math.min(partsPopover.scrollHeight, 250);
    const top = availableBelow < height && bounds.top > height + 16
      ? Math.max(12, bounds.top - height - 7)
      : topBelow;
    Object.assign(partsPopover.style, {
      position: 'fixed', top: top + 'px', left: left + 'px',
      width: width + 'px', maxHeight: Math.max(100, window.innerHeight - top - 12) + 'px'
    });
  }

  document.querySelectorAll('.requested-pr-more > summary').forEach((trigger) => {
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      if (activePartsTrigger === trigger) {
        closePartsPopover();
        return;
      }
      closePartsPopover();
      const content = trigger.parentElement.querySelector('.requested-pr-more-panel');
      if (!content) return;
      partsPopover = content.cloneNode(true);
      partsPopover.classList.add('requested-pr-floating-popover');
      document.body.appendChild(partsPopover);
      activePartsTrigger = trigger;
      trigger.setAttribute('aria-expanded', 'true');
      placePartsPopover(trigger);
    });
  });
  document.addEventListener('pointerdown', (event) => {
    if (partsPopover && !partsPopover.contains(event.target) &&
        !activePartsTrigger?.contains(event.target)) closePartsPopover();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closePartsPopover();
  });
  window.addEventListener('resize', closePartsPopover);
  window.addEventListener('scroll', closePartsPopover, true);

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

  function splitQuantityAndUnit(value) {
    const text = String(value || '').trim();

    if (!text || text === '—') {
      return {
        quantity: '1',
        unit: '—',
      };
    }

    const match = text.match(/^(\d+)\s*(.*)$/);

    if (!match) {
      return {
        quantity: text,
        unit: '—',
      };
    }

    return {
      quantity: String(match[1] || '1').trim(),
      unit: String(match[2] || '—').trim() || '—',
    };
  }

  function cleanPartNameQuantityUnit(rawName, rawQuantity) {
    let name = String(rawName || '').trim();
    let quantityText = String(rawQuantity || '').trim();

    /*
      Example:
      Engine Oil - Qty: 2 liter
    */
    if (name.toLowerCase().includes(' - qty:')) {
      const splitParts = name.split(/ - qty:/i);

      name = String(splitParts[0] || '').trim();
      quantityText = String(splitParts[1] || quantityText || '1').trim();

      const parsed = splitQuantityAndUnit(quantityText);

      return {
        name,
        quantity: parsed.quantity,
        unit: parsed.unit,
      };
    }

    /*
      Example:
      Engine Oil (2 liter)
    */
    const parenthesisMatch = name.match(/^(.*?)\s*\((\d+\s*[^)]*)\)$/);

    if (parenthesisMatch) {
      name = String(parenthesisMatch[1] || '').trim();
      quantityText = String(parenthesisMatch[2] || quantityText || '1').trim();

      const parsed = splitQuantityAndUnit(quantityText);

      return {
        name,
        quantity: parsed.quantity,
        unit: parsed.unit,
      };
    }

    /*
      Example:
      Engine Oil 2 liter
      Oil Filter 4 pcs
    */
    const trailingQtyMatch = name.match(
      /^(.*?)\s+(\d+)\s*(liter|liters|litre|litres|ltr|ltrs|pcs|pc|piece|pieces|set|sets|bottle|bottles|box|boxes|pack|packs|pair|pairs|gallon|gallons|kg|meter|meters)$/i
    );

    if (trailingQtyMatch && !quantityText) {
      return {
        name: String(trailingQtyMatch[1] || '').trim(),
        quantity: String(trailingQtyMatch[2] || '1').trim(),
        unit: String(trailingQtyMatch[3] || '—').trim(),
      };
    }

    const parsed = splitQuantityAndUnit(quantityText || '1');

    return {
      name,
      quantity: parsed.quantity,
      unit: parsed.unit,
    };
  }

  function parseRequestedParts(rawItem, rawQuantity) {
    const itemText = String(rawItem || '').trim();
    const fallbackQuantity = String(rawQuantity || '1').trim();

    if (!itemText) return [];

    return itemText
      .split(',')
      .map(function (part, index) {
        const cleanPart = part.trim();

        if (!cleanPart) return null;

        return cleanPartNameQuantityUnit(
          cleanPart,
          index === 0 ? fallbackQuantity : ''
        );
      })
      .filter(function (part) {
        return part && part.name;
      });
  }

  function normalizePartFromJson(part) {
    const rawName =
      part.name ||
      part.part_name ||
      part.item ||
      part.item_name ||
      '';

    const rawQuantity =
      part.quantity_display ||
      part.needed_display ||
      part.quantity ||
      part.needed ||
      '';

    const rawUnit =
      part.unit ||
      part.unit_of_measurement ||
      '';

    const parsed = cleanPartNameQuantityUnit(rawName, rawQuantity);

    return {
      name: parsed.name,
      quantity: parsed.quantity,
      unit: rawUnit ? rawUnit : parsed.unit,
    };
  }

  function renderRequestedParts(button) {
    const container = document.getElementById('viewRequestedPartsContainer');

    if (!container) return;

    container.replaceChildren();

    let parts = [];

    try {
      parts = JSON.parse(button.dataset.parts || '[]');
    } catch (error) {
      parts = [];
    }

    if (Array.isArray(parts) && parts.length > 0) {
      parts = parts
        .map(function (part) {
          return normalizePartFromJson(part);
        })
        .filter(function (part) {
          return part && part.name;
        });
    }

    if (!Array.isArray(parts) || parts.length === 0) {
      parts = parseRequestedParts(button.dataset.item, button.dataset.quantity);
    }

    if (!Array.isArray(parts) || parts.length === 0) {
      const row = document.createElement('tr');
      ['—', '—', '—'].forEach(function (value) {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.appendChild(cell);
      });
      container.appendChild(row);
      return;
    }

    parts.forEach(function (part) {
      const row = document.createElement('tr');

      [part.name || '—', part.quantity || '1', part.unit || '—'].forEach(function (value) {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.appendChild(cell);
      });

      container.appendChild(row);
    });
  }

  const viewRequestedPrModal = document.getElementById('viewRequestedPrModal');
  const closeRequestedPrModal = document.getElementById('closeRequestedPrModal');
  const closeRequestedPrModalBottom = document.getElementById('closeRequestedPrModalBottom');
  const viewRequestedStatus = document.getElementById('viewRequestedStatus');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.open-view-requested-pr-modal');

    if (!button) return;

    event.preventDefault();

    setField('viewRequestedPrNo', button.dataset.prNo);
    setField('viewRequestedJoNo', button.dataset.jobOrderNo);
    setField('viewRequestedBusNo', button.dataset.busNo);
    setField('viewRequestedCreated', button.dataset.created);
    setField('viewRequestedRemarks', button.dataset.remarks, 'No remarks');
    setField('viewRequestedStatus', button.dataset.status);
    if (viewRequestedStatus) {
      viewRequestedStatus.className = 'requested-pr-modal-status status-' +
        String(button.dataset.status || 'unknown').toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    const createPoForm = document.getElementById('requestedPrCreatePoForm');
    if (createPoForm) {
      const createUrl = button.dataset.createPoUrl || '';
      createPoForm.hidden = !createUrl;
      if (createUrl) createPoForm.action = createUrl;
      else createPoForm.removeAttribute('action');
    }

    renderRequestedParts(button);

    openModal(viewRequestedPrModal);
    closeRequestedPrModal?.focus();
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

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && viewRequestedPrModal?.classList.contains('show')) {
      closeModal(viewRequestedPrModal);
    }
  });
});
