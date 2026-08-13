function initBatchProfileSelector() {
    const form = document.getElementById('gpsUploadForm');
    const moduleSelect = form?.querySelector('select[name="module"]');
    const dataTypeSelect = form?.querySelector('select[name="data_type"]');
    const fileInput = document.getElementById('gpsFileInput');
    const selectedFileName = document.getElementById('selectedFileName');
    const uploadButton = document.getElementById('uploadGpsFileBtn');
    const processorHelper = form?.querySelector('.upload-details span:nth-child(1)');
    const pdfHelper = form?.querySelector('.upload-details span:nth-child(2)');

    if (!form || !moduleSelect || !dataTypeSelect || form.dataset.profileSelectorReady === 'true') {
        return;
    }

    form.dataset.profileSelectorReady = 'true';

    const gpsUploadUrl = form.action;
    const genericUploadUrl = '/batch-file-processing/generic-upload';

    const profiles = {
        Operation: {
            label: 'Operation — GPS Trip Records',
            dataType: 'GPS Trip Records',
            accept: '.csv,.txt,.pdf,.xls,.xlsx',
            formats: 'PDF, CSV, TXT, XLS, XLSX',
            pdf: true,
        },
        Maintenance: {
            label: 'Maintenance — Fuel Reports',
            dataType: 'Fuel Reports',
            accept: '.csv,.txt,.xls,.xlsx',
            formats: 'CSV, TXT, XLS, XLSX',
            pdf: false,
        },
        Warehouse: {
            label: 'Warehouse — Inventory Records',
            dataType: 'Inventory Records',
            accept: '.csv,.txt,.xls,.xlsx',
            formats: 'CSV, TXT, XLS, XLSX',
            pdf: false,
        },
        Purchase: {
            label: 'Purchase — Purchase Orders',
            dataType: 'Purchase Orders',
            accept: '.csv,.txt,.xls,.xlsx',
            formats: 'CSV, TXT, XLS, XLSX',
            pdf: false,
        },
    };

    moduleSelect.innerHTML = Object.entries(profiles)
        .map(([module, profile]) => `<option value="${module}">${profile.label}</option>`)
        .join('');

    function notify(message, type = 'info') {
        if (typeof window.showSystemNotification === 'function') {
            window.showSystemNotification(message, type);
            return;
        }

        console.warn(message);
    }

    function selectedExtension() {
        const fileName = fileInput?.files?.[0]?.name || '';
        const parts = fileName.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function resetFileSelection() {
        if (fileInput) {
            fileInput.value = '';
        }

        if (selectedFileName) {
            selectedFileName.textContent = 'Drag and drop data files here';
        }

        if (uploadButton) {
            uploadButton.disabled = true;
        }
    }

    function applyProfile() {
        const module = moduleSelect.value;
        const profile = profiles[module] || profiles.Operation;

        dataTypeSelect.innerHTML = `<option value="${profile.dataType}">${profile.dataType}</option>`;
        form.action = module === 'Operation' ? gpsUploadUrl : genericUploadUrl;

        if (fileInput) {
            fileInput.accept = profile.accept;
        }

        if (processorHelper) {
            processorHelper.innerHTML = `
                <i class="fa-solid fa-gears"></i>
                ${module} · ${profile.dataType} · ${profile.formats}
            `;
        }

        if (pdfHelper) {
            pdfHelper.innerHTML = profile.pdf
                ? '<i class="fa-solid fa-file-pdf"></i> PDF supported for GPS Trip Records'
                : '<i class="fa-solid fa-circle-info"></i> PDF not supported for this data type';
        }

        if (!profile.pdf && selectedExtension() === 'pdf') {
            resetFileSelection();

            notify(
                'PDF extraction is currently available only for Operation GPS Trip Records.',
                'info'
            );
        }
    }

    form.addEventListener('submit', (event) => {
        const profile = profiles[moduleSelect.value] || profiles.Operation;

        if (!profile.pdf && selectedExtension() === 'pdf') {
            event.preventDefault();
            event.stopImmediatePropagation();

            resetFileSelection();
            notify(
                'This data type currently supports CSV, TXT, XLS, and XLSX files. PDF remains available for GPS Trip Records.',
                'error'
            );
        }
    }, true);

    async function linkGenericBatchesToReview() {
        try {
            const response = await fetch('/batch-file-processing/generic-profiles', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const batchProfiles = data?.profiles || {};

            document.querySelectorAll('.uploaded-file[href]').forEach((link) => {
                let url;

                try {
                    url = new URL(link.href, window.location.origin);
                } catch {
                    return;
                }

                const batchId = url.searchParams.get('batch_id');
                const profile = batchProfiles[String(batchId || '')];

                if (!profile) {
                    return;
                }

                link.dataset.batchModule = profile.module || '';
                link.dataset.batchDataType = profile.data_type || '';
                link.dataset.batchStatus = profile.status || '';

                if (profile.status === 'Processed') {
                    if (profile.structured_url) {
                        link.href = profile.structured_url;
                    }
                    return;
                }

                if (profile.review_url) {
                    link.href = profile.review_url;
                }
            });
        } catch (error) {
            console.warn('Unable to resolve generic batch links.', error);
        }
    }

    moduleSelect.addEventListener('change', applyProfile);
    applyProfile();
    linkGenericBatchesToReview();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBatchProfileSelector, { once: true });
} else {
    initBatchProfileSelector();
}
