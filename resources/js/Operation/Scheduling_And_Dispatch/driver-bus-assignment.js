document.addEventListener('DOMContentLoaded', () => {
    const assignmentModal =
        document.getElementById('assignmentModal');

    const viewAssignmentModal =
        document.getElementById('viewAssignmentModal');

    const removeAssignmentModal =
        document.getElementById('removeAssignmentModal');

    const assignmentForm =
        document.getElementById('assignmentForm');

    const assignmentFormMethod =
        document.getElementById('assignmentFormMethod');

    const assignmentModalTitle =
        document.getElementById('assignmentModalTitle');

    const assignmentSubmitText =
        document.getElementById('assignmentSubmitText');

    const assignmentTrip =
        document.getElementById('assignmentTrip');

    const assignmentDriver =
        document.getElementById('assignmentDriver');

    const assignmentBus =
        document.getElementById('assignmentBus');

    const createAction =
        '/operation/driver-bus-assignment';

    let selectedRemoveForm = null;

    function openModal(modal) {
        modal?.classList.add('show', 'active');
    }

    function closeModal(modal) {
        modal?.classList.remove('show', 'active');
    }

    function normalizePath(value, fallback) {
        const raw = String(value || '').trim();

        if (!raw) {
            return fallback;
        }

        if (raw.startsWith('/') && !raw.startsWith('//')) {
            return raw;
        }

        try {
            const parsed = new URL(raw, window.location.origin);

            if (parsed.origin === window.location.origin) {
                return `${parsed.pathname}${parsed.search}${parsed.hash}`;
            }
        } catch (error) {
            console.warn('Invalid assignment URL.', error);
        }

        const cleaned = raw
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const index = cleaned.indexOf(
            'operation/driver-bus-assignment'
        );

        return index >= 0
            ? `/${cleaned.slice(index)}`
            : fallback;
    }

    function resetAssignmentForm() {
        assignmentForm?.reset();

        assignmentForm?.setAttribute(
            'action',
            createAction
        );

        if (assignmentFormMethod) {
            assignmentFormMethod.disabled = true;
        }

        if (assignmentTrip) {
            assignmentTrip.disabled = false;
            assignmentTrip.required = true;
        }

        if (assignmentModalTitle) {
            assignmentModalTitle.textContent =
                'Driver & Bus Assignment';
        }

        if (assignmentSubmitText) {
            assignmentSubmitText.textContent =
                'Confirm Assignment';
        }
    }

    document
        .getElementById('openAssignmentModal')
        ?.addEventListener('click', () => {
            resetAssignmentForm();
            openModal(assignmentModal);
        });

    document
        .querySelectorAll('.open-assignment')
        .forEach((button) => {
            button.addEventListener('click', () => {
                resetAssignmentForm();

                if (assignmentTrip) {
                    assignmentTrip.value =
                        button.dataset.tripId || '';
                }

                openModal(assignmentModal);
            });
        });

    [
        document.getElementById('closeAssignmentModal'),
        document.getElementById('cancelAssignmentModal'),
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(assignmentModal);
            });
        });

    document
        .querySelectorAll('.edit-assignment')
        .forEach((button) => {
            button.addEventListener('click', () => {
                if (!assignmentForm) {
                    return;
                }

                assignmentForm.setAttribute(
                    'action',
                    normalizePath(
                        button.dataset.updateUrl,
                        `/operation/driver-bus-assignment/${button.dataset.assignmentId}`
                    )
                );

                if (assignmentFormMethod) {
                    assignmentFormMethod.disabled = false;
                    assignmentFormMethod.value = 'PUT';
                }

                if (assignmentTrip) {
                    assignmentTrip.value =
                        button.dataset.tripId || '';

                    assignmentTrip.disabled = true;
                    assignmentTrip.required = false;
                }

                if (assignmentDriver) {
                    assignmentDriver.value =
                        button.dataset.driverId || '';
                }

                if (assignmentBus) {
                    assignmentBus.value =
                        button.dataset.busId || '';
                }

                if (assignmentModalTitle) {
                    assignmentModalTitle.textContent =
                        'Edit Assignment';
                }

                if (assignmentSubmitText) {
                    assignmentSubmitText.textContent =
                        'Update Assignment';
                }

                openModal(assignmentModal);
            });
        });

    const viewAssignmentContent =
        document.getElementById('viewAssignmentContent');

    document
        .querySelectorAll('.view-assignment')
        .forEach((button) => {
            button.addEventListener('click', () => {
                let details = {};

                try {
                    details = JSON.parse(
                        button.dataset.details || '{}'
                    );
                } catch (error) {
                    console.error(
                        'Unable to read assignment details.',
                        error
                    );
                }

                const fields = [
                    ['Trip ID', details.tripCode],
                    ['Date', details.date],
                    ['Route', details.route],
                    ['Trip Status', details.status],
                    ['Departure', details.departure],
                    ['Estimated Arrival', details.arrival],
                    ['Driver', details.driver || 'Not Assigned'],
                    ['Driver Attendance', details.driverStatus || '—'],
                    ['Bus', details.bus || 'Not Assigned'],
                    ['Assignment', details.assignmentStatus],
                ];

                if (viewAssignmentContent) {
                    viewAssignmentContent.innerHTML = fields
                        .map(([label, value]) => `
                            <div class="assignment-detail-card">
                                <label>${escapeHtml(label)}</label>
                                <div class="assignment-detail-value">
                                    ${escapeHtml(value || '—')}
                                </div>
                            </div>
                        `)
                        .join('');
                }

                openModal(viewAssignmentModal);
            });
        });

    [
        document.getElementById('closeViewAssignmentModal'),
        document.getElementById('closeViewAssignmentButton'),
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(viewAssignmentModal);
            });
        });

    const removeAssignmentName =
        document.getElementById('removeAssignmentName');

    document
        .querySelectorAll('.remove-assignment')
        .forEach((button) => {
            button.addEventListener('click', () => {
                selectedRemoveForm =
                    document.getElementById(
                        button.dataset.formId
                    );

                if (removeAssignmentName) {
                    removeAssignmentName.textContent =
                        button.dataset.tripCode || 'this trip';
                }

                openModal(removeAssignmentModal);
            });
        });

    document
        .getElementById('cancelRemoveAssignment')
        ?.addEventListener('click', () => {
            selectedRemoveForm = null;
            closeModal(removeAssignmentModal);
        });

    document
        .getElementById('confirmRemoveAssignment')
        ?.addEventListener('click', () => {
            selectedRemoveForm?.requestSubmit();
        });

    document
        .querySelectorAll(
            '.ui-form-overlay, .delete-modal-overlay'
        )
        .forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeModal(assignmentModal);
        closeModal(viewAssignmentModal);
        closeModal(removeAssignmentModal);
    });

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
