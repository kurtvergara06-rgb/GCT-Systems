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
      container.innerHTML = `
        <div class="requested-pr-breakdown-row">
          <span>—</span>
          <span>—</span>
          <span>—</span>
        </div>
      `;
      return;
    }

    let html = '';

    parts.forEach(function (part) {
      html += `
        <div class="requested-pr-breakdown-row">
          <span>${escapeHtml(part.name || '—')}</span>
          <span>${escapeHtml(part.quantity || '1')}</span>
          <span>${escapeHtml(part.unit || '—')}</span>
        </div>
      `;
    });

    container.innerHTML = html;
  }

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
    setField('viewRequestedCreated', button.dataset.created);
    setField('viewRequestedRemarks', button.dataset.remarks, 'No remarks');

    renderRequestedParts(button);

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

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal(viewRequestedPrModal);
    }
  });
});