document.addEventListener('DOMContentLoaded', () => {
    initSearchableCombos();
    initTripPrefill();
    initIncidentReportModal();
});

/* =========================================================
   SEARCHABLE COMBO SELECT
========================================================= */

function initSearchableCombos() {
    document.querySelectorAll('[data-combo]').forEach((combo) => {
        const input = combo.querySelector('[data-combo-input]');
        const hidden = combo.querySelector('[data-combo-value]');
        const clearBtn = combo.querySelector('[data-combo-clear]');
        const list = combo.querySelector('[data-combo-list]');
        const options = Array.from(list ? list.querySelectorAll('[data-combo-option]') : []);

        const selectedLabel = (value) => {
            const option = options.find((opt) => opt.dataset.value === String(value));
            return option ? option.querySelector('strong').textContent : '';
        };

        const open = () => {
            if (options.length > 0) {
                combo.classList.add('open');
                filter(input.value);
            }
        };

        const close = () => {
            combo.classList.remove('open');
        };

        const filter = (term) => {
            const needle = term.toLowerCase().trim();
            let visibleCount = 0;

            options.forEach((option) => {
                const label = option.querySelector('strong')
                    ? option.querySelector('strong').textContent.toLowerCase()
                    : '';
                const detail = option.querySelector('small')
                    ? option.querySelector('small').textContent.toLowerCase()
                    : '';
                const haystack = [option.dataset.search || '', label, detail].join(' ');

                const show = !needle || haystack.includes(needle);
                option.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            let emptyHint = list.querySelector('[data-combo-empty]');

            if (visibleCount === 0) {
                if (!emptyHint) {
                    emptyHint = document.createElement('li');
                    emptyHint.className = 'is-empty';
                    emptyHint.setAttribute('data-combo-empty', '');
                    emptyHint.textContent = 'No records found.';
                    list.appendChild(emptyHint);
                }
                emptyHint.style.display = '';
            } else if (emptyHint) {
                emptyHint.style.display = 'none';
            }
        };

        const selectOption = (option) => {
            hidden.value = option.dataset.value;
            input.value = selectedLabel(option.dataset.value);
            combo.classList.add('has-value');
            close();
            input.dispatchEvent(new Event('change'));
        };

        input.addEventListener('focus', open);
        input.addEventListener('input', () => {
            if (hidden.value) {
                hidden.value = '';
                combo.classList.remove('has-value');
            }
            open();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const visible = options.find((opt) => opt.style.display !== 'none');
            if (visible) selectOption(visible);
        });

        options.forEach((option) => {
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectOption(option);
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                hidden.value = '';
                input.value = '';
                combo.classList.remove('has-value');
                input.focus();
                open();
            });
        }

        document.addEventListener('click', (event) => {
            if (!combo.contains(event.target)) close();
        });
    });
}

/* =========================================================
   TRIP PREFILL (existing assignment / selected trip)
========================================================= */

function initTripPrefill() {
    const tripSelect = document.querySelector('[data-trip-select]');
    const assignmentInput = document.querySelector('[data-trip-assignment-id]');
    const prefillLink = document.querySelector('[data-prefill-link]');

    if (!tripSelect) return;

    const syncPrefill = () => {
        const selected = tripSelect.options[tripSelect.selectedIndex];
        const assignmentId = selected ? selected.dataset.assignmentId || '' : '';

        if (assignmentInput) {
            assignmentInput.value = assignmentId;
        }

        if (prefillLink) {
            if (assignmentId) {
                prefillLink.href = `?trip_assignment_id=${encodeURIComponent(assignmentId)}`;
                prefillLink.style.display = '';
            } else {
                prefillLink.style.display = 'none';
            }
        }
    };

    tripSelect.addEventListener('change', syncPrefill);
    syncPrefill();
}

/* =========================================================
   INCIDENT REPORT MODAL (listing page)
========================================================= */

function initIncidentReportModal() {
    const modal = document.getElementById('incidentReportModal');
    const trigger = document.getElementById('openIncidentReportModal');
    if (!modal || !trigger) return;

    const form = modal.querySelector('form');
    const closeButtons = [
        document.getElementById('closeIncidentReport'),
        document.getElementById('cancelIncidentReport'),
    ].filter(Boolean);
    const submitButton = document.getElementById('incidentReportSubmit');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    let previousBodyOverflow = '';
    let isSubmitting = false;

    const open = (focusError = false) => {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.classList.add('show', 'active');

        const errorTarget = focusError
            ? modal.querySelector('.ui-field-error')?.closest('.inc-form-group, .ui-form-group')?.querySelector('input, select, textarea')
            : null;

        (errorTarget || modal.querySelector('input[name="trip_schedule_id"]'))?.focus();
    };

    const close = () => {
        modal.classList.remove('show', 'active');
        document.body.style.overflow = previousBodyOverflow;
        trigger.focus();
    };

    trigger.addEventListener('click', () => open());
    closeButtons.forEach((button) => button.addEventListener('click', close));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(modal.querySelectorAll(focusableSelector))
            .filter((element) => element.getClientRects().length > 0);
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    form?.addEventListener('submit', (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) return;

        isSubmitting = true;
        submitButton?.setAttribute('aria-busy', 'true');
        if (submitButton) submitButton.disabled = true;

        const label = submitButton?.querySelector('[data-loading-label]');
        if (label) label.textContent = 'Saving...';
    });

    if (modal.querySelector('.ui-field-error, .inc-modal-alert')) {
        open(true);
    }
}