document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('gpsFileInput');
    const dropzone = document.getElementById('gpsDropzone');
    const chooseFileBtn = document.getElementById('chooseGpsFileBtn');
    const selectedFileName = document.getElementById('selectedFileName');
    const uploadForm = document.getElementById('gpsUploadForm');
    const uploadButton = document.getElementById('uploadGpsFileBtn');

    const feedbackModal = document.getElementById('batchFeedbackModal');
    const closeFeedbackModal = document.getElementById('closeBatchFeedbackModal');

    const rawUploadModal = document.getElementById('rawUploadModal');
    const closeRawUploadModal = document.getElementById('closeRawUploadModal');
    const rawUploadSearch = document.getElementById('rawUploadSearch');

    const allRecordsModal = document.getElementById('allBatchRecordsModal');
    const closeAllRecordsModal = document.getElementById('closeAllBatchRecordsModal');
    const recordsSearch = document.getElementById('allBatchRecordsSearch');

    const cleanDataModal = document.getElementById('cleanDataModal');
    const closeCleanDataModal = document.getElementById('closeCleanDataModal');
    const cleanDataSearch = document.getElementById('cleanDataSearch');

    const deleteModal = document.getElementById('batchDeleteModal');
    const deleteForm = document.getElementById('batchDeleteForm');
    const deleteFileName = document.getElementById('batchDeleteFileName');
    const cancelDeleteButton = document.getElementById('cancelBatchDelete');

    const bulkUpdateRecordsForm = document.getElementById('bulkUpdateRecordsForm');
    const saveAllBatchRecordsBtn = document.getElementById('saveAllBatchRecordsBtn');
    const unsavedChangesLabel = document.getElementById('unsavedChangesLabel');

    const confirmBatchForm = document.getElementById('confirmBatchForm');
    const markBatchProcessedBtn = document.getElementById(
        'markBatchProcessedBtn'
    );

    const allowedExtensions = ['csv', 'txt', 'pdf', 'xls', 'xlsx'];

    let hasUnsavedBatchChanges = false;

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('show');
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');

        const hasOpenModal = document.querySelector('.modal-overlay.show');

        if (!hasOpenModal) {
            document.body.classList.remove('modal-open');
        }
    }

    function showNotification(message, type = 'info') {
        if (typeof window.showSystemNotification === 'function') {
            window.showSystemNotification(message, type);
            return;
        }

        console.warn(message);
    }

    function getFileExtension(fileName) {
        const parts = String(fileName || '').split('.');

        if (parts.length < 2) {
            return '';
        }

        return parts.pop().toLowerCase();
    }

    function resetSelectedFile() {
        if (fileInput) {
            fileInput.value = '';
        }

        if (selectedFileName) {
            selectedFileName.textContent = 'Drag and drop GPS files here';
        }

        if (uploadButton) {
            uploadButton.disabled = true;
        }
    }

    function setSelectedFile(file) {
        if (!file) {
            resetSelectedFile();
            return;
        }

        const extension = getFileExtension(file.name);

        if (!allowedExtensions.includes(extension)) {
            showNotification(
                'Please upload only PDF, CSV, TXT, XLS, or XLSX files.',
                'error'
            );

            resetSelectedFile();
            return;
        }

        if (selectedFileName) {
            selectedFileName.textContent = file.name;
        }

        if (uploadButton) {
            uploadButton.disabled = false;
        }
    }

    function updateBatchSaveState() {
        if (!saveAllBatchRecordsBtn || !unsavedChangesLabel) {
            return;
        }

        saveAllBatchRecordsBtn.disabled = !hasUnsavedBatchChanges;

        if (hasUnsavedBatchChanges) {
            unsavedChangesLabel.textContent = 'Unsaved changes';
            unsavedChangesLabel.classList.add('has-changes');
        } else {
            unsavedChangesLabel.textContent = 'No unsaved changes';
            unsavedChangesLabel.classList.remove('has-changes');
        }
    }

    function updateEditedRow(input) {
        const row = input.closest('.batch-edit-row');

        if (!row) {
            return;
        }

        const rowInputs = row.querySelectorAll('.batch-edit-input');

        const rowChanged = Array.from(rowInputs).some(function (rowInput) {
            return rowInput.value !== rowInput.dataset.originalValue;
        });

        row.classList.toggle('row-edited', rowChanged);

        hasUnsavedBatchChanges =
            document.querySelectorAll('.batch-edit-row.row-edited').length > 0;

        updateBatchSaveState();
    }

    if (chooseFileBtn && fileInput) {
        chooseFileBtn.addEventListener('click', function () {
            fileInput.click();
        });
    }

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', function (event) {
            if (event.target.closest('#chooseGpsFileBtn')) {
                return;
            }

            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            setSelectedFile(fileInput.files[0]);
        });
    }

    if (dropzone && fileInput) {
        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('drag-active');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('drag-active');
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('drag-active');

            const file = event.dataTransfer.files[0];

            if (!file) {
                return;
            }

            const extension = getFileExtension(file.name);

            if (!allowedExtensions.includes(extension)) {
                showNotification(
                    'Please upload only PDF, CSV, TXT, XLS, or XLSX files.',
                    'error'
                );
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            fileInput.files = transfer.files;

            setSelectedFile(file);
        });
    }

    if (uploadForm && uploadButton && fileInput) {
        uploadForm.addEventListener('submit', function (event) {
            if (!fileInput.files.length) {
                event.preventDefault();

                showNotification(
                    'Please select a GPS report first.',
                    'warning'
                );

                return;
            }

            uploadButton.disabled = true;
            uploadButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
        });
    }

    if (closeFeedbackModal) {
        closeFeedbackModal.addEventListener('click', function () {
            closeModal(feedbackModal);
        });
    }

    if (feedbackModal) {
        feedbackModal.addEventListener('click', function (event) {
            if (event.target === feedbackModal) {
                closeModal(feedbackModal);
            }
        });
    }

    document
        .querySelectorAll('[data-open-raw-modal]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(rawUploadModal);
            });
        });

    if (closeRawUploadModal) {
        closeRawUploadModal.addEventListener('click', function () {
            closeModal(rawUploadModal);
        });
    }

    if (rawUploadModal) {
        rawUploadModal.addEventListener('click', function (event) {
            if (event.target === rawUploadModal) {
                closeModal(rawUploadModal);
            }
        });
    }

    if (rawUploadSearch) {
        rawUploadSearch.addEventListener('input', function () {
            const keyword = rawUploadSearch.value.toLowerCase().trim();

            document
                .querySelectorAll('#rawUploadTableBody tr')
                .forEach(function (row) {
                    const searchText = row.dataset.rawSearch || '';

                    row.style.display = searchText.includes(keyword)
                        ? ''
                        : 'none';
                });
        });
    }

    document
        .querySelectorAll('[data-open-records-modal]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(allRecordsModal);
            });
        });

    if (closeAllRecordsModal) {
        closeAllRecordsModal.addEventListener('click', function () {
            closeModal(allRecordsModal);
        });
    }

    if (allRecordsModal) {
        allRecordsModal.addEventListener('click', function (event) {
            if (event.target === allRecordsModal) {
                closeModal(allRecordsModal);
            }
        });
    }

    if (recordsSearch) {
        recordsSearch.addEventListener('input', function () {
            const keyword = recordsSearch.value.toLowerCase().trim();

            document
                .querySelectorAll('#allBatchRecordsTableBody tr')
                .forEach(function (row) {
                    const searchText = row.dataset.search || '';

                    row.style.display = searchText.includes(keyword)
                        ? ''
                        : 'none';
                });
        });
    }

    document
        .querySelectorAll('[data-open-clean-data-modal]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(cleanDataModal);
            });
        });

    if (closeCleanDataModal) {
        closeCleanDataModal.addEventListener('click', function () {
            closeModal(cleanDataModal);
        });
    }

    if (cleanDataModal) {
        cleanDataModal.addEventListener('click', function (event) {
            if (event.target === cleanDataModal) {
                closeModal(cleanDataModal);
            }
        });
    }

    if (cleanDataSearch) {
        cleanDataSearch.addEventListener('input', function () {
            const keyword = cleanDataSearch.value.toLowerCase().trim();

            document
                .querySelectorAll('#cleanDataTableBody tr')
                .forEach(function (row) {
                    const searchText = row.dataset.cleanSearch || '';

                    row.style.display = searchText.includes(keyword)
                        ? ''
                        : 'none';
                });
        });
    }

    document
        .querySelectorAll('[data-delete-batch]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                const deleteUrl = button.dataset.deleteUrl;
                const batchName = button.dataset.deleteName;

                if (deleteForm && deleteUrl) {
                    deleteForm.setAttribute('action', deleteUrl);
                }

                if (deleteFileName) {
                    deleteFileName.textContent =
                        batchName || 'this uploaded file';
                }

                openModal(deleteModal);
            });
        });

    if (cancelDeleteButton) {
        cancelDeleteButton.addEventListener('click', function () {
            closeModal(deleteModal);
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) {
                closeModal(deleteModal);
            }
        });
    }

    document
        .querySelectorAll('.batch-edit-input')
        .forEach(function (input) {
            input.dataset.originalValue = input.value;

            input.addEventListener('input', function () {
                updateEditedRow(input);
            });

            input.addEventListener('change', function () {
                updateEditedRow(input);
            });
        });

    if (saveAllBatchRecordsBtn && bulkUpdateRecordsForm) {
        saveAllBatchRecordsBtn.addEventListener('click', function () {
            if (!hasUnsavedBatchChanges) {
                return;
            }

            saveAllBatchRecordsBtn.disabled = true;
            saveAllBatchRecordsBtn.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            bulkUpdateRecordsForm.submit();
        });
    }

    if (confirmBatchForm) {
        confirmBatchForm.addEventListener('submit', function (event) {
            if (hasUnsavedBatchChanges) {
                event.preventDefault();

                showNotification(
                    'Please save your edited records before marking this batch as Processed.',
                    'warning'
                );

                return;
            }

            if (markBatchProcessedBtn) {
                markBatchProcessedBtn.disabled = true;
                markBatchProcessedBtn.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        [
            feedbackModal,
            rawUploadModal,
            allRecordsModal,
            cleanDataModal,
            deleteModal,
        ].forEach(function (modal) {
            if (modal && modal.classList.contains('show')) {
                closeModal(modal);
            }
        });
    });

    updateBatchSaveState();
});