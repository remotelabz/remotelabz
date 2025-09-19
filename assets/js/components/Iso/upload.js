import Remotelabz from '../API';

document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('file-input');
    const uploadButton = document.getElementById('upload-button');
    const progressContainer = document.getElementById('upload-progress');
    const progressBar = document.querySelector('.progress-bar');
    const progressPercentage = document.querySelector('.progress-percentage');
    const uploadedFilenameInput = document.getElementById('uploaded_filename');
    let selectedFile = null;


    fileInput.addEventListener('change', function (e) {
        if (e.target.files.length > 0) {
            selectedFile = e.target.files[0];
        }
    });

    uploadButton.addEventListener('click', function () {
        if (!selectedFile) return;
        console.log('Starting upload for file:', selectedFile.name);

        progressContainer.classList.remove('d-none');
        progressBar.style.width = '0%';
        progressPercentage.textContent = '0%';
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Uploading...';

        Remotelabz.iso.upload(selectedFile, function (progressEvent) {
            if (progressEvent.lengthComputable) {
                const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.setAttribute('aria-valuenow', percent);
                progressPercentage.textContent = percent + '%';
            }
        })
        .then(response => {
            uploadButton.disabled = false;
            uploadButton.innerHTML = '<i class="fa fa-upload me-1"></i> Upload';
            progressContainer.classList.add('d-none');

            if (response.data.success) {
                uploadedFilenameInput.value = response.data.filename;
                // Affiche le fichier uploadé (ajoute ta logique ici)
            } else {
                alert('Upload failed: ' + (response.data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            uploadButton.disabled = false;
            uploadButton.innerHTML = '<i class="fa fa-upload me-1"></i> Upload';
            progressContainer.classList.add('d-none');
            alert(error?.response?.data?.error || error.message || 'Unknown error');
        });
    });

    
});