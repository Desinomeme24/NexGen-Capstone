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
    <script>
        /* THEME: apply saved preference before first paint to avoid a flash */
        (function () {
            try {
                var saved = localStorage.getItem("nexgen-theme");
                if (saved === "light" || saved === "dark") {
                    document.documentElement.setAttribute("data-theme", saved);
                }
            } catch (e) {}
        })();
    </script>
    <title>Settings - NextGen</title>
    <!-- header.css owns the shared --bg-page/--text-heading/etc tokens and the
         data-theme="light" overrides. Loading it here (without header.php's
         markup) lets settings.css react to the same theme switch as every
         other page, instead of keeping its own separate copy of the palette. -->
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/settings.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                        <label for="new_profile_image" class="profile-edit-btn"><i class="bi bi-camera-fill"></i></label>
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
            <button class="nav-item active" data-target="account-panel" type="button"><i class="bi bi-person"></i><span>Account</span></button>
            <button class="nav-item" data-target="personalization-panel" type="button"><i class="bi bi-palette"></i><span>Personalization</span></button>
            <button class="nav-item" data-target="security-panel" type="button"><i class="bi bi-shield-lock"></i><span>Security &amp; Access</span></button>
        </nav>

        <div class="settings-sidebar-footer">
            <div class="legal-links">
                <a href="/NexGen/CODE/PHP/privacy_policy.php" class="back-link">
                    <i class="bi bi-file-earmark-text"></i> Privacy Policy
                </a>
                <a href="/NexGen/CODE/PHP/privacy_policy.php#cookie-notice" class="back-link">
                    <i class="bi bi-cookie"></i> Cookie Notice
                </a>
            </div>
            <div class="back-link-wrap">
                <a href="<?php echo $backLink; ?>" class="back-link back-to-dashboard"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </aside>

    <main class="settings-content">
        <section class="settings-panel active-panel" id="account-panel">
            <div class="panel-header">
                <h1>Account Settings</h1>
                <p class="panel-subtitle">Manage your personal information and how it's displayed.</p>
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
                    <div class="info-chip"><i class="bi bi-envelope-check"></i> Email only</div>
                </div>
            </form>
        </section>

        <section class="settings-panel" id="personalization-panel">
            <div class="panel-header">
                <h2>Personalization</h2>
                <p class="panel-subtitle">Choose how NexGen looks. </p>
            </div>

            <div class="theme-picker" id="themePicker">
                <button type="button" class="theme-option" data-theme-choice="dark">
                    <span class="theme-swatch theme-swatch-dark">
                        <span class="theme-swatch-card"></span>
                        <span class="theme-swatch-card short"></span>
                    </span>
                    <span class="theme-option-label"><i class="bi bi-moon-stars-fill"></i> Dark</span>
                </button>

                <button type="button" class="theme-option" data-theme-choice="light">
                    <span class="theme-swatch theme-swatch-light">
                        <span class="theme-swatch-card"></span>
                        <span class="theme-swatch-card short"></span>
                    </span>
                    <span class="theme-option-label"><i class="bi bi-sun-fill"></i> Light</span>
                </button>
            </div>
        </section>

        <section class="settings-panel" id="security-panel">
            <div class="panel-header">
                <h2>Security &amp; Access</h2>
                <p class="panel-subtitle">Choose how you'd like to change your password.</p>
            </div>

            <div class="password-box">

                <div class="segmented-control" id="securityChoiceGroup">
                    <button type="button" class="segmented-btn" data-method="current-password-method">
                        <i class="bi bi-key"></i> Use Current Password
                    </button>
                    <button type="button" class="segmented-btn" data-method="otp-method">
                        <i class="bi bi-envelope-check"></i> Send OTP to Email
                    </button>
                </div>

                <div class="security-method" id="current-password-method">
                    <div class="method-card">
                        <div class="method-card-header">
                            <div class="method-icon"><i class="bi bi-key-fill"></i></div>
                            <div>
                                <h3>Change with Current Password</h3>
                                <p>Verify it's you using your existing password.</p>
                            </div>
                        </div>

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
                </div>

                <div class="security-method" id="otp-method">
                    <div class="method-card">
                        <div class="method-card-header">
                            <div class="method-icon"><i class="bi bi-envelope-check-fill"></i></div>
                            <div>
                                <h3>Change via Email OTP</h3>
                                <p>We'll send a one-time code to verify it's you.</p>
                            </div>
                        </div>

                        <div class="step-flow">
                            <div>
                                <div class="step-label"><span class="step-badge">1</span> Request a code</div>

                                <form action="/NexGen/CODE/PHP/request_password_change.php" method="POST" class="password-form" id="otpRequestForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($requestOtpCsrfToken); ?>">

                                    <div class="form-group full">
                                        <label>Email Verification</label>
                                        <div class="info-chip"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
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
                            </div>

                            <div>
                                <div class="step-label"><span class="step-badge">2</span> Verify &amp; confirm</div>

                                <form action="/NexGen/CODE/PHP/verify_password_otp.php" method="POST" class="otp-form" id="otpVerifyForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($verifyOtpCsrfToken); ?>">

                                    <div class="form-group full">
                                        <label>Enter OTP sent to your email</label>
                                        <input type="text" name="otp_code" maxlength="6" placeholder="Enter the 6-digit OTP" required>
                                    </div>

                                    <div class="form-actions-left">
                                        <button type="submit" class="btn btn-save">Verify OTP &amp; Change Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
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
    const segmentedBtns = document.querySelectorAll(".segmented-btn");
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

        segmentedBtns.forEach(btn => {
            btn.classList.toggle("active", btn.dataset.method === methodId);
        });

        if (methodId) {
            const selectedMethod = document.getElementById(methodId);
            if (selectedMethod) {
                selectedMethod.classList.add("active-method");
                localStorage.setItem("settingsSecurityMethod", methodId);
            }
        }
    }

    navItems.forEach(item => {
        item.addEventListener("click", function () {
            openPanel(this.dataset.target);
        });
    });

    segmentedBtns.forEach(btn => {
        btn.addEventListener("click", function () {
            openSecurityMethod(this.dataset.method);
            openPanel("security-panel");
        });
    });

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
    /* THEME: personalization picker — sets data-theme, persists it under the
       same key admin_dashboard.php reads on load, and keeps the .selected
       highlight in sync with whichever theme is actually active. */
    (function () {
        const themeOptions = document.querySelectorAll(".theme-option");

        function currentTheme() {
            return document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark";
        }

        function syncSelected() {
            const active = currentTheme();
            themeOptions.forEach(function (option) {
                option.classList.toggle("selected", option.dataset.themeChoice === active);
            });
        }

        function applyTheme(choice) {
            if (choice === "light") {
                document.documentElement.setAttribute("data-theme", "light");
            } else {
                document.documentElement.removeAttribute("data-theme");
            }
            try { localStorage.setItem("nexgen-theme", choice); } catch (e) {}
            syncSelected();
        }

        themeOptions.forEach(function (option) {
            option.addEventListener("click", function () {
                applyTheme(option.dataset.themeChoice);
            });
        });

        syncSelected();
    })();
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