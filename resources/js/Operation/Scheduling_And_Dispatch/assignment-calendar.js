// Read-only monthly assignment status date picker. No assignment mutations.
window.GCTPartialNavigation.registerInitializer('operation-assignment-calendar', '.assignment-page', () => {
    const root = document.querySelector('[data-assignment-calendar]');
    if (!root || root.dataset.assignmentCalendarReady === 'true') return;
    root.dataset.assignmentCalendarReady = 'true';
    const find = (selector) => root.querySelector(selector);
    const trigger = find('[data-assignment-calendar-trigger]');
    const panel = find('[data-assignment-calendar-panel]');
    const monthHeading = find('[data-assignment-calendar-month]');
    const days = find('[data-assignment-calendar-days]');
    const feedback = find('[data-assignment-calendar-feedback]');
    const parts = (root.dataset.selectedDate || '').split('-').map(Number);
    const view = new Date(parts[0] || new Date().getFullYear(), (parts[1] || 1) - 1, 1);
    const cache = new Map();
    const events = new AbortController();
    let pendingFetch = null;

    const iso = (y, m, d) => String(y) + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    const monthKey = () => iso(view.getFullYear(), view.getMonth() + 1, 1).slice(0, 7);
    const today = () => {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
        }).formatToParts(new Date());
        const p = (type) => parts.find(item => item.type === type)?.value || '';
        return iso(p('year'), p('month'), p('day'));
    };
    const goToDate = (date) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
        const url = new URL(location.href);
        url.searchParams.set('trip_date', date);
        for (const key of ['trip_page', 'driver_page', 'bus_page', 'page', 'history_page']) {
            url.searchParams.delete(key);
        }
        location.assign(url.toString());
    };

    const render = () => {
        monthHeading.textContent = new Intl.DateTimeFormat('en-US', {
            month: 'long', year: 'numeric'
        }).format(view);
        days.replaceChildren();
        const data = cache.get(monthKey()) || {};
        const year = view.getFullYear(), month = view.getMonth();
        for (let i = 0; i < new Date(year, month, 1).getDay(); i++) {
            const blank = document.createElement('span');
            blank.className = 'assignment-calendar-empty';
            blank.setAttribute('aria-hidden', 'true');
            days.appendChild(blank);
        }
        const labels = {
            unassigned: 'Unassigned', partial: 'Partially Assigned', assigned: 'Fully Assigned',
            in_progress: 'In Progress', pending_ddr: 'Pending DDR',
            missed: 'Missed', history: 'Completed / Canceled',
            delayed: 'Delayed'
        };
        const fragment = document.createDocumentFragment();
        for (let day = 1; day <= new Date(year, month + 1, 0).getDate(); day++) {
            const date = iso(year, month + 1, day);
            const details = data[date] || {};
            const statuses = details.statuses || {};
            const total = Number(details.total) || 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'assignment-calendar-day';
            if (total) button.classList.add('has-trips');
            if (date === root.dataset.selectedDate) {
                button.classList.add('is-selected');
                button.setAttribute('aria-current', 'date');
            }
            if (date === today()) button.classList.add('is-today');
            const number = document.createElement('span');
            number.textContent = String(day);
            button.appendChild(number);
            const markers = document.createElement('span');
            markers.className = 'assignment-calendar-dots';
            markers.setAttribute('aria-hidden', 'true');
            const descriptions = [];
            for (const [status, label] of Object.entries(labels)) {
                const count = Number(statuses[status]) || 0;
                if (!count) continue;
                const dot = document.createElement('i');
                dot.className = 'assignment-calendar-dot is-' + status;
                markers.appendChild(dot);
                descriptions.push(count + ' ' + label);
            }
            button.appendChild(markers);
            button.setAttribute('aria-label', date + ': ' + total + ' trips' +
                (descriptions.length ? ', ' + descriptions.join(', ') : ''));
            button.addEventListener('click', () => goToDate(date));
            fragment.appendChild(button);
        }
        days.appendChild(fragment);
    };

    const load = async () => {
        pendingFetch?.abort();
        const request = new AbortController();
        pendingFetch = request;
        const key = monthKey();
        if (cache.has(key)) { feedback.textContent = ''; render(); return; }
        feedback.textContent = 'Loading assignment dates...';
        try {
            const url = new URL(root.dataset.calendarUrl, location.origin);
            url.searchParams.set('month', key);
            const response = await fetch(url, {
                signal: request.signal, credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            if (!response.ok) throw new Error('Assignment dates unavailable');
            const data = await response.json();
            if (request.signal.aborted || !root.isConnected || monthKey() !== key) return;
            cache.set(key, data.dates || {});
            feedback.textContent = '';
            render();
        } catch (error) {
            if (error.name === 'AbortError') return;
            feedback.textContent = 'Status indicators unavailable. You may still select a date.';
            if (monthKey() === key) render();
        }
    };

    const close = () => {
        panel.hidden = true;
        trigger?.setAttribute('aria-expanded', 'false');
    };
    trigger?.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
        if (open) void load();
    });
    find('[data-assignment-calendar-close]')?.addEventListener('click', close);
    for (const [selector, offset] of [['[data-assignment-calendar-prev]', -1], ['[data-assignment-calendar-next]', 1]]) {
        find(selector)?.addEventListener('click', () => {
            view.setMonth(view.getMonth() + offset, 1);
            render();
            void load();
        });
    }
    find('[data-assignment-calendar-today]')?.addEventListener('click', () => goToDate(today()));
    document.addEventListener('click', event => {
        if (!root.contains(event.target)) close();
    }, { signal: events.signal });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            trigger?.focus();
        }
    }, { signal: events.signal });
    window.addEventListener('gct:navigation-before', () => {
        events.abort();
        pendingFetch?.abort();
    }, { once: true });
});
