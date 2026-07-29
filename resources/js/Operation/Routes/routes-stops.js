document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    /* =========================================================
       HELPERS AND ELEMENTS
    ========================================================= */

    const $ = (id) => document.getElementById(id);
    const body = document.body;

    const routeModal = $('routeModal');
    const routeDetailsModal = $('routeDetailsModal');
    const deleteRouteModal = $('deleteRouteModal');
    const validationModal = $('routeValidationModal');

    const routeForm = $('routeForm');
    const routeFormMethod = $('routeFormMethod');
    const routeModalTitle = $('routeModalTitle');

    const routeCode = $('routeCode');
    const routeName = $('routeName');
    const routeOrigin = $('routeOrigin');
    const routeDestination = $('routeDestination');
    const routeDistance = $('routeDistance');
    const routeTime = $('routeTime');
    const routeStatus = $('routeStatus');

    const routeStopList = $('routeStopList');
    const saveRouteButton = $('saveRouteBtn');
    const saveRouteText = $('saveRouteText');
    const routeSearch = $('routeSearch');

    /* =========================================================
       ADD / EDIT MAP ELEMENTS
    ========================================================= */

    const mapElement = $('routeFormGpsMap');
    const mapMessage = $('routeFormGpsMapMessage');

    const fitMapButton = $('fitRouteFormGpsMap');
    const pinButton = $('pinActiveLocationBtn');
    const recalculateButton = $('recalculateRouteBtn');
    const activeFieldLabel = $('routeMapActiveField');

    const calculationSummary = $('routeCalculationSummary');
    const calculatedDistanceText = $('routeCalculatedDistanceText');
    const calculatedTimeText = $('routeCalculatedTimeText');
    const useCalculatedValuesButton = $('useCalculatedValuesBtn');

    /* =========================================================
       VIEW ROUTE MAP ELEMENTS
    ========================================================= */

    const viewMapElement = $('gpsTripMap');
    const viewFitMapButton = $('fitGpsMap');
    const viewMapMessage = $('gpsMapMessage');

    /* =========================================================
       HIDDEN FORM FIELDS
    ========================================================= */

    const hidden = {
        origin: {
            address: $('routeOriginAddress'),
            latitude: $('routeOriginLatitude'),
            longitude: $('routeOriginLongitude'),
            source: $('routeOriginSource'),
        },

        destination: {
            address: $('routeDestinationAddress'),
            latitude: $('routeDestinationLatitude'),
            longitude: $('routeDestinationLongitude'),
            source: $('routeDestinationSource'),
        },

        calculatedDistance: $('routeCalculatedDistance'),
        calculatedTime: $('routeCalculatedTime'),
        distanceSource: $('routeDistanceSource'),
        distanceManual: $('routeDistanceManual'),
        timeManual: $('routeTimeManual'),
        geometry: $('routeGeometry'),
    };

    /* =========================================================
       SERVER DATA
    ========================================================= */

    const gpsLocations = parseJson(
        $('gpsLocationSuggestionsData')
    )
        .map(normalizePlace)
        .filter(Boolean);

    const config = parseJsonObject(
        $('routeMapConfigData')
    );

    /* =========================================================
       STATE
    ========================================================= */

    const searchCache = new Map();
    const autocompleteControllers = new WeakMap();

    let map = null;
    let mapMarkerLayer = null;
    let mapRoadLayer = null;
    let mapBounds = null;

    let viewMap = null;
    let viewMarkerLayer = null;
    let viewRoadLayer = null;
    let viewMapBounds = null;

    let activeLocationInput = null;
    let pinMode = false;

    let routeRequestController = null;
    let selectedDeleteForm = null;

    let distanceEdited = false;
    let timeEdited = false;
    let lastCalculated = null;

    const originalAction = routeForm?.action || '';
    const originalRouteCode = routeCode?.value || 'R-01';

    /* =========================================================
       INITIAL SETUP
    ========================================================= */

    setupLocationInput(
        routeOrigin,
        'Origin',
        hidden.origin
    );

    setupLocationInput(
        routeDestination,
        'Destination',
        hidden.destination
    );

    getStopInputs().forEach((input) => {
        setupStopInput(input);
    });

    /* =========================================================
       EVENT LISTENERS
    ========================================================= */

    $('openRouteModal')?.addEventListener(
        'click',
        openCreateRouteModal
    );

    $('addRouteStop')?.addEventListener(
        'click',
        () => addStopInput('', true)
    );

    routeStopList?.addEventListener(
        'click',
        handleStopListClick
    );

    routeDistance?.addEventListener('input', () => {
        distanceEdited = true;
        setValue(hidden.distanceManual, '1');
    });

    routeTime?.addEventListener('input', () => {
        timeEdited = true;
        setValue(hidden.timeManual, '1');
    });

    useCalculatedValuesButton?.addEventListener(
        'click',
        () => {
            if (!lastCalculated) {
                return;
            }

            applyCalculatedValues(
                lastCalculated,
                true
            );
        }
    );

    fitMapButton?.addEventListener('click', () => {
        fitMapToBounds(
            map,
            mapBounds,
            15
        );
    });

    viewFitMapButton?.addEventListener('click', () => {
        fitMapToBounds(
            viewMap,
            viewMapBounds,
            15
        );
    });

    pinButton?.addEventListener(
        'click',
        enableManualPinMode
    );

    recalculateButton?.addEventListener(
        'click',
        () => calculateRoadRoute(true)
    );

    document.addEventListener(
        'click',
        handleDocumentClick
    );

    document.addEventListener(
        'keydown',
        handleEscapeKey
    );

    document
        .querySelectorAll('[data-close-route-modal]')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => closeModal(routeModal)
            );
        });

    document
        .querySelectorAll('[data-close-route-details]')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => closeModal(routeDetailsModal)
            );
        });

    $('cancelDeleteRoute')?.addEventListener(
        'click',
        () => closeModal(deleteRouteModal)
    );

    $('confirmDeleteRoute')?.addEventListener(
        'click',
        () => selectedDeleteForm?.requestSubmit()
    );

    $('closeRouteValidationModal')?.addEventListener(
        'click',
        () => closeModal(validationModal)
    );

    routeSearch?.addEventListener(
        'input',
        filterRouteTable
    );

    routeForm?.addEventListener(
        'submit',
        validateRouteFormBeforeSubmit
    );

    /* =========================================================
       CREATE ROUTE
    ========================================================= */

    function openCreateRouteModal() {
        resetCreateForm();
        openModal(routeModal);

        window.setTimeout(async () => {
            await initializeMap();
            map?.invalidateSize();
        }, 150);
    }

    function resetCreateForm() {
        if (!routeForm) {
            return;
        }

        routeForm.reset();
        routeForm.action = originalAction;

        if (routeFormMethod) {
            routeFormMethod.disabled = true;
            routeFormMethod.value = 'POST';
        }

        setTextElement(
            routeModalTitle,
            'New Route'
        );

        setTextElement(
            saveRouteText,
            'Save Route'
        );

        setValue(
            routeCode,
            originalRouteCode
        );

        setValue(
            routeStatus,
            'Active'
        );

        populateStops([]);

        getAllLocationInputs().forEach(
            clearPlace
        );

        resetCalculation();
        clearFormMap();

        activeLocationInput = null;
        pinMode = false;

        if (pinButton) {
            pinButton.disabled = true;
        }

        if (activeFieldLabel) {
            activeFieldLabel.innerHTML = `
                <i class="fa-solid fa-location-crosshairs"></i>
                Click an Origin, Stop, or Destination field first.
            `;
        }
    }

    /* =========================================================
       EDIT ROUTE
    ========================================================= */

    function prepareEditRoute(route) {
        if (!routeForm) {
            return;
        }

        routeForm.reset();
        routeForm.action = route.updateUrl;

        if (routeFormMethod) {
            routeFormMethod.disabled = false;
            routeFormMethod.value = 'PUT';
        }

        setTextElement(
            routeModalTitle,
            'Edit Route'
        );

        setTextElement(
            saveRouteText,
            'Update Route'
        );

        setValue(routeCode, route.routeCode);
        setValue(routeName, route.routeName);
        setValue(routeOrigin, route.origin);

        setValue(
            routeDestination,
            route.destination
        );

        setValue(
            routeDistance,
            route.distance
        );

        setValue(
            routeTime,
            route.time
        );

        setValue(
            routeStatus,
            route.status
        );

        populateStops(route.stops);

        restorePlace(
            routeOrigin,
            route.originMeta
        );

        restorePlace(
            routeDestination,
            route.destinationMeta
        );

        route.stops.forEach((stop, index) => {
            restorePlace(
                getStopInputs()[index],
                stop
            );
        });

        const savedGeometry =
            normalizeRouteGeometry(
                route.routeGeometry
            );

        setValue(
            hidden.geometry,
            savedGeometry
                ? JSON.stringify(savedGeometry)
                : ''
        );

        lastCalculated = {
            distance_km:
                route.calculatedDistance ||
                route.distance,

            duration_minutes:
                route.calculatedTime ||
                route.time,

            source:
                route.distanceSource ||
                'OSRM',

            geometry:
                savedGeometry,
        };

        openModal(routeModal);

        window.setTimeout(async () => {
            await initializeMap();
            map?.invalidateSize();

            if (savedGeometry) {
                drawRoadGeometry(savedGeometry);
            } else {
                renderFormMarkers();
            }
        }, 150);
    }

    /* =========================================================
       DOCUMENT CLICK HANDLER
    ========================================================= */

    function handleDocumentClick(event) {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const feedbackClose =
            target.closest(
                '[data-close-feedback]'
            );

        if (feedbackClose) {
            const feedbackModal =
                feedbackClose.closest(
                    '.feedback-modal-overlay'
                );

            closeModal(feedbackModal);
            return;
        }

        const editButton =
            target.closest(
                '.edit-route-btn'
            );

        if (editButton) {
            prepareEditRoute(
                getRouteData(editButton)
            );

            return;
        }

        const viewButton =
            target.closest(
                '.open-route-details'
            );

        if (viewButton) {
            showRouteDetails(
                getRouteData(viewButton)
            );

            return;
        }

        const deleteButton =
            target.closest(
                '.open-delete-route-modal'
            );

        if (deleteButton) {
            prepareDeleteRoute(deleteButton);
            return;
        }

        if (
            !target.closest(
                '.location-autocomplete'
            )
        ) {
            closeAllSuggestionPanels();
        }
    }

    /* =========================================================
       STOPS
    ========================================================= */

    function handleStopListClick(event) {
        const removeButton =
            event.target.closest(
                '.remove-route-stop'
            );

        if (!removeButton) {
            return;
        }

        const row =
            removeButton.closest(
                '.route-stop-item'
            );

        if (!row) {
            return;
        }

        const rows = getStopRows();

        if (rows.length === 1) {
            const input =
                row.querySelector(
                    'input[name="stops[]"]'
                );

            if (input) {
                input.value = '';
                clearPlace(input);
            }
        } else {
            row.remove();
        }

        updateStopNumbers();
        locationsChanged();
    }

    function addStopInput(
        value = '',
        focus = false,
        stop = null
    ) {
        if (!routeStopList) {
            return;
        }

        const row =
            document.createElement('div');

        row.className =
            'ui-form-repeater-row route-stop-item';

        row.innerHTML = `
            <div
                class="ui-form-repeater-number route-stop-number"
            ></div>

            <div class="route-stop-location-field">
                <input
                    type="text"
                    name="stops[]"
                    placeholder="Search or enter a stop"
                    autocomplete="off"
                >

                <input
                    type="hidden"
                    name="stop_addresses[]"
                >

                <input
                    type="hidden"
                    name="stop_latitudes[]"
                >

                <input
                    type="hidden"
                    name="stop_longitudes[]"
                >

                <input
                    type="hidden"
                    name="stop_sources[]"
                >
            </div>

            <button
                type="button"
                class="ui-form-repeater-remove remove-route-stop"
                title="Remove Stop"
            >
                <i class="fa-solid fa-trash"></i>
            </button>
        `;

        routeStopList.appendChild(row);

        const input =
            row.querySelector(
                'input[name="stops[]"]'
            );

        input.value =
            typeof value === 'string'
                ? value
                : value?.name || '';

        setupStopInput(input);

        if (stop) {
            restorePlace(input, stop);
        }

        updateStopNumbers();

        if (focus) {
            input.focus();
        }
    }

    function populateStops(stops) {
        if (!routeStopList) {
            return;
        }

        routeStopList.innerHTML = '';

        if (
            !Array.isArray(stops) ||
            stops.length === 0
        ) {
            addStopInput('');
            return;
        }

        stops.forEach((stop) => {
            addStopInput(
                stop.name || stop,
                false,
                typeof stop === 'object'
                    ? stop
                    : null
            );
        });
    }

    function updateStopNumbers() {
        getStopRows().forEach((row, index) => {
            const number =
                row.querySelector(
                    '.route-stop-number'
                );

            const input =
                row.querySelector(
                    'input[name="stops[]"]'
                );

            if (number) {
                number.textContent =
                    String(index + 1);
            }

            if (input) {
                input.dataset.role =
                    `Stop ${index + 1}`;
            }
        });
    }

    /* =========================================================
       AUTOCOMPLETE SETUP
    ========================================================= */

    function setupLocationInput(
        input,
        role,
        hiddenFields = null
    ) {
        if (
            !(input instanceof HTMLInputElement) ||
            input.dataset.locationReady === '1'
        ) {
            return;
        }

        input.dataset.locationReady = '1';
        input.dataset.role = role;
        input.autocomplete = 'off';

        if (hiddenFields) {
            input._routeHidden =
                hiddenFields;
        }

        wrapAutocomplete(input);
    }

    function setupStopInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const row =
            input.closest(
                '.route-stop-item'
            );

        input._routeHidden = {
            address:
                row?.querySelector(
                    'input[name="stop_addresses[]"]'
                ),

            latitude:
                row?.querySelector(
                    'input[name="stop_latitudes[]"]'
                ),

            longitude:
                row?.querySelector(
                    'input[name="stop_longitudes[]"]'
                ),

            source:
                row?.querySelector(
                    'input[name="stop_sources[]"]'
                ),
        };

        setupLocationInput(
            input,
            input.dataset.role || 'Stop',
            input._routeHidden
        );
    }

    function wrapAutocomplete(input) {
        let wrapper =
            input.closest(
                '.location-autocomplete'
            );

        if (!wrapper) {
            wrapper =
                document.createElement('div');

            wrapper.className =
                'location-autocomplete';

            input.parentNode.insertBefore(
                wrapper,
                input
            );

            wrapper.appendChild(input);
        }

        let status =
            wrapper.querySelector(
                '.location-confirmation-status'
            );

        if (!status) {
            status =
                document.createElement('div');

            status.className =
                'location-confirmation-status';

            wrapper.appendChild(status);
        }

        let panel =
            wrapper.querySelector(
                '.location-suggestion-panel'
            );

        if (!panel) {
            panel =
                document.createElement('div');

            panel.className =
                'location-suggestion-panel';

            panel.hidden = true;

            panel.setAttribute(
                'role',
                'listbox'
            );

            wrapper.appendChild(panel);
        }

        let timer = null;
        let highlightedIndex = -1;
        let results = [];
        let abortController = null;

        const closePanel = () => {
            panel.hidden = true;
            panel.innerHTML = '';
            highlightedIndex = -1;
        };

        autocompleteControllers.set(
            input,
            {
                panel,
                status,
                closePanel,
            }
        );

        input.addEventListener('focus', () => {
            setActiveInput(input);

            if (input.value.trim()) {
                runSearch();
            }
        });

        input.addEventListener('click', () => {
            setActiveInput(input);
        });

        input.addEventListener('input', () => {
            setActiveInput(input);

            if (
                input.dataset.selectedName &&
                normalize(input.value) !==
                normalize(
                    input.dataset.selectedName
                )
            ) {
                clearPlace(input);
                locationsChanged();
            }

            window.clearTimeout(timer);

            const localResults =
                searchGps(input.value);

            results = localResults;

            renderSuggestionPanel(
                panel,
                results,
                input.value,
                false
            );

            panel.hidden = false;

            if (
                input.value.trim().length >= 3
            ) {
                timer = window.setTimeout(
                    runSearch,
                    900
                );
            }
        });

        input.addEventListener(
            'keydown',
            (event) => {
                if (panel.hidden) {
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();

                    highlightedIndex =
                        Math.min(
                            highlightedIndex + 1,
                            results.length - 1
                        );

                    highlightSuggestion(
                        panel,
                        highlightedIndex
                    );
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();

                    highlightedIndex =
                        Math.max(
                            highlightedIndex - 1,
                            0
                        );

                    highlightSuggestion(
                        panel,
                        highlightedIndex
                    );
                }

                if (
                    event.key === 'Enter' &&
                    highlightedIndex >= 0
                ) {
                    event.preventDefault();

                    const place =
                        results[highlightedIndex];

                    if (place) {
                        choosePlace(
                            input,
                            place
                        );
                    }

                    closePanel();
                }

                if (event.key === 'Escape') {
                    closePanel();
                }
            }
        );

        panel.addEventListener(
            'mousedown',
            (event) => {
                event.preventDefault();
            }
        );

        panel.addEventListener(
            'click',
            (event) => {
                const item =
                    event.target.closest(
                        '[data-place-index]'
                    );

                if (!item) {
                    return;
                }

                const place =
                    results[
                        Number(
                            item.dataset.placeIndex
                        )
                    ];

                if (place) {
                    choosePlace(
                        input,
                        place
                    );
                }

                closePanel();
            }
        );

        async function runSearch() {
            const query =
                input.value.trim();

            const localResults =
                searchGps(query);

            results = localResults;

            renderSuggestionPanel(
                panel,
                results,
                query,
                query.length >= 3
            );

            panel.hidden = false;

            if (query.length < 3) {
                return;
            }

            abortController?.abort();

            abortController =
                new AbortController();

            try {
                const remoteResults =
                    await searchRemote(
                        query,
                        abortController.signal
                    );

                if (
                    input.value.trim() !== query
                ) {
                    return;
                }

                results = dedupePlaces([
                    ...localResults,
                    ...remoteResults,
                ]);

                renderSuggestionPanel(
                    panel,
                    results,
                    query,
                    false
                );
            } catch (error) {
                if (
                    error.name !== 'AbortError'
                ) {
                    renderSuggestionPanel(
                        panel,
                        results,
                        query,
                        false,
                        'Map search is temporarily unavailable.'
                    );
                }
            }
        }
    }

    /* =========================================================
       AUTOCOMPLETE RESULTS
    ========================================================= */

    function renderSuggestionPanel(
        panel,
        places,
        query,
        loading = false,
        error = ''
    ) {
        panel.innerHTML = '';

        if (places.length) {
            let previousGroup = '';

            places.forEach((place, index) => {
                const group =
                    place.source === 'GPS Batch'
                        ? 'Saved GPS locations'
                        : 'OpenStreetMap results';

                if (group !== previousGroup) {
                    const heading =
                        document.createElement(
                            'div'
                        );

                    heading.className =
                        'location-suggestion-heading';

                    heading.textContent =
                        group;

                    panel.appendChild(heading);
                    previousGroup = group;
                }

                const button =
                    document.createElement(
                        'button'
                    );

                button.type = 'button';

                button.className =
                    'location-suggestion-item';

                button.dataset.placeIndex =
                    String(index);

                button.innerHTML = `
                    <span class="location-suggestion-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </span>

                    <span class="location-suggestion-copy">
                        <strong>
                            ${highlightMatch(
                                place.name,
                                query
                            )}
                        </strong>

                        <small>
                            ${escapeHtml(
                                place.address ||
                                place.name
                            )}
                        </small>
                    </span>

                    <span class="location-source-badge">
                        ${escapeHtml(place.source)}
                    </span>
                `;

                panel.appendChild(button);
            });
        }

        if (loading) {
            const loadingRow =
                document.createElement('div');

            loadingRow.className =
                'location-suggestion-loading';

            loadingRow.innerHTML = `
                <i class="fa-solid fa-spinner fa-spin"></i>
                Searching OpenStreetMap…
            `;

            panel.appendChild(loadingRow);
        } else if (!places.length) {
            const emptyRow =
                document.createElement('div');

            emptyRow.className =
                'location-suggestion-empty';

            emptyRow.innerHTML = `
                <i class="fa-solid fa-map-pin"></i>

                <div>
                    <strong>
                        No confirmed location found
                    </strong>

                    <span>
                        Try another search or use
                        “Pin Active Field” on the map.
                    </span>
                </div>
            `;

            panel.appendChild(emptyRow);
        }

        if (error) {
            const errorRow =
                document.createElement('div');

            errorRow.className =
                'location-suggestion-error';

            errorRow.textContent = error;

            panel.appendChild(errorRow);
        }
    }

    async function searchRemote(
        query,
        signal
    ) {
        const cacheKey =
            query.toLowerCase();

        if (searchCache.has(cacheKey)) {
            return searchCache.get(
                cacheKey
            );
        }

        if (!config.searchUrl) {
            return [];
        }

        const response = await fetch(
            `${config.searchUrl}?q=${encodeURIComponent(query)}`,
            {
                headers: {
                    Accept:
                        'application/json',

                    'X-Requested-With':
                        'XMLHttpRequest',
                },

                signal,
            }
        );

        const data =
            await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                'Location search failed.'
            );
        }

        const places =
            (data.results || [])
                .map(normalizePlace)
                .filter(Boolean);

        searchCache.set(
            cacheKey,
            places
        );

        return places;
    }

    function searchGps(query) {
        const normalizedQuery =
            normalize(query);

        if (!normalizedQuery) {
            return gpsLocations.slice(0, 8);
        }

        return gpsLocations
            .map((place) => {
                const normalizedName =
                    normalize(place.name);

                const normalizedAddress =
                    normalize(place.address);

                let score = 0;

                if (
                    normalizedName ===
                    normalizedQuery
                ) {
                    score = 100;
                } else if (
                    normalizedName.startsWith(
                        normalizedQuery
                    )
                ) {
                    score = 80;
                } else if (
                    normalizedName.includes(
                        normalizedQuery
                    )
                ) {
                    score = 60;
                } else if (
                    normalizedAddress.includes(
                        normalizedQuery
                    )
                ) {
                    score = 40;
                }

                return {
                    place,
                    score,
                };
            })
            .filter(
                (entry) => entry.score > 0
            )
            .sort(
                (a, b) =>
                    b.score - a.score
            )
            .slice(0, 8)
            .map(
                (entry) => entry.place
            );
    }

    /* =========================================================
       LOCATION SELECTION
    ========================================================= */

    function choosePlace(
        input,
        place
    ) {
        if (!input || !place) {
            return;
        }

        input.value = place.name;

        input.dataset.selectedName =
            place.name;

        input.dataset.latitude =
            String(place.latitude);

        input.dataset.longitude =
            String(place.longitude);

        input.dataset.address =
            place.address || place.name;

        input.dataset.source =
            place.source;

        syncHiddenFields(input);
        updateConfirmation(input, true);
        setActiveInput(input);

        focusSelectedPlace(place);
        locationsChanged();
    }

    function restorePlace(
        input,
        place
    ) {
        if (
            !input ||
            !place ||
            !isValidCoordinate(
                place.latitude,
                place.longitude
            )
        ) {
            return;
        }

        input.dataset.selectedName =
            input.value;

        input.dataset.address =
            place.address || input.value;

        input.dataset.latitude =
            String(place.latitude);

        input.dataset.longitude =
            String(place.longitude);

        input.dataset.source =
            place.source ||
            place.location_source ||
            'Saved Route';

        syncHiddenFields(input);
        updateConfirmation(input, true);
    }

    function clearPlace(input) {
        if (!input) {
            return;
        }

        delete input.dataset.selectedName;
        delete input.dataset.latitude;
        delete input.dataset.longitude;
        delete input.dataset.address;
        delete input.dataset.source;

        syncHiddenFields(input);
        updateConfirmation(input, false);
    }

    function syncHiddenFields(input) {
        const fields =
            input._routeHidden || {};

        setValue(
            fields.address,
            input.dataset.address || ''
        );

        setValue(
            fields.latitude,
            input.dataset.latitude || ''
        );

        setValue(
            fields.longitude,
            input.dataset.longitude || ''
        );

        setValue(
            fields.source,
            input.dataset.source || ''
        );
    }

    function updateConfirmation(
        input,
        confirmed
    ) {
        const status =
            autocompleteControllers
                .get(input)
                ?.status;

        if (!status) {
            return;
        }

        status.className =
            `location-confirmation-status ${
                confirmed
                    ? 'confirmed'
                    : 'unconfirmed'
            }`;

        if (confirmed) {
            status.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>

                <span>
                    Confirmed •
                    ${escapeHtml(
                        input.dataset.source || ''
                    )}
                </span>
            `;

            return;
        }

        status.innerHTML =
            input.value.trim()
                ? `
                    <i class="fa-solid fa-triangle-exclamation"></i>

                    <span>
                        Select a suggestion or pin this location.
                    </span>
                `
                : '';
    }

    function setActiveInput(input) {
        activeLocationInput = input;

        if (pinButton) {
            pinButton.disabled = false;
        }

        if (activeFieldLabel) {
            activeFieldLabel.innerHTML = `
                <i class="fa-solid fa-location-crosshairs"></i>
                Active: ${escapeHtml(fieldRole(input))}
            `;
        }
    }

    function closeAllSuggestionPanels() {
        document
            .querySelectorAll(
                '.location-suggestion-panel'
            )
            .forEach((panel) => {
                panel.hidden = true;
            });
    }

    /* =========================================================
       MANUAL PIN
    ========================================================= */

    async function enableManualPinMode() {
        if (!activeLocationInput) {
            return;
        }

        await initializeMap();

        pinMode = true;

        map
            .getContainer()
            .classList
            .add('route-map-pin-mode');

        setMapMessage(
            `Click the map to position ${fieldRole(activeLocationInput)}.`,
            'warning'
        );
    }

    /* =========================================================
       ADD / EDIT MAP
    ========================================================= */

    async function initializeMap() {
        if (!mapElement) {
            return;
        }

        await loadLeaflet();
        fixDefaultLeafletIcons();

        if (!map) {
            map = L.map(
                mapElement,
                {
                    zoomControl: true,
                    attributionControl: true,
                }
            ).setView(
                [13.94, 121.16],
                9
            );

            addOpenStreetMapTiles(map);

            map.on('click', (event) => {
                if (
                    !pinMode ||
                    !activeLocationInput
                ) {
                    return;
                }

                pinMode = false;

                map
                    .getContainer()
                    .classList
                    .remove(
                        'route-map-pin-mode'
                    );

                const place = {
                    name:
                        activeLocationInput
                            .value
                            .trim() ||
                        fieldRole(
                            activeLocationInput
                        ),

                    address:
                        activeLocationInput
                            .value
                            .trim() ||
                        'Manually pinned location',

                    latitude:
                        event.latlng.lat,

                    longitude:
                        event.latlng.lng,

                    source:
                        'Manual Pin',
                };

                choosePlace(
                    activeLocationInput,
                    place
                );
            });
        }

        window.setTimeout(
            () => map?.invalidateSize(),
            100
        );
    }

    function focusSelectedPlace(place) {
        initializeMap().then(() => {
            renderFormMarkers();

            map.setView(
                [
                    Number(place.latitude),
                    Number(place.longitude),
                ],
                16,
                {
                    animate: true,
                }
            );
        });
    }

    /* =========================================================
       ROUTE CALCULATION
    ========================================================= */

    function locationsChanged() {
        distanceEdited = false;
        timeEdited = false;

        setValue(
            hidden.distanceManual,
            '0'
        );

        setValue(
            hidden.timeManual,
            '0'
        );

        setValue(
            hidden.geometry,
            ''
        );

        renderFormMarkers();
        calculateRoadRoute(false);
    }

    async function calculateRoadRoute(
        force = false
    ) {
        const inputs =
            getAllLocationInputs()
                .filter(
                    (input) =>
                        input.value.trim()
                );

        const points =
            inputs
                .filter(hasCoordinates)
                .map((input) => ({
                    latitude:
                        Number(
                            input.dataset.latitude
                        ),

                    longitude:
                        Number(
                            input.dataset.longitude
                        ),
                }));

        const hasIncompleteLocation =
            inputs.length !== points.length;

        if (
            !hasCoordinates(routeOrigin) ||
            !hasCoordinates(
                routeDestination
            ) ||
            hasIncompleteLocation
        ) {
            if (recalculateButton) {
                recalculateButton.disabled =
                    true;
            }

            setMapMessage(
                'Confirm Origin, every entered Stop, and Destination to calculate the road route.',
                'warning'
            );

            renderFormMarkers();
            return;
        }

        if (recalculateButton) {
            recalculateButton.disabled =
                false;
        }

        if (!config.routingUrl) {
            setMapMessage(
                'The routing URL is not configured.',
                'error'
            );

            return;
        }

        routeRequestController?.abort();

        routeRequestController =
            new AbortController();

        setMapMessage(
            'Calculating road distance and travel time…',
            'info'
        );

        try {
            const response = await fetch(
                config.routingUrl,
                {
                    method: 'POST',

                    headers: {
                        Accept:
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN':
                            config.csrfToken,

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },

                    body:
                        JSON.stringify({
                            points,
                        }),

                    signal:
                        routeRequestController
                            .signal,
                }
            );

            const data =
                await response.json();

            if (!response.ok) {
                const validationMessage =
                    Object.values(
                        data.errors || {}
                    )[0]?.[0];

                throw new Error(
                    data.message ||
                    validationMessage ||
                    'Route calculation failed.'
                );
            }

            lastCalculated = data;

            applyCalculatedValues(
                data,
                force
            );

            drawRoadGeometry(
                data.geometry
            );

            setMapMessage(
                `Route calculated successfully: ${data.distance_km} KM • ${data.duration_minutes} minutes.`,
                'success'
            );
        } catch (error) {
            if (
                error.name === 'AbortError'
            ) {
                return;
            }

            console.error(
                'Route calculation failed:',
                error
            );

            setMapMessage(
                `${error.message} You may enter distance and time manually.`,
                'error'
            );

            renderFormMarkers();
        }
    }

    function applyCalculatedValues(
        data,
        force = false
    ) {
        const geometry =
            normalizeRouteGeometry(
                data.geometry
            );

        setValue(
            hidden.calculatedDistance,
            data.distance_km
        );

        setValue(
            hidden.calculatedTime,
            data.duration_minutes
        );

        setValue(
            hidden.distanceSource,
            data.source || 'OSRM'
        );

        setValue(
            hidden.geometry,
            geometry
                ? JSON.stringify(geometry)
                : ''
        );

        if (
            force ||
            !distanceEdited
        ) {
            setValue(
                routeDistance,
                data.distance_km
            );

            distanceEdited = false;

            setValue(
                hidden.distanceManual,
                '0'
            );
        }

        if (
            force ||
            !timeEdited
        ) {
            setValue(
                routeTime,
                data.duration_minutes
            );

            timeEdited = false;

            setValue(
                hidden.timeManual,
                '0'
            );
        }

        if (calculatedDistanceText) {
            calculatedDistanceText.textContent =
                `${data.distance_km} KM calculated`;
        }

        if (calculatedTimeText) {
            calculatedTimeText.textContent =
                `${data.duration_minutes} min calculated`;
        }

        if (calculationSummary) {
            calculationSummary.hidden =
                false;
        }
    }

    /* =========================================================
       ADD / EDIT MARKERS
    ========================================================= */

    function renderFormMarkers() {
        if (!map || !window.L) {
            return;
        }

        clearFormMap();

        const inputs =
            getAllLocationInputs()
                .filter(hasCoordinates);

        if (!inputs.length) {
            if (fitMapButton) {
                fitMapButton.disabled = true;
            }

            return;
        }

        const markers = inputs.map((input) => {
            const marker = L.marker([
                Number(input.dataset.latitude),
                Number(input.dataset.longitude),
            ]);

            marker.bindPopup(`
                <div class="gps-map-popup">
                    <strong>
                        ${escapeHtml(fieldRole(input))}
                    </strong>

                    <span>
                        ${escapeHtml(input.value)}
                    </span>

                    <small>
                        ${escapeHtml(
                            input.dataset.source || ''
                        )}
                    </small>
                </div>
            `);

            return marker;
        });

        mapMarkerLayer =
            L.featureGroup(markers)
                .addTo(map);

        mapBounds =
            mapMarkerLayer.getBounds();

        if (fitMapButton) {
            fitMapButton.disabled = false;
        }

        if (
            inputs.length > 1 &&
            mapBounds.isValid()
        ) {
            map.fitBounds(
                mapBounds,
                {
                    padding: [40, 40],
                    maxZoom: 14,
                }
            );
        }
    }

    function drawRoadGeometry(value) {
        renderFormMarkers();

        if (!map || !window.L) {
            return;
        }

        const geometry =
            normalizeRouteGeometry(value);

        const latLngs =
            getGeometryLatLngs(geometry);

        if (latLngs.length < 2) {
            console.warn(
                'No valid OSRM geometry was available.',
                value
            );

            return;
        }

        if (mapRoadLayer) {
            map.removeLayer(mapRoadLayer);
            mapRoadLayer = null;
        }

        const roadCasing =
            L.polyline(
                latLngs,
                {
                    color: '#ffffff',
                    weight: 11,
                    opacity: 0.98,
                    lineCap: 'round',
                    lineJoin: 'round',
                    interactive: false,
                }
            );

        const roadMain =
            L.polyline(
                latLngs,
                {
                    color: '#0b40b5',
                    weight: 6,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round',
                }
            );

        const roadHighlight =
            L.polyline(
                latLngs,
                {
                    color: '#60a5fa',
                    weight: 2,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round',
                    interactive: false,
                }
            );

        mapRoadLayer =
            L.layerGroup([
                roadCasing,
                roadMain,
                roadHighlight,
            ]).addTo(map);

        const boundsItems = [
            roadMain,
        ];

        if (mapMarkerLayer) {
            boundsItems.push(
                mapMarkerLayer
            );
        }

        mapBounds =
            L.featureGroup(
                boundsItems
            ).getBounds();

        fitMapToBounds(
            map,
            mapBounds,
            15
        );
    }

    function clearFormMap() {
        if (
            map &&
            mapMarkerLayer
        ) {
            map.removeLayer(
                mapMarkerLayer
            );
        }

        if (
            map &&
            mapRoadLayer
        ) {
            map.removeLayer(
                mapRoadLayer
            );
        }

        mapMarkerLayer = null;
        mapRoadLayer = null;
        mapBounds = null;
    }

    /* =========================================================
       VIEW ROUTE DETAILS
    ========================================================= */

    async function showRouteDetails(route) {
        setText(
            'viewRouteCode',
            route.routeCode || '—'
        );

        setText(
            'viewRouteName',
            route.routeName || '—'
        );

        setText(
            'viewRouteOrigin',
            route.origin || '—'
        );

        setText(
            'viewRouteDestination',
            route.destination || '—'
        );

        setText(
            'viewRouteDistance',
            route.distance
                ? `${Number(route.distance).toFixed(2)} KM`
                : '—'
        );

        setText(
            'viewRouteTime',
            route.time
                ? `${route.time} minutes`
                : '—'
        );

        renderViewStatus(
            route.status
        );

        renderViewRouteSequence(
            route
        );

        setText(
            'viewRouteStopCount',
            `${route.stops.length} ${
                route.stops.length === 1
                    ? 'Stop'
                    : 'Stops'
            }`
        );

        openModal(routeDetailsModal);

        window.setTimeout(async () => {
            try {
                await initializeViewMap();
                viewMap?.invalidateSize();

                drawSavedRouteInView(
                    route
                );
            } catch (error) {
                console.error(
                    'Unable to display saved route map:',
                    error
                );

                setViewMapMessage(
                    'The saved route map could not be loaded.',
                    'error'
                );
            }
        }, 150);
    }

    function renderViewStatus(status) {
        const container =
            $('viewRouteStatus');

        if (!container) {
            return;
        }

        const value =
            status || 'Inactive';

        container.innerHTML = `
            <span
                class="route-status ${escapeHtml(
                    value.toLowerCase()
                )}"
            >
                ${escapeHtml(value)}
            </span>
        `;
    }

    function renderViewRouteSequence(route) {
        const path =
            $('viewRoutePath');

        if (!path) {
            return;
        }

        path.innerHTML = '';

        const nodes = [
            {
                name: route.origin,
                role: 'Origin',
                type: 'origin',
            },

            ...route.stops.map(
                (stop, index) => ({
                    name:
                        stop.name ||
                        `Stop ${index + 1}`,

                    role:
                        `Stop ${index + 1}`,

                    type: 'stop',
                })
            ),

            {
                name: route.destination,
                role: 'Destination',
                type: 'destination',
            },
        ];

        nodes.forEach((node) => {
            const item =
                document.createElement(
                    'article'
                );

            item.className =
                `horizontal-route-node ${node.type}`;

            item.innerHTML = `
                <div
                    class="horizontal-route-marker"
                ></div>

                <div
                    class="horizontal-route-content"
                >
                    <strong>
                        ${escapeHtml(node.name)}
                    </strong>

                    <span>
                        ${escapeHtml(node.role)}
                    </span>
                </div>
            `;

            path.appendChild(item);
        });
    }

    /* =========================================================
       VIEW ROUTE MAP
    ========================================================= */

    async function initializeViewMap() {
        if (!viewMapElement) {
            return;
        }

        await loadLeaflet();
        fixDefaultLeafletIcons();

        if (!viewMap) {
            viewMap = L.map(
                viewMapElement,
                {
                    zoomControl: true,
                    attributionControl: true,
                }
            ).setView(
                [13.94, 121.16],
                9
            );

            addOpenStreetMapTiles(viewMap);
        }

        window.setTimeout(
            () => viewMap?.invalidateSize(),
            100
        );
    }

    function drawSavedRouteInView(route) {
        if (!viewMap || !window.L) {
            return;
        }

        clearViewMap();

        const points = [];

        if (
            isValidCoordinate(
                route.originMeta?.latitude,
                route.originMeta?.longitude
            )
        ) {
            points.push({
                label: 'Origin',
                name: route.origin,

                latitude:
                    Number(
                        route.originMeta.latitude
                    ),

                longitude:
                    Number(
                        route.originMeta.longitude
                    ),

                source:
                    route.originMeta.source ||
                    'Saved Route',
            });
        }

        route.stops.forEach(
            (stop, index) => {
                if (
                    !isValidCoordinate(
                        stop.latitude,
                        stop.longitude
                    )
                ) {
                    return;
                }

                points.push({
                    label:
                        `Stop ${index + 1}`,

                    name:
                        stop.name ||
                        `Stop ${index + 1}`,

                    latitude:
                        Number(stop.latitude),

                    longitude:
                        Number(stop.longitude),

                    source:
                        stop.source ||
                        stop.location_source ||
                        'Saved Route',
                });
            }
        );

        if (
            isValidCoordinate(
                route.destinationMeta?.latitude,
                route.destinationMeta?.longitude
            )
        ) {
            points.push({
                label: 'Destination',
                name: route.destination,

                latitude:
                    Number(
                        route.destinationMeta.latitude
                    ),

                longitude:
                    Number(
                        route.destinationMeta.longitude
                    ),

                source:
                    route.destinationMeta.source ||
                    'Saved Route',
            });
        }

        if (!points.length) {
            setViewMapMessage(
                'This route has no saved map coordinates.',
                'warning'
            );

            if (viewFitMapButton) {
                viewFitMapButton.disabled =
                    true;
            }

            return;
        }

        const markers =
            points.map((point) => {
                const marker = L.marker([
                    point.latitude,
                    point.longitude,
                ]);

                marker.bindPopup(`
                    <div class="gps-map-popup">
                        <strong>
                            ${escapeHtml(point.label)}
                        </strong>

                        <span>
                            ${escapeHtml(point.name)}
                        </span>

                        <small>
                            ${escapeHtml(point.source)}
                        </small>
                    </div>
                `);

                return marker;
            });

        viewMarkerLayer =
            L.featureGroup(markers)
                .addTo(viewMap);

        const geometry =
            normalizeRouteGeometry(
                route.routeGeometry
            );

        const roadLatLngs =
            getGeometryLatLngs(
                geometry
            );

        let visibleRoadLayer = null;

        if (roadLatLngs.length >= 2) {
            const roadCasing =
                L.polyline(
                    roadLatLngs,
                    {
                        color: '#ffffff',
                        weight: 11,
                        opacity: 0.98,
                        lineCap: 'round',
                        lineJoin: 'round',
                        interactive: false,
                    }
                );

            const roadMain =
                L.polyline(
                    roadLatLngs,
                    {
                        color: '#0b40b5',
                        weight: 6,
                        opacity: 0.95,
                        lineCap: 'round',
                        lineJoin: 'round',
                    }
                );

            const roadHighlight =
                L.polyline(
                    roadLatLngs,
                    {
                        color: '#60a5fa',
                        weight: 2,
                        opacity: 0.95,
                        lineCap: 'round',
                        lineJoin: 'round',
                        interactive: false,
                    }
                );

            viewRoadLayer =
                L.layerGroup([
                    roadCasing,
                    roadMain,
                    roadHighlight,
                ]).addTo(viewMap);

            visibleRoadLayer =
                roadMain;
        } else if (points.length >= 2) {
            const fallback =
                points.map(
                    (point) => [
                        point.latitude,
                        point.longitude,
                    ]
                );

            const fallbackCasing =
                L.polyline(
                    fallback,
                    {
                        color: '#ffffff',
                        weight: 10,
                        opacity: 0.95,
                        interactive: false,
                    }
                );

            const fallbackMain =
                L.polyline(
                    fallback,
                    {
                        color: '#0b40b5',
                        weight: 5,
                        opacity: 0.9,
                        dashArray: '10 8',
                    }
                );

            viewRoadLayer =
                L.layerGroup([
                    fallbackCasing,
                    fallbackMain,
                ]).addTo(viewMap);

            visibleRoadLayer =
                fallbackMain;
        }

        const boundsItems = [
            viewMarkerLayer,
        ];

        if (visibleRoadLayer) {
            boundsItems.push(
                visibleRoadLayer
            );
        }

        viewMapBounds =
            L.featureGroup(
                boundsItems
            ).getBounds();

        fitMapToBounds(
            viewMap,
            viewMapBounds,
            15
        );

        if (viewFitMapButton) {
            viewFitMapButton.disabled =
                false;
        }

        const hasActualGeometry =
            roadLatLngs.length >= 2;

        setViewMapMessage(
            hasActualGeometry
                ? 'Actual saved OSRM road route displayed.'
                : 'No saved OSRM geometry was found. A reference path is displayed.',

            hasActualGeometry
                ? 'success'
                : 'warning'
        );
    }

    function clearViewMap() {
        if (
            viewMap &&
            viewMarkerLayer
        ) {
            viewMap.removeLayer(
                viewMarkerLayer
            );
        }

        if (
            viewMap &&
            viewRoadLayer
        ) {
            viewMap.removeLayer(
                viewRoadLayer
            );
        }

        viewMarkerLayer = null;
        viewRoadLayer = null;
        viewMapBounds = null;
    }

    /* =========================================================
       DELETE ROUTE
    ========================================================= */

    function prepareDeleteRoute(button) {
        selectedDeleteForm =
            document.getElementById(
                button.dataset.formId || ''
            );

        setText(
            'deleteRouteName',
            [
                button.dataset.routeCode,
                button.dataset.routeName,
            ]
                .filter(Boolean)
                .join(' - ') ||
            'this route'
        );

        openModal(deleteRouteModal);
    }

    /* =========================================================
       VALIDATION
    ========================================================= */

    function validateRouteFormBeforeSubmit(
        event
    ) {
        const invalidLocations =
            getAllLocationInputs()
                .filter(
                    (input) =>
                        input.value.trim() &&
                        !hasCoordinates(input)
                );

        if (
            !hasCoordinates(routeOrigin) ||
            !hasCoordinates(
                routeDestination
            ) ||
            invalidLocations.length
        ) {
            event.preventDefault();

            setMapMessage(
                'Confirm Origin, every entered Stop, and Destination by selecting a suggestion or pinning them on the map.',
                'error'
            );

            const firstInvalid =
                invalidLocations[0] ||
                (
                    !hasCoordinates(routeOrigin)
                        ? routeOrigin
                        : routeDestination
                );

            firstInvalid?.focus();
            return;
        }

        if (saveRouteButton) {
            saveRouteButton.disabled = true;
        }

        if (saveRouteText) {
            saveRouteText.textContent =
                routeFormMethod?.disabled
                    ? 'Saving...'
                    : 'Updating...';
        }
    }

    /* =========================================================
       TABLE SEARCH
    ========================================================= */

    function filterRouteTable() {
        const query =
            routeSearch
                ?.value
                .trim()
                .toLowerCase() || '';

        document
            .querySelectorAll(
                '.routes-table tbody tr'
            )
            .forEach((row) => {
                row.hidden =
                    query !== '' &&
                    !row
                        .textContent
                        .toLowerCase()
                        .includes(query);
            });
    }

    /* =========================================================
       ROUTE DATA
    ========================================================= */

    function getRouteData(button) {
        return {
            routeCode:
                button.dataset.routeCode ||
                '',

            routeName:
                button.dataset.routeName ||
                '',

            origin:
                button.dataset.origin ||
                '',

            destination:
                button.dataset.destination ||
                '',

            distance:
                button.dataset.distance ||
                '',

            time:
                button.dataset.time ||
                '',

            calculatedDistance:
                button.dataset.calculatedDistance ||
                '',

            calculatedTime:
                button.dataset.calculatedTime ||
                '',

            distanceSource:
                button.dataset.distanceSource ||
                '',

            status:
                button.dataset.status ||
                'Active',

            updateUrl:
                button.dataset.updateUrl ||
                '',

            originMeta: {
                address:
                    button.dataset.originAddress,

                latitude:
                    button.dataset.originLatitude,

                longitude:
                    button.dataset.originLongitude,

                source:
                    button.dataset.originSource,
            },

            destinationMeta: {
                address:
                    button.dataset.destinationAddress,

                latitude:
                    button.dataset.destinationLatitude,

                longitude:
                    button.dataset.destinationLongitude,

                source:
                    button.dataset.destinationSource,
            },

            stops:
                parseStops(
                    button.dataset.stops
                ),

            routeGeometry:
                normalizeRouteGeometry(
                    button.dataset.routeGeometry
                ),
        };
    }

    /* =========================================================
       RESET
    ========================================================= */

    function resetCalculation() {
        distanceEdited = false;
        timeEdited = false;
        lastCalculated = null;

        setValue(
            hidden.calculatedDistance,
            ''
        );

        setValue(
            hidden.calculatedTime,
            ''
        );

        setValue(
            hidden.distanceSource,
            ''
        );

        setValue(
            hidden.distanceManual,
            '0'
        );

        setValue(
            hidden.timeManual,
            '0'
        );

        setValue(
            hidden.geometry,
            ''
        );

        if (calculationSummary) {
            calculationSummary.hidden =
                true;
        }

        if (recalculateButton) {
            recalculateButton.disabled =
                true;
        }

        if (fitMapButton) {
            fitMapButton.disabled =
                true;
        }

        setMapMessage(
            'Select confirmed locations to build the road route.',
            'info'
        );
    }

    /* =========================================================
       MODALS
    ========================================================= */

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add(
            'show',
            'active'
        );

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        body.classList.add(
            'modal-open'
        );

        body.style.overflow =
            'hidden';
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove(
            'show',
            'active'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        const openModalExists =
            document.querySelector(
                '.ui-form-overlay.show, ' +
                '.ui-form-overlay.active, ' +
                '.route-modal-overlay.show, ' +
                '.route-modal-overlay.active, ' +
                '.modal-overlay.show, ' +
                '.modal-overlay.active'
            );

        if (!openModalExists) {
            body.classList.remove(
                'modal-open'
            );

            body.style.removeProperty(
                'overflow'
            );
        }
    }

    function handleEscapeKey(event) {
        if (event.key !== 'Escape') {
            return;
        }

        closeAllSuggestionPanels();

        if (
            routeDetailsModal?.classList
                .contains('active')
        ) {
            closeModal(routeDetailsModal);
            return;
        }

        if (
            routeModal?.classList
                .contains('active')
        ) {
            closeModal(routeModal);
            return;
        }

        if (
            deleteRouteModal?.classList
                .contains('active')
        ) {
            closeModal(deleteRouteModal);
            return;
        }

        if (
            validationModal?.classList
                .contains('active')
        ) {
            closeModal(validationModal);
        }
    }

    /* =========================================================
       MAP HELPERS
    ========================================================= */

    function fitMapToBounds(
        targetMap,
        bounds,
        maxZoom = 15
    ) {
        if (
            !targetMap ||
            !bounds?.isValid()
        ) {
            return;
        }

        targetMap.fitBounds(
            bounds,
            {
                padding: [40, 40],
                maxZoom,
            }
        );
    }

    function setMapMessage(
        message,
        type = 'info'
    ) {
        if (!mapMessage) {
            return;
        }

        mapMessage.textContent =
            message;

        mapMessage.dataset.type =
            type;
    }

    function setViewMapMessage(
        message,
        type = 'info'
    ) {
        if (!viewMapMessage) {
            return;
        }

        viewMapMessage.textContent =
            message;

        viewMapMessage.dataset.type =
            type;
    }

    /* =========================================================
       GEOMETRY HELPERS
    ========================================================= */

    function normalizeRouteGeometry(value) {
        if (!value) {
            return null;
        }

        let geometry = value;

        if (
            typeof geometry === 'string'
        ) {
            geometry =
                parseDatasetJson(
                    geometry
                );
        }

        if (
            !geometry ||
            typeof geometry !== 'object'
        ) {
            return null;
        }

        if (
            geometry.type === 'Feature' &&
            geometry.geometry
        ) {
            geometry =
                geometry.geometry;
        }

        if (
            geometry.route &&
            typeof geometry.route ===
                'object'
        ) {
            geometry =
                geometry.route;
        }

        if (
            geometry.geometry &&
            typeof geometry.geometry ===
                'object'
        ) {
            geometry =
                geometry.geometry;
        }

        if (
            Array.isArray(
                geometry.coordinates
            ) &&
            geometry.coordinates.length >= 2
        ) {
            return {
                type:
                    geometry.type ||
                    'LineString',

                coordinates:
                    geometry.coordinates,
            };
        }

        console.warn(
            'The route geometry is not a valid LineString:',
            geometry
        );

        return null;
    }

    function getGeometryLatLngs(geometry) {
        const normalizedGeometry =
            normalizeRouteGeometry(
                geometry
            );

        if (
            !normalizedGeometry ||
            !Array.isArray(
                normalizedGeometry.coordinates
            )
        ) {
            return [];
        }

        return normalizedGeometry
            .coordinates
            .map((coordinate) => {
                if (
                    !Array.isArray(coordinate) ||
                    coordinate.length < 2
                ) {
                    return null;
                }

                const longitude =
                    Number(coordinate[0]);

                const latitude =
                    Number(coordinate[1]);

                if (
                    !Number.isFinite(latitude) ||
                    !Number.isFinite(longitude)
                ) {
                    return null;
                }

                return [
                    latitude,
                    longitude,
                ];
            })
            .filter(Boolean);
    }

    /* =========================================================
       DEFAULT LEAFLET ICONS
    ========================================================= */

    function fixDefaultLeafletIcons() {
        if (
            !window.L ||
            !L.Icon?.Default
        ) {
            return;
        }

        delete L.Icon.Default
            .prototype
            ._getIconUrl;

        L.Icon.Default.mergeOptions({
            iconRetinaUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',

            iconUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',

            shadowUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
    }

    /* =========================================================
       LEAFLET LOADER
    ========================================================= */

    async function loadLeaflet() {
        if (window.L) {
            fixDefaultLeafletIcons();
            return;
        }

        if (
            window.__routesLeafletPromise
        ) {
            await window
                .__routesLeafletPromise;

            fixDefaultLeafletIcons();
            return;
        }

        window.__routesLeafletPromise =
            new Promise(
                (resolve, reject) => {
                    if (
                        !document.querySelector(
                            'link[data-routes-leaflet]'
                        )
                    ) {
                        const link =
                            document.createElement(
                                'link'
                            );

                        link.rel =
                            'stylesheet';

                        link.href =
                            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';

                        link.dataset
                            .routesLeaflet =
                            '1';

                        document.head
                            .appendChild(link);
                    }

                    const existing =
                        document.querySelector(
                            'script[data-routes-leaflet]'
                        );

                    if (existing) {
                        if (window.L) {
                            resolve();
                            return;
                        }

                        existing.addEventListener(
                            'load',
                            resolve,
                            {
                                once: true,
                            }
                        );

                        existing.addEventListener(
                            'error',
                            reject,
                            {
                                once: true,
                            }
                        );

                        return;
                    }

                    const script =
                        document.createElement(
                            'script'
                        );

                    script.src =
                        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

                    script.dataset
                        .routesLeaflet =
                        '1';

                    script.onload =
                        resolve;

                    script.onerror =
                        reject;

                    document.head
                        .appendChild(script);
                }
            );

        await window
            .__routesLeafletPromise;

        fixDefaultLeafletIcons();
    }

    function addOpenStreetMapTiles(
        targetMap
    ) {
        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,

                attribution:
                    '&copy; OpenStreetMap contributors',
            }
        ).addTo(targetMap);
    }

    /* =========================================================
       GENERAL HELPERS
    ========================================================= */

    function getAllLocationInputs() {
        return [
            routeOrigin,
            ...getStopInputs(),
            routeDestination,
        ].filter(Boolean);
    }

    function getStopInputs() {
        return routeStopList
            ? Array.from(
                routeStopList
                    .querySelectorAll(
                        'input[name="stops[]"]'
                    )
            )
            : [];
    }

    function getStopRows() {
        return routeStopList
            ? Array.from(
                routeStopList
                    .querySelectorAll(
                        '.route-stop-item'
                    )
            )
            : [];
    }

    function hasCoordinates(input) {
        return (
            input &&
            isValidCoordinate(
                input.dataset.latitude,
                input.dataset.longitude
            )
        );
    }

    function isValidCoordinate(
        latitude,
        longitude
    ) {
        const lat =
            Number(latitude);

        const lng =
            Number(longitude);

        return (
            Number.isFinite(lat) &&
            Number.isFinite(lng) &&
            Math.abs(lat) <= 90 &&
            Math.abs(lng) <= 180
        );
    }

    function fieldRole(input) {
        if (!input) {
            return 'Location';
        }

        return (
            input.dataset.role ||
            (
                input === routeOrigin
                    ? 'Origin'
                    : input ===
                        routeDestination
                        ? 'Destination'
                        : 'Location'
            )
        );
    }

    function parseJson(element) {
        try {
            const value =
                JSON.parse(
                    element?.textContent ||
                    '[]'
                );

            return Array.isArray(value)
                ? value
                : [];
        } catch (error) {
            console.error(
                'Unable to parse JSON array:',
                error
            );

            return [];
        }
    }

    function parseJsonObject(element) {
        try {
            const value =
                JSON.parse(
                    element?.textContent ||
                    '{}'
                );

            return (
                value &&
                typeof value === 'object'
            )
                ? value
                : {};
        } catch (error) {
            console.error(
                'Unable to parse JSON object:',
                error
            );

            return {};
        }
    }

    function parseDatasetJson(value) {
        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return null;
        }

        let parsedValue = value;

        try {
            for (
                let attempt = 0;
                attempt < 4;
                attempt += 1
            ) {
                if (
                    typeof parsedValue !==
                    'string'
                ) {
                    break;
                }

                const trimmedValue =
                    parsedValue.trim();

                if (!trimmedValue) {
                    return null;
                }

                parsedValue =
                    JSON.parse(
                        trimmedValue
                    );
            }

            return parsedValue;
        } catch (error) {
            console.error(
                'Unable to parse dataset JSON:',
                error,
                value
            );

            return null;
        }
    }

    function parseStops(value) {
        const parsed =
            parseDatasetJson(value);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.map((stop) => {
            if (
                typeof stop === 'string'
            ) {
                return {
                    name: stop,
                };
            }

            return stop;
        });
    }

    function normalizePlace(place) {
        const latitude =
            Number(place?.latitude);

        const longitude =
            Number(place?.longitude);

        if (
            !place?.name ||
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude)
        ) {
            return null;
        }

        return {
            id:
                place.id ||
                `${place.source}-${latitude}-${longitude}`,

            name:
                String(place.name),

            address:
                String(
                    place.address ||
                    place.name
                ),

            latitude,
            longitude,

            source:
                String(
                    place.source ||
                    'OpenStreetMap'
                ),

            grouping:
                place.grouping || '',
        };
    }

    function dedupePlaces(places) {
        const seen = new Set();

        return places.filter((place) => {
            const key = [
                normalize(place.name),

                Number(place.latitude)
                    .toFixed(5),

                Number(place.longitude)
                    .toFixed(5),
            ].join(':');

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);
            return true;
        });
    }

    function normalize(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(
                /[^a-z0-9]+/g,
                ''
            );
    }

    function highlightSuggestion(
        panel,
        index
    ) {
        panel
            .querySelectorAll(
                '.location-suggestion-item'
            )
            .forEach(
                (element, itemIndex) => {
                    element.classList.toggle(
                        'active',
                        itemIndex === index
                    );
                }
            );
    }

    function highlightMatch(
        value,
        query
    ) {
        const text =
            String(value || '');

        const search =
            String(query || '');

        if (!search) {
            return escapeHtml(text);
        }

        const index =
            text
                .toLowerCase()
                .indexOf(
                    search.toLowerCase()
                );

        if (index < 0) {
            return escapeHtml(text);
        }

        return [
            escapeHtml(
                text.slice(0, index)
            ),

            '<mark>',

            escapeHtml(
                text.slice(
                    index,
                    index + search.length
                )
            ),

            '</mark>',

            escapeHtml(
                text.slice(
                    index + search.length
                )
            ),
        ].join('');
    }

    function escapeHtml(value) {
        const div =
            document.createElement('div');

        div.textContent =
            String(value ?? '');

        return div.innerHTML;
    }

    function setValue(
        element,
        value
    ) {
        if (element) {
            element.value =
                value ?? '';
        }
    }

    function setText(
        id,
        value
    ) {
        const element = $(id);

        if (element) {
            element.textContent =
                value ?? '';
        }
    }

    function setTextElement(
        element,
        value
    ) {
        if (element) {
            element.textContent =
                value ?? '';
        }
    }
});