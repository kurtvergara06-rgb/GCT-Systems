@props([
    'stage' => 'diagnostic',
    'domain' => 'all',
    'insight' => null,
])

@php
    $stage = strtolower(trim((string) $stage));
    $domain = strtolower(trim((string) $domain));
    $insightData = is_array($insight) ? $insight : null;
    $hasInsight = filled(data_get($insightData, 'title'))
        && filled(data_get($insightData, 'message'));

    $priority = $hasInsight
        ? (string) data_get($insightData, 'priority', 'warning')
        : 'warning';

    $sessionKey = $hasInsight
        ? 'gct_insight_dismissed_' . $stage . '_' . $domain . '_' . substr(
            sha1(
                (string) data_get($insightData, 'title')
                . '|' . (string) data_get($insightData, 'metric')
                . '|' . (string) data_get($insightData, 'message')
            ),
            0,
            12
        )
        : null;
@endphp

@if($hasInsight)
<div
    class="analytics-insight-toast-container"
    data-analytics-insight-toast
    data-session-key="{{ $sessionKey }}"
    data-target-selector="{{ data_get($insightData, 'target', '') }}"
    role="region"
    aria-label="Analytics Alert Notification"
>
    <div class="analytics-insight-toast priority-{{ $priority }} stage-{{ $stage }}">
        <div class="toast-header-row">
            <div class="toast-eyebrow">
                <span class="toast-icon-wrap">
                    <i class="{{ data_get($insightData, 'icon', 'fa-solid fa-triangle-exclamation') }}"></i>
                </span>
                <span class="toast-tag">{{ data_get($insightData, 'label', 'Analytics Alert') }}</span>
                <span class="toast-priority-pill priority-{{ $priority }}">{{ ucfirst($priority) }}</span>
            </div>

            <button
                type="button"
                class="toast-dismiss-btn"
                aria-label="Dismiss analytics alert"
                title="Dismiss"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="toast-body-row">
            <strong class="toast-headline">{{ data_get($insightData, 'title') }}</strong>
            <p class="toast-copy">{{ data_get($insightData, 'message') }}</p>
        </div>

        <div class="toast-footer-row">
            @if(filled(data_get($insightData, 'metric')))
                <span class="toast-metric-chip">
                    <i class="fa-solid fa-circle-info"></i>
                    {{ data_get($insightData, 'metric') }}
                </span>
            @endif

            <button
                type="button"
                class="toast-action-btn"
                data-toast-action
            >
                <span>{{ data_get($insightData, 'action', 'View Details') }}</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        const container = document.querySelector('[data-analytics-insight-toast]');
        if (!container) return;

        const sessionKey = container.dataset.sessionKey;
        if (sessionStorage.getItem(sessionKey) === '1') {
            container.remove();
            return;
        }

        // 1. Notification Bell Animation (runs 2-3s only, then returns to static state)
        const bellBtn = document.querySelector('.icon-btn.notification');
        if (bellBtn) {
            bellBtn.classList.add('is-ringing');
            setTimeout(function () {
                bellBtn.classList.remove('is-ringing');
            }, 2300);

            // Small unread notification indicator badge
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                if (badge.hasAttribute('hidden') || badge.textContent.trim() === '0' || badge.style.display === 'none') {
                    badge.removeAttribute('hidden');
                    badge.style.display = 'inline-flex';
                    badge.textContent = '1';
                }
            }
        }

        // 2. Auto-dismiss timer (10 seconds) with hover pause and resume
        let autoDismissTimer = null;
        let remainingTime = 10000;
        let lastStartTime = Date.now();

        function startTimer() {
            lastStartTime = Date.now();
            autoDismissTimer = setTimeout(function () {
                dismissToast(false);
            }, remainingTime);
        }

        function pauseTimer() {
            if (autoDismissTimer) {
                clearTimeout(autoDismissTimer);
                autoDismissTimer = null;
                remainingTime -= (Date.now() - lastStartTime);
                if (remainingTime < 2000) remainingTime = 2000;
            }
        }

        function dismissToast(userExplicit) {
            if (autoDismissTimer) {
                clearTimeout(autoDismissTimer);
                autoDismissTimer = null;
            }
            if (userExplicit) {
                sessionStorage.setItem(sessionKey, '1');
            }
            const toastEl = container.querySelector('.analytics-insight-toast');
            if (toastEl) {
                toastEl.classList.add('is-dismissing');
            }
            setTimeout(function () {
                container.remove();
            }, 260);
        }

        startTimer();

        container.addEventListener('mouseenter', pauseTimer);
        container.addEventListener('mouseleave', function () {
            startTimer();
        });

        const closeBtn = container.querySelector('.toast-dismiss-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dismissToast(true);
            });
        }

        // 3. Global & Reusable Target Highlighting Function
        window.gctHighlightTarget = function (targetSelectorOrEl) {
            let targetEl = null;
            if (typeof targetSelectorOrEl === 'string') {
                const selectorList = targetSelectorOrEl.split(',');
                for (let i = 0; i < selectorList.length; i++) {
                    const sel = selectorList[i].trim();
                    if (sel) {
                        const found = document.querySelector(sel);
                        if (found) {
                            targetEl = found;
                            break;
                        }
                    }
                }
            } else if (targetSelectorOrEl instanceof Element) {
                targetEl = targetSelectorOrEl;
            }

            if (!targetEl) return null;

            // Scroll into view smoothly (centered vertically)
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Clear previous highlight timer if user clicked multiple times
            if (targetEl._gctHighlightTimer) {
                clearTimeout(targetEl._gctHighlightTimer);
                targetEl._gctHighlightTimer = null;
            }

            // Remove class and trigger DOM reflow to reliably restart animation
            targetEl.classList.remove('gct-highlight-pulse');
            void targetEl.offsetWidth;
            targetEl.classList.add('gct-highlight-pulse');

            // Keep highlight visible for ~2.6 seconds (within 2-4s range), then smoothly return to normal
            targetEl._gctHighlightTimer = setTimeout(function () {
                targetEl.classList.remove('gct-highlight-pulse');
                targetEl._gctHighlightTimer = null;
            }, 2600);

            return targetEl;
        };

        // 4. "View Details" / "Review Recommendations" click handler
        const actionBtn = container.querySelector('[data-toast-action]');
        if (actionBtn) {
            actionBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const selectorString = container.dataset.targetSelector || '';
                window.gctHighlightTarget(selectorString);

                // Reset timer giving user time to view highlighted entry and click multiple times
                if (autoDismissTimer) {
                    clearTimeout(autoDismissTimer);
                }
                remainingTime = 8000;
                startTimer();
            });
        }
    })();
</script>
@endif
