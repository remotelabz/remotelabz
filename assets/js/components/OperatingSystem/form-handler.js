import Remotelabz from '../API';

export class OperatingSystemFormHandler {
    constructor() {
        this.selectedFile = null;
        this.uploadedFile = null;
        this.initElements();
        this.bindEvents();
        this.initialize();
    }

    initialize() {
        // Initialisation des options hyperviseur et image source
        this.loadExistingValues();
        this.toggleHypervisorOptions();
        this.toggleImageSourceBlocks();

        // Set default selection for QEMU if no selection
        if (this.qemuOptions && this.qemuOptions.style.display === 'block' && !document.querySelector('.image-source-radio:checked')) {
            const sourceFilename = document.getElementById('source-filename');
            if (sourceFilename) {
                sourceFilename.checked = true;
                sourceFilename.dispatchEvent(new Event('change'));
            }
        }
    }

    toggleHypervisorOptions() {
        if (!this.hypervisorSelect) return;
        const selectedOption = this.hypervisorSelect.options[this.hypervisorSelect.selectedIndex];
        const hypervisorName = selectedOption ? selectedOption.text.toLowerCase() : '';

        if (hypervisorName.includes('qemu')) {
            this.qemuOptions.style.display = 'block';
            this.lxcOptions.style.display = 'none';
        } else if (hypervisorName.includes('lxc')) {
            this.qemuOptions.style.display = 'none';
            this.lxcOptions.style.display = 'block';
        } else {
            this.qemuOptions.style.display = 'none';
            this.lxcOptions.style.display = 'none';
        }
        this.loadExistingValues();
    }

    toggleImageSourceBlocks() {
        const selectedSource = document.querySelector('.image-source-radio:checked');
        if (!this.fileUploadBlock || !this.urlBlock || !this.filenameOnlyBlock) return;

        this.fileUploadBlock.style.display = 'none';
        this.urlBlock.style.display = 'none';
        this.filenameOnlyBlock.style.display = 'none';

        if (selectedSource) {
            switch (selectedSource.value) {
                case 'upload':
                    this.fileUploadBlock.style.display = 'block';
                    break;
                case 'url':
                    this.urlBlock.style.display = 'block';
                    break;
                case 'filename':
                    this.filenameOnlyBlock.style.display = 'block';
                    break;
            }
        }
    }

    loadExistingValues() {
        // Récupérer le nom de l'hyperviseur sélectionné
        const selectedOption = this.hypervisorSelect?.options[this.hypervisorSelect.selectedIndex];
        const hypervisorName = selectedOption ? selectedOption.text.toLowerCase() : '';

        console.log('Loading existing values for hypervisor:', hypervisorName);

        // Charger les valeurs pour LXC
        if (hypervisorName.includes('lxc')) {
            // Pour LXC, charger depuis originalFilenameField OU depuis l'attribut data
            const existingFilename = this.originalFilenameField?.value || 
                                    this.lxcFilenameInput?.getAttribute('data-initial-value') ||
                                    this.lxcFilenameInput?.value;
            
            if (existingFilename && this.lxcFilenameInput) {
                console.log('Setting LXC filename:', existingFilename);
                this.lxcFilenameInput.value = existingFilename;
                this.lxcFilenameInput.readOnly = true;  // Ajoutez cette ligne
                
                // Synchroniser avec le champ hidden
                if (this.originalFilenameField) {
                    this.originalFilenameField.value = existingFilename;
                }
                if (this.originalFilenameHidden) {
                    this.originalFilenameHidden.value = existingFilename;
                }
            }
        } 
        // Charger les valeurs pour QEMU
        else if (hypervisorName.includes('qemu')) {
            const existingFilename = this.originalFilenameField?.value;
            const existingUrl = this.urlInput?.value;
            
            console.log('Existing QEMU filename:', existingFilename);
            console.log('Existing QEMU URL:', existingUrl);
            
            // Si on a un filename, remplir le champ QEMU
            if (existingFilename && this.qemuFilenameInput) {
                this.qemuFilenameInput.value = existingFilename;
                this.qemuFilenameInput.readOnly = true;

                if (this.originalFilenameHidden) {
                   this.originalFilenameHidden.value = existingFilename;
                }
            }
            
            // Déterminer quel type de source était utilisé lors de l'édition
            if (!document.querySelector('.image-source-radio:checked')) {
                if (existingUrl && existingUrl.trim() !== '') {
                    // Si on a une URL, sélectionner le mode URL
                    const sourceUrl = document.getElementById('source-url');
                    if (sourceUrl) {
                        sourceUrl.checked = true;
                        sourceUrl.dispatchEvent(new Event('change'));
                    }
                } else if (existingFilename && existingFilename.trim() !== '') {
                    // Si on a un filename mais pas d'URL, c'est filename-only
                    const sourceFilename = document.getElementById('source-filename');
                    if (sourceFilename) {
                        sourceFilename.checked = true;
                        sourceFilename.dispatchEvent(new Event('change'));
                    }
                }
            }
        }
    }

    syncFilenameValues() {
        if (!this.originalFilenameField) return;
        const selectedOption = this.hypervisorSelect.options[this.hypervisorSelect.selectedIndex];
        const hypervisorName = selectedOption ? selectedOption.text.toLowerCase() : '';

        if (hypervisorName.includes('lxc')) {
            if (this.lxcFilenameInput) {
                this.originalFilenameField.value = this.lxcFilenameInput.value;
            }
        } else if (hypervisorName.includes('qemu')) {
            const selectedSource = document.querySelector('.image-source-radio:checked');
            if (selectedSource && selectedSource.value === 'filename') {
                if (this.qemuFilenameInput) {
                    this.originalFilenameField.value = this.qemuFilenameInput.value;
                }
            } else if (selectedSource && selectedSource.value === 'upload') {
                // Pour l'upload, on utilise le champ uploaded_filename
                if (this.uploadedFilenameInput && this.uploadedFilenameInput.value) {
                    this.originalFilenameField.value = this.uploadedFilenameInput.value;
                }
            } else if (selectedSource && selectedSource.value === 'url') {
                // Pour l'URL, on vide le champ filename
                this.originalFilenameField.value = '';
            }
        }
    }

    initElements() {
        this.hypervisorSelect = document.getElementById('hypervisor-select');
        this.qemuOptions = document.getElementById('qemu-options');
        this.lxcOptions = document.getElementById('lxc-options');
        this.imageSourceRadios = document.querySelectorAll('.image-source-radio');
        this.imageSourceCards = document.querySelectorAll('.image-source-card');
        this.fileUploadBlock = document.querySelector('.file-upload-block');
        this.urlBlock = document.querySelector('.url-block');
        this.filenameOnlyBlock = document.querySelector('.filename-only-block');
        this.fileInput = document.getElementById('file-input');
        this.browseButton = document.getElementById('browse-button');
        this.qemuFilenameInput = document.getElementById('qemu-filename-input');
        this.lxcFilenameInput = document.getElementById('lxc-filename-input');
        this.originalFilenameField = document.querySelector('input[name*="image_Filename"]');
        this.originalFilenameHidden = document.querySelector('input[name*="original_filename"]');

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
        
        // CORRECTION: Essayer différentes façons de trouver le champ caché
        this.uploadedFilenameInput = document.getElementById('uploaded-file-name') 
            || document.querySelector('input[name="operating_system[uploaded_filename]"]')
            || document.querySelector('input[id*="uploaded_filename"]');
            
        this.urlInput = document.querySelector('input[name="operating_system[imageUrl]"]');
    }

    bindEvents() {
        if (this.hypervisorSelect) {
            this.hypervisorSelect.addEventListener('change', () => {
                this.toggleHypervisorOptions();
                this.toggleImageSourceBlocks();
                this.loadExistingValues();
            });
        }

        if (this.qemuFilenameInput) {
            this.qemuFilenameInput.addEventListener('input', () => this.syncFilenameValues());
        }
        if (this.lxcFilenameInput) {
            this.lxcFilenameInput.addEventListener('input', () => this.syncFilenameValues());
        }

        this.imageSourceRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                this.imageSourceCards.forEach(card => {
                    card.classList.toggle('active', card.dataset.sourceType === radio.value);
                });
                this.toggleImageSourceBlocks();
                this.syncFilenameValues();
            });
        });

        this.imageSourceCards.forEach(card => {
            card.addEventListener('click', function() {
                const radio = card.querySelector('.image-source-radio');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });

        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                this.syncFilenameValues();
            });
        }

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

    handleSourceTypeChange() {
        const checkedRadio = document.querySelector('input[name="operating_system[fileSourceType]"]:checked');
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
        } else {
            console.error('uploadedFilenameInput not found! Trying fallback...');
            
            // Fallback: essayer de trouver le champ par différents moyens
            const fallbackInput = document.getElementById('operating_system_uploaded_filename') 
                || document.querySelector('input[name*="uploaded_filename"]')
                || document.querySelector('input[type="hidden"]');
                
            if (fallbackInput) {
                fallbackInput.value = uploadData.filename;
                this.uploadedFilenameInput = fallbackInput;
            } else {
                // En dernier recours, créer le champ
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'operating_system[uploaded_filename]';
                hiddenInput.id = 'uploaded_filename';
                hiddenInput.value = uploadData.filename;
                this.form.appendChild(hiddenInput);
                this.uploadedFilenameInput = hiddenInput;
            }
        }
        
        // Synchroniser avec le champ filename principal
        this.syncFilenameValues();
    }

    handleUpload() {
        if (!this.selectedFile) return;

        this.showUploadProgress();
        this.setUploadButtonState(true, 'Uploading...');

        Remotelabz.os.upload(this.selectedFile, (progressEvent) => {
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

        Remotelabz.os.deleteTempFile(filename)
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

        Remotelabz.os.validateUrl(url)
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
        
        // Synchroniser avec le champ filename principal
        this.syncFilenameValues();
    }

    handleFormSubmit(e) {
        this.debugFormState();

        console.log('Form submission attempt');

        // Synchroniser avant validation
        this.syncFilenameValues();

        // Récupérer le type d'hyperviseur
        const selectedOption = this.hypervisorSelect?.options[this.hypervisorSelect.selectedIndex];
        const hypervisorName = selectedOption ? selectedOption.text.toLowerCase() : '';
        
        console.log('Hypervisor type:', hypervisorName);

        if (hypervisorName.includes('lxc')) {
            // Pour LXC, seulement valider le nom du template
            const lxcFilenameValue = this.lxcFilenameInput?.value?.trim();
            if (!lxcFilenameValue) {
                e.preventDefault();
                alert('Veuillez entrer un nom de template LXC.');
                return false;
            }
        } else if (hypervisorName.includes('qemu')) {
            // Pour QEMU, valider selon le type de source sélectionné
            const activeSourceCard = document.querySelector('.image-source-card.active[data-source-type]');
            const fileSourceType = activeSourceCard ? activeSourceCard.dataset.sourceType : null;
            
            console.log('File source type:', fileSourceType);

            if (fileSourceType === 'upload') {
                const uploadedFilename = this.uploadedFilenameInput?.value;
                if (!uploadedFilename || uploadedFilename.trim() === '') {
                    e.preventDefault();
                    if (this.selectedFile) {
                        alert('Veuillez uploader le fichier sélectionné avant de soumettre le formulaire.');
                    } else {
                        alert('Veuillez sélectionner et uploader un fichier, ou changer de mode.');
                    }
                    return false;
                }
            } else if (fileSourceType === 'url') {
                const urlValue = this.urlInput?.value?.trim();
                if (!urlValue) {
                    e.preventDefault();
                    alert('Veuillez entrer une URL valide ou changer de mode.');
                    return false;
                }
            } else if (fileSourceType === 'filename') {
                const filenameValue = this.qemuFilenameInput?.value?.trim();
                if (!filenameValue) {
                    e.preventDefault();
                    alert('Veuillez entrer un nom de fichier.');
                    return false;
                }
            } else {
                e.preventDefault();
                alert('Veuillez sélectionner un type de source (Upload, URL ou Nom de fichier).');
                return false;
            }
        } else {
            e.preventDefault();
            alert('Veuillez sélectionner un hyperviseur.');
            return false;
        }

        console.log('Form validation passed');
        this.debugFormState();

        return true;
    }

    debugFormState() {
        console.log('=== Form Debug State ===');
        console.log('Selected file:', this.selectedFile);
        console.log('Uploaded file:', this.uploadedFile);
        console.log('Uploaded filename input:', this.uploadedFilenameInput?.value);
        console.log('URL input:', this.urlInput?.value);
        console.log('Original filename field:', this.originalFilenameField?.value);
        console.log('QEMU filename input:', this.qemuFilenameInput?.value);
        console.log('LXC filename input:', this.lxcFilenameInput?.value);
        
        const activeSourceCard = document.querySelector('.image-source-card.active[data-source-type]');
        console.log('Active source card:', activeSourceCard?.dataset.sourceType);
        
        // Vérifier tous les éléments du formulaire
        console.log('Form elements:');
        const formData = new FormData(this.form);
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
        console.log('========================');
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
    new OperatingSystemFormHandler();
});