@php
    $hasConfiguration = $configuration !== null;
    $sortedSlots = $hasConfiguration
        ? $configuration->slots->sortBy(fn ($slot) => $slot->minutes())->values()
        : collect();
    $enabledSlotCount = $sortedSlots->filter(fn ($slot) => $slot->is_enabled)->count();
@endphp

<div class="ctt-panel-header">
    <span class="ctt-panel-title">
        <i class="fa-solid {{ str_contains($category, 'incoming') ? 'fa-arrow-down' : 'fa-arrow-up' }}" aria-hidden="true"></i>
        {{ $categoryLabel }}
    </span>
    @if(! $hasConfiguration)
        <span class="ctt-status-chip ctt-status-none">Not Configured</span>
    @elseif($configuration->is_active)
        <span class="ctt-status-chip ctt-status-active">Active</span>
    @else
        <span class="ctt-status-chip ctt-status-disabled">Disabled</span>
    @endif
</div>

@if($hasConfiguration)
    <p class="ctt-panel-range">
        <i class="fa-regular fa-clock" aria-hidden="true"></i>
        <strong>{{ \App\Support\ClientTripTimes::displayTime($configuration->start_time) }} â€“ {{ \App\Support\ClientTripTimes::displayTime($configuration->end_time) }}</strong>
        <span>{{ $enabledSlotCount }} of {{ $sortedSlots->count() }} {{ \Illuminate\Support\Str::plural('slot', $sortedSlots->count()) }} enabled</span>
    </p>

    @unless($configuration->is_active)
        <p class="ctt-panel-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Disabled â€” trips for this category are not restricted until this configuration is enabled again.
        </p>
    @endunless

    <div class="ctt-slot-list">
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
        <form class="ctt-add-slot-form" data-config-id="{{ $configuration->id }}" autocomplete="off">
            <input type="time" name="slot_time" aria-label="New slot time" required>
            <button type="submit" class="ctt-btn ctt-btn-small">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Slot
            </button>
        </form>
        <span class="ctt-field-error" data-error-for="slot_time"></span>

        <div class="ctt-panel-footer">
            <button
                type="button"
                class="ctt-btn ctt-btn-ghost ctt-edit-range"
                data-config-id="{{ $configuration->id }}"
                data-start-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->startMinutes()) }}"
                data-end-time="{{ \App\Support\ClientTripTimes::inputTimeFromMinutes($configuration->endMinutes()) }}"
                data-is-active="{{ $configuration->is_active ? '1' : '0' }}"
            >
                <i class="fa-solid fa-sliders" aria-hidden="true"></i> Edit Range
            </button>
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
