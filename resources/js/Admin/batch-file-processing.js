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

    function setSelectedFile(file) {
        if (!file) {
            return;
        }

        const extension = file.name.split('.').pop().toLowerCase();

        if (extension !== 'csv' && extension !== 'txt') {
            alert('Please upload only CSV or TXT files.');

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

            if (extension !== 'csv' && extension !== 'txt') {
                alert('Please upload only CSV or TXT files.');
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
            uploadButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
        });
    }

    if (closeFeedbackModal && feedbackModal) {
        closeFeedbackModal.addEventListener('click', function () {
            feedbackModal.classList.remove('show');
        });
    }

    if (feedbackModal) {
        feedbackModal.addEventListener('click', function (event) {
            if (event.target === feedbackModal) {
                feedbackModal.classList.remove('show');
            }
        });
    }

    document.querySelectorAll('[data-open-raw-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (rawUploadModal) {
                rawUploadModal.classList.add('show');
            }
        });
    });

    if (closeRawUploadModal && rawUploadModal) {
        closeRawUploadModal.addEventListener('click', function () {
            rawUploadModal.classList.remove('show');
        });
    }

    if (rawUploadModal) {
        rawUploadModal.addEventListener('click', function (event) {
            if (event.target === rawUploadModal) {
                rawUploadModal.classList.remove('show');
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
            if (allRecordsModal) {
                allRecordsModal.classList.add('show');
            }
        });
    });

    if (closeAllRecordsModal && allRecordsModal) {
        closeAllRecordsModal.addEventListener('click', function () {
            allRecordsModal.classList.remove('show');
        });
    }

    if (allRecordsModal) {
        allRecordsModal.addEventListener('click', function (event) {
            if (event.target === allRecordsModal) {
                allRecordsModal.classList.remove('show');
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

    document.querySelectorAll('[data-delete-batch]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (deleteForm) {
                deleteForm.action = button.dataset.deleteUrl;
            }

            if (deleteFileName) {
                deleteFileName.textContent = button.dataset.deleteName;
            }

            if (deleteModal) {
                deleteModal.classList.add('show');
            }
        });
    });

    if (cancelDeleteButton && deleteModal) {
        cancelDeleteButton.addEventListener('click', function () {
            deleteModal.classList.remove('show');
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) {
                deleteModal.classList.remove('show');
            }
        });
    }
});