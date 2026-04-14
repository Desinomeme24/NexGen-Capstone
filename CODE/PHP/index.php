<?php
session_start();

$popupMessage = "";
$popupType = "";
$openLoginAfterLoad = false;
$openSignupAfterLoad = false;

if (isset($_SESSION['success'])) {
    $popupMessage = $_SESSION['success'];
    $popupType = "success";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $popupMessage = $_SESSION['error'];
    $popupType = "error";

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
    <!-- LOGIN MODAL -->
    <div class="modal" id="loginModal">
        <div class="modal-box">
            <button class="close-modal" id="closeLogin" type="button">&times;</button>

            <div class="modal-mini-logo">
                <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
            </div>

            <h2>LOG IN YOUR NEXT GEN ACCOUNT</h2>

            <form action="/NexGen/CODE/PHP/login_process.php" method="POST">
                <label>User Name</label>
                <input type="text" name="username" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button type="submit" class="silver-btn">Log in</button>

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

    <!-- SIGNUP MODAL -->
    <div class="modal" id="signupModal">
        <div class="modal-box signup-box">
            <button class="close-modal" id="closeSignup" type="button">&times;</button>

            <div class="modal-mini-logo">
                <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
            </div>

            <h2>SUBMIT ACCOUNT REQUEST</h2>

            <form action="/NexGen/CODE/PHP/signup_process.php" method="POST" enctype="multipart/form-data">
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
                <input type="password" name="signup_password" required>

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>

                <label>Valid ID</label>
                <input type="file" name="valid_id" accept="image/*,.pdf" required>

                <button type="submit" class="silver-btn">Submit for Approval</button>

                <p class="modal-text">
                    Have an account?
                    <a href="#" id="goToLogin">Sign in</a>
                </p>
            </form>
        </div>
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
});
</script>
<script src="/NexGen/CODE/JS/script.js"></script>
</body>
</html>