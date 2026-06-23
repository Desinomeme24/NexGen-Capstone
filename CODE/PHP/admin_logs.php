<?php
session_start();
require_once("config.php");

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

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$fullName     = $_SESSION['full_name'] ?? 'System Administrator';

$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action'] ?? '');

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR l.description LIKE ? OR l.target_type LIKE ?) ";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($action !== '') {
    $where .= " AND l.action = ? ";
    $params[] = $action;
    $types .= "s";
}

$logs = [];
$actions = [];

$actionSql = "SELECT DISTINCT action FROM admin_logs ORDER BY action ASC";
$actionResult = $conn->query($actionSql);
if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        $actions[] = $row['action'];
    }
}

$sql = "
    SELECT 
        l.id,
        l.admin_id,
        l.action,
        l.target_type,
        l.target_id,
        l.description,
        l.previous_hash,
        l.log_hash,
        l.ip_address,
        l.user_agent,
        l.created_at,
        u.full_name AS admin_name,
        u.username AS admin_username
    FROM admin_logs l
    LEFT JOIN users u ON l.admin_id = u.id
    $where
    ORDER BY l.created_at DESC, l.id DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
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

function renderAdminLogsTable(array $logs): void
{
    ?>
    <div class="table-wrap logs-table-wrap" id="adminLogsTableWrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin</th>
                    <th>Username</th>
                    <th>Action</th>
                    <th>Target Type</th>
                    <th>Target ID</th>
                    <th>Description</th>
                    <th>Integrity</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="9">No admin log records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $isValidIntegrity = !empty($log['log_hash']) && verifyAdminLogRowIntegrity($log);
                    ?>
                    <tr>
                        <td><?php echo (int)$log['id']; ?></td>
                        <td><?php echo e($log['admin_name'] ?? 'Unknown Admin'); ?></td>
                        <td><?php echo e($log['admin_username'] ?? 'N/A'); ?></td>
                        <td><?php echo e($log['action']); ?></td>
                        <td><?php echo e($log['target_type']); ?></td>
                        <td><?php echo (int)$log['target_id']; ?></td>
                        <td><?php echo e($log['description']); ?></td>
                        <td>
                            <span class="mini-badge <?php echo $isValidIntegrity ? 'yes' : 'no'; ?>">
                                <?php echo $isValidIntegrity ? 'Valid' : 'Legacy/Check'; ?>
                            </span>
                        </td>
                        <td><?php echo e(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    renderAdminLogsTable($logs);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">

    <style>
        .logs-table-wrap {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 18px;
            position: relative;
            transition: opacity 0.15s ease;
        }

        .logs-table-wrap.loading {
            opacity: 0.55;
            pointer-events: none;
        }

        .logs-table-wrap table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .logs-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f245c;
        }

        .logs-table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .logs-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .logs-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
        }

        .mini-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            min-width: 90px;
        }

        .mini-badge.yes {
            background: rgba(70, 194, 126, 0.18);
            color: #8df0b3;
        }

        .mini-badge.no {
            background: rgba(255, 107, 107, 0.18);
            color: #ffb0b0;
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
                <h1>Admin Logs</h1>
            </div>
            <div class="user-pill">
                <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
                <span><?php echo e($fullName); ?></span>
            </div>
        </div>

        <section class="panel">
            <div class="panel-header">
                <h2>Activity Log Records</h2>
            </div>
            <div class="panel-body">
                <form method="GET" class="filters" id="logsFilterForm" onsubmit="return false;">
                    <input
                        class="input flex-1"
                        type="text"
                        name="search"
                        id="logsSearchInput"
                        placeholder="Search admin, description, or target type..."
                        value="<?php echo e($search); ?>"
                        autocomplete="off"
                    >

                    <select class="select w-240" name="action" id="logsActionFilter">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act): ?>
                            <option value="<?php echo e($act); ?>" <?php echo $action === $act ? 'selected' : ''; ?>>
                                <?php echo e($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <a class="btn btn-silver" href="admin_logs.php">Reset</a>
                </form>

                <div id="adminLogsContainer">
                    <?php renderAdminLogsTable($logs); ?>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="/NexGen/CODE/JS/admin_module.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('logsFilterForm');
    const searchInput = document.getElementById('logsSearchInput');
    const actionFilter = document.getElementById('logsActionFilter');
    const container = document.getElementById('adminLogsContainer');

    let controller = null;

    function updateLogs() {
        const params = new URLSearchParams(new FormData(form));

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const currentWrap = document.getElementById('adminLogsTableWrap');
        if (currentWrap) {
            currentWrap.classList.add('loading');
        }

        fetch('admin_logs.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            container.innerHTML = html;
            const qs = params.toString();
            window.history.replaceState({}, '', 'admin_logs.php' + (qs ? '?' + qs : ''));
        })
        .catch(function(error) {
            if (error.name !== 'AbortError') {
                console.error('Admin logs live filter error:', error);
            }
        })
        .finally(function() {
            const newWrap = document.getElementById('adminLogsTableWrap');
            if (newWrap) {
                newWrap.classList.remove('loading');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateLogs();
            }
        });
    }

    if (actionFilter) {
        actionFilter.addEventListener('change', updateLogs);
    }
});
</script>
</body>
</html>