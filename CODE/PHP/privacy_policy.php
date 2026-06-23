<?php
session_start();

$backLink = "/NexGen/CODE/PHP/index.php";

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'system_admin') {
        $backLink = "/NexGen/CODE/PHP/admin_dashboard.php";
    } else {
        $backLink = "/NexGen/CODE/PHP/dashboard.php";
    }
} else {
    $returnTo = $_GET['return_to'] ?? '';

    if ($returnTo === 'signup') {
        $backLink = "/NexGen/CODE/PHP/index.php?open=signup";
    } elseif ($returnTo === 'login') {
        $backLink = "/NexGen/CODE/PHP/index.php?open=login";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - NextGen</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #0b2257 0%, #12367f 100%);
            color: #fff;
            min-height: 100vh;
        }

        .privacy-page {
            max-width: 980px;
            margin: 0 auto;
            padding: 36px 20px 48px;
        }

        .privacy-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .privacy-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .privacy-logo img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }

        .privacy-logo-text h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
        }

        .privacy-logo-text p {
            margin: 6px 0 0;
            color: rgba(255,255,255,0.82);
            font-size: 0.98rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.16);
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.22);
            transform: translateY(-1px);
        }

        .policy-card {
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.22);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .policy-card h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.35rem;
            color: #f7d98b;
        }

        .policy-card p,
        .policy-card li {
            color: rgba(255,255,255,0.92);
            line-height: 1.8;
            font-size: 0.98rem;
        }

        .policy-card ul {
            margin-top: 0;
            margin-bottom: 20px;
            padding-left: 22px;
        }

        .policy-card .section {
            margin-bottom: 24px;
        }

        .policy-note {
            margin-top: 24px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(247,217,139,0.12);
            border: 1px solid rgba(247,217,139,0.24);
            color: #fff3cf;
            line-height: 1.7;
        }

        @media (max-width: 640px) {
            .privacy-logo-text h1 {
                font-size: 1.6rem;
            }

            .policy-card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body>
    <div class="privacy-page">
        <div class="privacy-top">
            <div class="privacy-logo">
                <img src="/NexGen/IMAGES/NGlogo.png" alt="NextGen Logo">
                <div class="privacy-logo-text">
                    <h1>Privacy Policy</h1>
                    <p>NextGen Micro-Enterprise System</p>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars($backLink); ?>" class="back-btn">← Back</a>
        </div>

        <div class="policy-card">
            <div class="section">
                <h2>1. Introduction</h2>
                <p>
                    NextGen values the privacy and security of user information. This Privacy Policy explains
                    how personal data and system-generated information are collected, used, stored, and protected
                    within the system.
                </p>
            </div>

            <div class="section">
                <h2>2. Information We Collect</h2>
                <ul>
                    <li>Account registration details such as employee number, username, full name, email, phone number, and address.</li>
                    <li>Authentication-related information such as encrypted passwords, login attempts, lockout details, and session activity.</li>
                    <li>Uploaded files such as valid IDs and profile images submitted by users.</li>
                    <li>System-generated logs including account approvals, security actions, and administrative activities.</li>
                </ul>
            </div>

            <div class="section">
                <h2>3. Purpose of Data Collection</h2>
                <ul>
                    <li>To review and process account registration requests.</li>
                    <li>To authenticate users and control access based on assigned roles and permissions.</li>
                    <li>To protect the system through logging, account lockout, CAPTCHA validation, and other security mechanisms.</li>
                    <li>To maintain accurate records for auditing, monitoring, and account management.</li>
                </ul>
            </div>

            <div class="section">
                <h2>4. Data Protection</h2>
                <p>
                    NextGen applies security measures such as password hashing, role-based access control,
                    session timeout, account lockout, CAPTCHA validation, and activity logging to help protect
                    user data from unauthorized access, misuse, or alteration.
                </p>
            </div>

            <div class="section">
                <h2>5. Cookie Use</h2>
                <p>
                    NextGen uses essential browser storage or session-related cookies for login session continuity,
                    security controls, inactivity timeout handling, and access management. These are used only
                    for proper system operation and are not intended for advertising or public tracking.
                </p>
            </div>

            <div class="section">
                <h2>6. Data Sharing</h2>
                <p>
                    Information collected in the system is intended only for legitimate system operations,
                    administrative review, and business management processes. User information is not intended
                    for public disclosure or unauthorized third-party sharing.
                </p>
            </div>

            <div class="section">
                <h2>7. User Responsibility</h2>
                <p>
                    Users are expected to provide accurate information during registration and to keep their
                    account credentials secure. Any suspicious activity or unauthorized access attempt should
                    be reported immediately to the system administrator.
                </p>
            </div>

            <div class="section">
                <h2>8. Policy Updates</h2>
                <p>
                    NextGen may update this Privacy Policy when necessary to improve system security,
                    compliance, and operational transparency. Users are encouraged to review this page
                    periodically.
                </p>
            </div>

            <div class="policy-note">
                By using or registering in the NextGen system, users acknowledge that their information may be
                processed for authentication, approval, access control, security monitoring, legitimate system
                operations, and essential cookie-based session handling.
            </div>
        </div>
    </div>
</body>
</html>