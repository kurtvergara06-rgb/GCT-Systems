// Browser-only display options, scoped to the authenticated GCT account.
const keyForCurrentUser = () => {
    const id = document.getElementById('appSidebar')?.dataset.gctUserId;
    return id ? `gct:ui-preferences:${id}` : null;
};
const defaults = Object.freeze({ density: 'comfortable', reducedMotion: false });
const loadPreferences = () => {
    const key = keyForCurrentUser();
    if (!key) return { ...defaults };
    try {
        const value = JSON.parse(localStorage.getItem(key) || '{}');
        return {
            density: value?.density === 'compact' ? 'compact' : 'comfortable',
            reducedMotion: value?.reducedMotion === true,
        };
    } catch {
        return { ...defaults };
    }
};
const applyPreferences = (preferences) => {
    document.documentElement.classList.toggle('gct-density-compact', preferences.density === 'compact');
    document.documentElement.classList.toggle('gct-reduced-motion', preferences.reducedMotion);
};
const updateInputs = (preferences) => {
    const density = document.querySelector('[data-gct-preference="density"]');
    const motion = document.querySelector('[data-gct-preference="reducedMotion"]');
    if (density) density.value = preferences.density;
    if (motion) motion.checked = preferences.reducedMotion;
};
const renderPreferences = () => {
    const preferences = loadPreferences();
    applyPreferences(preferences);
    updateInputs(preferences);
};
const announce = (message) => {
    const status = document.querySelector('[data-gct-preferences-status]');
    if (status) status.textContent = message;
};
document.addEventListener('change', (event) => {
    const control = event.target.closest('[data-gct-preference]');
    if (!control || !keyForCurrentUser()) return;
    const preferences = loadPreferences();
    if (control.dataset.gctPreference === 'density') {
        preferences.density = control.value === 'compact' ? 'compact' : 'comfortable';
    } else if (control.dataset.gctPreference === 'reducedMotion') {
        preferences.reducedMotion = control.checked;
    }
    try {
        localStorage.setItem(keyForCurrentUser(), JSON.stringify(preferences));
        renderPreferences();
        announce('Display preferences saved on this browser.');
    } catch {
        announce('This browser did not allow saving preferences.');
    }
});
document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-gct-reset-preferences]') || !keyForCurrentUser()) return;
    try {
        localStorage.removeItem(keyForCurrentUser());
        renderPreferences();
        announce('Display preferences reset for this browser.');
    } catch {
        announce('Unable to reset browser preferences.');
    }
});
document.addEventListener('DOMContentLoaded', renderPreferences);
window.addEventListener('gct:navigation-ready', renderPreferences);
if (document.readyState !== 'loading') renderPreferences();
