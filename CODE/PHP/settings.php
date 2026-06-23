<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT username, full_name, email, phone, address, profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$popupMessage = "";
$popupType = "";

if (isset($_SESSION['success'])) {
    $popupMessage = $_SESSION['success'];
    $popupType = "success";
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $popupMessage = $_SESSION['error'];
    $popupType = "error";
    unset($_SESSION['error']);
}

$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default.png';

$backLink = "/NexGen/CODE/PHP/dashboard.php";
if (isset($_SESSION['role']) && $_SESSION['role'] === 'system_admin') {
    $backLink = "/NexGen/CODE/PHP/admin_dashboard.php";
}

$otpNewPasswordValue = $_SESSION['otp_new_password_plain'] ?? '';
$otpConfirmPasswordValue = $_SESSION['otp_confirm_password_plain'] ?? '';

/* SECURITY: Generate CSRF tokens for settings forms */
$accountCsrfToken = generateCsrfToken('update_account_form');
$directPasswordCsrfToken = generateCsrfToken('change_password_direct_form');
$requestOtpCsrfToken = generateCsrfToken('request_password_change_form');
$verifyOtpCsrfToken = generateCsrfToken('verify_password_otp_form');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/settings.css">
    <style>
        .security-option-box {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .security-option-box label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #fff;
        }

        .security-option-box select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            color: #fff;
            outline: none;
            font-size: 15px;
        }

        .security-option-box select option {
            color: #000;
        }

        .security-method {
            display: none;
            margin-top: 20px;
            animation: fadeIn 0.3s ease;
        }

        .security-method.active-method {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<?php if (!empty($popupMessage)): ?>
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
            <div class="popup-icon"><?php echo $popupType === 'success' ? '✓' : '!'; ?></div>
            <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
            <p><?php echo htmlspecialchars($popupMessage); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="settings-page">

    <aside class="settings-sidebar">
        <div class="sidebar-profile-card">
            <div class="profile-section">
                <form action="/NexGen/CODE/PHP/update_profile_image.php" method="POST" enctype="multipart/form-data" class="profile-edit-form" id="profileImageForm">
                    <div class="profile-image-wrapper">
                        <img src="/NexGen/CODE/PHP/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="sidebar-profile-img">
                        <label for="new_profile_image" class="profile-edit-btn">✎</label>
                        <input type="file" name="new_profile_image" id="new_profile_image" accept="image/*" hidden>
                    </div>
                </form>

                <div class="sidebar-profile-info">
                    <h2><?php echo htmlspecialchars($user['username'] ?? 'No Username'); ?></h2>
                    <p class="profile-fullname"><?php echo htmlspecialchars($user['full_name'] ?? 'No Name'); ?></p>
                </div>
            </div>
        </div>

        <nav class="settings-nav">
            <button class="nav-item active" data-target="account-panel" type="button">Account</button>
            <button class="nav-item" data-target="security-panel" type="button">Security & Access</button>
        </nav>

        <div class="back-link-wrap">
            <a href="/NexGen/CODE/PHP/privacy_policy.php" class="back-link" style="margin-bottom:10px; display:inline-block;">
                Privacy Policy
            </a>
            <br>
            <a href="/NexGen/CODE/PHP/privacy_policy.php#cookie-notice" class="back-link" style="margin-bottom:10px; display:inline-block;">
                Cookie Notice
            </a>
            <br>
            <a href="<?php echo $backLink; ?>" class="back-link">← Back to Dashboard</a>
        </div>
    </aside>

    <main class="settings-content">
        <section class="settings-panel active-panel" id="account-panel">
            <div class="panel-header">
                <h1>Account Settings</h1>
            </div>

            <form action="/NexGen/CODE/PHP/update_account.php" method="POST" class="account-form" id="accountForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($accountCsrfToken); ?>">

                <div class="form-top-actions">
                    <button type="reset" class="btn btn-reset">Reset</button>
                    <button type="submit" class="btn btn-save">Save</button>
                </div>

                <div class="form-group full">
                    <label>Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Change name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Change email">
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Set contact number">
                    </div>
                </div>

                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" placeholder="Set address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full">
                    <label>OTP Verification Method</label>
                    <input type="text" value="Email only" readonly>
                </div>
            </form>
        </section>

        <section class="settings-panel" id="security-panel">
            <div class="panel-header">
                <h2>Security & Access</h2>
            </div>

            <div class="password-box">

                <div class="security-option-box">
                    <label for="securityChoice">Choose how you want to proceed</label>
                    <select id="securityChoice">
                        <option value="">-- Select an option --</option>
                        <option value="current-password-method">Use Current Password to Change Password</option>
                        <option value="otp-method">Send OTP to Email</option>
                    </select>
                </div>

                <div class="security-method" id="current-password-method">
                    <form action="/NexGen/CODE/PHP/change_password_direct.php" method="POST" class="password-form" id="directPasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo e($directPasswordCsrfToken); ?>">

                        <div class="form-group full">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" value="<?php echo htmlspecialchars($otpNewPasswordValue); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_new_password" value="<?php echo htmlspecialchars($otpConfirmPasswordValue); ?>" required>
                            </div>
                        </div>

                        <div class="form-actions-left">
                            <button type="submit" class="btn btn-save">Change Password</button>
                        </div>
                    </form>
                </div>

                <div class="security-method" id="otp-method">
                    <form action="/NexGen/CODE/PHP/request_password_change.php" method="POST" class="password-form" id="otpRequestForm">
                        <input type="hidden" name="csrf_token" value="<?php echo e($requestOtpCsrfToken); ?>">

                        <div class="form-group full">
                            <label>Email Verification</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="otpNewPassword" required>
                            </div>

                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_new_password" id="otpConfirmPassword" required>
                            </div>
                        </div>

                        <div class="form-actions-left">
                            <button type="submit" class="btn btn-send-otp">Send OTP to Email</button>
                        </div>
                    </form>

                    <form action="/NexGen/CODE/PHP/verify_password_otp.php" method="POST" class="otp-form" id="otpVerifyForm">
                        <input type="hidden" name="csrf_token" value="<?php echo e($verifyOtpCsrfToken); ?>">

                        <div class="form-group full">
                            <label>Enter OTP sent to your email</label>
                            <input type="text" name="otp_code" maxlength="6" placeholder="Enter the 6-digit OTP" required>
                        </div>

                        <div class="form-actions-left">
                            <button type="submit" class="btn btn-save">Verify OTP & Change Password</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </main>
</div>

<script src="/NexGen/CODE/JS/settings.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const navItems = document.querySelectorAll(".settings-nav .nav-item");
    const panels = document.querySelectorAll(".settings-panel");
    const securityChoice = document.getElementById("securityChoice");
    const securityMethods = document.querySelectorAll(".security-method");

    const accountForm = document.getElementById("accountForm");
    const directPasswordForm = document.getElementById("directPasswordForm");
    const otpRequestForm = document.getElementById("otpRequestForm");
    const otpVerifyForm = document.getElementById("otpVerifyForm");

    const profileInput = document.getElementById("new_profile_image");
    const profileForm = document.getElementById("profileImageForm");

    function openPanel(panelId) {
        navItems.forEach(item => {
            item.classList.toggle("active", item.dataset.target === panelId);
        });

        panels.forEach(panel => {
            panel.classList.toggle("active-panel", panel.id === panelId);
        });

        localStorage.setItem("settingsActivePanel", panelId);
    }

    function openSecurityMethod(methodId) {
        securityMethods.forEach(method => {
            method.classList.remove("active-method");
        });

        if (methodId) {
            const selectedMethod = document.getElementById(methodId);
            if (selectedMethod) {
                selectedMethod.classList.add("active-method");
                securityChoice.value = methodId;
                localStorage.setItem("settingsSecurityMethod", methodId);
            }
        }
    }

    navItems.forEach(item => {
        item.addEventListener("click", function () {
            openPanel(this.dataset.target);
        });
    });

    if (securityChoice) {
        securityChoice.addEventListener("change", function () {
            if (this.value) {
                openSecurityMethod(this.value);
                openPanel("security-panel");
            } else {
                securityMethods.forEach(method => method.classList.remove("active-method"));
                localStorage.removeItem("settingsSecurityMethod");
            }
        });
    }

    if (accountForm) {
        accountForm.addEventListener("submit", function () {
            localStorage.setItem("settingsActivePanel", "account-panel");
        });
    }

    if (directPasswordForm) {
        directPasswordForm.addEventListener("submit", function () {
            localStorage.setItem("settingsActivePanel", "security-panel");
            localStorage.setItem("settingsSecurityMethod", "current-password-method");
        });
    }

    if (otpRequestForm) {
        otpRequestForm.addEventListener("submit", function () {
            localStorage.setItem("settingsActivePanel", "security-panel");
            localStorage.setItem("settingsSecurityMethod", "otp-method");
        });
    }

    if (otpVerifyForm) {
        otpVerifyForm.addEventListener("submit", function () {
            localStorage.setItem("settingsActivePanel", "security-panel");
            localStorage.setItem("settingsSecurityMethod", "otp-method");
        });
    }

    const savedPanel = localStorage.getItem("settingsActivePanel") || "account-panel";
    const savedMethod = localStorage.getItem("settingsSecurityMethod") || "";

    openPanel(savedPanel);

    if (savedPanel === "security-panel" && savedMethod) {
        openSecurityMethod(savedMethod);
    }

    if (profileInput && profileForm) {
        profileInput.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                profileForm.submit();
            }
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const otpRequestForm = document.getElementById("otpRequestForm");
    const otpVerifyForm = document.getElementById("otpVerifyForm");
    const otpNewPassword = document.getElementById("otpNewPassword");
    const otpConfirmPassword = document.getElementById("otpConfirmPassword");

    const popupBox = document.getElementById("popupBox");
    const popupText = popupBox ? popupBox.textContent.toLowerCase() : "";

    if (otpNewPassword) {
        const savedNewPassword = sessionStorage.getItem("otp_new_password") || "";
        otpNewPassword.value = savedNewPassword;

        otpNewPassword.addEventListener("input", function () {
            sessionStorage.setItem("otp_new_password", otpNewPassword.value);
        });
    }

    if (otpConfirmPassword) {
        const savedConfirmPassword = sessionStorage.getItem("otp_confirm_password") || "";
        otpConfirmPassword.value = savedConfirmPassword;

        otpConfirmPassword.addEventListener("input", function () {
            sessionStorage.setItem("otp_confirm_password", otpConfirmPassword.value);
        });
    }

    if (otpRequestForm) {
        otpRequestForm.addEventListener("submit", function () {
            if (otpNewPassword) {
                sessionStorage.setItem("otp_new_password", otpNewPassword.value);
            }
            if (otpConfirmPassword) {
                sessionStorage.setItem("otp_confirm_password", otpConfirmPassword.value);
            }
            localStorage.setItem("settingsActivePanel", "security-panel");
            localStorage.setItem("settingsSecurityMethod", "otp-method");
        });
    }

    if (otpVerifyForm) {
        otpVerifyForm.addEventListener("submit", function () {
            localStorage.setItem("settingsActivePanel", "security-panel");
            localStorage.setItem("settingsSecurityMethod", "otp-method");
        });
    }

    if (popupText.includes("password changed successfully")) {
        sessionStorage.removeItem("otp_new_password");
        sessionStorage.removeItem("otp_confirm_password");
    }
});
</script>
</body>
</html>