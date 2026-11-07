// Per-account browser preferences: no permission or workflow state is mutated.
const keyForCurrentUser = () => {
    const id = document.getElementById('appSidebar')?.dataset.gctUserId;
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
    root.style.colorScheme = effectiveTheme;
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
if (document.readyState !== 'loading') renderPreferences();
