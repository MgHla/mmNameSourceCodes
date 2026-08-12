<?php
require_once 'includes/security.php';

// Only check if session exists, don't redirect aggressively
if (!isset($_SESSION['user_active'])) {
    // Instead of redirecting, show error
    $accessDenied = true;
}

// Check if file was uploaded and the path is inside the private uploads dir
if (!isset($_SESSION['uploaded_file'])) {
    $noFile = true;
} else {
    $realUploaded = realpath($_SESSION['uploaded_file']);
    $realUploadDir = realpath(UPLOAD_DIR);
    if ($realUploaded === false || $realUploadDir === false
        || strpos($realUploaded, $realUploadDir) !== 0) {
        $noFile = true;
        $_SESSION['uploaded_file'] = null;
    } elseif (!file_exists($realUploaded)) {
        $noFile = true;
    }
}

// If access denied or no file, show page with error
if (isset($accessDenied) || isset($noFile)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ် - Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-warning">
                <h4>Session Issue</h4>
                <p><?php echo isset($accessDenied) ? 'Your session has expired.' : 'No file was uploaded.'; ?></p>
                <a href="upload.php" class="btn btn-primary">Go to Upload Page</a>
                <a href="logout.php" class="btn btn-secondary">Reset Session</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Continue with normal processing...
require_once 'config/database.php';
require_once 'includes/session_manager_db.php';

$database = new Database();
$db = $database->getConnection();
$sessionManager = new SessionManagerDB($db);

// Check if user has active session and uploaded file
if (!isset($_SESSION['user_active']) || !$sessionManager->isCurrentUser() || !isset($_SESSION['uploaded_file'])) {
    header('Location: index.php');
    exit();
}

// Function to convert English digits to Myanmar digits
function convertToMyanmarDigits($number) {
    $myanmarDigits = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($englishDigits, $myanmarDigits, $number);
}

// Function to remove ALL spaces from a string
function removeAllSpaces($string) {
    return preg_replace('/\s+/', '', $string);
}

// Function to process name mapping
function processNameMapping($enName, $db) {
    $words = explode(' ', trim($enName));
    $mmName = [];
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $stmt = $db->prepare("SELECT mm_name FROM tbl_name_map WHERE eng_name = :eng_name");
            $stmt->execute([':eng_name' => $word]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $mmName[] = $result['mm_name'];
            } else {
                $mmName[] = $word;
            }
        }
    }
    
    return implode('', $mmName);
}

// Function to process NRC mapping
function processNRCMapping($nrc, $db) {
    $result = [
        'NRCdiv' => '',
        'NRCtsp' => '',
        'NRCtype' => '',
        'NRCno' => ''
    ];
    
    $originalNRC = $nrc;
    $nrc = removeAllSpaces($nrc);
    
    if (preg_match('/^(\d+\/[^(]+)\s*\(?([^)]+)\)?\s*(.+)$/', $nrc, $matches)) {
        $regionTsp = trim($matches[1]);
        $nrcType = trim($matches[2]);
        $nrcNumber = trim($matches[3]);
        
        $nrcType = str_replace(['(', ')'], '', $nrcType);
        
        // Map region/township
        $stmt = $db->prepare("SELECT div_code, tsp_id FROM tbl_nrc_map WHERE eng_div_tsp = :eng_div_tsp");
        $stmt->execute([':eng_div_tsp' => $regionTsp]);
        $nrcMap = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nrcMap) {
            $stmt = $db->prepare("SELECT eng_div_tsp, div_code, tsp_id FROM tbl_nrc_map");
            $stmt->execute();
            $allNrcMaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allNrcMaps as $map) {
                if (removeAllSpaces($map['eng_div_tsp']) === $regionTsp) {
                    $nrcMap = $map;
                    break;
                }
            }
        }
        
        if ($nrcMap) {
            $result['NRCdiv'] = $nrcMap['div_code'];
            $result['NRCtsp'] = $nrcMap['tsp_id'];
        }
        
        // Map NRC type
        $stmt = $db->prepare("SELECT nrctype_mm FROM tbl_nrc_type_map WHERE nrctype_en = :nrctype_en");
        $stmt->execute([':nrctype_en' => $nrcType]);
        $typeMap = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($typeMap) {
            $result['NRCtype'] = $typeMap['nrctype_mm'];
        }
        
        $nrcNumber = removeAllSpaces($nrcNumber);
        $result['NRCno'] = convertToMyanmarDigits($nrcNumber);
    }
    
    return $result;
}

// Function to process phone number
function processPhoneNumber($phone, $db) {
    $ePhone = $phone; // Default - keep original if no match
    $matchedAreaCode = '';
    $matchedTown = '';
    
    // Clean the phone number - remove spaces but keep other characters
    $cleanPhone = removeAllSpaces($phone);
    
    // Get all area codes from database, ordered by length (longest first)
    $stmt = $db->prepare("SELECT Town, AreaCode, rCode FROM tbl_phone_map ORDER BY LENGTH(AreaCode) DESC");
    $stmt->execute();
    $areaCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($areaCodes as $areaCode) {
        $code = $areaCode['AreaCode'];
        $rCode = $areaCode['rCode'];
        $town = $areaCode['Town'];
        
        // Remove spaces from area code for comparison
        $cleanCode = removeAllSpaces($code);
        
        // Check if phone starts with this area code (case insensitive)
        if (stripos($cleanPhone, $cleanCode) === 0) {
            // Found matching area code
            $matchedAreaCode = $code;
            $matchedTown = ''; // $town;
            
            // Remove the area code from phone number
            $remainingNumber = substr($cleanPhone, strlen($cleanCode));
            
            // Remove any leading special characters from remaining number
            $remainingNumber = ltrim($remainingNumber, '()-_., ');
            
            // If rCode exists, prepend it
            if (!empty($rCode)) {
                $ePhone = $rCode . $remainingNumber;
            } else {
                $ePhone = $remainingNumber;
            }

            
            // Clean the final number
            $ePhone = preg_replace('/[^0-9]/', '', $ePhone);
            
            break; // Stop after first (longest) match
        }
    }
    
    return [
        'ePhone' => $ePhone,
        'matched_area_code' => $matchedAreaCode,
        'matched_town' => $matchedTown
    ];
}

// Process the uploaded file
$filePath = $_SESSION['uploaded_file'];
$processedData = [];
$errors = [];
$successCount = 0;
$failCount = 0;
$phoneMatchCount = 0;
$phoneNoMatchCount = 0;
$handle = null;
$headers = [];
$fileExtension = '';
$totalRows = 0;

// Emit an inline script that updates the progress bar as rows are processed.
function emitProgress($current, $total) {
    $percent = $total > 0 ? (int)round(($current / $total) * 100) : 100;
    if ($percent > 100) $percent = 100;
    echo '<script>updateProgress(' . $percent . ', ' . (int)$current . ', ' . (int)$total . ')</script>';
    @ob_flush();
    flush();
}

try {
    // Determine file type
    $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    if ($fileExtension === 'csv') {
        // Open the file and read headers
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            
            if ($headers && substr($headers[0], 0, 3) === "\xEF\xBB\xBF") {
                $headers[0] = substr($headers[0], 3);
            }
            
            $headers = array_map('trim', $headers);
            
            $requiredColumns = ['No', 'enName', 'NRC', 'Phone'];
            $missingColumns = array_diff($requiredColumns, $headers);
            if (!empty($missingColumns)) {
                $errors[] = "Missing required columns: " . implode(', ', $missingColumns) .
                            ". Required columns: No, enName, NRC, Phone (mmName, NRCdiv, NRCtsp, NRCtype, NRCno, ePhone are auto-populated).";
            }
            
            // First pass: count total rows for the progress bar
            while (fgetcsv($handle) !== FALSE) {
                $totalRows++;
            }
            rewind($handle);
            fgetcsv($handle); // skip header
        }
    } elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
        // For Excel files, you would need PhpSpreadsheet library
        $errors[] = "Excel processing requires PhpSpreadsheet library. Please use CSV format.";
    }
} catch (Exception $e) {
    $errors[] = "Error reading file: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ် - Processing Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
    @font-face {
        font-family: "Aka02-Regular";
        src: local('A ka 02'), url('https://cdn.jsdelivr.net/gh/saturngod/myanmar-unicode-fonts@master/docs/Aka/Aka02-Regular.ttf') format('truetype');
    }

    @font-face {font-family:"Aka10-Light";
	src:local('A ka 10'),url('https://cdn.jsdelivr.net/gh/saturngod/myanmar-unicode-fonts@master/docs/Aka/Aka10-Light.ttf') format('truetype');}
	
    h4 {
        font-family: "Aka02-Regular";
      font-weight: 500; 
    }

    .mmbody {
		font-family:"Aka10-Light";
    }

    .navbar-brand {
        font-family: "Aka02-Regular";
    }
	@media (min-width: 1400px) {
	    .container, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
	        max-width: 95vw;
	    }
	}
        .card-body {
            max-height: 75vh;
            overflow-y: auto;
            position: relative;
        }
        .sticky-buttons {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: white;
            padding: 8px 0;
            margin-bottom: 8px;
        }
        .phone-original {
            color: #6c757d;
            font-size: 0.9em;
        }
        .phone-processed {
            font-weight: bold;
            color: #198754;
        }
        .mm-font {
            font-size: 1.1em;
        }
        @media print {
            .page-break {
                page-break-before: always;
            }
            .card-body {
                max-height: none;
                overflow: visible;
            }
            .pagination-controls {
                display: none !important;
            }
            #resultsTable tbody tr {
                display: table-row !important;
            }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand"><i class="bi bi-translate" style="font-size: 1.6rem;"></i> မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ်</span>
            <div class="d-flex">
                <a href="upload.php" class="btn btn-outline-light btn-sm me-2">Upload Another File</a>
                <a href="logout.php?csrf=<?php echo urlencode(csrf_token()); ?>" class="btn btn-outline-light btn-sm">End Session</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Progress Bar -->
        <div id="progressSection" class="card shadow mb-4">
            <div class="card-body text-center">
                <h5><i class="bi bi-hourglass-split"></i> Processing File...</h5>
                <div class="progress mt-3" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <p id="progressText" class="mt-2 mb-0 small text-muted">Preparing to process...</p>
            </div>
        </div>
        <script>
        function updateProgress(percent, current, total) {
            const bar = document.getElementById('progressBar');
            const text = document.getElementById('progressText');
            if (bar) {
                bar.style.width = percent + '%';
                bar.setAttribute('aria-valuenow', percent);
                bar.textContent = percent + '%';
            }
            if (text) text.textContent = 'Processing record ' + current + ' of ' + total + '...';
        }
        function completeProgress() {
            const section = document.getElementById('progressSection');
            if (section) section.classList.add('d-none');
        }
        </script>
        
        <?php
        // Flush the initial HTML so the progress bar appears immediately.
        while (ob_get_level() > 0) { @ob_end_flush(); }
        flush();
        
        // Second pass: process each row while streaming progress updates.
        if ($handle && $fileExtension === 'csv') {
            $rowCount = 0;
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowCount++;
                if ($rowCount % 5 === 0) {
                    emitProgress($rowCount, $totalRows);
                }
                
                if (empty(array_filter($data))) {
                    continue;
                }
                
                if (count($headers) === count($data)) {
                    $row = array_combine($headers, $data);
                } else {
                    $errors[] = "Row {$rowCount}: Column count mismatch.";
                    $failCount++;
                    continue;
                }
                
                try {
                    // Process name mapping
                    $mmName = processNameMapping($row['enName'] ?? '', $db);
                    
                    // Process NRC mapping
                    $nrcData = processNRCMapping($row['NRC'] ?? '', $db);
                    
                    // Process phone number
                    $phoneData = processPhoneNumber($row['Phone'] ?? '', $db);
                    
                    if (!empty($phoneData['matched_area_code'])) {
                        $phoneMatchCount++;
                    } else {
                        $phoneNoMatchCount++;
                    }
                    
                    $processedData[] = [
                        'No' => $row['No'] ?? $rowCount,
                        'enName' => $row['enName'] ?? '',
                        'NRC' => $row['NRC'] ?? '',
                        'Phone' => $row['Phone'] ?? '',
                        'mmName' => $mmName,
                        'NRCdiv' => $nrcData['NRCdiv'],
                        'NRCtsp' => $nrcData['NRCtsp'],
                        'NRCtype' => $nrcData['NRCtype'],
                        'NRCno' => $nrcData['NRCno'],
                        'ePhone' => $phoneData['ePhone'],
                        'phone_town' => $phoneData['matched_town'],
                        'phone_area_code' => $phoneData['matched_area_code']
                    ];
                    
                    $successCount++;
                } catch (Exception $e) {
                    $errors[] = "Row {$rowCount}: " . $e->getMessage();
                    $failCount++;
                }
            }
            fclose($handle);
        }
        
        emitProgress($totalRows, $totalRows);
        
        // Store processing summary in session for export
        $_SESSION['processing_summary'] = [
            'total' => $successCount + $failCount,
            'success' => $successCount,
            'fail' => $failCount,
            'phone_matched' => $phoneMatchCount,
            'phone_not_matched' => $phoneNoMatchCount
        ];
        ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $successCount; ?></h3>
                        <p class="mb-0">Success</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $failCount; ?></h3>
                        <p class="mb-0">Failed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $phoneMatchCount; ?></h3>
                        <p class="mb-0">Phone Matched</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $phoneNoMatchCount; ?></h3>
                        <p class="mb-0">Phone Not Matched</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Errors Display -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <h5><i class="bi bi-exclamation-triangle"></i> Processing Warnings</h5>
                <ul class="mb-0">
                    <?php foreach (array_slice($errors, 0, 10) as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 10): ?>
                        <li>... and <?php echo count($errors) - 10; ?> more warnings</li>
                    <?php endif; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Results Table -->
        <?php if (!empty($processedData)): ?>
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Processing Results</h5>
                    <div>
                        <span class="badge bg-success me-2"><?php echo count($processedData); ?> records</span>
                        <span class="badge bg-info"><?php echo $phoneMatchCount; ?> phones mapped</span>
                    </div>
                </div>
                <div class="card-body mmbody">
                    <div class="mt-3 d-flex gap-2 sticky-buttons">
                        <button onclick="exportToCSV()" class="btn btn-success">
                            <i class="bi bi-download"></i> Export to CSV
                        </button>
                        <button onclick="exportToExcel()" class="btn btn-info text-white">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export to Excel
                        </button>
<!--                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="bi bi-printer"></i> Print
                        </button>
-->
                        <a href="upload.php" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Upload Another File
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="resultsTable" class="table table-striped table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>enName</th>
                                    <th>NRC</th>
                                    <th>Phone</th>
                                    <th>mmName</th>
                                    <th>NRCdiv</th>
                                    <th>NRCtsp</th>
                                    <th>NRCtype</th>
                                    <th>NRCno</th>
                                    <th>ePhone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowIndex = 0; foreach ($processedData as $row): ?>
                                <tr<?php if ($rowIndex > 0 && $rowIndex % 20 == 0) echo ' class="page-break"'; ?>>
                                    <td><?php echo htmlspecialchars($row['No']); ?></td>
                                    <td><?php echo htmlspecialchars($row['enName']); ?></td>
                                    <td><small><?php echo htmlspecialchars($row['NRC']); ?></small></td>
                                    <td class="phone-original"><?php echo htmlspecialchars($row['Phone']); ?></td>
                                    <td class="mm-font"><?php echo htmlspecialchars($row['mmName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['NRCdiv']); ?></td>
                                    <td><?php echo htmlspecialchars($row['NRCtsp']); ?></td>
                                    <td><?php echo htmlspecialchars($row['NRCtype']); ?></td>
                                    <td><?php echo htmlspecialchars($row['NRCno']); ?></td>
                                    <td class="phone-processed">
                                        <?php echo htmlspecialchars($row['ePhone']); ?>
                                        <?php if (!empty($row['phone_town'])): ?>
                                            <br><small class="text-muted">(<?php echo htmlspecialchars($row['phone_town']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php $rowIndex++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-controls mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span id="pageInfo" class="small text-muted"></span>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select id="pageSizeSelect" class="form-select form-select-sm" style="width:auto" onchange="setPageSize(this.value)">
                                <option value="25" selected>25 / page</option>
                                <option value="50">50 / page</option>
                                <option value="100">100 / page</option>
                                <option value="200">200 / page</option>
                                <option value="500">500 / page</option>
                            </select>
                            <button id="prevPageBtn" class="btn btn-sm btn-outline-primary" onclick="changePage(-1)">Prev</button>
                            <span id="pageNumbers" class="d-flex align-items-center"></span>
                            <button id="nextPageBtn" class="btn btn-sm btn-outline-primary" onclick="changePage(1)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h5>No data processed</h5>
                <p>No valid records were found in the uploaded file.</p>
                <a href="upload.php" class="btn btn-primary">Go Back to Upload</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function getCurrentDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return `${year}${month}${day}_${hours}${minutes}${seconds}`;
    }
    
    function exportToCSV() {
        const dateTime = getCurrentDateTime();
        const filename = `processed_data_${dateTime}.csv`;
        
        const rows = [];
        const table = document.querySelector('table');
        const headers = [];
        
        // Get headers
        table.querySelectorAll('thead th').forEach(th => {
            headers.push('"' + th.textContent.trim().replace(/"/g, '""') + '"');
        });
        rows.push(headers.join(','));
        
        // Get data rows
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('td').forEach(td => {
                // Get text content without nested elements
                let text = td.textContent.trim().replace(/\n/g, ' ').replace(/\s+/g, ' ');
                row.push('"' + text.replace(/"/g, '""') + '"');
            });
            rows.push(row.join(','));
        });
        
        downloadFile(rows.join('\n'), filename, 'text/csv;charset=utf-8;');
    }
    
    function exportToExcel() {
        const dateTime = getCurrentDateTime();
        const filename = `processed_data_${dateTime}.xls`;
        
        const table = document.querySelector('table');
        const html = table.outerHTML;
        
        const excelHTML = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" 
                  xmlns:x="urn:schemas-microsoft-com:office:excel" 
                  xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <!--[if gte mso 9]><xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Processed Data</x:Name>
                                <x:WorksheetOptions>
                                    <x:DisplayGridlines/>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml><![endif]-->
            </head>
            <body>
                ${html}
            </body>
            </html>
        `;
        
        downloadFile(excelHTML, filename, 'application/vnd.ms-excel;charset=utf-8;');
    }
    
    function downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    // ============ Pagination ============
    const allRows = Array.from(document.querySelectorAll('#resultsTable tbody tr'));
    let currentPage = 1;
    let pageSize = 25;
    
    function getTotalPages() {
        return Math.max(1, Math.ceil(allRows.length / pageSize));
    }
    
    function renderTable() {
        const start = (currentPage - 1) * pageSize;
        allRows.forEach((row, i) => {
            row.classList.toggle('d-none', i < start || i >= start + pageSize);
        });
        renderPagination();
    }
    
    function pageNumberList(totalPages) {
        const pages = [];
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, currentPage + 2);
        
        if (start > 1) {
            pages.push(1);
            if (start > 2) pages.push('...');
        }
        for (let i = start; i <= end; i++) pages.push(i);
        if (end < totalPages) {
            if (end < totalPages - 1) pages.push('...');
            pages.push(totalPages);
        }
        return pages;
    }
    
    function renderPagination() {
        const totalPages = getTotalPages();
        if (currentPage > totalPages) currentPage = totalPages;
        
        document.getElementById('prevPageBtn').disabled = currentPage <= 1;
        document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
        
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(allRows.length, currentPage * pageSize);
        document.getElementById('pageInfo').textContent = allRows.length
            ? `Showing ${start + 1}–${end} of ${allRows.length}`
            : 'No records';
        
        const container = document.getElementById('pageNumbers');
        container.innerHTML = '';
        
        pageNumberList(totalPages).forEach(n => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm';
            btn.style.marginLeft = '2px';
            if (n === '...') {
                btn.textContent = '…';
                btn.classList.add('btn-link');
                btn.disabled = true;
            } else {
                btn.textContent = n;
                btn.classList.add(n === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.onclick = () => goToPage(n);
            }
            container.appendChild(btn);
        });
    }
    
    function goToPage(page) {
        const totalPages = getTotalPages();
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        currentPage = page;
        renderTable();
    }
    
    function changePage(delta) {
        goToPage(currentPage + delta);
    }
    
    function setPageSize(size) {
        pageSize = parseInt(size, 10) || 25;
        goToPage(1);
    }
    
    renderTable();
    completeProgress();
    </script>
</body>
</html>
