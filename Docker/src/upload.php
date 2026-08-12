<?php
require_once 'includes/security.php';

// Check session
if (!isset($_SESSION['user_active'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Get PHP upload limits
$maxUploadSize = min(
    ini_get('upload_max_filesize'),
    ini_get('post_max_size')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    csrf_verify();
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds PHP limit. Maximum allowed: ' . $maxUploadSize,
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder not found.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        
        $error = $uploadErrors[$file['error']] ?? 'Unknown upload error (Code: ' . $file['error'] . ')';
        
        // Additional info for error code 1
        if ($file['error'] === UPLOAD_ERR_INI_SIZE) {
            $error .= '<br><small class="text-muted">Your file size: ' . 
                      (isset($_SERVER['CONTENT_LENGTH']) ? 
                       number_format($_SERVER['CONTENT_LENGTH'] / 1024 / 1024, 2) . ' MB' : 
                       'Unknown') . 
                      '</small>';
        }
    } else {
        $allowedTypes = ['csv', 'xlsx', 'xls'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileSize = $file['size'];
        
        if (!in_array($fileExtension, $allowedTypes)) {
            $error = 'Invalid file type. Please upload CSV or Excel file only.';
        } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB application limit
            $error = 'File too large. Maximum size is 10MB. Your file: ' . 
                     number_format($fileSize / 1024 / 1024, 2) . ' MB';
        } else {
            // Validate file content, not just the extension.
            $contentValidation = validateUploadContent($file['tmp_name'], $fileExtension, $fileSize);
            if ($contentValidation !== true) {
                $error = $contentValidation;
            } else {
                // Create upload directory outside the web root
                $uploadDir = UPLOAD_DIR;

                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0750, true);
                }

                // Generate safe, unpredictable filename (original name is never kept)
                $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                $filePath = $uploadDir . '/' . $fileName;

                if (@move_uploaded_file($file['tmp_name'], $filePath)) {
                    $_SESSION['uploaded_file'] = $filePath;
                    $_SESSION['uploaded_filename'] = $file['name'];
                    header('Location: process.php');
                    exit();
                } else {
                    $error = 'Failed to save file. Please check folder permissions.<br>';
                    $error .= 'Upload directory: ' . $uploadDir . '<br>';
                    $error .= 'Writable: ' . (is_writable($uploadDir) ? 'Yes' : 'No');
                }
            }
        }
    }
}

// Validate that the uploaded file matches the claimed extension.
function validateUploadContent($tmpPath, $extension, $fileSize) {
    $handle = @fopen($tmpPath, 'rb');
    if ($handle === false) {
        return 'Could not read the uploaded file.';
    }
    $head = fread($handle, 2048);
    fclose($handle);

    if ($extension === 'csv') {
        // CSV must be plain text; reject binary content (no null bytes).
        if (strpos($head, "\0") !== false) {
            return 'Invalid CSV file: binary content detected. Please upload a plain-text CSV.';
        }
    } elseif (in_array($extension, ['xlsx', 'xls'], true)) {
        // .xlsx/.xls files are ZIP archives and must start with PK bytes.
        if (substr($head, 0, 2) !== 'PK') {
            return 'Invalid Excel file: not a valid archive. Please upload a real .xlsx/.xls file.';
        }
    }

    if ($fileSize <= 0) {
        return 'The uploaded file is empty.';
    }
    return true;
}

// Get current PHP settings for display
$phpSettings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time') . ' seconds',
    'memory_limit' => ini_get('memory_limit')
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ် - Upload File</title>
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
        .file-size-info {
            font-size: 0.85em;
            color: #666;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand"><i class="bi bi-translate" style="font-size: 1.6rem;"></i> မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ်</span>
            <div class="d-flex">
                <a href="logout.php?csrf=<?php echo urlencode(csrf_token()); ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> End Session
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-circle"></i> Upload Error</h5>
                        <p class="mb-0"><?php echo $error; ?></p>
                        
                        <?php if (strpos($error, 'exceeds PHP limit') !== false): ?>
                            <hr>
                            <h6>How to Fix:</h6>
                            <ol class="small">
                                <li>Upload a smaller file (less than <?php echo $maxUploadSize; ?>)</li>
                                <li>Or increase PHP upload limits in php.ini</li>
                                <li>Or contact your system administrator</li>
                            </ol>
<!--                            <div class="mt-2">
                                <a href="php_info.php" target="_blank" class="btn btn-sm btn-outline-danger">
                                    View PHP Settings
                                </a>
                            </div>
-->
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="bi bi-upload"></i> Upload Excel/CSV File</h5>
                    </div>
                    <div class="card-body">
                        <!-- PHP Settings Info -->
                        <div class="alert alert-secondary file-size-info">
                            <strong>Server Limits:</strong><br>
                            Max File Size: <strong><?php echo $phpSettings['upload_max_filesize']; ?></strong> | 
                            Max POST Size: <strong><?php echo $phpSettings['post_max_size']; ?></strong> | 
                            Max Execution: <strong><?php echo $phpSettings['max_execution_time']; ?></strong>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6>Required File Format:</h6>
                            <p class="mb-1">Columns: <code>No, enName, NRC, Phone</code></p>
                            <small>mmName, NRCdiv, NRCtsp, NRCtype, NRCno, ePhone - will be auto-populated</small>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm" onsubmit="showUploadProgress()">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="file" class="form-label">
                                    <strong>Choose CSV File</strong>
                                </label>
                                <input type="file" class="form-control" id="file" name="file" 
                                       accept=".csv" required
                                       onchange="checkFileSize(this)">
                                <div class="form-text" id="fileSizeHelp"></div>
                                <div class="invalid-feedback" id="fileError"></div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="bi bi-cloud-upload"></i> Upload and Process
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-house"></i> Home
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Alternative: Paste Data -->
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h6>Alternative: Paste CSV Data</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">If file upload doesn't work, you can paste your data directly.</p>
                        <a href="direct_process.php" class="btn btn-outline-primary">
                            <i class="bi bi-clipboard"></i> Go to Direct Input Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Upload Progress Overlay -->
    <div id="uploadOverlay" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050;">
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="card shadow p-4 text-center" style="width: 90%; max-width: 400px;">
                <h5><i class="bi bi-cloud-upload"></i> Uploading...</h5>
                <div class="progress mt-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                </div>
                <p class="text-muted small mt-2 mb-0">Please wait while your file is uploaded and processed...</p>
            </div>
        </div>
    </div>
    
    <script>
    function showUploadProgress() {
        const fileInput = document.getElementById('file');
        if (fileInput && !fileInput.files.length) {
            fileInput.classList.add('is-invalid');
            document.getElementById('fileError').textContent = 'Please choose a file first.';
            return false;
        }
        document.getElementById('uploadOverlay').classList.remove('d-none');
        return true;
    }
    
    // Max file size from PHP (in bytes)
    const maxFileSize = <?php echo 10 * 1024 * 1024; ?>; // 10MB
    
    function checkFileSize(input) {
        const file = input.files[0];
        const helpDiv = document.getElementById('fileSizeHelp');
        const errorDiv = document.getElementById('fileError');
        const submitBtn = document.getElementById('submitBtn');
        
        if (file) {
            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            helpDiv.textContent = `File size: ${fileSizeMB} MB`;
            
            if (file.size > maxFileSize) {
                input.classList.add('is-invalid');
                errorDiv.textContent = `File too large (${fileSizeMB} MB). Maximum is 10 MB.`;
                submitBtn.disabled = true;
                helpDiv.className = 'form-text text-danger';
            } else if (file.size > maxFileSize * 0.8) {
                // Warning for large files
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                helpDiv.className = 'form-text text-warning';
                helpDiv.textContent += ' - Large file, processing may take time';
                submitBtn.disabled = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                helpDiv.className = 'form-text text-success';
                submitBtn.disabled = false;
            }
        }
    }
    </script>
</body>
</html>
