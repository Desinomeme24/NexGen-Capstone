<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    session_unset();
    session_destroy();
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SESSION['role'] === 'system_admin') {
    header("Location: /NexGen/CODE/PHP/admin_dashboard.php");
    exit();
}

if (!in_array($_SESSION['role'], ['owner', 'employee'], true)) {
    session_unset();
    session_destroy();
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$role = $_SESSION['role'];
$isOwner = $role === 'owner';
$isEmployee = $role === 'employee';
$canInventory = (int)($_SESSION['can_inventory'] ?? 0) === 1;
$canSales = (int)($_SESSION['can_sales'] ?? 0) === 1;
$canSalesAnalytics = (int)($_SESSION['can_sales_analytics'] ?? 0) === 1;
$canAccountsReceivable = (int)($_SESSION['can_accounts_receivable'] ?? 0) === 1;
$hasAnyBusinessModule = $canInventory || $canSales || $canSalesAnalytics || $canAccountsReceivable;

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

$pageTitle = $isOwner ? 'Owner Dashboard' : 'Employee Dashboard';
$pageSubtitle = $isOwner
    ? 'Manage inventory levels and view sales analytics.'
    : 'Manage inventory and record daily sales.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/dashboard.css">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php if (!empty($popupMessage)): ?>
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
            <div class="popup-icon">
                <?php echo $popupType === 'success' ? '✓' : '!'; ?>
            </div>
            <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
            <p><?php echo htmlspecialchars($popupMessage); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="dashboard-page">

    <?php include 'header.php'; ?>

    <section class="top-video-area" id="topVideoArea">
        <div class="video-background">
            <video class="bg-video active" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo1.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo2.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo3.mp4" type="video/mp4">
            </video>
            <video class="bg-video" autoplay muted loop playsinline>
                <source src="/NexGen/VIDEOS/storevideo2.mp4" type="video/mp4">
            </video>
            <div class="video-overlay"></div>
        </div>

        <div class="hero-scroll-indicator">
            <span>Scroll Down</span>
            <i class="bi bi-chevron-double-down"></i>
        </div>

        <section class="hero-section modern-hero-section" id="home-section">
            <div class="modern-hero-shell" id="heroShell">
                <div class="modern-hero-badge">
                    <i class="bi bi-stars"></i>
                    <span><?php echo $isOwner ? 'Owner Workspace' : 'Employee Workspace'; ?></span>
                </div>

                <div class="modern-hero-text">
                    <h1><?php echo $isOwner ? 'Manage Your Business Smarter' : 'Work Faster Every Day'; ?></h1>
                    <p><?php echo htmlspecialchars($pageSubtitle); ?></p>

                    <div class="modern-hero-actions">
                        <a href="#module-section" class="hero-btn modern-hero-btn">Open Modules</a>
                        <a href="/NexGen/CODE/PHP/about_us.php" class="hero-btn modern-hero-btn secondary-btn">Learn More</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="module-section modern-module-section" id="module-section">
            <div class="module-section-header module-reveal">
                <span class="module-kicker">Business Tools</span>
                <h2><?php echo $isOwner ? 'Owner Modules' : 'Employee Modules'; ?></h2>
                <p>
                    <?php
                    if (!$hasAnyBusinessModule) {
                        echo 'No business modules are currently assigned to this account.';
                    } elseif ($isOwner) {
                        echo 'Access your assigned business tools in one modern workspace.';
                    } else {
                        echo 'Access your assigned daily business tools in one clean workspace.';
                    }
                    ?>
                </p>
            </div>

            <div class="modern-module-grid">
                <?php if ($canInventory): ?>
                <a href="/NexGen/CODE/PHP/inventory_management.php" class="modern-module-card module-reveal">
                    <div class="module-card-glow"></div>
                    <div class="module-card-icon">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div class="module-card-content">
                        <h3>Inventory Management</h3>
                        <p>Manage products, monitor stock levels, update item details, and track inventory movement.</p>
                        <span class="module-card-link">Open Module <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($canSales): ?>
                    <a href="/NexGen/CODE/PHP/sales_recording.php" class="modern-module-card module-reveal">
                        <div class="module-card-glow"></div>
                        <div class="module-card-icon">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <div class="module-card-content">
                            <h3>Sales</h3>
                            <p>Record transactions, process daily sales, and manage order and payment details quickly.</p>
                            <span class="module-card-link">Open Module <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($canSalesAnalytics): ?>
                    <a href="/NexGen/CODE/PHP/sales_analytics.php" class="modern-module-card module-reveal">
                        <div class="module-card-glow"></div>
                        <div class="module-card-icon">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                        <div class="module-card-content">
                            <h3>Sales Analytics</h3>
                            <p>View trends, monitor business performance, and analyze revenue insights with visual reports.</p>
                            <span class="module-card-link">Open Module <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($canAccountsReceivable): ?>
                <a href="/NexGen/CODE/PHP/accounts_receivable.php" class="modern-module-card module-reveal">
                    <div class="module-card-glow"></div>
                    <div class="module-card-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="module-card-content">
                        <h3>Accounts Receivable</h3>
                        <p>Monitor unpaid balances, overdue accounts, and customer receivable follow-ups.</p>
                        <span class="module-card-link">Open Module <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </section>
    </section>

    <section class="why-section" id="why-section">
        <div class="why-top-shape"></div>
        <div class="why-inner">
            <div class="why-header-line">
                <span class="why-line"></span>
                <h2>WHY US?</h2>
            </div>

            <div class="why-outline-text">WHY US</div>
            <div class="why-highlight-box">WHY NEXTGEN MICRO-ENTERPRISE?</div>

            <div class="why-content">
                <div class="why-left">
                    <img src="/NexGen/IMAGES/whyus.jpg" alt="Why Us Image">
                </div>

                <div class="why-right">
                    <div class="why-icon-text">
                        <div class="why-description">
                            <p><strong>Next Gen</strong> is designed to provide micro-enterprises with a simple, secure, and intelligent way to manage business operations.</p>
                            <p>We focus on usability, reliability, and real-time data access.</p>
                            <p class="why-closing">Choose Next Gen — where business management meets smart technology.</p>
                        </div>
                    </div>

                    <a href="/NexGen/CODE/PHP/about_us.php" class="learn-btn">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-section" id="footer-section">
        <div class="footer-top-line"></div>

        <div class="footer-logo">
            <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
        </div>

        <div class="footer-grid">
            <div class="footer-item">
                <span class="footer-icon">✉️</span>
                <p><strong>Email:</strong> nextgenmicroenterprise@gmail.com</p>
            </div>

            <div class="footer-item">
                <span class="footer-icon">🌐</span>
                <p><strong>Website:</strong> https://nextgenmicroenterprise.com</p>
            </div>
        </div>

        <div class="footer-copy">
            Copyright © 2026 NextGen Micro-Enterprise
        </div>
    </footer>

</div>

<?php include 'chatbot.php'; ?>
<script src="/NexGen/CODE/JS/dashboard.js"></script>
</body>
</html>