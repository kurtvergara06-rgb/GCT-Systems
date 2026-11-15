document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('gpsFileInput');
    const dropzone = document.getElementById('gpsDropzone');
    const chooseFileBtn = document.getElementById('chooseGpsFileBtn');
    const selectedFileName = document.getElementById('selectedFileName');
    const uploadForm = document.getElementById('gpsUploadForm');

    if (!fileInput || !dropzone || !chooseFileBtn || !uploadForm) {
        return;
    }

    const isAllowedFile = (file) => {
        const extension = file.name.split('.').pop().toLowerCase();

        return ['csv', 'txt'].includes(extension);
    };

    const uploadSelectedFile = (file) => {
        if (!file) {
            return;
        }

        if (!isAllowedFile(file)) {
            alert('Please upload only CSV or TXT GPS reports.');
            return;
        }

        selectedFileName.textContent = `Uploading ${file.name}...`;

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        fileInput.files = dataTransfer.files;

        uploadForm.submit();
    };

    chooseFileBtn.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];

        uploadSelectedFile(file);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('drag-active');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('drag-active');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();

        const file = event.dataTransfer.files[0];

        uploadSelectedFile(file);
    });
});