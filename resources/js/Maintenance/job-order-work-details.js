const registerJobOrderWorkDetails = () => {
    const partialNavigation = window.GCTPartialNavigation;

    if (!partialNavigation?.registerInitializer) {
        return;
    }

    partialNavigation.registerInitializer('maintenance-job-order-work-details', '.jo-page', async (root) => {
        const detailsUrl = new URL('/job-orders/work-details', window.location.origin);
        let workDetails = {};

        try {
            const response = await fetch(detailsUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                workDetails = await response.json();
            }
        } catch {
            workDetails = {};
        }

        const shortText = (value, max = 72) => {
            const text = String(value || '').trim();
            return text.length > max ? `${text.slice(0, max - 1)}…` : text;
        };

        root.querySelectorAll('.job-orders-table tbody tr').forEach((row) => {
            const action = row.querySelector('.open-edit-modal[data-id]');
            const id = action?.dataset.id;
            const work = id ? String(workDetails[id] || '').trim() : '';
            const maintenanceCell = row.querySelector('td:nth-child(2)');

            if (!maintenanceCell || maintenanceCell.querySelector('.jo-work-inline')) {
                return;
            }

            const workLine = document.createElement('span');
            workLine.className = `jo-work-inline${work ? '' : ' is-empty'}`;
            workLine.title = work || 'No specific work recorded yet';
            workLine.textContent = work ? `Work: ${shortText(work)}` : 'Work: Not recorded';
            maintenanceCell.appendChild(workLine);
        });
    });
};

registerJobOrderWorkDetails();
