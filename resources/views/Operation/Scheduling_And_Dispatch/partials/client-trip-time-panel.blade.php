@php
    $hasConfiguration = $configuration !== null;
    $sortedSlots = $hasConfiguration
        ? $configuration->slots->sortBy(fn ($slot) => $slot->minutes())->values()
        : collect();
    $enabledSlotCount = $sortedSlots->filter(fn ($slot) => $slot->is_enabled)->count();
@endphp

<div class="ctt-panel-header ctt-ref-panel-header" data-client-id="{{ $client->id }}" data-category="{{ $category }}">
    <span class="ctt-panel-title">
        <span class="ctt-direction-icon ctt-direction-{{ $category }}">
            <i class="fa-solid {{ str_contains($category, 'incoming') ? 'fa-sun' : 'fa-moon' }}" aria-hidden="true"></i>
        </span>
        <span>{{ $categoryLabel }}</span>
    </span>
    <span class="ctt-panel-top-actions">
        @if(! $hasConfiguration)
            <span class="ctt-status-chip ctt-status-none"><i class="fa-solid fa-clock" aria-hidden="true"></i> Not Configured</span>
        @elseif($configuration->is_active)
            <span class="ctt-status-chip ctt-status-active"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Configured</span>
        @else
            <span class="ctt-status-chip ctt-status-disabled">Disabled</span>
        @endif
        @if($hasConfiguration && $canEditOperation)
            <button
                type="button"
                class="ctt-btn ctt-btn-ghost ctt-ref-edit ctt-open-editor"
                data-config-id="{{ $configuration->id }}"
                aria-expanded="false"
                data-start-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->startMinutes()) }}"
                data-end-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->endMinutes()) }}"
                data-is-active="{{ $configuration->is_active ? '1' : '0' }}"
            ><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</button>
        @endif
    </span>
</div>
@if($hasConfiguration)
    <div class="ctt-ref-window">
        <span>Allowed departure window</span>
        <strong>{{ \App\Support\ClientTripTimes::displayTime($configuration->start_time) }} – {{ \App\Support\ClientTripTimes::displayTime($configuration->end_time) }}</strong>
    </div>
    @unless($configuration->is_active)
        <p class="ctt-panel-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Disabled — new trips for this category are blocked until this configuration is enabled again.
        </p>
    @endunless

    <div class="ctt-ref-slots-label">Allowed time slots ({{ $enabledSlotCount }})</div>
    <div class="ctt-slot-list" aria-label="Saved allowed time slots">
        @forelse($sortedSlots as $slot)
            <span
                class="ctt-slot-chip {{ $slot->is_enabled ? '' : 'is-off' }}"
                data-slot-id="{{ $slot->id }}"
                data-slot-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($slot->minutes()) }}"
                data-slot-enabled="{{ $slot->is_enabled ? '1' : '0' }}"
            >
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                {{ \App\Support\ClientTripTimes::displayTime($slot->minutes()) }}
                @if($canEditOperation)
                    <button
                        type="button"
                        class="ctt-slot-toggle"
                        title="{{ $slot->is_enabled ? 'Disable this slot' : 'Enable this slot' }}"
                        aria-label="{{ $slot->is_enabled ? 'Disable this slot' : 'Enable this slot' }}"
                    >
                        <i class="fa-solid {{ $slot->is_enabled ? 'fa-toggle-on' : 'fa-toggle-off' }}" aria-hidden="true"></i>
                    </button>
                    <button
                        type="button"
                        class="ctt-slot-edit"
                        title="Edit this slot"
                        aria-label="Edit this slot"
                    >
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    </button>
                @endif
            </span>
        @empty
            <p class="ctt-slot-empty">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                No time slots are configured for this category yet.
            </p>
        @endforelse
    </div>

    @if($canEditOperation)
        <div class="ctt-ref-slot-editor" hidden>
            <div class="ctt-ref-editor-actions">
                <button
                    type="button"
                    class="ctt-btn ctt-btn-ghost ctt-edit-range"
                    data-config-id="{{ $configuration->id }}"
                    data-start-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->startMinutes()) }}"
                    data-end-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->endMinutes()) }}"
                    data-is-active="{{ $configuration->is_active ? '1' : '0' }}"
                ><i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit Range</button>
                <span class="ctt-ref-editor-note">Select a saved slot to edit or enable/disable it.</span>
            </div>
            <form class="ctt-add-slot-form" data-config-id="{{ $configuration->id }}" data-min-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes(max($configuration->startMinutes(), \App\Support\ClientTripTimes::isAmCategory($category) ? 0 : 720)) }}" data-max-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes(min($configuration->endMinutes(), \App\Support\ClientTripTimes::isAmCategory($category) ? 719 : 1439)) }}" autocomplete="off">
                <input type="time" name="slot_time" aria-label="New slot time" min="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes(max($configuration->startMinutes(), \App\Support\ClientTripTimes::isAmCategory($category) ? 0 : 720)) }}" max="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes(min($configuration->endMinutes(), \App\Support\ClientTripTimes::isAmCategory($category) ? 719 : 1439)) }}" required>
                <button type="submit" class="ctt-btn ctt-btn-small"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Slot</button>
            </form>
            <span class="ctt-slot-save-hint">Selecting a time does not save it. Click Add Slot to save.</span>
            <span class="ctt-field-error" data-error-for="slot_time" role="alert"></span>
        </div>
    @endif
@else
    <div class="ctt-panel-empty">
        <i class="fa-regular fa-clock" aria-hidden="true"></i>
        <span>No {{ $categoryLabel }} trip times are configured for this client.</span>
        @if($canEditOperation)
            <button
                type="button"
                class="ctt-btn ctt-add-config"
                data-client-id="{{ $client->id }}"
                data-client-name="{{ $client->name }}"
                data-category="{{ $category }}"
            >
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Configuration
            </button>
        @endif
    </div>
@endif
