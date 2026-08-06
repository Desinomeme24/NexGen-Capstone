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
    <?php include __DIR__ . '/theme_init.php'; ?>
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
        <div class="hero-bg-circuit" aria-hidden="true">
            <div class="circuit-orb orb-1"></div>
            <div class="circuit-orb orb-2"></div>
            <div class="circuit-orb orb-3"></div>
            <div class="circuit-orb orb-4"></div>
            <div class="circuit-flow"></div>
            <div class="circuit-grid"></div>
            <div class="circuit-pulses-layer" id="circuitPulses"></div>
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

    <div class="why-section-toggle-wrap">
        <button type="button" class="why-section-toggle-btn" id="whySectionToggleBtn" aria-expanded="false" aria-controls="why-section">
            <span>Why NextGen?</span>
            <i class="bi bi-chevron-down" id="whySectionToggleIcon"></i>
        </button>
    </div>

    <section class="why-section" id="why-section">
        <div class="why-top-shape"></div>
        <div class="why-inner">
            <div class="why-header-line why-reveal why-reveal-up">
                <span class="why-line"></span>
                <h2>WHY US?</h2>
            </div>

            <div class="why-outline-text why-reveal why-reveal-up">WHY US</div>
            <div class="why-highlight-box why-reveal why-reveal-up">WHY NEXGEN FOR SMEs?</div>

            <div class="why-content">
                <div class="why-left why-reveal why-reveal-left">
                    <img src="/NexGen/IMAGES/whyus.jpg" alt="Why Us Image">
                </div>

                <div class="why-right why-reveal why-reveal-right">
                    <div class="why-icon-text">
                        <div class="why-description">
                            <p><strong>Next Gen</strong> is designed to provide Small and Medium Enterprises (SMEs) with a secure, efficient, and intelligent platform for managing inventory, sales transactions, and business operations.</p>
                            <p>We prioritize accuracy, usability, reliability, and real-time access to business information to help enterprises operate more effectively.</p>
                            <p class="why-closing">Choose NexGen — where smarter business management meets intelligent technology.</p>
                        </div>

                        <button type="button" class="why-toggle-btn" id="whyToggleBtn" aria-expanded="false" aria-controls="whyExtra">
                            <span>More About Us</span>
                            <i class="bi bi-chevron-down" id="whyToggleIcon"></i>
                        </button>

                        <div class="why-extra" id="whyExtra">
                            <p>Founded to support growing businesses, NexGen brings together inventory management, sales recording, sales analytics, accounts receivable monitoring, and an AI-powered chatbot assistant into one centralized web-based platform.</p>
                            <p>Our system reduces manual work, improves data accuracy, streamlines daily operations, and provides real-time business insights, allowing business owners to focus on making informed decisions and achieving sustainable growth.</p>
                        </div>
                    </div>

                    <a href="/NexGen/CODE/PHP/about_us.php" class="learn-btn">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-section" id="footer-section">
        <div class="footer-wave-wrap" aria-hidden="true">
            <svg class="wave-back" viewBox="0 0 2400 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,40 C150,70 350,10 600,40 C850,70 1050,10 1200,40 C1350,70 1550,10 1800,40 C2050,70 2250,10 2400,40 L2400,100 L0,100 Z"></path>
            </svg>
            <svg class="wave-mid" viewBox="0 0 2400 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,30 C150,50 350,10 600,30 C850,50 1050,10 1200,30 C1350,50 1550,10 1800,30 C2050,50 2250,10 2400,30 L2400,100 L0,100 Z"></path>
            </svg>
            <svg class="wave-front" viewBox="0 0 2400 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,25 C150,38 350,12 600,25 C850,38 1050,12 1200,25 C1350,38 1550,12 1800,25 C2050,38 2250,12 2400,25 L2400,100 L0,100 Z"></path>
            </svg>
            <svg class="wave-crest" viewBox="0 0 2400 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,40 C150,70 350,10 600,40 C850,70 1050,10 1200,40 C1350,70 1550,10 1800,40 C2050,70 2250,10 2400,40"></path>
            </svg>
        </div>
        <div class="footer-top-line"></div>

        <div class="footer-logo">
            <img src="/NexGen/IMAGES/NGlogo.png" alt="NexGen Logo">
        </div>

        <div class="footer-social">
            <a href="https://www.facebook.com/profile.php?id=61587577520854" target="_blank" rel="noopener" aria-label="NexGen on Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" target="_blank" rel="noopener" aria-label="NexGen on Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" target="_blank" rel="noopener" aria-label="NexGen on X"><i class="bi bi-twitter-x"></i></a>
            <a href="#" target="_blank" rel="noopener" aria-label="NexGen on TikTok"><i class="bi bi-tiktok"></i></a>
        </div>

        <div class="footer-copy">
            &copy; 2026 NexGen. All rights reserved.
        </div>
    </footer>

</div>

<?php include 'chatbot.php'; ?>
<script src="/NexGen/CODE/JS/dashboard.js"></script>
<script>
/* Circuit pulse spawner — same approach as login_process.php's
   #circuitPulses, adapted to the taller scrollable hero area
   (uses the .hero-bg-circuit container's own size instead of the
   viewport, since this section runs ~200vh tall). */
(function () {
    var container = document.querySelector('.hero-bg-circuit');
    var layer = document.getElementById('circuitPulses');
    if (!container || !layer) return;

    var GRID = 48;

    function spawnPulse() {
        var width = container.offsetWidth || window.innerWidth;
        var height = container.offsetHeight || window.innerHeight;
        var cols = Math.ceil(width / GRID);
        var rows = Math.ceil(height / GRID);

        var dot = document.createElement('div');
        dot.className = 'circuit-pulse';

        var horizontal = Math.random() < 0.5;
        var steps = 3 + Math.floor(Math.random() * 5);
        var x, y, dx, dy;

        if (horizontal) {
            y = Math.floor(Math.random() * rows) * GRID;
            x = Math.floor(Math.random() * Math.max(1, cols - steps)) * GRID;
            dx = steps * GRID; dy = 0;
        } else {
            x = Math.floor(Math.random() * cols) * GRID;
            y = Math.floor(Math.random() * Math.max(1, rows - steps)) * GRID;
            dx = 0; dy = steps * GRID;
        }

        dot.style.left = x + 'px';
        dot.style.top = y + 'px';

        var duration = steps * 0.6;
        dot.style.animationDuration = duration + 's';
        layer.appendChild(dot);

        if (dot.animate) {
            dot.animate(
                [{ transform: 'translate(0,0)' }, { transform: 'translate(' + dx + 'px,' + dy + 'px)' }],
                { duration: duration * 1000, easing: 'linear' }
            );
        }

        setTimeout(function () { dot.remove(); }, duration * 1000);
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    for (var i = 0; i < 14; i++) {
        setTimeout(spawnPulse, i * 150);
    }
    setInterval(spawnPulse, 350);
})();
</script>
</body>
</html>