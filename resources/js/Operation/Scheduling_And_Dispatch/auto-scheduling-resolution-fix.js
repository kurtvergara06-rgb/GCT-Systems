document.addEventListener('DOMContentLoaded', () => {
    const originalFetch = window.fetch.bind(window);

    window.fetch = async (input, init = {}) => {
        const url = typeof input === 'string'
            ? input
            : input?.url || '';

        if (
            url.includes('/operation/auto-scheduling/resolve')
            && typeof init.body === 'string'
        ) {
            try {
                const payload = JSON.parse(init.body);

                if (
                    !payload.proposed_departure_time
                    && payload.suggested_time
                ) {
                    payload.proposed_departure_time =
                        payload.suggested_time;

                    delete payload.suggested_time;

                    init = {
                        ...init,
                        body: JSON.stringify(payload),
                    };
                }
            } catch (error) {
                console.warn(
                    'Unable to normalize AI resolution payload.',
                    error
                );
            }
        }

        return originalFetch(input, init);
    };

    const toDisplayTime = (value) => {
        const match = String(value || '').match(
            /\b([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?\b/
        );

        if (!match) {
            return null;
        }

        const hour24 = Number(match[1]);
        const minute = match[2];
        const period = hour24 >= 12 ? 'PM' : 'AM';
        const hour12 = hour24 % 12 || 12;

        return `${hour12}:${minute} ${period}`;
    };

    const normalizeRecommendedActions = () => {
        document
            .querySelectorAll('.ai-action-item')
            .forEach((item) => {
                const title = item.querySelector('strong');
                const duplicateTime = item.querySelector('small');

                if (!title) {
                    return;
                }

                const displayTime = toDisplayTime(
                    title.textContent
                );

                if (displayTime) {
                    title.textContent =
                        `Review departure at ${displayTime}`;
                }

                if (
                    duplicateTime
                    && /suggested\s*time/i.test(
                        duplicateTime.textContent || ''
                    )
                ) {
                    duplicateTime.remove();
                }
            });
    };

    normalizeRecommendedActions();

    const observer = new MutationObserver(
        normalizeRecommendedActions
    );

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
});
