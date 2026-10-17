window.GCTPartialNavigation.registerInitializer('operation-auto-scheduling', '.auto-scheduling-page', () => {
    const pageRoot = document.querySelector('.auto-scheduling-page');
    const canEditOperation =
        pageRoot?.dataset.operationEdit === 'true';
    const canAssignReserve = pageRoot?.dataset.reserveHead === 'true';

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

    const incomingPreviewBody =
        document.getElementById('autoScheduleIncomingBody');

    const outgoingPreviewBody =
        document.getElementById('autoScheduleOutgoingBody');

    const incomingTripCount =
        document.getElementById('incomingTripCount');

    const incomingRequiredBusesCount =
        document.getElementById('incomingRequiredBusesCount');

    const incomingReadyCount =
        document.getElementById('incomingReadyCount');

    const incomingReviewCount =
        document.getElementById('incomingReviewCount');

    const outgoingTripCount =
        document.getElementById('outgoingTripCount');

    const outgoingRequiredBusesCount =
        document.getElementById('outgoingRequiredBusesCount');

    const outgoingReadyCount =
        document.getElementById('outgoingReadyCount');

    const outgoingReviewCount =
        document.getElementById('outgoingReviewCount');

    const incomingManualCount = document.getElementById('incomingManualCount');
    const incomingExternalCount = document.getElementById('incomingExternalCount');
    const outgoingManualCount = document.getElementById('outgoingManualCount');
    const outgoingExternalCount = document.getElementById('outgoingExternalCount');

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

    const aiMlStatus =
        document.getElementById('aiMlStatus');

    const resourceAvailableDrivers =
        document.getElementById('resourceAvailableDrivers');

    const resourceAvailableBuses =
        document.getElementById('resourceAvailableBuses');

    const rulesToggle = pageRoot?.querySelector('[data-rules-toggle]');
    const rulesGrid = pageRoot?.querySelector('#autoSchedulingRulesGrid');
    rulesToggle?.addEventListener('click', () => {
        if (!rulesGrid) return;
        const expand = rulesGrid.hidden;
        rulesGrid.hidden = !expand;
        rulesToggle.setAttribute('aria-expanded', String(expand));
        rulesToggle.querySelector('span').textContent = expand ? 'Hide rules' : 'Show rules';
        rulesToggle.querySelector('i').className = expand ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
    });

    // Refresh resource counts whenever the selected schedule criteria change.
    // Reuse the server-side index queries so these numbers match actual eligibility.
    const filterControls = [
        document.getElementById('autoScheduleDate'),
        document.getElementById('autoScheduleShift'),
        document.getElementById('autoScheduleRoute'),
    ].filter(Boolean);
    let availabilityRequest = null;
    let availabilityVersion = 0;
    const refreshAvailability = async () => {
        if (!form) return;
        availabilityRequest?.abort();
        const request = new AbortController();
        availabilityRequest = request;
        const version = ++availabilityVersion;
        const params = new URLSearchParams(new FormData(form));
        params.delete('_token');
        const url = new URL(window.location.pathname, window.location.origin);
        url.search = params.toString();
        const targets = [
            'summaryTrips', 'summaryDrivers', 'summaryBuses', 'summaryConflicts',
            'generationTripCount', 'generationDriverCount', 'generationBusCount',
            'resourceAvailableDrivers', 'resourceAvailableBuses',
        ];
        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                signal: request.signal,
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Availability refresh failed');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (version !== availabilityVersion || !pageRoot.isConnected) return;
            for (const id of targets) {
                const source = doc.getElementById(id);
                const target = document.getElementById(id);
                if (source && target) target.textContent = source.textContent.trim();
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.warn('Auto Scheduling counts could not refresh:', error);
            }
        }
    };
    for (const control of filterControls) {
        control.addEventListener('change', refreshAvailability);
    }

    let recommendations = [];
    let schedulingConflicts = [];
    let deferredSlots = [];
    // Drafts persist preview decisions, but never reserve a vehicle.
    const draftSaveStatus = document.getElementById('autoDraftSaveStatus');
    const setDraftSaveStatus = (message, state = 'idle') => {
        if (!draftSaveStatus) return;
        draftSaveStatus.textContent = message;
        draftSaveStatus.dataset.state = state;
    };
    const draftButton = document.getElementById('saveAutoScheduleDraftButton');
    const draftPrintButton = document.getElementById('printAutoScheduleDraftButton');
    const draftKey = () => {
        const values = new FormData(form);
        return new URLSearchParams({
            schedule_date: String(values.get('schedule_date') || ''),
            shift: String(values.get('shift') || 'all'),
            shuttle_route_id: String(values.get('shuttle_route_id') || 'all'),
        });
    };
    let draftSaveTimer = null;
    let draftRestoreVersion = 0;
    let draftRestoreInProgress = false;
    let draftConfirmed = false;
    let lastSummary = {};
    let previewScope = null;
    const currentScope = () => draftKey().toString();
    const hasPreview = () => recommendations.length > 0 || schedulingConflicts.length > 0 || deferredSlots.length > 0;
    const draftCsrfToken = () => form?.querySelector('input[name="_token"]')?.value || '';
    async function saveScheduleDraft({ silent = false } = {}) {
        if (!canEditOperation || !form) return;
        const key = draftKey();
        if (!key.get('schedule_date') || previewScope !== key.toString()) return;
        const result = await fetch('/operation/auto-scheduling/draft', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': draftCsrfToken(),
            },
            body: JSON.stringify({
                ...Object.fromEntries(key),
                recommendations, conflicts: schedulingConflicts, decisions: deferredSlots, summary: lastSummary,
            }),
        });
        const responseData = await result.json().catch(() => ({}));
        if (!result.ok) {
            setDraftSaveStatus('Draft not saved', 'error');
            throw new Error(getErrorMessage(responseData) || 'Unable to save draft. Please retry.');
        }
        setDraftSaveStatus('Draft saved', 'saved');
        if (!silent) window.showSystemToast?.('Schedule draft saved.', 'success', 'Draft Saved');
    }
    function queueDraftSave() {
        if (!canEditOperation || !form || previewScope !== currentScope()) return;
        clearTimeout(draftSaveTimer);
        setDraftSaveStatus('Saving changes...', 'saving');
        draftSaveTimer = setTimeout(() => {
            saveScheduleDraft({ silent: true }).catch(error => {
                window.showSystemToast?.(error.message, 'error', 'Draft Not Saved');
            });
        }, 750);
    }
    async function restoreScheduleDraft() {
        if (!form || !pageRoot?.isConnected || draftConfirmed) return;
        document.dispatchEvent(new CustomEvent('gct:auto-reserve-selections-reset'));
        clearTimeout(draftSaveTimer);
        const version = ++draftRestoreVersion;
        const key = draftKey();
        const targetScope = key.toString();
        previewScope = null;
        recommendations = [];
        schedulingConflicts = [];
        deferredSlots = [];
        lastSummary = {};
        draftRestoreInProgress = true;
        try {
            renderRecommendations([], []);
            renderConflicts([]);
            updateMlStatus([]);
        } finally {
            draftRestoreInProgress = false;
        }
        setDraftSaveStatus('No draft for selected filters', 'idle');
        if (footerMessage) footerMessage.textContent = 'Generate a schedule for the selected date, shift and route.';
        if (!key.get('schedule_date')) return;
        try {
            const response = await fetch('/operation/auto-scheduling/draft?' + key, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            if (!response.ok || version !== draftRestoreVersion || targetScope !== currentScope()) return;
            const { draft } = await response.json();
            if (!draft || version !== draftRestoreVersion || targetScope !== currentScope()) return;
            previewScope = targetScope;
            recommendations = Array.isArray(draft.recommendations) ? draft.recommendations : [];
            // Migrate older drafts that hid review slots but left them in the active conflict list.
            schedulingConflicts = Array.isArray(draft.conflicts)
                ? draft.conflicts.filter(conflict => !conflict.dismissed_from_preview)
                : [];
            deferredSlots = Array.isArray(draft.decisions) ? draft.decisions : [];
            lastSummary = draft.summary || {};
            draftRestoreInProgress = true;
            try {
                updateSummary(lastSummary);
                renderRecommendations(recommendations, schedulingConflicts);
                renderConflicts(schedulingConflicts);
                updateMlStatus(schedulingConflicts);
            } finally {
                draftRestoreInProgress = false;
            }
            setDraftSaveStatus('Draft restored', 'saved');
            if (footerMessage) footerMessage.textContent = 'Saved draft restored. Availability will be revalidated before confirmation.';
        } catch (error) {
            console.warn('Unable to restore Auto-Scheduling draft:', error);
        }
    }
    const savedDraftsButton = document.getElementById('openAutoScheduleDrafts');
    const savedDraftsDialog = document.createElement('dialog');
    savedDraftsDialog.className = 'auto-saved-drafts-dialog';
    savedDraftsDialog.setAttribute('aria-label', 'Saved Auto-Scheduling drafts');
    savedDraftsDialog.innerHTML = `
        <header><h2><i class="fa-solid fa-folder-open"></i> Saved Drafts</h2>
        <button type="button" data-close-drafts aria-label="Close saved drafts"><i class="fa-solid fa-xmark"></i></button></header>
        <p>Choose a draft to restore. Drafts are previews and do not reserve buses.</p>
        <div class="auto-drafts-list" aria-live="polite">Loading drafts...</div>`;
    pageRoot.appendChild(savedDraftsDialog);
    savedDraftsDialog.querySelector('[data-close-drafts]').addEventListener('click', () => savedDraftsDialog.close());
    savedDraftsButton?.addEventListener('click', async () => {
        savedDraftsDialog.showModal();
        const list = savedDraftsDialog.querySelector('.auto-drafts-list');
        list.textContent = 'Loading drafts...';
        try {
            const response = await fetch('/operation/auto-scheduling/drafts', {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Unable to load drafts.');
            const data = await response.json();
            const drafts = Array.isArray(data.drafts) ? data.drafts : [];
            list.replaceChildren();
            if (!drafts.length) {
                list.textContent = 'No saved drafts yet. Generate recommendations to create your first draft.';
            }
            drafts.forEach(draft => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'auto-draft-choice';
                const label = document.createElement('strong');
                label.textContent = draft.schedule_date + ' · ' + draft.shift + ' · ' + draft.shuttle_route_id;
                const details = document.createElement('small');
                details.textContent = draft.ready_count + ' recommendations · '
                    + draft.review_count + ' active conflicts · '
                    + Number(draft.decision_count || 0) + ' deferred slots · Saved ' + draft.updated_at;
                button.append(label, details);
                button.addEventListener('click', () => {
                    for (const [name, value] of Object.entries({
                        schedule_date: draft.schedule_date,
                        shift: draft.shift,
                        shuttle_route_id: draft.shuttle_route_id,
                    })) {
                        const field = form?.elements.namedItem(name);
                        if (field) field.value = value;
                    }
                    savedDraftsDialog.close();
                    void restoreScheduleDraft();
                });
                list.appendChild(button);
            });
        } catch (error) {
            list.textContent = error.message;
        }
    });

    draftButton?.addEventListener('click', () => {
        saveScheduleDraft().catch(error => window.showSystemToast?.(error.message, 'error', 'Draft Not Saved'));
    });
    draftPrintButton?.addEventListener('click', () => window.print());
    for (const control of filterControls) control.addEventListener('change', restoreScheduleDraft);
    void restoreScheduleDraft();



    // Edits remain client-side until the existing Confirm Schedule transaction.
    let editing = null;
    const editDialog = document.createElement('dialog');
    editDialog.className = 'auto-preview-edit-dialog';
    editDialog.setAttribute('aria-label', 'Edit schedule assignment');
    editDialog.innerHTML = `
        <form method="dialog" class="auto-preview-edit-form">
            <h3>Edit Assignment</h3>
            <p class="auto-preview-edit-context"></p>
            <label>Driver<select name="driver" required></select></label>
            <label>Bus<select name="bus" required></select></label>
            <p class="auto-preview-edit-error" role="alert" hidden></p>
            <div class="auto-preview-edit-actions">
                <button type="button" data-cancel-preview-edit>Cancel</button>
                <button type="submit" class="schedule-primary-btn">Apply to Preview</button>
            </div>
        </form>`;
    pageRoot?.append(editDialog);
    editDialog.querySelector('[data-cancel-preview-edit]')?.addEventListener('click', () => editDialog.close());
    const previewEditUrl = '/operation/auto-scheduling/edit-preview';
    const csrfTokenForPreview = () => form?.querySelector('input[name="_token"]')?.value || '';
    const otherPreviewAssignments = () => recommendations
        .filter((_, index) => !(editing?.kind === 'ready' && index === editing.index))
        .map(({ trip_schedule_id, driver_attendance_id, bus_id }) => ({
            trip_schedule_id, driver_attendance_id, bus_id,
        }));
    async function previewEditRequest(payload) {
        const response = await fetch(previewEditUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfTokenForPreview(),
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(getErrorMessage(data));
        return data;
    }
    const conflictIdentity = (conflict) => [
        Number(conflict?.trip_schedule_id || 0),
        Number(conflict?.slot ?? conflict?.slot_number ?? 1),
    ].join(':');

    window.__gctAutoScheduleReserveAbortController?.abort();
    const reserveAbortController = new AbortController();
    window.__gctAutoScheduleReserveAbortController = reserveAbortController;

    // Inline alternative selection: reuse backend preview validation without opening a modal.
    document.addEventListener('gct:apply-inline-alternative', async (event) => {
        if (!canEditOperation) return;
        const detail = event.detail || {};
        const index = schedulingConflicts.findIndex(
            (item) => conflictIdentity(item) === detail.conflictKey
        );
        const item = schedulingConflicts[index];
        if (!item || Number(item.trip_schedule_id) !== Number(detail.tripScheduleId)) {
            window.showSystemToast?.('This trip slot changed. Regenerate the schedule.', 'warning');
            return;
        }
        const driverId = Number(detail.driverId);
        const busId = Number(detail.busId);
        if (!driverId || !busId) {
            window.showSystemToast?.('Select a valid driver and bus pair first.', 'warning');
            return;
        }
        if (detail.reserve && !canAssignReserve) {
            window.showSystemToast?.('Only Operations Head can assign a Reserve Bus.', 'warning');
            return;
        }
        const applyButton = document.querySelector('[data-gct-apply-inline="' + CSS.escape(detail.conflictKey) + '"]');
        if (applyButton) applyButton.disabled = true;
        try {
            const data = await previewEditRequest({
                trip_schedule_id: item.trip_schedule_id,
                driver_attendance_id: driverId,
                bus_id: busId,
                recommendations: otherPreviewAssignments(),
            });
            if (detail.reserve && data.bus.purpose !== 'Reserve') {
                throw new Error('The selected Reserve Bus changed. Regenerate the schedule.');
            }
            const replacement = {
                ...item,
                driver_attendance_id: data.driver.id,
                driver_id: data.driver.driver_id,
                driver_name: data.driver.name,
                driver_status: data.driver.status,
                bus_id: data.bus.id,
                bus_no: data.bus.name,
                bus_purpose: data.bus.purpose || 'Regular',
                reserve_approval_required: data.bus.purpose === 'Reserve',
                result: 'Ready',
            };
            schedulingConflicts.splice(index, 1);
            recommendations.push(replacement);
            renderRecommendations(recommendations, schedulingConflicts);
            renderConflicts(schedulingConflicts);
            updateReadyBadgeOnly();
            document.dispatchEvent(new CustomEvent('gct:inline-preview-applied', {
                detail: { conflictKey: detail.conflictKey },
            }));
            window.showSystemToast?.('Assignment applied to preview. Confirm Schedule is still required.', 'success');
        } catch (error) {
            window.showSystemToast?.(error.message || 'Assignment validation failed.', 'error', 'Cannot apply alternative');
            if (applyButton) applyButton.disabled = false;
        }
    }, { signal: reserveAbortController.signal });
    // Report-side selection is a preview action, not an Edit Assignment modal.
    document.addEventListener('gct:apply-reserve-selection', async (event) => {
        if (!canEditOperation) return;
        const index = Number(event.detail?.index);
        const item = schedulingConflicts[index];
        if (!item || item.issue_type !== 'reserve_approval_required') return;
        const driverId = Number(item.suggested_driver_attendance_id);
        const busId = Number(item.suggested_bus_id);
        if (!driverId || !busId) return;

        try {
            const data = await previewEditRequest({
                trip_schedule_id: item.trip_schedule_id,
                driver_attendance_id: driverId,
                bus_id: busId,
                recommendations: recommendations.map(({ trip_schedule_id, driver_attendance_id, bus_id }) => ({
                    trip_schedule_id, driver_attendance_id, bus_id,
                })),
            });
            const replacement = {
                ...item,
                driver_attendance_id: data.driver.id,
                driver_id: data.driver.driver_id,
                driver_name: data.driver.name,
                driver_status: data.driver.status,
                bus_id: data.bus.id,
                bus_no: data.bus.name,
                bus_purpose: data.bus.purpose || 'Reserve',
                reserve_approval_required: true,
                result: 'Ready',
            };
            schedulingConflicts.splice(index, 1);
            recommendations.push(replacement);
            renderRecommendations(recommendations, schedulingConflicts);
            renderConflicts(schedulingConflicts);
            updateReadyBadgeOnly();
            document.dispatchEvent(new CustomEvent('gct:reserve-preview-applied', { detail: { index } }));
            window.showSystemToast?.('Reserve Bus added to the preview. Confirm Schedule is still required.', 'success');
        } catch (error) {
            window.showSystemToast?.(error.message, 'error', 'Reserve Bus not eligible');
        }
    });

    document.addEventListener('gct:defer-conflict-slot', (event) => {
        if (!canEditOperation) return;
        const index = Number(event.detail?.index);
        const decision = event.detail?.decision;
        if (!Number.isInteger(index) || !['manual_later', 'external_bus'].includes(decision)) return;
        const conflict = schedulingConflicts[index];
        if (!conflict) return;
        schedulingConflicts.splice(index, 1);
        deferredSlots.push({ ...conflict, result: 'Deferred', decision, notes: '' });
        renderRecommendations(recommendations, schedulingConflicts);
        renderConflicts(schedulingConflicts);
        updateMlStatus(schedulingConflicts);
        updateReadyBadgeOnly();
    });

    previewSection?.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-edit-kind][data-edit-index]');
        if (!btn || !canEditOperation) return;
        const kind = btn.dataset.editKind;
        const index = Number(btn.dataset.editIndex);
        const item = kind === 'ready' ? recommendations[index] : schedulingConflicts[index];
        if (!item) return;
        editing = { kind, index, item };
        const errorEl = editDialog.querySelector('.auto-preview-edit-error');
        errorEl.hidden = true;
        editDialog.querySelector('.auto-preview-edit-context').textContent =
            `${item.base_trip_code || item.trip_code} — Slot ${item.slot || 1} of ${item.required_buses || 1}`;
        try {
            const data = await previewEditRequest({ trip_schedule_id: item.trip_schedule_id });
            for (const [field, choices, selected] of [
                ['driver', data.drivers, item.driver_attendance_id],
                ['bus', data.buses, item.bus_id],
            ]) {
                const select = editDialog.querySelector(`[name="${field}"]`);
                select.replaceChildren(new Option('Select ' + field, ''));
                for (const option of choices) {
                    select.add(new Option(option.name, String(option.id)));
                }
                if (selected) select.value = String(selected);
            }
            editDialog.showModal();
        } catch (error) {
            window.showSystemToast?.(error.message, 'error', 'Cannot edit assignment');
        }
    });
    editDialog.querySelector('form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!editing) return;
        const submit = editDialog.querySelector('[type="submit"]');
        const errorEl = editDialog.querySelector('.auto-preview-edit-error');
        submit.disabled = true;
        errorEl.hidden = true;
        try {
            const driverId = Number(editDialog.querySelector('[name="driver"]').value);
            const busId = Number(editDialog.querySelector('[name="bus"]').value);
            if (!driverId || !busId) throw new Error('Select an eligible driver and bus.');
            const data = await previewEditRequest({
                trip_schedule_id: editing.item.trip_schedule_id,
                driver_attendance_id: driverId,
                bus_id: busId,
                recommendations: otherPreviewAssignments(),
            });
            const replacement = {
                ...editing.item,
                driver_attendance_id: data.driver.id,
                driver_id: data.driver.driver_id,
                driver_name: data.driver.name,
                driver_status: data.driver.status,
                bus_id: data.bus.id,
                bus_no: data.bus.name,
                bus_purpose: data.bus.purpose || 'Regular',
                reserve_approval_required: data.bus.purpose === 'Reserve',
                result: 'Ready',
            };
            if (editing.kind === 'ready') {
                recommendations[editing.index] = replacement;
            } else {
                const conflictIndex = schedulingConflicts.findIndex(
                    (conflict) => conflictIdentity(conflict) === (editing.conflictKey || conflictIdentity(editing.item))
                );
                if (conflictIndex < 0) {
                    throw new Error('This conflict changed. Regenerate the schedule and select the Reserve Bus again.');
                }
                schedulingConflicts.splice(conflictIndex, 1);
                recommendations.push(replacement);
            }
            editDialog.close();
            editing = null;
            renderRecommendations(recommendations, schedulingConflicts);
            renderConflicts(schedulingConflicts);
            updateReadyBadgeOnly();
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    const requestRegeneration = () => {
        if (previewScope !== currentScope() || !hasPreview()) {
            void generateSchedule();
            return;
        }
        if (typeof window.openSystemConfirmation === 'function') {
            window.openSystemConfirmation({
                title: 'Replace Existing Draft?',
                message: 'Regenerating will replace this preview with new recommendations. Your current saved draft will be overwritten.',
                button: 'Regenerate Draft',
                type: 'warning',
                closeOnConfirm: true,
            }, () => { void generateSchedule(); });
            return;
        }
        window.showSystemToast?.('Save or remove the current preview before generating again.', 'warning', 'Draft Already Open');
    };
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        requestRegeneration();
    });

    regenerateButton?.addEventListener('click', requestRegeneration);


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

            ${renderMlProvenance(ai)}

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


function renderMlProvenance(ai) {
    const ml = ai.ml || {};

    const anySignal =
        Boolean(ml.bus_ready)
        || Boolean(ml.driver_ready);

    if (!anySignal) {
        return '';
    }

    const labelFor = (source) => {
        if (source === 'ml') {
            return 'ML model';
        }
        if (source === 'data' || source === 'data_fallback') {
            return 'Data-derived';
        }
        return 'Rule-based';
    };

    const scoreRow = (label, scores) => {
        const entries = Object.entries(scores || {});
        if (!entries.length) {
            return '';
        }
        const pills = entries
            .map(([id, value]) => {
                const num = Number(value);
                if (!Number.isFinite(num)) {
                    return '';
                }
                return `<span class="ai-ml-score" title="Candidate ${escapeHtml(id)}">${escapeHtml(id)}: ${num}</span>`;
            })
            .join('');
        return `
            <div class="ai-ml-scores">
                <span class="ai-ml-scores-label">${escapeHtml(label)}</span>
                ${pills}
            </div>
        `;
    };

    const chip = (ready, source, title) => `
        <span class="ai-ml-chip ${
            ready ? 'is-ready' : 'is-fallback'
        }" title="${escapeHtml(title)}">
            <i class="fa-solid fa-microchip"></i>
            ${escapeHtml(title)}:
            ${escapeHtml(labelFor(source))}
        </span>
    `;

    return `
        <div class="ai-ml-provenance">
            ${chip(ml.bus_ready, ml.bus_source, 'Bus')}
            ${chip(ml.driver_ready, ml.driver_source, 'Driver')}
            ${scoreRow('Bus suitability', ml.bus_suitability)}
            ${scoreRow('Driver suitability', ml.driver_suitability)}
        </div>
    `;
}


function updateMlStatus(conflicts) {
    if (!aiMlStatus) {
        return;
    }

    let busReady = false;
    let driverReady = false;
    let busSource = 'rule_fallback';
    let driverSource = 'rule_fallback';
    let found = false;

    for (const item of conflicts) {
        const ml = item?.ai?.ml || {};

        if (typeof ml.bus_ready !== 'boolean'
            && typeof ml.driver_ready !== 'boolean') {
            continue;
        }

        found = true;
        busReady = busReady || Boolean(ml.bus_ready);
        driverReady = driverReady || Boolean(ml.driver_ready);

        if (ml.bus_source) {
            busSource = ml.bus_source;
        }

        if (ml.driver_source) {
            driverSource = ml.driver_source;
        }
    }

    if (!found) {
        aiMlStatus.innerHTML = `
            <span class="ai-status-label"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ranking Source</span>
            <span class="ai-status-badge is-pending"><i class="fa-solid fa-bus" aria-hidden="true"></i> Bus: Not reported</span>
            <span class="ai-status-badge is-pending"><i class="fa-solid fa-user" aria-hidden="true"></i> Driver: Not reported</span>
        `;
        return;
    }

    const badge = (ready, source, label) => `
        <span class="ai-status-badge ${
            ready ? 'is-ready' : 'is-fallback'
        }">
            <i class="fa-solid fa-microchip"></i>
            ${escapeHtml(label)}:
            ${escapeHtml(
                source === 'ml'
                    ? 'ML model'
                    : source === 'data' || source === 'data_fallback'
                        ? 'Data-derived'
                        : 'Rule-based'
            )}
        </span>
    `;

    aiMlStatus.innerHTML = `
        <span class="ai-status-label">
            <i class="fa-solid fa-microchip"></i>
            Ranking Source
        </span>
        ${badge(busReady, busSource, 'Bus')}
        ${badge(driverReady, driverSource, 'Driver')}
    `;
}

    function getResolutionActionLabel(action) {
        if (
            action?.type === 'adjust_departure_time'
            && action?.suggested_time
        ) {
            return `Review departure at ${
                formatDisplayTime(action.suggested_time)
            }`;
        }

        return action?.title
            || action?.label
            || 'Review recommended action';
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

        document.dispatchEvent(new CustomEvent('gct:auto-reserve-selections-reset'));

        const generateUrl =
            normalizePath(
                generateButton.dataset.generateUrl,
                '/operation/auto-scheduling/generate'
            );

        draftRestoreVersion += 1;
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

            schedulingConflicts =
                Array.isArray(data.conflicts)
                    ? data.conflicts
                    : [];
            deferredSlots = [];

            previewScope = currentScope();
            lastSummary = data.summary || {};
            updateSummary(lastSummary);
            renderRecommendations(recommendations, schedulingConflicts);
            clearTimeout(draftSaveTimer);
            if (canEditOperation) {
                try {
                    await saveScheduleDraft({ silent: true });
                } catch (saveError) {
                    window.showSystemToast?.(saveError.message, 'error', 'Draft Not Saved');
                }
            }

            renderConflicts(schedulingConflicts);
            updateMlStatus(schedulingConflicts);

            previewSection?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        } catch (error) {
            console.error(error);

            recommendations = [];
            deferredSlots = [];

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
        if (!canEditOperation) {
            window.showSystemToast?.(
                'Your role has view-only access to Operation scheduling.',
                'warning',
                'View-only access'
            );
            return;
        }

        if (!confirmButton || (recommendations.length === 0 && deferredSlots.length === 0)) {
            return;
        }
        if (schedulingConflicts.length > 0) {
            window.showSystemToast?.(
                'Resolve all scheduling conflicts before confirming. Removing a review slot from the preview does not resolve its conflict.',
                'warning', 'Conflicts Pending'
            );
            return;
        }

        const reserveRecommendations = recommendations.filter(
            (item) => item.bus_purpose === 'Reserve' || item.reserve_approval_required
        );
        if (reserveRecommendations.length && !canAssignReserve) {
            window.showSystemToast?.(
                'Only the Operations Head may confirm Reserve Bus assignments.',
                'warning', 'Head Access Required'
            );
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
                    draft_filters: Object.fromEntries(draftKey()),
                    conflicts: schedulingConflicts,
                    decisions: deferredSlots.map((item) => ({
                        trip_schedule_id: item.trip_schedule_id,
                        slot: item.slot,
                        decision: item.decision,
                        notes: item.notes || null,
                    })),
                    recommendations: recommendations.map((item) => ({
                        trip_schedule_id: item.trip_schedule_id,
                        driver_attendance_id: item.driver_attendance_id,
                        bus_id: item.bus_id,
                        slot: item.slot,

                    })),
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(getErrorMessage(data));
            }

            draftConfirmed = true;
            setDraftSaveStatus('Schedule confirmed', 'saved');
            clearTimeout(draftSaveTimer);
            recommendations = [];
            schedulingConflicts = [];
            deferredSlots = [];
            renderRecommendations(recommendations, schedulingConflicts);

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
            loading || (recommendations.length === 0 && deferredSlots.length === 0) || schedulingConflicts.length > 0;

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
                <span>${Number(summary.ready || 0)} Ready Slots</span>
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


    function getDirection(item) {
        return String(item?.direction || '').trim().toLowerCase();
    }

    function renderRecommendations(items, conflicts = schedulingConflicts) {
        const safeItems = Array.isArray(items) ? items : [];
        const safeConflicts = Array.isArray(conflicts) ? conflicts : [];
        const safeDecisions = Array.isArray(deferredSlots) ? deferredSlots : [];
        const visibleConflicts = safeConflicts;

        // Partition exclusively by each trip's actual direction field
        const incomingRecs = safeItems.filter((r) => getDirection(r) === 'incoming');
        const incomingConfs = visibleConflicts.filter((c) => getDirection(c) === 'incoming');
        const incomingDecisions = safeDecisions.filter((d) => getDirection(d) === 'incoming');

        const outgoingRecs = safeItems.filter((r) => getDirection(r) === 'outgoing');
        const outgoingConfs = visibleConflicts.filter((c) => getDirection(c) === 'outgoing');
        const outgoingDecisions = safeDecisions.filter((d) => getDirection(d) === 'outgoing');

        setText(incomingManualCount, incomingDecisions.filter((d) => d.decision === 'manual_later').length);
        setText(incomingExternalCount, incomingDecisions.filter((d) => d.decision === 'external_bus').length);
        setText(outgoingManualCount, outgoingDecisions.filter((d) => d.decision === 'manual_later').length);
        setText(outgoingExternalCount, outgoingDecisions.filter((d) => d.decision === 'external_bus').length);

        // Counts are per trip, not slot positions: Slot 3 of 3 is not 3 buses assigned.
        const assignedByTrip = new Map();
        for (const rec of safeItems) {
            if (rec.result !== 'Ready') continue;
            const tripKey = String(rec.trip_schedule_id || rec.base_trip_code || rec.trip_code || '');
            assignedByTrip.set(tripKey, (assignedByTrip.get(tripKey) || 0) + 1);
        }
        const withAssignmentCount = (row) => ({
            ...row,
            tripAssignedCount: assignedByTrip.get(
                String(row.trip_schedule_id || row.base_trip_code || row.trip_code || '')
            ) || 0,
        });

        // Build combined rows with original master recommendations index
        const incomingRows = [
            ...incomingRecs.map((item) => ({
                ...withAssignmentCount(item),
                originalIndex: safeItems.indexOf(item),
            })),
            ...incomingConfs.map((conf) => ({
                ...withAssignmentCount(conf),
                originalIndex: null,
                conflictIndex: safeConflicts.indexOf(conf),
            })),
            ...incomingDecisions.map((decision) => ({
                ...withAssignmentCount(decision),
                result: 'Deferred',
                decisionIndex: safeDecisions.indexOf(decision),
            })),
        ];

        const outgoingRows = [
            ...outgoingRecs.map((item) => ({
                ...withAssignmentCount(item),
                originalIndex: safeItems.indexOf(item),
            })),
            ...outgoingConfs.map((conf) => ({
                ...withAssignmentCount(conf),
                originalIndex: null,
                conflictIndex: safeConflicts.indexOf(conf),
            })),
            ...outgoingDecisions.map((decision) => ({
                ...withAssignmentCount(decision),
                result: 'Deferred',
                decisionIndex: safeDecisions.indexOf(decision),
            })),
        ];

        const sortedIncoming = sortScheduleRows(incomingRows);
        const sortedOutgoing = sortScheduleRows(outgoingRows);

        renderDirectionSection({
            tbody: incomingPreviewBody,
            rows: sortedIncoming,
            directionName: 'Incoming',
            emptyMessage: 'No Scheduled and Unassigned incoming trips found for this date.',
            tripCountEl: incomingTripCount,
            requiredBusesEl: incomingRequiredBusesCount,
            readyCountEl: incomingReadyCount,
            reviewCountEl: incomingReviewCount,
            recs: incomingRecs,
            confs: [...incomingConfs, ...incomingDecisions],
        });

        renderDirectionSection({
            tbody: outgoingPreviewBody,
            rows: sortedOutgoing,
            directionName: 'Outgoing',
            emptyMessage: 'No Scheduled and Unassigned outgoing trips found for this date.',
            tripCountEl: outgoingTripCount,
            requiredBusesEl: outgoingRequiredBusesCount,
            readyCountEl: outgoingReadyCount,
            reviewCountEl: outgoingReviewCount,
            recs: outgoingRecs,
            confs: [...outgoingConfs, ...outgoingDecisions],
        });

        if (previewBody && previewBody.style.display !== 'none') {
            const allSorted = [...sortedIncoming, ...sortedOutgoing];
            previewBody.innerHTML = allSorted.length
                ? allSorted.map((item) => renderRowHtml(item)).join('')
                : `<tr><td colspan="8"><div class="auto-empty-state"><i class="fa-solid fa-calendar-xmark"></i><strong>No recommendations</strong><span>No recommendations were generated.</span></div></td></tr>`;
        }



        document.querySelectorAll('[data-remove-index]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.removeIndex);

                if (
                    Number.isNaN(index)
                    || !recommendations[index]
                ) {
                    return;
                }

                const [removed] = recommendations.splice(index, 1);
                schedulingConflicts.push({
                    ...removed,
                    result: 'Conflict',
                    issue_type: 'recommendation_removed',
                    missing_driver: true,
                    missing_bus: true,
                    driver_attendance_id: null,
                    driver_id: null,
                    driver_name: null,
                    bus_id: null,
                    bus_no: null,
                    reason: 'The AI recommendation was removed. Choose Manual Later, External Bus, or select another eligible assignment.',
                });

                renderRecommendations(
                    recommendations,
                    schedulingConflicts
                );

                renderConflicts(schedulingConflicts);
                updateMlStatus(schedulingConflicts);

                updateReadyBadgeOnly();
            });
        });

        document.querySelectorAll('[data-defer-decision][data-conflict-index]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.conflictIndex);
                if (!Number.isInteger(index) || index < 0 || !schedulingConflicts[index]) return;

                const [conflict] = schedulingConflicts.splice(index, 1);
                deferredSlots.push({
                    ...conflict,
                    result: 'Deferred',
                    decision: button.dataset.deferDecision,
                    notes: '',
                });
                renderRecommendations(recommendations, schedulingConflicts);
                renderConflicts(schedulingConflicts);
                updateMlStatus(schedulingConflicts);
                updateReadyBadgeOnly();
            });
        });

        document.querySelectorAll('[data-reopen-decision-index]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.reopenDecisionIndex);
                if (!Number.isInteger(index) || index < 0 || !deferredSlots[index]) return;
                const [decision] = deferredSlots.splice(index, 1);
                schedulingConflicts.push({ ...decision, result: 'Conflict', decision: undefined });
                renderRecommendations(recommendations, schedulingConflicts);
                renderConflicts(schedulingConflicts);
                updateMlStatus(schedulingConflicts);
                updateReadyBadgeOnly();
            });
        });

        if (!draftRestoreInProgress && !draftConfirmed && (recommendations.length || schedulingConflicts.length || deferredSlots.length)) queueDraftSave();
        updateConfirmButton();
    }

    function renderDirectionSection({
        tbody,
        rows,
        directionName,
        emptyMessage,
        tripCountEl,
        requiredBusesEl,
        readyCountEl,
        reviewCountEl,
        recs,
        confs,
    }) {
        if (!tbody) {
            return;
        }

        const uniqueTripCodes = new Set();
        let totalRequiredBuses = 0;

        rows.forEach((r) => {
            const code = String(r.base_trip_code || r.trip_code || '').split(' ')[0];
            if (code && !uniqueTripCodes.has(code)) {
                uniqueTripCodes.add(code);
                totalRequiredBuses += Math.max(1, Number(r.required_buses || 1));
            }
        });

        const readyCount = recs.filter((r) => r.result === 'Ready').length;
        const reviewCount = confs.length + (totalRequiredBuses > (readyCount + confs.length) ? (totalRequiredBuses - readyCount - confs.length) : 0);

        setText(tripCountEl, uniqueTripCodes.size);
        setText(requiredBusesEl, totalRequiredBuses);
        setText(readyCountEl, readyCount);
        setText(reviewCountEl, reviewCount);

        if (!rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="auto-empty-state">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <strong>No ${escapeHtml(directionName.toLowerCase())} recommendations</strong>
                            <span>${escapeHtml(emptyMessage)}</span>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = rows.map((item) => renderRowHtml(item)).join('');
    }

    function sortScheduleRows(rows) {
        return rows.slice().sort((a, b) => {
            const timeA = String(a.departure_time || '');
            const timeB = String(b.departure_time || '');
            if (timeA !== timeB) return timeA.localeCompare(timeB);

            const tripA = String(a.base_trip_code || a.trip_code || '');
            const tripB = String(b.base_trip_code || b.trip_code || '');
            if (tripA !== tripB) return tripA.localeCompare(tripB);

            const slotA = Number(a.slot || 1);
            const slotB = Number(b.slot || 1);
            return slotA - slotB;
        });
    }

    function renderRowHtml(item) {
        const cleanTripCode = escapeHtml(item.base_trip_code || String(item.trip_code || '').split(' ')[0]);
        const required = Math.max(1, Number(item.required_buses || 1));
        const slotNumber = Math.max(1, Number(item.slot || 1));
        const assigned = Math.min(required, Math.max(0, Number(item.tripAssignedCount || 0)));
        const slotLabel = required > 1
            ? `Slot ${slotNumber} of ${required}`
            : 'Single bus slot';
        const assignmentProgress = `${assigned}/${required} buses ready`;

        const isConflict = item.result === 'Conflict';

        return `
            <tr class="${isConflict ? 'conflict-row' : ''}">
                <td>
                    <strong>${cleanTripCode}</strong>
                </td>

                <td>
                    <div class="auto-time-cell">
                        <strong>
                            ${escapeHtml(
                                item.departure_display
                                || formatDisplayTime(item.departure_time)
                            )}
                        </strong>

                        <small>
                            to
                            ${escapeHtml(
                                item.arrival_display
                                || formatDisplayTime(item.estimated_arrival_time)
                            )}
                        </small>
                    </div>
                </td>

                <td>
                    <div class="route-cell">
                        <strong>
                            ${escapeHtml(item.route_code || '—')}
                        </strong>

                        <span>
                            ${escapeHtml(item.route_name || 'Route')}
                        </span>
                    </div>
                </td>

                <td>
                    <span class="required-bus-count">
                        <i class="fa-solid fa-bus"></i>
                        ${escapeHtml(slotLabel)}
                    </span>
                    <small class="auto-bus-assignment-progress">${escapeHtml(assignmentProgress)}</small>
                </td>

                <td>
                    ${renderDriver(item)}
                </td>

                <td>
                    ${renderBus(item)}
                </td>

                <td>
                    ${renderResultBadge(item)}
                </td>

                <td>
                    ${renderAction(item)}
                </td>
            </tr>
        `;
    }

    function renderDriver(item) {
        if (!item || !item.driver_name || ['Conflict', 'Deferred'].includes(item.result)) {
            return `
                <div class="driver-cell unassigned">
                    <div class="driver-avatar unassigned">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                    <div>
                        <strong class="unassigned-text">Unassigned</strong>
                        <span class="unassigned-sub">${item?.missing_driver ? 'No driver available' : 'Pending review'}</span>
                    </div>
                </div>
            `;
        }

        return `
            <div class="driver-cell">
                <div class="driver-avatar">
                    ${escapeHtml(getInitials(item.driver_name))}
                </div>

                <div>
                    <strong>
                        ${escapeHtml(item.driver_name)}
                    </strong>

                    <span>
                        ${escapeHtml(item.driver_status || 'Eligible')}
                    </span>
                </div>
            </div>
        `;
    }

    function renderBus(item) {
        if (!item || !item.bus_no || ['Conflict', 'Deferred'].includes(item.result)) {
            return `
                <span class="bus-chip unassigned">
                    <span class="unassigned-text">Unassigned</span>
                </span>
            `;
        }

        return `
            <span class="auto-bus-preview">
                <span class="bus-chip"><i class="fa-solid fa-bus" aria-hidden="true"></i> ${escapeHtml(item.bus_no)}</span>
                ${item.bus_purpose === 'Reserve' || item.reserve_approval_required
                    ? '<span class="auto-reserve-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Reserve · Head assignment</span>'
                    : ''}
            </span>
        `;
    }

    function renderResultBadge(item) {
        if (!item || item.result === 'Conflict') {
            return `
                <span class="schedule-status conflict">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Needs Review
                </span>
            `;
        }
        if (item.result === 'Deferred') {
            const external = item.decision === 'external_bus';
            return `
                <span class="schedule-status ${external ? 'external-pending' : 'manual-pending'}">
                    <i class="fa-solid ${external ? 'fa-building' : 'fa-user-gear'}"></i>
                    ${external ? 'External Bus Pending' : 'Pending Manual'}
                </span>
            `;
        }
        if (item.result === 'Unassigned') {
            return `
                <span class="schedule-status unassigned">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Unassigned
                </span>
            `;
        }

        return `
            <span class="schedule-status ready">
                <i class="fa-solid fa-circle-check"></i>
                Ready
            </span>
        `;
    }

    function renderAction(item) {
        if (item.result === 'Deferred' && Number.isInteger(item.decisionIndex)) {
            return `
                <button type="button" class="schedule-action-btn edit"
                    data-reopen-decision-index="${item.decisionIndex}"
                    title="Return this slot to active review">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Reopen</span>
                </button>
            `;
        }
        if (item.result === 'Ready' && item.originalIndex !== null && item.originalIndex !== undefined
            && (item.bus_purpose === 'Reserve' || item.reserve_approval_required)) {
            return `
                <div class="auto-reserve-actions">
                    <button type="button" class="schedule-action-btn remove"
                        data-remove-index="${item.originalIndex}"
                        title="Remove from preview">
                        <i class="fa-solid fa-circle-minus" aria-hidden="true"></i><span class="schedule-remove-label">Remove</span>
                    </button>
                </div>`;
        }
        if (item.result === 'Conflict' && Number.isInteger(item.conflictIndex) && item.conflictIndex >= 0) {
            return `
                <div class="auto-conflict-decision-actions">
                    <button type="button" class="schedule-action-btn edit"
                        data-defer-decision="manual_later" data-conflict-index="${item.conflictIndex}"
                        title="Keep this slot for manual assignment later">
                        <i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>Manual Later</span>
                    </button>
                    <button type="button" class="schedule-action-btn remove"
                        data-defer-decision="external_bus" data-conflict-index="${item.conflictIndex}"
                        title="Record an external bus arrangement as pending">
                        <i class="fa-solid fa-building" aria-hidden="true"></i><span>External Bus</span>
                    </button>
                </div>
            `;
        }
        if (item.result === 'Ready' && item.originalIndex !== null && item.originalIndex !== undefined) {
            return `
                <button
                    type="button"
                    class="schedule-action-btn remove"
                    data-remove-index="${item.originalIndex}"
                    title="Remove from preview"
                    aria-label="Remove ${escapeHtml(item.trip_code)} recommendation from preview"
                >
                    <i class="fa-solid fa-circle-minus" aria-hidden="true"></i><span class="schedule-remove-label">Remove</span>
                </button>
            `;
        }
        return `<span class="schedule-action-empty">—</span>`;
    }


    function renderConflicts(items) {
        if (!conflictSection || !conflictContent) {
            return;
        }

        if (!items.length) {
            conflictSection.hidden = true;
            conflictContent.innerHTML = '';
            document.dispatchEvent(new CustomEvent('gct:auto-conflicts-updated', {
                detail: { conflicts: [] },
            }));
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

        // Keep the detailed AI Conflict Report synchronized with saved drafts
        // and edits, not only with fresh /generate network responses.
        document.dispatchEvent(new CustomEvent('gct:auto-conflicts-updated', {
            detail: { conflicts: items.slice() },
        }));
    }



    function renderConflictButtons(item, index) {
        if (!canEditOperation) {
            return `
                <div class="ai-resolution-buttons">
                    <span class="ai-resolution-btn manual" aria-disabled="true">
                        <i class="fa-solid fa-eye"></i>
                        View-only access
                    </span>
                </div>
            `;
        }

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
        if (!canEditOperation) {
            window.showSystemToast?.(
                'Your role cannot apply scheduling resolutions.',
                'warning',
                'View-only access'
            );
            return;
        }

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

        overlay.dataset.pageOwned = 'true';
        document.body.appendChild(overlay);
        document.body.classList.add('ai-modal-open');

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
        if (!canEditOperation) {
            window.showSystemToast?.(
                'Your role cannot apply scheduling resolutions.',
                'warning',
                'View-only access'
            );
            return;
        }

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
                        slot: Number(conflict.slot || 1),
                        proposed_departure_time:
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
        const errorHtml = `
            <tr>
                <td colspan="8">
                    <div class="auto-empty-state error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <strong>Schedule generation failed</strong>
                        <span>${escapeHtml(message)}</span>
                    </div>
                </td>
            </tr>
        `;

        if (incomingPreviewBody) {
            incomingPreviewBody.innerHTML = errorHtml;
        }

        if (outgoingPreviewBody) {
            outgoingPreviewBody.innerHTML = errorHtml;
        }

        if (previewBody) {
            previewBody.innerHTML = errorHtml;
        }

        setText(incomingTripCount, 0);
        setText(incomingRequiredBusesCount, 0);
        setText(incomingReadyCount, 0);
        setText(incomingReviewCount, 0);

        setText(outgoingTripCount, 0);
        setText(outgoingRequiredBusesCount, 0);
        setText(outgoingReadyCount, 0);
        setText(outgoingReviewCount, 0);

        if (footerMessage) {
            footerMessage.textContent =
                'Unable to generate schedule.';
        }
    }


    function updateReadyBadgeOnly() {
        if (readyBadge) {
            readyBadge.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>${recommendations.length} Ready Slots</span>
            `;
        }

        if (footerMessage) {
            footerMessage.textContent = `${recommendations.length} valid recommendation(s), `
                + `${deferredSlots.length} deferred slot(s), and ${schedulingConflicts.length} active conflict(s).`;
        }

        updateConfirmButton();
    }


    function updateConfirmButton() {
        if (confirmButton) {
            confirmButton.disabled =
                (recommendations.length === 0 && deferredSlots.length === 0) || schedulingConflicts.length > 0;
            confirmButton.title = schedulingConflicts.length
                ? 'Resolve all ' + schedulingConflicts.length + ' scheduling conflict(s) before confirming'
                : '';
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


/* =========================================================
   CONSOLIDATED: resources/js/Operation/Scheduling_And_Dispatch/auto-conflict-redesign.js
========================================================= */
(() => {
    const canAssignReserve =
        document.querySelector('.auto-scheduling-page')
            ?.dataset.reserveHead === 'true';
    const canEditOperation =
        document.querySelector('.auto-scheduling-page')
            ?.dataset.operationEdit === 'true';

    const generatePath = '/operation/auto-scheduling/generate';
    const resolvePath = '/operation/auto-scheduling/resolve';
    const assignmentPath = '/operation/driver-bus-assignment';

    let conflicts = [];
    const selections = new Map();
    let redesignQueued = false;

    const conflictIdentity = (conflict) => [
        Number(conflict?.trip_schedule_id || 0),
        Number(conflict?.slot ?? conflict?.slot_number ?? 1),
    ].join(':');

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const normalizeTime = (value) => {
        const parts = String(value || '').trim().split(':');

        if (parts.length < 2) {
            return String(value || '');
        }

        return [
            parts[0].padStart(2, '0'),
            parts[1].padStart(2, '0'),
            (parts[2] || '00').padStart(2, '0'),
        ].join(':');
    };

    const displayTime = (value) => {
        const normalized = normalizeTime(value);
        const [hourText, minute = '00'] = normalized.split(':');
        let hour = Number(hourText);

        if (!Number.isFinite(hour)) {
            return String(value || '—');
        }

        const period = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;

        return `${hour}:${minute} ${period}`;
    };

    const getTimeAction = (conflict) => {
        const actions = conflict?.ai?.conflict?.recommended_actions;

        if (!Array.isArray(actions)) {
            return null;
        }

        return actions.find((action) => (
            action?.type === 'adjust_departure_time'
            && action?.suggested_time
        )) || null;
    };

    const getAlternatives = (conflict, type) => {
        const values = conflict?.ai?.[type];
        return Array.isArray(values) ? values : [];
    };

    const getSelection = (index) => {
        const conflict = conflicts[index];
        const drivers = conflict?.missing_driver ? [] : getAlternatives(conflict, 'alternative_drivers');
        const buses = getAlternatives(conflict, 'alternative_buses');
        const existing = selections.get(index) || {};

        const selection = {
            driverId: existing.driverId ?? drivers[0]?.id ?? null,
            busId: existing.busId ?? buses[0]?.id ?? null,
        };

        selections.set(index, selection);
        return selection;
    };

    const findAlternative = (items, id) => items.find(
        (item) => Number(item.id) === Number(id)
    ) || null;

    const getConflictSeverity = (conflict) => {
        const findings = Array.isArray(conflict?.ai?.conflict?.findings)
            ? conflict.ai.conflict.findings
            : [];
        const issueText = [
            conflict?.reason,
            conflict?.ai?.conflict?.title,
            conflict?.ai?.conflict?.explanation,
        ].join(' ').toLowerCase();

        if (
            conflict?.missing_driver
            || conflict?.missing_bus
            || issueText.includes('maintenance')
        ) {
            return { key: 'high', label: 'High' };
        }

        if (
            findings.length
            || issueText.includes('overlap')
            || issueText.includes('conflict')
        ) {
            return { key: 'medium', label: 'Medium' };
        }

        return { key: 'low', label: 'Low' };
    };

    const formatOrdinal = (value) => {
        const number = Math.max(1, Number(value || 1));
        const remainder = number % 100;
        const suffix = remainder >= 11 && remainder <= 13
            ? 'th'
            : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] || 'th');

        return `${number}${suffix}`;
    };

    const renderFindings = (findings) => {
        if (!findings.length) {
            return `
                <div class="gct-finding-item">
                    <span class="gct-finding-count">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <span>The system detected a resource conflict that requires review.</span>
                </div>
            `;
        }

        return findings.map((finding) => `
            <div class="gct-finding-item">
                <span class="gct-finding-count">${Number(finding?.count || 0)}</span>
                <span>${escapeHtml(
                    finding?.explanation
                    || finding?.category
                    || 'Scheduling issue detected.'
                )}</span>
            </div>
        `).join('');
    };

    const scoringSourceLabel = (source) => ({
        ml: 'Trained ML model',
        data: 'Data-derived fallback',
        data_fallback: 'Data-derived fallback',
        rule: 'Rule-based fallback',
        rule_fallback: 'Rule-based fallback'
    }[source] || 'Source unavailable');

    const renderOptionCards = (items, type, selectedId, index) => {
        if (!items.length) {
            return `
                <div class="gct-finding-item">
                    <span class="gct-finding-count">
                        <i class="fa-solid fa-minus"></i>
                    </span>
                    <span>No alternative ${type === 'driver' ? 'drivers' : 'buses'} are currently available.</span>
                </div>
            `;
        }

        return items.slice(0, 6).map((item) => {
            const selected = Number(item.id) === Number(selectedId);

            return `
                <button
                    type="button"
                    class="gct-option-card ${selected ? 'is-selected' : ''}"
                    data-gct-conflict-index="${index}"
                    data-gct-option-type="${type}"
                    data-gct-option-id="${Number(item.id)}"
                    aria-pressed="${selected ? 'true' : 'false'}"
                >
                    <span class="gct-option-radio" aria-hidden="true"></span>
                    <strong>${escapeHtml(item.label || `${type} ${item.id}`)}</strong>
                    ${item.score !== null && item.score !== undefined
                        ? `<span class="gct-option-score">Suitability ${Number(item.score)}/100</span>`
                        : ''}
                    <p>${escapeHtml(item.reason || 'Available for final validation.')}</p>
                    <small class="gct-scoring-source">${escapeHtml(scoringSourceLabel(conflicts[index]?.ai?.ml?.[type === 'driver' ? 'driver_source' : 'bus_source']))}</small>
                </button>
            `;
        }).join('');
    };

    const renderConflictLegacy = (conflict, index) => {
        const ai = conflict?.ai || {};
        const aiConflict = ai?.conflict || {};
        const isResourceShortage = conflict?.issue_type === 'resource_unavailable';
        const issueDescription = isResourceShortage
            ? 'This trip needs an eligible driver or bus before an assignment can be created.'
            : 'The system could not create a conflict-free assignment.';
        const findings = Array.isArray(aiConflict.findings)
            ? aiConflict.findings
            : [];
        const drivers = getAlternatives(conflict, 'alternative_drivers');
        const buses = getAlternatives(conflict, 'alternative_buses');
        const missingDriver = conflict?.missing_driver === true;
        const missingBus = conflict?.missing_bus === true;
        const action = getTimeAction(conflict);
        const selection = getSelection(index);
        const selectedDriver = findAlternative(drivers, selection.driverId);
        const selectedBus = findAlternative(buses, selection.busId);
        const score = Number.isFinite(Number(ai.score)) ? Number(ai.score) : 0;
        const confidence = score >= 80
            ? 'High confidence'
            : score >= 55
                ? 'Review advised'
                : 'Needs review';
        const actionTitle = missingDriver
            ? 'Driver attendance required'
            : missingBus
                ? 'Eligible bus required'
                : action?.suggested_time
                    ? `Move departure to ${displayTime(action.suggested_time)}`
                    : 'Manual review required';
        const actionDescription = String(
            missingDriver
                ? 'Record valid attendance for the trip date and shift before generating an assignment. Available buses alone cannot resolve this trip.'
                : missingBus
                    ? 'An eligible bus is required before this trip can be assigned.'
                    : action?.explanation
            || 'Review the available resources and validate the final combination before saving.'
        )
            .replaceAll('Laravel must recheck', 'The system will recheck')
            .replaceAll('Laravel will check', 'The system will verify');

        return `
            <article class="gct-conflict-record" data-gct-conflict-record="${index}">
                <div class="gct-conflict-summary">
                    <div class="gct-conflict-summary-item">
                        <span class="gct-conflict-summary-icon"><i class="fa-solid fa-briefcase"></i></span>
                        <div class="gct-conflict-summary-copy">
                            <span>Trip</span>
                            <strong>${escapeHtml(conflict.trip_code)}</strong>
                        </div>
                    </div>

                    <div class="gct-conflict-summary-item">
                        <span class="gct-conflict-summary-icon"><i class="fa-solid fa-route"></i></span>
                        <div class="gct-conflict-summary-copy">
                            <span>Route</span>
                            <strong>${escapeHtml(`${conflict.route_code} — ${conflict.route_name}`)}</strong>
                        </div>
                    </div>

                    <div class="gct-conflict-summary-item">
                        <span class="gct-conflict-summary-icon"><i class="fa-regular fa-clock"></i></span>
                        <div class="gct-conflict-summary-copy">
                            <span>Departure</span>
                            <strong>${escapeHtml(
                                conflict.departure_display
                                || displayTime(conflict.departure_time)
                            )}</strong>
                        </div>
                    </div>

                    <div class="gct-conflict-summary-item issue">
                        <span class="gct-conflict-summary-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <div class="gct-conflict-summary-copy">
                            <span>Issue</span>
                            <strong>${escapeHtml(
                                aiConflict.title
                                || conflict.reason
                                || 'Unable to assign resources.'
                            )}</strong>
                            <small>${escapeHtml(issueDescription)}</small>
                        </div>
                    </div>
                </div>

                <div class="gct-ai-panel">
                    <div class="gct-ai-heading">
                        <div class="gct-ai-title-wrap">
                            <span class="gct-ai-icon"><i class="fa-solid fa-microchip"></i></span>
                            <div>
                                <span class="section-eyebrow warning">${isResourceShortage ? 'Availability Analysis' : 'AI Conflict Analysis'}</span>
                                <h3>${escapeHtml(aiConflict.title || (isResourceShortage ? 'Eligible resources unavailable' : 'Scheduling conflict detected'))}</h3>
                                <p>${escapeHtml(
                                    aiConflict.explanation
                                    || ai.conflict_explanation
                                    || conflict.reason
                                    || 'The selected trip needs additional resource review.'
                                )}</p>
                            </div>
                        </div>
                        <span class="gct-ai-score"><i class="fa-solid fa-chart-line"></i> ${missingDriver || missingBus || ai.status === 'conflict' ? 'No viable assignment' : `Suitability ${score}/100`}</span>
                    </div>

                    <div class="gct-ai-layout">
                        <div class="gct-ai-column">
                            <section class="gct-panel-block">
                                <h4 class="gct-panel-heading">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    What the AI found
                                </h4>
                                <div class="gct-finding-list">${renderFindings(findings)}</div>
                            </section>

                            ${drivers.length && !missingDriver ? `
                                <section class="gct-panel-block">
                                    <div class="gct-option-section-header">
                                        <strong><i class="fa-solid fa-user-tie"></i> Alternative Drivers</strong>
                                        <span>Select a preferred driver</span>
                                    </div>
                                    <div class="gct-option-grid">
                                        ${renderOptionCards(drivers, 'driver', selection.driverId, index)}
                                    </div>
                                </section>
                            ` : ''}

                            <section class="gct-panel-block">
                                <h4 class="gct-panel-heading">
                                    <i class="fa-regular fa-circle-question"></i>
                                    Context
                                </h4>
                                <div class="gct-ai-context">
                                    <p>The AI reviewed current attendance, workloads, trip overlaps, bus status, and PMS information. Every selected option is checked again by the system before saving.</p>
                                    <i class="fa-solid fa-brain"></i>
                                </div>
                            </section>
                        </div>

                        <div class="gct-ai-column">
                            <section class="gct-panel-block">
                                <h4 class="gct-panel-heading">
                                    <i class="fa-regular fa-star"></i>
                                    ${missingDriver || missingBus ? 'Required Before Scheduling' : 'Best Recommended Action'}
                                </h4>
                                <div class="gct-best-action">
                                    <div class="gct-best-action-top">
                                        <div>
                                            <h4>${escapeHtml(actionTitle)}</h4>
                                            <p>${escapeHtml(actionDescription)}</p>
                                        </div>
                                        <div class="gct-confidence" ${missingDriver || missingBus ? 'hidden' : ''}>
                                            <span>Score</span>
                                            <strong>${score}</strong>
                                            <small>${escapeHtml(confidence)}</small>
                                        </div>
                                    </div>

                                    <div class="gct-validation-list">
                                        <span class="gct-validation-item"><i class="fa-regular fa-circle-check"></i> Driver attendance and availability will be verified</span>
                                        <span class="gct-validation-item"><i class="fa-regular fa-circle-check"></i> Driver and bus overlaps will be checked</span>
                                        <span class="gct-validation-item"><i class="fa-regular fa-circle-check"></i> Bus operational and PMS status will be validated</span>
                                        <span class="gct-validation-item"><i class="fa-regular fa-circle-check"></i> Original trip duration will be preserved</span>
                                    </div>
                                </div>

                                ${buses.length && !missingDriver ? `
                                    <div class="gct-option-section">
                                        <div class="gct-option-section-header">
                                            <strong><i class="fa-solid fa-bus"></i> Alternative Buses</strong>
                                            <span>Select a preferred bus</span>
                                        </div>
                                        <div class="gct-option-grid">
                                            ${renderOptionCards(buses, 'bus', selection.busId, index)}
                                        </div>
                                    </div>
                                ` : ''}

                                ${missingDriver ? '<p class="gct-availability-note">Bus availability is informational until a valid driver attendance record exists.</p>' : ''}
                                <div class="gct-selected-combination" ${missingDriver || missingBus ? 'hidden' : ''}>
                                    <span class="gct-selected-chip">
                                        <i class="fa-solid fa-user-check"></i>
                                        ${escapeHtml(missingDriver ? 'No eligible driver' : (selectedDriver?.label || 'Best available driver'))}
                                    </span>
                                    <span class="gct-selected-chip">
                                        <i class="fa-solid fa-bus-simple"></i>
                                        ${escapeHtml(missingDriver ? 'Bus selection unavailable' : (selectedBus?.label || 'Best available bus'))}
                                    </span>
                                    <span class="gct-selected-chip">
                                        <i class="fa-regular fa-clock"></i>
                                        ${escapeHtml(action?.suggested_time ? displayTime(action.suggested_time) : 'Manual timing')}
                                    </span>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <footer class="gct-conflict-actions">
                    ${canEditOperation ? `
                        <button type="button" class="ai-resolution-btn manual" data-gct-defer-slot="manual_later" data-gct-conflict-index="${index}">
                            <i class="fa-solid fa-user-gear"></i>
                            Handle Manually Later
                        </button>
                        <button type="button" class="ai-resolution-btn manual" data-gct-defer-slot="external_bus" data-gct-conflict-index="${index}">
                            <i class="fa-solid fa-building"></i>
                            External Bus Pending
                        </button>
                        <button
                            type="button"
                            class="ai-resolution-btn primary"
                            data-review-ai-resolution="${index}"
                            ${action && !missingDriver && !missingBus && drivers.length && buses.length ? '' : 'disabled'}
                        >
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            Review Selected Resolution
                        </button>
                    ` : `
                        <span class="ai-resolution-btn manual" aria-disabled="true">
                            <i class="fa-solid fa-eye"></i>
                            View-only access
                        </span>
                    `}
                </footer>
            </article>
        `;
    };

    const recommendationTabs = new Map();
    const selectedReserveByConflict = new Map();
    const inlineSelectionsByConflict = new Map();

    document.addEventListener('gct:auto-reserve-selections-reset', () => {
        selectedReserveByConflict.clear();
        inlineSelectionsByConflict.clear();
    });

    const renderReportOptionCards = (items, type, selectedId, index) => items
        .map((item) => {
            const selected = Number(item.id) === Number(selectedId);

            return `
                <button
                    type="button"
                    class="gct-option-card gct-report-option ${selected ? 'is-selected' : ''}"
                    data-gct-conflict-index="${index}"
                    data-gct-option-type="${type}"
                    data-gct-option-id="${Number(item.id)}"
                    aria-pressed="${selected ? 'true' : 'false'}"
                >
                    <span class="gct-option-radio" aria-hidden="true"></span>
                    <span class="gct-report-option-copy">
                        <strong>${escapeHtml(item.label || `${type} ${item.id}`)}</strong>
                        <small>${escapeHtml(item.reason || 'Available for final server validation.')}</small>
                    </span>
                    <span class="gct-option-score">
                        ${item.score !== null && item.score !== undefined && Number.isFinite(Number(item.score))
                            ? `Suitability ${Number(item.score)}/100`
                            : 'Suitability unavailable'}
                        · ${escapeHtml(scoringSourceLabel(item.score_source || conflicts[index]?.ai?.ml?.[type === 'driver' ? 'driver_source' : 'bus_source']))}
                    </span>
                </button>
            `;
        })
        .join('');

    const renderRecommendationGroup = (
        items,
        type,
        selectedId,
        index,
        alternativesOnly = false
    ) => {
        const visibleItems = alternativesOnly
            ? items.slice(1, 6)
            : items.slice(0, 1);

        if (!visibleItems.length) {
            return `
                <div class="gct-report-empty">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>No ${alternativesOnly ? 'additional ' : ''}${type === 'driver' ? 'driver' : 'bus'} option is currently available.</span>
                </div>
            `;
        }

        return `
            <div class="gct-report-options" data-resource-type="${type}">
                ${renderReportOptionCards(visibleItems, type, selectedId, index)}
            </div>
        `;
    };

    const renderConflict = (conflict, index) => {
        const ai = conflict?.ai || {};
        const aiConflict = ai?.conflict || {};
        const missingDriver = conflict?.missing_driver === true;
        const missingBus = conflict?.missing_bus === true;
        const drivers = getAlternatives(conflict, 'alternative_drivers');
        const allBuses = getAlternatives(conflict, 'alternative_buses');
        const reserveInformation = allBuses.filter(item => String(item?.label || '').includes('(Reserve)'));
        const buses = allBuses.filter(item => !String(item?.label || '').includes('(Reserve)'));
        const validPairs = Array.isArray(ai.valid_pairs) ? ai.valid_pairs : [];
        const conflictKey = conflictIdentity(conflict);
        const storedReserve = selectedReserveByConflict.get(conflictKey);
        const selectedReserve = reserveInformation.find((bus) => (
            (Number(storedReserve?.id) > 0 && Number(bus.id) === Number(storedReserve.id))
            || String(bus.label || '') === String(storedReserve?.label || '')
        )) || null;
        const action = getTimeAction(conflict);
        const selection = getSelection(index);
        const inlineChoice = inlineSelectionsByConflict.get(conflictKey) || {};
        const selectedDriverId = Number(inlineChoice.driverId || 0);
        const selectedBusId = Number(selectedReserve?.id || inlineChoice.busId || 0);
        const selectedBusIsReserve = Boolean(selectedReserve);
        const pairAllowed = validPairs.some((pair) => Number(pair.driver_attendance_id) === selectedDriverId && Number(pair.bus_id) === selectedBusId);
        const inlineCanApply = Boolean(canEditOperation && pairAllowed && (!selectedBusIsReserve || canAssignReserve));
        const severity = getConflictSeverity(conflict);
        const hardConflict = missingDriver || missingBus || ai.status === 'conflict';
        const ml = ai.ml || {};
        const sourceLabel = (source) => ({
            ml: 'ML model', data: 'Data-derived fallback', data_fallback: 'Data-derived fallback',
            rule: 'Rule-based fallback', rule_fallback: 'Rule-based fallback'
        }[source] || 'Source unavailable');
        const score = Number(ai.score);
        const suitabilityText = hardConflict
            ? 'No viable assignment'
            : Number.isFinite(score) && ai.score !== null && ai.score !== undefined
                ? `Assignment suitability: ${score}/100`
                : 'Assignment suitability unavailable';
        const activeTab = recommendationTabs.get(index) || 'recommended';
        const issueTitle = aiConflict.title
            || conflict.reason
            || 'Scheduling conflict detected';
        // Previously saved drafts may contain explanations from the retired Shift policy.
        const explanation = String(aiConflict.explanation
            || ai.conflict_explanation
            || conflict.reason
            || 'The trip could not be assigned using the resources available for its departure window.')
            .replace(/attendance,?\s+shift,?\s+and\s+availability/gi, 'attendance and availability')
            .replace(/attendance\s+and\s+shift/gi, 'attendance and availability');
        const requiredBuses = Math.max(1, Number(conflict.required_buses || 1));
        const assignedBuses = Math.min(requiredBuses, Math.max(0, Number(conflict.already_assigned_count || 0)));
        const assignmentProgress = `${assignedBuses}/${requiredBuses}`;
        const requiredLabel = `${assignmentProgress} assigned`;
        const reportTripCode = conflict.base_trip_code || String(conflict.trip_code || 'Trip').replace(/\s*\(Bus \d+\/\d+\)$/, '');
        const stripSlotSuffix = (text) => String(text || '').replace(/\s*\(Bus \d+\/\d+\)/g, '');
        const canApply = Boolean(
            action
            && !missingDriver
            && !missingBus
            && drivers.length
            && buses.length
        );
        const issueDetail = missingDriver && missingBus
            ? 'An eligible driver and bus are required before this slot can be assigned.'
            : missingDriver
                ? 'A driver with valid attendance and no overlapping trip is required.'
                : missingBus
                    ? 'A bus that is active, clear of maintenance, and free during this trip window is required.'
                    : 'The selected resources or departure time conflict with another operational requirement.';
        const isReserveReview = conflict.issue_type === 'reserve_approval_required';
        const reserveSelected = selections.get(index)?.busId === Number(conflict.suggested_bus_id);
        const direction = conflict.direction || 'Incoming';

        return `
            <article class="gct-conflict-record gct-report" data-gct-conflict-record="${index}">
                <header class="gct-report-trip">
                    <span class="gct-report-direction ${direction.toLowerCase()}">
                        <i class="fa-solid fa-arrow-${direction === 'Outgoing' ? 'up' : 'down'}"></i>
                    </span>
                    <div>
                        <strong>${escapeHtml(reportTripCode)} (${escapeHtml(assignmentProgress)})</strong>
                        <span>${escapeHtml(`${conflict.route_code || '-'} - ${conflict.route_name || 'Unknown route'}`)}</span>
                    </div>
                    <span class="gct-conflict-severity ${severity.key}">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        ${severity.label} Severity
                    </span>
                </header>

                <div class="gct-report-meta">
                    <div>
                        <i class="fa-regular fa-calendar"></i>
                        <span>Departure<strong>${escapeHtml(conflict.departure_display || displayTime(conflict.departure_time))}</strong></span>
                    </div>
                    <div>
                        <i class="fa-solid fa-bus"></i>
                        <span>Required buses<strong>${escapeHtml(requiredLabel)}</strong></span>
                    </div>
                    <div>
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Terminal<strong>${escapeHtml(conflict.terminal || conflict.shift || 'Not specified')}</strong></span>
                    </div>
                </div>

                <section class="gct-report-alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <span>Issue Summary</span>
                        <strong>${escapeHtml(stripSlotSuffix(issueTitle))}</strong>
                        <p>${escapeHtml(issueDetail)}</p>
                    </div>
                </section>

                <section class="gct-report-explanation">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Detailed Explanation</strong>
                        <p>${escapeHtml(stripSlotSuffix(explanation))}</p>
                        <p><strong>${escapeHtml(suitabilityText)}</strong></p>
                        <p>Bus scoring: ${escapeHtml(sourceLabel(ml.bus_source))} · Driver scoring: ${escapeHtml(sourceLabel(ml.driver_source))}</p>
                        ${Array.isArray(aiConflict.findings) && aiConflict.findings.length ? `
                            <div class="gct-report-findings">
                                <strong>Findings</strong>
                                <ul>${aiConflict.findings.map(finding => `<li>${escapeHtml(typeof finding === 'string' ? finding : finding?.message || finding?.description || finding?.title || 'Scheduling restriction identified')}</li>`).join('')}</ul>
                            </div>
                        ` : ''}
                    </div>
                </section>

                <div class="gct-report-tabs" role="tablist" aria-label="Conflict recommendation options">
                    <button
                        type="button"
                        role="tab"
                        data-gct-option-tab="recommended"
                        data-gct-conflict-index="${index}"
                        class="${activeTab === 'recommended' ? 'active' : ''}"
                    >
                        Recommended Assignments (${Math.max(drivers.length ? 1 : 0, buses.length ? 1 : 0)})
                    </button>
                    <button
                        type="button"
                        role="tab"
                        data-gct-option-tab="alternative"
                        data-gct-conflict-index="${index}"
                        class="${activeTab === 'alternative' ? 'active' : ''}"
                    >Alternative Options (${drivers.length + buses.length + reserveInformation.length + validPairs.length + (isReserveReview ? 1 : 0)})</button>
                </div>

                <div class="gct-report-recommendations">
                    ${activeTab === 'alternative' ? `
                        <section class="gct-alternative-section" data-gct-alternative-section="drivers">
                            <header><strong><i class="fa-solid fa-id-card"></i> Driver Alternatives</strong><span>${drivers.length} eligible</span></header>
                            ${drivers.length
                                ? `<div class="gct-report-options" data-resource-type="driver">${renderReportOptionCards(drivers.slice(0, 5), 'driver', selectedDriverId, index)}</div>`
                                : '<div class="gct-report-empty">No Laravel-eligible driver alternative is available.</div>'}
                        </section>
                        <section class="gct-alternative-section" data-gct-alternative-section="buses">
                            <header><strong><i class="fa-solid fa-bus"></i> Bus Alternatives</strong><span>${buses.length + reserveInformation.length + (isReserveReview ? 1 : 0)} eligible</span></header>
                            ${isReserveReview ? `
                                <button type="button" class="gct-option-card gct-report-option ${reserveSelected ? 'is-selected' : ''}"
                                    data-gct-select-reserve="${index}" aria-pressed="${reserveSelected ? 'true' : 'false'}"
                                    style="width:100%;text-align:left;display:flex;gap:12px;align-items:center;padding:12px;">
                                    <span class="gct-option-radio" aria-hidden="true"></span>
                                    <span class="gct-report-option-copy"><strong>Reserve Bus ${escapeHtml(conflict.suggested_bus_no || '')}</strong><small>Laravel eligibility passed · Operations Head direct assignment · Revalidated before confirmation</small></span>
                                </button>
                            ` : ''}
                            ${buses.length ? `<div class="gct-report-options" data-resource-type="bus">${renderReportOptionCards(buses.slice(0, 5), 'bus', selectedBusId, index)}</div>` : ''}
                            ${reserveInformation.length ? `
                                <div class="gct-report-reserve-alternatives">
                                    ${reserveInformation.map(bus => `
                                        <button type="button" class="gct-option-card gct-report-option gct-reserve-choice ${selectedReserve === bus ? 'is-selected' : ''}" data-gct-review-reserve="${index}" data-gct-reserve-id="${Number(bus.id)}" data-gct-reserve-label="${escapeHtml(bus.label)}" aria-pressed="${selectedReserve === bus ? 'true' : 'false'}" aria-disabled="${canAssignReserve ? 'false' : 'true'}">
                                            <span class="gct-reserve-choice-identity"><i class="fa-solid fa-bus" aria-hidden="true"></i><strong>${escapeHtml(bus.label)}</strong><small>${bus.score !== null && bus.score !== undefined ? `Suitability ${Number(bus.score)}/100 · ` : ''}${escapeHtml(scoringSourceLabel(bus.score_source || ml.bus_source))}</small></span>
                                            <span class="gct-reserve-choice-indicator" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                        </button>
                                    `).join('')}
                                </div>
                            ` : (!isReserveReview && !buses.length ? '<div class="gct-report-empty">No Laravel-eligible bus alternative is available.</div>' : '')}
                        </section>
                        <section class="gct-alternative-section" data-gct-alternative-section="pairs">
                            <header><strong><i class="fa-solid fa-link"></i> Valid Driver&ndash;Bus Pairs</strong><span>${validPairs.length} valid</span></header>
                            ${validPairs.length ? `<div class="gct-valid-pair-list">${validPairs.map(pair => `
                                <button type="button" class="gct-valid-pair" data-gct-inline-pair="${index}" data-gct-inline-driver="${Number(pair.driver_attendance_id)}" data-gct-inline-bus="${Number(pair.bus_id)}" aria-pressed="${selectedDriverId === Number(pair.driver_attendance_id) && selectedBusId === Number(pair.bus_id) ? 'true' : 'false'}">
                                    <strong>${escapeHtml(pair.driver_label)} <i class="fa-solid fa-arrow-right"></i> ${escapeHtml(pair.bus_label)}</strong>
                                    <small>Driver: ${pair.driver_score !== null && pair.driver_score !== undefined ? `${Number(pair.driver_score)}/100 · ` : ''}${escapeHtml(scoringSourceLabel(pair.driver_score_source))}</small>
                                    <small>Bus: ${pair.bus_score !== null && pair.bus_score !== undefined ? `${Number(pair.bus_score)}/100 · ` : ''}${escapeHtml(scoringSourceLabel(pair.bus_score_source))}</small>
                                    <span>${escapeHtml(pair.reason || 'Laravel eligibility passed; final server validation still applies.')}</span>
                                </button>
                            `).join('')}</div>` : '<div class="gct-report-empty">No complete driver&ndash;bus pair currently passes deterministic eligibility.</div>'}
                        </section>
                    ` : ''}
                    ${isReserveReview && activeTab === 'recommended' ? '<div class="gct-report-empty">Open Alternative Options to select the proposed Reserve Bus.</div>' : ''}
                    ${activeTab === 'recommended' && !isReserveReview ? (missingDriver || missingBus
                        ? '<div class="gct-report-empty">No complete assignment is currently recommended. Review the separated alternatives and valid pairs.</div>'
                        : `${renderRecommendationGroup(drivers, 'driver', selection.driverId, index)}${renderRecommendationGroup(buses, 'bus', selection.busId, index)}`) : ''}
                    ${activeTab === 'recommended' && action?.suggested_time ? `
                        <div class="gct-report-time-option">
                            <i class="fa-regular fa-clock"></i>
                            <span>Proposed departure<strong>${escapeHtml(displayTime(action.suggested_time))}</strong><small>${escapeHtml(action.explanation || 'This time is a suggestion, not a validated solution. Driver, bus, maintenance and overlap rules must be rechecked.')}</small></span>
                        </div>
                    ` : ''}
                </div>

                <footer class="gct-conflict-actions gct-report-actions">
                    ${canEditOperation ? `
                        <button type="button" class="ai-resolution-btn manual" data-gct-defer-slot="manual_later" data-gct-conflict-index="${index}">
                            <i class="fa-solid fa-user-gear"></i>
                            Handle Manually Later
                        </button>
                        <button type="button" class="ai-resolution-btn manual" data-gct-defer-slot="external_bus" data-gct-conflict-index="${index}">
                            <i class="fa-solid fa-building"></i>
                            External Bus Pending
                        </button>
                        ${activeTab === 'alternative' ? `
                            <button type="button" class="ai-resolution-btn primary" data-gct-apply-inline="${escapeHtml(conflictKey)}" data-gct-apply-inline-index="${index}" ${inlineCanApply ? '' : 'disabled'}>
                                <i class="fa-solid fa-check-circle"></i> Apply to Preview
                            </button>
                        ` : `
                            <button type="button" class="ai-resolution-btn primary" data-review-ai-resolution="${index}" ${canApply ? '' : 'disabled'}>
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Apply Recommendation
                            </button>
                        `}
                    ` : `
                        <span class="ai-resolution-btn manual" aria-disabled="true">
                            <i class="fa-solid fa-eye"></i>
                            View-only access
                        </span>
                    `}
                </footer>
            </article>
        `;
    };

    let selectedConflictIndex = 0;
    let conflictDirectionFilter = 'all';
    let conflictSearch = '';
    let conflictReportClosed = false;
    let conflictSortKey = null;
    let conflictSortDirection = 'asc';

    const renderConflictSortHeader = (label, key) => {
        const active = conflictSortKey === key;
        const icon = active
            ? (conflictSortDirection === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down')
            : 'fa-sort';

        return `
            <button
                type="button"
                class="gct-conflict-sort ${active ? 'is-active' : ''}"
                data-gct-conflict-sort="${key}"
                aria-label="Sort by ${label}"
            >
                ${label}
                <i class="fa-solid ${icon}" aria-hidden="true"></i>
            </button>
        `;
    };

    const redesignConflicts = () => {
        redesignQueued = false;
        const container = document.getElementById('autoSchedulingConflictContent');
        const section = document.getElementById('autoSchedulingConflictSection');
        if (!container || !section || !conflicts.length || section.hidden) return;
        const signature = JSON.stringify(conflicts.map(c => [c.trip_schedule_id, c.slot, c.reason, c.result]));
        if (container.dataset.gctWorkspaceSignature === signature && container.querySelector('.gct-conflict-workspace')) return;
        container.dataset.gctWorkspaceSignature = signature;

        selectedConflictIndex = Math.max(0, Math.min(selectedConflictIndex, conflicts.length - 1));
        const incomingCount = conflicts.filter(c => (c.direction || 'Incoming') === 'Incoming').length;
        const outgoingCount = conflicts.length - incomingCount;
        const indexed = conflicts.map((item, index) => ({ item, index }));
        let shown = indexed.filter(({ item }) => {
            const direction = item.direction || 'Incoming';
            const text = [item.trip_code, item.route_code, item.route_name, item.reason, item.issue_type]
                .join(' ').toLowerCase();
            return (conflictDirectionFilter === 'all' || direction === conflictDirectionFilter)
                && text.includes(conflictSearch.toLowerCase());
        });

        if (conflictSortKey) {
            shown = [...shown].sort((left, right) => {
                const severityOrder = { low: 1, medium: 2, high: 3 };
                const values = {
                    trip: (entry) => entry.item.base_trip_code || entry.item.trip_code || '',
                    route: (entry) => `${entry.item.route_code || ''} ${entry.item.route_name || ''}`,
                    time: (entry) => normalizeTime(entry.item.departure_time),
                    issue: (entry) => entry.item.reason || '',
                    severity: (entry) => severityOrder[getConflictSeverity(entry.item).key] || 0,
                    status: () => 'Needs Review',
                };
                const getter = values[conflictSortKey] || values.trip;
                const leftValue = getter(left);
                const rightValue = getter(right);
                const comparison = typeof leftValue === 'number'
                    ? leftValue - rightValue
                    : String(leftValue).localeCompare(String(rightValue));

                return conflictSortDirection === 'asc' ? comparison : -comparison;
            });
        }

        const rows = shown.map(({ item, index }) => {
            const direction = item.direction || 'Incoming';
            const issue = String(item.reason || 'Resources unavailable')
                .replace(/\s*\(Bus\s+\d+\/\d+\)\.?\s*$/i, '.');
            const trip = item.base_trip_code || item.trip_code || 'Trip';
            const severity = getConflictSeverity(item);
            const required = Math.max(1, Number(item.required_buses || 1));
            const assigned = Math.max(0, Number(item.already_assigned_count || 0));
            const ready = Math.max(0, Math.min(required, Number(item.ready_buses || 0)));
            const slot = Math.min(required, assigned + Math.max(1, Number(item.slot || 1)));
            const slotLabel = required === 1 ? 'Single bus slot' : `Slot ${slot} of ${required}`;
            return `<tr class="${index === selectedConflictIndex ? 'is-selected' : ''}" data-gct-report-index="${index}" tabindex="0" role="button" aria-selected="${index === selectedConflictIndex}" aria-label="View AI conflict report for ${escapeHtml(trip)}, ${escapeHtml(item.slot_label || '')}">
                <td><span class="gct-conflict-trip-cell">
                    <span class="gct-conflict-dir ${direction === 'Incoming' ? 'in' : 'out'}">${direction === 'Incoming' ? 'IN' : 'OUT'}</span>
                    <span class="gct-conflict-trip-meta"><strong>${escapeHtml(trip)}</strong><span class="gct-conflict-slot-tag"><i class="fa-solid fa-bus" aria-hidden="true"></i> ${escapeHtml(slotLabel)}</span><small>${ready}/${required} buses ready</small></span>
                </span></td>
                <td><strong>${escapeHtml(item.route_code || '—')}</strong><small>${escapeHtml(item.route_name || '')}</small></td>
                <td>${escapeHtml(item.departure_display || displayTime(item.departure_time))}</td>
                <td class="gct-conflict-issue">${escapeHtml(issue)}</td>
                <td><span class="gct-conflict-severity ${severity.key}"><i class="fa-solid ${severity.key === 'low' ? 'fa-circle-info' : 'fa-triangle-exclamation'}"></i> ${severity.label}</span></td>
                <td><span class="gct-conflict-state"><i class="fa-regular fa-clock"></i> Needs Review</span></td>
            </tr>`;
        }).join('');
        container.innerHTML = `
            <div class="gct-conflict-workspace ${conflictReportClosed ? 'is-report-closed' : ''}">
                <div class="gct-conflict-list">
                    <div class="gct-conflict-toolbar">
                        <div class="gct-conflict-tabs">
                            <button type="button" data-gct-direction="all" class="${conflictDirectionFilter === 'all' ? 'active' : ''}">All Conflicts (${conflicts.length})</button>
                            <button type="button" data-gct-direction="Incoming" class="${conflictDirectionFilter === 'Incoming' ? 'active' : ''}">Incoming (${incomingCount})</button>
                            <button type="button" data-gct-direction="Outgoing" class="${conflictDirectionFilter === 'Outgoing' ? 'active' : ''}">Outgoing (${outgoingCount})</button>
                        </div>
                        <input type="search" data-gct-conflict-search placeholder="Search trip, route or issue..." value="${escapeHtml(conflictSearch)}" aria-label="Search scheduling conflicts">
                    </div>
                    <div class="gct-conflict-table-scroll"><table class="gct-conflict-table">
                        <thead><tr>
                            <th>${renderConflictSortHeader('Trip / Slot', 'trip')}</th>
                            <th>${renderConflictSortHeader('Route', 'route')}</th>
                            <th>${renderConflictSortHeader('Time', 'time')}</th>
                            <th>${renderConflictSortHeader('Issue', 'issue')}</th>
                            <th>${renderConflictSortHeader('Severity', 'severity')}</th>
                            <th>${renderConflictSortHeader('Status', 'status')}</th>
                        </tr></thead>
                        <tbody>${rows || '<tr><td colspan="6">No conflicts match your filters.</td></tr>'}</tbody>
                    </table></div>
                    <p class="gct-conflict-count">Showing ${shown.length} of ${conflicts.length} conflicts</p>
                </div>
                ${conflictReportClosed ? '' : `
                    <aside class="gct-conflict-report-panel" aria-label="AI Conflict Report">
                        <div class="gct-conflict-report-header">
                            <strong><span class="gct-report-header-icon" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span> AI Conflict Report</strong>
                            <button type="button" data-gct-close-report aria-label="Close conflict report">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="gct-conflict-report-body">${renderConflict(conflicts[selectedConflictIndex], selectedConflictIndex)}</div>
                    </aside>
                `}
            </div>`;
    };

    document.getElementById('autoSchedulingConflictContent')?.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-gct-direction]');
        if (tab) {
            conflictDirectionFilter = tab.dataset.gctDirection;
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            return;
        }
        const sortButton = event.target.closest('[data-gct-conflict-sort]');
        if (sortButton) {
            const nextKey = sortButton.dataset.gctConflictSort;
            conflictSortDirection = conflictSortKey === nextKey && conflictSortDirection === 'asc'
                ? 'desc'
                : 'asc';
            conflictSortKey = nextKey;
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            return;
        }
        const optionTab = event.target.closest('[data-gct-option-tab]');
        if (optionTab) {
            const index = Number(optionTab.dataset.gctConflictIndex);
            recommendationTabs.set(index, optionTab.dataset.gctOptionTab);
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            return;
        }
        const closeReport = event.target.closest('[data-gct-close-report]');
        if (closeReport) {
            conflictReportClosed = true;
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            return;
        }
        const row = event.target.closest('[data-gct-report-index]');
        if (row) {
            selectedConflictIndex = Number(row.dataset.gctReportIndex);
            conflictReportClosed = false;
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            const reportBody = document.querySelector('#autoSchedulingConflictContent .gct-conflict-report-body');
            if (reportBody) reportBody.scrollTop = 0;
        }
    });
    document.getElementById('autoSchedulingConflictContent')?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const row = event.target.closest('tr[data-gct-report-index]');
        if (!row || event.target !== row) return;
        event.preventDefault();
        row.click();
        document.querySelector('tr[data-gct-report-index="' + row.dataset.gctReportIndex + '"]')?.focus();
    });
    document.getElementById('autoSchedulingConflictContent')?.addEventListener('input', (event) => {
        if (!event.target.matches('[data-gct-conflict-search]')) return;
        conflictSearch = event.target.value;
        document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
        const selectionStart = event.target.selectionStart;
        redesignConflicts();
        const input = document.querySelector('[data-gct-conflict-search]');
        input?.focus();
        if (selectionStart !== null) input?.setSelectionRange(selectionStart, selectionStart);
    });

    const queueRedesign = () => {
        if (redesignQueued) {
            return;
        }

        redesignQueued = true;
        window.requestAnimationFrame(redesignConflicts);
    };

    const openReviewModal = (index) => {
        if (!canEditOperation) {
            window.showSystemToast?.(
                'Your role cannot apply scheduling resolutions.',
                'warning',
                'View-only access'
            );
            return;
        }

        const conflict = conflicts[index];
        if (conflict?.missing_driver || conflict?.missing_bus) {
            window.showSystemToast?.('Eligible driver attendance and a bus are required before a resolution can be reviewed.', 'warning', 'Resources unavailable');
            return;
        }
        const action = getTimeAction(conflict);

        if (!conflict || !action) {
            window.showSystemToast?.(
                'No safe automatic resolution is available for this conflict.',
                'warning',
                'Manual review required'
            );
            return;
        }

        closeModal();

        const drivers = getAlternatives(conflict, 'alternative_drivers');
        const buses = getAlternatives(conflict, 'alternative_buses');
        const selection = getSelection(index);
        const selectedDriver = findAlternative(drivers, selection.driverId);
        const selectedBus = findAlternative(buses, selection.busId);
        const overlay = document.createElement('div');
        overlay.className = 'ai-resolution-modal-overlay';
        overlay.id = 'aiResolutionModal';

        overlay.innerHTML = `
            <div class="ai-resolution-modal" role="dialog" aria-modal="true" aria-labelledby="aiResolutionModalTitle">
                <div class="ai-resolution-modal-header">
                    <div>
                        <span class="section-eyebrow">AI-assisted resolution</span>
                        <h2 id="aiResolutionModalTitle">Review selected solution</h2>
                    </div>
                    <button type="button" class="ai-modal-close" data-gct-close-modal aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="ai-resolution-summary-grid">
                    <div><span>Trip</span><strong>${escapeHtml(conflict.trip_code)}</strong></div>
                    <div><span>Current departure</span><strong>${escapeHtml(
                        conflict.departure_display || displayTime(conflict.departure_time)
                    )}</strong></div>
                    <div class="proposed"><span>Proposed departure</span><strong>${escapeHtml(displayTime(action.suggested_time))}</strong></div>
                </div>

                <div class="gct-resolution-selection">
                    <div class="gct-resolution-selection-card">
                        <span>Preferred driver</span>
                        <strong>${escapeHtml(selectedDriver?.label || 'Best available driver')}</strong>
                    </div>
                    <div class="gct-resolution-selection-card">
                        <span>Preferred bus</span>
                        <strong>${escapeHtml(selectedBus?.label || 'Best available bus')}</strong>
                    </div>
                </div>

                <div class="ai-resolution-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>The system will verify the selected resources, attendance, PMS status, trip duration, and overlapping assignments before saving. Invalid selections will never be forced.</p>
                </div>

                <p class="ai-resolution-description">${escapeHtml(
                    String(action.explanation || '')
                        .replaceAll('Laravel must recheck', 'The system will recheck')
                        .replaceAll('Laravel will check', 'The system will verify')
                )}</p>

                <div class="ai-resolution-error" id="gctResolutionError" hidden></div>

                <div class="ai-resolution-modal-actions">
                    <button type="button" class="ai-resolution-btn manual" data-gct-close-modal>Cancel</button>
                    <button type="button" class="ai-resolution-btn primary" id="gctApplyResolutionButton">
                        <i class="fa-solid fa-circle-check"></i>
                        Apply Resolution
                    </button>
                </div>
            </div>
        `;

        overlay.dataset.pageOwned = 'true';
        document.body.appendChild(overlay);
        document.body.classList.add('ai-modal-open');

        overlay.querySelectorAll('[data-gct-close-modal]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.getElementById('gctApplyResolutionButton')?.addEventListener('click', () => {
            applyResolution(index, action);
        });
    };

    const closeModal = () => {
        document.getElementById('aiResolutionModal')?.remove();
        document.body.classList.remove('ai-modal-open');
    };

    const showResultModal = (conflict, action, data, preferred) => {
        closeModal();
        const resolution = data?.resolution || {};
        const overlay = document.createElement('div');
        overlay.className = 'ai-resolution-modal-overlay';
        overlay.id = 'aiResolutionModal';
        const preferredDriver = preferred.driverLabel || 'Best available driver';
        const preferredBus = preferred.busLabel || 'Best available bus';
        const actualDriver = resolution.driver_name || preferredDriver;
        const actualBus = resolution.bus_no || preferredBus;
        const changedDriver = preferred.driverLabel && actualDriver !== preferred.driverLabel;
        const changedBus = preferred.busLabel && actualBus !== preferred.busLabel;

        overlay.innerHTML = `
            <div class="ai-resolution-modal" role="dialog" aria-modal="true" aria-labelledby="gctResultTitle">
                <div class="ai-resolution-modal-header">
                    <div>
                        <span class="section-eyebrow">Conflict resolved</span>
                        <h2 id="gctResultTitle">Resolution Applied Successfully</h2>
                    </div>
                    <span class="gct-result-status"><i class="fa-solid fa-circle-check"></i> Validated</span>
                </div>

                <div class="ai-resolution-summary-grid">
                    <div><span>Trip</span><strong>${escapeHtml(resolution.trip_code || conflict.trip_code)}</strong></div>
                    <div><span>Previous departure</span><strong>${escapeHtml(
                        conflict.departure_display || displayTime(conflict.departure_time)
                    )}</strong></div>
                    <div class="proposed"><span>New departure</span><strong>${escapeHtml(
                        resolution.departure_display || displayTime(action.suggested_time)
                    )}</strong></div>
                </div>

                <div class="gct-resolution-selection">
                    <div class="gct-resolution-selection-card">
                        <span>Assigned driver</span>
                        <strong>${escapeHtml(actualDriver)}</strong>
                        ${changedDriver ? '<small>The preferred driver was no longer valid, so the system used another eligible driver.</small>' : ''}
                    </div>
                    <div class="gct-resolution-selection-card">
                        <span>Assigned bus</span>
                        <strong>${escapeHtml(actualBus)}</strong>
                        ${changedBus ? '<small>The preferred bus was no longer valid, so the system used another eligible bus.</small>' : ''}
                    </div>
                    <div class="gct-resolution-selection-card">
                        <span>New estimated arrival</span>
                        <strong>${escapeHtml(resolution.arrival_display || 'Updated')}</strong>
                    </div>
                    <div class="gct-resolution-selection-card">
                        <span>Assignment status</span>
                        <strong>Ready</strong>
                    </div>
                </div>

                <div class="gct-result-checks">
                    <span><i class="fa-solid fa-circle-check"></i> Driver attendance and availability verified</span>
                    <span><i class="fa-solid fa-circle-check"></i> Driver and bus overlap checks passed</span>
                    <span><i class="fa-solid fa-circle-check"></i> Bus operational status validated</span>
                    <span><i class="fa-solid fa-circle-check"></i> Trip duration preserved and schedule saved</span>
                </div>

                <div class="ai-resolution-modal-actions">
                    <a href="${data?.redirect_url || assignmentPath}" class="ai-resolution-btn manual">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        View Assignment
                    </a>
                    <button type="button" class="ai-resolution-btn primary" id="gctConfirmChangesButton">
                        <i class="fa-solid fa-circle-check"></i>
                        Confirm These Changes
                    </button>
                </div>
            </div>
        `;

        overlay.dataset.pageOwned = 'true';
        document.body.appendChild(overlay);
        document.body.classList.add('ai-modal-open');

        document.getElementById('gctConfirmChangesButton')?.addEventListener('click', () => {
            closeModal();
            window.showSystemToast?.(
                `${resolution.trip_code || conflict.trip_code} has been updated and confirmed.`,
                'success',
                'Schedule updated',
                { timeout: 5000 }
            );
            document.getElementById('regenerateScheduleButton')?.click();
        });
    };

    const applyResolution = async (index, action) => {
        if (!canEditOperation) {
            window.showSystemToast?.(
                'Your role cannot apply scheduling resolutions.',
                'warning',
                'View-only access'
            );
            return;
        }

        const conflict = conflicts[index];
        const button = document.getElementById('gctApplyResolutionButton');
        const errorBox = document.getElementById('gctResolutionError');
        const selection = getSelection(index);
        const drivers = getAlternatives(conflict, 'alternative_drivers');
        const buses = getAlternatives(conflict, 'alternative_buses');
        const preferredDriver = findAlternative(drivers, selection.driverId);
        const preferredBus = findAlternative(buses, selection.busId);
        const csrfToken = document.querySelector('#autoSchedulingForm input[name="_token"]')?.value || '';

        if (!button) {
            return;
        }

        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Applying...';

        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = '';
        }

        try {
            const response = await fetch(resolvePath, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    trip_schedule_id: conflict.trip_schedule_id,
                    proposed_departure_time: normalizeTime(action.suggested_time),
                    preferred_driver_attendance_id: selection.driverId,
                    preferred_bus_id: selection.busId,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data?.message
                    || Object.values(data?.errors || {}).flat()[0]
                    || 'Unable to apply the selected resolution.';
                throw new Error(message);
            }

            showResultModal(conflict, action, data, {
                driverLabel: preferredDriver?.label || null,
                busLabel: preferredBus?.label || null,
            });
        } catch (error) {
            console.error(error);

            if (errorBox) {
                errorBox.hidden = false;
                errorBox.textContent = error.message || 'Unable to apply the selected resolution.';
            }
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-circle-check"></i> Apply Resolution';
        }
    };

    const installFetchCapture = () => {
        if (window.__gctConflictFetchCaptureInstalled) {
            return;
        }

        window.__gctConflictFetchCaptureInstalled = true;
        const nativeFetch = window.fetch.bind(window);

        window.fetch = async (...args) => {
            const response = await nativeFetch(...args);
            const input = args[0];
            const url = typeof input === 'string' ? input : input?.url || '';

            if (url.includes(generatePath)) {
                response.clone().json().then((data) => {
                    conflicts = Array.isArray(data?.conflicts) ? data.conflicts : [];
                    selections.clear();
                    selectedReserveByConflict.clear();
                    queueRedesign();
                }).catch(() => {});
            }

            return response;
        };
    };

    document.addEventListener('gct:auto-conflicts-updated', (event) => {
        if (!document.querySelector('.auto-scheduling-page')) return;
        conflicts = Array.isArray(event.detail?.conflicts) ? event.detail.conflicts.slice() : [];
        const container = document.getElementById('autoSchedulingConflictContent');
        if (container) delete container.dataset.gctWorkspaceSignature;
        if (!conflicts.length) {
            selections.clear();
            return;
        }
        queueRedesign();
    });

    document.addEventListener('gct:inline-preview-applied', (event) => {
        const index = conflicts.findIndex((item) => conflictIdentity(item) === event.detail?.conflictKey);
        if (index >= 0) conflicts.splice(index, 1);
        inlineSelectionsByConflict.delete(event.detail?.conflictKey);
        selectedReserveByConflict.delete(event.detail?.conflictKey);
        document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
        queueRedesign();
    });

    document.addEventListener('gct:reserve-preview-applied', (event) => {
        const index = Number(event.detail?.index);
        conflicts.splice(index, 1);
        selections.clear();
        const container = document.getElementById('autoSchedulingConflictContent');
        if (container) delete container.dataset.gctWorkspaceSignature;
        queueRedesign();
    });

    installFetchCapture();

    const content = document.getElementById('autoSchedulingConflictContent');

    if (content) {
        const observer = new MutationObserver(() => {
            if (
                conflicts.length
                && !conflictReportClosed
                && !content.querySelector('.gct-conflict-record')
            ) {
                delete content.dataset.gctWorkspaceSignature;
                queueRedesign();
            }
        });

        observer.observe(content, {
            childList: true,
            subtree: false,
        });
    }

    document.addEventListener('click', (event) => {
        const deferSlot = event.target.closest('[data-gct-defer-slot]');
        if (deferSlot) {
            event.preventDefault();
            document.dispatchEvent(new CustomEvent('gct:defer-conflict-slot', {
                detail: {
                    index: Number(deferSlot.dataset.gctConflictIndex),
                    decision: deferSlot.dataset.gctDeferSlot,
                },
            }));
            return;
        }

        const reserveInfo = event.target.closest('[data-gct-review-reserve]');
        if (reserveInfo) {
            event.preventDefault();
            if (!canAssignReserve) {
                window.showSystemToast?.('Only Operations Head may assign a Reserve Bus.', 'warning', 'Reserve assignment restricted');
                return;
            }
            const index = Number(reserveInfo.dataset.gctReviewReserve);
            const conflict = conflicts[index];
            if (!conflict) return;
            const candidate = getAlternatives(conflict, 'alternative_buses').find((bus) => (
                (Number(reserveInfo.dataset.gctReserveId) > 0
                    && Number(bus.id) === Number(reserveInfo.dataset.gctReserveId))
                || String(bus.label || '') === String(reserveInfo.dataset.gctReserveLabel || '')
            ));
            if (!candidate || !String(candidate.label || '').includes('(Reserve)')) {
                window.showSystemToast?.('This Reserve Bus option is stale. Regenerate the schedule.', 'warning', 'Option unavailable');
                return;
            }
            selectedReserveByConflict.set(conflictIdentity(conflict), {
                id: Number(reserveInfo.dataset.gctReserveId),
                label: reserveInfo.dataset.gctReserveLabel,
            });
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            redesignConflicts();
            return;
        }

        const pairButton = event.target.closest('[data-gct-inline-pair]');
        if (pairButton) {
            event.preventDefault();
            const index = Number(pairButton.dataset.gctInlinePair);
            const conflict = conflicts[index];
            if (!conflict) return;
            const key = conflictIdentity(conflict);
            const driverId = Number(pairButton.dataset.gctInlineDriver);
            const busId = Number(pairButton.dataset.gctInlineBus);
            const pair = (conflict.ai?.valid_pairs || []).find((item) =>
                Number(item.driver_attendance_id) === driverId && Number(item.bus_id) === busId
            );
            if (!pair) return;
            inlineSelectionsByConflict.set(key, { driverId, busId });
            const matchingBus = getAlternatives(conflict, 'alternative_buses').find((bus) => Number(bus.id) === busId);
            if (matchingBus && String(matchingBus.label).includes('(Reserve)')) {
                selectedReserveByConflict.set(key, { id: busId, label: matchingBus.label });
            } else {
                selectedReserveByConflict.delete(key);
            }
            document.getElementById('autoSchedulingConflictContent')?.removeAttribute('data-gct-workspace-signature');
            queueRedesign();
            return;
        }

        const inlineApply = event.target.closest('[data-gct-apply-inline-index]');
        if (inlineApply) {
            event.preventDefault();
            const index = Number(inlineApply.dataset.gctApplyInlineIndex);
            const conflict = conflicts[index];
            if (!conflict) return;
            const key = conflictIdentity(conflict);
            const choice = inlineSelectionsByConflict.get(key) || {};
            const reserve = selectedReserveByConflict.get(key);
            const driverId = Number(choice.driverId);
            const busId = Number(reserve?.id || choice.busId);
            const pair = (conflict.ai?.valid_pairs || []).some((item) =>
                Number(item.driver_attendance_id) === driverId && Number(item.bus_id) === busId
            );
            if (!pair) {
                window.showSystemToast?.('Select a valid driver–bus pair first.', 'warning');
                return;
            }
            document.dispatchEvent(new CustomEvent('gct:apply-inline-alternative', {
                detail: { conflictKey: key, tripScheduleId: Number(conflict.trip_schedule_id), driverId, busId, reserve: Boolean(reserve) },
            }));
            return;
        }

        const selectReserve = event.target.closest('[data-gct-select-reserve]');
        if (selectReserve) {
            event.preventDefault();
            const index = Number(selectReserve.dataset.gctSelectReserve);
            const conflict = conflicts[index];
            if (!conflict || conflict.issue_type !== 'reserve_approval_required') return;
            selections.set(index, { ...getSelection(index), busId: Number(conflict.suggested_bus_id) });
            const container = document.getElementById('autoSchedulingConflictContent');
            if (container) delete container.dataset.gctWorkspaceSignature;
            queueRedesign();
            return;
        }
        const applyReserve = event.target.closest('[data-apply-selected-reserve]');
        if (applyReserve && !applyReserve.disabled) {
            event.preventDefault();
            const index = Number(applyReserve.dataset.applySelectedReserve);
            const conflict = conflicts[index];
            if (!conflict || selections.get(index)?.busId !== Number(conflict.suggested_bus_id)) return;
            applyReserve.disabled = true;
            document.dispatchEvent(new CustomEvent('gct:apply-reserve-selection', { detail: { index } }));
            return;
        }

        const option = event.target.closest('[data-gct-option-type]');

        if (option) {
            event.preventDefault();
            const index = Number(option.dataset.gctConflictIndex);
            const type = option.dataset.gctOptionType;
            const id = Number(option.dataset.gctOptionId);
            const selection = getSelection(index);

            if (type === 'driver') {
                selection.driverId = id;
            } else if (type === 'bus') {
                selection.busId = id;
            }

            selections.set(index, selection);
            const key = conflictIdentity(conflicts[index]);
            const saved = inlineSelectionsByConflict.get(key) || {};
            inlineSelectionsByConflict.set(key, type === 'driver'
                ? { ...saved, driverId: id }
                : { ...saved, busId: id });
            if (type === 'bus') selectedReserveByConflict.delete(key);
            const container = document.getElementById('autoSchedulingConflictContent');
            if (container) {
                delete container.dataset.gctWorkspaceSignature;
            }
            queueRedesign();
            return;
        }

        const reviewButton = event.target.closest('[data-review-ai-resolution]');

        if (reviewButton && reviewButton.closest('.gct-conflict-record')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const index = Number(reviewButton.dataset.reviewAiResolution);
            openReviewModal(index);
        }
    }, true);
})();

if (!window.__gctAutoSchedulingRealtimeReinitBound) {
    window.__gctAutoSchedulingRealtimeReinitBound = true;

    window.addEventListener(
        'system-regions-refreshed',
        (event) => {
            if (!document.querySelector('.auto-scheduling-page')) {
                return;
            }

            const regions = Array.isArray(event.detail?.regions)
                ? event.detail.regions
                : [];

            if (!regions.includes('summary')) {
                return;
            }

            const resetHtml = `
                <tr>
                    <td colspan="8">
                        <div class="auto-empty-state">
                            <i class="fa-solid fa-rotate"></i>
                            <strong>Schedule data changed</strong>
                            <span>Generate the schedule again to review current recommendations.</span>
                        </div>
                    </td>
                </tr>
            `;

            const incomingBody = document.getElementById('autoScheduleIncomingBody');
            if (incomingBody) {
                incomingBody.innerHTML = resetHtml;
            }

            const outgoingBody = document.getElementById('autoScheduleOutgoingBody');
            if (outgoingBody) {
                outgoingBody.innerHTML = resetHtml;
            }

            const previewBody =
                document.getElementById(
                    'autoSchedulePreviewBody'
                );

            if (previewBody) {
                previewBody.innerHTML = resetHtml;
            }

            const readyBadge =
                document.getElementById(
                    'generatedReadyCount'
                );

            if (readyBadge) {
                readyBadge.innerHTML =
                    '<i class="fa-solid fa-circle-check"></i><span>0 Ready Slots</span>';
            }

            const footerMessage =
                document.getElementById(
                    'generatedFooterMessage'
                );

            if (footerMessage) {
                footerMessage.textContent =
                    'Schedule data changed. Generate again before confirming assignments.';
            }

            const confirmButton =
                document.getElementById(
                    'confirmScheduleButton'
                );

            if (confirmButton) {
                confirmButton.disabled = true;
            }

            const conflictSection =
                document.getElementById(
                    'autoSchedulingConflictSection'
                );

            if (conflictSection) {
                conflictSection.hidden = true;
            }

            window.dispatchEvent(
                new CustomEvent(
                    'gct:navigation-ready',
                    {
                        detail: {
                            source: 'realtime-regions',
                        },
                    }
                )
            );
        }
    );
}
