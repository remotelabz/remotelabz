/**
 * Modern Circular Gauges
 * Creates animated circular gauges with glowing dots
 * 
 * @author RemoteLabz
 */

/**
 * Creates a single circular gauge
 * @param {string} type - Gauge type (cpu, memory, disk, lxcfs)
 * @param {number} value - Value percentage (0-100)
 * @param {string} label - Display label
 * @returns {HTMLElement} The gauge container element
 */
function createGauge(type, value, label) {
    const container = document.createElement('div');
    container.className = 'gauge-container';
    
    const radius = 80;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (value / 100) * circumference;
    const numDots = 36;
    
    // Calculate which dots should be active
    const activeDots = Math.floor((value / 100) * numDots);
    
    let dotsHTML = '';
    for (let i = 0; i < numDots; i++) {
        const angle = (i / numDots) * 360;
        const radians = (angle * Math.PI) / 180;
        const dotRadius = radius + 15;
        const x = 100 + dotRadius * Math.cos(radians);
        const y = 100 + dotRadius * Math.sin(radians);
        
        if (i < activeDots) {
            dotsHTML += `<circle class="gauge-dot-active ${type}" cx="${x}" cy="${y}" r="3" style="animation-delay: ${i * 0.02}s" />`;
        } else {
            dotsHTML += `<circle cx="${x}" cy="${y}" r="2" fill="#4a4a4a" />`;
        }
    }
    
    container.innerHTML = `
        <svg class="gauge-svg" viewBox="0 0 200 200">
            <!-- Background circle -->
            <circle class="gauge-bg" cx="100" cy="100" r="${radius}" />
            
            <!-- Progress circle -->
            <circle 
                class="gauge-progress ${type}" 
                cx="100" 
                cy="100" 
                r="${radius}"
                stroke-dasharray="${circumference}"
                stroke-dashoffset="${offset}"
            />
            
            <!-- Glowing dots -->
            ${dotsHTML}
        </svg>
        
        <div class="gauge-label">
            <div class="gauge-title">${label}</div>
            <div class="gauge-value">${value}%</div>
        </div>
    `;
    
    return container;
}

/**
 * Updates an existing gauge with a new value
 * @param {HTMLElement} container - The gauge container
 * @param {number} newValue - New value percentage (0-100)
 */
function updateGauge(container, newValue) {
    const progressCircle = container.querySelector('.gauge-progress');
    const valueLabel = container.querySelector('.gauge-value');
    const svg = container.querySelector('.gauge-svg');
    
    const radius = 70;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (newValue / 100) * circumference;
    
    // Update progress circle
    progressCircle.style.strokeDashoffset = offset;
    
    // Update value label
    valueLabel.textContent = `${newValue}%`;
    
    // Update dots
    const numDots = 36;
    const activeDots = Math.floor((newValue / 100) * numDots);
    const type = progressCircle.classList[1]; // Get gauge type (cpu, memory, etc.)
    
    // Remove old dots
    const oldDots = svg.querySelectorAll('circle:not(.gauge-bg):not(.gauge-progress)');
    oldDots.forEach(dot => dot.remove());
    
    // Add new dots
    let dotsHTML = '';
    for (let i = 0; i < numDots; i++) {
        const angle = (i / numDots) * 360 - 90;
        const radians = (angle * Math.PI) / 180;
        const dotRadius = radius + 15;
        const x = 100 + dotRadius * Math.cos(radians);
        const y = 100 + dotRadius * Math.sin(radians);
        
        if (i < activeDots) {
            dotsHTML += `<circle class="gauge-dot-active ${type}" cx="${x}" cy="${y}" r="3" style="animation-delay: ${i * 0.02}s" />`;
        } else {
            dotsHTML += `<circle cx="${x}" cy="${y}" r="2" fill="#4a4a4a" />`;
        }
    }
    
    // Insert new dots after progress circle
    const progressElement = svg.querySelector('.gauge-progress');
    progressElement.insertAdjacentHTML('afterend', dotsHTML);
}

/**
 * Initializes gauges from DOM elements
 * Looks for elements with data-gauge attributes
 */
function initGauges() {
    const gaugeElements = document.querySelectorAll('[data-gauge]');
    
    gaugeElements.forEach(element => {
        const type = element.getAttribute('data-gauge-type') || 'cpu';
        const value = parseInt(element.getAttribute('data-gauge-value')) || 0;
        const label = element.getAttribute('data-gauge-label') || type.toUpperCase();
        
        const gauge = createGauge(type, value, label);
        element.appendChild(gauge);
    });
}

/**
 * Draws gauges for worker stats
 * To be called from resources page
 */
function drawWorkerGauges() {
    // CPU gauges
    const cpus = document.querySelectorAll('p[name="cpu"]');
    cpus.forEach((cpu) => {
        const cpuText = cpu.textContent.trim();
        const match = cpuText.match(/(\d+)/);
        
        if (match) {
            const value = parseInt(match[1]);
            const cardBody = cpu.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            
            // Clear existing content
            statsCircle.innerHTML = '';
            
            // Create and insert gauge
            const gauge = createGauge('cpu', value, 'CPU');
            const svg = gauge.querySelector('.gauge-svg');
            const label = gauge.querySelector('.gauge-label');
            
            statsCircle.appendChild(svg);
            statsCircle.appendChild(label);
            statsCircle.classList.add('gauge-container');
        } else {
            // Show NA if no value
            const cardBody = cpu.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            statsCircle.innerHTML = '<div class="gauge-label"><div class="gauge-title">CPU</div><div class="gauge-value">NA</div></div>';
            statsCircle.classList.add('gauge-container');
        }
    });
    
    // Memory gauges
    const memories = document.querySelectorAll('p[name="memory"]');
    memories.forEach((memory) => {
        const memoryText = memory.textContent.trim();
        const match = memoryText.match(/(\d+)/);
        
        if (match) {
            const value = parseInt(match[1]);
            const cardBody = memory.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            
            statsCircle.innerHTML = '';
            const gauge = createGauge('memory', value, 'Memory');
            const svg = gauge.querySelector('.gauge-svg');
            const label = gauge.querySelector('.gauge-label');
            
            statsCircle.appendChild(svg);
            statsCircle.appendChild(label);
            statsCircle.classList.add('gauge-container');
        } else {
            const cardBody = memory.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            statsCircle.innerHTML = '<div class="gauge-label"><div class="gauge-title">Memory</div><div class="gauge-value">NA</div></div>';
            statsCircle.classList.add('gauge-container');
        }
    });
    
    // Disk gauges
    const disks = document.querySelectorAll('p[name="disk"]');
    disks.forEach((disk) => {
        const diskText = disk.textContent.trim();
        const match = diskText.match(/(\d+)/);
        
        if (match) {
            const value = parseInt(match[1]);
            const cardBody = disk.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            
            statsCircle.innerHTML = '';
            const gauge = createGauge('disk', value, 'Disk');
            const svg = gauge.querySelector('.gauge-svg');
            const label = gauge.querySelector('.gauge-label');
            
            statsCircle.appendChild(svg);
            statsCircle.appendChild(label);
            statsCircle.classList.add('gauge-container');
        } else {
            const cardBody = disk.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            statsCircle.innerHTML = '<div class="gauge-label"><div class="gauge-title">Disk</div><div class="gauge-value">NA</div></div>';
            statsCircle.classList.add('gauge-container');
        }
    });
    
    // LXCFS gauges
    const lxcfses = document.querySelectorAll('p[name="lxcfs"]');
    lxcfses.forEach((lxcfs) => {
        const lxcfsText = lxcfs.textContent.trim();
        const match = lxcfsText.match(/(\d+)/);
        
        if (match) {
            const value = parseInt(match[1]);
            const cardBody = lxcfs.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            
            statsCircle.innerHTML = '';
            const gauge = createGauge('lxcfs', value, 'LXCFS');
            const svg = gauge.querySelector('.gauge-svg');
            const label = gauge.querySelector('.gauge-label');
            
            statsCircle.appendChild(svg);
            statsCircle.appendChild(label);
            statsCircle.classList.add('gauge-container');
        } else {
            const cardBody = lxcfs.closest('.card-body');
            const statsCircle = cardBody.querySelector('.stats-circle');
            statsCircle.innerHTML = '<div class="gauge-label"><div class="gauge-title">LXCFS</div><div class="gauge-value">NA</div></div>';
            statsCircle.classList.add('gauge-container');
        }
    });
}

// Initialize on DOMContentLoaded if on resources page
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on the resources page
        if (document.querySelector('[data-page="resources"]')) {
            drawWorkerGauges();
            
            // Redraw gauges on theme change
            const themeSwitcher = document.getElementById("themeSwitcher");
            if (themeSwitcher) {
                themeSwitcher.addEventListener('change', () => {
                    setTimeout(function() {
                        drawWorkerGauges();
                    }, 100);
                });
            }
        }
    });
}

// Export functions for use in other modules
export { createGauge, updateGauge, initGauges, drawWorkerGauges };