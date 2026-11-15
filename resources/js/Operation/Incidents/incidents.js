document.addEventListener('DOMContentLoaded', () => {
    initSearchableCombos();
    initTripPrefill();
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