<?php
session_start();
require_once("config.php");

/* SESSION SECURITY: enforce 5-minute timeout on admin dashboard */
enforceSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

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

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$fullName     = $_SESSION['full_name'] ?? 'System Administrator';

$pendingRequests  = 0;
$approvedRequests = 0;
$rejectedRequests = 0;
$activeUsers      = 0;

$res = $conn->query("SELECT COUNT(*) AS total FROM registration_requests WHERE request_status = 'pending'");
if ($res) {
    $pendingRequests = (int)($res->fetch_assoc()['total'] ?? 0);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM registration_requests WHERE request_status = 'approved'");
if ($res) {
    $approvedRequests = (int)($res->fetch_assoc()['total'] ?? 0);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM registration_requests WHERE request_status = 'rejected'");
if ($res) {
    $rejectedRequests = (int)($res->fetch_assoc()['total'] ?? 0);
}

$res = $conn->query("SELECT COUNT(*) AS total FROM users WHERE account_status = 'active'");
if ($res) {
    $activeUsers = (int)($res->fetch_assoc()['total'] ?? 0);
}

$recentRequests = [];
$sql = "SELECT id, request_code, employee_no, full_name, username, requested_role, request_status, created_at
        FROM registration_requests
        ORDER BY created_at DESC
        LIMIT 10";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentRequests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">
    <style>
        .dashboard-requests-table-wrap {
            max-height: 320px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 18px;
        }

        .dashboard-requests-table-wrap table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .dashboard-requests-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f245c;
        }

        .dashboard-requests-table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .dashboard-requests-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .dashboard-requests-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
        }
    </style>
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

<div class="admin-shell">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-content">
        <div class="topbar">
            <div class="page-title">
                <h1>System Administrator</h1>
            </div>
            <div class="user-pill">
                <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
                <span><?php echo e($fullName); ?></span>
            </div>
        </div>

        <div class="card-grid">
            <div class="stat-card">
                <h3>Pending Requests</h3>
                <div class="value"><?php echo $pendingRequests; ?></div>
            </div>
            <div class="stat-card">
                <h3>Approved Requests</h3>
                <div class="value"><?php echo $approvedRequests; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected Requests</h3>
                <div class="value"><?php echo $rejectedRequests; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Users</h3>
                <div class="value"><?php echo $activeUsers; ?></div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-header">
                <h2>Recent Registration Requests</h2>
                <a class="btn btn-gold" href="pending_requests.php">Open Requests</a>
            </div>
            <div class="panel-body">
                <div class="table-wrap dashboard-requests-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Request Code</th>
                                <th>Employee No</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentRequests)): ?>
                            <tr>
                                <td colspan="9">No registration requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRequests as $req): ?>
                                <tr>
                                    <td><?php echo (int)$req['id']; ?></td>
                                    <td><?php echo e($req['request_code']); ?></td>
                                    <td><?php echo e($req['employee_no']); ?></td>
                                    <td><?php echo e($req['full_name']); ?></td>
                                    <td><?php echo e($req['username']); ?></td>
                                    <td><?php echo e(ucwords(str_replace('_', ' ', $req['requested_role']))); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo e($req['request_status']); ?>">
                                            <?php echo e(ucfirst($req['request_status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($req['created_at']) ? e(date('M d, Y h:i A', strtotime($req['created_at']))) : '—'; ?></td>
                                    <td>
                                        <a class="btn btn-silver" href="view_request.php?id=<?php echo (int)$req['id']; ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="/NexGen/CODE/JS/admin_module.js"></script>
<script>
    /* SESSION SECURITY: client-side 5-minute inactivity auto logout */
    (function () {
        const timeoutMs = <?php echo (int)SESSION_TIMEOUT_SECONDS * 1000; ?>;
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
</body>
</html>