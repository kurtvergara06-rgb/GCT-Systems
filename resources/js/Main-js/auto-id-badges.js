document.addEventListener('DOMContentLoaded', () => {
  const idHeaders = [
    'id',
    'bus id',
    'bus #',
    'bus no',
    'vehicle',
    'jo #',
    'jo no',
    'job order',
    'pr #',
    'pr no',
    'po #',
    'po no',
    'trip code',
    'route code',
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
    );
  };

  document.querySelectorAll('table').forEach((table) => {
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
  });
});
