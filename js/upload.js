import { APP_CONFIG } from './config.js';

// Show file upload section
function showFileUpload() {
    document.getElementById('fileUploadSection').classList.remove('d-none');
    document.getElementById('pasteDataSection').classList.add('d-none');
    document.getElementById('btnFileUpload').classList.add('active');
    document.getElementById('btnPasteData').classList.remove('active');
}

// Show paste data section
function showPasteData() {
    document.getElementById('fileUploadSection').classList.add('d-none');
    document.getElementById('pasteDataSection').classList.remove('d-none');
    document.getElementById('btnFileUpload').classList.remove('active');
    document.getElementById('btnPasteData').classList.add('active');
}

// Parse CSV data
function parseCSV(csvText) {
    const lines = csvText.split('\n').filter(line => line.trim());
    if (lines.length < 2) return [];
    
    const headers = lines[0].split(',').map(h => h.trim());
    const data = [];
    
    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim());
        if (values.length >= headers.length) {
            const row = {};
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            data.push(row);
        }
    }
    
    return data;
}

// Handle file upload
async function handleFileUpload(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const errorMessage = document.getElementById('errorMessage');
    
    if (!fileInput.files[0]) {
        showError('Please select a file.');
        return;
    }
    
    const file = fileInput.files[0];
    
    if (file.size > APP_CONFIG.maxFileSize) {
        showError('File too large. Maximum size is 10MB.');
        return;
    }
    
    try {
        const text = await file.text();
        const data = parseCSV(text);
        
        if (data.length === 0) {
            showError('No valid data found in file.');
            return;
        }
        
        // Store data in session storage
        sessionStorage.setItem('processed_data', JSON.stringify(data));
        
        // Redirect to process page
        window.location.href = 'process.html';
        
    } catch (error) {
        console.error('File read error:', error);
        showError('Failed to read file.');
    }
}

// Handle paste form
async function handlePasteForm(e) {
    e.preventDefault();
    
    const csvData = document.getElementById('csvData').value;
    const errorMessage = document.getElementById('errorMessage');
    
    if (!csvData.trim()) {
        showError('Please paste CSV data.');
        return;
    }
    
    const data = parseCSV(csvData);
    
    if (data.length === 0) {
        showError('No valid data found. Check format.');
        return;
    }
    
    // Store data
    sessionStorage.setItem('processed_data', JSON.stringify(data));
    
    // Redirect to process page
    window.location.href = 'process.html';
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Check if URL has #paste
    if (window.location.hash === '#paste') {
        showPasteData();
    }
    
    document.getElementById('uploadForm')?.addEventListener('submit', handleFileUpload);
    document.getElementById('pasteForm')?.addEventListener('submit', handlePasteForm);
    
    // File info display
    document.getElementById('csvFile')?.addEventListener('change', function() {
        const file = this.files[0];
        const fileInfo = document.getElementById('fileInfo');
        if (file) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            fileInfo.textContent = `Selected: ${file.name} (${sizeMB} MB)`;
        }
    });
});

// Make functions global
window.showFileUpload = showFileUpload;
window.showPasteData = showPasteData;