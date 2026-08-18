const parseTripPoints = (chart) => {
    try {
        return JSON.parse(chart.dataset.tripPoints || '[]').map((point) => ({
            label: String(point.label || ''),
            value: Number(point.value || 0),
            partial: Boolean(point.partial),
        }));
    } catch {
        return [];
    }
};

const parseFuelPoints = (chart) => {
    try {
        return JSON.parse(chart.dataset.fuelPoints || '[]').map((point) => ({
            label: String(point.label || ''),
            fuel: Number(point.fuel || 0),
            distance: Number(point.distance || 0),
            efficiency: Number(point.efficiency || 0),
            entries: Number(point.entries || 0),
        }));
    } catch {
        return [];
    }
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const drawSmoothCurve = (context, points) => {
    if (points.length < 2) return;

    context.beginPath();
    context.moveTo(points[0].x, points[0].y);

    for (let index = 0; index < points.length - 1; index += 1) {
        const previous = points[index - 1] || points[index];
        const current = points[index];
        const next = points[index + 1];
        const following = points[index + 2] || next;
        const tension = 0.16;
        const minY = Math.min(current.y, next.y);
        const maxY = Math.max(current.y, next.y);
        const control1X = current.x + ((next.x - previous.x) * tension);
        const control1Y = clamp(current.y + ((next.y - previous.y) * tension), minY, maxY);
        const control2X = next.x - ((following.x - current.x) * tension);
        const control2Y = clamp(next.y - ((following.y - current.y) * tension), minY, maxY);
        context.bezierCurveTo(control1X, control1Y, control2X, control2Y, next.x, next.y);
    }
};

const bindTripCanvasChart = (chart) => {
    if (chart.dataset.tripCanvasBound === 'true') return;

    const canvas = chart.querySelector('.trip-canvas');
    const tooltip = chart.querySelector('.trip-canvas-tooltip');
    const tooltipTitle = tooltip?.querySelector('strong');
    const tooltipValue = tooltip?.querySelector('b');
    const data = parseTripPoints(chart);
    if (!canvas || !data.length) return;

    chart.dataset.tripCanvasBound = 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let activeIndex = -1;
    let pointerX = null;
    let animationProgress = reducedMotion ? 1 : 0;
    let resizeFrame = null;
    let geometry = [];
    let metrics = null;

    const resizeCanvas = () => {
        const rect = chart.getBoundingClientRect();
        const width = Math.max(320, rect.width);
        const height = Math.max(220, rect.height);
        const ratio = Math.max(1, window.devicePixelRatio || 1);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        const context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        metrics = { context, width, height };
    };

    const render = () => {
        if (!metrics) resizeCanvas();
        const { context, width, height } = metrics;
        const padding = { left: 46, right: 26, top: 20, bottom: 38 };
        const plotWidth = Math.max(1, width - padding.left - padding.right);
        const plotHeight = Math.max(1, height - padding.top - padding.bottom);
        const maxValue = Math.max(1, ...data.map((point) => point.value));
        const stepX = data.length > 1 ? plotWidth / (data.length - 1) : 0;

        geometry = data.map((point, index) => ({
            ...point,
            x: padding.left + (stepX * index),
            y: padding.top + plotHeight - ((point.value / maxValue) * plotHeight),
        }));

        context.clearRect(0, 0, width, height);
        context.font = '600 10px system-ui, sans-serif';
        context.textBaseline = 'middle';
        context.setLineDash([4, 6]);
        context.lineWidth = 1;
        context.strokeStyle = '#dfe7f2';
        context.fillStyle = '#94a3b8';
        context.textAlign = 'left';

        for (let index = 0; index < 5; index += 1) {
            const ratio = index / 4;
            const y = padding.top + (plotHeight * ratio);
            const labelValue = Math.round(maxValue * (1 - ratio));
            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();
            context.fillText(String(labelValue), 8, y);
        }

        context.setLineDash([]);
        context.textAlign = 'center';
        context.fillStyle = '#64748b';
        geometry.forEach((point) => context.fillText(`${point.label}${point.partial ? '*' : ''}`, point.x, height - 13));

        const completed = geometry.filter((point) => !point.partial);
        if (completed.length > 1) {
            context.save();
            context.beginPath();
            context.rect(0, 0, padding.left + (plotWidth * animationProgress), height);
            context.clip();
            context.strokeStyle = '#2563eb';
            context.lineWidth = 3;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            drawSmoothCurve(context, completed);
            context.stroke();
            context.restore();
        }

        geometry.forEach((point, index) => {
            if (point.x > padding.left + (plotWidth * animationProgress) + 2 && !point.partial) return;
            const isActive = index === activeIndex;
            const radius = isActive ? 6.5 : 4.5;
            context.save();
            context.beginPath();
            context.arc(point.x, point.y, radius, 0, Math.PI * 2);
            context.fillStyle = point.partial ? '#ffffff' : (isActive ? '#2563eb' : '#ffffff');
            context.fill();
            context.lineWidth = isActive ? 3 : 2.5;
            context.strokeStyle = point.partial ? '#94a3b8' : '#2563eb';
            if (point.partial) context.setLineDash([2, 2]);
            context.stroke();
            context.restore();
        });

        if (activeIndex >= 0 && pointerX !== null) {
            const crosshairX = clamp(pointerX, padding.left, width - padding.right);
            context.save();
            context.setLineDash([3, 4]);
            context.lineWidth = 1;
            context.strokeStyle = 'rgba(100, 116, 139, .55)';
            context.beginPath();
            context.moveTo(crosshairX, padding.top);
            context.lineTo(crosshairX, padding.top + plotHeight);
            context.stroke();
            context.restore();
        }
    };

    const animate = (startTime) => {
        const elapsed = Math.min(1, (performance.now() - startTime) / 950);
        animationProgress = 1 - Math.pow(1 - elapsed, 3);
        render();
        if (elapsed < 1) requestAnimationFrame(() => animate(startTime));
    };

    const showTooltip = (index, localX) => {
        const point = geometry[index];
        if (!point || !tooltip) return;
        activeIndex = index;
        pointerX = localX;
        tooltipTitle.textContent = `${point.label}${point.partial ? ' · Current partial period' : ''}`;
        tooltipValue.textContent = String(point.value);
        tooltip.style.left = `${clamp(localX, 90, chart.clientWidth - 90)}px`;
        tooltip.style.top = `${Math.max(62, point.y)}px`;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');
        render();
    };

    const hideTooltip = () => {
        activeIndex = -1;
        pointerX = null;
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
        render();
    };

    canvas.addEventListener('pointermove', (event) => {
        if (!geometry.length) return;
        const rect = canvas.getBoundingClientRect();
        const localX = event.clientX - rect.left;
        const index = geometry.reduce((closest, point, pointIndex) => {
            const distance = Math.abs(point.x - localX);
            return distance < closest.distance ? { index: pointIndex, distance } : closest;
        }, { index: 0, distance: Number.POSITIVE_INFINITY }).index;
        showTooltip(index, localX);
    });
    canvas.addEventListener('pointerleave', hideTooltip);

    const resizeObserver = new ResizeObserver(() => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => {
            resizeCanvas();
            render();
        });
    });
    resizeObserver.observe(chart);
    resizeCanvas();
    render();
    if (animationProgress < 1) requestAnimationFrame((time) => animate(time));
};

const bindFuelUsageCanvas = (chart) => {
    if (chart.dataset.fuelCanvasBound === 'true') return;

    const canvas = chart.querySelector('.fuel-usage-canvas');
    const tooltip = chart.querySelector('.fuel-usage-tooltip');
    const tooltipTitle = tooltip?.querySelector('strong');
    const tooltipFuel = tooltip?.querySelector('[data-fuel-value]');
    const tooltipDistance = tooltip?.querySelector('[data-distance-value]');
    const tooltipEfficiency = tooltip?.querySelector('[data-efficiency-value]');
    const data = parseFuelPoints(chart).slice(0, 10);
    if (!canvas || !data.length) return;

    chart.dataset.fuelCanvasBound = 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let progress = reducedMotion ? 1 : 0;
    let activeIndex = -1;
    let metrics = null;
    let bars = [];
    let resizeFrame = null;

    const resizeCanvas = () => {
        const rect = chart.getBoundingClientRect();
        const width = Math.max(360, rect.width);
        const height = Math.max(250, rect.height);
        const ratio = Math.max(1, window.devicePixelRatio || 1);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        const context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        metrics = { context, width, height };
    };

    const roundedRect = (context, x, y, width, height, radius) => {
        const safeRadius = Math.min(radius, width / 2, height / 2);
        context.beginPath();
        context.roundRect(x, y, width, height, safeRadius);
    };

    const render = () => {
        if (!metrics) resizeCanvas();
        const { context, width, height } = metrics;
        const padding = { left: 50, right: 20, top: 34, bottom: 54 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const maxFuel = Math.max(1, ...data.map((point) => point.fuel));
        const slot = plotWidth / data.length;
        const barWidth = clamp(slot * 0.56, 18, 44);

        context.clearRect(0, 0, width, height);
        context.font = '600 10px system-ui, sans-serif';
        context.textBaseline = 'middle';
        context.strokeStyle = '#e4eaf2';
        context.fillStyle = '#94a3b8';
        context.lineWidth = 1;
        context.setLineDash([4, 6]);
        context.textAlign = 'left';

        for (let index = 0; index < 5; index += 1) {
            const ratio = index / 4;
            const y = padding.top + plotHeight * ratio;
            const value = maxFuel * (1 - ratio);
            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();
            context.fillText(`${value.toFixed(value >= 100 ? 0 : 1)} L`, 5, y);
        }

        context.setLineDash([]);
        context.textAlign = 'center';
        bars = data.map((point, index) => {
            const x = padding.left + (slot * index) + ((slot - barWidth) / 2);
            const finalHeight = (point.fuel / maxFuel) * plotHeight;
            const drawnHeight = finalHeight * progress;
            const y = padding.top + plotHeight - drawnHeight;
            const isActive = index === activeIndex;

            context.save();
            context.fillStyle = isActive ? '#1557d5' : '#3b82f6';
            context.shadowColor = isActive ? 'rgba(21, 87, 213, .22)' : 'transparent';
            context.shadowBlur = isActive ? 10 : 0;
            roundedRect(context, x, y, barWidth, Math.max(2, drawnHeight), 7);
            context.fill();
            context.restore();

            if (progress > 0.62) {
                context.fillStyle = '#203553';
                context.font = '800 9px system-ui, sans-serif';
                context.fillText(`${point.fuel.toFixed(1)} L`, x + (barWidth / 2), Math.max(12, y - 10));
            }

            context.fillStyle = '#526277';
            context.font = `${isActive ? '800' : '700'} 9px system-ui, sans-serif`;
            const label = point.label.length > 9 ? `${point.label.slice(0, 8)}…` : point.label;
            context.fillText(label, x + (barWidth / 2), height - 18);

            return { ...point, x, y, width: barWidth, height: drawnHeight, centerX: x + (barWidth / 2) };
        });
    };

    const animate = (start) => {
        const elapsed = Math.min(1, (performance.now() - start) / 760);
        progress = 1 - Math.pow(1 - elapsed, 3);
        render();
        if (elapsed < 1) requestAnimationFrame(() => animate(start));
    };

    const showTooltip = (index) => {
        const bar = bars[index];
        if (!bar || !tooltip) return;
        activeIndex = index;
        if (tooltipTitle) tooltipTitle.textContent = bar.label;
        if (tooltipFuel) tooltipFuel.textContent = `${bar.fuel.toFixed(1)} L`;
        if (tooltipDistance) tooltipDistance.textContent = `${bar.distance.toFixed(1)} km`;
        if (tooltipEfficiency) tooltipEfficiency.textContent = `${bar.efficiency.toFixed(2)} km/L`;
        tooltip.style.left = `${clamp(bar.centerX, 94, chart.clientWidth - 94)}px`;
        tooltip.style.top = `${Math.max(72, bar.y)}px`;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');
        render();
    };

    const hideTooltip = () => {
        activeIndex = -1;
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
        render();
    };

    canvas.addEventListener('pointermove', (event) => {
        if (!bars.length) return;
        const rect = canvas.getBoundingClientRect();
        const localX = event.clientX - rect.left;
        const index = bars.reduce((closest, bar, barIndex) => {
            const distance = Math.abs(bar.centerX - localX);
            return distance < closest.distance ? { index: barIndex, distance } : closest;
        }, { index: 0, distance: Number.POSITIVE_INFINITY }).index;
        showTooltip(index);
    });
    canvas.addEventListener('pointerleave', hideTooltip);

    const resizeObserver = new ResizeObserver(() => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => {
            resizeCanvas();
            render();
        });
    });
    resizeObserver.observe(chart);
    resizeCanvas();
    render();
    if (progress < 1) requestAnimationFrame((time) => animate(time));
};

const hexToRgba = (hex, alpha) => {
    const value = hex.replace('#', '');
    const normalized = value.length === 3 ? value.split('').map((character) => `${character}${character}`).join('') : value;
    const numeric = Number.parseInt(normalized, 16);
    const red = (numeric >> 16) & 255;
    const green = (numeric >> 8) & 255;
    const blue = numeric & 255;
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
};

const bindFleetCssDonut = (card) => {
    const donut = card.querySelector('.fleet-css-donut');
    const rows = Array.from(card.querySelectorAll('.availability-row[data-donut-index]'));
    const center = donut?.querySelector('.fleet-css-donut-center');
    const centerValue = center?.querySelector('strong');
    const centerLabel = center?.querySelector('span');
    const tooltip = donut?.querySelector('.fleet-css-donut-tooltip');
    const tooltipLabel = tooltip?.querySelector('strong');
    const tooltipMeta = tooltip?.querySelector('span');
    if (!donut || donut.dataset.cssDonutBound === 'true') return;

    donut.dataset.cssDonutBound = 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const defaultValue = donut.dataset.defaultValue || centerValue?.textContent?.trim() || '0%';
    const defaultLabel = donut.dataset.defaultLabel || centerLabel?.textContent?.trim() || 'Active';
    const colors = ['#16a34a', '#f59e0b', '#94a3b8'];
    let centerTransitionToken = 0;
    let activeIndex = null;

    const segments = rows.map((row, index) => ({
        row,
        color: colors[index] || '#94a3b8',
        label: row.dataset.label || 'Status',
        value: Number.parseInt(row.dataset.value || '0', 10) || 0,
        percentage: Math.max(0, Number.parseFloat(row.dataset.percentage || '0') || 0),
    }));

    const renderGradient = (focusedIndex = null) => {
        let offset = 0;
        const stops = segments.map((segment, index) => {
            const start = offset;
            offset += segment.percentage;
            const color = focusedIndex === null || focusedIndex === index ? segment.color : hexToRgba(segment.color, .24);
            return `${color} ${start.toFixed(2)}% ${offset.toFixed(2)}%`;
        });
        donut.style.background = `conic-gradient(from -90deg, ${stops.join(', ')})`;
    };

    const setCenter = (value, label) => {
        const token = ++centerTransitionToken;
        if (!center || reducedMotion) {
            if (centerValue) centerValue.textContent = value;
            if (centerLabel) centerLabel.textContent = label;
            return;
        }
        const fadeOut = center.animate([{ opacity: 1, transform: 'scale(1)' }, { opacity: .12, transform: 'scale(.94)' }], { duration: 90, easing: 'ease-out', fill: 'forwards' });
        fadeOut.finished.then(() => {
            if (token !== centerTransitionToken) return;
            if (centerValue) centerValue.textContent = value;
            if (centerLabel) centerLabel.textContent = label;
            center.animate([{ opacity: .12, transform: 'scale(.94)' }, { opacity: 1, transform: 'scale(1)' }], { duration: 130, easing: 'cubic-bezier(.22,.61,.36,1)', fill: 'forwards' });
        }).catch(() => {});
    };

    const positionTooltip = (x, y) => {
        if (!tooltip) return;
        tooltip.style.left = `${clamp(x, 34, donut.clientWidth - 34)}px`;
        tooltip.style.top = `${clamp(y, 30, donut.clientHeight - 30)}px`;
    };

    const showItem = (index, pointer = null) => {
        const segment = segments[index];
        if (!segment) return;
        activeIndex = index;
        rows.forEach((row, rowIndex) => row.classList.toggle('is-donut-active', rowIndex === index));
        donut.classList.add('is-active');
        renderGradient(index);
        setCenter(`${segment.percentage.toFixed(1)}%`, segment.label);
        if (tooltipLabel) tooltipLabel.textContent = segment.label;
        if (tooltipMeta) tooltipMeta.textContent = `${segment.value} bus${segment.value === 1 ? '' : 'es'} · ${segment.percentage.toFixed(1)}%`;
        if (pointer) positionTooltip(pointer.x, pointer.y);
        tooltip?.classList.add('is-visible');
        tooltip?.setAttribute('aria-hidden', 'false');
    };

    const clearItem = () => {
        activeIndex = null;
        rows.forEach((row) => row.classList.remove('is-donut-active'));
        donut.classList.remove('is-active');
        renderGradient();
        setCenter(defaultValue, defaultLabel);
        tooltip?.classList.remove('is-visible');
        tooltip?.setAttribute('aria-hidden', 'true');
    };

    const indexFromPointer = (event) => {
        const rect = donut.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const dx = x - centerX;
        const dy = y - centerY;
        const distance = Math.sqrt((dx * dx) + (dy * dy));
        const outerRadius = rect.width / 2;
        const innerRadius = outerRadius - 31;
        if (distance < innerRadius || distance > outerRadius + 3) return { index: null, x, y };
        const degrees = (Math.atan2(dy, dx) * 180 / Math.PI + 450) % 360;
        const percentage = (degrees / 360) * 100;
        let offset = 0;
        const index = segments.findIndex((segment) => {
            offset += segment.percentage;
            return percentage <= offset + 0.001;
        });
        return { index: index < 0 ? segments.length - 1 : index, x, y };
    };

    renderGradient();
    rows.forEach((row, index) => {
        row.setAttribute('tabindex', '0');
        row.addEventListener('pointerenter', () => showItem(index));
        row.addEventListener('pointerleave', clearItem);
        row.addEventListener('focus', () => showItem(index));
        row.addEventListener('blur', clearItem);
    });
    donut.addEventListener('pointermove', (event) => {
        const pointer = indexFromPointer(event);
        if (pointer.index === null) {
            if (activeIndex !== null) clearItem();
            return;
        }
        showItem(pointer.index, pointer);
    });
    donut.addEventListener('pointerleave', clearItem);
};

const bindFuelDetailsSearch = (input) => {
    if (input.dataset.fuelSearchBound === 'true') return;
    const scope = input.closest('.analytics-domain-card');
    const table = scope?.querySelector('[data-fuel-details-table]');
    if (!table) return;
    input.dataset.fuelSearchBound = 'true';
    const rows = Array.from(table.querySelectorAll('tbody tr[data-fuel-bus]'));
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        rows.forEach((row) => {
            row.hidden = query !== '' && !String(row.dataset.fuelBus || '').includes(query);
        });
    });
};

const initializeAnalyticsDomainPanels = () => {
    document.querySelectorAll('.analytics-domain-card').forEach((card) => card.classList.add('is-chart-visible'));
    document.querySelectorAll('.trip-canvas-chart').forEach(bindTripCanvasChart);
    document.querySelectorAll('.fuel-usage-chart').forEach(bindFuelUsageCanvas);
    document.querySelectorAll('.descriptive-availability-card').forEach(bindFleetCssDonut);
    document.querySelectorAll('[data-fuel-table-search]').forEach(bindFuelDetailsSearch);
};

initializeAnalyticsDomainPanels();
document.addEventListener('DOMContentLoaded', initializeAnalyticsDomainPanels);
document.addEventListener('ajax:content-updated', initializeAnalyticsDomainPanels);
