/* Retire previous PWA service worker and offline cache without disrupting login. */
const retireLegacyPwa = async () => {
  if (!('serviceWorker' in navigator)) return;
  try {
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(registrations
      .filter((registration) => {
        const scriptUrl = registration.active?.scriptURL
          || registration.waiting?.scriptURL
          || registration.installing?.scriptURL;
        if (!scriptUrl) return false;
        const url = new URL(scriptUrl);
        return url.origin === window.location.origin && url.pathname === '/sw.js';
      })
      .map((registration) => registration.unregister()));
  } catch {
    // Never interfere with authentication or user workflows.
  }

  if (!('caches' in window)) return;
  try {
    const names = await window.caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('froms-offline-'))
      .map((name) => window.caches.delete(name)));
  } catch {
    // Cache clearing is best effort.
  }
};

retireLegacyPwa();
