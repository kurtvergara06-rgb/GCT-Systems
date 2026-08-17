const bindFleetAvailabilityDonut = (card) => {
    const ring = card.querySelector('.fleet-donut');
    const groups = Array.from(card.querySelectorAll('.fleet-donut-group'));
    const rows = Array.from(card.querySelectorAll('.availability-row[data-donut-index]'));
    const centerValue = ring?.querySelector('.fleet-donut-center strong');
    const centerLabel = ring?.querySelector('.fleet-donut-center span');
    const tooltip = ring?.querySelector('.fleet-donut-tooltip');
    const tooltipLabel = tooltip?.querySelector('strong');
    const tooltipMeta = tooltip?.querySelector('span');

    if (!ring || !groups.length || ring.dataset.fleetDonutBound === 'true') {
        return;
    }

    const track = ring.querySelector('.fleet-donut-track');
    if (track) {
        track.setAttribute('fill', 'none');
        track.style.fill = 'none';
    }

    groups.forEach((group) => {
        const percentage = Math.max(0, Math.min(100, Number.parseFloat(group.dataset.percentage || '0') || 0));
        const offset = Math.max(0, Math.min(100, groups
            .slice(0, Number(group.dataset.donutIndex) || 0)
            .reduce((sum, previous) => sum + (Number.parseFloat(previous.dataset.percentage || '0') || 0), 0)));
        const gap = Math.max(0, 100 - percentage);

        group.querySelectorAll('.fleet-donut-segment').forEach((circle) => {
            circle.setAttribute('fill', 'none');
            circle.style.fill = 'none';
            circle.style.strokeDasharray = `${percentage} ${gap}`;
            circle.style.strokeDashoffset = `${-offset}`;
        });
    });

    ring.dataset.fleetDonutBound = 'true';
    ring.classList.add('is-ready');

    const defaultValue = ring.dataset.defaultValue || centerValue?.textContent?.trim() || '0%';
    const defaultLabel = ring.dataset.defaultLabel || centerLabel?.textContent?.trim() || 'Active';

    const showItem = (index) => {
        const group = groups[index];
        if (!group) {
            return;
        }

        const label = group.dataset.label || 'Status';
        const value = Number.parseInt(group.dataset.value || '0', 10) || 0;
        const percentage = Number.parseFloat(group.dataset.percentage || '0') || 0;

        groups.forEach((item, itemIndex) => {
            item.classList.toggle('is-active', itemIndex === index);
            item.classList.toggle('is-muted', itemIndex !== index);
        });

        rows.forEach((row) => {
            row.classList.toggle('is-donut-active', Number(row.dataset.donutIndex) === index);
        });

        if (centerValue) {
            centerValue.textContent = `${percentage.toFixed(1)}%`;
        }
        if (centerLabel) {
            centerLabel.textContent = label;
        }
        if (tooltipLabel) {
            tooltipLabel.textContent = label;
        }
        if (tooltipMeta) {
            tooltipMeta.textContent = `${value} bus${value === 1 ? '' : 'es'} · ${percentage.toFixed(1)}%`;
        }
        tooltip?.classList.add('is-visible');
        tooltip?.setAttribute('aria-hidden', 'false');
    };

    const clearItem = () => {
        groups.forEach((group) => group.classList.remove('is-active', 'is-muted'));
        rows.forEach((row) => row.classList.remove('is-donut-active'));

        if (centerValue) {
            centerValue.textContent = defaultValue;
        }
        if (centerLabel) {
            centerLabel.textContent = defaultLabel;
        }
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
    };

    groups.forEach((group, index) => {
        group.addEventListener('pointerenter', () => showItem(index));
        group.addEventListener('pointerleave', clearItem);
        group.addEventListener('focus', () => showItem(index));
        group.addEventListener('blur', clearItem);
    });

    rows.forEach((row) => {
        const index = Number(row.dataset.donutIndex);
        row.addEventListener('pointerenter', () => showItem(index));
        row.addEventListener('pointerleave', clearItem);
    });
};

const initializeAnalyticsDomainPanels = () => {
    document.querySelectorAll('.analytics-domain-card').forEach((card) => {
        card.classList.add('is-chart-visible');
    });

    document.querySelectorAll('.descriptive-availability-card').forEach(bindFleetAvailabilityDonut);
};

initializeAnalyticsDomainPanels();
document.addEventListener('DOMContentLoaded', initializeAnalyticsDomainPanels);
document.addEventListener('ajax:content-updated', initializeAnalyticsDomainPanels);
