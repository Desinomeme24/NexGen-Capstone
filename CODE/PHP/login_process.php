<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/* SECURITY: CSRF validation */
if (!validateCsrfToken('login_form', $_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid or expired login form token.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$captchaSelection = $_POST['captcha_selection'] ?? [];

if ($username === '' || $password === '') {
    $_SESSION['error'] = "Please enter your username and password.";
    $_SESSION['form_type'] = 'login';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STEP 1: CHECK USER ACCOUNT IN users TABLE FIRST
|--------------------------------------------------------------------------
*/
$sql = "SELECT id, username, full_name, email, password, profile_image, role, account_status,
               can_inventory, can_sales, can_sales_analytics, can_accounts_receivable,
               failed_login_attempts, locked_until
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

/* SECURITY: Check current lock first */
if (isUserLocked($user)) {
    $lockUntilTs = strtotime((string)$user['locked_until']);

    $_SESSION['error'] = "Your account is temporarily locked due to multiple failed login attempts.";
    $_SESSION['form_type'] = 'login';
    $_SESSION['lockout_until_ts'] = $lockUntilTs;
    $_SESSION['lockout_username'] = $user['username'];
    $_SESSION['lockout_user_id'] = (int)$user['id'];
    $_SESSION['lockout_role'] = (string)$user['role'];

    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| HELPER: record failed attempt and optionally lock account
|--------------------------------------------------------------------------
*/
function recordFailedAttempt(mysqli $conn, array $user, string $reason = 'Invalid username or password.'): void
{
    $newAttempts = ((int)$user['failed_login_attempts']) + 1;
    $maxAttempts = 3;
    $attemptsLeft = max(0, $maxAttempts - $newAttempts);
    $lockedUntil = null;

    if ($newAttempts >= $maxAttempts) {
        $newAttempts = $maxAttempts;
        $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    }

    $updateFailSql = "UPDATE users
                      SET failed_login_attempts = ?, locked_until = ?, last_failed_login_at = NOW()
                      WHERE id = ?";
    $updateFailStmt = $conn->prepare($updateFailSql);

    if ($updateFailStmt) {
        $updateFailStmt->bind_param("isi", $newAttempts, $lockedUntil, $user['id']);
        $updateFailStmt->execute();
        $updateFailStmt->close();
    }

    $_SESSION['form_type'] = 'login';

    if ($lockedUntil !== null) {
        $_SESSION['error'] = "Too many failed login attempts. Your account has been locked for 15 minutes.";
        $_SESSION['lockout_until_ts'] = strtotime($lockedUntil);
        $_SESSION['lockout_username'] = $user['username'];
        $_SESSION['lockout_user_id'] = (int)$user['id'];
        $_SESSION['lockout_role'] = (string)$user['role'];
        unset($_SESSION['attempt_warning'], $_SESSION['attempts_left']);
    } else {
        $_SESSION['error'] = $reason;
        $_SESSION['attempt_warning'] = true;
        $_SESSION['attempts_left'] = $attemptsLeft;
        unset(
            $_SESSION['lockout_until_ts'],
            $_SESSION['lockout_username'],
            $_SESSION['lockout_user_id'],
            $_SESSION['lockout_role']
        );
    }
}

/*
|--------------------------------------------------------------------------
| CAPTCHA: wrong captcha also counts as failed attempt
|--------------------------------------------------------------------------
*/
if (!validateImageCaptchaSelection('login_form', (array)$captchaSelection)) {
    recordFailedAttempt(
        $conn,
        $user,
        "Incorrect captcha selection. You have " . max(0, 3 - (((int)$user['failed_login_attempts']) + 1)) . " attempt(s) left before your account is temporarily locked."
    );
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STEP 3: VERIFY PASSWORD
|--------------------------------------------------------------------------
*/
if (!password_verify($password, $user['password'])) {
    recordFailedAttempt(
        $conn,
        $user,
        "Invalid username or password. You have " . max(0, 3 - (((int)$user['failed_login_attempts']) + 1)) . " attempt(s) left before your account is temporarily locked."
    );
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/* SECURITY: Reset attempts after successful login */
$resetAttemptSql = "UPDATE users
                    SET failed_login_attempts = 0,
                        locked_until = NULL,
                        last_failed_login_at = NULL
                    WHERE id = ?";
$resetAttemptStmt = $conn->prepare($resetAttemptSql);
if ($resetAttemptStmt) {
    $resetAttemptStmt->bind_param("i", $user['id']);
    $resetAttemptStmt->execute();
    $resetAttemptStmt->close();
}

unset(
    $_SESSION['lockout_until_ts'],
    $_SESSION['lockout_username'],
    $_SESSION['lockout_user_id'],
    $_SESSION['lockout_role'],
    $_SESSION['attempt_warning'],
    $_SESSION['attempts_left']
);

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

/* SESSION SECURITY: start inactivity timer after successful login */
$_SESSION['last_activity'] = time();
if (defined('SESSION_TIMEOUT_SECONDS')) {
    $_SESSION['session_timeout_seconds'] = SESSION_TIMEOUT_SECONDS;
}

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

$displayName  = !empty($user['full_name'])     ? $user['full_name']     : $user['username'];
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Success – NextGen</title>
    <meta http-equiv="refresh" content="2.5;url=<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            font-family: 'DM Sans', sans-serif;
            background: #0a1220;
        }

        /* ── Same videos as dashboard ── */
        .bg-video-wrap {
            position: fixed; inset: 0; z-index: 0; overflow: hidden;
        }
        .bg-video {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1.2s ease-in-out;
        }
        .bg-video.active { opacity: 1; }

        /* ── Dim so content stays readable ── */
        .overlay {
            position: fixed; inset: 0; z-index: 1;
            background: rgba(5, 12, 35, 0.42);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        /* ── Center stage ── */
        .stage {
            position: fixed; inset: 0; z-index: 2;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── Floating content — no card box ── */
        .content {
            text-align: center;
            animation: floatIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            opacity: 0;
            transform: translateY(28px);
        }
        @keyframes floatIn {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── ID Badge ── */
        .badge-wrap {
            position: relative;
            width: 136px;
            margin: 0 auto 32px;
        }
        .badge-clip {
            width: 40px; height: 28px;
            background: linear-gradient(160deg, #7aaeff 0%, #3b6ef8 100%);
            border-radius: 8px 8px 0 0;
            margin: 0 auto -10px;
            position: relative; z-index: 2;
            box-shadow: 0 -4px 10px rgba(59,110,248,0.4);
        }
        .badge-clip::after {
            content: '';
            position: absolute;
            top: 9px; left: 50%; transform: translateX(-50%);
            width: 14px; height: 7px;
            background: rgba(0,0,0,0.25); border-radius: 20px;
        }
        .badge-body {
            background: linear-gradient(170deg, #e8f0fc 0%, #cfddf5 100%);
            border-radius: 16px;
            width: 100%; height: 148px;
            position: relative; z-index: 1;
            display: flex; flex-direction: column; align-items: center;
            box-shadow: 0 14px 40px rgba(0,0,0,0.45), 0 1px 0 rgba(255,255,255,0.6) inset;
            overflow: hidden;
        }
        .profile-img {
            width: 70px; height: 70px;
            border-radius: 50%; object-fit: cover;
            margin-top: 22px;
            border: 3px solid #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
            animation: avatarPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.25s both;
        }
        @keyframes avatarPop {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .badge-footer {
            background: #1a3a7a; width: 100%; height: 34px;
            margin-top: auto;
            display: flex; align-items: center; justify-content: center;
            gap: 3px; padding: 0 14px;
        }
        .bar { display: block; height: 16px; background: rgba(255,255,255,0.88); border-radius: 1px; }
        .bar:nth-child(1){width:2px} .bar:nth-child(2){width:4px}
        .bar:nth-child(3){width:1px} .bar:nth-child(4){width:3px}
        .bar:nth-child(5){width:5px} .bar:nth-child(6){width:2px}
        .bar:nth-child(7){width:3px} .bar:nth-child(8){width:1px}
        .bar:nth-child(9){width:4px} .bar:nth-child(10){width:2px}

        /* ── Verified hex badge ── */
        .verified-badge {
            position: absolute; bottom: 10px; right: -14px; z-index: 3;
            animation: badgePop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) 0.5s both;
        }
        @keyframes badgePop {
            from { transform: scale(0) rotate(-30deg); opacity: 0; }
            to   { transform: scale(1) rotate(0deg);   opacity: 1; }
        }
        .verified-outer {
            width: 52px; height: 52px; background: #fff;
            clip-path: polygon(50% 0%,93% 25%,93% 75%,50% 100%,7% 75%,7% 25%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px rgba(59,110,248,0.45);
        }
        .verified-inner {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #6a95ff 0%, #3b6ef8 100%);
            clip-path: polygon(50% 0%,93% 25%,93% 75%,50% 100%,7% 75%,7% 25%);
            display: flex; align-items: center; justify-content: center;
        }
        .verified-inner svg {
            width: 18px; height: 18px;
            stroke: #fff; stroke-width: 3;
            stroke-linecap: round; stroke-linejoin: round; fill: none;
        }

        /* ── Text ── */
        .label-success {
            font-family: 'Sora', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 2.5px; text-transform: uppercase;
            color: #6a95ff; margin-bottom: 8px;
        }
        .user-name {
            font-family: 'Sora', sans-serif;
            font-size: 28px; font-weight: 800;
            color: #ffffff; letter-spacing: -0.3px;
            margin-bottom: 6px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }
        .label-redirect {
            font-size: 13px; color: rgba(255,255,255,0.6);
            margin-bottom: 24px;
        }

        /* ── Progress bar ── */
        .progress-wrap { width: 180px; margin: 0 auto; }
        .progress-track {
            height: 4px; background: rgba(255,255,255,0.18);
            border-radius: 99px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; width: 0%; border-radius: 99px;
            background: linear-gradient(90deg, #3b6ef8 0%, #6a95ff 100%);
            box-shadow: 0 0 10px rgba(106,149,255,0.7);
            animation: fillBar 2.5s cubic-bezier(0.4,0,0.2,1) forwards;
        }
        @keyframes fillBar { 0%{width:0%} 100%{width:100%} }
    </style>
</head>
<body>

<div class="bg-video-wrap">
    <video class="bg-video active" autoplay muted loop playsinline>
        <source src="/NexGen/VIDEOS/storevideo1.mp4" type="video/mp4">
    </video>
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="/NexGen/VIDEOS/storevideo2.mp4" type="video/mp4">
    </video>
    <video class="bg-video" autoplay muted loop playsinline>
        <source src="/NexGen/VIDEOS/storevideo3.mp4" type="video/mp4">
    </video>
</div>

<div class="overlay"></div>

<div class="stage">
    <div class="content">

        <div class="badge-wrap">
            <div class="badge-clip"></div>
            <div class="badge-body">
                <img class="profile-img"
                     src="<?php echo htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="Profile"
                     onerror="this.src='uploads/default.png'">
                <div class="badge-footer">
                    <span class="bar"></span><span class="bar"></span>
                    <span class="bar"></span><span class="bar"></span>
                    <span class="bar"></span><span class="bar"></span>
                    <span class="bar"></span><span class="bar"></span>
                    <span class="bar"></span><span class="bar"></span>
                </div>
            </div>
            <div class="verified-badge">
                <div class="verified-outer">
                    <div class="verified-inner">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="label-success">Login Successful</div>
        <div class="user-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="label-redirect">Redirecting to your dashboard…</div>

        <div class="progress-wrap">
            <div class="progress-track">
                <div class="progress-fill"></div>
            </div>
        </div>

    </div>
</div>

<script>
(function(){
    var videos = document.querySelectorAll('.bg-video');
    var i = 0;
    setInterval(function(){
        videos[i].classList.remove('active');
        i = (i + 1) % videos.length;
        videos[i].classList.add('active');
    }, 6000);
})();
</script>
</body>
</html>