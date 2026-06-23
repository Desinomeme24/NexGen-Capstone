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

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$fullName     = $_SESSION['full_name'] ?? 'System Administrator';

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'resubmit'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($status !== 'all') {
    $where .= " AND request_status = ? ";
    $params[] = $status;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR username LIKE ? OR employee_no LIKE ? OR email LIKE ?) ";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

$sql = "SELECT * FROM registration_requests {$where} ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);

$requests = [];
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function renderPendingRequestsTable(array $requests): void
{
    ?>
    <div class="table-wrap pending-requests-table-wrap" id="pendingRequestsTableWrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Employee No</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Requested Role</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="9">No matching registration requests found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo e($row['employee_no']); ?></td>
                        <td><?php echo e($row['full_name']); ?></td>
                        <td><?php echo e($row['username']); ?></td>
                        <td><?php echo e($row['email']); ?></td>
                        <td><?php echo e(ucwords(str_replace('_', ' ', $row['requested_role']))); ?></td>
                        <td>
                            <span class="badge badge-<?php echo e($row['request_status']); ?>">
                                <?php echo e(ucfirst($row['request_status'])); ?>
                            </span>
                        </td>
                        <td><?php echo e(date('M d, Y h:i A', strtotime($row['created_at']))); ?></td>
                        <td>
                            <a class="btn btn-silver" href="view_request.php?id=<?php echo (int)$row['id']; ?>">View</a>
                        </td>
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
    renderPendingRequestsTable($requests);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Requests - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">

    <style>
        .pending-requests-table-wrap {
            max-height: 460px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 18px;
            position: relative;
            transition: opacity 0.15s ease;
        }

        .pending-requests-table-wrap.loading {
            opacity: 0.62;
            pointer-events: none;
        }

        .pending-requests-table-wrap table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .pending-requests-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f245c;
        }

        .pending-requests-table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .pending-requests-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .pending-requests-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
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
    </style>
</head>
<body>
<div class="admin-shell">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-content">
        <div class="topbar">
            <div class="page-title">
                <h1>Registration Requests</h1>
            </div>
            <div class="user-pill">
                <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
                <span><?php echo e($fullName); ?></span>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="notice <?php echo e($flash['type']); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2>All Requests</h2>
            </div>
            <div class="panel-body">
                <form method="GET" class="filters" id="requestFilterForm" onsubmit="return false;">
                    <input
                        class="input flex-1"
                        type="text"
                        name="search"
                        id="requestSearchInput"
                        placeholder="Search by name, username, employee no, or email..."
                        value="<?php echo e($search); ?>"
                        autocomplete="off"
                    >
                    <select class="select w-240" name="status" id="requestStatusFilter">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="resubmit" <?php echo $status === 'resubmit' ? 'selected' : ''; ?>>Resubmit</option>
                    </select>
                    <a class="btn btn-silver" href="pending_requests.php">Reset</a>
                </form>

                <div id="pendingRequestsContainer">
                    <?php renderPendingRequestsTable($requests); ?>
                </div>
            </div>
        </section>
    </main>
</div>

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

<script src="/NexGen/CODE/JS/admin_module.js"></script>
<script>
function openLogoutModal(event) {
    if (event) event.preventDefault();
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

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('requestFilterForm');
    const searchInput = document.getElementById('requestSearchInput');
    const statusFilter = document.getElementById('requestStatusFilter');
    const container = document.getElementById('pendingRequestsContainer');

    let controller = null;

    function updatePendingRequests() {
        const params = new URLSearchParams(new FormData(form));

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const currentWrap = document.getElementById('pendingRequestsTableWrap');
        if (currentWrap) currentWrap.classList.add('loading');

        fetch('pending_requests.php?' + params.toString(), {
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
            window.history.replaceState({}, '', 'pending_requests.php' + (qs ? '?' + qs : ''));
        })
        .catch(function(error) {
            if (error.name !== 'AbortError') {
                console.error('Pending requests live filter error:', error);
            }
        })
        .finally(function() {
            const newWrap = document.getElementById('pendingRequestsTableWrap');
            if (newWrap) newWrap.classList.remove('loading');
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updatePendingRequests();
            }
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', updatePendingRequests);
    }
});
</script>
</body>
</html>