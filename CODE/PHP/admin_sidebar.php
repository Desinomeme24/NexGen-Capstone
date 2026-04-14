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

<aside class="admin-sidebar admin-sidebar-custom">
    <div class="brand-box">
        <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
        <div class="brand-meta">
            <h2><?php echo e($adminFullName); ?></h2>
            <p>System Administrator</p>
        </div>
    </div>

    <nav class="admin-menu admin-menu-custom">
        <div class="admin-menu-main">
            <a href="admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="pending_requests.php" class="<?php echo $currentPage === 'pending_requests.php' ? 'active' : ''; ?>">Pending Requests</a>
            <a href="manage_users.php" class="<?php echo $currentPage === 'manage_users.php' ? 'active' : ''; ?>">Manage Users</a>
            <a href="accounts_masterlist.php" class="<?php echo $currentPage === 'accounts_masterlist.php' ? 'active' : ''; ?>">Accounts Masterlist</a>
            <a href="admin_logs.php" class="<?php echo $currentPage === 'admin_logs.php' ? 'active' : ''; ?>">Admin Logs</a>
            <a href="/NexGen/CODE/PHP/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">Settings</a>
        </div>

        <div class="admin-menu-bottom">
            <a href="#" class="logout-link" onclick="openLogoutModal(event)">Log Out</a>
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
    justify-content: space-evenly;
    flex: 1;
    min-height: 0;
    gap: 14px;
}

.admin-menu-main a,
.admin-menu-bottom a {
    display: flex;
    align-items: center;
    min-height: 62px;
    box-sizing: border-box;
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
    max-width: 360px;
    background: linear-gradient(180deg, #1f3c88 0%, #1a3578 100%);
    border-radius: 20px;
    padding: 26px 22px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.30);
    text-align: center;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.10);
    transform: translateY(15px) scale(0.96);
    transition: 0.25s ease;
}

.logout-confirm-overlay.show .logout-confirm-box {
    transform: translateY(0) scale(1);
}

.logout-confirm-icon {
    width: 62px;
    height: 62px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #f7d98b;
}

.logout-confirm-box h3 {
    margin: 0 0 8px;
    font-size: 25px;
    font-weight: 800;
    color: #fff;
}

.logout-confirm-box p {
    margin: 0 0 22px;
    font-size: 15px;
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
    border-radius: 12px;
    padding: 11px 18px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    min-width: 120px;
    transition: 0.2s ease;
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
        gap: 14px;
    }

    .admin-menu-bottom {
        margin-top: 14px;
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
    }
});
</script>