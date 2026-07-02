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

    const deleteModal = document.getElementById('batchDeleteModal');
    const deleteForm = document.getElementById('batchDeleteForm');
    const deleteFileName = document.getElementById('batchDeleteFileName');
    const cancelDeleteButton = document.getElementById('cancelBatchDelete');

    const editTripRecordModal = document.getElementById('editTripRecordModal');
    const openEditTripRecordModal = document.getElementById('openEditTripRecordModal');
    const closeEditTripRecordModal = document.getElementById('closeEditTripRecordModal');
    const cancelEditTripRecordModal = document.getElementById('cancelEditTripRecordModal');

    const allowedExtensions = ['csv', 'txt', 'pdf', 'xls', 'xlsx'];

    function openModal(modal) {
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.classList.remove('show');
        }
    }

    function setSelectedFile(file) {
        if (!file) {
            return;
        }

        const extension = file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            alert('Please upload only PDF, CSV, TXT, XLS, or XLSX files.');

            if (fileInput) {
                fileInput.value = '';
            }

            if (selectedFileName) {
                selectedFileName.textContent = 'Drag and drop GPS files here';
            }

            if (uploadButton) {
                uploadButton.disabled = true;
            }

            return;
        }

        if (selectedFileName) {
            selectedFileName.textContent = file.name;
        }

        if (uploadButton) {
            uploadButton.disabled = false;
        }
    }

    if (chooseFileBtn && fileInput) {
        chooseFileBtn.addEventListener('click', function () {
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

            const extension = file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                alert('Please upload only PDF, CSV, TXT, XLS, or XLSX files.');
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
                alert('Please select a GPS report first.');
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

    document.querySelectorAll('[data-open-raw-modal]').forEach(function (button) {
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

            document.querySelectorAll('#rawUploadTableBody tr').forEach(function (row) {
                const searchText = row.dataset.rawSearch || '';

                row.style.display = searchText.includes(keyword) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('[data-open-records-modal]').forEach(function (button) {
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

            document.querySelectorAll('#allBatchRecordsTableBody tr').forEach(function (row) {
                const searchText = row.dataset.search || '';

                row.style.display = searchText.includes(keyword) ? '' : 'none';
            });
        });
    }

    if (openEditTripRecordModal) {
        openEditTripRecordModal.addEventListener('click', function () {
            openModal(editTripRecordModal);
        });
    }

    if (closeEditTripRecordModal) {
        closeEditTripRecordModal.addEventListener('click', function () {
            closeModal(editTripRecordModal);
        });
    }

    if (cancelEditTripRecordModal) {
        cancelEditTripRecordModal.addEventListener('click', function () {
            closeModal(editTripRecordModal);
        });
    }

    if (editTripRecordModal) {
        editTripRecordModal.addEventListener('click', function (event) {
            if (event.target === editTripRecordModal) {
                closeModal(editTripRecordModal);
            }
        });
    }

    document.querySelectorAll('[data-delete-batch]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (deleteForm) {
                deleteForm.action = button.dataset.deleteUrl;
            }

            if (deleteFileName) {
                deleteFileName.textContent = button.dataset.deleteName;
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
});