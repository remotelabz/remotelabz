import Remotelabz from '../API';

export class IsoFormHandler {
    constructor() {
        this.selectedFile = null;
        this.uploadedFile = null;
        this.initElements();
        this.bindEvents();
        this.initialize();
    }

    initElements() {
        // Radio buttons et blocs
        this.radios = document.querySelectorAll('.file-source-radio');
        this.uploadBlock = document.querySelector('.file-upload-block');
        this.urlBlock = document.querySelector('.url-block');
        
        // Upload elements
        this.fileInput = document.getElementById('file-input');
        this.uploadZone = document.querySelector('.upload-zone');
        this.uploadContent = document.getElementById('upload-content');
        this.fileSelected = document.getElementById('file-selected');
        this.fileUploaded = document.getElementById('file-uploaded');
        this.browseButton = document.getElementById('browse-button');
        
        // Buttons
        this.uploadButton = document.getElementById('upload-button');
        this.cancelFileButton = document.getElementById('cancel-file');
        this.removeUploadedFileButton = document.getElementById('remove-uploaded-file');
        this.validateUrlBtn = document.getElementById('validateUrl');
        
        // Progress
        this.progressContainer = document.getElementById('upload-progress');
        this.progressBar = document.querySelector('.progress-bar');
        this.progressPercentage = document.querySelector('.progress-percentage');
        
        // Form
        this.form = document.querySelector('form');
        this.submitButton = document.getElementById('submit-button');
        this.uploadedFilenameInput = document.getElementById('uploaded_filename');
        this.urlInput = document.querySelector('input[name="iso[Filename_url]"]');
    }

    bindEvents() {
        // Source type selection
        this.radios.forEach(radio => {
            radio.addEventListener('change', () => this.handleSourceTypeChange());
        });

        // Cards click
        document.querySelectorAll('.file-source-card').forEach(card => {
            card.addEventListener('click', () => this.handleCardClick(card));
        });

        // File operations
        this.browseButton?.addEventListener('click', () => this.fileInput.click());
        this.fileInput?.addEventListener('change', (e) => this.handleFileSelection(e));
        
        // Upload operations
        this.uploadButton?.addEventListener('click', () => this.handleUpload());
        this.cancelFileButton?.addEventListener('click', () => this.resetUploadState());
        this.removeUploadedFileButton?.addEventListener('click', () => this.handleRemoveUploadedFile());
        
        // URL validation
        this.validateUrlBtn?.addEventListener('click', () => this.handleUrlValidation());
        this.urlInput?.addEventListener('input', () => this.resetUrlValidation());
        
        // Drag & drop
        this.setupDragAndDrop();
        
        // Form submission
        this.form?.addEventListener('submit', (e) => this.handleFormSubmit(e));
    }

    initialize() {
        this.updateSourceCards();
    }

    handleSourceTypeChange() {
        const checkedRadio = document.querySelector('input[name="iso[fileSourceType]"]:checked');
        if (checkedRadio?.value === 'upload') {
            this.uploadBlock.style.display = '';
            this.urlBlock.style.display = 'none';
        } else {
            this.uploadBlock.style.display = 'none';
            this.urlBlock.style.display = '';
            this.resetUploadState();
        }
        this.updateSourceCards();
    }

    handleCardClick(card) {
        const radio = card.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    }

    updateSourceCards() {
        document.querySelectorAll('.file-source-card').forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('active', radio?.checked);
        });
    }

    setupDragAndDrop() {
        if (!this.uploadZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, () => {
                this.uploadZone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, () => {
                this.uploadZone.classList.remove('drag-over');
            }, false);
        });

        this.uploadZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileSelection({ target: { files } });
            }
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    handleFileSelection(e) {
        if (e.target.files.length > 0) {
            this.selectedFile = e.target.files[0];
            this.displaySelectedFile(this.selectedFile);
            this.showFileSelected();
        }
    }

    displaySelectedFile(file) {
        if (!this.fileSelected) return;
        
        const fileName = this.fileSelected.querySelector('.file-name');
        const fileSize = this.fileSelected.querySelector('.file-size');
        
        if (fileName) fileName.textContent = file.name;
        if (fileSize) fileSize.textContent = this.formatFileSize(file.size);
    }

    showFileSelected() {
        this.uploadContent?.classList.add('d-none');
        this.fileSelected?.classList.remove('d-none');
        this.fileUploaded?.classList.add('d-none');
    }

    showFileUploaded(uploadData) {
        const uploadedFileName = this.fileUploaded?.querySelector('.uploaded-file-name');
        const uploadedFileSize = this.fileUploaded?.querySelector('.uploaded-file-size');
        
        if (uploadedFileName) uploadedFileName.textContent = uploadData.originalName;
        if (uploadedFileSize) uploadedFileSize.textContent = this.formatFileSize(uploadData.size);
        
        this.uploadContent?.classList.add('d-none');
        this.fileSelected?.classList.add('d-none');
        this.fileUploaded?.classList.remove('d-none');
        
        this.uploadedFile = uploadData;
        if (this.uploadedFilenameInput) {
            this.uploadedFilenameInput.value = uploadData.filename;
        }
    }

    handleUpload() {
        if (!this.selectedFile) return;

        console.log('Starting upload for file:', this.selectedFile.name);
        this.showUploadProgress();
        this.setUploadButtonState(true, 'Uploading...');

        Remotelabz.iso.upload(this.selectedFile, (progressEvent) => {
            this.updateProgress(progressEvent);
        })
        .then(response => {
            this.hideUploadProgress();
            this.setUploadButtonState(false, 'Upload');

            if (response.data.success) {
                this.showFileUploaded({
                    filename: response.data.filename,
                    originalName: this.selectedFile.name,
                    size: this.selectedFile.size
                });
            } else {
                alert('Upload failed: ' + (response.data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            this.hideUploadProgress();
            this.setUploadButtonState(false, 'Upload');
            alert(error?.response?.data?.error || error.message || 'Unknown error');
        });
    }

    handleRemoveUploadedFile() {
        const filename = this.uploadedFilenameInput?.value;
        if (!filename) return;

        Remotelabz.iso.deleteTempFile(filename)
            .then(response => {
                if (response.data.success) {
                    this.resetUploadState();
                } else {
                    alert('Erreur lors de la suppression : ' + (response.data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                alert('Erreur réseau');
            });
    }

    handleUrlValidation() {
        const url = this.urlInput?.value.trim();
        if (!url) {
            alert('Veuillez entrer une URL d\'abord');
            return;
        }

        this.setUrlValidationButtonState(true, 'Validation...');

        Remotelabz.iso.validateUrl(url)
            .then(response => {
                const data = response.data;
                
                if (data.success && data.valid) {
                    this.setUrlValidationButtonState(false, 'Valide', 'success');
                    let infoMessage = 'URL valide';
                    if (data.fileSize) infoMessage += ' - Taille: ' + this.formatFileSize(data.fileSize);
                    if (data.fileName) infoMessage += ' - Fichier: ' + data.fileName;
                    this.showUrlValidationResult(infoMessage, 'success');
                } else {
                    this.setUrlValidationButtonState(false, 'Invalide', 'danger');
                    this.showUrlValidationResult(data.error || 'Erreur de validation inconnue', 'danger');
                }
            })
            .catch(error => {
                this.setUrlValidationButtonState(false, 'Erreur', 'danger');
                this.showUrlValidationResult('Erreur réseau lors de la validation', 'danger');
            });
    }

    resetUrlValidation() {
        if (!this.validateUrlBtn) return;
        
        this.validateUrlBtn.classList.remove('btn-outline-success', 'btn-outline-danger');
        this.validateUrlBtn.classList.add('btn-outline-secondary');
        this.validateUrlBtn.innerHTML = '<i class="fa fa-check"></i> Valider';
        this.validateUrlBtn.disabled = false;

        const existingAlert = this.urlBlock?.querySelector('.url-validation-result');
        if (existingAlert) existingAlert.remove();
    }

    resetUploadState() {
        this.selectedFile = null;
        this.uploadedFile = null;
        
        if (this.uploadedFilenameInput) this.uploadedFilenameInput.value = '';
        if (this.fileInput) this.fileInput.value = '';
        
        this.uploadContent?.classList.remove('d-none');
        this.fileSelected?.classList.add('d-none');
        this.fileUploaded?.classList.add('d-none');
        this.progressContainer?.classList.add('d-none');
    }

    handleFormSubmit(e) {
        const fileSourceType = document.querySelector('input[name="iso[fileSourceType]"]:checked')?.value;
        
        if (fileSourceType === 'upload' && !this.uploadedFile) {
            e.preventDefault();
            alert('Please upload a file first or switch to URL mode');
            return false;
        }
        
        return true;
    }

    // Utility methods
    showUploadProgress() {
        this.progressContainer?.classList.remove('d-none');
        if (this.progressBar) {
            this.progressBar.style.width = '0%';
            this.progressBar.setAttribute('aria-valuenow', '0');
        }
        if (this.progressPercentage) this.progressPercentage.textContent = '0%';
    }

    hideUploadProgress() {
        this.progressContainer?.classList.add('d-none');
    }

    updateProgress(progressEvent) {
        if (progressEvent.lengthComputable) {
            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            if (this.progressBar) {
                this.progressBar.style.width = percent + '%';
                this.progressBar.setAttribute('aria-valuenow', percent);
            }
            if (this.progressPercentage) this.progressPercentage.textContent = percent + '%';
        }
    }

    setUploadButtonState(loading, text) {
        if (!this.uploadButton) return;
        
        this.uploadButton.disabled = loading;
        this.uploadButton.innerHTML = loading 
            ? `<i class="fa fa-spinner fa-spin me-1"></i> ${text}`
            : `<i class="fa fa-upload me-1"></i> ${text}`;
    }

    setUrlValidationButtonState(loading, text, type = 'secondary') {
        if (!this.validateUrlBtn) return;
        
        this.validateUrlBtn.disabled = loading;
        this.validateUrlBtn.classList.remove('btn-outline-success', 'btn-outline-danger', 'btn-outline-secondary');
        this.validateUrlBtn.classList.add(`btn-outline-${type}`);
        
        const icon = loading ? 'spinner fa-spin' : 
                    type === 'success' ? 'check text-success' : 
                    type === 'danger' ? 'times text-danger' : 'check';
        
        this.validateUrlBtn.innerHTML = `<i class="fa fa-${icon}"></i> ${text}`;
    }

    showUrlValidationResult(message, type) {
        const existingAlert = this.urlBlock?.querySelector('.url-validation-result');
        if (existingAlert) existingAlert.remove();

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} mt-2 url-validation-result`;
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        const inputGroup = this.urlBlock?.querySelector('.input-group');
        if (inputGroup) {
            inputGroup.parentNode.insertBefore(alertDiv, inputGroup.nextSibling);
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    new IsoFormHandler();
});