
window.GCTPartialNavigation.registerInitializer('operation-auto-calendar', '.auto-scheduling-page', () => {
    const root = document.getElementById('autoSchedulingCalendar');
    const dateInput = document.getElementById('autoScheduleDate');
    const routeInput = document.getElementById('autoScheduleRoute');
    if (!root || !dateInput || root.dataset.calendarInitialized === 'true') return;
    root.dataset.calendarInitialized = 'true';

    const find = (selector) => root.querySelector(selector);
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
    }).formatToParts(new Date());
    const part = (type) => parts.find(item => item.type === type)?.value || '';
    const today = part('year') + '-' + part('month') + '-' + part('day');
    let selected = /^\d{4}-\d{2}-\d{2}$/.test(dateInput.value) ? dateInput.value : today;
    let month = selected.slice(0, 7);
    let counts = new Map();
    let currentRequest;

    const formatDate = (iso, options) => new Intl.DateTimeFormat('en-US', {
        ...options, timeZone: 'UTC'
    }).format(new Date(iso + 'T12:00:00Z'));

    const showDetails = () => {
        const row = counts.get(selected) || { total: 0, pending: 0, assigned: 0 };
        find('[data-calendar-selected-date]').textContent = formatDate(selected, {
            month: 'short', day: 'numeric', year: 'numeric'
        });
        find('[data-calendar-total]').textContent = row.total;
        find('[data-calendar-pending]').textContent = row.pending;
        find('[data-calendar-assigned]').textContent = row.assigned;
        const percent = row.total ? Math.round(row.assigned / row.total * 100) : 0;
        find('[data-calendar-progress-label]').textContent = percent + '% Assigned';
        find('[data-calendar-progress]').setAttribute('aria-valuenow', String(percent));
        find('[data-calendar-progress-fill]').style.width = percent + '%';
    };

    const draw = () => {
        find('[data-calendar-month]').textContent = formatDate(month + '-01', {
            month: 'long', year: 'numeric'
        });
        const container = find('[data-calendar-days]');
        const start = new Date(month + '-01T12:00:00Z');
        const year = start.getUTCFullYear();
        const index = start.getUTCMonth();
        const count = new Date(Date.UTC(year, index + 1, 0)).getUTCDate();
        const frag = document.createDocumentFragment();

        for (let i = 0; i < start.getUTCDay(); i++) {
            const blank = document.createElement('span');
            blank.className = 'auto-calendar-empty';
            blank.setAttribute('aria-hidden', 'true');
            frag.appendChild(blank);
        }
        for (let i = 1; i <= count; i++) {
            const date = month + '-' + String(i).padStart(2, '0');
            const row = counts.get(date) || { total: 0, pending: 0, assigned: 0 };
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'auto-calendar-day';
            if (date === selected) button.classList.add('is-selected');
            if (date === today) button.classList.add('is-today');
            const number = document.createElement('span');
            number.className = 'auto-calendar-day-number';
            number.textContent = String(i);
            const status = document.createElement('span');
            status.className = 'auto-calendar-day-status ' + (row.pending ? 'is-pending' : row.total ? 'is-assigned' : 'is-none');
            if (row.total) {
                const dot = document.createElement('i');
                dot.className = 'auto-calendar-dot ' + (row.pending ? 'is-pending' : 'is-assigned');
                dot.setAttribute('aria-hidden', 'true');
                status.appendChild(dot);
            }
            status.appendChild(document.createTextNode(row.total ? String(row.total) : '—'));
            button.setAttribute('aria-label', formatDate(date, {
                weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
            }) + ': ' + row.total + ' total, ' + row.pending + ' need scheduling, ' + row.assigned + ' assigned');
            button.setAttribute('aria-pressed', String(date === selected));
            button.append(number, status);
            button.addEventListener('click', () => {
                dateInput.value = date;
                selected = date;
                draw();
                dateInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
            frag.appendChild(button);
        }
        container.replaceChildren(frag);
        showDetails();
    };

    const load = async () => {
        currentRequest?.abort();
        const request = new AbortController();
        currentRequest = request;
        const requestedMonth = month;
        find('[data-calendar-feedback]').hidden = true;
        try {
            const url = new URL(root.dataset.calendarUrl, location.origin);
            url.searchParams.set('month', requestedMonth);
            url.searchParams.set('shuttle_route_id', routeInput?.value || 'all');
            const response = await fetch(url, {
                signal: request.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) throw new Error('Calendar request failed');
            const data = await response.json();
            if (request.signal.aborted || requestedMonth !== month || !root.isConnected) return;
            counts = new Map((Array.isArray(data.days) ? data.days : []).map(day => [
                day.date, {
                    total: Number(day.total) || 0,
                    pending: Number(day.pending) || 0,
                    assigned: Number(day.assigned) || 0
                }
            ]));
            draw();
        } catch (error) {
            if (error.name === 'AbortError') return;
            counts = new Map();
            draw();
            const feedback = find('[data-calendar-feedback]');
            feedback.textContent = 'Trip counts are temporarily unavailable. Schedule Date still works.';
            feedback.hidden = false;
        }
    };

    const navigate = (offset) => {
        const next = new Date(month + '-01T12:00:00Z');
        next.setUTCMonth(next.getUTCMonth() + offset);
        month = next.toISOString().slice(0, 7);
        void load();
    };
    find('[data-calendar-prev]')?.addEventListener('click', () => navigate(-1));
    find('[data-calendar-next]')?.addEventListener('click', () => navigate(1));
    find('[data-calendar-today]')?.addEventListener('click', () => {
        selected = today;
        month = today.slice(0, 7);
        dateInput.value = today;
        dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        void load();
    });
    const toggle = find('[data-calendar-toggle]');
    toggle?.addEventListener('click', () => {
        const body = find('#autoCalendarBody');
        body.hidden = !body.hidden;
        root.classList.toggle('is-collapsed', body.hidden);
        toggle.setAttribute('aria-expanded', String(!body.hidden));
        toggle.querySelector('span').textContent = body.hidden ? 'Show calendar' : 'Hide calendar';
        toggle.querySelector('i').className = body.hidden ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-up';
    });
    dateInput.addEventListener('change', () => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(dateInput.value)) return;
        selected = dateInput.value;
        if (selected.slice(0, 7) !== month) {
            month = selected.slice(0, 7);
            void load();
        } else {
            draw();
        }
    });
    routeInput?.addEventListener('change', () => void load());
    void load();
});
