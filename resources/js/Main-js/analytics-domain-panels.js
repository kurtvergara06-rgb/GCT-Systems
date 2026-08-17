const bindFleetAvailabilityDonut = (card) => {
    const ring = card.querySelector('.fleet-donut');
    const groups = Array.from(card.querySelectorAll('.fleet-donut-group'));
    const rows = Array.from(card.querySelectorAll('.availability-row[data-donut-index]'));
    const center = ring?.querySelector('.fleet-donut-center');
    const centerValue = center?.querySelector('strong');
    const centerLabel = center?.querySelector('span');
    const tooltip = ring?.querySelector('.fleet-donut-tooltip');
    const tooltipLabel = tooltip?.querySelector('strong');
    const tooltipMeta = tooltip?.querySelector('span');
    const svg = ring?.querySelector('.fleet-donut-svg');

    if (!ring || !groups.length || ring.dataset.fleetDonutBound === 'true') {
        return;
    }

    const segments = groups.map((group) => {
        const percentage = Math.max(0, Math.min(100, Number.parseFloat(group.dataset.percentage || '0') || 0));
        const main = group.querySelector('.fleet-donut-segment-main');

        return {
            group,
            percentage,
            label: group.dataset.label || 'Status',
            value: Number.parseInt(group.dataset.value || '0', 10) || 0,
            color: main?.style.stroke || main?.getAttribute('stroke') || '#16a34a',
        };
    });

    let offset = 0;
    const stops = segments.map((segment) => {
        const start = offset;
        offset += segment.percentage;
        return `${segment.color} ${start.toFixed(2)}% ${offset.toFixed(2)}%`;
    });

    ring.style.background = `conic-gradient(from -90deg, ${stops.join(', ')})`;
    ring.style.opacity = '1';
    ring.style.visibility = 'visible';
    ring.style.overflow = 'visible';
    ring.style.boxShadow = '0 0 0 1px rgba(148, 163, 184, .08)';

    if (svg) {
        svg.style.display = 'none';
        svg.setAttribute('aria-hidden', 'true');
    }

    if (center) {
        center.style.position = 'absolute';
        center.style.left = '50%';
        center.style.top = '50%';
        center.style.transform = 'translate(-50%, -50%)';
        center.style.width = '112px';
        center.style.height = '112px';
        center.style.borderRadius = '50%';
        center.style.display = 'grid';
        center.style.placeContent = 'center';
        center.style.background = '#ffffff';
        center.style.textAlign = 'center';
        center.style.zIndex = '3';
        center.style.boxShadow = 'inset 0 0 0 1px rgba(226, 232, 240, .8)';
    }

    ring.dataset.fleetDonutBound = 'true';
    ring.classList.add('is-ready');

    const defaultValue = ring.dataset.defaultValue || centerValue?.textContent?.trim() || '0%';
    const defaultLabel = ring.dataset.defaultLabel || centerLabel?.textContent?.trim() || 'Active';

    const showItem = (index) => {
        const segment = segments[index];
        if (!segment) {
            return;
        }

        rows.forEach((row) => {
            row.classList.toggle('is-donut-active', Number(row.dataset.donutIndex) === index);
        });

        if (centerValue) {
            centerValue.textContent = `${segment.percentage.toFixed(1)}%`;
        }
        if (centerLabel) {
            centerLabel.textContent = segment.label;
        }
        if (tooltipLabel) {
            tooltipLabel.textContent = segment.label;
        }
        if (tooltipMeta) {
            tooltipMeta.textContent = `${segment.value} bus${segment.value === 1 ? '' : 'es'} · ${segment.percentage.toFixed(1)}%`;
        }

        ring.style.transform = 'scale(1.025)';
        ring.style.transition = 'transform .18s ease, filter .18s ease';
        ring.style.filter = 'drop-shadow(0 6px 12px rgba(15, 35, 71, .10))';

        tooltip?.classList.add('is-visible');
        tooltip?.setAttribute('aria-hidden', 'false');
    };

    const clearItem = () => {
        rows.forEach((row) => row.classList.remove('is-donut-active'));

        if (centerValue) {
            centerValue.textContent = defaultValue;
        }
        if (centerLabel) {
            centerLabel.textContent = defaultLabel;
        }

        ring.style.transform = 'scale(1)';
        ring.style.filter = 'none';

        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
    };

    rows.forEach((row) => {
        const index = Number(row.dataset.donutIndex);
        row.addEventListener('pointerenter', () => showItem(index));
        row.addEventListener('pointerleave', clearItem);
        row.addEventListener('focus', () => showItem(index));
        row.addEventListener('blur', clearItem);
        row.setAttribute('tabindex', '0');
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
