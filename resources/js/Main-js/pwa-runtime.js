/* Shared PWA registration. Never cache account content or force-refresh a form. */
const registerPwa = () => {
  if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      // Browser fetches updated service workers on navigation; check again when
      // users return to a long-lived tab, without reloading or discarding edits.
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden && navigator.onLine) {
          registration.update().catch(() => {});
        }
      });
      // Inform users without silently refreshing a live form or workflow.
      window.addEventListener('gct:pwa-update-available', () => {
        if (document.getElementById('gctPwaUpdateNotice')) return;
        const notice = document.createElement('div');
        notice.id = 'gctPwaUpdateNotice';
        notice.setAttribute('role', 'status');
        notice.style.cssText = [
          'position:fixed', 'z-index:2147482000', 'bottom:16px', 'right:16px',
          'max-width:min(360px,calc(100vw - 32px))', 'padding:14px 16px',
          'border-radius:12px', 'background:#10294d', 'color:white',
          'box-shadow:0 8px 30px #0004', 'font:14px/1.5 system-ui,sans-serif'
        ].join(';');
        const message = document.createElement('span');
        message.textContent = 'A FROMS update is available. Save your work, then refresh the page to apply it.';
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.textContent = 'Dismiss';
        dismiss.setAttribute('aria-label', 'Dismiss update notification');
        dismiss.style.cssText = 'margin-left:12px;background:transparent;color:#fff;border:1px solid #ffffff80;border-radius:6px;padding:5px 9px;cursor:pointer';
        dismiss.addEventListener('click', () => notice.remove());
        notice.append(message, dismiss);
        document.body.appendChild(notice);
      });
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        if (!worker) return;
        worker.addEventListener('statechange', () => {
          if (worker.state !== 'installed' || !navigator.serviceWorker.controller) return;
          window.dispatchEvent(new CustomEvent('gct:pwa-update-available'));
          // Update on next ordinary navigation. Do not automatically reload
          // authenticated pages while a user is editing an unsaved form.
        });
      });
    } catch (_) {
      // Offline registration errors must not prevent normal operation.
    }
  }, { once: true });
};
registerPwa();
