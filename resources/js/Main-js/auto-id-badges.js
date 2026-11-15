const idHeaders = [
  'id',
  'driver id',
  'mechanic id',
  'bus id',
  'bus #',
  'bus no',
  'vehicle id',
  'vehicle',
  'jo #',
  'jo no',
  'job order',
  'pr #',
  'pr no',
  'po #',
  'po no',
  'trip id',
  'trip code',
  'route id',
  'route code',
  'schedule id',
  'assignment id',
  'item code',
  'reference',
];

const shouldBadgeHeader = (text) => {
  const normalized = text.trim().toLowerCase();

  if (!normalized || normalized.includes('status')) {
    return false;
  }

  return idHeaders.some((header) =>
    normalized === header
    || normalized.startsWith(`${header} `)
    || normalized.includes(' / bus')
    || normalized.includes(' / job order')
    || normalized.includes(' / driver')
    || normalized.includes(' / mechanic')
  );
};

const makeCompactIdBadge = (element) => {
  element.classList.add('system-id-badge', 'system-id-badge--small');
  element.title = element.textContent.trim();

  /*
   * Some pages style <strong> or <span> as block-level elements.
   * Keep detected IDs content-sized so they match the compact Job Order
   * identifier chips instead of stretching across the whole table cell.
   */
  element.style.setProperty('display', 'inline-flex', 'important');
  element.style.setProperty('width', 'max-content', 'important');
  element.style.setProperty('max-width', '100%', 'important');
  element.style.setProperty('box-sizing', 'border-box', 'important');
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

      const existingBadge = cell.querySelector('.system-id-badge');
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
      ].join(','));

      if (existingCandidate) {
        makeCompactIdBadge(existingCandidate);
        return;
      }

      const nestedCandidate = Array.from(cell.children).find((element) => {
        if (!['STRONG', 'SPAN'].includes(element.tagName)) {
          return false;
        }

        if (element.matches('.badge, .status-badge, .workflow-badge, .source-badge')) {
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
