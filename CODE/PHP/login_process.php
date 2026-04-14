<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['error'] = "Please enter your username and password.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STEP 1: CHECK USER ACCOUNT IN users TABLE
|--------------------------------------------------------------------------
*/
$sql = "SELECT id, username, full_name, password, profile_image, role, account_status,
               can_inventory, can_sales, can_sales_analytics, can_accounts_receivable
        FROM users
        WHERE username = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | STEP 2: IF NOT FOUND IN users, CHECK registration_requests
    |--------------------------------------------------------------------------
    */
    $requestSql = "SELECT username, request_status, admin_remarks, full_name
                   FROM registration_requests
                   WHERE username = ?
                   ORDER BY id DESC
                   LIMIT 1";

    $requestStmt = $conn->prepare($requestSql);

    if ($requestStmt) {
        $requestStmt->bind_param("s", $username);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        $requestData = $requestResult->fetch_assoc();
        $requestStmt->close();

        if ($requestData) {
            $requestStatus = strtolower(trim((string)($requestData['request_status'] ?? '')));
            $remarks = trim((string)($requestData['admin_remarks'] ?? ''));

            switch ($requestStatus) {
                case 'pending':
                    $_SESSION['error'] = "Your account request is still pending admin approval. Please wait for confirmation.";
                    $_SESSION['form_type'] = 'login';
                    header("Location: /NexGen/CODE/PHP/index.php");
                    exit();

                case 'rejected':
                    $_SESSION['error'] = $remarks !== ''
                        ? "Your account request has been rejected. Reason: " . $remarks
                        : "Your account request has been rejected by the admin. Please contact the administrator for more details.";
                    $_SESSION['form_type'] = 'login';
                    header("Location: /NexGen/CODE/PHP/index.php");
                    exit();

                case 'resubmit':
                    $_SESSION['error'] = $remarks !== ''
                        ? "Your account request needs resubmission. Please update the following: " . $remarks
                        : "Your account request needs to be resubmitted. Please review your submitted information and try again.";
                    $_SESSION['form_type'] = 'login';
                    header("Location: /NexGen/CODE/PHP/index.php");
                    exit();
            }
        }
    }

    $_SESSION['error'] = "Invalid username or password.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| STEP 3: VERIFY PASSWORD
|--------------------------------------------------------------------------
*/
if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Invalid username or password.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STEP 4: CHECK ACCOUNT STATUS IN users TABLE
|--------------------------------------------------------------------------
*/
if ($user['account_status'] !== 'active') {
    $status = strtolower(trim((string)$user['account_status']));
    $message = "Your account is not active yet. Please wait for admin approval.";

    switch ($status) {
        case 'pending':
            $message = "Your account request is still pending admin approval. Please wait for confirmation.";
            break;

        case 'rejected':
            $message = "Your account request has been rejected by the admin. Please contact the administrator for more details.";
            break;

        case 'resubmit':
            $message = "Your account request needs to be resubmitted. Please review your submitted information and try again.";
            break;

        case 'inactive':
            $message = "Your account is currently inactive. Please contact the administrator for assistance.";
            break;

        case 'disabled':
            $message = "Your account has been disabled. Please contact the administrator for assistance.";
            break;

        default:
            $message = "Your account is currently unavailable. Please contact the administrator for assistance.";
            break;
    }

    $_SESSION['error'] = $message;
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STEP 5: LOGIN SESSION
|--------------------------------------------------------------------------
*/
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default.png';
$_SESSION['role'] = $user['role'];
$_SESSION['account_status'] = $user['account_status'];
$_SESSION['can_inventory'] = (int)($user['can_inventory'] ?? 0);
$_SESSION['can_sales'] = (int)($user['can_sales'] ?? 0);
$_SESSION['can_sales_analytics'] = (int)($user['can_sales_analytics'] ?? 0);
$_SESSION['can_accounts_receivable'] = (int)($user['can_accounts_receivable'] ?? 0);
$_SESSION['success'] = "Login successful. Welcome back, " . $user['username'] . "!";

/* update last login BEFORE redirect */
$updateLastLogin = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
if ($updateLastLogin) {
    $updateLastLogin->bind_param("i", $user['id']);
    $updateLastLogin->execute();
    $updateLastLogin->close();
}

/* decide redirect target */
$redirectUrl = "/NexGen/CODE/PHP/index.php";

if ($user['role'] === 'system_admin') {
    $redirectUrl = "/NexGen/CODE/PHP/admin_dashboard.php";
} elseif (in_array($user['role'], ['owner', 'employee'], true)) {
    $redirectUrl = "/NexGen/CODE/PHP/dashboard.php";
} else {
    session_unset();
    session_destroy();
    session_start();

    $_SESSION['error'] = "Invalid user role.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$displayName = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Success - NextGen</title>
    <meta http-equiv="refresh" content="2.5;url=<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { width: 100%; height: 100%; font-family: Arial, sans-serif; overflow: hidden; }
        body { color: #ffffff; background: #0b2d7a; }
        .success-page { position: relative; min-height: 100vh; width: 100%; display: flex; align-items: center; justify-content: center; padding: 24px; overflow: hidden; }
        .video-background { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
        .bg-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 1s ease-in-out; }
        .bg-video.active { opacity: 1; }
        .video-overlay { position: absolute; inset: 0; background:
                linear-gradient(180deg, rgba(7, 26, 77, 0.30), rgba(9, 42, 117, 0.42)),
                radial-gradient(circle at center, rgba(255,255,255,0.05), transparent 45%); z-index: 1; }
        .success-card { position: relative; z-index: 2; width: 100%; max-width: 430px; border-radius: 30px; padding: 30px 26px 26px; text-align: center; background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.20); box-shadow: 0 24px 50px rgba(0, 0, 0, 0.26); }
        .id-illustration { width: 240px; height: 240px; margin: 0 auto 18px; position: relative; }
        .lanyard-top { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 42px; height: 76px; border-radius: 10px 10px 0 0; background: #58a0ff; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05); }
        .lanyard-hole { position: absolute; top: 8px; left: 50%; transform: translateX(-50%); width: 14px; height: 14px; border-radius: 50%; background: #44576a; }
        .lanyard-clip { position: absolute; top: 58px; left: 50%; transform: translateX(-50%); width: 60px; height: 16px; border-radius: 999px; background: #cfcfcf; }
        .id-body { position: absolute; top: 54px; left: 50%; transform: translateX(-50%); width: 165px; height: 170px; border-radius: 22px 22px 14px 14px; background: rgba(230, 230, 230, 0.92); box-shadow: 0 10px 18px rgba(0,0,0,0.10); overflow: hidden; }
        .profile-circle { width: 74px; height: 74px; border-radius: 50%; margin: 22px auto 0; background: #4b8ff7; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .profile-circle img { width: 100%; height: 100%; object-fit: cover; }
        .profile-placeholder { width: 34px; height: 34px; border-radius: 50%; background: #ffffff; position: relative; }
        .profile-placeholder::after { content: ""; position: absolute; left: 50%; bottom: -18px; transform: translateX(-50%); width: 58px; height: 34px; border-radius: 30px 30px 0 0; background: #ffffff; }
        .id-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 38px; background: #31445d; }
        .id-lines { position: absolute; left: 50%; bottom: 8px; transform: translateX(-50%); display: flex; gap: 8px; }
        .id-lines span { width: 4px; height: 26px; border-radius: 999px; background: #d8e6ff; }
        .shield { position: absolute; right: 18px; bottom: 18px; width: 88px; height: 88px; background: #4b8ff7; clip-path: polygon(50% 0%, 86% 16%, 100% 48%, 80% 84%, 50% 100%, 20% 84%, 0% 48%, 14% 16%); display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 22px rgba(75, 143, 247, 0.35); }
        .shield-inner { width: 58px; height: 58px; border-radius: 50%; background: #f8f8f8; display: flex; align-items: center; justify-content: center; color: #31445d; font-size: 34px; font-weight: 900; }
        .welcome-name { font-size: 28px; font-weight: 800; color: #ffffff; margin-bottom: 8px; line-height: 1.2; text-shadow: 0 4px 18px rgba(0, 0, 0, 0.28); }
        .welcome-subtitle { font-size: 16px; color: rgba(255,255,255,0.92); margin-bottom: 14px; }
        .redirect-note { font-size: 14px; color: rgba(255,255,255,0.82); }
        .loader { width: 100%; max-width: 190px; height: 8px; margin: 18px auto 0; background: rgba(255,255,255,0.22); border-radius: 999px; overflow: hidden; }
        .loader::after { content: ""; display: block; width: 100%; height: 100%; background: linear-gradient(90deg, #58a0ff, #2f5cff); transform-origin: left; animation: loadbar 2.3s linear forwards; }
        @keyframes loadbar { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @media (max-width: 520px) {
            .success-card { max-width: 100%; padding: 24px 18px 22px; border-radius: 24px; }
            .id-illustration { width: 210px; height: 210px; }
            .welcome-name { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="success-page">
        <div class="video-background">
            <video class="bg-video active" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo1.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo2.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo3.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo2.mp4" type="video/mp4">
            </video>
            <div class="video-overlay"></div>
        </div>

        <div class="success-card">
            <div class="id-illustration">
                <div class="lanyard-top">
                    <div class="lanyard-hole"></div>
                </div>
                <div class="lanyard-clip"></div>

                <div class="id-body">
                    <div class="profile-circle">
                        <?php if (!empty($profileImage)): ?>
                            <img src="/NexGen/CODE/PHP/<?php echo htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="profile-placeholder"></div>
                        <?php endif; ?>
                    </div>

                    <div class="id-bar">
                        <div class="id-lines">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>

                <div class="shield">
                    <div class="shield-inner">✓</div>
                </div>
            </div>

            <div class="welcome-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="welcome-subtitle">Login successful</div>
            <div class="redirect-note">Redirecting to your dashboard...</div>
            <div class="loader"></div>
        </div>
    </div>

    <script>
        const videos = document.querySelectorAll('.bg-video');
        let currentVideo = 0;

        setInterval(() => {
            videos[currentVideo].classList.remove('active');
            currentVideo = (currentVideo + 1) % videos.length;
            videos[currentVideo].classList.add('active');
        }, 5000);
    </script>
</body>
</html>