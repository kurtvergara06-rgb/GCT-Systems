// Per-account browser preferences: no permission or workflow state is mutated.
// The authenticated identity must agree between the server-rendered page
// and sidebar. Never read another account's localStorage on stale navigation.
const currentAccountId = () => {
    const serverId = document.querySelector('meta[name="gct-account-id"]')?.content;
    const sidebarId = document.getElementById('appSidebar')?.dataset.gctUserId;
    return serverId && sidebarId && serverId === sidebarId ? serverId : null;
};
const keyForCurrentUser = () => {
    const id = currentAccountId();
    return id ? `gct:ui-preferences:${id}` : null;
};
const defaults = Object.freeze({
    theme: 'light',
    font: 'poppins',
    fontSize: 'default',
    density: 'comfortable',
    reducedMotion: false,
});
const normalizePreferences = (value) => ({
    theme: ['light', 'dark', 'system'].includes(value?.theme) ? value.theme : 'light',
    font: ['poppins', 'inter', 'roboto'].includes(value?.font) ? value.font : 'poppins',
    fontSize: ['small', 'default', 'large'].includes(value?.fontSize) ? value.fontSize : 'default',
    density: value?.density === 'compact' ? 'compact' : 'comfortable',
    reducedMotion: value?.reducedMotion === true,
});
const loadPreferences = () => {
    const key = keyForCurrentUser();
    if (!key) return { ...defaults };
    try {
        return normalizePreferences(JSON.parse(localStorage.getItem(key) || '{}'));
    } catch {
        return { ...defaults };
    }
};
const resolveTheme = (preference) => preference === 'system'
    ? (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : preference;
const applyPreferences = (preferences) => {
    const root = document.documentElement;
    const effectiveTheme = resolveTheme(preferences.theme);
    root.dataset.gctTheme = effectiveTheme;
    root.dataset.gctThemeChoice = preferences.theme;
    root.dataset.gctFont = preferences.font;
    root.dataset.gctFontSize = preferences.fontSize;
    root.classList.toggle('gct-density-compact', preferences.density === 'compact');
    root.classList.toggle('gct-reduced-motion', preferences.reducedMotion);
    // Dark appearance is currently enabled for Operation and Account pages.
    // Other departments retain light native form controls until migrated.
    const darkPageEnabled = document.body.dataset.gctOperationPage === '1'
        || document.querySelector('.account-page') !== null;
    root.style.colorScheme = darkPageEnabled ? effectiveTheme : 'light';
};
const updateInputs = (preferences) => {
    document.querySelectorAll('[data-gct-preference]').forEach((control) => {
        const field = control.dataset.gctPreference;
        if (!(field in preferences)) return;
        if (control.type === 'checkbox') control.checked = preferences[field] === true;
        else if (control.type === 'radio') control.checked = control.value === preferences[field];
        else control.value = preferences[field];
    });
};
const renderPreferences = () => {
    const preferences = loadPreferences();
    applyPreferences(preferences);
    updateInputs(preferences);
};
const announce = (message) => {
    document.querySelectorAll('[data-gct-preferences-status]').forEach((status) => {
        status.textContent = message;
    });
};
document.addEventListener('change', (event) => {
    const control = event.target.closest?.('[data-gct-preference]');
    const key = keyForCurrentUser();
    if (!control || !key) return;
    const field = control.dataset.gctPreference;
    if (!(field in defaults)) return;
    const preferences = loadPreferences();
    preferences[field] = control.type === 'checkbox' ? control.checked : control.value;
    const safe = normalizePreferences(preferences);
    try {
        localStorage.setItem(key, JSON.stringify(safe));
        renderPreferences();
        announce('Your personalization settings were saved on this browser.');
    } catch {
        renderPreferences();
        announce('This browser did not allow saving preferences.');
    }
});
document.addEventListener('click', (event) => {
    if (!event.target.closest?.('[data-gct-reset-preferences]')) return;
    const key = keyForCurrentUser();
    if (!key) return;
    try {
        localStorage.removeItem(key);
        renderPreferences();
        announce('Display preferences restored to GCT defaults.');
    } catch {
        announce('Unable to reset browser preferences.');
    }
});
const colorSchemeMedia = window.matchMedia?.('(prefers-color-scheme: dark)');
colorSchemeMedia?.addEventListener?.('change', () => {
    if (loadPreferences().theme === 'system') renderPreferences();
});
document.addEventListener('DOMContentLoaded', renderPreferences);
document.addEventListener('gct:page-loaded', renderPreferences);
window.addEventListener('gct:navigation-ready', renderPreferences);
window.addEventListener('pageshow', renderPreferences);
if (document.readyState !== 'loading') renderPreferences();


// Optional notification sound. Critical in-app messages are never filtered
// or muted; only the sound cue is configurable on this browser.
const soundKeyForCurrentUser = () => {
    const id = currentAccountId();
    return id ? `gct:notification-sound:${id}` : null;
};
const notificationSoundEnabled = () => {
    const key = soundKeyForCurrentUser();
    if (!key) return false;
    try { return localStorage.getItem(key) === 'enabled'; }
    catch { return false; }
};
const renderSoundPreference = () => {
    document.querySelectorAll('[data-gct-notification-sound]').forEach((control) => {
        control.checked = notificationSoundEnabled();
    });
};
document.addEventListener('change', (event) => {
    const control = event.target.closest?.('[data-gct-notification-sound]');
    const key = soundKeyForCurrentUser();
    if (!control || !key) return;
    try {
        localStorage.setItem(key, control.checked ? 'enabled' : 'disabled');
        renderSoundPreference();
        document.querySelectorAll('[data-gct-settings-status]').forEach((el) => {
            el.textContent = 'Notification sound preference saved for this browser.';
        });
    } catch {
        control.checked = notificationSoundEnabled();
        document.querySelectorAll('[data-gct-settings-status]').forEach((el) => {
            el.textContent = 'This browser could not save the sound preference.';
        });
    }
});
document.addEventListener('DOMContentLoaded', renderSoundPreference);
window.addEventListener('gct:navigation-ready', renderSoundPreference);
document.addEventListener('gct:page-loaded', renderSoundPreference);
window.addEventListener('pageshow', renderSoundPreference);
if (document.readyState !== 'loading') renderSoundPreference();

let observedNotificationAccount = null;
let lastObservedUnreadCount = null;
let notificationAudioContext = null;
const playOptionalNotificationCue = () => {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;
    try {
        notificationAudioContext ||= new AudioContextClass();
        if (notificationAudioContext.state !== 'running') return;
        const oscillator = notificationAudioContext.createOscillator();
        const volume = notificationAudioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 660;
        volume.gain.setValueAtTime(0.0001, notificationAudioContext.currentTime);
        volume.gain.exponentialRampToValueAtTime(0.035, notificationAudioContext.currentTime + 0.012);
        volume.gain.exponentialRampToValueAtTime(0.0001, notificationAudioContext.currentTime + 0.17);
        oscillator.connect(volume);
        volume.connect(notificationAudioContext.destination);
        oscillator.start();
        oscillator.stop(notificationAudioContext.currentTime + 0.18);
    } catch {
        // Browser restrictions must never break the notification center.
    }
};
// Audio activation requires a user gesture in browsers that block autoplay.
document.addEventListener('pointerdown', () => {
    if (!notificationSoundEnabled()) return;
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;
    try {
        notificationAudioContext ||= new AudioContextClass();
        if (notificationAudioContext.state === 'suspended') {
            notificationAudioContext.resume().catch(() => {});
        }
    } catch {
        // Sound is best effort, not a required part of system notifications.
    }
});
window.addEventListener('topbar-summary-loaded', (event) => {
    const account = soundKeyForCurrentUser();
    const count = event.detail?.unread_count;
    if (!account || typeof count !== 'number') return;
    const next = Math.max(0, count);
    if (account !== observedNotificationAccount) {
        observedNotificationAccount = account;
        lastObservedUnreadCount = next;
        return;
    }
    if (lastObservedUnreadCount !== null && next > lastObservedUnreadCount && notificationSoundEnabled()) {
        playOptionalNotificationCue();
    }
    lastObservedUnreadCount = next;
});
