function initImportExportPage() {
    const importModule = document.getElementById('importModule');
    const importDataType = document.getElementById('importDataType');
    const importFile = document.getElementById('importFile');
    const selectedFileName = document.getElementById('selectedFileName');
    const exportModule = document.getElementById('exportModule');
    const exportDataType = document.getElementById('exportDataType');
    const exportFormat = document.getElementById('exportFormat');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');

    if (!importModule || !importDataType || !exportModule || !exportDataType) return;

    const transferButtons = document.querySelectorAll('.primary-transfer-btn');
    const importButton = transferButtons[0];
    const exportButton = transferButtons[1];
    const validationPreview = document.querySelector('.validation-preview');
    const validationStatus = document.querySelector('.validation-status');
    const validationValues = document.querySelectorAll('.validation-item strong');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const profiles = {
        Operation: 'GPS Trip Records',
        Maintenance: 'Fuel Reports',
        Warehouse: 'Inventory Records',
        Purchase: 'Purchase Orders',
    };

    function notify(message, type = 'info', title = null) {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(message, type, title, { timeout: 5500 });
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function applyStatusPresentation() {
        document.querySelectorAll('.status-badge').forEach((badge) => {
            const text = badge.textContent.trim().toLowerCase();
            badge.style.border = '1px solid transparent';
            if (text === 'for review') {
                badge.style.background = '#fff3cd';
                badge.style.color = '#9a6700';
                badge.style.borderColor = '#f8dd82';
            } else if (text === 'processing') {
                badge.style.background = '#e0ecff';
                badge.style.color = '#0b40b5';
                badge.style.borderColor = '#bfd4ff';
            }
        });
    }

    function setModules(select) {
        select.innerHTML = '<option value="">Select Module</option>' +
            Object.keys(profiles).map((module) => `<option value="${module}">${module}</option>`).join('');
    }

    function applyDataType(moduleSelect, dataTypeSelect) {
        const type = profiles[moduleSelect.value];
        dataTypeSelect.innerHTML = type
            ? `<option value="${type}">${type}</option>`
            : '<option value="">Select Data Type</option>';
    }

    function clearValidationErrors() {
        validationPreview?.querySelector('.import-validation-errors')?.remove();
    }

    function showValidationErrors(errors = []) {
        clearValidationErrors();
        if (!validationPreview || !errors.length) return;

        const panel = document.createElement('div');
        panel.className = 'import-validation-errors';
        panel.style.marginTop = '12px';
        panel.style.padding = '12px';
        panel.style.border = '1px solid #fecaca';
        panel.style.borderRadius = '9px';
        panel.style.background = '#fff7f7';
        panel.innerHTML = `
            <div class="import-validation-errors-title" style="display:flex;align-items:center;gap:7px;color:#b91c1c;font-size:11px;margin-bottom:8px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong>Rows requiring correction</strong>
            </div>
            <div class="import-validation-error-list" style="display:grid;gap:6px;"></div>
        `;

        const list = panel.querySelector('.import-validation-error-list');
        errors.forEach((error) => {
            const item = document.createElement('div');
            item.className = 'import-validation-error-item';
            item.style.display = 'grid';
            item.style.gridTemplateColumns = '64px minmax(0,1fr)';
            item.style.gap = '8px';
            item.style.fontSize = '11px';
            item.style.color = '#7f1d1d';
            item.innerHTML = `<strong>Row ${error.row ?? '—'}</strong><span></span>`;
            item.querySelector('span').textContent = error.message || 'Validation failed.';
            list.appendChild(item);
        });
        validationPreview.appendChild(panel);
    }

    function refreshImportButton() {
        const ready = Boolean(importModule.value && importDataType.value && importFile?.files?.length);
        if (!importButton) return;
        importButton.disabled = !ready;
        importButton.setAttribute('aria-disabled', String(!ready));
        importButton.title = ready ? 'Validate and import this structured file.' : 'Choose a supported mapping and structured file first.';
        importButton.innerHTML = '<i class="fa-solid fa-file-import"></i> Validate & Import Records';
    }

    function refreshExportButton() {
        const ready = Boolean(exportModule.value && exportDataType.value && exportFormat?.value);
        if (!exportButton) return;
        exportButton.disabled = !ready;
        exportButton.setAttribute('aria-disabled', String(!ready));
        exportButton.title = ready ? 'Generate a file from current FROMS records.' : 'Choose a supported mapping first.';
        exportButton.innerHTML = '<i class="fa-solid fa-file-export"></i> Generate Export';
    }

    function updateValidation(total = '—', valid = '—', invalid = '—', status = 'Ready to validate', state = 'waiting') {
        if (validationValues.length >= 3) {
            validationValues[0].textContent = total;
            validationValues[1].textContent = valid;
            validationValues[2].textContent = invalid;
        }
        if (validationStatus) {
            validationStatus.textContent = status;
            validationStatus.className = `validation-status ${state}`;
            validationStatus.style.border = '1px solid #dce5f2';
            validationStatus.style.background = '#ffffff';
            validationStatus.style.color = '#64748b';
            if (state === 'failed') {
                validationStatus.style.borderColor = '#f8dd82';
                validationStatus.style.background = '#fff3cd';
                validationStatus.style.color = '#9a6700';
            } else if (state === 'success') {
                validationStatus.style.borderColor = '#b7efca';
                validationStatus.style.background = '#dcfce7';
                validationStatus.style.color = '#15803d';
            }
        }
    }

    function downloadBlob(blob, fileName) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function fileNameFromDisposition(disposition) {
        const utfMatch = disposition?.match(/filename\*=UTF-8''([^;]+)/i);
        if (utfMatch) return decodeURIComponent(utfMatch[1]);
        const plainMatch = disposition?.match(/filename="?([^";]+)"?/i);
        return plainMatch?.[1] || `froms-export-${Date.now()}.${exportFormat?.value || 'xlsx'}`;
    }

    setModules(importModule);
    setModules(exportModule);
    applyDataType(importModule, importDataType);
    applyDataType(exportModule, exportDataType);

    if (exportFormat) {
        exportFormat.innerHTML = '<option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option>';
    }

    importModule.addEventListener('change', () => {
        applyDataType(importModule, importDataType);
        clearValidationErrors();
        updateValidation();
        refreshImportButton();
    });

    exportModule.addEventListener('change', () => {
        applyDataType(exportModule, exportDataType);
        refreshExportButton();
    });

    importFile?.addEventListener('change', () => {
        if (selectedFileName) {
            selectedFileName.textContent = importFile.files.length ? importFile.files[0].name : 'No file selected';
        }
        clearValidationErrors();
        updateValidation();
        refreshImportButton();
    });

    exportFormat?.addEventListener('change', refreshExportButton);

    importButton?.addEventListener('click', async () => {
        if (importButton.disabled || !importFile?.files?.length) return;

        importButton.disabled = true;
        importButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Validating & Importing...';
        clearValidationErrors();
        updateValidation('—', '—', '—', 'Validating file...', 'waiting');

        const formData = new FormData();
        formData.append('module', importModule.value);
        formData.append('data_type', importDataType.value);
        formData.append('file', importFile.files[0]);

        try {
            const response = await fetch('/admin/import-export/import', {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            const validation = data.validation || {};

            if (!response.ok) {
                updateValidation(validation.total ?? '—', validation.valid ?? '—', validation.invalid ?? '—', 'For Review', 'failed');
                showValidationErrors(data.errors || []);
                notify(data.message || 'Import validation failed.', 'error', 'Import needs correction');
                return;
            }

            updateValidation(validation.total ?? 0, validation.valid ?? 0, validation.invalid ?? 0, 'Import completed', 'success');
            notify(data.message || 'Import completed successfully.', 'success', 'Import completed');
            window.setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Import failed:', error);
            updateValidation('—', '—', '—', 'Import failed', 'failed');
            notify('Unable to import the selected file. Please try again.', 'error', 'Import failed');
        } finally {
            refreshImportButton();
        }
    });

    exportButton?.addEventListener('click', async () => {
        if (exportButton.disabled) return;
        if (dateFrom?.value && dateTo?.value && dateFrom.value > dateTo.value) {
            notify('Date From cannot be later than Date To.', 'error', 'Invalid date range');
            return;
        }

        exportButton.disabled = true;
        exportButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating Export...';

        const formData = new FormData();
        formData.append('_token', csrf);
        formData.append('module', exportModule.value);
        formData.append('data_type', exportDataType.value);
        formData.append('date_from', dateFrom?.value || '');
        formData.append('date_to', dateTo?.value || '');
        formData.append('format', exportFormat?.value || 'xlsx');

        try {
            const response = await fetch('/admin/import-export/export', {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/octet-stream, application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || contentType.includes('application/json')) {
                const data = await response.json().catch(() => ({}));
                notify(data.message || 'No records could be exported with the selected filters.', 'error', 'Export not generated');
                return;
            }

            const blob = await response.blob();
            const fileName = fileNameFromDisposition(response.headers.get('content-disposition'));
            const count = response.headers.get('x-froms-export-count');
            downloadBlob(blob, fileName);
            notify(count ? `${count} record(s) exported successfully.` : 'Export generated successfully.', 'success', 'Export completed');
            window.setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            console.error('Export failed:', error);
            notify('Unable to generate the export. Please try again.', 'error', 'Export failed');
        } finally {
            refreshExportButton();
        }
    });

    applyStatusPresentation();
    updateValidation();
    refreshImportButton();
    refreshExportButton();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImportExportPage, { once: true });
} else {
    initImportExportPage();
}
