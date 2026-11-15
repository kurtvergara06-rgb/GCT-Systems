document.addEventListener('DOMContentLoaded', () => {
    const form =
        document.getElementById('autoSchedulingForm');

    const generateButton =
        document.getElementById('generateScheduleButton');

    const regenerateButton =
        document.getElementById('regenerateScheduleButton');

    const confirmButton =
        document.getElementById('confirmScheduleButton');

    const previewBody =
        document.getElementById('autoSchedulePreviewBody');

    const previewSection =
        document.getElementById('generatedScheduleSection');

    const conflictSection =
        document.getElementById('autoSchedulingConflictSection');

    const conflictContent =
        document.getElementById('autoSchedulingConflictContent');

    const readyBadge =
        document.getElementById('generatedReadyCount');

    const footerMessage =
        document.getElementById('generatedFooterMessage');

    const summaryTrips =
        document.getElementById('summaryTrips');

    const summaryDrivers =
        document.getElementById('summaryDrivers');

    const summaryBuses =
        document.getElementById('summaryBuses');

    const summaryConflicts =
        document.getElementById('summaryConflicts');

    const generationTripCount =
        document.getElementById('generationTripCount');

    const generationDriverCount =
        document.getElementById('generationDriverCount');

    const generationBusCount =
        document.getElementById('generationBusCount');

    const resourceAvailableDrivers =
        document.getElementById('resourceAvailableDrivers');

    const resourceAvailableBuses =
        document.getElementById('resourceAvailableBuses');

    let recommendations = [];


    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        await generateSchedule();
    });


    regenerateButton?.addEventListener('click', async () => {
        await generateSchedule();
    });


    confirmButton?.addEventListener('click', () => {
        window.alert(
            'Preview generation is ready. Confirm and save will be connected after local testing.'
        );
    });


    async function generateSchedule() {
        if (!form || !generateButton) {
            return;
        }

        const generateUrl =
            normalizePath(
                generateButton.dataset.generateUrl,
                '/operation/auto-scheduling/generate'
            );

        setLoading(true);

        try {
            const response = await fetch(
                generateUrl,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    getErrorMessage(data)
                );
            }

            recommendations =
                Array.isArray(data.recommendations)
                    ? data.recommendations
                    : [];

            updateSummary(data.summary || {});
            renderRecommendations(recommendations);

            renderConflicts(
                Array.isArray(data.conflicts)
                    ? data.conflicts
                    : []
            );

            previewSection?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        } catch (error) {
            console.error(error);

            recommendations = [];

            renderError(
                error.message
                || 'Unable to generate schedule.'
            );

            renderConflicts([]);
            updateConfirmButton();
        } finally {
            setLoading(false);
        }
    }


    function setLoading(loading) {
        generateButton.disabled = loading;

        if (regenerateButton) {
            regenerateButton.disabled = loading;
        }

        generateButton.innerHTML = loading
            ? `
                <i class="fa-solid fa-spinner fa-spin"></i>
                <span>Generating...</span>
            `
            : `
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <span>Generate Schedule</span>
            `;
    }


    function updateSummary(summary) {
        setText(summaryTrips, summary.trips);
        setText(summaryDrivers, summary.drivers);
        setText(summaryBuses, summary.buses);
        setText(summaryConflicts, summary.conflicts);

        setText(
            generationTripCount,
            summary.trips
        );

        setText(
            generationDriverCount,
            summary.drivers
        );

        setText(
            generationBusCount,
            summary.buses
        );

        setText(
            resourceAvailableDrivers,
            summary.drivers
        );

        setText(
            resourceAvailableBuses,
            summary.buses
        );

        if (readyBadge) {
            readyBadge.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>${Number(summary.ready || 0)} Ready</span>
            `;
        }

        if (footerMessage) {
            const conflicts =
                Number(summary.conflicts || 0);

            footerMessage.textContent =
                conflicts > 0
                    ? `${conflicts} trip(s) require manual review.`
                    : Number(summary.ready || 0) > 0
                        ? 'All generated recommendations are ready.'
                        : 'No recommendations were generated.';
        }

        updateConfirmButton();
    }


    function renderRecommendations(items) {
        if (!previewBody) {
            return;
        }

        if (!items.length) {
            previewBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="auto-empty-state">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <strong>No ready recommendations</strong>
                            <span>
                                No Scheduled and Unassigned trips could be
                                assigned using the selected filters.
                            </span>
                        </div>
                    </td>
                </tr>
            `;

            updateConfirmButton();
            return;
        }

        previewBody.innerHTML = items
            .map((item, index) => `
                <tr>
                    <td>${escapeHtml(item.trip_code)}</td>

                    <td>
                        <div class="auto-time-cell">
                            <strong>
                                ${escapeHtml(
                                    item.departure_display
                                    || formatDisplayTime(
                                        item.departure_time
                                    )
                                )}
                            </strong>

                            <small>
                                to
                                ${escapeHtml(
                                    item.arrival_display
                                    || formatDisplayTime(
                                        item.estimated_arrival_time
                                    )
                                )}
                            </small>
                        </div>
                    </td>

                    <td>
                        <div class="route-cell">
                            <strong>
                                ${escapeHtml(item.route_code)}
                            </strong>

                            <span>
                                ${escapeHtml(item.route_name)}
                            </span>
                        </div>
                    </td>

                    <td>
                        ${renderDriver(item)}
                    </td>

                    <td>
                        <span class="bus-chip">
                            ${escapeHtml(item.bus_no)}
                        </span>
                    </td>

                    <td>
                        <span class="schedule-status ready">
                            Ready
                        </span>
                    </td>

                    <td>
                        <button
                            type="button"
                            class="schedule-action-btn remove"
                            data-remove-index="${index}"
                            title="Remove recommendation"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </td>
                </tr>
            `)
            .join('');

        document
            .querySelectorAll('[data-remove-index]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    const index =
                        Number(button.dataset.removeIndex);

                    if (
                        Number.isNaN(index)
                        || !recommendations[index]
                    ) {
                        return;
                    }

                    recommendations.splice(index, 1);

                    renderRecommendations(
                        recommendations
                    );

                    updateReadyBadgeOnly();
                });
            });

        updateConfirmButton();
    }


    function renderDriver(item) {
        return `
            <div class="driver-cell">
                <div class="driver-avatar">
                    ${escapeHtml(
                        getInitials(item.driver_name)
                    )}
                </div>

                <div>
                    <strong>
                        ${escapeHtml(item.driver_name)}
                    </strong>

                    <span>
                        ${escapeHtml(
                            item.driver_status
                            || 'Available'
                        )}
                    </span>
                </div>
            </div>
        `;
    }


    function renderConflicts(items) {
        if (!conflictSection || !conflictContent) {
            return;
        }

        if (!items.length) {
            conflictSection.hidden = true;
            conflictContent.innerHTML = '';
            return;
        }

        conflictSection.hidden = false;

        conflictContent.innerHTML = items
            .map((item) => `
                <div class="auto-conflict-record">
                    <div class="conflict-trip">
                        <span>Trip</span>
                        <strong>
                            ${escapeHtml(item.trip_code)}
                        </strong>
                    </div>

                    <div class="conflict-trip">
                        <span>Route</span>
                        <strong>
                            ${escapeHtml(
                                `${item.route_code} — ${item.route_name}`
                            )}
                        </strong>
                    </div>

                    <div class="conflict-trip">
                        <span>Departure</span>
                        <strong>
                            ${escapeHtml(
                                item.departure_display
                                || formatDisplayTime(
                                    item.departure_time
                                )
                            )}
                        </strong>
                    </div>

                    <div class="conflict-reason">
                        <i class="fa-solid fa-circle-info"></i>

                        <div>
                            <strong>
                                ${escapeHtml(
                                    item.reason
                                    || 'Unable to assign resources.'
                                )}
                            </strong>

                            <span>
                                Review attendance, buses,
                                or existing assignments.
                            </span>
                        </div>
                    </div>
                </div>
            `)
            .join('');
    }


    function renderError(message) {
        if (!previewBody) {
            return;
        }

        previewBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="auto-empty-state error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <strong>Schedule generation failed</strong>
                        <span>${escapeHtml(message)}</span>
                    </div>
                </td>
            </tr>
        `;

        if (footerMessage) {
            footerMessage.textContent =
                'Unable to generate schedule.';
        }
    }


    function updateReadyBadgeOnly() {
        if (readyBadge) {
            readyBadge.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>${recommendations.length} Ready</span>
            `;
        }

        if (footerMessage) {
            footerMessage.textContent =
                recommendations.length > 0
                    ? `${recommendations.length} recommendation(s) remain in the preview.`
                    : 'No recommendations remain in the preview.';
        }

        updateConfirmButton();
    }


    function updateConfirmButton() {
        if (confirmButton) {
            /*
             * Preview-only phase:
             * saving will be connected after testing.
             */
            confirmButton.disabled = true;
        }
    }


    function normalizePath(value, fallback) {
        const raw = String(value || '').trim();

        if (!raw) {
            return fallback;
        }

        if (
            raw.startsWith('/')
            && !raw.startsWith('//')
        ) {
            return raw;
        }

        try {
            const parsed =
                new URL(raw, window.location.origin);

            if (
                parsed.origin
                === window.location.origin
            ) {
                return (
                    parsed.pathname
                    + parsed.search
                    + parsed.hash
                );
            }
        } catch (error) {
            console.warn(
                'Invalid Auto Scheduling URL.',
                error
            );
        }

        const cleaned = raw
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const index =
            cleaned.indexOf(
                'operation/auto-scheduling'
            );

        return index >= 0
            ? `/${cleaned.slice(index)}`
            : fallback;
    }


    function formatDisplayTime(value) {
        if (!value) {
            return '—';
        }

        const parts = String(value).split(':');

        let hour = Number(parts[0]);
        const minute = parts[1] || '00';

        const period =
            hour >= 12
                ? 'PM'
                : 'AM';

        hour = hour % 12 || 12;

        return `${hour}:${minute} ${period}`;
    }


    function getInitials(name) {
        return String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase();
    }


    function getErrorMessage(data) {
        if (data?.message) {
            return data.message;
        }

        if (data?.errors) {
            return Object
                .values(data.errors)
                .flat()
                .join(' ');
        }

        return 'Unable to generate schedule.';
    }


    function setText(element, value) {
        if (element) {
            element.textContent =
                String(value ?? 0);
        }
    }


    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
