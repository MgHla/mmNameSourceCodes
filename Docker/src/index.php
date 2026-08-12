<?php
require_once 'includes/security.php';

// Simple session check - no redirect loops
$dbAvailable = false;
$alreadyLoggedIn = false;

// Try database connection
try {
    require_once 'config/database.php';
    require_once 'includes/session_manager_db.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $sessionManager = new SessionManagerDB($db);
    $dbAvailable = true;
} catch (Exception $e) {
    // Database not available
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session'])) {
    csrf_verify();
    session_regenerate_id(true);
    $_SESSION['user_active'] = true;
    $_SESSION['start_time'] = time();
    if ($dbAvailable) {
        try {
            // Record the session for activity tracking. Multiple users
            // are allowed concurrently.
            $sessionManager->checkConcurrency();
        } catch (Exception $e) {
            // If database error, allow access
        }
    }
    header('Location: upload.php');
    exit();
}

// Check if already logged in (BUT DON'T REDIRECT)
if ($dbAvailable && isset($_SESSION['user_active']) && $sessionManager->isCurrentUser()) {
    $alreadyLoggedIn = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ်</title>
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
      font-weight: 500; font-size: 
    }

    .mmbody {
		font-family:"Aka10-Light";
    }
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .status-available { background-color: #28a745; box-shadow: 0 0 10px #28a745; }
        .status-busy { background-color: #dc3545; box-shadow: 0 0 10px #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-translate" style="font-size: 1.6rem;"></i>  မြန်မာအမည်များ ဘာသာပြန်ခြင်းစနစ်</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($alreadyLoggedIn): ?>
                            <!-- User already has active session -->
                            <div class="text-center mb-4">
                                <div>
                                    <span class="status-indicator status-available"></span>
                                    <span class="text-success fw-bold">Your Session is Active</span>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="upload.php" class="btn btn-success btn-lg">Continue to Upload</a>
                                <a href="logout.php?csrf=<?php echo urlencode(csrf_token()); ?>" class="btn btn-danger">End Session</a>
                            </div>
                        <?php else: ?>
                            <!-- Normal login flow -->
                            <div class="text-center mb-4">
                                <h5>System Status</h5>
                                <div>
                                    <span class="status-indicator status-available"></span>
                                    <span class="text-success fw-bold">System Available</span>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <?php echo csrf_field(); ?>
                                <div class="d-grid">
                                    <button type="submit" name="start_session" class="btn btn-primary btn-lg">
                                        Start Session
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
