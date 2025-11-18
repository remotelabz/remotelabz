/**
 * This file implements JavaScript for device form functionality
 */

$(function () {
    console.log("coucou");
    // Configuration du sélecteur d'icône
    const iconSelect = document.querySelector('select[name*="[icon]"]');
    const iconDisplay = document.getElementById('iconDisplay');
    const iconPreview = document.getElementById('iconPreview');
    const iconDropdown = document.getElementById('iconDropdown');
    const iconOptions = document.getElementById('iconOptions');
    const iconSearch = document.getElementById('iconSearch');
    
    // Récupérer le chemin de base des icônes depuis l'attribut data
    const iconBasePath = iconDisplay?.dataset.iconPath || '/build/images/icons/';
    
    // Récupérer les options du select
    const icons = {};
    if (iconSelect) {
        Array.from(iconSelect.options).forEach(option => {
            if (option.value) {
                icons[option.value] = option.text;
            }
        });
    }
    
    // Créer les options visuelles
    function createIconOptions(filter = '') {
        if (!iconOptions) return;
        iconOptions.innerHTML = '';
        
        Object.entries(icons).forEach(([value, label]) => {
            if (filter === '' || label.toLowerCase().includes(filter.toLowerCase()) || value.toLowerCase().includes(filter.toLowerCase())) {
                const option = document.createElement('div');
                option.className = 'icon-option';
                option.dataset.value = value;
                
                option.innerHTML = `
                    <img src="${iconBasePath}${value}" alt="${label}" onerror="this.style.display='none'">
                    <span>${label}</span>
                `;
                
                option.addEventListener('click', () => selectIcon(value, label));
                iconOptions.appendChild(option);
            }
        });
    }
    
    // Sélectionner une icône
    function selectIcon(value, label) {
        if (!iconSelect) return;
        iconSelect.value = value;
        if (iconDisplay) iconDisplay.value = label;
        
        if (iconPreview) {
            if (value) {
                iconPreview.innerHTML = `<img src="${iconBasePath}${value}" alt="${label}">`;
                iconPreview.classList.remove('empty');
            } else {
                iconPreview.innerHTML = '<i class="fas fa-image"></i>';
                iconPreview.classList.add('empty');
            }
        }
        
        // Mettre à jour la sélection visuelle
        document.querySelectorAll('.icon-option').forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.value === value);
        });
        
        if (iconDropdown) iconDropdown.style.display = 'none';
    }
    
    // Initialiser la valeur actuelle
    if (iconSelect && iconSelect.value) {
        const currentLabel = iconSelect.options[iconSelect.selectedIndex].text;
        selectIcon(iconSelect.value, currentLabel);
    }
    
    // Événements
    if (iconDisplay) {
        iconDisplay.addEventListener('click', () => {
            createIconOptions();
            if (iconDropdown) {
                iconDropdown.style.display = iconDropdown.style.display === 'block' ? 'none' : 'block';
            }
            if (iconSearch) iconSearch.focus();
        });
    }
    
    if (iconSearch) {
        iconSearch.addEventListener('input', (e) => {
            createIconOptions(e.target.value);
        });
    }
    
    // Fermer le dropdown en cliquant ailleurs
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.icon-selector-container') && iconDropdown) {
            iconDropdown.style.display = 'none';
        }
    });
    
    // Auto-calcul du CPU
    const coreInput = document.querySelector('input[name*="nbCore"]');
    const socketInput = document.querySelector('input[name*="nbSocket"]');
    const threadInput = document.querySelector('input[name*="nbThread"]');
    const cpuInput = document.querySelector('input[name*="nbCpu"]');
    
    if (coreInput && socketInput && threadInput && cpuInput) {
        function updateCpuCount() {
            const cores = parseInt(coreInput.value) || 1;
            const sockets = parseInt(socketInput.value) || 1;
            const threads = parseInt(threadInput.value) || 1;
            const calculated = cores * sockets * threads;
            
            if (parseInt(cpuInput.value) < calculated) {
                cpuInput.value = calculated;
                cpuInput.style.borderColor = '#28a745';
                setTimeout(() => {
                    cpuInput.style.borderColor = '';
                }, 2000);
            }
        }
        
        coreInput.addEventListener('input', updateCpuCount);
        socketInput.addEventListener('input', updateCpuCount);
        threadInput.addEventListener('input', updateCpuCount);
    }
    
    // Gestion de l'Operating System et des champs dépendants
    const osSelect = document.querySelector('select[name*="[operatingSystem]"]');
    const protocolSelect = document.querySelector('select[name*="[controlProtocolTypes]"]');
    const isoFieldsContainer = document.getElementById('iso-fields-container');
    
    // Fonction pour mettre à jour les protocoles de contrôle
    function updateProtocols() {
        if (!osSelect || !protocolSelect) return;
        
        const selectedOption = osSelect.options[osSelect.selectedIndex];
        const osText = selectedOption.text.toLowerCase();
        
        // Désélectionner tous les protocoles
        Array.from(protocolSelect.options).forEach(option => {
            option.selected = false;
        });
        
        // Sélectionner automatiquement selon l'hyperviseur
        Array.from(protocolSelect.options).forEach(option => {
            const protocolName = option.text.toLowerCase();
            
            if (osText.includes('lxc') && protocolName === 'login') {
                option.selected = true;
            } else if (osText.includes('qemu') && protocolName === 'vnc') {
                option.selected = true;
            }
        });
    }
    
    // Fonction pour gérer l'affichage des champs ISO
    function toggleIsoFields() {
        if (!osSelect || !isoFieldsContainer) return;
        
        const selectedOption = osSelect.options[osSelect.selectedIndex];
        const hasFlavorDisk = selectedOption.getAttribute('data-has-flavor-disk') === '1';
        
        // Rechercher les éléments
        const cdromBusRow = isoFieldsContainer.querySelector('[id*="cdrom_bus_type"]')?.closest('.mb-3, .form-group, .row');
        const isosRow = isoFieldsContainer.querySelector('[id*="isos"]')?.closest('.mb-3, .form-group, .row');
        
        if (hasFlavorDisk) {
            // Afficher les champs
            if (cdromBusRow) {
                cdromBusRow.style.display = '';
            }
            if (isosRow) {
                isosRow.style.display = '';
            }
        } else {
            // Masquer les champs
            if (cdromBusRow) {
                cdromBusRow.style.display = 'none';
            }
            if (isosRow) {
                isosRow.style.display = 'none';
            }
            
            // Réinitialiser les sélections
            const cdromBusSelect = document.querySelector('select[name*="[cdrom_bus_type]"]');
            if (cdromBusSelect) {
                cdromBusSelect.value = '';
            }
            
            const isosSelect = document.querySelector('select[name*="[isos]"]');
            if (isosSelect) {
                Array.from(isosSelect.options).forEach(option => {
                    option.selected = false;
                });
            }
        }
    }
    
    // Événement au changement d'OS
    if (osSelect) {
        osSelect.addEventListener('change', function() {
            updateProtocols();
            toggleIsoFields();
        });
        
        // Appliquer au chargement de la page
        toggleIsoFields();
    }
});