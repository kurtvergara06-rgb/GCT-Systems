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

window.GCTRegions = Object.freeze({
    get: getRegion,
    replace: replaceRegion,
    setLoading: setRegionLoading,
});
