<?php
require_once 'includes/security.php';
require_once 'config/database.php';
require_once 'includes/session_manager_db.php';

$database = new Database();
$db = $database->getConnection();
$sessionManager = new SessionManagerDB($db);

if (!isset($_SESSION['user_active']) || !$sessionManager->isCurrentUser()) {
    header('Location: index.php');
    exit();
}

// Function to remove all spaces
function removeAllSpaces($string) {
    return preg_replace('/\s+/', '', $string);
}

// Function to convert English digits to Myanmar digits
function convertToMyanmarDigits($number) {
    $myanmarDigits = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($englishDigits, $myanmarDigits, $number);
}

// Process functions (same as before)
function processNameMapping($enName, $db) {
    $words = explode(' ', trim($enName));
    $mmName = [];
    foreach ($words as $word) {
        if (!empty($word)) {
            $stmt = $db->prepare("SELECT mm_name FROM tbl_name_map WHERE eng_name = :eng_name");
            $stmt->execute([':eng_name' => $word]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $mmName[] = $result ? $result['mm_name'] : $word;
        }
    }
    return implode('', $mmName);
}

function processNRCMapping($nrc, $db) {
    $result = ['NRCdiv' => '', 'NRCtsp' => '', 'NRCtype' => '', 'NRCno' => ''];
    $nrc = removeAllSpaces($nrc);
    
    if (preg_match('/^(\d+\/[^(]+)\s*\(?([^)]+)\)?\s*(.+)$/', $nrc, $matches)) {
        $regionTsp = trim($matches[1]);
        $nrcType = str_replace(['(', ')'], '', trim($matches[2]));
        $nrcNumber = removeAllSpaces(trim($matches[3]));
        
        // Map region/township
        $stmt = $db->prepare("SELECT div_code, tsp_id FROM tbl_nrc_map WHERE REPLACE(eng_div_tsp, ' ', '') = :eng_div_tsp");
        $stmt->execute([':eng_div_tsp' => $regionTsp]);
        $nrcMap = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        
        $result['NRCno'] = convertToMyanmarDigits($nrcNumber);
    }
    return $result;
}

function processPhoneNumber($phone, $db) {
    $ePhone = $phone;
    $cleanPhone = removeAllSpaces($phone);
    
    $stmt = $db->prepare("SELECT Town, AreaCode, rCode FROM tbl_phone_map ORDER BY LENGTH(AreaCode) DESC");
    $stmt->execute();
    $areaCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($areaCodes as $areaCode) {
        $code = removeAllSpaces($areaCode['AreaCode']);
        
        if (stripos($cleanPhone, $code) === 0) {
            $remainingNumber = substr($cleanPhone, strlen($code));
            $remainingNumber = ltrim($remainingNumber, '()-_., ');
            
            if (!empty($areaCode['rCode'])) {
                $ePhone = $areaCode['rCode'] . $remainingNumber;
            } else {
                $ePhone = $remainingNumber;
            }
            
            $ePhone = preg_replace('/[^0-9]/', '', $ePhone);
            break;
        }
    }
    
    return $ePhone;
}

$processedData = [];
$error = '';
$totalRows = 0;
$csvHeaders = [];
$csvLines = [];

// Emit an inline script that updates the progress bar as rows are processed.
function emitProgress($current, $total) {
    $percent = $total > 0 ? (int)round(($current / $total) * 100) : 100;
    if ($percent > 100) $percent = 100;
    echo '<script>updateProgress(' . $percent . ', ' . (int)$current . ', ' . (int)$total . ')</script>';
    @ob_flush();
    flush();
}

// Parse pasted data (row processing happens later so the progress bar can update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_data'])) {
    csrf_verify();

    // Limit the size of pasted data to prevent abuse
    if (strlen($_POST['manual_data']) > 5 * 1024 * 1024) {
        $error = 'Pasted data is too large. Maximum is 5MB.';
    } else {
        $lines = explode("\n", trim($_POST['manual_data']));
        
        if (count($lines) > 1) {
            // First line is header
            $csvHeaders = str_getcsv(array_shift($lines), ",");
            $csvHeaders = array_map('trim', $csvHeaders);
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $csvLines[] = $line;
            }
            $totalRows = count($csvLines);
        } else {
            $error = 'Please paste data with header row and at least one data row';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ် - Direct Process</title>
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
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand"><i class="bi bi-translate" style="font-size: 1.6rem;"></i> မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ်</span>
            <div class="d-flex">
                <a href="upload.php" class="btn btn-outline-light btn-sm me-2">File Upload</a>
                <a href="logout.php?csrf=<?php echo urlencode(csrf_token()); ?>" class="btn btn-outline-light btn-sm">End Session</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5>Paste CSV Data Directly</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Paste your CSV data here (include header row):</label>
                        <textarea name="manual_data" class="form-control" rows="10" 
                                  placeholder="No,enName,NRC,Phone
1,Htin Linn Aye,9/Ma Na Ma (C) 001002,+095(09)2010240"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Process Data</button>
                </form>
            </div>
        </div>

        <!-- Progress Bar -->
        <?php if (!$error && $totalRows > 0): ?>
        <div id="progressSection" class="card shadow mb-4">
            <div class="card-body text-center">
                <h5><i class="bi bi-hourglass-split"></i> Processing Data...</h5>
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
        
        // Process each row while streaming progress updates.
        $rowIndex = 0;
        foreach ($csvLines as $line) {
            $rowIndex++;
            if ($rowIndex % 5 === 0) {
                emitProgress($rowIndex, $totalRows);
            }
            
            $data = str_getcsv($line, ",");
            if (count($csvHeaders) === count($data)) {
                $row = array_combine($csvHeaders, $data);
            } else {
                continue;
            }
            
            $mmName = processNameMapping($row['enName'] ?? '', $db);
            $nrcData = processNRCMapping($row['NRC'] ?? '', $db);
            $ePhone = processPhoneNumber($row['Phone'] ?? '', $db);
            
            $processedData[] = [
                'No' => $row['No'] ?? '',
                'enName' => $row['enName'] ?? '',
                'NRC' => $row['NRC'] ?? '',
                'Phone' => $row['Phone'] ?? '',
                'mmName' => $mmName,
                'NRCdiv' => $nrcData['NRCdiv'],
                'NRCtsp' => $nrcData['NRCtsp'],
                'NRCtype' => $nrcData['NRCtype'],
                'NRCno' => $nrcData['NRCno'],
                'ePhone' => $ePhone
            ];
        }
        
        emitProgress($totalRows, $totalRows);
        echo '<script>completeProgress();</script>';
        ?>
        <?php endif; ?>

        <?php if (!empty($processedData)): ?>
        <div class="card shadow">
            <div class="card-header">
                <h5>Results (<?php echo count($processedData); ?> records)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th><th>enName</th><th>NRC</th><th>Phone</th>
                                <th>mmName</th><th>NRCdiv</th><th>NRCtsp</th>
                                <th>NRCtype</th><th>NRCno</th><th>ePhone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processedData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['No']); ?></td>
                                <td><?php echo htmlspecialchars($row['enName']); ?></td>
                                <td><?php echo htmlspecialchars($row['NRC']); ?></td>
                                <td><?php echo htmlspecialchars($row['Phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['mmName']); ?></td>
                                <td><?php echo htmlspecialchars($row['NRCdiv']); ?></td>
                                <td><?php echo htmlspecialchars($row['NRCtsp']); ?></td>
                                <td><?php echo htmlspecialchars($row['NRCtype']); ?></td>
                                <td><?php echo htmlspecialchars($row['NRCno']); ?></td>
                                <td><?php echo htmlspecialchars($row['ePhone']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
