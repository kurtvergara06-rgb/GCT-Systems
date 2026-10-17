<x-layout.app
    title="FROMS - Auto Scheduling"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/Scheduling_And_Dispatch/auto-dispatch.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Scheduling_And_Dispatch/auto-scheduling.js',
    ]"
>
@php
    $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
    $canApproveOperation = auth()->user()?->hasSystemPermission('operation', 'approve') ?? false;
    $canAssignReserve = auth()->user()?->canAssignReserveBus() ?? false;
@endphp

<div class="app">
  <x-layout.sidebar department="Operation" />

    <main
        class="main auto-scheduling-page"
        data-operation-edit="{{ $canEditOperation ? 'true' : 'false' }}"
        data-operation-approve="{{ $canApproveOperation ? 'true' : 'false' }}"
        data-reserve-head="{{ $canAssignReserve ? 'true' : 'false' }}"
    >
        <x-layout.topbar
            title="Auto Scheduling"
            subtitle="Automatically assign available drivers and shuttle buses to scheduled trips"
            notification-count="4"
        />

        <section data-ajax-region="summary" class="auto-summary-grid">
            <article class="auto-summary-card">
                <div class="summary-icon blue"><i class="fa-solid fa-calendar-days"></i></div>
                <div>
                    <p>Trips to Schedule</p>
                    <h2 id="summaryTrips">{{ $tripsToSchedule }}</h2>
                    <small>For selected date</small>
                </div>
            </article>

            <article class="auto-summary-card">
                <div class="summary-icon green"><i class="fa-solid fa-user-check"></i></div>
                <div>
                    <p>Available Drivers</p>
                    <h2 id="summaryDrivers">{{ $availableDrivers }}</h2>
                    <small>Present</small>
                </div>
            </article>

            <article class="auto-summary-card">
                <div class="summary-icon purple"><i class="fa-solid fa-bus"></i></div>
                <div>
                    <p>Available Buses</p>
                    <h2 id="summaryBuses">{{ $availableBuses }}</h2>
                    <small>Active buses</small>
                </div>
            </article>

            <article class="auto-summary-card">
                <div class="summary-icon yellow"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <p>Potential Conflicts</p>
                    <h2 id="summaryConflicts">{{ $potentialConflicts }}</h2>
                    <small>Needs review</small>
                </div>
            </article>
        </section>

        <section class="auto-main-grid">
            <article class="auto-card schedule-configuration-card">
                <div class="auto-card-header">
                    <div>
                        <span class="section-eyebrow">Scheduling Setup</span>
                        <h2>Generate Dispatch Schedule</h2>
                        <p>Select the date, shift, and route to prepare driver and bus recommendations.</p>
                    </div>
                    <div class="auto-scheduling-assurance"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>The system checks attendance, bus availability, and schedule conflicts before confirming assignments.</span></div>
                </div>

                <form id="autoSchedulingForm" class="auto-scheduling-form">
                    @csrf

                    <div class="schedule-form-grid">
                        <div class="schedule-field">
                            <label for="autoScheduleDate">Schedule Date</label>
                            <div class="field-control">
                                <i class="fa-solid fa-calendar"></i>
                                <input
                                    type="date"
                                    name="schedule_date"
                                    id="autoScheduleDate"
                                    value="{{ $selectedDate }}"
                                    required
                                >
                            </div>
                        </div>

                        <div class="schedule-field">
                            <label for="autoScheduleShift">Shift</label>
                            <div class="field-control">
                                <i class="fa-solid fa-clock"></i>
                                <select name="shift" id="autoScheduleShift">
                                    <option value="all" @selected($selectedShift === 'all')>All Shifts</option>
                                    <option value="Morning" @selected($selectedShift === 'Morning')>Morning</option>
                                    <option value="Afternoon" @selected($selectedShift === 'Afternoon')>Afternoon</option>
                                    <option value="Night" @selected($selectedShift === 'Night')>Night</option>
                                </select>
                            </div>
                        </div>

                        <div class="schedule-field">
                            <label for="autoScheduleRoute">Route</label>
                            <div class="field-control">
                                <i class="fa-solid fa-route"></i>
                                <select name="shuttle_route_id" id="autoScheduleRoute">
                                    <option value="all" @selected((string) $selectedRoute === 'all')>All Routes</option>
                                    @foreach($activeRoutes as $route)
                                        <option
                                            value="{{ $route->id }}"
                                            @selected((string) $selectedRoute === (string) $route->id)
                                        >
                                            {{ $route->route_code }} — {{ $route->route_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="generation-summary">
                        <div class="generation-summary-item">
                            <span>Trips</span>
                            <strong id="generationTripCount">{{ $tripsToSchedule }}</strong>
                        </div>
                        <div class="generation-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                        <div class="generation-summary-item">
                            <span>Drivers</span>
                            <strong id="generationDriverCount">{{ $availableDrivers }}</strong>
                        </div>
                        <div class="generation-arrow"><i class="fa-solid fa-plus"></i></div>
                        <div class="generation-summary-item">
                            <span>Buses</span>
                            <strong id="generationBusCount">{{ $availableBuses }}</strong>
                        </div>
                    </div>

                    <div class="ai-ml-status" id="aiMlStatus" role="status" aria-live="polite">
                        <span class="ai-status-label"><i class="fa-solid fa-microchip" aria-hidden="true"></i> Ranking Source</span>
                        <span class="ai-status-badge is-pending"><i class="fa-solid fa-bus" aria-hidden="true"></i> Bus: Not evaluated</span>
                        <span class="ai-status-badge is-pending"><i class="fa-solid fa-user" aria-hidden="true"></i> Driver: Not evaluated</span>
                    </div>

                    <button
                        type="submit"
                        class="generate-schedule-btn"
                        id="generateScheduleButton"
                        data-generate-url="/operation/auto-scheduling/generate"
                    >
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>Generate Schedule</span>
                    </button>
                </form>
            </article>

            <article class="auto-card resource-card">
                <div class="auto-card-header compact">
                    <div>
                        <span class="section-eyebrow">Resource Status</span>
                        <h2>Current Availability</h2>
                        <p>Resources eligible for scheduling.</p>
                    </div>
                </div>

                <div class="resource-list">
                    <div class="resource-item">
                        <div class="resource-info">
                            <div class="resource-icon green"><i class="fa-solid fa-user-check"></i></div>
                            <div><strong>Available Drivers</strong><span>Present</span></div>
                        </div>
                        <strong class="resource-count" id="resourceAvailableDrivers">{{ $availableDrivers }}</strong>
                    </div>

                    <div class="resource-item">
                        <div class="resource-info">
                            <div class="resource-icon blue"><i class="fa-solid fa-bus"></i></div>
                            <div><strong>Available Buses</strong><span>Active and operational</span></div>
                        </div>
                        <strong class="resource-count" id="resourceAvailableBuses">{{ $availableBuses }}</strong>
                    </div>

                    <div class="resource-item">
                        <div class="resource-info">
                            <div class="resource-icon red"><i class="fa-solid fa-user-xmark"></i></div>
                            <div><strong>Unavailable Drivers</strong><span>Absent, on leave, or on duty</span></div>
                        </div>
                        <strong class="resource-count">{{ $unavailableDrivers }}</strong>
                    </div>

                    <div class="resource-item">
                        <div class="resource-info">
                            <div class="resource-icon orange"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <div><strong>Unavailable Buses</strong><span>Inactive or under maintenance</span></div>
                        </div>
                        <strong class="resource-count">{{ $unavailableBuses }}</strong>
                    </div>
                </div>
            </article>
        </section>

        <section class="auto-card scheduling-rules-card">
            <div class="auto-card-header rules-section-header">
                <div class="rules-section-heading">
                    <span class="rules-heading-icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></span>
                    <div>
                        <span class="section-eyebrow">Dispatch Logic</span>
                        <h2>Scheduling Rules</h2>
                        <p>Rules checked before assigning a driver or shuttle bus.</p>
                    </div>
                </div>
                <div class="rules-header-actions"><span class="rules-enforced-label"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Automatically enforced by the system</span><button type="button" class="rules-collapse-toggle" aria-expanded="false" aria-controls="autoSchedulingRulesGrid" data-rules-toggle><i class="fa-solid fa-chevron-down" aria-hidden="true"></i><span>Show rules</span></button></div>
            </div>

            <div class="rules-grid" id="autoSchedulingRulesGrid" hidden>
                @foreach([
                    ['Driver must be present', 'Only Present drivers can be assigned.'],
                    ['Driver must be available', 'Prevent overlapping driver assignments.'],
                    ['Bus must be operational', 'Only Active buses are eligible.'],
                    ['Exclude maintenance buses', 'Buses under maintenance cannot be assigned.'],
                    ['Prevent bus conflicts', 'The same bus cannot serve overlapping trips.'],
                    ['Balance workload', 'Prefer drivers and buses with fewer assignments.'],
                ] as [$title, $text])
                    <div class="rule-item active">
                        <div class="rule-check"><i class="fa-solid fa-check"></i></div>
                        <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="auto-card generated-schedule-card" id="generatedScheduleSection">
            <div class="gct-auto-print-header" aria-hidden="true">
                <div>
                    <h1>GCT-Systems</h1>
                    <h2>Auto Scheduling — Draft Assignment Sheet</h2>
                    <p>Proposed assignments for Operations review. Not authorized for dispatch.</p>
                </div>
                <div class="gct-auto-print-meta">
                    <strong>DRAFT — NOT CONFIRMED</strong>
                    <span>Schedule date: <span id="gctAutoPrintDate">—</span></span>
                    <span>Printed: <span id="gctAutoPrintGenerated">—</span></span>
                </div>
            </div>
            <div class="auto-card-header schedule-result-header">
                <div>
                    <span class="section-eyebrow">Auto-Generated Result</span>
                    <h2>Schedule Preview</h2>
                    <p>Review the recommendations before saving assignments.</p>
                </div>

                <div class="auto-draft-navigation">
                    <span id="autoDraftSaveStatus" class="auto-draft-save-status" role="status" aria-live="polite">No draft loaded</span>
                    <button type="button" id="openAutoScheduleDrafts" class="schedule-secondary-btn">
                        <i class="fa-solid fa-folder-open"></i> Saved Drafts
                    </button>
                    <a class="schedule-secondary-btn" href="{{ route('driver-bus-assignment') }}">
                        <i class="fa-solid fa-clipboard-check"></i> Confirmed Assignments
                    </a>
                    <div class="schedule-result-badge" id="generatedReadyCount">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>0 Ready Slots</span>
                    </div>
                </div>
            </div>

            <div class="service-panels-surface auto-service-panels">
                {{-- Incoming Trips Section --}}
                <article class="service-direction-panel incoming" id="autoIncomingPanel">
                    <header class="service-direction-header">
                        <div class="service-direction-heading">
                            <span class="service-direction-icon">
                                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h3>Incoming Trips</h3>
                                <p>Buses transporting employees to work</p>
                            </div>
                        </div>
                        <div class="service-direction-metrics">
                            <div class="service-metric-stat">
                                <span>Trips</span>
                                <strong id="incomingTripCount">0</strong>
                            </div>
                            <div class="service-metric-stat">
                                <span>Required Buses</span>
                                <strong id="incomingRequiredBusesCount">0</strong>
                            </div>
                            <div class="service-metric-chips">
                                <span class="service-chip modified auto-chip-ready">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    <span><strong id="incomingReadyCount">0</strong> Ready Slots</span>
                                </span>
                                <span class="service-chip cancelled auto-chip-review" id="incomingReviewChip">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <span><strong id="incomingReviewCount">0</strong> Slots to Review</span>
                                </span>
                                <span class="service-chip auto-chip-manual"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span><strong id="incomingManualCount">0</strong> Manual</span></span>
                            </div>
                        </div>
                    </header>

                    <div class="auto-table-wrap" role="region" aria-label="Incoming trip recommendations" tabindex="0">
                        <table class="auto-schedule-table">
                            <colgroup>
                                <col style="width:9%">
                                <col style="width:11%">
                                <col style="width:18%">
                                <col style="width:12%">
                                <col style="width:14%">
                                <col style="width:14%">
                                <col style="width:10%">
                                <col style="width:12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Trip ID</th>
                                    <th>Time</th>
                                    <th>Route</th>
                                    <th>Required Buses</th>
                                    <th>Driver</th>
                                    <th>Bus</th>
                                    <th>Result</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="autoScheduleIncomingBody">
                                <tr>
                                    <td colspan="8">
                                        <div class="auto-empty-state">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                                            <strong>No schedule generated yet</strong>
                                            <span>Select the date and filters, then click Generate Schedule.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                {{-- Outgoing Trips Section --}}
                <article class="service-direction-panel outgoing" id="autoOutgoingPanel">
                    <header class="service-direction-header">
                        <div class="service-direction-heading">
                            <span class="service-direction-icon">
                                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h3>Outgoing Trips</h3>
                                <p>Buses transporting employees home</p>
                            </div>
                        </div>
                        <div class="service-direction-metrics">
                            <div class="service-metric-stat">
                                <span>Trips</span>
                                <strong id="outgoingTripCount">0</strong>
                            </div>
                            <div class="service-metric-stat">
                                <span>Required Buses</span>
                                <strong id="outgoingRequiredBusesCount">0</strong>
                            </div>
                            <div class="service-metric-chips">
                                <span class="service-chip modified auto-chip-ready">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    <span><strong id="outgoingReadyCount">0</strong> Ready Slots</span>
                                </span>
                                <span class="service-chip cancelled auto-chip-review" id="outgoingReviewChip">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <span><strong id="outgoingReviewCount">0</strong> Slots to Review</span>
                                </span>
                                <span class="service-chip auto-chip-manual"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span><strong id="outgoingManualCount">0</strong> Manual</span></span>
                            </div>
                        </div>
                    </header>

                    <div class="auto-table-wrap" role="region" aria-label="Outgoing trip recommendations" tabindex="0">
                        <table class="auto-schedule-table">
                            <colgroup>
                                <col style="width:9%">
                                <col style="width:11%">
                                <col style="width:18%">
                                <col style="width:12%">
                                <col style="width:14%">
                                <col style="width:14%">
                                <col style="width:10%">
                                <col style="width:12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Trip ID</th>
                                    <th>Time</th>
                                    <th>Route</th>
                                    <th>Required Buses</th>
                                    <th>Driver</th>
                                    <th>Bus</th>
                                    <th>Result</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="autoScheduleOutgoingBody">
                                <tr>
                                    <td colspan="8">
                                        <div class="auto-empty-state">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                                            <strong>No schedule generated yet</strong>
                                            <span>Select the date and filters, then click Generate Schedule.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <tbody id="autoSchedulePreviewBody" style="display:none;" aria-hidden="true"></tbody>

            <div class="schedule-footer auto-sticky-actions">
                <div class="schedule-footer-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="generatedFooterMessage">Generate a schedule to view recommendations.</span>
                </div>

                <div class="schedule-footer-actions">
                    @if($canEditOperation)
                        <button type="button" class="schedule-secondary-btn" id="saveAutoScheduleDraftButton">
                            <i class="fa-solid fa-floppy-disk"></i> Save Draft
                        </button>
                    @else
                        <span title="You don't have authorized access to this action." aria-label="You don't have authorized access to this action." style="display:inline-flex;cursor:not-allowed"><button type="button" class="schedule-secondary-btn" disabled aria-disabled="true" tabindex="-1" style="opacity:.45;filter:grayscale(1);pointer-events:none"><i class="fa-solid fa-floppy-disk"></i> Save Draft</button></span>
                    @endif
                    <button type="button" class="schedule-secondary-btn" id="printAutoScheduleDraftButton">
                        <i class="fa-solid fa-print"></i> Print Draft
                    </button>
                    <button type="button" class="schedule-secondary-btn" id="regenerateScheduleButton">
                        <i class="fa-solid fa-rotate"></i>
                        Regenerate
                    </button>

                    @if($canEditOperation && $canApproveOperation)
                        <button type="button" class="schedule-primary-btn" id="confirmScheduleButton" disabled>
                            <i class="fa-solid fa-circle-check"></i>
                            Confirm Schedule
                        </button>
                    @else
                        <span title="You don't have authorized access to this action." aria-label="You don't have authorized access to this action." style="display:inline-flex;cursor:not-allowed">
                          <button type="button" class="schedule-primary-btn" disabled aria-disabled="true" tabindex="-1" style="opacity:.45;filter:grayscale(1);pointer-events:none"><i class="fa-solid fa-circle-check"></i> Confirm Schedule</button>
                        </span>
                    @endif
                </div>
            </div>
            <div class="gct-auto-print-signatures" aria-hidden="true">
                <div>Prepared by / Operations Staff</div>
                <div>Reviewed by / Operations Head</div>
            </div>
        </section>

        <section
            class="auto-card conflict-card"
            id="autoSchedulingConflictSection"
            hidden
        >
            <div class="conflict-header">
                <div class="conflict-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <span class="section-eyebrow warning">Requires Attention</span>
                    <h2>Scheduling Conflicts</h2>
                    <p>Some trips could not be assigned automatically.</p>
                </div>
            </div>

            <div id="autoSchedulingConflictContent"></div>
        </section>
    </main>
</div>

<script>
var getResolutionActionLabel = function (action) {
    if (
        action
        && action.type === 'adjust_departure_time'
        && action.suggested_time
    ) {
        var parts = String(action.suggested_time).split(':');
        var hour = Number(parts[0]);
        var minute = parts[1] || '00';
        var period = hour >= 12 ? 'PM' : 'AM';

        hour = hour % 12 || 12;

        return 'Review departure at '
            + hour
            + ':'
            + minute
            + ' '
            + period;
    }

    return action && (action.title || action.label)
        ? (action.title || action.label)
        : 'Review recommended action';
};

document.addEventListener('DOMContentLoaded', () => {
    const cleanSuggestedTime = () => {
        document
            .querySelectorAll('.ai-action-item > small')
            .forEach((element) => element.remove());
    };

    cleanSuggestedTime();

    const observer = new MutationObserver(
        cleanSuggestedTime
    );

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
});
</script>

</x-layout.app>
