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

    element.classList.add('system-id-badge', 'system-id-badge--small');
    element.title = element.textContent.trim();
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

      if (!cell || cell.querySelector('.system-id-badge')) {
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
        existingCandidate.classList.add('system-id-badge', 'system-id-badge--small');
        existingCandidate.title = existingCandidate.textContent.trim();
        return;
      }

      /*
       * Some tables render identifiers inside a semantic <strong> or <span>
       * so they can keep secondary text underneath (for example a bus model).
       * Treat that first direct child as the identifier instead of requiring
       * the ID to be a raw text node. This keeps Bus IDs visually consistent
       * with Job Orders without page-specific markup.
       */
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
        nestedCandidate.classList.add('system-id-badge', 'system-id-badge--small');
        nestedCandidate.title = nestedCandidate.textContent.trim();
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
      badge.className = 'system-id-badge system-id-badge--small';
      badge.textContent = value;
      badge.title = value;
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