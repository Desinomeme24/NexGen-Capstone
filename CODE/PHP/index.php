<?php
session_start();
include 'config.php';

/* CAPTCHA: Generate manual image captcha for login and signup */
$loginCaptcha = generateImageCaptcha('login_form');
$signupCaptcha = generateImageCaptcha('signup_form');

/* SECURITY: Generate CSRF tokens for login and signup forms */
$loginCsrfToken = generateCsrfToken('login_form');
$signupCsrfToken = generateCsrfToken('signup_form');

$popupMessage = "";
$popupType = "";
$openLoginAfterLoad = false;
$openSignupAfterLoad = false;

if (isset($_GET['open']) && $_GET['open'] === 'signup') {
    $openSignupAfterLoad = true;
}

if (isset($_GET['open']) && $_GET['open'] === 'login') {
    $openLoginAfterLoad = true;
}

/* LOCKOUT: Real-time countdown data */
$showLockCountdown = false;
$lockoutUntilTs = 0;
$lockoutUsername = '';
$lockoutUserId = 0;
$lockoutRole = '';
$lockoutIsAdmin = false;

/* ATTEMPT WARNING POPUP */
$showAttemptPopup = false;
$attemptsLeft = null;
$attemptPopupText = '';

if (isset($_SESSION['lockout_until_ts']) && (int)$_SESSION['lockout_until_ts'] > time()) {
    $showLockCountdown = true;
    $lockoutUntilTs = (int)$_SESSION['lockout_until_ts'];
    $lockoutUsername = (string)($_SESSION['lockout_username'] ?? '');
    $lockoutUserId = (int)($_SESSION['lockout_user_id'] ?? 0);
    $lockoutRole = (string)($_SESSION['lockout_role'] ?? '');
    $lockoutIsAdmin = ($lockoutRole === 'system_admin');
    $openLoginAfterLoad = true;
} else {
    unset(
        $_SESSION['lockout_until_ts'],
        $_SESSION['lockout_username'],
        $_SESSION['lockout_user_id'],
        $_SESSION['lockout_role']
    );
}

if (!empty($_SESSION['attempt_warning'])) {
    $showAttemptPopup = true;
    $attemptsLeft = isset($_SESSION['attempts_left']) ? (int)$_SESSION['attempts_left'] : null;
    $attemptPopupText = (string)($_SESSION['error'] ?? 'Your login attempt was not successful.');
    $openLoginAfterLoad = true;
    unset($_SESSION['attempt_warning'], $_SESSION['attempts_left']);
}

if (isset($_SESSION['success'])) {
    $popupMessage = $_SESSION['success'];
    $popupType = "success";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $sessionError = $_SESSION['error'];

    if (!$showAttemptPopup && !$showLockCountdown) {
        $popupMessage = $sessionError;
        $popupType = "error";
    }

    if (isset($_SESSION['form_type']) && $_SESSION['form_type'] === 'login') {
        $openLoginAfterLoad = true;
    }

    if (isset($_SESSION['form_type']) && $_SESSION['form_type'] === 'signup') {
        $openSignupAfterLoad = true;
    }

    unset($_SESSION['error']);
    unset($_SESSION['form_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Next Gen Micro-Enterprise</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/style.css">

    <style>
        .captcha-checkbox-wrap {
            margin-top: 14px;
            margin-bottom: 12px;
        }

        .captcha-checkbox-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 74px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .captcha-checkbox-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .captcha-checkbox-left input[type="checkbox"] {
            width: 22px;
            height: 22px;
            accent-color: #f7d98b;
            cursor: pointer;
            flex-shrink: 0;
        }

        .captcha-checkbox-left label {
            color: #fff;
            font-size: 15px;
            cursor: pointer;
            user-select: none;
        }

        .captcha-checkbox-right {
            text-align: center;
            font-size: 10px;
            color: rgba(255,255,255,0.72);
            line-height: 1.3;
            min-width: 68px;
        }

        .captcha-checkbox-right img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            display: block;
            margin: 0 auto 4px;
            opacity: 0.9;
        }

        .captcha-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.52);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: 0.25s ease;
            z-index: 999999;
            padding: 20px;
        }

        .captcha-popup-overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .captcha-popup-box {
            width: 100%;
            max-width: 560px;
            background: linear-gradient(180deg, #1f3c88 0%, #1a3578 100%);
            border-radius: 22px;
            padding: 22px 20px 18px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            transform: translateY(14px) scale(0.97);
            transition: 0.25s ease;
        }

        .captcha-popup-overlay.show .captcha-popup-box {
            transform: translateY(0) scale(1);
        }

        .captcha-popup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .captcha-popup-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
        }

        .captcha-popup-close {
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 24px;
            cursor: pointer;
        }

        .captcha-popup-close:hover {
            background: rgba(255,255,255,0.22);
        }

        .captcha-popup-title {
            margin-bottom: 12px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }

        .captcha-popup-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .captcha-popup-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            background: rgba(255,255,255,0.08);
        }

        .captcha-popup-item input {
            display: none;
        }

        .captcha-popup-item img {
            display: block;
            width: 100%;
            height: 110px;
            object-fit: cover;
        }

        .captcha-popup-item span {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(0,0,0,0.55);
            border: 2px solid rgba(255,255,255,0.65);
            box-sizing: border-box;
        }

        .captcha-popup-item input:checked + img + span {
            background: #f7d98b;
            border-color: #f7d98b;
        }

        .captcha-help {
            margin-top: 10px;
            font-size: 13px;
            color: rgba(255,255,255,0.78);
        }

        .captcha-error-box {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255, 95, 95, 0.12);
            color: #ffd9d9;
            font-size: 13px;
            line-height: 1.5;
        }

        .captcha-popup-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .captcha-btn-cancel,
        .captcha-btn-verify {
            border: none;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            min-width: 120px;
            transition: 0.2s ease;
        }

        .captcha-btn-cancel {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }

        .captcha-btn-cancel:hover {
            background: rgba(255,255,255,0.22);
        }

        .captcha-btn-verify {
            background: #f7d98b;
            color: #17306b;
        }

        .captcha-btn-verify:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
        }

        .captcha-verified-text {
            margin-top: 8px;
            font-size: 13px;
            color: #aef3b8;
            display: none;
        }

        .captcha-verified-text.show {
            display: block;
        }

        .password-field-wrap {
            position: relative;
            width: 100%;
        }

        .password-field-wrap input {
            width: 100%;
            padding-right: 56px;
        }

        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: 1px solid rgba(247, 217, 139, 0.35);
            outline: none;
            background: black;
            color: #f7d98b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.18);
            transition: background 0.22s ease, color 0.22s ease, transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        .password-toggle-btn:hover {
            background: linear-gradient(180deg, rgba(247, 217, 139, 0.26), rgba(212, 168, 77, 0.18));
            color: black;
            border-color: rgba(247, 217, 139, 0.55);
            box-shadow: 0 12px 22px rgba(247, 217, 139, 0.16);
        }

        .password-toggle-btn:focus-visible {
            box-shadow: 0 0 0 2px rgba(247, 217, 139, 0.28), 0 12px 22px rgba(247, 217, 139, 0.16);
            border-color: rgba(247, 217, 139, 0.72);
        }

        .eye-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .eye-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
            filter: drop-shadow(0 0 6px rgba(247, 217, 139, 0.18));
        }

        .lockout-popup-overlay,
        .attempt-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.60);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000001;
            padding: 20px;
        }

        .lockout-popup-overlay.show,
        .attempt-popup-overlay.show {
            display: flex;
        }

        .lockout-popup-box,
        .attempt-popup-box {
            width: 100%;
            max-width: 460px;
            background: linear-gradient(180deg, #264ba6 0%, #1c3f99 100%);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            box-shadow: 0 24px 54px rgba(0, 0, 0, 0.35);
            color: #fff;
            text-align: center;
            padding: 28px 24px 24px;
        }

        .lockout-popup-icon,
        .attempt-popup-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 900;
        }

        .lockout-popup-icon {
            background: rgba(255, 107, 107, 0.16);
            color: #ffd2d2;
        }

        .attempt-popup-icon {
            background: rgba(247, 217, 139, 0.16);
            color: #f7d98b;
        }

        .lockout-popup-box h3,
        .attempt-popup-box h3 {
            margin: 0 0 10px;
            font-size: 26px;
            font-weight: 900;
        }

        .lockout-popup-box p,
        .attempt-popup-box p {
            margin: 0;
            font-size: 15px;
            line-height: 1.6;
            color: rgba(255,255,255,0.88);
        }

        .lockout-timer-wrap {
            margin: 18px 0 10px;
            padding: 16px 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .lockout-timer-label {
            font-size: 13px;
            color: rgba(255,255,255,0.75);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .lockout-timer {
            font-size: 34px;
            font-weight: 900;
            color: #f7d98b;
            letter-spacing: 1px;
            line-height: 1;
        }

        .lockout-popup-btn,
        .attempt-popup-btn {
            margin-top: 18px;
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            min-width: 150px;
            background: #f7d98b;
            color: #17306b;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .lockout-popup-btn:hover:not(:disabled),
        .attempt-popup-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
        }

        .lockout-popup-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .lockout-popup-btn-secondary {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }

        .lockout-popup-btn-secondary:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,0.22);
            box-shadow: none;
        }

        .login-lock-note {
            margin-top: 8px;
            font-size: 13px;
            color: #ffd8d8;
            display: none;
        }

        .login-lock-note.show {
            display: block;
        }

        .attempts-left-highlight {
            margin-top: 14px;
            font-size: 34px;
            font-weight: 900;
            color: #f7d98b;
            line-height: 1;
        }

        .admin-unlock-note {
            margin-top: 10px;
            font-size: 13px;
            color: rgba(255,255,255,0.78);
            line-height: 1.5;
        }

        .privacy-consent-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 14px;
            margin-bottom: 12px;
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
        }

        .privacy-consent-wrap input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: #f7d98b;
            flex-shrink: 0;
            cursor: pointer;
        }

        .privacy-consent-wrap label {
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
            cursor: pointer;
        }

        .privacy-policy-inline-note {
            margin-top: 8px;
            margin-bottom: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
            color: rgba(255,255,255,0.82);
            font-size: 12px;
            line-height: 1.6;
        }

        .privacy-policy-link {
            color: #f7d98b;
            font-weight: 700;
            text-decoration: none;
        }

        .privacy-policy-link:hover {
            text-decoration: underline;
        }

        .cookie-banner {
            position: fixed;
            left: 20px;
            right: 20px;
            bottom: 20px;
            z-index: 99999;
            background: rgba(8, 27, 84, 0.95);
            color: #fff;
            border-radius: 18px;
            padding: 16px 18px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.28);
            display: none;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .cookie-banner.show {
            display: flex;
        }

        .cookie-banner-text {
            max-width: 760px;
            font-size: 13px;
            line-height: 1.6;
            color: rgba(255,255,255,0.92);
        }

        .cookie-banner-text strong {
            color: #f7d98b;
            font-size: 15px;
        }

        .cookie-banner-text a {
            color: #f7d98b;
            font-weight: 700;
            text-decoration: none;
        }

        .cookie-banner-text a:hover {
            text-decoration: underline;
        }

        .cookie-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cookie-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
            min-width: 100px;
        }

        .cookie-accept {
            background: #f7d98b;
            color: #12306b;
        }

        .cookie-decline {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .captcha-notice-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.60);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000002;
    padding: 20px;
}

.captcha-notice-overlay.show {
    display: flex;
}

.captcha-notice-box {
    width: 100%;
    max-width: 420px;
    background: linear-gradient(180deg, #264ba6 0%, #1c3f99 100%);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 24px;
    box-shadow: 0 24px 54px rgba(0, 0, 0, 0.35);
    color: #fff;
    text-align: center;
    padding: 28px 24px 24px;
}

.captcha-notice-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 900;
    background: rgba(255, 107, 107, 0.16);
    color: #ffd2d2;
}

.captcha-notice-box h3 {
    margin: 0 0 10px;
    font-size: 26px;
    font-weight: 900;
}

.captcha-notice-box p {
    margin: 0;
    font-size: 15px;
    line-height: 1.6;
    color: rgba(255,255,255,0.88);
}

.captcha-notice-btn {
    margin-top: 18px;
    border: none;
    border-radius: 12px;
    padding: 12px 18px;
    min-width: 150px;
    background: #f7d98b;
    color: #17306b;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.captcha-notice-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
}
    </style>
</head>
<body>

<?php if (!empty($popupMessage)): ?>
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
            <div class="popup-icon">
                <?php echo $popupType === "success" ? "✓" : "!"; ?>
            </div>
            <h3><?php echo $popupType === "success" ? "Success" : "Error"; ?></h3>
            <p><?php echo htmlspecialchars($popupMessage); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="landing-page">

    <div class="bg-carousel">
        <div class="bg-slide active" style="background-image: url('/NexGen/IMAGES/store1.jpg');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store2.jpg');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store3.jpg');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store4.jpg');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store5.png');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store6.png');"></div>
        <div class="bg-slide" style="background-image: url('/NexGen/IMAGES/store7.png');"></div>
    </div>

    <div class="carousel-dots" id="carouselDots">
        <span class="dot active" data-slide="0"></span>
        <span class="dot" data-slide="1"></span>
        <span class="dot" data-slide="2"></span>
        <span class="dot" data-slide="3"></span>
        <span class="dot" data-slide="4"></span>
        <span class="dot" data-slide="5"></span>
        <span class="dot" data-slide="6"></span>
    </div>

    <header class="topbar">
        <div class="menu-btn" id="openLoginFromMenu">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="top-logo">
            <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
        </div>
    </header>

    <section class="hero clickable-area" id="openLoginArea">
        <div class="hero-left">
            <h1>NEXT GEN<br>MICRO-ENTERPRISE</h1>
            <p>Run your business the Next Gen way.</p>
            <button class="hero-btn" id="openLoginBtn" type="button">Our next gen way</button>
        </div>

        <div class="hero-right">
            <img src="/NexGen/IMAGES/NGlogo.png" alt="Main Logo" class="hero-logo">
        </div>
    </section>

    <div class="chatbot-icon chatbot-login-trigger" id="openLoginFromBot" title="Log in to use NextGen Assistant">
        <span class="chatbot-label">Ask NextGen AI</span>
        <span class="chatbot-logo-wrap">
            <img src="/NexGen/IMAGES/chatbot.png" alt="NextGen Assistant">
        </span>
    </div>

    <div class="modal" id="loginModal">
        <div class="modal-box">
            <button class="close-modal" id="closeLogin" type="button">&times;</button>

            <div class="modal-mini-logo">
                <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
            </div>

            <h2>LOG IN YOUR NEXT GEN ACCOUNT</h2>

            <form action="/NexGen/CODE/PHP/login_process.php" method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($loginCsrfToken); ?>">

                <label>User Name</label>
                <input type="text" name="username" id="loginUsername" required value="<?php echo htmlspecialchars($lockoutUsername); ?>">

                <label>Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="password" id="loginPassword" required>
                    <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Show password">
                        <span class="eye-icon eye-open">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </span>
                        <span class="eye-icon eye-closed" style="display:none;">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 6.3A11.2 11.2 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.1 3.8"></path>
                                <path d="M6.7 6.8C4.1 8.5 2 12 2 12a17.3 17.3 0 0 0 10 6c1.4 0 2.7-.2 3.8-.7"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                <p class="login-lock-note" id="loginLockNote">This specific account is temporarily locked. Other accounts can still log in.</p>

                <div class="captcha-checkbox-wrap">
                    <div class="captcha-checkbox-card">
                        <div class="captcha-checkbox-left">
                            <input type="checkbox" id="loginRobotCheck">
                            <label for="loginRobotCheck">I'm not a robot</label>
                        </div>
                        <div class="captcha-checkbox-right">
                            <img src="/NexGen/IMAGES/NGlogo.png" alt="Captcha">
                            Manual<br>Captcha
                        </div>
                    </div>
                    <div class="captcha-verified-text" id="loginVerifiedText">Captcha verified. You can continue.</div>
                </div>

                <button type="submit" class="silver-btn" id="loginSubmitBtn">Log in</button>

                <p class="modal-text">
                    <a href="/NexGen/CODE/PHP/forgot_password.php">Forgot Password?</a>
                </p>

                <p class="modal-text">
                    Don’t have an account?
                    <a href="#" id="goToSignup">Sign Up</a>
                </p>
            </form>
        </div>
    </div>

    <div class="modal" id="signupModal">
        <div class="modal-box signup-box">
            <button class="close-modal" id="closeSignup" type="button">&times;</button>

            <div class="modal-mini-logo">
                <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
            </div>

            <h2>SUBMIT ACCOUNT REQUEST</h2>

            <form action="/NexGen/CODE/PHP/signup_process.php" method="POST" enctype="multipart/form-data" id="signupForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($signupCsrfToken); ?>">

                <label>Employee Number</label>
                <input type="text" name="employee_no" required>

                <label>User Name</label>
                <input type="text" name="signup_username" required>

                <label>Full Name</label>
                <input type="text" name="fullname" required>

                <label>Email Account</label>
                <input type="email" name="email" required>

                <label>Phone Number</label>
                <input type="text" name="phone" required>

                <label>Address</label>
                <input type="text" name="address" required>

                <label>Requested Role</label>
                <select name="requested_role" required>
                    <option value="">Select Role</option>
                    <option value="employee">Employee</option>
                    <option value="owner">Owner</option>
                </select>

                <label>Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="signup_password" id="signupPassword" required>
                    <button type="button" class="password-toggle-btn" data-target="signupPassword" aria-label="Show password">
                        <span class="eye-icon eye-open">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </span>
                        <span class="eye-icon eye-closed" style="display:none;">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 6.3A11.2 11.2 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.1 3.8"></path>
                                <path d="M6.7 6.8C4.1 8.5 2 12 2 12a17.3 17.3 0 0 0 10 6c1.4 0 2.7-.2 3.8-.7"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                <label>Confirm Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="confirm_password" id="signupConfirmPassword" required>
                    <button type="button" class="password-toggle-btn" data-target="signupConfirmPassword" aria-label="Show password">
                        <span class="eye-icon eye-open">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </span>
                        <span class="eye-icon eye-closed" style="display:none;">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 6.3A11.2 11.2 0 0 1 12 6c6.4 0 10 6 10 6a17.6 17.6 0 0 1-3.1 3.8"></path>
                                <path d="M6.7 6.8C4.1 8.5 2 12 2 12a17.3 17.3 0 0 0 10 6c1.4 0 2.7-.2 3.8-.7"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                            </svg>
                        </span>
                    </button>
                </div>

                <label>Valid ID</label>
                <input type="file" name="valid_id" accept="image/*,.pdf" required>

                <div class="privacy-consent-wrap">
                    <input type="checkbox" name="privacy_consent" id="privacy_consent" required>
                    <label for="privacy_consent">
                        I have read and agree to the
                        <a href="/NexGen/CODE/PHP/privacy_policy.php?return_to=signup" class="privacy-policy-link">Privacy Policy</a>
                    </label>
                </div>

                <div class="privacy-policy-inline-note">
                    Your submitted information will be used only for account request review, authentication, access control,
                    security logging, and legitimate system operations.
                </div>

                <div class="captcha-checkbox-wrap">
                    <div class="captcha-checkbox-card">
                        <div class="captcha-checkbox-left">
                            <input type="checkbox" id="signupRobotCheck">
                            <label for="signupRobotCheck">I'm not a robot</label>
                        </div>
                        <div class="captcha-checkbox-right">
                            <img src="/NexGen/IMAGES/NGlogo.png" alt="Captcha">
                            Manual<br>Captcha
                        </div>
                    </div>
                    <div class="captcha-verified-text" id="signupVerifiedText">Captcha verified. You can continue.</div>
                </div>

                <button type="submit" class="silver-btn">Submit for Approval</button>

                <p class="modal-text">
                    Have an account?
                    <a href="#" id="goToLogin">Sign in</a>
                </p>
            </form>
        </div>
    </div>

</div>

<div class="cookie-banner" id="cookieBanner">
    <div class="cookie-banner-text">
        <strong>Cookie Consent</strong><br>
        This system uses essential cookies or browser storage for login session handling, inactivity timeout,
        security settings, and access control. By continuing to use NextGen, you acknowledge this use.
        Read our
        <a href="/NexGen/CODE/PHP/privacy_policy.php?return_to=login">Privacy Policy</a>.
    </div>

    <div class="cookie-actions">
        <button class="cookie-btn cookie-accept" id="acceptCookiesBtn" type="button">Accept</button>
        <button class="cookie-btn cookie-decline" id="declineCookiesBtn" type="button">Decline</button>
    </div>
</div>

<div class="captcha-popup-overlay" id="loginCaptchaPopup">
    <div class="captcha-popup-box">
        <div class="captcha-popup-header">
            <h3>Verify Login</h3>
            <button type="button" class="captcha-popup-close" id="closeLoginCaptchaPopup">&times;</button>
        </div>

        <?php if ($loginCaptcha['success']): ?>
            <div class="captcha-popup-title">
                Select all images with <strong><?php echo htmlspecialchars($loginCaptcha['target_label']); ?></strong>
            </div>

            <div class="captcha-popup-grid" id="loginCaptchaGrid">
                <?php foreach ($loginCaptcha['items'] as $item): ?>
                    <label class="captcha-popup-item">
                        <input type="checkbox" name="captcha_selection[]" value="<?php echo htmlspecialchars($item['id']); ?>" form="loginForm">
                        <img src="<?php echo htmlspecialchars($item['relative_path']); ?>" alt="Captcha image">
                        <span></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="captcha-help">Choose only the matching images, then click Verify.</p>

            <div class="captcha-popup-actions">
                <button type="button" class="captcha-btn-cancel" id="cancelLoginCaptcha">Cancel</button>
                <button type="button" class="captcha-btn-verify" id="verifyLoginCaptcha">Verify</button>
            </div>
        <?php else: ?>
            <div class="captcha-error-box"><?php echo htmlspecialchars($loginCaptcha['message']); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="captcha-popup-overlay" id="signupCaptchaPopup">
    <div class="captcha-popup-box">
        <div class="captcha-popup-header">
            <h3>Verify Signup</h3>
            <button type="button" class="captcha-popup-close" id="closeSignupCaptchaPopup">&times;</button>
        </div>

        <?php if ($signupCaptcha['success']): ?>
            <div class="captcha-popup-title">
                Select all images with <strong><?php echo htmlspecialchars($signupCaptcha['target_label']); ?></strong>
            </div>

            <div class="captcha-popup-grid" id="signupCaptchaGrid">
                <?php foreach ($signupCaptcha['items'] as $item): ?>
                    <label class="captcha-popup-item">
                        <input type="checkbox" name="captcha_selection[]" value="<?php echo htmlspecialchars($item['id']); ?>" form="signupForm">
                        <img src="<?php echo htmlspecialchars($item['relative_path']); ?>" alt="Captcha image">
                        <span></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="captcha-help">Choose only the matching images, then click Verify.</p>

            <div class="captcha-popup-actions">
                <button type="button" class="captcha-btn-cancel" id="cancelSignupCaptcha">Cancel</button>
                <button type="button" class="captcha-btn-verify" id="verifySignupCaptcha">Verify</button>
            </div>
        <?php else: ?>
            <div class="captcha-error-box"><?php echo htmlspecialchars($signupCaptcha['message']); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="lockout-popup-overlay" id="lockoutPopup">
    <div class="lockout-popup-box">
        <div class="lockout-popup-icon">!</div>
        <h3>Account Locked</h3>
        <p id="lockoutMessage">
            Too many failed login attempts. Please wait until the countdown reaches zero before trying again.
        </p>

        <div class="lockout-timer-wrap">
            <div class="lockout-timer-label">Time Remaining</div>
            <div class="lockout-timer" id="lockoutTimer">15:00</div>
        </div>

        <div class="admin-unlock-note" id="adminUnlockNote" style="display:none;">
            Admin account detected. You can use OTP to unlock this locked admin account securely.
        </div>

        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:18px;">
            <button type="button" class="lockout-popup-btn lockout-popup-btn-secondary" id="lockoutBackBtn">Back</button>
            <button type="button" class="lockout-popup-btn lockout-popup-btn-secondary" id="adminOtpUnlockBtn" style="display:none;">Use OTP to Unlock</button>
            <button type="button" class="lockout-popup-btn" id="lockoutOkayBtn" disabled>Wait for Unlock</button>
        </div>
    </div>
</div>

<div class="attempt-popup-overlay" id="attemptPopup">
    <div class="attempt-popup-box">
        <div class="attempt-popup-icon">!</div>
        <h3>Login Warning</h3>
        <p id="attemptPopupMessage">Your login attempt failed.</p>
        <<div class="attempts-left-highlight" id="attemptsLeftHighlight">2</div>
        <p style="margin-top: 10px;">attempt(s) left before your account is temporarily locked.</p>
        <button type="button" class="attempt-popup-btn" id="attemptPopupBtn">OK</button>
    </div>
</div>
<div class="captcha-notice-overlay" id="captchaNoticePopup">
    <div class="captcha-notice-box">
        <div class="captcha-notice-icon">!</div>
        <h3>Captcha Required</h3>
        <p id="captchaNoticeMessage">Please select captcha images first.</p>
        <button type="button" class="captcha-notice-btn" id="captchaNoticeOkBtn">OK</button>
    </div>
</div>

<script>
window.addEventListener("load", function () {
    <?php if ($openLoginAfterLoad): ?>
        if (typeof openLoginModal === "function") openLoginModal();
    <?php endif; ?>

    <?php if ($openSignupAfterLoad): ?>
        if (typeof openSignupModal === "function") openSignupModal();
    <?php endif; ?>

    const cookieBanner = document.getElementById('cookieBanner');
    const acceptCookiesBtn = document.getElementById('acceptCookiesBtn');
    const declineCookiesBtn = document.getElementById('declineCookiesBtn');
    const cookieConsent = localStorage.getItem('nextgen_cookie_consent');

    if (!cookieConsent && cookieBanner) {
        cookieBanner.classList.add('show');
    }

    if (acceptCookiesBtn) {
        acceptCookiesBtn.addEventListener('click', function() {
            localStorage.setItem('nextgen_cookie_consent', 'accepted');
            cookieBanner.classList.remove('show');
        });
    }

    if (declineCookiesBtn) {
        declineCookiesBtn.addEventListener('click', function() {
            localStorage.setItem('nextgen_cookie_consent', 'declined');
            cookieBanner.classList.remove('show');
        });
    }

    const loginRobotCheck = document.getElementById('loginRobotCheck');
    const loginCaptchaPopup = document.getElementById('loginCaptchaPopup');
    const closeLoginCaptchaPopup = document.getElementById('closeLoginCaptchaPopup');
    const cancelLoginCaptcha = document.getElementById('cancelLoginCaptcha');
    const verifyLoginCaptcha = document.getElementById('verifyLoginCaptcha');
    const loginVerifiedText = document.getElementById('loginVerifiedText');

    function openLoginCaptchaModal() {
        if (loginCaptchaPopup) {
            loginCaptchaPopup.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeLoginCaptchaModal(uncheck = true) {
        if (loginCaptchaPopup) {
            loginCaptchaPopup.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (uncheck && loginRobotCheck) {
            loginRobotCheck.checked = false;
        }
    }

    function currentEnteredUsername() {
        const el = document.getElementById('loginUsername');
        return el ? el.value.trim() : '';
    }

    function isLockedUsernameAttempt() {
        const entered = currentEnteredUsername().toLowerCase();
        const lockedUser = (lockoutUsername || '').toLowerCase();

        if (!lockoutActive || lockoutUntilTs <= Math.floor(Date.now() / 1000)) {
            return false;
        }

        if (!lockedUser) {
            return false;
        }

        return entered !== '' && entered === lockedUser;
    }

    if (loginRobotCheck) {
        loginRobotCheck.addEventListener('change', function(e) {
            if (isLockedUsernameAttempt()) {
                e.preventDefault();
                this.checked = false;
                openLockoutPopup();
                return;
            }

            if (this.checked) {
                openLoginCaptchaModal();
            } else {
                if (loginVerifiedText) loginVerifiedText.classList.remove('show');
                document.querySelectorAll('#loginCaptchaPopup input[type="checkbox"]').forEach(function(box) {
                    box.checked = false;
                });
            }
        });
    }

    if (closeLoginCaptchaPopup) {
        closeLoginCaptchaPopup.addEventListener('click', function() {
            closeLoginCaptchaModal(true);
        });
    }

    if (cancelLoginCaptcha) {
        cancelLoginCaptcha.addEventListener('click', function() {
            closeLoginCaptchaModal(true);
        });
    }

    if (verifyLoginCaptcha) {
        verifyLoginCaptcha.addEventListener('click', function() {
            const checked = document.querySelectorAll('#loginCaptchaPopup input[type="checkbox"]:checked').length;
            if (checked > 0) {
                closeLoginCaptchaModal(false);
                if (loginVerifiedText) loginVerifiedText.classList.add('show');
            } else {
                openCaptchaNotice('Please select captcha images first.');
            }
        });
    }

    const signupRobotCheck = document.getElementById('signupRobotCheck');
    const signupCaptchaPopup = document.getElementById('signupCaptchaPopup');
    const closeSignupCaptchaPopup = document.getElementById('closeSignupCaptchaPopup');
    const cancelSignupCaptcha = document.getElementById('cancelSignupCaptcha');
    const verifySignupCaptcha = document.getElementById('verifySignupCaptcha');
    const signupVerifiedText = document.getElementById('signupVerifiedText');

    function openSignupCaptchaModal() {
        if (signupCaptchaPopup) {
            signupCaptchaPopup.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSignupCaptchaModal(uncheck = true) {
        if (signupCaptchaPopup) {
            signupCaptchaPopup.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (uncheck && signupRobotCheck) {
            signupRobotCheck.checked = false;
        }
    }

    if (signupRobotCheck) {
        signupRobotCheck.addEventListener('change', function() {
            if (this.checked) {
                openSignupCaptchaModal();
            } else {
                if (signupVerifiedText) signupVerifiedText.classList.remove('show');
                document.querySelectorAll('#signupCaptchaPopup input[type="checkbox"]').forEach(function(box) {
                    box.checked = false;
                });
            }
        });
    }

    if (closeSignupCaptchaPopup) {
        closeSignupCaptchaPopup.addEventListener('click', function() {
            closeSignupCaptchaModal(true);
        });
    }

    if (cancelSignupCaptcha) {
        cancelSignupCaptcha.addEventListener('click', function() {
            closeSignupCaptchaModal(true);
        });
    }

    if (verifySignupCaptcha) {
        verifySignupCaptcha.addEventListener('click', function() {
            const checked = document.querySelectorAll('#signupCaptchaPopup input[type="checkbox"]:checked').length;
            if (checked > 0) {
                closeSignupCaptchaModal(false);
                if (signupVerifiedText) signupVerifiedText.classList.add('show');
            } else {
                alert('Please select captcha images first.');
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target === loginCaptchaPopup) {
            closeLoginCaptchaModal(true);
        }

        if (e.target === signupCaptchaPopup) {
            closeSignupCaptchaModal(true);
        }
        if (e.target === captchaNoticePopup) {
    closeCaptchaNotice();
}
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLoginCaptchaModal(true);
            closeSignupCaptchaModal(true);
        }
        closeCaptchaNotice();
    });

    document.querySelectorAll('.password-toggle-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const openIcon = this.querySelector('.eye-open');
            const closedIcon = this.querySelector('.eye-closed');

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                this.setAttribute('aria-label', 'Hide password');
                if (openIcon) openIcon.style.display = 'none';
                if (closedIcon) closedIcon.style.display = 'flex';
            } else {
                input.type = 'password';
                this.setAttribute('aria-label', 'Show password');
                if (openIcon) openIcon.style.display = 'flex';
                if (closedIcon) closedIcon.style.display = 'none';
            }
        });
    });
    const captchaNoticePopup = document.getElementById('captchaNoticePopup');
const captchaNoticeMessage = document.getElementById('captchaNoticeMessage');
const captchaNoticeOkBtn = document.getElementById('captchaNoticeOkBtn');

function openCaptchaNotice(message) {
    if (!captchaNoticePopup || !captchaNoticeMessage) return;
    captchaNoticeMessage.textContent = message || 'Please select captcha images first.';
    captchaNoticePopup.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeCaptchaNotice() {
    if (!captchaNoticePopup) return;
    captchaNoticePopup.classList.remove('show');

    const loginOpen = loginCaptchaPopup && loginCaptchaPopup.classList.contains('show');
    const signupOpen = signupCaptchaPopup && signupCaptchaPopup.classList.contains('show');

    document.body.style.overflow = (loginOpen || signupOpen) ? 'hidden' : '';
}

if (captchaNoticeOkBtn) {
    captchaNoticeOkBtn.addEventListener('click', closeCaptchaNotice);
}

    const lockoutActive = <?php echo $showLockCountdown ? 'true' : 'false'; ?>;
    const lockoutUntilTs = <?php echo (int)$lockoutUntilTs; ?>;
    const lockoutUsername = <?php echo json_encode($lockoutUsername); ?>;
    const lockoutIsAdmin = <?php echo $lockoutIsAdmin ? 'true' : 'false'; ?>;

    const lockoutPopup = document.getElementById('lockoutPopup');
    const lockoutTimer = document.getElementById('lockoutTimer');
    const lockoutMessage = document.getElementById('lockoutMessage');
    const lockoutOkayBtn = document.getElementById('lockoutOkayBtn');
    const lockoutBackBtn = document.getElementById('lockoutBackBtn');
    const adminOtpUnlockBtn = document.getElementById('adminOtpUnlockBtn');
    const adminUnlockNote = document.getElementById('adminUnlockNote');

    const loginForm = document.getElementById('loginForm');
    const loginUsername = document.getElementById('loginUsername');
    const loginPassword = document.getElementById('loginPassword');
    const loginSubmitBtn = document.getElementById('loginSubmitBtn');
    const loginLockNote = document.getElementById('loginLockNote');

    function formatSeconds(totalSeconds) {
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function setLoginLockedState(showNoteOnly) {
        if (loginUsername) loginUsername.disabled = false;
        if (loginPassword) loginPassword.disabled = false;
        if (loginSubmitBtn) loginSubmitBtn.disabled = false;
        if (loginRobotCheck) loginRobotCheck.disabled = false;

        document.querySelectorAll('#loginForm .password-toggle-btn').forEach(function(btn) {
            btn.disabled = false;
        });

        if (loginLockNote) {
            loginLockNote.classList.toggle('show', showNoteOnly);
        }
    }

    function openLockoutPopup() {
        if (lockoutPopup) {
            lockoutPopup.classList.add('show');
        }
    }

    function closeLockoutPopup() {
        if (lockoutPopup) {
            lockoutPopup.classList.remove('show');
        }
    }

    if (lockoutBackBtn) {
        lockoutBackBtn.addEventListener('click', function() {
            closeLockoutPopup();
        });
    }

    if (adminOtpUnlockBtn) {
        adminOtpUnlockBtn.addEventListener('click', function() {
            window.location.href = '/NexGen/CODE/PHP/admin_unlock_otp.php';
        });
    }

    if (lockoutActive && lockoutUntilTs > 0) {
        if (typeof openLoginModal === "function") openLoginModal();
        setLoginLockedState(true);

        if (currentEnteredUsername().toLowerCase() === (lockoutUsername || '').toLowerCase()) {
            openLockoutPopup();
        }

        if (lockoutIsAdmin) {
            if (adminOtpUnlockBtn) adminOtpUnlockBtn.style.display = 'inline-flex';
            if (adminUnlockNote) adminUnlockNote.style.display = 'block';
        } else {
            if (adminOtpUnlockBtn) adminOtpUnlockBtn.style.display = 'none';
            if (adminUnlockNote) adminUnlockNote.style.display = 'none';
        }

        if (lockoutMessage) {
            if (lockoutUsername) {
                lockoutMessage.textContent = 'The account "' + lockoutUsername + '" is temporarily locked due to multiple failed login attempts. Please wait for the countdown to finish.';
            } else {
                lockoutMessage.textContent = 'This account is temporarily locked due to multiple failed login attempts. Please wait for the countdown to finish.';
            }
        }

        const timerInterval = setInterval(function() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = lockoutUntilTs - now;

            if (remaining <= 0) {
                clearInterval(timerInterval);

                if (lockoutTimer) {
                    lockoutTimer.textContent = '00:00';
                }

                if (lockoutMessage) {
                    lockoutMessage.textContent = 'The lock period has ended. You may now try logging in again.';
                }

                if (lockoutOkayBtn) {
                    lockoutOkayBtn.disabled = false;
                    lockoutOkayBtn.textContent = 'Try Login Again';
                    lockoutOkayBtn.onclick = function() {
                        closeLockoutPopup();
                    };
                }

                if (adminOtpUnlockBtn) adminOtpUnlockBtn.style.display = 'none';
                if (adminUnlockNote) adminUnlockNote.style.display = 'none';

                setLoginLockedState(false);
                return;
            }

            if (lockoutTimer) {
                lockoutTimer.textContent = formatSeconds(remaining);
            }
        }, 1000);
    } else {
        setLoginLockedState(false);

        if (lockoutOkayBtn) {
            lockoutOkayBtn.disabled = false;
            lockoutOkayBtn.textContent = 'OK';
            lockoutOkayBtn.onclick = function() {
                closeLockoutPopup();
            };
        }

        if (adminOtpUnlockBtn) adminOtpUnlockBtn.style.display = 'none';
        if (adminUnlockNote) adminUnlockNote.style.display = 'none';
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (isLockedUsernameAttempt()) {
                e.preventDefault();
                openLockoutPopup();
                return false;
            }
        });
    }

    if (loginUsername) {
        loginUsername.addEventListener('input', function() {
            if (!isLockedUsernameAttempt()) {
                closeLockoutPopup();
                if (loginLockNote) loginLockNote.classList.remove('show');
            } else {
                if (loginLockNote) loginLockNote.classList.add('show');
            }
        });
    }

    const showAttemptPopup = <?php echo $showAttemptPopup ? 'true' : 'false'; ?>;
    const attemptsLeft = <?php echo $attemptsLeft === null ? 'null' : (int)$attemptsLeft; ?>;
    const attemptPopupText = <?php echo json_encode($attemptPopupText); ?>;

    const attemptPopup = document.getElementById('attemptPopup');
    const attemptPopupBtn = document.getElementById('attemptPopupBtn');
    const attemptsLeftHighlight = document.getElementById('attemptsLeftHighlight');
    const attemptPopupMessage = document.getElementById('attemptPopupMessage');

    if (showAttemptPopup && attemptPopup) {
        if (typeof openLoginModal === "function") openLoginModal();

        if (attemptsLeftHighlight && attemptsLeft !== null) {
            attemptsLeftHighlight.textContent = String(attemptsLeft);
        }

        if (attemptPopupMessage) {
            attemptPopupMessage.textContent = attemptPopupText || 'Your login attempt was not successful.';
        }

        attemptPopup.classList.add('show');
    }

    if (attemptPopupBtn) {
        attemptPopupBtn.addEventListener('click', function() {
            if (attemptPopup) {
                attemptPopup.classList.remove('show');
            }
        });
    }
});
</script>
<script src="/NexGen/CODE/JS/script.js"></script>
</body>
</html>