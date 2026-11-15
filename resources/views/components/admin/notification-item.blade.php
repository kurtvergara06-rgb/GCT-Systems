@props(['notification'])

@php
    $typeClass = strtolower($notification['type'] ?? 'update');
    $moduleClass = strtolower($notification['module'] ?? 'system');
@endphp

<article
    class="notification-item {{ ($notification['unread'] ?? false) ? 'unread' : '' }}"
    data-notification-item
    data-module="{{ $notification['module'] ?? 'System' }}"
    data-type="{{ $notification['type'] ?? 'Update' }}"
    data-state="{{ ($notification['unread'] ?? false) ? 'unread' : 'read' }}"
>
    <div class="notification-icon {{ $typeClass }}">
        <i class="fa-solid {{ $notification['icon'] ?? 'fa-bell' }}"></i>
    </div>

    <div class="notification-content">
        <div class="notification-heading">
            <div>
                <div class="notification-title-row">
                    <h3>{{ $notification['title'] ?? 'System notification' }}</h3>

                    @if($notification['unread'] ?? false)
                        <span class="unread-dot" aria-label="Unread"></span>
                    @endif
                </div>

                <p>{{ $notification['message'] ?? '—' }}</p>
            </div>

            <div class="notification-time">
                <strong>{{ $notification['date'] ?? '—' }}</strong>
                <span>{{ $notification['time'] ?? '—' }}</span>
            </div>
        </div>

        <div class="notification-meta">
            <x-ui.status-badge :status="$notification['module'] ?? 'System'" :class="$moduleClass" />
            <x-ui.status-badge :status="$notification['type'] ?? 'Update'" :class="$typeClass" />
            <span class="system-id-badge system-id-badge--small">{{ $notification['reference'] ?? '—' }}</span>
        </div>
    </div>

    <div class="notification-actions record-actions">
        <x-ui.action-button
            type="view"
            title="View Details"
            class="open-notification-modal"
            data-title="{{ $notification['title'] ?? '' }}"
            data-message="{{ $notification['message'] ?? '' }}"
            data-module="{{ $notification['module'] ?? 'System' }}"
            data-type="{{ $notification['type'] ?? 'Update' }}"
            data-reference="{{ $notification['reference'] ?? '—' }}"
            data-date="{{ $notification['date'] ?? '—' }}"
            data-time="{{ $notification['time'] ?? '—' }}"
        />

        @if($notification['unread'] ?? false)
            <button
                type="button"
                class="action-btn approve notification-mark-read"
                title="Mark as Read"
                data-read-url="{{ route('admin.notifications.read', $notification['id']) }}"
            >
                <i class="fa-solid fa-check"></i>
            </button>
        @endif
    </div>
</article>
