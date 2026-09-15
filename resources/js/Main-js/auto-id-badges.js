const idHeaders = [
  'id',
  'driver id',
  'driver #',
  'mechanic id',
  'mechanic #',
  'bus',
  'bus id',
  'bus #',
  'bus no',
  'bus no.',
  'bus number',
  'vehicle',
  'vehicle id',
  'vehicle #',
  'vehicle no',
  'vehicle no.',
  'jo',
  'jo #',
  'jo no',
  'jo no.',
  'job order',
  'job order #',
  'job order no',
  'job order no.',
  'job order id',
  'pr',
  'pr #',
  'pr no',
  'pr no.',
  'purchase request',
  'purchase request #',
  'purchase request no',
  'purchase request no.',
  'purchase request id',
  'request',
  'request #',
  'request no',
  'request no.',
  'request id',
  'po',
  'po #',
  'po no',
  'po no.',
  'purchase order',
  'purchase order #',
  'purchase order no',
  'purchase order no.',
  'purchase order id',
  'trip',
  'trip id',
  'trip code',
  'trip #',
  'trip no',
  'trip no.',
  'trip number',
  'route id',
  'route code',
  'route #',
  'schedule id',
  'assignment id',
  'batch id',
  'batch #',
  'batch no',
  'batch no.',
  'batch code',
  'record id',
  'record #',
  'record no',
  'record no.',
  'item code',
  'item id',
  'item #',
  'item no',
  'item no.',
  'part id',
  'part code',
  'part #',
  'part no',
  'part no.',
  'maintenance id',
  'reference',
  'reference #',
  'reference no',
];

const nonIdWords = [
  'status', 'date', 'time', 'type', 'score', 'count', 'amount', 'cost', 'price',
  'fee', 'quantity', 'qty', 'total', 'action', 'name', 'description', 'distance',
  'fuel', 'model', 'mileage', 'grouping', 'destination', 'origin', 'reason',
  'duration', 'loss', 'level', 'rate', 'efficiency', 'speed', 'idle', 'factor',
  'breakdown', 'trend', 'state', 'risk', 'progress', 'capacity', 'note', 'alert'
];

const shouldBadgeHeader = (text) => {
  const normalized = text.trim().toLowerCase();

  if (!normalized) {
    return false;
  }

  if (nonIdWords.some((word) => normalized.includes(word))) {
    return false;
  }

  const clean = normalized.replace(/[.:#]/g, '').trim();

  return idHeaders.some((header) => {
    const cleanHeader = header.replace(/[.:#]/g, '').trim();
    return (
      normalized === header
      || clean === cleanHeader
      || normalized.startsWith(`${header} `)
      || clean.startsWith(`${cleanHeader} `)
      || normalized.includes(' / bus')
      || normalized.includes(' / job order')
      || normalized.includes(' / driver')
      || normalized.includes(' / mechanic')
    );
  });
};

const makeCompactIdBadge = (element) => {
  element.classList.add('system-id-badge', 'system-id-badge--small');
  element.title = element.textContent.trim();

  /*
   * Keep detected IDs content-sized and non-animated so they match
   * clean system identifiers.
   */
  element.style.setProperty('display', 'inline-flex', 'important');
  element.style.setProperty('align-items', 'center', 'important');
  element.style.setProperty('justify-content', 'center', 'important');
  element.style.setProperty('width', 'max-content', 'important');
  element.style.setProperty('max-width', '100%', 'important');
  element.style.setProperty('box-sizing', 'border-box', 'important');
  element.style.setProperty('transition', 'none', 'important');
};

const normalizeExistingIdElements = (root = document) => {
  root.querySelectorAll([
    '.personnel-id',
    '.driver-id',
    '.mechanic-id',
    '.bus-id',
    '.route-code',
    '.trip-code',
    '.assignment-reference',
    '.table-bus-chip',
    '.bus-chip',
    '.item-code-chip',
    '.table-item-code-chip',
    '.record-id-chip',
    '.batch-id-chip',
  ].join(',')).forEach((element) => {
    if (!element.textContent.trim()) {
      return;
    }

    makeCompactIdBadge(element);
  });
};

const badgeTable = (table) => {
  const headers = Array.from(table.querySelectorAll('thead th'));
  const targetIndexes = headers
    .map((header, index) => shouldBadgeHeader(header.textContent || '') ? index : -1)
    .filter((index) => index >= 0);

  if (!targetIndexes.length) {
    return;
  }

  table.querySelectorAll('tbody tr').forEach((row) => {
    const cells = row.querySelectorAll(':scope > td');

    targetIndexes.forEach((index) => {
      const cell = cells[index];

      if (!cell) {
        return;
      }

      const existingBadge = cell.querySelector([
        '.system-id-badge',
        '.table-bus-chip',
        '.bus-chip',
        '.item-code-chip',
        '.table-item-code-chip',
      ].join(','));
      if (existingBadge) {
        makeCompactIdBadge(existingBadge);
        return;
      }

      const existingCandidate = cell.querySelector([
        '.personnel-id',
        '.driver-id',
        '.mechanic-id',
        '.bus-id',
        '.route-code',
        '.trip-code',
        '.assignment-reference',
        '.record-id-chip',
        '.batch-id-chip',
      ].join(','));

      if (existingCandidate) {
        makeCompactIdBadge(existingCandidate);
        return;
      }

      const nestedCandidate = Array.from(cell.children).find((element) => {
        if (!['STRONG', 'SPAN'].includes(element.tagName)) {
          return false;
        }

        // Never touch status, risk, alerts, categories, or buttons
        if (element.matches('.badge, .status-badge, .workflow-badge, .source-badge, .diag-badge, .ft-badge, .fuel-status-badge, .risk-badge, [class*="status"], [class*="risk"], [class*="alert"], [class*="btn"], button')) {
          return false;
        }

        const value = element.textContent.trim();
        return value !== '' && value !== '—' && !value.toLowerCase().includes('no ');
      });

      if (nestedCandidate) {
        makeCompactIdBadge(nestedCandidate);
        return;
      }

      const directTextNodes = Array.from(cell.childNodes)
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .filter((node) => node.textContent.trim() !== '');

      const value = directTextNodes
        .map((node) => node.textContent.trim())
        .join(' ')
        .trim();

      if (!value || value === '—' || value.toLowerCase().includes('no ')) {
        return;
      }

      directTextNodes.forEach((node) => node.remove());

      const badge = document.createElement('span');
      badge.textContent = value;
      makeCompactIdBadge(badge);
      cell.prepend(badge);
    });
  });
};

const processIdBadges = (root = document) => {
  normalizeExistingIdElements(root);

  if (root.matches?.('table')) {
    badgeTable(root);
  }

  root.querySelectorAll?.('table').forEach(badgeTable);
};

document.addEventListener('DOMContentLoaded', () => {
  processIdBadges(document);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          processIdBadges(node);
        }
      });
    });
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
});
