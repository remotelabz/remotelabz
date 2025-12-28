/**
 * User Import Results JavaScript
 * Handles password visibility toggle, copy to clipboard, and CSV export
 * 
 * File: assets/js/user-import-results.js
 * 
 * Usage: Add this entry to webpack.config.js:
 * .addEntry('user-import-results', './assets/js/user-import-results.js')
 * 
 * Then in the template:
 * {{ encore_entry_script_tags('user-import-results') }}
 */

(function() {
    'use strict';

    /**
     * Initialize when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initPasswordToggle();
        initPasswordCopy();
        initCSVExport();
    });

    /**
     * Toggle password visibility
     */
    function initPasswordToggle() {
        const toggleButtons = document.querySelectorAll('.toggle-password');
        
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (!input || !icon) return;
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }

    /**
     * Copy password to clipboard
     */
    function initPasswordCopy() {
        const copyButtons = document.querySelectorAll('.copy-password');
        
        copyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                
                if (!input) return;
                
                // Copy to clipboard using modern Clipboard API
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(function() {
                        showCopySuccess(button);
                    }).catch(function(err) {
                        console.error('Failed to copy password:', err);
                        // Fallback to legacy method
                        legacyCopyToClipboard(input.value, button);
                    });
                } else {
                    // Fallback for older browsers
                    legacyCopyToClipboard(input.value, button);
                }
            });
        });
    }

    /**
     * Legacy method to copy text to clipboard
     * @param {string} text - Text to copy
     * @param {HTMLElement} button - Button element for visual feedback
     */
    function legacyCopyToClipboard(text, button) {
        const tempInput = document.createElement('input');
        tempInput.value = text;
        tempInput.style.position = 'absolute';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(button);
            }
        } catch (err) {
            console.error('Failed to copy password:', err);
        }
        
        document.body.removeChild(tempInput);
    }

    /**
     * Show visual feedback when password is copied
     * @param {HTMLElement} button - Button element
     */
    function showCopySuccess(button) {
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i>';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }

    /**
     * Export table data to CSV
     */
    function initCSVExport() {
        const exportButton = document.getElementById('exportCSV');
        
        if (!exportButton) return;
        
        exportButton.addEventListener('click', function() {
            const table = document.getElementById('importedUsersTable');
            
            if (!table) {
                console.error('Table not found');
                return;
            }
            
            const csv = generateCSV(table);
            downloadCSV(csv, 'imported_users_' + getCurrentDate() + '.csv');
        });
    }

    /**
     * Generate CSV from table
     * @param {HTMLElement} table - Table element
     * @returns {string} CSV content
     */
    function generateCSV(table) {
        const csv = [];
        
        // Headers - exclude "Password Source", "Email Sent", and "Actions" columns
        const headers = [];
        const headerCells = table.querySelectorAll('thead th');
        const columnsToExclude = [5, 6, 7]; // Password Source, Email Sent, Actions (0-based index)
        
        headerCells.forEach(function(th, index) {
            if (!columnsToExclude.includes(index)) {
                headers.push('"' + th.textContent.trim() + '"');
            }
        });
        csv.push(headers.join(','));
        
        // Rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(tr) {
            const row = [];
            const cells = tr.querySelectorAll('td');
            
            cells.forEach(function(td, index) {
                // Skip excluded columns
                if (columnsToExclude.includes(index)) {
                    return;
                }
                
                // Password column (index 4) - get value from input
                if (index === 4) {
                    const input = td.querySelector('input');
                    row.push('"' + (input ? input.value : '') + '"');
                } 
                // Other columns
                else {
                    // Escape quotes in cell content
                    const cellText = td.textContent.trim().replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }
            });
            
            if (row.length > 0) {
                csv.push(row.join(','));
            }
        });
        
        return csv.join('\n');
    }

    /**
     * Download CSV file
     * @param {string} content - CSV content
     * @param {string} filename - Filename
     */
    function downloadCSV(content, filename) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (navigator.msSaveBlob) {
            // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    }

    /**
     * Get current date in YYYY-MM-DD format
     * @returns {string} Current date
     */
    function getCurrentDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

})();