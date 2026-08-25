<?php
session_start();
require_once 'config.php';

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

$candidates = $_SESSION['fp_candidates'] ?? null;

if (!$candidates) {
    $_SESSION['error'] = 'Please start the password reset process again.';
    header('Location: /NexGen/CODE/PHP/forgot_password.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Account - NextGen</title>
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

        <h1>Select Account</h1>
        <p class="subtext">This email is linked to more than one account. Choose which one to reset.</p>

        <form action="/NexGen/CODE/PHP/send_forgot_otp.php" method="POST">
            <div class="account-list">
                <?php foreach ($candidates as $c): ?>
                    <label class="account-option">
                        <input type="radio" name="selected_user_id" value="<?php echo (int) $c['id']; ?>" required>
                        <span>
                            <?php echo htmlspecialchars($c['masked_username']); ?><?php if ($c['business_name'] !== ''): ?> &mdash; <?php echo htmlspecialchars($c['business_name']); ?><?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="main-btn">Send OTP</button>
        </form>

        <div class="links">
            <a href="/NexGen/CODE/PHP/forgot_password.php">Start Over</a>
            <a href="/NexGen/CODE/PHP/index.php">Back to Login</a>
        </div>
    </div>
</div>

<script src="/NexGen/CODE/JS/forgot_password.js"></script>
</body>
</html>
