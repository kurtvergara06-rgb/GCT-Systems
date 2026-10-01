const registerJobOrderWorkDetails = () => {
    const partialNavigation = window.GCTPartialNavigation;

    if (!partialNavigation?.registerInitializer) {
        return;
    }

    partialNavigation.registerInitializer('maintenance-job-order-work-details', '.jo-page', async (root) => {
        const createForm = root.querySelector('#newJobOrderForm') || document.getElementById('newJobOrderForm');
        const editForm = root.querySelector('#editJobForm') || document.getElementById('editJobForm');

        const addWorkField = ({ form, afterId, inputId, placeholder }) => {
            if (!form || document.getElementById(inputId)) {
                return document.getElementById(inputId);
            }

            const anchor = document.getElementById(afterId);
            const anchorGroup = anchor?.closest('.ui-form-group');

            if (!anchorGroup) {
                return null;
            }

            const group = document.createElement('div');
            group.className = 'ui-form-group ui-form-full jo-work-to-perform-field';
            group.innerHTML = `
                <label for="${inputId}">
                    Work / Repair to Perform <span class="ui-required">*</span>
                </label>
                <textarea
                    name="work_to_perform"
                    id="${inputId}"
                    maxlength="2000"
                    placeholder="${placeholder}"
                    required
                ></textarea>
                <span class="jo-work-help">Describe the actual maintenance or repair work that will be performed, separate from the reported problem.</span>
            `;

            anchorGroup.insertAdjacentElement('afterend', group);
            return group.querySelector('textarea');
        };

        const createWorkInput = addWorkField({
            form: createForm,
            afterId: 'jobProblemIssue',
            inputId: 'jobWorkToPerform',
            placeholder: 'e.g. Replace tail light bulb and inspect wiring',
        });

        const editWorkInput = addWorkField({
            form: editForm,
            afterId: 'edit_problem_issue',
            inputId: 'edit_work_to_perform',
            placeholder: 'Describe the repair or maintenance work to perform',
        });

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

        root.addEventListener('click', (event) => {
            const button = event.target.closest('.open-edit-modal[data-id]');

            if (!button || !editWorkInput) {
                return;
            }

            const id = button.dataset.id;
            const work = String(workDetails[id] || '');
            const isViewOnly = button.dataset.viewOnly === '1';

            editWorkInput.value = work;
            editWorkInput.disabled = isViewOnly;
            editWorkInput.readOnly = isViewOnly;
            editWorkInput.required = !isViewOnly;
            editWorkInput.placeholder = isViewOnly && !work
                ? 'No specific work was recorded for this existing Job Order.'
                : 'Describe the repair or maintenance work to perform';
        });

        createForm?.addEventListener('reset', () => {
            if (createWorkInput) {
                createWorkInput.value = '';
            }
        });
    });
};

registerJobOrderWorkDetails();
