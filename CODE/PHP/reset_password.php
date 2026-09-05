<?php
require_once __DIR__ . '/config.php';

$fpPortal = nxNormalizeLoginPortal(
    $_POST['portal']
        ?? $_GET['portal']
        ?? $_SESSION['fp_portal']
        ?? $_SESSION['login_portal']
        ?? 'client'
);
$_SESSION['fp_portal'] = $fpPortal;
$loginPath = nxLoginPathForPortal($fpPortal);
$forgotStartPath = nxAppUrl('forgot_password.php?portal=' . rawurlencode($fpPortal));

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

if (empty($_SESSION['fp_selected_user_id'])) {
    $_SESSION['error'] = 'Please start the password reset process again.';
    header('Location: ' . $forgotStartPath);
    exit();
}

$resetEmail = $_SESSION['fp_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NextGen</title>
    <link rel="stylesheet" href="<?php echo e(nxCodeUrl('STYLE/forgot_password.css')); ?>">
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

<div class="forgot-page">
    <div class="forgot-card">
        <img src="<?php echo e(nxProjectUrl('IMAGES/NGlogo.png')); ?>" alt="Logo" class="forgot-logo">

        <h1>Reset Password</h1>
        <p class="subtext">Enter the OTP sent to your email and set a new password.</p>

        <form action="<?php echo e(nxAppUrl('process_reset_password.php')); ?>" method="POST">
            <input type="hidden" name="portal" value="<?php echo e($fpPortal); ?>">
            <label>Email Address</label>
            <input type="email" value="<?php echo htmlspecialchars($resetEmail); ?>" readonly>

            <label>OTP Code</label>
            <input type="text" name="otp_code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required placeholder="Enter 6-digit OTP">

            <label>New Password</label>
            <input type="password" name="new_password" minlength="12" maxlength="64" required placeholder="Enter new password" autocomplete="new-password">

            <label>Confirm New Password</label>
            <input type="password" name="confirm_new_password" minlength="12" maxlength="64" required placeholder="Confirm new password" autocomplete="new-password">

            <button type="submit" class="main-btn">Reset Password</button>
        </form>

        <div class="links">
            <form action="<?php echo e(nxAppUrl('send_forgot_otp.php')); ?>" method="POST" class="inline-form">
                <input type="hidden" name="portal" value="<?php echo e($fpPortal); ?>">
                <button type="submit" class="link-btn">Resend OTP</button>
            </form>
            <a href="<?php echo e($loginPath); ?>">Back to Login</a>
        </div>
    </div>
</div>

<script src="<?php echo e(nxCodeUrl('JS/forgot_password.js')); ?>"></script>
</body>
</html>
