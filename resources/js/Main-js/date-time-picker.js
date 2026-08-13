const GCT_PICKER_SELECTOR = 'input[type="date"], input[type="time"], input[type="datetime-local"]';

function pad(value) {
    return String(value).padStart(2, '0');
}

function localDateValue(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseDateValue(value) {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function parseTimeValue(value) {
    if (!value) {
        return null;
    }

    const [hour, minute] = value.split(':').map(Number);

    if (Number.isNaN(hour) || Number.isNaN(minute)) {
        return null;
    }

    return { hour, minute };
}

function formatDate(value) {
    const date = parseDateValue(value);

    if (!date) {
        return 'Select date';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatTime(value) {
    const parsed = parseTimeValue(value);

    if (!parsed) {
        return 'Select time';
    }

    const displayHour = parsed.hour % 12 || 12;
    const period = parsed.hour >= 12 ? 'PM' : 'AM';

    return `${pad(displayHour)}:${pad(parsed.minute)} ${period}`;
}

function formatDateTime(value) {
    if (!value || !value.includes('T')) {
        return 'Select date & time';
    }

    const [dateValue, timeValue] = value.split('T');
    return `${formatDate(dateValue)} · ${formatTime(timeValue)}`;
}

function dispatchPickerChange(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

class GctDateTimePicker {
    constructor() {
        this.activeInput = null;
        this.activeTrigger = null;
        this.viewDate = new Date();
        this.selectedDate = null;
        this.timeHour = 12;
        this.timeMinute = 0;
        this.timePeriod = 'AM';
        this.popover = this.buildPopover();

        document.body.appendChild(this.popover);
        this.bindGlobalEvents();
        this.enhanceAll();
        this.observeDom();
    }

    buildPopover() {
        const popover = document.createElement('div');
        popover.className = 'gct-picker-popover';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-modal', 'false');
        popover.hidden = true;
        return popover;
    }

    enhanceAll(root = document) {
        root.querySelectorAll?.(GCT_PICKER_SELECTOR).forEach((input) => this.enhance(input));
    }

    enhance(input) {
        if (input.dataset.gctPickerReady === 'true' || input.dataset.nativePicker === 'true') {
            return;
        }

        input.dataset.gctPickerReady = 'true';
        input.classList.add('gct-picker-source');

        const host = input.parentElement;
        host?.classList.add('gct-picker-host');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'gct-picker-trigger';
        trigger.dataset.pickerFor = input.id || input.name || '';
        trigger.innerHTML = `
            <span class="gct-picker-trigger-icon" aria-hidden="true">
                <i class="fa-solid ${input.type === 'time' ? 'fa-clock' : 'fa-calendar-days'}"></i>
            </span>
            <span class="gct-picker-trigger-value"></span>
            <span class="gct-picker-trigger-chevron" aria-hidden="true">
                <i class="fa-solid fa-chevron-down"></i>
            </span>
        `;

        input.insertAdjacentElement('afterend', trigger);
        this.syncTrigger(input, trigger);

        trigger.addEventListener('click', () => this.open(input, trigger));

        input.addEventListener('change', () => this.syncTrigger(input, trigger));
        input.addEventListener('input', () => this.syncTrigger(input, trigger));

        if (input.disabled) {
            trigger.disabled = true;
        }

        const observer = new MutationObserver(() => {
            trigger.disabled = input.disabled;
            this.syncTrigger(input, trigger);
        });

        observer.observe(input, {
            attributes: true,
            attributeFilter: ['disabled', 'value'],
        });
    }

    syncTrigger(input, trigger) {
        const valueNode = trigger.querySelector('.gct-picker-trigger-value');

        if (!valueNode) {
            return;
        }

        if (input.type === 'date') {
            valueNode.textContent = formatDate(input.value);
        } else if (input.type === 'time') {
            valueNode.textContent = formatTime(input.value);
        } else {
            valueNode.textContent = formatDateTime(input.value);
        }

        trigger.classList.toggle('is-empty', !input.value);
        trigger.classList.toggle('is-readonly', input.readOnly);
    }

    open(input, trigger) {
        if (input.disabled || input.readOnly) {
            return;
        }

        this.activeInput = input;
        this.activeTrigger = trigger;

        if (input.type === 'date') {
            this.prepareDateState(input.value);
            this.renderCalendar(false);
        } else if (input.type === 'time') {
            this.prepareTimeState(input.value);
            this.renderTimePicker(false);
        } else {
            const [dateValue = '', timeValue = ''] = (input.value || '').split('T');
            this.prepareDateState(dateValue);
            this.prepareTimeState(timeValue);
            this.renderDateTimePicker();
        }

        this.popover.hidden = false;
        this.positionPopover();
        trigger.setAttribute('aria-expanded', 'true');
    }

    close() {
        if (this.activeTrigger) {
            this.activeTrigger.setAttribute('aria-expanded', 'false');
        }

        this.popover.hidden = true;
        this.popover.innerHTML = '';
        this.activeInput = null;
        this.activeTrigger = null;
    }

    prepareDateState(value) {
        const selected = parseDateValue(value) || new Date();
        this.selectedDate = new Date(selected.getFullYear(), selected.getMonth(), selected.getDate());
        this.viewDate = new Date(selected.getFullYear(), selected.getMonth(), 1);
    }

    prepareTimeState(value) {
        const parsed = parseTimeValue(value);
        const now = new Date();
        const hour24 = parsed?.hour ?? now.getHours();

        this.timeHour = hour24 % 12 || 12;
        this.timeMinute = parsed?.minute ?? now.getMinutes();
        this.timePeriod = hour24 >= 12 ? 'PM' : 'AM';
    }

    renderCalendar(embedded = false) {
        const monthName = new Intl.DateTimeFormat('en-US', { month: 'long' }).format(this.viewDate);
        const year = this.viewDate.getFullYear();
        const month = this.viewDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const gridStart = new Date(year, month, 1 - firstDay.getDay());
        const todayValue = localDateValue(new Date());
        const selectedValue = this.selectedDate ? localDateValue(this.selectedDate) : '';
        const minValue = this.activeInput?.min || '';
        const maxValue = this.activeInput?.max || '';

        const cells = [];

        for (let index = 0; index < 42; index += 1) {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const value = localDateValue(date);
            const outside = date.getMonth() !== month;
            const disabled = (minValue && value < minValue) || (maxValue && value > maxValue);
            const classes = [
                'gct-calendar-day',
                outside ? 'is-outside' : '',
                value === todayValue ? 'is-today' : '',
                value === selectedValue ? 'is-selected' : '',
            ].filter(Boolean).join(' ');

            cells.push(`
                <button
                    type="button"
                    class="${classes}"
                    data-date="${value}"
                    ${disabled ? 'disabled' : ''}
                    aria-label="${date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}"
                >${date.getDate()}</button>
            `);
        }

        const calendarHtml = `
            <div class="gct-calendar-panel">
                <div class="gct-picker-heading">
                    <div>
                        <strong>${embedded ? 'Select date' : 'Choose a date'}</strong>
                        <span>${embedded ? 'Pick the date first, then set the time.' : 'Select a date from the calendar.'}</span>
                    </div>
                    ${embedded ? '' : '<button type="button" class="gct-picker-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>'}
                </div>

                <div class="gct-calendar-nav">
                    <button type="button" class="gct-calendar-nav-btn" data-nav="prev" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="gct-calendar-current" aria-label="Current month">${monthName} ${year}</button>
                    <button type="button" class="gct-calendar-nav-btn" data-nav="next" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
                </div>

                <div class="gct-calendar-weekdays">
                    ${['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((day) => `<span>${day}</span>`).join('')}
                </div>

                <div class="gct-calendar-grid">${cells.join('')}</div>

                ${embedded ? '' : `
                    <div class="gct-picker-footer">
                        <button type="button" class="gct-picker-secondary" data-action="today">Today</button>
                    </div>
                `}
            </div>
        `;

        if (embedded) {
            return calendarHtml;
        }

        this.popover.innerHTML = calendarHtml;
        this.bindCalendarEvents(false);
    }

    bindCalendarEvents(embedded) {
        this.popover.querySelector('.gct-picker-close')?.addEventListener('click', () => this.close());

        this.popover.querySelectorAll('[data-nav]').forEach((button) => {
            button.addEventListener('click', () => {
                this.viewDate.setMonth(this.viewDate.getMonth() + (button.dataset.nav === 'next' ? 1 : -1));

                if (embedded) {
                    this.renderDateTimePicker();
                } else {
                    this.renderCalendar(false);
                }
            });
        });

        this.popover.querySelectorAll('[data-date]').forEach((button) => {
            button.addEventListener('click', () => {
                const date = parseDateValue(button.dataset.date);

                if (!date) {
                    return;
                }

                this.selectedDate = date;

                if (embedded) {
                    this.renderDateTimePicker();
                    return;
                }

                this.activeInput.value = button.dataset.date;
                dispatchPickerChange(this.activeInput);
                this.close();
            });
        });

        this.popover.querySelector('[data-action="today"]')?.addEventListener('click', () => {
            const today = localDateValue(new Date());
            this.activeInput.value = today;
            dispatchPickerChange(this.activeInput);
            this.close();
        });
    }

    timeControlsHtml() {
        const minutes = Array.from({ length: 60 }, (_, index) => index);

        return `
            <div class="gct-time-controls">
                <div class="gct-time-column">
                    <label>Hour</label>
                    <select class="gct-time-select" data-time-part="hour">
                        ${Array.from({ length: 12 }, (_, index) => index + 1)
                            .map((hour) => `<option value="${hour}" ${hour === this.timeHour ? 'selected' : ''}>${pad(hour)}</option>`)
                            .join('')}
                    </select>
                </div>

                <span class="gct-time-separator">:</span>

                <div class="gct-time-column">
                    <label>Minute</label>
                    <select class="gct-time-select" data-time-part="minute">
                        ${minutes
                            .map((minute) => `<option value="${minute}" ${minute === this.timeMinute ? 'selected' : ''}>${pad(minute)}</option>`)
                            .join('')}
                    </select>
                </div>

                <div class="gct-period-toggle" role="group" aria-label="AM or PM">
                    <button type="button" data-period="AM" class="${this.timePeriod === 'AM' ? 'is-active' : ''}">AM</button>
                    <button type="button" data-period="PM" class="${this.timePeriod === 'PM' ? 'is-active' : ''}">PM</button>
                </div>
            </div>
        `;
    }

    bindTimeEvents(onApply) {
        this.popover.querySelector('[data-time-part="hour"]')?.addEventListener('change', (event) => {
            this.timeHour = Number(event.target.value);
        });

        this.popover.querySelector('[data-time-part="minute"]')?.addEventListener('change', (event) => {
            this.timeMinute = Number(event.target.value);
        });

        this.popover.querySelectorAll('[data-period]').forEach((button) => {
            button.addEventListener('click', () => {
                this.timePeriod = button.dataset.period;
                this.popover.querySelectorAll('[data-period]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });
            });
        });

        this.popover.querySelector('[data-action="apply-time"]')?.addEventListener('click', onApply);
    }

    resolvedTimeValue() {
        let hour24 = this.timeHour % 12;

        if (this.timePeriod === 'PM') {
            hour24 += 12;
        }

        return `${pad(hour24)}:${pad(this.timeMinute)}`;
    }

    renderTimePicker(embedded = false) {
        const timeHtml = `
            <div class="gct-time-panel">
                <div class="gct-picker-heading">
                    <div>
                        <strong>${embedded ? 'Select time' : 'Choose a time'}</strong>
                        <span>${embedded ? 'Set the time for the selected date.' : 'Choose the hour, minute, and period.'}</span>
                    </div>
                    ${embedded ? '' : '<button type="button" class="gct-picker-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>'}
                </div>

                <div class="gct-time-preview">
                    <i class="fa-regular fa-clock"></i>
                    <span>${pad(this.timeHour)}:${pad(this.timeMinute)}</span>
                    <strong>${this.timePeriod}</strong>
                </div>

                ${this.timeControlsHtml()}

                ${embedded ? '' : `
                    <div class="gct-picker-footer">
                        <button type="button" class="gct-picker-secondary gct-picker-close-secondary">Cancel</button>
                        <button type="button" class="gct-picker-primary" data-action="apply-time"><i class="fa-solid fa-check"></i> Set Time</button>
                    </div>
                `}
            </div>
        `;

        if (embedded) {
            return timeHtml;
        }

        this.popover.innerHTML = timeHtml;
        this.popover.querySelector('.gct-picker-close')?.addEventListener('click', () => this.close());
        this.popover.querySelector('.gct-picker-close-secondary')?.addEventListener('click', () => this.close());

        this.bindTimeEvents(() => {
            this.activeInput.value = this.resolvedTimeValue();
            dispatchPickerChange(this.activeInput);
            this.close();
        });
    }

    renderDateTimePicker() {
        this.popover.innerHTML = `
            <div class="gct-datetime-panel">
                <div class="gct-picker-heading gct-datetime-heading">
                    <div>
                        <strong>Choose date & time</strong>
                        <span>Set both values in one place.</span>
                    </div>
                    <button type="button" class="gct-picker-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div class="gct-datetime-layout">
                    ${this.renderCalendar(true)}
                    ${this.renderTimePicker(true)}
                </div>

                <div class="gct-picker-footer">
                    <button type="button" class="gct-picker-secondary gct-picker-close-secondary">Cancel</button>
                    <button type="button" class="gct-picker-primary" data-action="apply-datetime"><i class="fa-solid fa-check"></i> Apply</button>
                </div>
            </div>
        `;

        this.popover.querySelector('.gct-picker-close')?.addEventListener('click', () => this.close());
        this.popover.querySelector('.gct-picker-close-secondary')?.addEventListener('click', () => this.close());
        this.bindCalendarEvents(true);
        this.bindTimeEvents(() => {});

        this.popover.querySelector('[data-action="apply-datetime"]')?.addEventListener('click', () => {
            const dateValue = localDateValue(this.selectedDate || new Date());
            this.activeInput.value = `${dateValue}T${this.resolvedTimeValue()}`;
            dispatchPickerChange(this.activeInput);
            this.close();
        });
    }

    positionPopover() {
        if (!this.activeTrigger || this.popover.hidden) {
            return;
        }

        const rect = this.activeTrigger.getBoundingClientRect();
        const margin = 10;
        const maxLeft = Math.max(margin, window.innerWidth - this.popover.offsetWidth - margin);
        let left = Math.min(Math.max(rect.left, margin), maxLeft);
        let top = rect.bottom + 8;

        if (top + this.popover.offsetHeight > window.innerHeight - margin) {
            top = Math.max(margin, rect.top - this.popover.offsetHeight - 8);
        }

        this.popover.style.left = `${left}px`;
        this.popover.style.top = `${top}px`;
    }

    bindGlobalEvents() {
        document.addEventListener('mousedown', (event) => {
            if (this.popover.hidden) {
                return;
            }

            if (this.popover.contains(event.target) || this.activeTrigger?.contains(event.target)) {
                return;
            }

            this.close();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !this.popover.hidden) {
                this.close();
            }
        });

        window.addEventListener('resize', () => this.positionPopover());
        window.addEventListener('scroll', () => this.positionPopover(), true);
    }

    observeDom() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    if (node.matches?.(GCT_PICKER_SELECTOR)) {
                        this.enhance(node);
                    }

                    this.enhanceAll(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }
}

function bootGctDateTimePicker() {
    if (document.body.dataset.gctDateTimePickerReady === 'true') {
        return;
    }

    document.body.dataset.gctDateTimePickerReady = 'true';
    new GctDateTimePicker();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootGctDateTimePicker);
} else {
    bootGctDateTimePicker();
}
