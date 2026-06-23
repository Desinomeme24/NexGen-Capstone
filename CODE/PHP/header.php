<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/* SESSION SECURITY: enforce 2-minute timeout on protected user pages using header */
enforceSessionTimeout();

$displayName  = $_SESSION['username'] ?? 'Client';
$fullName     = $_SESSION['full_name'] ?? 'Client';
$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$currentPage  = basename($_SERVER['PHP_SELF']);
$role         = $_SESSION['role'] ?? 'employee';
$isOwner      = $role === 'owner';
$isEmployee   = $role === 'employee';
$canInventory = (int)($_SESSION['can_inventory'] ?? 0) === 1;
$canSales = (int)($_SESSION['can_sales'] ?? 0) === 1;
$canSalesAnalytics = (int)($_SESSION['can_sales_analytics'] ?? 0) === 1;
$canAccountsReceivable = (int)($_SESSION['can_accounts_receivable'] ?? 0) === 1;
$hasAnyBusinessModule = $canInventory || $canSales || $canSalesAnalytics || $canAccountsReceivable;
$roleLabel = $isOwner ? 'Owner' : ($isEmployee ? 'Employee' : ucwords(str_replace('_', ' ', (string)$role)));

$showCategoryOpen = in_array(
    $currentPage,
    ['inventory_management.php', 'sales_recording.php', 'sales_analytics.php', 'accounts_receivable.php'],
    true
);
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <button class="close-sidebar" id="closeSidebar" type="button" onclick="closeSidebarMenu()">&times;</button>
    </div>

    <div class="profile-section">
        <form action="/NexGen/CODE/PHP/update_profile_image.php" method="POST" enctype="multipart/form-data" class="profile-edit-form">
            <div class="profile-image-wrapper">
                <img src="/NexGen/CODE/PHP/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-img">
                <label for="new_profile_image" class="edit-profile-btn">✎</label>
                <input type="file" name="new_profile_image" id="new_profile_image" accept="image/*" hidden>
            </div>
            <button type="submit" class="hidden-upload-btn" id="submitProfileBtn">Upload</button>
        </form>

        <h2><?php echo htmlspecialchars($displayName); ?></h2>
        <p class="profile-fullname"><?php echo htmlspecialchars($fullName); ?></p>
        <p class="profile-fullname"><?php echo htmlspecialchars($roleLabel); ?></p>
    </div>

    <nav class="sidebar-menu">
        <a href="/NexGen/CODE/PHP/dashboard.php" class="menu-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-house-door-fill"></i>
            <span><?php echo $isOwner ? 'Owner Dashboard' : 'Employee Dashboard'; ?></span>
        </a>

        <a href="/NexGen/CODE/PHP/about_us.php" class="menu-item <?php echo $currentPage === 'about_us.php' ? 'active' : ''; ?>">
            <i class="bi bi-info-circle-fill"></i>
            <span>About Us</span>
        </a>

        <?php if ($hasAnyBusinessModule): ?>
        <div class="dropdown-section">
            <button class="dropdown-btn <?php echo $showCategoryOpen ? 'active-btn' : ''; ?>" id="categoryToggle" type="button">
                <span class="d-flex align-items-center gap-3">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Categories</span>
                </span>
                <span id="dropdownArrow">▼</span>
            </button>

            <div class="dropdown-content <?php echo $showCategoryOpen ? 'show' : ''; ?>" id="categoryMenu">
                <?php if ($canInventory): ?>
                <a href="/NexGen/CODE/PHP/inventory_management.php" class="<?php echo $currentPage === 'inventory_management.php' ? 'active-sub active-submenu' : ''; ?>">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Inventory Management</span>
                </a>
                <?php endif; ?>

                <?php if ($canSales): ?>
                    <a href="/NexGen/CODE/PHP/sales_recording.php" class="<?php echo $currentPage === 'sales_recording.php' ? 'active-sub active-submenu' : ''; ?>">
                        <i class="bi bi-receipt-cutoff"></i>
                        <span>Sales</span>
                    </a>
                <?php endif; ?>

                <?php if ($canSalesAnalytics): ?>
                    <a href="/NexGen/CODE/PHP/sales_analytics.php" class="<?php echo $currentPage === 'sales_analytics.php' ? 'active-sub active-submenu' : ''; ?>">
                        <i class="bi bi-bar-chart-line-fill"></i>
                        <span>Sales Analytics</span>
                    </a>
                <?php endif; ?>

                <?php if ($canAccountsReceivable): ?>
                    <a href="/NexGen/CODE/PHP/accounts_receivable.php" class="<?php echo $currentPage === 'accounts_receivable.php' ? 'active-sub active-submenu' : ''; ?>">
                        <i class="bi bi-wallet2"></i>
                        <span>Accounts Receivable</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <a href="/NexGen/CODE/PHP/settings.php" class="menu-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
            <i class="bi bi-gear-wide-connected"></i>
            <span>Settings</span>
        </a>

        <a href="#" class="menu-item" onclick="openLogoutModal(event)">
            <i class="bi bi-box-arrow-right"></i>
            <span>Log Out</span>
        </a>
    </nav>
</aside>

<div class="overlay" id="overlay"></div>

<header class="topbar">
    <div class="menu-btn" id="openSidebar" role="button" tabindex="0" aria-label="Open sidebar" onclick="openSidebarMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="topbar-right">
        <a href="/NexGen/CODE/PHP/settings.php" class="top-user-info">
            <img src="/NexGen/CODE/PHP/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="top-user-img">
            <span class="top-user-name"><?php echo htmlspecialchars($displayName); ?></span>
        </a>

        <div class="top-logo">
            <img src="/NexGen/IMAGES/NGlogo.png" alt="Logo">
        </div>
    </div>
</header>

<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal-card">
        <div class="logout-modal-icon">
            <i class="bi bi-box-arrow-right"></i>
        </div>

        <h3>Log out?</h3>
        <p>You’re about to end your current session.</p>

       <form action="/NexGen/CODE/PHP/logout.php" method="POST" class="logout-form">
    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken('logout_form')); ?>">
    <button type="button" class="logout-cancel-btn" onclick="closeLogoutModal()">Cancel</button>
    <button type="submit" class="logout-confirm-btn">Log Out</button>
</form>
    </div>
</div>

<style>
    .logout-trigger {
        width: 100%;
        border: none;
        background: transparent;
        text-align: left;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 12px;
        color: inherit;
        font: inherit;
    }

    .logout-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: all 0.25s ease;
        z-index: 99999;
    }

    .logout-modal-overlay.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .logout-modal-card {
        width: 100%;
        max-width: 380px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        padding: 28px 24px 22px;
        text-align: center;
        transform: translateY(20px) scale(0.96);
        transition: all 0.25s ease;
    }

    .logout-modal-overlay.show .logout-modal-card {
        transform: translateY(0) scale(1);
    }

    .logout-modal-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto 14px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(37, 99, 235, 0.95));
        color: #fff;
        font-size: 28px;
        box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
    }

    .logout-modal-card h3 {
        margin: 0 0 8px;
        color: #ffffff;
        font-size: 1.45rem;
        font-weight: 700;
    }

    .logout-modal-card p {
        margin: 0 0 22px;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.98rem;
        line-height: 1.5;
    }

    .logout-form {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .logout-cancel-btn,
    .logout-confirm-btn {
        border: none;
        border-radius: 14px;
        padding: 12px 18px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 120px;
    }

    .logout-cancel-btn {
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .logout-cancel-btn:hover {
        background: rgba(255, 255, 255, 0.22);
        transform: translateY(-1px);
    }

    .logout-confirm-btn {
        background: linear-gradient(135deg, #1877f2, #0f5fd6);
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(24, 119, 242, 0.32);
    }

    .logout-confirm-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 22px rgba(24, 119, 242, 0.4);
    }

    @media (max-width: 480px) {
        .logout-form {
            flex-direction: column;
        }

        .logout-cancel-btn,
        .logout-confirm-btn {
            width: 100%;
        }
    }
</style>

<script>
    function openLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', function (e) {
        const modal = document.getElementById('logoutModal');
        if (e.target === modal) {
            closeLogoutModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeLogoutModal();
        }
    });

    /* SESSION SECURITY: client-side 5-minute inactivity auto logout */
    (function () {
        const timeoutMs =2 * 60 * 1000;
        let inactivityTimer = null;

        function triggerTimeoutLogout() {
            window.location.href = "/NexGen/CODE/PHP/logout.php?timeout=1";
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(triggerTimeoutLogout, timeoutMs);
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });

        resetInactivityTimer();
    })();
</script>