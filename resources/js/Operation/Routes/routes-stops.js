document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    /* =========================================================
       ELEMENTS
    ========================================================= */

    const body = document.body;

    const routeModal =
        document.getElementById('routeModal');

    const routeDetailsModal =
        document.getElementById('routeDetailsModal');

    const openRouteModalButton =
        document.getElementById('openRouteModal');

    const routeForm =
        document.getElementById('routeForm');

    const routeFormMethod =
        document.getElementById('routeFormMethod');

    const routeModalTitle =
        document.getElementById('routeModalTitle');

    const routeCode =
        document.getElementById('routeCode');

    const routeName =
        document.getElementById('routeName');

    const routeOrigin =
        document.getElementById('routeOrigin');

    const routeDestination =
        document.getElementById('routeDestination');

    const routeDistance =
        document.getElementById('routeDistance');

    const routeTime =
        document.getElementById('routeTime');

    const routeStatus =
        document.getElementById('routeStatus');

    const routeStopList =
        document.getElementById('routeStopList');

    const addRouteStopButton =
        document.getElementById('addRouteStop');

    const saveRouteButton =
        document.getElementById('saveRouteBtn');

    const saveRouteText =
        document.getElementById('saveRouteText');

    const routeSearch =
        document.getElementById('routeSearch');

    const routeStatusFilter =
        document.getElementById('routeStatusFilter');

    /* =========================================================
       VIEW ROUTE ELEMENTS
    ========================================================= */

    const viewRouteCode =
        document.getElementById('viewRouteCode');

    const viewRouteName =
        document.getElementById('viewRouteName');

    const viewRouteOrigin =
        document.getElementById('viewRouteOrigin');

    const viewRouteDestination =
        document.getElementById(
            'viewRouteDestination'
        );

    const viewRouteDistance =
        document.getElementById('viewRouteDistance');

    const viewRouteTime =
        document.getElementById('viewRouteTime');

    const viewRouteStatus =
        document.getElementById('viewRouteStatus');

    const viewRoutePath =
        document.getElementById('viewRoutePath');

    const viewRouteStopCount =
        document.getElementById(
            'viewRouteStopCount'
        );

    /* =========================================================
       GPS DATA ELEMENTS
    ========================================================= */

    const gpsTripRecordsData =
        document.getElementById(
            'gpsTripRecordsData'
        );

    const gpsRouteSuggestionsData =
        document.getElementById(
            'gpsRouteSuggestionsData'
        );

    const routeNameSuggestionList =
        document.getElementById(
            'routeNameSuggestionList'
        );

    const routeOriginSuggestionList =
        document.getElementById(
            'routeOriginSuggestionList'
        );

    const routeDestinationSuggestionList =
        document.getElementById(
            'routeDestinationSuggestionList'
        );

    /* =========================================================
       VIEW GPS MAP ELEMENTS
    ========================================================= */

    const gpsTripSelect =
        document.getElementById('gpsTripSelect');

    const gpsTripMapElement =
        document.getElementById('gpsTripMap');

    const gpsMapMessage =
        document.getElementById('gpsMapMessage');

    const gpsTripDetails =
        document.getElementById('gpsTripDetails');

    const fitGpsMapButton =
        document.getElementById('fitGpsMap');

    const gpsDetailBus =
        document.getElementById('gpsDetailBus');

    const gpsDetailGrouping =
        document.getElementById(
            'gpsDetailGrouping'
        );

    const gpsDetailBeginning =
        document.getElementById(
            'gpsDetailBeginning'
        );

    const gpsDetailEnding =
        document.getElementById(
            'gpsDetailEnding'
        );

    const gpsDetailOrigin =
        document.getElementById(
            'gpsDetailOrigin'
        );

    const gpsDetailDestination =
        document.getElementById(
            'gpsDetailDestination'
        );

    const gpsDetailMileage =
        document.getElementById(
            'gpsDetailMileage'
        );

    const gpsDetailDuration =
        document.getElementById(
            'gpsDetailDuration'
        );

    /* =========================================================
       ADD / EDIT GPS MAP ELEMENTS
    ========================================================= */

    const routeFormGpsTripSelect =
        document.getElementById(
            'routeFormGpsTripSelect'
        );

    const routeFormGpsMapElement =
        document.getElementById(
            'routeFormGpsMap'
        );

    const routeFormGpsMapMessage =
        document.getElementById(
            'routeFormGpsMapMessage'
        );

    const fitRouteFormGpsMapButton =
        document.getElementById(
            'fitRouteFormGpsMap'
        );

    /* =========================================================
       DELETE ELEMENTS
    ========================================================= */

    const deleteRouteModal =
        document.getElementById(
            'deleteRouteModal'
        );

    const deleteRouteName =
        document.getElementById(
            'deleteRouteName'
        );

    const cancelDeleteRouteButton =
        document.getElementById(
            'cancelDeleteRoute'
        );

    const confirmDeleteRouteButton =
        document.getElementById(
            'confirmDeleteRoute'
        );

    /* =========================================================
       VALIDATION ELEMENTS
    ========================================================= */

    const validationModal =
        document.getElementById(
            'routeValidationModal'
        );

    const closeValidationModalButton =
        document.getElementById(
            'closeRouteValidationModal'
        );

    /* =========================================================
       STATE
    ========================================================= */

    const originalStoreUrl =
        routeForm?.getAttribute('action') ?? '';

    const originalRouteCode =
        routeCode?.value ?? 'R-01';

    const gpsTripRecords =
        parseJsonElement(
            gpsTripRecordsData
        );

    const gpsRouteSuggestions =
        parseJsonElement(
            gpsRouteSuggestionsData
        );

    let selectedDeleteForm = null;

    let viewGpsMap = null;
    let viewGpsLayer = null;
    let viewGpsBounds = null;

    let formGpsMap = null;
    let formGpsLayer = null;
    let formGpsBounds = null;

    let currentlyApplyingSuggestion = false;

    const leafletAssets = {
        css:
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',

        js:
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    };

    /* =========================================================
       INITIAL AUTOCOMPLETE SETUP
    ========================================================= */

    setupRouteAutocomplete();

    function setupRouteAutocomplete() {
        if (routeName instanceof HTMLInputElement) {
            routeName.setAttribute(
                'list',
                'routeNameSuggestionList'
            );

            routeName.setAttribute(
                'autocomplete',
                'off'
            );
        }

        if (routeOrigin instanceof HTMLInputElement) {
            routeOrigin.setAttribute(
                'list',
                'routeOriginSuggestionList'
            );

            routeOrigin.setAttribute(
                'autocomplete',
                'off'
            );
        }

        if (
            routeDestination instanceof
            HTMLInputElement
        ) {
            routeDestination.setAttribute(
                'list',
                'routeDestinationSuggestionList'
            );

            routeDestination.setAttribute(
                'autocomplete',
                'off'
            );
        }

        renderRouteNameSuggestions();
        renderOriginSuggestions();
        renderDestinationSuggestions();
    }

    /* =========================================================
       ROUTE NAME SUGGESTIONS
    ========================================================= */

    function renderRouteNameSuggestions() {
        if (
            !(
                routeNameSuggestionList instanceof
                HTMLDataListElement
            )
        ) {
            return;
        }

        routeNameSuggestionList.innerHTML = '';

        const uniqueNames = uniqueValues(
            gpsRouteSuggestions.map(
                (suggestion) =>
                    suggestion.grouping
            )
        );

        uniqueNames.forEach((name) => {
            const option =
                document.createElement('option');

            option.value = name;

            routeNameSuggestionList.appendChild(
                option
            );
        });
    }

    function renderOriginSuggestions() {
        if (
            !(
                routeOriginSuggestionList instanceof
                HTMLDataListElement
            )
        ) {
            return;
        }

        routeOriginSuggestionList.innerHTML = '';

        const uniqueOrigins = uniqueValues(
            gpsRouteSuggestions.map(
                (suggestion) =>
                    suggestion.origin
            )
        );

        uniqueOrigins.forEach((origin) => {
            const option =
                document.createElement('option');

            option.value = origin;

            routeOriginSuggestionList.appendChild(
                option
            );
        });
    }

    function renderDestinationSuggestions() {
        if (
            !(
                routeDestinationSuggestionList instanceof
                HTMLDataListElement
            )
        ) {
            return;
        }

        routeDestinationSuggestionList.innerHTML =
            '';

        const selectedOrigin =
            normalizeComparable(
                routeOrigin?.value
            );

        const availableSuggestions =
            selectedOrigin
                ? gpsRouteSuggestions.filter(
                    (suggestion) =>
                        normalizeComparable(
                            suggestion.origin
                        ) === selectedOrigin
                )
                : gpsRouteSuggestions;

        const destinations = uniqueValues(
            availableSuggestions.map(
                (suggestion) =>
                    suggestion.destination
            )
        );

        destinations.forEach((destination) => {
            const option =
                document.createElement('option');

            option.value = destination;

            routeDestinationSuggestionList
                .appendChild(option);
        });
    }

    /* =========================================================
       ROUTE AUTOCOMPLETE EVENTS
    ========================================================= */

    routeName?.addEventListener(
        'input',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            const suggestion =
                findSuggestionByGrouping(
                    routeName.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion
                );
            }
        }
    );

    routeName?.addEventListener(
        'change',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            const suggestion =
                findSuggestionByGrouping(
                    routeName.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion
                );
            }
        }
    );

    routeOrigin?.addEventListener(
        'input',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            renderDestinationSuggestions();

            const suggestion =
                findSuggestionByLocations(
                    routeOrigin.value,
                    routeDestination?.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion,
                    {
                        preserveRouteName:
                            false,
                    }
                );
            }
        }
    );

    routeOrigin?.addEventListener(
        'change',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            renderDestinationSuggestions();

            const suggestion =
                findSuggestionByLocations(
                    routeOrigin.value,
                    routeDestination?.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion
                );
            }
        }
    );

    routeDestination?.addEventListener(
        'input',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            const suggestion =
                findSuggestionByLocations(
                    routeOrigin?.value,
                    routeDestination.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion
                );
            }
        }
    );

    routeDestination?.addEventListener(
        'change',
        () => {
            if (currentlyApplyingSuggestion) {
                return;
            }

            const suggestion =
                findSuggestionByLocations(
                    routeOrigin?.value,
                    routeDestination.value
                );

            if (suggestion) {
                applyRouteSuggestion(
                    suggestion
                );
            }
        }
    );

    /* =========================================================
       APPLY ROUTE SUGGESTION
    ========================================================= */

    async function applyRouteSuggestion(
        suggestion,
        options = {}
    ) {
        if (!suggestion) {
            return;
        }

        currentlyApplyingSuggestion = true;

        const preserveRouteName =
            options.preserveRouteName === true;

        if (!preserveRouteName) {
            setInputValue(
                routeName,
                suggestion.grouping
            );
        }

        setInputValue(
            routeOrigin,
            suggestion.origin
        );

        setInputValue(
            routeDestination,
            suggestion.destination
        );

        if (
            suggestion.distance_km !== null &&
            suggestion.distance_km !== undefined
        ) {
            setInputValue(
                routeDistance,
                suggestion.distance_km
            );
        }

        if (
            suggestion
                .estimated_time_minutes !== null &&
            suggestion
                .estimated_time_minutes !== undefined
        ) {
            setInputValue(
                routeTime,
                suggestion
                    .estimated_time_minutes
            );
        }

        renderDestinationSuggestions();

        populateFormGpsSelect(
            getCurrentFormRoute()
        );

        if (
            routeFormGpsTripSelect instanceof
            HTMLSelectElement
        ) {
            routeFormGpsTripSelect.value =
                String(
                    suggestion
                        .latest_gps_trip_id ??
                    ''
                );
        }

        try {
            await initializeFormGpsMap();

            const record =
                getGpsRecordById(
                    suggestion
                        .latest_gps_trip_id
                );

            if (record) {
                renderFormGpsTrip(record);
            } else {
                renderSuggestionCoordinates(
                    suggestion
                );
            }
        } catch (error) {
            console.error(
                'Unable to display suggested route:',
                error
            );

            setFormGpsMapMessage(
                'The suggested route was selected, but the map could not be displayed.',
                'error'
            );
        } finally {
            currentlyApplyingSuggestion = false;
        }
    }

    async function renderSuggestionCoordinates(
        suggestion
    ) {
        const pair =
            parseCoordinatePair(
                suggestion.coordinates
            );

        if (!pair || !formGpsMap) {
            return;
        }

        clearFormGpsLayer();

        const originMarker =
            window.L.marker(
                pair.origin
            );

        originMarker.bindPopup(
            buildSimplePopup(
                'Origin',
                suggestion.origin,
                suggestion
            )
        );

        const destinationMarker =
            window.L.marker(
                pair.destination
            );

        destinationMarker.bindPopup(
            buildSimplePopup(
                'Destination',
                suggestion.destination,
                suggestion
            )
        );

        const referenceLine =
            window.L.polyline(
                [
                    pair.origin,
                    pair.destination,
                ],
                {
                    weight: 5,
                    opacity: 0.82,
                    dashArray: '10 8',
                }
            );

        formGpsLayer =
            window.L.featureGroup([
                originMarker,
                destinationMarker,
                referenceLine,
            ]);

        formGpsLayer.addTo(
            formGpsMap
        );

        formGpsBounds =
            formGpsLayer.getBounds();

        formGpsMap.fitBounds(
            formGpsBounds,
            {
                padding: [35, 35],
                maxZoom: 13,
            }
        );

        originMarker.openPopup();

        if (
            fitRouteFormGpsMapButton instanceof
            HTMLButtonElement
        ) {
            fitRouteFormGpsMapButton.disabled =
                false;
        }

        setFormGpsMapMessage(
            `${normalizeText(
                suggestion.grouping,
                'Suggested route'
            )} loaded from processed GPS data.`,
            'success'
        );
    }

    function buildSimplePopup(
        type,
        location,
        suggestion
    ) {
        return `
            <div class="gps-map-popup">
                <strong>
                    ${escapeHtml(type)}:
                    ${escapeHtml(location)}
                </strong>

                <span>
                    Route:
                    ${escapeHtml(
                        suggestion.grouping
                    )}
                </span>

                <span>
                    Bus:
                    ${escapeHtml(
                        suggestion.bus_no ||
                        'Unknown Bus'
                    )}
                </span>
            </div>
        `;
    }

    /* =========================================================
       FIND SUGGESTIONS
    ========================================================= */

    function findSuggestionByGrouping(value) {
        const normalized =
            normalizeComparable(value);

        if (!normalized) {
            return null;
        }

        return gpsRouteSuggestions.find(
            (suggestion) =>
                normalizeComparable(
                    suggestion.grouping
                ) === normalized
        ) ?? null;
    }

    function findSuggestionByLocations(
        origin,
        destination
    ) {
        const normalizedOrigin =
            normalizeComparable(origin);

        const normalizedDestination =
            normalizeComparable(destination);

        if (
            !normalizedOrigin ||
            !normalizedDestination
        ) {
            return null;
        }

        return gpsRouteSuggestions.find(
            (suggestion) =>
                normalizeComparable(
                    suggestion.origin
                ) === normalizedOrigin &&
                normalizeComparable(
                    suggestion.destination
                ) === normalizedDestination
        ) ?? null;
    }

    /* =========================================================
       MODAL HELPERS
    ========================================================= */

    function openModal(modal) {
        if (!(modal instanceof HTMLElement)) {
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

        body.classList.add('modal-open');
        body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!(modal instanceof HTMLElement)) {
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

        const remainingOpenModal =
            document.querySelector(
                '.ui-form-overlay.show, ' +
                '.ui-form-overlay.active, ' +
                '.route-modal-overlay.show, ' +
                '.route-modal-overlay.active, ' +
                '.modal-overlay.show, ' +
                '.modal-overlay.active'
            );

        if (!remainingOpenModal) {
            body.classList.remove(
                'modal-open'
            );

            body.style.removeProperty(
                'overflow'
            );
        }
    }

    function isModalOpen(modal) {
        return (
            modal instanceof HTMLElement &&
            (
                modal.classList.contains(
                    'show'
                ) ||
                modal.classList.contains(
                    'active'
                )
            )
        );
    }

    /* =========================================================
       ROUTE DATA
    ========================================================= */

    function getRouteData(button) {
        return {
            id:
                normalizeText(
                    button.dataset.id
                ),

            routeCode:
                normalizeText(
                    button.dataset.routeCode
                ),

            routeName:
                normalizeText(
                    button.dataset.routeName
                ),

            origin:
                normalizeText(
                    button.dataset.origin
                ),

            destination:
                normalizeText(
                    button.dataset.destination
                ),

            distance:
                normalizeText(
                    button.dataset.distance
                ),

            time:
                normalizeText(
                    button.dataset.time
                ),

            status:
                normalizeText(
                    button.dataset.status,
                    'Active'
                ),

            updateUrl:
                normalizeText(
                    button.dataset.updateUrl
                ),

            stops:
                parseStops(
                    button.dataset.stops
                ),
        };
    }

    function parseStops(rawStops) {
        if (!rawStops) {
            return [];
        }

        try {
            const parsed =
                JSON.parse(rawStops);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map(
                    (stop) =>
                        normalizeText(stop)
                )
                .filter(Boolean);
        } catch (error) {
            console.error(
                'Invalid route stops JSON:',
                error
            );

            return [];
        }
    }

    /* =========================================================
       NEW ROUTE
    ========================================================= */

    openRouteModalButton?.addEventListener(
        'click',
        () => {
            resetCreateForm();
            prepareRouteFormMap();

            openModal(routeModal);

            window.setTimeout(() => {
                initializeFormGpsMap()
                    .catch((error) => {
                        console.error(
                            'Unable to initialize form GPS map:',
                            error
                        );

                        setFormGpsMapMessage(
                            'The GPS map could not be loaded.',
                            'error'
                        );
                    });
            }, 150);

            window.requestAnimationFrame(
                () => {
                    routeName?.focus();
                }
            );
        }
    );

    function resetCreateForm() {
        if (
            !(
                routeForm instanceof
                HTMLFormElement
            )
        ) {
            return;
        }

        routeForm.reset();

        routeForm.action =
            originalStoreUrl;

        if (routeFormMethod) {
            routeFormMethod.disabled =
                true;

            routeFormMethod.value =
                'POST';
        }

        setElementText(
            routeModalTitle,
            'New Route'
        );

        setElementText(
            saveRouteText,
            'Save Route'
        );

        setInputValue(
            routeCode,
            originalRouteCode
        );

        setInputValue(
            routeStatus,
            'Active'
        );

        populateStops([]);
        renderDestinationSuggestions();
        resetSubmitButton();
        clearValidationStyles();
    }

    /* =========================================================
       EDIT ROUTE
    ========================================================= */

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const editButton =
                target.closest(
                    '.edit-route-btn'
                );

            if (
                !(
                    editButton instanceof
                    HTMLElement
                )
            ) {
                return;
            }

            prepareEditRoute(
                getRouteData(editButton)
            );
        }
    );

    function prepareEditRoute(route) {
        if (
            !(
                routeForm instanceof
                HTMLFormElement
            )
        ) {
            return;
        }

        routeForm.reset();

        if (route.updateUrl) {
            routeForm.action =
                route.updateUrl;
        }

        if (routeFormMethod) {
            routeFormMethod.disabled =
                false;

            routeFormMethod.value =
                'PUT';
        }

        setElementText(
            routeModalTitle,
            'Edit Route'
        );

        setElementText(
            saveRouteText,
            'Update Route'
        );

        setInputValue(
            routeCode,
            route.routeCode
        );

        setInputValue(
            routeName,
            route.routeName
        );

        setInputValue(
            routeOrigin,
            route.origin
        );

        setInputValue(
            routeDestination,
            route.destination
        );

        setInputValue(
            routeDistance,
            route.distance
        );

        setInputValue(
            routeTime,
            route.time
        );

        setInputValue(
            routeStatus,
            route.status
        );

        populateStops(route.stops);

        renderDestinationSuggestions();
        resetSubmitButton();
        clearValidationStyles();

        prepareRouteFormMap();

        openModal(routeModal);

        window.setTimeout(() => {
            initializeFormGpsMap()
                .then(() => {
                    const matching =
                        findSuggestionByLocations(
                            route.origin,
                            route.destination
                        );

                    if (matching) {
                        applyRouteSuggestion(
                            matching
                        );
                    }
                })
                .catch((error) => {
                    console.error(
                        'Unable to initialize form GPS map:',
                        error
                    );

                    setFormGpsMapMessage(
                        'The GPS map could not be loaded.',
                        'error'
                    );
                });
        }, 150);

        window.requestAnimationFrame(
            () => {
                routeName?.focus();
            }
        );
    }

    /* =========================================================
       CLOSE ROUTE FORM
    ========================================================= */

    document
        .querySelectorAll(
            '[data-close-route-modal]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(routeModal);
                    resetSubmitButton();
                }
            );
        });

    /* =========================================================
       ROUTE STOPS
    ========================================================= */

    addRouteStopButton?.addEventListener(
        'click',
        () => {
            addStopInput('', true);
        }
    );

    routeStopList?.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const removeButton =
                target.closest(
                    '.remove-route-stop'
                );

            if (
                !(
                    removeButton instanceof
                    HTMLButtonElement
                )
            ) {
                return;
            }

            const row =
                removeButton.closest(
                    '.route-stop-item'
                );

            if (!(row instanceof HTMLElement)) {
                return;
            }

            const rows = getStopRows();

            if (rows.length <= 1) {
                const input =
                    row.querySelector(
                        'input[name="stops[]"]'
                    );

                if (
                    input instanceof
                    HTMLInputElement
                ) {
                    input.value = '';
                    input.focus();
                }

                return;
            }

            row.remove();
            updateStopNumbers();
        }
    );

    function populateStops(stops = []) {
        if (
            !(
                routeStopList instanceof
                HTMLElement
            )
        ) {
            return;
        }

        routeStopList.innerHTML = '';

        const validStops =
            Array.isArray(stops)
                ? stops
                    .map(
                        (stop) =>
                            normalizeText(
                                stop
                            )
                    )
                    .filter(Boolean)
                : [];

        if (validStops.length === 0) {
            addStopInput('');
            return;
        }

        validStops.forEach((stop) => {
            addStopInput(stop);
        });

        updateStopNumbers();
    }

    function addStopInput(
        value = '',
        focusInput = false
    ) {
        if (
            !(
                routeStopList instanceof
                HTMLElement
            )
        ) {
            return;
        }

        const row =
            document.createElement('div');

        row.className =
            'ui-form-repeater-row ' +
            'route-stop-item';

        const number =
            document.createElement('div');

        number.className =
            'ui-form-repeater-number ' +
            'route-stop-number';

        const input =
            document.createElement('input');

        input.type = 'text';
        input.name = 'stops[]';

        input.value =
            normalizeText(value);

        input.placeholder =
            'Enter stop name';

        input.autocomplete = 'off';

        const removeButton =
            document.createElement('button');

        removeButton.type = 'button';

        removeButton.className =
            'ui-form-repeater-remove ' +
            'remove-route-stop';

        removeButton.title =
            'Remove Stop';

        removeButton.innerHTML = `
            <i
                class="fa-solid fa-trash"
                aria-hidden="true"
            ></i>
        `;

        row.append(
            number,
            input,
            removeButton
        );

        routeStopList.appendChild(row);

        updateStopNumbers();

        if (focusInput) {
            input.focus();
        }
    }

    function getStopRows() {
        if (
            !(
                routeStopList instanceof
                HTMLElement
            )
        ) {
            return [];
        }

        return Array.from(
            routeStopList.querySelectorAll(
                '.route-stop-item'
            )
        );
    }

    function updateStopNumbers() {
        getStopRows().forEach(
            (row, index) => {
                const number =
                    row.querySelector(
                        '.route-stop-number'
                    );

                if (number) {
                    number.textContent =
                        String(index + 1);
                }

                const input =
                    row.querySelector(
                        'input[name="stops[]"]'
                    );

                if (
                    input instanceof
                    HTMLInputElement
                ) {
                    input.setAttribute(
                        'aria-label',
                        `Shuttle stop ${index + 1}`
                    );
                }
            }
        );
    }

    /* =========================================================
       FORM GPS SELECT
    ========================================================= */

    function getCurrentFormRoute() {
        return {
            routeName:
                normalizeText(
                    routeName?.value
                ),

            origin:
                normalizeText(
                    routeOrigin?.value
                ),

            destination:
                normalizeText(
                    routeDestination?.value
                ),
        };
    }

    function prepareRouteFormMap() {
        populateFormGpsSelect(
            getCurrentFormRoute()
        );

        resetFormGpsMap();
    }

    function populateFormGpsSelect(route) {
        populateGpsSelect(
            routeFormGpsTripSelect,
            route
        );
    }

    routeFormGpsTripSelect?.addEventListener(
        'change',
        async () => {
            const record =
                getGpsRecordById(
                    routeFormGpsTripSelect.value
                );

            if (!record) {
                resetFormGpsMap();
                return;
            }

            try {
                await initializeFormGpsMap();

                renderFormGpsTrip(
                    record
                );
            } catch (error) {
                console.error(
                    'Unable to display form GPS trip:',
                    error
                );

                setFormGpsMapMessage(
                    'The selected GPS trip could not be displayed.',
                    'error'
                );
            }
        }
    );

    fitRouteFormGpsMapButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    formGpsMap &&
                    formGpsBounds
                ) {
                    formGpsMap.fitBounds(
                        formGpsBounds,
                        {
                            padding:
                                [35, 35],

                            maxZoom:
                                13,
                        }
                    );
                }
            }
        );

    async function initializeFormGpsMap() {
        if (
            !(
                routeFormGpsMapElement instanceof
                HTMLElement
            )
        ) {
            return;
        }

        await loadLeaflet();

        if (!window.L) {
            throw new Error(
                'Leaflet is unavailable.'
            );
        }

        if (!formGpsMap) {
            formGpsMap =
                window.L.map(
                    routeFormGpsMapElement,
                    {
                        zoomControl:
                            true,

                        attributionControl:
                            true,
                    }
                );

            formGpsMap.setView(
                [13.94, 121.16],
                9
            );

            addOpenStreetMapLayer(
                formGpsMap
            );
        }

        window.setTimeout(() => {
            formGpsMap?.invalidateSize();
        }, 100);
    }

    function renderFormGpsTrip(record) {
        const coordinatePair =
            parseCoordinatePair(
                record.coordinates
            );

        if (!coordinatePair) {
            clearFormGpsLayer();

            setFormGpsMapMessage(
                'This GPS trip contains invalid coordinates.',
                'error'
            );

            return;
        }

        clearFormGpsLayer();

        formGpsLayer =
            createGpsRouteLayer(
                record,
                coordinatePair
            );

        formGpsLayer.addTo(
            formGpsMap
        );

        formGpsBounds =
            formGpsLayer.getBounds();

        formGpsMap.fitBounds(
            formGpsBounds,
            {
                padding: [35, 35],
                maxZoom: 13,
            }
        );

        openFirstMarkerPopup(
            formGpsLayer
        );

        if (
            fitRouteFormGpsMapButton instanceof
            HTMLButtonElement
        ) {
            fitRouteFormGpsMapButton.disabled =
                false;
        }

        setFormGpsMapMessage(
            `${normalizeText(
                record.bus_no,
                'Bus'
            )} GPS trip displayed.`,
            'success'
        );
    }

    function clearFormGpsLayer() {
        if (
            formGpsMap &&
            formGpsLayer
        ) {
            formGpsMap.removeLayer(
                formGpsLayer
            );
        }

        formGpsLayer = null;
        formGpsBounds = null;
    }

    function resetFormGpsMap() {
        clearFormGpsLayer();

        if (
            routeFormGpsTripSelect instanceof
            HTMLSelectElement
        ) {
            routeFormGpsTripSelect.value =
                '';
        }

        if (
            fitRouteFormGpsMapButton instanceof
            HTMLButtonElement
        ) {
            fitRouteFormGpsMapButton.disabled =
                true;
        }

        setFormGpsMapMessage(
            gpsTripRecords.length > 0
                ? 'Select a processed GPS trip to display it on the map.'
                : 'No processed GPS trips are available.',

            gpsTripRecords.length > 0
                ? 'info'
                : 'error'
        );

        window.setTimeout(() => {
            formGpsMap?.invalidateSize();
        }, 100);
    }

    function setFormGpsMapMessage(
        message,
        type = 'info'
    ) {
        if (
            !(
                routeFormGpsMapMessage instanceof
                HTMLElement
            )
        ) {
            return;
        }

        routeFormGpsMapMessage.textContent =
            message;

        routeFormGpsMapMessage.dataset.type =
            type;
    }

    /* =========================================================
       VIEW ROUTE DETAILS
    ========================================================= */

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const viewButton =
                target.closest(
                    '.open-route-details'
                );

            if (
                !(
                    viewButton instanceof
                    HTMLElement
                )
            ) {
                return;
            }

            showRouteDetails(
                getRouteData(viewButton)
            );
        }
    );

    function showRouteDetails(route) {
        setElementText(
            viewRouteCode,
            route.routeCode || '—'
        );

        setElementText(
            viewRouteName,
            route.routeName || '—'
        );

        setElementText(
            viewRouteOrigin,
            route.origin || '—'
        );

        setElementText(
            viewRouteDestination,
            route.destination || '—'
        );

        setElementText(
            viewRouteDistance,
            route.distance
                ? `${formatNumber(
                    route.distance
                )} KM`
                : '—'
        );

        setElementText(
            viewRouteTime,
            route.time
                ? `${formatNumber(
                    route.time
                )} minutes`
                : '—'
        );

        renderViewStatus(
            route.status
        );

        renderRoutePath(
            route.origin,
            route.stops,
            route.destination
        );

        if (viewRouteStopCount) {
            viewRouteStopCount.textContent =
                `${route.stops.length} ${
                    route.stops.length === 1
                        ? 'Stop'
                        : 'Stops'
                }`;
        }

        populateGpsSelect(
            gpsTripSelect,
            route
        );

        resetViewGpsMap();

        openModal(routeDetailsModal);

        window.setTimeout(() => {
            initializeViewGpsMap()
                .then(() => {
                    const matchingRecord =
                        findMatchingGpsRecord(
                            route
                        );

                    if (
                        matchingRecord &&
                        gpsTripSelect instanceof
                        HTMLSelectElement
                    ) {
                        gpsTripSelect.value =
                            String(
                                matchingRecord.id
                            );

                        renderViewGpsTrip(
                            matchingRecord
                        );
                    }
                })
                .catch((error) => {
                    console.error(
                        'Unable to initialize view GPS map:',
                        error
                    );

                    setViewGpsMapMessage(
                        'The GPS map could not be loaded.',
                        'error'
                    );
                });
        }, 150);
    }

    function renderViewStatus(status) {
        if (
            !(
                viewRouteStatus instanceof
                HTMLElement
            )
        ) {
            return;
        }

        const normalizedStatus =
            normalizeText(
                status,
                'Inactive'
            );

        const statusClass =
            normalizedStatus
                .toLowerCase()
                .replace(
                    /\s+/g,
                    '-'
                );

        viewRouteStatus.innerHTML =
            '';

        const badge =
            document.createElement(
                'span'
            );

        badge.className =
            `route-status ${statusClass}`;

        badge.textContent =
            normalizedStatus;

        viewRouteStatus.appendChild(
            badge
        );
    }

    function renderRoutePath(
        origin,
        stops,
        destination
    ) {
        if (
            !(
                viewRoutePath instanceof
                HTMLElement
            )
        ) {
            return;
        }

        viewRoutePath.innerHTML = '';

        const routeNodes = [
            {
                name:
                    normalizeText(
                        origin,
                        'Unknown Origin'
                    ),

                type:
                    'origin',

                role:
                    'Origin',
            },

            ...(Array.isArray(stops)
                ? stops
                : []
            ).map((stop) => ({
                name:
                    normalizeText(
                        stop,
                        'Unnamed Stop'
                    ),

                type:
                    'stop',

                role:
                    'Intermediate Stop',
            })),

            {
                name:
                    normalizeText(
                        destination,
                        'Unknown Destination'
                    ),

                type:
                    'destination',

                role:
                    'Destination',
            },
        ];

        routeNodes.forEach((node) => {
            viewRoutePath.appendChild(
                createRoutePathNode(
                    node
                )
            );
        });
    }

    function createRoutePathNode(node) {
        const item =
            document.createElement(
                'article'
            );

        item.className =
            `horizontal-route-node ${node.type}`;

        const marker =
            document.createElement(
                'div'
            );

        marker.className =
            'horizontal-route-marker';

        const content =
            document.createElement(
                'div'
            );

        content.className =
            'horizontal-route-content';

        const name =
            document.createElement(
                'strong'
            );

        name.textContent =
            node.name;

        const role =
            document.createElement(
                'span'
            );

        role.textContent =
            node.role;

        content.append(
            name,
            role
        );

        item.append(
            marker,
            content
        );

        return item;
    }

    document
        .querySelectorAll(
            '[data-close-route-details]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(
                        routeDetailsModal
                    );
                }
            );
        });

    /* =========================================================
       VIEW GPS MAP
    ========================================================= */

    gpsTripSelect?.addEventListener(
        'change',
        async () => {
            const record =
                getGpsRecordById(
                    gpsTripSelect.value
                );

            if (!record) {
                resetViewGpsMap();
                return;
            }

            try {
                await initializeViewGpsMap();

                renderViewGpsTrip(
                    record
                );
            } catch (error) {
                console.error(
                    'Unable to display GPS trip:',
                    error
                );

                setViewGpsMapMessage(
                    'The selected GPS trip could not be displayed.',
                    'error'
                );
            }
        }
    );

    fitGpsMapButton?.addEventListener(
        'click',
        () => {
            if (
                viewGpsMap &&
                viewGpsBounds
            ) {
                viewGpsMap.fitBounds(
                    viewGpsBounds,
                    {
                        padding:
                            [35, 35],

                        maxZoom:
                            13,
                    }
                );
            }
        }
    );

    async function initializeViewGpsMap() {
        if (
            !(
                gpsTripMapElement instanceof
                HTMLElement
            )
        ) {
            return;
        }

        await loadLeaflet();

        if (!viewGpsMap) {
            viewGpsMap =
                window.L.map(
                    gpsTripMapElement
                );

            viewGpsMap.setView(
                [13.94, 121.16],
                9
            );

            addOpenStreetMapLayer(
                viewGpsMap
            );
        }

        window.setTimeout(() => {
            viewGpsMap?.invalidateSize();
        }, 100);
    }

    function renderViewGpsTrip(record) {
        const coordinatePair =
            parseCoordinatePair(
                record.coordinates
            );

        updateViewGpsDetails(record);

        if (!coordinatePair) {
            clearViewGpsLayer();

            setViewGpsMapMessage(
                'This GPS trip contains invalid coordinates.',
                'error'
            );

            return;
        }

        clearViewGpsLayer();

        viewGpsLayer =
            createGpsRouteLayer(
                record,
                coordinatePair
            );

        viewGpsLayer.addTo(
            viewGpsMap
        );

        viewGpsBounds =
            viewGpsLayer.getBounds();

        viewGpsMap.fitBounds(
            viewGpsBounds,
            {
                padding: [35, 35],
                maxZoom: 13,
            }
        );

        openFirstMarkerPopup(
            viewGpsLayer
        );

        if (
            fitGpsMapButton instanceof
            HTMLButtonElement
        ) {
            fitGpsMapButton.disabled =
                false;
        }

        setViewGpsMapMessage(
            `${normalizeText(
                record.bus_no,
                'Bus'
            )} GPS trip displayed.`,
            'success'
        );
    }

    function updateViewGpsDetails(record) {
        setElementText(
            gpsDetailBus,
            record.bus_no || '—'
        );

        setElementText(
            gpsDetailGrouping,
            record.grouping || '—'
        );

        setElementText(
            gpsDetailBeginning,
            formatDateTime(
                record.beginning_at
            ) || '—'
        );

        setElementText(
            gpsDetailEnding,
            formatDateTime(
                record.ending_at
            ) || '—'
        );

        setElementText(
            gpsDetailOrigin,
            record.initial_location || '—'
        );

        setElementText(
            gpsDetailDestination,
            record.final_location || '—'
        );

        setElementText(
            gpsDetailMileage,
            record.mileage_km !== null &&
            record.mileage_km !== undefined
                ? `${formatNumber(
                    record.mileage_km
                )} KM`
                : '—'
        );

        setElementText(
            gpsDetailDuration,
            formatDuration(
                record.total_minutes ??
                record.duration_minutes
            )
        );

        if (
            gpsTripDetails instanceof
            HTMLElement
        ) {
            gpsTripDetails.hidden =
                false;
        }
    }

    function clearViewGpsLayer() {
        if (
            viewGpsMap &&
            viewGpsLayer
        ) {
            viewGpsMap.removeLayer(
                viewGpsLayer
            );
        }

        viewGpsLayer = null;
        viewGpsBounds = null;
    }

    function resetViewGpsMap() {
        clearViewGpsLayer();

        if (
            gpsTripSelect instanceof
            HTMLSelectElement
        ) {
            gpsTripSelect.value = '';
        }

        if (
            gpsTripDetails instanceof
            HTMLElement
        ) {
            gpsTripDetails.hidden = true;
        }

        if (
            fitGpsMapButton instanceof
            HTMLButtonElement
        ) {
            fitGpsMapButton.disabled =
                true;
        }

        setViewGpsMapMessage(
            gpsTripRecords.length > 0
                ? 'Select a processed GPS trip to display it on the map.'
                : 'No processed GPS trips are available.',

            gpsTripRecords.length > 0
                ? 'info'
                : 'error'
        );
    }

    function setViewGpsMapMessage(
        message,
        type = 'info'
    ) {
        if (
            !(
                gpsMapMessage instanceof
                HTMLElement
            )
        ) {
            return;
        }

        gpsMapMessage.textContent =
            message;

        gpsMapMessage.dataset.type =
            type;
    }

    /* =========================================================
       SHARED GPS FUNCTIONS
    ========================================================= */

    function populateGpsSelect(
        select,
        route
    ) {
        if (
            !(
                select instanceof
                HTMLSelectElement
            )
        ) {
            return;
        }

        select.innerHTML =
            '<option value="">Select a GPS trip</option>';

        const matchingRecords =
            gpsTripRecords.filter(
                (record) =>
                    gpsRecordMatchesRoute(
                        record,
                        route
                    )
            );

        const orderedRecords = [
            ...matchingRecords,

            ...gpsTripRecords.filter(
                (record) =>
                    !matchingRecords.includes(
                        record
                    )
            ),
        ];

        orderedRecords.forEach(
            (record) => {
                select.appendChild(
                    createGpsTripOption(
                        record
                    )
                );
            }
        );

        select.disabled =
            gpsTripRecords.length === 0;
    }

    function createGpsTripOption(record) {
        const option =
            document.createElement(
                'option'
            );

        option.value =
            String(record.id ?? '');

        option.textContent = [
            normalizeText(
                record.bus_no,
                'Unknown Bus'
            ),

            normalizeText(
                record.grouping
            ),

            formatDateTime(
                record.beginning_at
            ),
        ]
            .filter(Boolean)
            .join(' • ');

        return option;
    }

    function findMatchingGpsRecord(route) {
        return gpsTripRecords.find(
            (record) =>
                gpsRecordMatchesRoute(
                    record,
                    route
                )
        ) ?? null;
    }

    function gpsRecordMatchesRoute(
        record,
        route
    ) {
        const routeOriginValue =
            normalizeComparable(
                route?.origin
            );

        const routeDestinationValue =
            normalizeComparable(
                route?.destination
            );

        const gpsOrigin =
            normalizeComparable(
                record.initial_location
            );

        const gpsDestination =
            normalizeComparable(
                record.final_location
            );

        const grouping =
            normalizeComparable(
                record.grouping
            );

        return (
            (
                routeOriginValue &&
                routeDestinationValue &&
                gpsOrigin.includes(
                    routeOriginValue
                ) &&
                gpsDestination.includes(
                    routeDestinationValue
                )
            ) ||
            (
                routeOriginValue &&
                routeDestinationValue &&
                grouping.includes(
                    routeOriginValue
                ) &&
                grouping.includes(
                    routeDestinationValue
                )
            )
        );
    }

    function getGpsRecordById(value) {
        const id = Number(value);

        if (!Number.isFinite(id)) {
            return null;
        }

        return gpsTripRecords.find(
            (record) =>
                Number(record.id) === id
        ) ?? null;
    }

    function parseCoordinatePair(rawValue) {
        const match =
            normalizeText(rawValue).match(
                /(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*(?:->|→|to)\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/i
            );

        if (!match) {
            return null;
        }

        const values =
            match.slice(1).map(Number);

        if (
            values.some(
                (value) =>
                    !Number.isFinite(value)
            ) ||
            Math.abs(values[0]) > 90 ||
            Math.abs(values[2]) > 90 ||
            Math.abs(values[1]) > 180 ||
            Math.abs(values[3]) > 180
        ) {
            return null;
        }

        return {
            origin:
                [values[0], values[1]],

            destination:
                [values[2], values[3]],
        };
    }

    function createGpsRouteLayer(
        record,
        coordinatePair
    ) {
        const originMarker =
            window.L.marker(
                coordinatePair.origin
            );

        originMarker.bindPopup(
            buildGpsPopup(
                'Origin',
                record.initial_location,
                record
            )
        );

        const destinationMarker =
            window.L.marker(
                coordinatePair.destination
            );

        destinationMarker.bindPopup(
            buildGpsPopup(
                'Destination',
                record.final_location,
                record
            )
        );

        const line =
            window.L.polyline(
                [
                    coordinatePair.origin,
                    coordinatePair.destination,
                ],
                {
                    weight: 5,
                    opacity: 0.82,
                    dashArray: '10 8',
                }
            );

        return window.L.featureGroup([
            originMarker,
            destinationMarker,
            line,
        ]);
    }

    function buildGpsPopup(
        type,
        location,
        record
    ) {
        return `
            <div class="gps-map-popup">
                <strong>
                    ${escapeHtml(type)}:
                    ${escapeHtml(
                        location ||
                        'Unknown location'
                    )}
                </strong>

                <span>
                    Bus:
                    ${escapeHtml(
                        record.bus_no ||
                        'Unknown Bus'
                    )}
                </span>

                <span>
                    Route:
                    ${escapeHtml(
                        record.grouping ||
                        'Unspecified Route'
                    )}
                </span>
            </div>
        `;
    }

    function openFirstMarkerPopup(layer) {
        const marker =
            layer
                .getLayers()
                .find(
                    (item) =>
                        item instanceof
                        window.L.Marker
                );

        marker?.openPopup();
    }

    /* =========================================================
       LEAFLET
    ========================================================= */

    async function loadLeaflet() {
        if (window.L) {
            return;
        }

        if (
            window.__fromsLeafletPromise instanceof
            Promise
        ) {
            return window.__fromsLeafletPromise;
        }

        window.__fromsLeafletPromise =
            new Promise(
                (resolve, reject) => {
                    if (
                        !document.querySelector(
                            'link[data-froms-leaflet]'
                        )
                    ) {
                        const stylesheet =
                            document.createElement(
                                'link'
                            );

                        stylesheet.rel =
                            'stylesheet';

                        stylesheet.href =
                            leafletAssets.css;

                        stylesheet.dataset
                            .fromsLeaflet =
                            'true';

                        document.head.appendChild(
                            stylesheet
                        );
                    }

                    const existingScript =
                        document.querySelector(
                            'script[data-froms-leaflet]'
                        );

                    if (existingScript) {
                        if (window.L) {
                            resolve();
                            return;
                        }

                        existingScript
                            .addEventListener(
                                'load',
                                resolve,
                                {
                                    once:
                                        true,
                                }
                            );

                        existingScript
                            .addEventListener(
                                'error',
                                reject,
                                {
                                    once:
                                        true,
                                }
                            );

                        return;
                    }

                    const script =
                        document.createElement(
                            'script'
                        );

                    script.src =
                        leafletAssets.js;

                    script.async = true;

                    script.dataset
                        .fromsLeaflet =
                        'true';

                    script.addEventListener(
                        'load',
                        resolve,
                        {
                            once: true,
                        }
                    );

                    script.addEventListener(
                        'error',
                        () => {
                            reject(
                                new Error(
                                    'Leaflet failed to load.'
                                )
                            );
                        },
                        {
                            once: true,
                        }
                    );

                    document.head.appendChild(
                        script
                    );
                }
            );

        return window.__fromsLeafletPromise;
    }

    function addOpenStreetMapLayer(map) {
        window.L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,

                attribution:
                    '&copy; OpenStreetMap contributors',
            }
        ).addTo(map);
    }

    /* =========================================================
       DELETE ROUTE
    ========================================================= */

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const deleteButton =
                target.closest(
                    '.open-delete-route-modal'
                );

            if (
                !(
                    deleteButton instanceof
                    HTMLElement
                )
            ) {
                return;
            }

            const formId =
                normalizeText(
                    deleteButton.dataset.formId
                );

            selectedDeleteForm =
                formId
                    ? document.getElementById(
                        formId
                    )
                    : null;

            const routeDisplay = [
                normalizeText(
                    deleteButton.dataset
                        .routeCode
                ),

                normalizeText(
                    deleteButton.dataset
                        .routeName
                ),
            ]
                .filter(Boolean)
                .join(' - ');

            setElementText(
                deleteRouteName,
                routeDisplay ||
                'this route'
            );

            openModal(
                deleteRouteModal
            );
        }
    );

    cancelDeleteRouteButton
        ?.addEventListener(
            'click',
            () => {
                selectedDeleteForm = null;

                closeModal(
                    deleteRouteModal
                );
            }
        );

    confirmDeleteRouteButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    !(
                        selectedDeleteForm instanceof
                        HTMLFormElement
                    )
                ) {
                    closeModal(
                        deleteRouteModal
                    );

                    return;
                }

                confirmDeleteRouteButton.disabled =
                    true;

                selectedDeleteForm
                    .requestSubmit();
            }
        );

    /* =========================================================
       SEARCH
    ========================================================= */

    routeSearch?.addEventListener(
        'input',
        () => {
            const query =
                normalizeText(
                    routeSearch.value
                ).toLowerCase();

            document
                .querySelectorAll(
                    '.routes-table tbody tr'
                )
                .forEach((row) => {
                    if (
                        !(
                            row instanceof
                            HTMLTableRowElement
                        )
                    ) {
                        return;
                    }

                    const text =
                        normalizeText(
                            row.textContent
                        ).toLowerCase();

                    row.hidden =
                        query !== '' &&
                        !text.includes(query);
                });
        }
    );

    routeStatusFilter?.addEventListener(
        'change',
        () => {}
    );

    /* =========================================================
       FORM SUBMISSION
    ========================================================= */

    routeForm?.addEventListener(
        'submit',
        () => {
            if (
                saveRouteButton instanceof
                HTMLButtonElement
            ) {
                saveRouteButton.disabled =
                    true;
            }

            const editing =
                routeFormMethod &&
                !routeFormMethod.disabled;

            setElementText(
                saveRouteText,
                editing
                    ? 'Updating...'
                    : 'Saving...'
            );
        }
    );

    function resetSubmitButton() {
        if (
            saveRouteButton instanceof
            HTMLButtonElement
        ) {
            saveRouteButton.disabled =
                false;

            saveRouteButton.removeAttribute(
                'aria-busy'
            );
        }
    }

    /* =========================================================
       CLOSE HANDLERS
    ========================================================= */

    closeValidationModalButton
        ?.addEventListener(
            'click',
            () => {
                closeModal(
                    validationModal
                );
            }
        );

    [
        routeModal,
        routeDetailsModal,
        deleteRouteModal,
        validationModal,
    ]
        .filter(Boolean)
        .forEach((modal) => {
            modal.addEventListener(
                'click',
                (event) => {
                    if (
                        event.target === modal
                    ) {
                        closeModal(modal);
                    }
                }
            );
        });

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (
                isModalOpen(
                    deleteRouteModal
                )
            ) {
                closeModal(
                    deleteRouteModal
                );

                return;
            }

            if (
                isModalOpen(
                    routeDetailsModal
                )
            ) {
                closeModal(
                    routeDetailsModal
                );

                return;
            }

            if (
                isModalOpen(
                    routeModal
                )
            ) {
                closeModal(
                    routeModal
                );
            }
        }
    );

    /* =========================================================
       UTILITIES
    ========================================================= */

    function parseJsonElement(element) {
        if (
            !(
                element instanceof
                HTMLScriptElement
            )
        ) {
            return [];
        }

        try {
            const parsed =
                JSON.parse(
                    element.textContent ||
                    '[]'
                );

            return Array.isArray(parsed)
                ? parsed
                : [];
        } catch (error) {
            console.error(
                'Invalid JSON data:',
                error
            );

            return [];
        }
    }

    function normalizeText(
        value,
        fallback = ''
    ) {
        const result =
            String(value ?? '').trim();

        return result !== ''
            ? result
            : fallback;
    }

    function normalizeComparable(value) {
        return normalizeText(value)
            .toLowerCase()
            .replace(
                /[^a-z0-9]+/g,
                ''
            );
    }

    function uniqueValues(values) {
        const seen = new Set();

        return values
            .map((value) =>
                normalizeText(value)
            )
            .filter((value) => {
                const key =
                    normalizeComparable(value);

                if (!key || seen.has(key)) {
                    return false;
                }

                seen.add(key);

                return true;
            });
    }

    function setElementText(
        element,
        value
    ) {
        if (element) {
            element.textContent =
                normalizeText(value);
        }
    }

    function setInputValue(
        input,
        value
    ) {
        if (
            input instanceof
            HTMLInputElement ||
            input instanceof
            HTMLSelectElement
        ) {
            input.value =
                normalizeText(value);
        }
    }

    function formatNumber(value) {
        const number =
            Number(value);

        if (!Number.isFinite(number)) {
            return normalizeText(value);
        }

        return number.toLocaleString(
            undefined,
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }
        );
    }

    function formatDateTime(value) {
        const text =
            normalizeText(value);

        if (!text) {
            return '';
        }

        const date =
            new Date(
                text.includes('T')
                    ? text
                    : text.replace(
                        ' ',
                        'T'
                    )
            );

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return text;
        }

        return date.toLocaleString(
            undefined,
            {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }
        );
    }

    function formatDuration(value) {
        const minutes =
            Number(value);

        if (
            !Number.isFinite(minutes) ||
            minutes < 0
        ) {
            return '—';
        }

        const hours =
            Math.floor(
                minutes / 60
            );

        const remainingMinutes =
            Math.round(
                minutes % 60
            );

        if (hours === 0) {
            return (
                `${remainingMinutes} min`
            );
        }

        return (
            `${hours} hr ` +
            `${remainingMinutes} min`
        );
    }

    function escapeHtml(value) {
        const temporaryElement =
            document.createElement(
                'div'
            );

        temporaryElement.textContent =
            normalizeText(value);

        return temporaryElement.innerHTML;
    }

    function clearValidationStyles() {
        if (
            !(
                routeForm instanceof
                HTMLFormElement
            )
        ) {
            return;
        }

        routeForm
            .querySelectorAll(
                '.is-invalid, ' +
                '.invalid, ' +
                '[aria-invalid="true"]'
            )
            .forEach((element) => {
                element.classList.remove(
                    'is-invalid',
                    'invalid'
                );

                element.removeAttribute(
                    'aria-invalid'
                );
            });
    }

    /* =========================================================
       INITIALIZATION
    ========================================================= */

    updateStopNumbers();

    if (isModalOpen(validationModal)) {
        body.classList.add(
            'modal-open'
        );

        body.style.overflow =
            'hidden';
    }
});