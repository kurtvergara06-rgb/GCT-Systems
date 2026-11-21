// Read the existing public legal pages inside a dialog, without duplicating policy text.
(() => {
  let dialog = null;
  let content = null;
  let heading = null;
  let controller = null;
  let previousFocus = null;

  function ensureDialog() {
    if (dialog) return;
    dialog = document.createElement('dialog');
    dialog.className = 'gct-legal-dialog';
    dialog.setAttribute('aria-labelledby', 'gctLegalDialogTitle');
    dialog.innerHTML = `
      <div class="gct-legal-dialog-header">
        <div>
          <span class="gct-legal-dialog-label">GCT Transport Services, Inc.</span>
          <h2 id="gctLegalDialogTitle">Legal information</h2>
        </div>
        <button type="button" class="gct-legal-dialog-close" aria-label="Close legal information" data-legal-close>
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
      <div class="gct-legal-dialog-body" data-legal-content tabindex="0"></div>
    `;
    heading = dialog.querySelector('#gctLegalDialogTitle');
    content = dialog.querySelector('[data-legal-content]');
    dialog.querySelector('[data-legal-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      controller?.abort();
      controller = null;
      previousFocus?.focus?.();
    });
    // No backdrop-click dismissal; only the explicit close button and Escape close it.
    document.body.appendChild(dialog);
  }

  async function openLegal(url, title) {
    ensureDialog();
    controller?.abort();
    controller = new AbortController();
    const currentController = controller;
    previousFocus = dialog.open ? previousFocus : document.activeElement;
    heading.textContent = title;
    content.textContent = 'Loading ' + title.toLowerCase() + '…';
    content.scrollTop = 0;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector('[data-legal-close]').focus();

    try {
      const response = await fetch(url, { headers: { 'Accept': 'text/html' }, signal: currentController.signal });
      if (!response.ok) throw new Error('Unable to retrieve legal information');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      const article = html.querySelector('article.legal-card');
      if (!article || controller !== currentController || !dialog.open) throw new Error('Legal content unavailable');
      content.replaceChildren(...Array.from(article.children).map(node => document.importNode(node, true)));
      // The standalone page heading is repeated in the dialog header; hide only its duplicate.
      content.querySelector('h1')?.remove();
      content.querySelector('.legal-kicker')?.remove();
      content.querySelectorAll('a[href]').forEach(link => {
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin) return;
        if (destination.pathname === '/privacy') link.dataset.legalModal = 'Privacy Notice';
        if (destination.pathname === '/terms') link.dataset.legalModal = 'Terms of Use';
      });
      content.scrollTop = 0;
    } catch (error) {
      if (error.name === 'AbortError' || controller !== currentController || !dialog.open) return;
      content.replaceChildren();
      const p = document.createElement('p');
      p.textContent = 'Unable to load this document right now.';
      const link = document.createElement('a');
      link.href = url;
      link.textContent = 'Open the full page';
      content.append(p, link);
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('a[data-legal-modal]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openLegal(link.href, link.dataset.legalModal);
  });
})();
