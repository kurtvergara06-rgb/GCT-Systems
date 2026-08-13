import '../../../css/Admin/Data_Management/data-activity-modal.css';

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
            if (text === 'for review') badge.classList.add('for-review');
            if (text === 'needs correction') badge.classList.add('needs-correction');
            if (text === 'processing') badge.classList.add('processing');
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
        panel.innerHTML = `
            <div class="import-validation-errors-title">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong>Rows requiring correction</strong>
            </div>
            <div class="import-validation-error-list"></div>
        `;

        const list = panel.querySelector('.import-validation-error-list');
        errors.forEach((error) => {
            const item = document.createElement('div');
            item.className = 'import-validation-error-item';
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
        importButton.title = ready ? 'Validate this structured file and stage it for approval.' : 'Choose a supported mapping and structured file first.';
        importButton.innerHTML = '<i class="fa-solid fa-file-circle-check"></i> Validate Records';
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

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function closeActivityModal() {
        document.getElementById('transferActivityModal')?.remove();
        document.body.classList.remove('transfer-activity-modal-open');
    }

    function uploadCorrectedFile() {
        closeActivityModal();
        const importCard = importModule.closest('.transfer-card');
        importCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });

        window.setTimeout(() => {
            importFile?.focus({ preventScroll: true });
            importFile?.click();
        }, 350);
    }

    async function approveActivity(activityId, button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Approving...';

        try {
            const response = await fetch(`/admin/import-export/activity/${activityId}/approve`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                notify(data.message || 'Unable to approve this import.', 'error', 'Approval failed');
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-circle-check"></i> Approve Import';
                return;
            }

            notify(data.message || 'Import approved successfully.', 'success', 'Import completed');
            closeActivityModal();
            window.setTimeout(() => window.location.reload(), 700);
        } catch (error) {
            console.error('Import approval failed:', error);
            notify('Unable to approve this import. Please try again.', 'error', 'Approval failed');
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-circle-check"></i> Approve Import';
        }
    }

    async function openActivityModal(activityId) {
        if (!activityId) return;

        try {
            const response = await fetch(`/admin/import-export/activity/${activityId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                notify(data.message || 'Unable to load activity details.', 'error', 'Details unavailable');
                return;
            }

            closeActivityModal();
            const statusClass = String(data.status || '').toLowerCase().replaceAll(' ', '-');
            const errors = Array.isArray(data.validation_errors) ? data.validation_errors : [];
            const validationHtml = errors.length ? `
                <div class="transfer-validation-panel">
                    <div class="transfer-validation-title">Validation issues</div>
                    <div class="transfer-validation-list">
                        ${errors.map((error) => `
                            <div class="transfer-validation-row">
                                <strong>Row ${escapeHtml(error.row ?? '—')}</strong>
                                <span>${escapeHtml(error.message || 'Validation failed.')}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : '';

            const invalidCount = Number(data.failed_records) || errors.length;
            const needsCorrection = data.activity_type === 'Import' && data.status === 'Needs Correction';
            const noteHtml = data.status === 'For Review'
                ? '<div class="transfer-review-note">All rows passed validation. Review the summary below, then approve the import.</div>'
                : needsCorrection
                    ? `<div class="transfer-review-note">${escapeHtml(invalidCount)} records require correction. Fix the listed issues in the source file and upload it again. This import cannot be approved yet.</div>`
                    : '';

            const overlay = document.createElement('div');
            overlay.id = 'transferActivityModal';
            overlay.className = 'transfer-activity-modal-overlay show';
            overlay.innerHTML = `
                <div class="transfer-activity-modal" role="dialog" aria-modal="true" aria-labelledby="transferActivityTitle">
                    <div class="transfer-activity-modal-header">
                        <div>
                            <h2 id="transferActivityTitle">Import / Export Details</h2>
                            <p>${escapeHtml(data.file_name)}</p>
                        </div>
                        <button type="button" class="transfer-activity-modal-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="transfer-activity-modal-body">
                        <div class="transfer-activity-status-row">
                            <span class="transfer-activity-status ${statusClass}">${escapeHtml(data.status)}</span>
                            <span>${escapeHtml(data.date_time)}</span>
                        </div>
                        <div class="transfer-activity-grid">
                            <div class="transfer-activity-item wide"><span>File</span><strong>${escapeHtml(data.file_name)}</strong></div>
                            <div class="transfer-activity-item"><span>Activity</span><strong>${escapeHtml(data.activity_type)}</strong></div>
                            <div class="transfer-activity-item"><span>Module</span><strong>${escapeHtml(data.module)}</strong></div>
                            <div class="transfer-activity-item"><span>Data Type</span><strong>${escapeHtml(data.data_type)}</strong></div>
                            <div class="transfer-activity-item"><span>Data Source</span><strong>${escapeHtml(data.source)}</strong></div>
                            <div class="transfer-activity-item"><span>Total Records</span><strong>${escapeHtml(data.total_records)}</strong></div>
                            <div class="transfer-activity-item"><span>Valid / Successful</span><strong>${escapeHtml(data.successful_records)}</strong></div>
                            <div class="transfer-activity-item"><span>Invalid / Failed</span><strong>${escapeHtml(data.failed_records)}</strong></div>
                            <div class="transfer-activity-item"><span>Skipped</span><strong>${escapeHtml(data.skipped_records)}</strong></div>
                            <div class="transfer-activity-item"><span>Processed By</span><strong>${escapeHtml(data.processed_by)}</strong></div>
                        </div>
                        ${noteHtml}
                        ${validationHtml}
                    </div>
                    <div class="transfer-activity-modal-footer">
                        <button type="button" class="transfer-modal-btn secondary" data-close-activity>Close</button>
                        ${needsCorrection ? '<button type="button" class="transfer-modal-btn approve" data-upload-corrected><i class="fa-solid fa-file-arrow-up"></i> Upload Corrected File</button>' : ''}
                        ${data.can_approve ? '<button type="button" class="transfer-modal-btn approve" data-approve-activity><i class="fa-solid fa-circle-check"></i> Approve Import</button>' : ''}
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.classList.add('transfer-activity-modal-open');
            overlay.querySelector('.transfer-activity-modal-close')?.addEventListener('click', closeActivityModal);
            overlay.querySelector('[data-close-activity]')?.addEventListener('click', closeActivityModal);
            overlay.querySelector('[data-upload-corrected]')?.addEventListener('click', uploadCorrectedFile);
            overlay.querySelector('[data-approve-activity]')?.addEventListener('click', (event) => approveActivity(activityId, event.currentTarget));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) closeActivityModal();
            });
        } catch (error) {
            console.error('Unable to load transfer activity:', error);
            notify('Unable to load activity details. Please try again.', 'error', 'Details unavailable');
        }
    }

    async function bindRecentActivityButtons() {
        const rows = Array.from(document.querySelectorAll('.recent-transfer-card tbody tr'))
            .filter((row) => row.querySelector('.action-btn.view'));
        if (!rows.length) return;

        try {
            const response = await fetch('/admin/import-export/recent-activities', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            const activities = Array.isArray(data.activities) ? data.activities : [];

            rows.forEach((row, index) => {
                const activity = activities[index];
                const action = row.querySelector('.action-btn.view');
                if (!activity || !action) return;
                action.dataset.activityId = String(activity.id);
                action.title = activity.status === 'For Review' ? 'Review and approve import' : 'View activity details';
                action.addEventListener('click', (event) => {
                    event.preventDefault();
                    openActivityModal(activity.id);
                });
            });
        } catch (error) {
            console.warn('Unable to bind recent activity detail buttons:', error);
        }
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
        importButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Validating Records...';
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
                updateValidation(validation.total ?? '—', validation.valid ?? '—', validation.invalid ?? '—', 'Needs Correction', 'failed');
                showValidationErrors(data.errors || []);
                notify(data.message || 'Import validation failed.', 'error', 'Needs correction');
                if (data.activity_id) openActivityModal(data.activity_id);
                return;
            }

            updateValidation(validation.total ?? 0, validation.valid ?? 0, validation.invalid ?? 0, 'For Review', 'review');
            notify(data.message || 'Validation completed. Review and approve the import.', 'success', 'Ready for approval');
            if (data.activity_id) openActivityModal(data.activity_id);
        } catch (error) {
            console.error('Import validation failed:', error);
            updateValidation('—', '—', '—', 'Validation failed', 'failed');
            notify('Unable to validate the selected file. Please try again.', 'error', 'Validation failed');
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

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeActivityModal();
    });

    applyStatusPresentation();
    updateValidation();
    refreshImportButton();
    refreshExportButton();
    bindRecentActivityButtons();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImportExportPage, { once: true });
} else {
    initImportExportPage();
}
