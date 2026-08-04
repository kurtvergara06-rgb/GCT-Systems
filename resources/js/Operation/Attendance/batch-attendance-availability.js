document.addEventListener('DOMContentLoaded', () => {
    const attendancePath = window.location.pathname.replace(/\/$/, '');

    if (
        !attendancePath.endsWith('/driver-attendance')
        && !attendancePath.endsWith('/mechanic-attendance')
    ) {
        return;
    }

    const initializeRows = (root = document) => {
        root.querySelectorAll('[data-batch-row]').forEach((row) => {
            if (row.dataset.availabilityInitialized === 'true') {
                return;
            }

            const badge = row.querySelector('.batch-availability');
            const status = row.querySelector('[data-status]');

            if (!badge || !status) {
                return;
            }

            row.dataset.availabilityInitialized = 'true';
            row.dataset.baseAvailability = badge.textContent.trim() || 'Available';

            const updateAvailability = () => {
                const attendanceStatus = status.value;
                const unavailable = ['Absent', 'On Leave'].includes(
                    attendanceStatus
                );

                const availability = unavailable
                    ? 'Unavailable'
                    : row.dataset.baseAvailability || 'Available';

                badge.textContent = availability;
                badge.classList.remove(
                    'available',
                    'busy',
                    'unavailable'
                );

                if (availability === 'Available') {
                    badge.classList.add('available');
                } else if (availability === 'Unavailable') {
                    badge.classList.add('unavailable');
                } else {
                    badge.classList.add('busy');
                }

                badge.setAttribute(
                    'title',
                    availability === 'Available'
                        ? 'Present and currently free for assignment.'
                        : availability === 'On Duty'
                            ? 'Present but currently assigned to an active trip.'
                            : availability === 'On Job'
                                ? 'Present but currently assigned to an active job order.'
                                : 'Not available because the attendance status is Absent or On Leave.'
                );
            };

            status.addEventListener('change', updateAvailability);
            updateAvailability();
        });
    };

    initializeRows();

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) {
                    continue;
                }

                if (node.matches?.('[data-batch-row]')) {
                    initializeRows(node.parentElement || node);
                } else if (node.querySelector?.('[data-batch-row]')) {
                    initializeRows(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
});
