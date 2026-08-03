import SupabaseClient from './supabase-client.js';

const supabase = new SupabaseClient();

// Myanmar digit mapping
const myanmarDigits = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

// Utility functions
function removeAllSpaces(str) {
    return str ? str.replace(/\s+/g, '') : '';
}

function convertToMyanmarDigits(number) {
    let result = number;
    englishDigits.forEach((digit, index) => {
        result = result.replace(new RegExp(digit, 'g'), myanmarDigits[index]);
    });
    return result;
}

// Process name mapping
async function processNameMapping(enName) {

}

// Process NRC mapping
async function processNRCMapping(nrc) {
    const result = { NRCdiv: '', NRCtsp: '', NRCtype: '', NRCno: '' };
    
    if (!nrc) return result;

    const trimmedNrc = nrc.trim();
    const match = trimmedNrc.match(/^([^\(\)]+?)\s*\(([^)]+)\)\s*(.*)$/);
    
    if (!match) {
        console.warn('NRC format not matched:', nrc);
        return result;
    }
    
    const regionTsp = removeAllSpaces(match[1]);
    const nrcType = match[2].trim();
    const nrcNumber = removeAllSpaces(match[3]);
    
    try {
        const nrcMaps = await supabase.query('tbl_nrc_map', {
            select: 'div_code, tsp_id, eng_div_tsp'
        });
        
        const nrcMap = nrcMaps.find(m => 
            removeAllSpaces(m.eng_div_tsp).toLowerCase() === regionTsp.toLowerCase()
        );
        
        if (nrcMap) {
            result.NRCdiv = nrcMap.div_code;
            result.NRCtsp = nrcMap.tsp_id;
        }

        const typeMaps = await supabase.query('tbl_nrc_type_map', {
            select: 'nrctype_mm, nrctype_en'
        });

        const typeMap = typeMaps.find(t => 
            t.nrctype_en && t.nrctype_en.trim().toLowerCase() === nrcType.toLowerCase()
        );
        
        if (typeMap) {
            result.NRCtype = typeMap.nrctype_mm;
        } else {
            result.NRCtype = nrcType;
        }

        result.NRCno = convertToMyanmarDigits(nrcNumber);
        
    } `catch` (error) {
        console.error('NRC mapping error:', error);
    }
    
    return result;
}

// Process phone number
async function processPhoneNumber(phone) {
    if (!phone) return phone;
    
    const cleanPhone = removeAllSpaces(phone);
    
    try {
        const areaCodes = await supabase.query('tbl_phone_map', {
            order: 'area_code.desc'
        });
        
        for (const areaCode of areaCodes) {
            const code = removeAllSpaces(areaCode.area_code);
            
            if (cleanPhone.toLowerCase().startsWith(code.toLowerCase())) {
                let remaining = cleanPhone.substring(code.length);
                remaining = remaining.replace(/^[()\-_., ]+/, '');
                
                let ePhone = remaining;
                if (areaCode.r_code) {
                    ePhone = areaCode.r_code + remaining;
                }
                
                return {
                    ePhone: ePhone.replace(/[^0-9]/g, ''),
                    matched: true
                };
            }
        }
    } catch (error) {
        console.error('Phone mapping error:', error);
    }
    
    return {
        ePhone: phone.replace(/[^0-9]/g, ''),
        matched: false
    };
}

// Main processing function
async function processData() {
    const dataStr = sessionStorage.getItem('processed_data');
    
    if (!dataStr) {
        document.getElementById('loadingSection').classList.add('d-none');
        document.getElementById('errorSection').textContent = 
            'No data to process. Please upload data first. ' +
            '<a href="upload.html">Go to Upload Page</a>';
        document.getElementById('errorSection').classList.remove('d-none');
        return;
    }
    
    const data = JSON.parse(dataStr);
    const results = [];
    let phoneMatchCount = 0;
    
    for (const row of data) {
        try {
            const mmName = await processNameMapping(row.enName);
            const nrcData = await processNRCMapping(row.NRC);
            const phoneData = await processPhoneNumber(row.Phone);
            
            if (phoneData.matched) phoneMatchCount++;
            
            results.push({
                No: row.No || '',
                enName: row.enName || '',
                NRC: row.NRC || '',
                Phone: row.Phone || '',
                mmName: mmName,
                NRCdiv: nrcData.NRCdiv,
                NRCtsp: nrcData.NRCtsp,
                NRCtype: nrcData.NRCtype,
                NRCno: nrcData.NRCno,
                ePhone: phoneData.ePhone
            });
        } catch (error) {
            console.error('Row processing error:', error);
            results.push({
                No: row.No || '',
                enName: row.enName || '',
                NRC: row.NRC || '',
                Phone: row.Phone || '',
                mmName: '',
                NRCdiv: '',
                NRCtsp: '',
                NRCtype: '',
                NRCno: '',
                ePhone: ''
            });
        }
    }
    
    // Store results for export
    sessionStorage.setItem('processed_results', JSON.stringify(results));
    
    // Display results
    displayResults(results, phoneMatchCount);
}

// Display results in table
function displayResults(results, phoneMatchCount) {
    document.getElementById('loadingSection').classList.add('d-none');
    document.getElementById('summarySection').classList.remove('d-none');
    document.getElementById('resultsSection').classList.remove('d-none');
    
    // Update summary
    document.getElementById('totalRecords').textContent = results.length;
    document.getElementById('phoneMatched').textContent = phoneMatchCount;
    document.getElementById('phoneNotMatched').textContent = results.length - phoneMatchCount;
    document.getElementById('recordCount').textContent = `${results.length} records`;
    
    // Populate table
    const tbody = document.getElementById('resultsBody');
    tbody.innerHTML = '';
    
    results.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(row.No)}</td>
            <td>${escapeHtml(row.enName)}</td>
            <td><small>${escapeHtml(row.NRC)}</small></td>
            <td><small>${escapeHtml(row.Phone)}</small></td>
            <td class="mm-font">${escapeHtml(row.mmName)}</td>
            <td>${escapeHtml(row.NRCdiv)}</td>
            <td>${escapeHtml(row.NRCtsp)}</td>
            <td>${escapeHtml(row.NRCtype)}</td>
            <td>${escapeHtml(row.NRCno)}</td>
            <td class="phone-processed">${escapeHtml(row.ePhone)}</td>
        `;
        tbody.appendChild(tr);
    });
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export to CSV
function exportToCSV() {
    const results = JSON.parse(sessionStorage.getItem('processed_results') || '[]');
    
    if (results.length === 0) {
        alert('No data to export');
        return;
    }
    
    const headers = ['No', 'enName', 'NRC', 'Phone', 'mmName', 'NRCdiv', 'NRCtsp', 'NRCtype', 'NRCno', 'ePhone'];
    const dateTime = getCurrentDateTime();
    
    let csv = headers.join(',') + '\n';
    
    results.forEach(row => {
        const values = headers.map(h => {
            const val = row[h] || '';
            return '"' + val.replace(/"/g, '""') + '"';
        });
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `nrc_processed_${dateTime}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

// Get current date time for filename
function getCurrentDateTime() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    return `${y}${m}${d}_${h}${min}${s}`;
}

// Load and process data on page load
document.addEventListener('DOMContentLoaded', () => {
    processData();
});

// Make export function global
window.exportToCSV = exportToCSV;