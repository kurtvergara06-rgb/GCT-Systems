const regionSelector = (name) => `[data-ajax-region="${CSS.escape(String(name))}"]`;

const getRegion = (name, root = document) => root.querySelector(regionSelector(name));

const replaceRegion = (name, html, root = document) => {
    const current = getRegion(name, root);
    if (!current) return null;

    const template = document.createElement('template');
    template.innerHTML = String(html).trim();
    const replacement = template.content.firstElementChild;

    if (!replacement) return null;

    current.replaceWith(replacement);

    document.dispatchEvent(new CustomEvent('system:region-replaced', {
        detail: { name, element: replacement },
    }));

    return replacement;
};

const setRegionLoading = (name, loading = true, root = document) => {
    const region = getRegion(name, root);
    if (!region) return null;

    region.toggleAttribute('aria-busy', Boolean(loading));
    region.classList.toggle('is-region-loading', Boolean(loading));
    return region;
};

let inFlightRefresh = null;
let lastRefreshCompletedAt = 0;

/**
 * Fetch fresh HTML from the server and replace data-ajax-region elements.
 * Preserves current URL parameters, filters, and state.
 * @param {string} url
 * @param {string[]|null} regionNames
 * @returns {Promise<boolean>}
 */
const refreshRegions = async (url = window.location.href, regionNames = null) => {
    // If a refresh just completed within 200ms for the same URL, avoid double-fetching
    const now = Date.now();
    if (inFlightRefresh) {
        return inFlightRefresh;
    }

    inFlightRefresh = (async () => {
        const allRegions = Array.from(document.querySelectorAll('[data-ajax-region]'));
        const availableNames = [...new Set(allRegions.map((el) => el.dataset.ajaxRegion).filter(Boolean))];

        const targetNames = Array.isArray(regionNames) && regionNames.length > 0
            ? regionNames.filter((name) => availableNames.includes(name))
            : availableNames;

        if (targetNames.length === 0) {
            return false;
        }

        targetNames.forEach((name) => setRegionLoading(name, true));

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`Region refresh failed with status ${response.status}.`);
            }

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            let replacedCount = 0;

            targetNames.forEach((name) => {
                const selector = regionSelector(name);
                const source = parsed.querySelector(selector);
                if (!source) return;

                if (replaceRegion(name, source.outerHTML)) {
                    replacedCount += 1;
                }
            });

            if (replacedCount > 0) {
                const detail = { regions: targetNames, url };
                window.dispatchEvent(new CustomEvent('system-regions-refreshed', { detail }));
                window.dispatchEvent(new CustomEvent('ajax:content-updated', { detail }));
            }

            lastRefreshCompletedAt = Date.now();
            return replacedCount > 0;
        } catch (error) {
            console.warn('GCTRegions refresh failed:', error);
            return false;
        } finally {
            targetNames.forEach((name) => setRegionLoading(name, false));
        }
    })();

    try {
        return await inFlightRefresh;
    } finally {
        inFlightRefresh = null;
    }
};

window.GCTRegions = Object.freeze({
    get: getRegion,
    replace: replaceRegion,
    setLoading: setRegionLoading,
    refresh: refreshRegions,
});

window.refreshGCTAjaxRegions = refreshRegions;

export { getRegion, replaceRegion, setRegionLoading, refreshRegions };
