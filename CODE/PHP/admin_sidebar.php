<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$profileImage  = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$adminFullName = $_SESSION['full_name'] ?? 'System Administrator';
$currentPage   = basename($_SERVER['PHP_SELF']);
?>

<button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Open menu" aria-expanded="false" aria-controls="adminSidebar">
    <i class="bi bi-list"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar admin-sidebar-custom" id="adminSidebar">
    <div class="brand-box">
        <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
        <div class="brand-meta">
            <h2><?php echo e($adminFullName); ?></h2>
            <p>System Administrator</p>
        </div>
    </div>

    <nav class="admin-menu admin-menu-custom">
        <div class="admin-menu-main">
            <a href="admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="bi bi-house"></i><span>Dashboard</span></a>
            <a href="pending_requests.php" class="<?php echo $currentPage === 'pending_requests.php' ? 'active' : ''; ?>"><i class="bi bi-clock-history"></i><span>Pending Requests</span></a>
            <a href="manage_users.php" class="<?php echo $currentPage === 'manage_users.php' ? 'active' : ''; ?>"><i class="bi bi-people"></i><span>Manage Users</span></a>
            <a href="accounts_masterlist.php" class="<?php echo $currentPage === 'accounts_masterlist.php' ? 'active' : ''; ?>"><i class="bi bi-list-ul"></i><span>Accounts Masterlist</span></a>
            <a href="admin_logs.php" class="<?php echo $currentPage === 'admin_logs.php' ? 'active' : ''; ?>"><i class="bi bi-file-earmark-text"></i><span>Admin Logs</span></a>
            <a href="/NexGen/CODE/PHP/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>"><i class="bi bi-gear"></i><span>Settings</span></a>
        </div>

        <div class="admin-menu-bottom">
            <a href="#" class="logout-link" onclick="openLogoutModal(event)"><i class="bi bi-box-arrow-right"></i><span>Log Out</span></a>
        </div>
    </nav>
</aside>

<div class="logout-confirm-overlay" id="logoutConfirmOverlay">
    <div class="logout-confirm-box">
        <div class="logout-confirm-icon">⇦</div>
        <h3>Log out?</h3>
        <p>Are you sure you want to log out of your account?</p>

        <div class="logout-confirm-actions">
            <button type="button" class="logout-btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <form action="/NexGen/CODE/PHP/logout.php" method="POST" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken('logout_form')); ?>">
                <button type="submit" class="logout-btn-confirm">Log Out</button>
            </form>
        </div>
    </div>
</div>

<style>
.admin-sidebar-custom {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    height: 100vh;
    box-sizing: border-box;
    padding-bottom: 22px;
}

.admin-menu-custom {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    margin-top: 18px;
}

.admin-menu-main {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    flex: 1;
    min-height: 0;
    gap: 8px;
}

.admin-menu-main a,
.admin-menu-bottom a {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    box-sizing: border-box;
}

.admin-menu-main a i,
.admin-menu-bottom a i {
    font-size: 16px;
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}

.admin-menu-bottom {
    margin-top: 18px;
}

.logout-confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: 0.25s ease;
    z-index: 99999;
    padding: 20px;
}

.logout-confirm-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.logout-confirm-box {
    width: 100%;
    max-width: 340px;
    background: linear-gradient(180deg, #1f3c88 0%, #1a3578 100%);
    border-radius: 14px;
    padding: 22px 20px;
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.28);
    text-align: center;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.10);
    transform: translateY(10px) scale(0.98);
    transition: 0.2s ease;
}

.logout-confirm-overlay.show .logout-confirm-box {
    transform: translateY(0) scale(1);
}

.logout-confirm-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #f7d98b;
}

.logout-confirm-box h3 {
    margin: 0 0 6px;
    font-size: 19px;
    font-weight: 700;
    color: #fff;
}

.logout-confirm-box p {
    margin: 0 0 18px;
    font-size: 13.5px;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.5;
}

.logout-confirm-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.logout-btn-cancel,
.logout-btn-confirm {
    border: none;
    border-radius: 8px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    min-width: 110px;
    transition: 0.15s ease;
}

.logout-btn-cancel {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
}

.logout-btn-cancel:hover {
    background: rgba(255, 255, 255, 0.22);
}

.logout-btn-confirm {
    background: #f7d98b;
    color: #17306b;
}

.logout-btn-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
}

@media (max-height: 760px) {
    .admin-sidebar-custom {
        height: auto;
        min-height: 100vh;
    }

    .admin-menu-main {
        justify-content: flex-start;
        gap: 8px;
    }

    .admin-menu-bottom {
        margin-top: 10px;
    }
}
</style>

<script>
function openLogoutModal(event) {
    event.preventDefault();
    const modal = document.getElementById('logoutConfirmOverlay');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutConfirmOverlay');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('logoutConfirmOverlay');
    if (e.target === modal) {
        closeLogoutModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
        closeMobileSidebar();
    }
});

/* MOBILE SIDEBAR: hamburger toggle + overlay for small screens */
function openMobileSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('mobileNavToggle');
    if (sidebar) sidebar.classList.add('mobile-open');
    if (overlay) overlay.classList.add('show');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('mobileNavToggle');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.remove('show');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('mobileNavToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('adminSidebar');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('.admin-menu-main a, .admin-menu-bottom a:not(.logout-link)').forEach(function(link) {
            link.addEventListener('click', closeMobileSidebar);
        });
    }
});
</script>