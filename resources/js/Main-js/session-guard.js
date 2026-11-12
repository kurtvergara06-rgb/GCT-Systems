/*
 * GCT session guard. Requests are sent only after real user activity, never
 * as a perpetual heartbeat from an idle browser tab. The server remains
 * authoritative: HTTP 401/419 require login, even with Remember Me configured.
 */
(() => {
    const accountId = document.querySelector('meta[name="gct-account-id"]')?.content;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const lifetime = Number(document.querySelector('meta[name="gct-session-lifetime-seconds"]')?.content || 0) * 1000;
    if (!accountId || !csrf || !Number.isFinite(lifetime) || lifetime < 60000) return;

    const interval = Math.min(180000, Math.max(30000, Math.floor(lifetime / 4)));
    const warningWindow = Math.min(300000, Math.floor(lifetime / 3));
    let expiresAt = Date.now() + lifetime;
    let lastRenewal = Date.now();
    let activeAt = 0;
    let requestInFlight = false;
    let expired = false;
    let warning = null;
    let countdown = null;
    let continueButton = null;
    const loginUrl = '/';

    function setMessage(message) {
        if (countdown) countdown.textContent = message;
    }

    function showWarning() {
        if (warning || expired) return;
        warning = document.createElement('section');
        warning.className = 'gct-session-warning';
        warning.setAttribute('role', 'dialog');
        warning.setAttribute('aria-modal', 'false');
        warning.setAttribute('aria-labelledby', 'gct-session-warning-title');
        warning.innerHTML = `
            <div class="gct-session-warning-heading">
                <span aria-hidden="true">◷</span>
                <strong id="gct-session-warning-title">Session expiring soon</strong>
            </div>
            <p>Your session is nearing its inactivity limit. Save your current work and select Continue Session to remain signed in.</p>
            <p class="gct-session-warning-time" aria-live="off"></p>
            <div class="gct-session-warning-actions">
                <button type="button" data-gct-session-continue>Continue Session</button>
            </div>`;
        document.body.append(warning);
        countdown = warning.querySelector('.gct-session-warning-time');
        continueButton = warning.querySelector('[data-gct-session-continue]');
        continueButton?.addEventListener('click', () => renew(true));
    }

    function hideWarning() {
        warning?.remove();
        warning = null;
        countdown = null;
        continueButton = null;
    }

    function sessionExpired() {
        if (expired) return;
        expired = true;
        showWarning();
        // Replace the warning with an explicit reauthentication prompt.
        if (!warning) {
            warning = document.createElement('section');
            warning.className = 'gct-session-warning';
            document.body.append(warning);
        }
        warning.innerHTML = `
            <strong id="gct-session-warning-title">Session may have expired</strong>
            <p>Save or copy any unsaved information before signing in again. This page will not be cleared automatically.</p>
            <a href="/">Go to Login</a>`;
        warning.setAttribute('role', 'alertdialog');
        warning.setAttribute('aria-labelledby', 'gct-session-warning-title');
        countdown = null;
        continueButton = null;
    }

    async function renew(force = false) {
        if (requestInFlight || expired) return;
        if (!force && (document.hidden || Date.now() - lastRenewal < interval || Date.now() - activeAt > 30000)) return;
        requestInFlight = true;
        if (continueButton) continueButton.disabled = true;
        try {
            const response = await fetch('/session/renew', {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'manual',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (response.status === 401 || response.status === 419 || response.type === 'opaqueredirect') {
                sessionExpired();
                return;
            }
            if (!response.ok) {
                setMessage('Unable to verify the session. Save your work and try Continue Session again.');
                return;
            }
            const data = await response.json();
            if (!data.authenticated || String(data.account_id) !== String(accountId)) {
                sessionExpired();
                return;
            }
            const renewedLifetime = Number(data.lifetime_seconds) * 1000;
            expiresAt = Date.now() + (renewedLifetime > 0 ? renewedLifetime : lifetime);
            lastRenewal = Date.now();
            hideWarning();
        } catch (_) {
            setMessage('Connection unavailable. Save your work and reconnect before continuing.');
        } finally {
            requestInFlight = false;
            if (continueButton) continueButton.disabled = false;
        }
    }

    function checkExpiry() {
        if (expired) return;
        const remaining = expiresAt - Date.now();
        if (remaining <= 0) {
            // A remembered login might restore authentication on the next request,
            // but do not assume it: the server must confirm it.
            showWarning();
            setMessage('The inactivity limit has been reached. Select Continue Session to verify your login.');
            return;
        }
        if (remaining <= warningWindow) {
            showWarning();
            setMessage(`Approximately ${Math.ceil(remaining / 60000)} minute(s) remaining.`);
        }
    }

    function noteActivity(event) {
        if (expired || document.hidden) return;
        if (event.type === 'keydown' && ['Shift', 'Control', 'Alt', 'Meta'].includes(event.key)) return;
        activeAt = Date.now();
        if (!warning) void renew();
    }

    for (const name of ['pointerdown', 'keydown', 'input']) {
        document.addEventListener(name, noteActivity, { passive: true });
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkExpiry(); });
    window.addEventListener('pageshow', checkExpiry);
    window.setInterval(checkExpiry, 15000);
})();
