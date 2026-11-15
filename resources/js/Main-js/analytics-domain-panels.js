const DONUT_COLORS = ['#16a34a', '#f59e0b', '#94a3b8'];

const buildFleetAvailabilityDonut = (card) => {
    const ring = card.querySelector('.availability-ring');
    const rows = Array.from(card.querySelectorAll('.availability-row')).slice(0, 3);

    if (!ring || rows.length < 3 || ring.dataset.fleetDonutBound === 'true') {
        return;
    }

    const items = rows.map((row, index) => {
        const label = row.querySelector('span:not(.availability-dot)')?.textContent?.trim() || `Status ${index + 1}`;
        const value = Number.parseInt(row.querySelector('strong')?.textContent?.trim() || '0', 10) || 0;

        return {
            label,
            value,
            color: DONUT_COLORS[index],
            row,
        };
    });

    const total = items.reduce((sum, item) => sum + item.value, 0);
    if (total <= 0) {
        return;
    }

    const defaultValue = ring.querySelector('strong')?.textContent?.trim() || '0%';
    const defaultLabel = ring.querySelector('span')?.textContent?.trim() || 'Active';
    const svgNamespace = 'http://www.w3.org/2000/svg';

    ring.dataset.fleetDonutBound = 'true';
    ring.classList.add('is-interactive-donut');
    ring.replaceChildren();

    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.setAttribute('viewBox', '0 0 180 180');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Fleet availability status distribution');
    svg.classList.add('fleet-donut-svg');

    const track = document.createElementNS(svgNamespace, 'circle');
    track.setAttribute('cx', '90');
    track.setAttribute('cy', '90');
    track.setAttribute('r', '59');
    track.setAttribute('pathLength', '100');
    track.classList.add('fleet-donut-track');
    svg.appendChild(track);

    const center = document.createElement('div');
    center.className = 'availability-ring-center fleet-donut-center';
    const centerValue = document.createElement('strong');
    centerValue.textContent = defaultValue;
    const centerLabel = document.createElement('span');
    centerLabel.textContent = defaultLabel;
    center.append(centerValue, centerLabel);

    const tooltip = document.createElement('div');
    tooltip.className = 'fleet-donut-tooltip';
    const tooltipLabel = document.createElement('strong');
    const tooltipMeta = document.createElement('span');
    tooltip.append(tooltipLabel, tooltipMeta);

    const groups = [];
    let offset = 0;

    const showItem = (index) => {
        const item = items[index];
        if (!item) {
            return;
        }

        const percentage = (item.value / total) * 100;
        groups.forEach((group, groupIndex) => {
            group.classList.toggle('is-active', groupIndex === index);
            group.classList.toggle('is-muted', groupIndex !== index);
        });
        rows.forEach((row, rowIndex) => row.classList.toggle('is-donut-active', rowIndex === index));

        centerValue.textContent = `${percentage.toFixed(1)}%`;
        centerLabel.textContent = item.label;
        tooltipLabel.textContent = item.label;
        tooltipMeta.textContent = `${item.value} bus${item.value === 1 ? '' : 'es'} · ${percentage.toFixed(1)}%`;
        tooltip.classList.add('is-visible');
    };

    const clearItem = () => {
        groups.forEach((group) => group.classList.remove('is-active', 'is-muted'));
        rows.forEach((row) => row.classList.remove('is-donut-active'));
        centerValue.textContent = defaultValue;
        centerLabel.textContent = defaultLabel;
        tooltip.classList.remove('is-visible');
    };

    items.forEach((item, index) => {
        const percentage = (item.value / total) * 100;
        const group = document.createElementNS(svgNamespace, 'g');
        group.classList.add('fleet-donut-group');
        group.setAttribute('tabindex', '0');
        group.setAttribute('role', 'button');
        group.setAttribute('aria-label', `${item.label}: ${item.value} buses, ${percentage.toFixed(1)} percent`);

        const innerArc = document.createElementNS(svgNamespace, 'circle');
        innerArc.setAttribute('cx', '90');
        innerArc.setAttribute('cy', '90');
        innerArc.setAttribute('r', '59');
        innerArc.setAttribute('pathLength', '100');
        innerArc.setAttribute('transform', 'rotate(-90 90 90)');
        innerArc.style.stroke = item.color;
        innerArc.style.strokeDashoffset = String(-offset);
        innerArc.style.setProperty('--segment-length', '0');
        innerArc.dataset.segmentLength = percentage.toFixed(4);
        innerArc.classList.add('fleet-donut-segment', 'fleet-donut-segment-main');

        const outerArc = document.createElementNS(svgNamespace, 'circle');
        outerArc.setAttribute('cx', '90');
        outerArc.setAttribute('cy', '90');
        outerArc.setAttribute('r', '75');
        outerArc.setAttribute('pathLength', '100');
        outerArc.setAttribute('transform', 'rotate(-90 90 90)');
        outerArc.style.stroke = item.color;
        outerArc.style.strokeDashoffset = String(-offset);
        outerArc.style.setProperty('--segment-length', '0');
        outerArc.dataset.segmentLength = percentage.toFixed(4);
        outerArc.classList.add('fleet-donut-segment', 'fleet-donut-segment-outer');

        group.append(innerArc, outerArc);
        group.addEventListener('pointerenter', () => showItem(index));
        group.addEventListener('pointerleave', clearItem);
        group.addEventListener('focus', () => showItem(index));
        group.addEventListener('blur', clearItem);
        svg.appendChild(group);
        groups.push(group);

        item.row.addEventListener('pointerenter', () => showItem(index));
        item.row.addEventListener('pointerleave', clearItem);

        offset += percentage;
    });

    ring.append(svg, center, tooltip);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            ring.querySelectorAll('.fleet-donut-segment').forEach((segment) => {
                segment.style.setProperty('--segment-length', segment.dataset.segmentLength || '0');
            });
        });
    });
};

const initializeAnalyticsDomainPanels = () => {
    document.querySelectorAll('.analytics-domain-card').forEach((card) => {
        card.classList.add('is-chart-visible');
    });

    document.querySelectorAll('.descriptive-availability-card').forEach(buildFleetAvailabilityDonut);
};

initializeAnalyticsDomainPanels();
document.addEventListener('DOMContentLoaded', initializeAnalyticsDomainPanels);
document.addEventListener('ajax:content-updated', initializeAnalyticsDomainPanels);
