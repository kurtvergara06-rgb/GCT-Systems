@php
    $toasts = collect();

    $sessionTypes = [
        ['type' => 'success', 'title' => 'Success', 'icon' => 'fa-solid fa-circle-check', 'message' => session('success')],
        ['type' => 'error', 'title' => 'Error', 'icon' => 'fa-solid fa-circle-exclamation', 'message' => session('error')],
        ['type' => 'warning', 'title' => 'Warning', 'icon' => 'fa-solid fa-triangle-exclamation', 'message' => session('warning')],
        ['type' => 'info', 'title' => 'Info', 'icon' => 'fa-solid fa-circle-info', 'message' => session('info')],
    ];

    foreach ($sessionTypes as $entry) {
        $message = $entry['message'];

        if (is_string($message)) {
            $message = trim($message);
        }

        if (!empty($message)) {
            $toasts->push([
                'type' => $entry['type'],
                'title' => $entry['title'],
                'icon' => $entry['icon'],
                'message' => $message,
            ]);
        }
    }

    if ($errors->any()) {
        $firstError = trim((string) $errors->first());

        if (!empty($firstError)) {
            $toasts->push([
                'type' => 'error',
                'title' => 'Validation Error',
                'icon' => 'fa-solid fa-circle-exclamation',
                'message' => $firstError,
            ]);
        }
    }
@endphp

@if($toasts->isNotEmpty())
    <div class="system-toast-root" aria-live="polite" aria-atomic="true">
        @foreach($toasts as $toast)
            <div
                class="system-toast-notification system-toast-notification--{{ $toast['type'] }}"
                data-system-toast
                data-type="{{ $toast['type'] }}"
                role="status"
                aria-live="polite"
            >
                <div class="system-toast-icon" aria-hidden="true">
                    <i class="{{ $toast['icon'] }}"></i>
                </div>

                <div class="system-toast-body">
                    <div class="system-toast-title">{{ $toast['title'] }}</div>
                    <div class="system-toast-message">{{ $toast['message'] }}</div>
                </div>

                <button type="button" class="system-toast-close" aria-label="Close notification">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        @endforeach
    </div>
@endif
