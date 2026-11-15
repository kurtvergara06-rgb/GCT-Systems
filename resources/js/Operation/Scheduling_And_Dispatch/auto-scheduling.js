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
    let schedulingConflicts = [];


    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        await generateSchedule();
    });


    regenerateButton?.addEventListener('click', async () => {
        await generateSchedule();
    });


    confirmButton?.addEventListener('click', async () => {
        await confirmSchedule();
    });


    document.addEventListener('click', (event) => {
        const reviewButton = event.target.closest(
            '[data-review-ai-resolution]'
        );

        if (reviewButton) {
            const index = Number(
                reviewButton.dataset.reviewAiResolution
            );

            if (
                Number.isInteger(index)
                && schedulingConflicts[index]
            ) {
                openResolutionModal(
                    schedulingConflicts[index]
                );
            }

            return;
        }

        if (
            event.target.closest('[data-close-resolution-modal]')
        ) {
            closeResolutionModal();
        }
    });

    function renderAiConflictAnalysis({
    ai,
    aiConflict,
    findings,
    actions,
    warnings,
    alternativeDrivers,
    alternativeBuses,
}) {
    const title =
        aiConflict.title
        || 'AI Conflict Analysis';

    const explanation =
        aiConflict.explanation
        || ai.conflict_explanation
        || 'The AI detected an unresolved scheduling conflict.';

    const score =
        Number.isFinite(Number(ai.score))
            ? Number(ai.score)
            : null;

    return `
        <div class="ai-conflict-analysis">
            <div class="ai-conflict-heading">
                <div class="ai-conflict-title">
                    <span class="ai-icon">
                        <i class="fa-solid fa-robot"></i>
                    </span>

                    <div>
                        <span class="section-eyebrow warning">
                            AI Conflict Analysis
                        </span>

                        <h3>${escapeHtml(title)}</h3>
                    </div>
                </div>

                ${
                    score !== null
                        ? `
                            <span class="ai-score-badge">
                                Score: ${score}
                            </span>
                        `
                        : ''
                }
            </div>

            <p class="ai-conflict-explanation">
                ${escapeHtml(explanation)}
            </p>

            ${renderAiFindings(findings)}

            ${renderAiWarnings(warnings)}

            ${renderAiActions(actions)}

            ${renderAiAlternatives(
                alternativeDrivers,
                alternativeBuses
            )}
        </div>
    `;
}


function renderAiFindings(findings) {
    if (!findings.length) {
        return '';
    }

    return `
        <div class="ai-detail-section">
            <h4>
                <i class="fa-solid fa-magnifying-glass"></i>
                What the AI found
            </h4>

            <div class="ai-findings-list">
                ${findings
                    .map((finding) => `
                        <div class="ai-finding-item">
                            <span class="ai-finding-count">
                                ${Number(finding.count || 0)}
                            </span>

                            <span>
                                ${escapeHtml(
                                    finding.explanation
                                    || finding.category
                                    || 'Scheduling issue detected.'
                                )}
                            </span>
                        </div>
                    `)
                    .join('')}
            </div>
        </div>
    `;
}


function renderAiWarnings(warnings) {
    if (!warnings.length) {
        return '';
    }

    return `
        <div class="ai-detail-section">
            <h4>
                <i class="fa-solid fa-triangle-exclamation"></i>
                Warnings
            </h4>

            <ul class="ai-warning-list">
                ${warnings
                    .map((warning) => `
                        <li>
                            ${escapeHtml(warning)}
                        </li>
                    `)
                    .join('')}
            </ul>
        </div>
    `;
}


    function renderAiActions(actions) {
        if (!actions.length) {
            return '';
        }

        return `
            <div class="ai-detail-section">
                <h4>
                    <i class="fa-solid fa-lightbulb"></i>
                    Recommended actions
                </h4>

                <div class="ai-action-list">
                    ${actions
                        .map((action) => `
                            <div class="ai-action-item">
                                <strong>
                                    ${escapeHtml(
                                        getResolutionActionLabel(action)
                                    )}
                                </strong>

                                <span>
                                    ${escapeHtml(
                                        action.explanation
                                        || ''
                                    )}
                                </span>

                                ${
                                    action.suggested_time
                                        ? `
                                            <small>
                                                Suggested time:
                                                ${escapeHtml(
                                                    formatDisplayTime(
                                                        action.suggested_time
                                                    )
                                                )}
                                            </small>
                                        `
                                        : ''
                                }
                            </div>
                        `)
                        .join('')}
                </div>
            </div>
        `;
    }


    function renderAiAlternatives(
        alternativeDrivers,
        alternativeBuses
    ) {
        if (
            !alternativeDrivers.length
            && !alternativeBuses.length
        ) {
            return '';
        }

        return `
            <div class="ai-detail-section">
                <h4>
                    <i class="fa-solid fa-list-check"></i>
                    Possible alternatives
                </h4>

                <div class="ai-alternative-grid">
                    ${
                        alternativeDrivers.length
                            ? `
                                <div class="ai-alternative-group">
                                    <strong>
                                        Alternative drivers
                                    </strong>

                                    ${alternativeDrivers
                                        .map((driver) => `
                                            <div class="ai-alternative-item">
                                                <span>
                                                    ${escapeHtml(
                                                        driver.label
                                                    )}
                                                </span>

                                                ${
                                                    driver.score !== null
                                                    && driver.score !== undefined
                                                        ? `
                                                            <small>
                                                                Score:
                                                                ${Number(
                                                                    driver.score
                                                                )}
                                                            </small>
                                                        `
                                                        : ''
                                                }

                                                <p>
                                                    ${escapeHtml(
                                                        driver.reason
                                                    )}
                                                </p>
                                            </div>
                                        `)
                                        .join('')}
                                </div>
                            `
                            : ''
                    }

                    ${
                        alternativeBuses.length
                            ? `
                                <div class="ai-alternative-group">
                                    <strong>
                                        Alternative buses
                                    </strong>

                                    ${alternativeBuses
                                        .map((bus) => `
                                            <div class="ai-alternative-item">
                                                <span>
                                                    ${escapeHtml(
                                                        bus.label
                                                    )}
                                                </span>

                                                ${
                                                    bus.score !== null
                                                    && bus.score !== undefined
                                                        ? `
                                                            <small>
                                                                Score:
                                                                ${Number(
                                                                    bus.score
                                                                )}
                                                            </small>
                                                        `
                                                        : ''
                                                }

                                                <p>
                                                    ${escapeHtml(
                                                        bus.reason
                                                    )}
                                                </p>
                                            </div>
                                        `)
                                        .join('')}
                                </div>
                            `
                            : ''
                    }
                </div>
            </div>
        `;
    }


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

            schedulingConflicts =
                Array.isArray(data.conflicts)
                    ? data.conflicts
                    : [];

            renderConflicts(schedulingConflicts);

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

            schedulingConflicts = [];
            renderConflicts([]);
            updateConfirmButton();
        } finally {
            setLoading(false);
        }
    }


    async function confirmSchedule() {
        if (!confirmButton || !recommendations.length) {
            return;
        }

        const confirmUrl = normalizePath(
            confirmButton.dataset.confirmUrl,
            '/operation/auto-scheduling/confirm'
        );

        const csrfToken = form
            ?.querySelector('input[name="_token"]')
            ?.value;

        setConfirmLoading(true);

        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({
                    recommendations: recommendations.map((item) => ({
                        trip_schedule_id: item.trip_schedule_id,
                        driver_attendance_id: item.driver_attendance_id,
                        bus_id: item.bus_id,
                    })),
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(getErrorMessage(data));
            }

            recommendations = [];
            renderRecommendations(recommendations);

            if (footerMessage) {
                footerMessage.textContent =
                    data.message
                    || 'Schedule assignments saved successfully.';
            }

            if (data.redirect_url) {
                window.location.assign(
                    normalizePath(
                        data.redirect_url,
                        '/operation/driver-bus-assignment'
                    )
                );
            }
        } catch (error) {
            console.error(error);

            if (footerMessage) {
                footerMessage.textContent =
                    error.message
                    || 'Unable to save the schedule.';
            }

            window.alert(
                error.message
                || 'Unable to save the schedule.'
            );
        } finally {
            setConfirmLoading(false);
        }
    }


    function setConfirmLoading(loading) {
        if (!confirmButton) {
            return;
        }

        confirmButton.disabled =
            loading || recommendations.length === 0;

        confirmButton.innerHTML = loading
            ? `
                <i class="fa-solid fa-spinner fa-spin"></i>
                Saving...
            `
            : `
                <i class="fa-solid fa-circle-check"></i>
                Confirm Schedule
            `;
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
            .map((item) => {
                const ai = item.ai || {};
                const aiConflict = ai.conflict || {};
                const analysis = ai || {};

                const findings = Array.isArray(
                    aiConflict.findings
                )
                    ? aiConflict.findings
                    : [];

                const actions = Array.isArray(
                    aiConflict.recommended_actions
                )
                    ? aiConflict.recommended_actions
                    : [];

                const warnings = Array.isArray(
                    analysis.warnings
                )
                    ? analysis.warnings
                    : [];

                const alternativeDrivers =
                    Array.isArray(
                        analysis.alternative_drivers
                    )
                        ? analysis.alternative_drivers
                        : [];

                const alternativeBuses =
                    Array.isArray(
                        analysis.alternative_buses
                    )
                        ? analysis.alternative_buses
                        : [];

                return `
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
                                    The system could not create a
                                    conflict-free assignment.
                                </span>
                            </div>
                        </div>

                        ${
                            ai.available
                                ? renderAiConflictAnalysis({
                                    ai,
                                    aiConflict,
                                    findings,
                                    actions,
                                    warnings,
                                    alternativeDrivers,
                                    alternativeBuses,
                                })
                                : `
                                    <div class="ai-unavailable-notice">
                                        <i class="fa-solid fa-robot"></i>

                                        <div>
                                            <strong>
                                                AI explanation unavailable
                                            </strong>

                                            <span>
                                                The normal scheduling conflict
                                                remains valid. You may resolve
                                                it manually.
                                            </span>
                                        </div>
                                    </div>
                                `
                        }

                        ${renderConflictButtons(item, items.indexOf(item))}
                    </div>
                `;
            })
            .join('');
    }



    function renderConflictButtons(item, index) {
        const actions = Array.isArray(
            item?.ai?.conflict?.recommended_actions
        )
            ? item.ai.conflict.recommended_actions
            : [];

        const timeAction = actions.find(
            (action) =>
                action.type === 'adjust_departure_time'
                && action.suggested_time
        );

        return `
            <div class="ai-resolution-buttons">
                <a
                    href="/operation/driver-bus-assignment"
                    class="ai-resolution-btn manual"
                >
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    Resolve Manually
                </a>

                <button
                    type="button"
                    class="ai-resolution-btn primary"
                    data-review-ai-resolution="${index}"
                    ${timeAction ? '' : 'disabled'}
                    title="${
                        timeAction
                            ? 'Review and apply the AI resolution'
                            : 'No safe automatic resolution is available'
                    }"
                >
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Review AI Resolution
                </button>
            </div>
        `;
    }


    function getTimeResolutionAction(conflict) {
        const actions = Array.isArray(
            conflict?.ai?.conflict?.recommended_actions
        )
            ? conflict.ai.conflict.recommended_actions
            : [];

        return actions.find(
            (action) =>
                action.type === 'adjust_departure_time'
                && action.suggested_time
        ) || null;
    }


    function openResolutionModal(conflict) {
        const action = getTimeResolutionAction(conflict);

        if (!action) {
            window.alert(
                'No safe automatic resolution is available for this conflict.'
            );
            return;
        }

        closeResolutionModal();

        const overlay = document.createElement('div');
        overlay.className = 'ai-resolution-modal-overlay';
        overlay.id = 'aiResolutionModal';

        overlay.innerHTML = `
            <div
                class="ai-resolution-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="aiResolutionModalTitle"
            >
                <div class="ai-resolution-modal-header">
                    <div>
                        <span class="section-eyebrow">
                            AI-assisted resolution
                        </span>
                        <h2 id="aiResolutionModalTitle">
                            Review proposed solution
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="ai-modal-close"
                        data-close-resolution-modal
                        aria-label="Close"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="ai-resolution-summary-grid">
                    <div>
                        <span>Trip</span>
                        <strong>${escapeHtml(conflict.trip_code)}</strong>
                    </div>
                    <div>
                        <span>Current departure</span>
                        <strong>${escapeHtml(
                            conflict.departure_display
                            || formatDisplayTime(conflict.departure_time)
                        )}</strong>
                    </div>
                    <div class="proposed">
                        <span>Proposed departure</span>
                        <strong>${escapeHtml(
                            formatDisplayTime(action.suggested_time)
                        )}</strong>
                    </div>
                </div>

                <div class="ai-resolution-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>
                        Laravel will check the driver, bus, attendance,
                        shift, PMS status, and overlapping trips again
                        before saving. Nothing will be changed when the
                        proposed time is no longer valid.
                    </p>
                </div>

                <p class="ai-resolution-description">
                    ${escapeHtml(action.explanation || '')}
                </p>

                <div
                    class="ai-resolution-error"
                    id="aiResolutionError"
                    hidden
                ></div>

                <div class="ai-resolution-modal-actions">
                    <button
                        type="button"
                        class="ai-resolution-btn manual"
                        data-close-resolution-modal
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="ai-resolution-btn primary"
                        id="applyAiResolutionButton"
                    >
                        <i class="fa-solid fa-circle-check"></i>
                        Apply Resolution
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.classList.add('ai-modal-open');

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeResolutionModal();
            }
        });

        document
            .getElementById('applyAiResolutionButton')
            ?.addEventListener('click', async () => {
                await applyAiResolution(conflict, action);
            });
    }


    function closeResolutionModal() {
        document
            .getElementById('aiResolutionModal')
            ?.remove();

        document.body.classList.remove('ai-modal-open');
    }


    async function applyAiResolution(conflict, action) {
        const button = document.getElementById(
            'applyAiResolutionButton'
        );

        const errorBox = document.getElementById(
            'aiResolutionError'
        );

        const csrfToken = form
            ?.querySelector('input[name="_token"]')
            ?.value;

        if (!button) {
            return;
        }

        button.disabled = true;
        button.innerHTML = `
            <i class="fa-solid fa-spinner fa-spin"></i>
            Applying...
        `;

        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = '';
        }

        try {
            const response = await fetch(
                '/operation/auto-scheduling/resolve',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify({
                        trip_schedule_id:
                            conflict.trip_schedule_id,
                        resolution_type:
                            'adjust_departure_time',
                        suggested_time:
                            normalizeApiTime(
                                action.suggested_time
                            ),
                    }),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    getErrorMessage(data)
                );
            }

            closeResolutionModal();

            window.alert(
                data.message
                || 'The scheduling conflict was resolved.'
            );

            await generateSchedule();
        } catch (error) {
            console.error(error);

            if (errorBox) {
                errorBox.hidden = false;
                errorBox.textContent =
                    error.message
                    || 'Unable to apply the resolution.';
            }
        } finally {
            button.disabled = false;
            button.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                Apply Resolution
            `;
        }
    }


    function normalizeApiTime(value) {
        const parts = String(value || '')
            .trim()
            .split(':');

        if (parts.length < 2) {
            return String(value || '');
        }

        return [
            parts[0].padStart(2, '0'),
            parts[1].padStart(2, '0'),
            (parts[2] || '00').padStart(2, '0'),
        ].join(':');
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
            confirmButton.disabled =
                recommendations.length === 0;
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
