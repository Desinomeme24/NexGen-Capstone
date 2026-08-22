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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/theme_init.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/forgot_password.css?v=3">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php if (!empty($popupMessage)): ?>
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
            <div class="popup-icon"><i class="bi <?php echo $popupType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i></div>
            <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
            <p><?php echo htmlspecialchars($popupMessage); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="forgot-page">
    <div class="forgot-card">
        <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo" class="forgot-logo">

        <h1>Forgot Password</h1>
        <p class="subtext">Enter your username or email to receive a 6-digit OTP.</p>

        <form action="/NexGen/CODE/PHP/send_forgot_otp.php" method="POST">
            <label>Username or Email Address</label>
            <input type="text" name="identifier" required placeholder="Enter your username or email">

            <button type="submit" class="main-btn">Send OTP</button>
        </form>

        <div class="links">
            <a href="/NexGen/CODE/PHP/reset_password.php">Already have OTP?</a>
            <a href="/NexGen/CODE/PHP/index.php">Back to Login</a>
        </div>
    </div>
</div>

<script src="/NexGen/CODE/JS/forgot_password.js"></script>
</body>
</html>