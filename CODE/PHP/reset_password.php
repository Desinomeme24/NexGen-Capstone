<?php
session_start();

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

$resetEmail = $_SESSION['reset_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/forgot_password.css">
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
        <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo" class="forgot-logo">

        <h1>Reset Password</h1>
        <p class="subtext">Enter the OTP sent to your email and set a new password.</p>

        <form action="/NexGen/CODE/PHP/process_reset_password.php" method="POST">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($resetEmail); ?>" placeholder="Enter your email">

            <label>OTP Code</label>
            <input type="text" name="otp_code" maxlength="6" required placeholder="Enter 6-digit OTP">

            <label>New Password</label>
            <input type="password" name="new_password" required placeholder="Enter new password">

            <label>Confirm New Password</label>
            <input type="password" name="confirm_new_password" required placeholder="Confirm new password">

            <button type="submit" class="main-btn">Reset Password</button>
        </form>

        <div class="links">
            <a href="/NexGen/CODE/PHP/forgot_password.php">Resend OTP</a>
            <a href="/NexGen/CODE/PHP/index.php">Back to Login</a>
        </div>
    </div>
</div>

<script src="/NexGen/CODE/JS/forgot_password.js"></script>
</body>
</html>