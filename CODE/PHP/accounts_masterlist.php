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

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function initialsFromName(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $parts = array_filter($parts);

    if (empty($parts)) {
        return '?';
    }

    if (count($parts) === 1) {
        return strtoupper(mb_substr($parts[0], 0, 2));
    }

    $first = mb_substr(reset($parts), 0, 1);
    $last  = mb_substr(end($parts), 0, 1);

    return strtoupper($first . $last);
}

function statusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'active' => 'badge-active-state',
        'inactive' => 'badge-inactive-state',
        'terminated' => 'badge-terminated-state',
        default => 'badge-inactive-state',
    };
}

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$fullName     = $_SESSION['full_name'] ?? 'System Administrator';

$search = trim($_GET['search'] ?? '');
$accounts = [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$hasLastLoginAt = hasColumn($conn, 'users', 'last_login_at');
$activityExpr = $hasLastLoginAt
    ? "COALESCE(u.last_login_at, u.created_at)"
    : "u.created_at";

$where = " WHERE u.role IN ('owner', 'employee') ";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (
        u.employee_no LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
        OR u.phone LIKE ?
        OR u.role LIKE ?
    ) ";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like, $like];
    $types = "sssss";
}

$sql = "
    SELECT
        u.id,
        u.employee_no,
        u.full_name,
        u.email,
        u.phone,
        CASE
            WHEN u.role = 'owner' THEN 'Owner'
            WHEN u.role = 'employee' THEN 'Employee'
            ELSE u.role
        END AS position,
        CASE
            WHEN u.account_status IN ('disabled', 'rejected') THEN 'terminated'
            WHEN {$activityExpr} < DATE_SUB(NOW(), INTERVAL 15 DAY) THEN 'inactive'
            ELSE 'active'
        END AS visibility_status,
        u.created_at
    FROM users u
    {$where}
    ORDER BY u.created_at DESC, u.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }

    $stmt->close();
}

function renderAccountsTable(array $accounts): void
{
    ?>
    <div class="table-wrap accounts-table-wrap" id="accountsTableWrap">
        <table>
            <colgroup>
                <col style="width: 80px;">
                <col style="width: 150px;">
                <col style="width: 240px;">
                <col style="width: 270px;">
                <col style="width: 160px;">
                <col style="width: 160px;">
                <col style="width: 150px;">
                <col style="width: 180px;">
            </colgroup>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>EMPLOYEE NO</th>
                    <th>FULL NAME</th>
                    <th>EMAIL</th>
                    <th>PHONE</th>
                    <th>POSITION</th>
                    <th>STATUS</th>
                    <th>CREATED</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($accounts)): ?>
                <tr>
                    <td colspan="8">No account records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($accounts as $acc): ?>
                    <tr>
                        <td class="id-col"><?php echo (int)$acc['id']; ?></td>
                        <td class="empno-col"><?php echo e($acc['employee_no']); ?></td>
                        <td><?php echo e($acc['full_name']); ?></td>
                        <td><?php echo e($acc['email']); ?></td>
                        <td><?php echo e($acc['phone']); ?></td>
                        <td class="position-col"><?php echo e($acc['position']); ?></td>
                        <td class="status-col">
                            <span class="<?php echo e(statusBadgeClass((string)$acc['visibility_status'])); ?>">
                                <?php echo e(ucfirst((string)$acc['visibility_status'])); ?>
                            </span>
                        </td>
                        <td class="created-col">
                            <?php echo !empty($acc['created_at']) ? e(date('M d, Y h:i A', strtotime($acc['created_at']))) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="accounts-cards-wrap" id="accountsCardsWrap">
        <?php if (empty($accounts)): ?>
            <div class="account-card-empty">No account records found.</div>
        <?php else: ?>
            <?php foreach ($accounts as $i => $acc): ?>
                <div class="account-card">
                    <div class="account-card-index"><?php echo e(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)); ?></div>
                    <div class="account-card-avatar"><?php echo e(initialsFromName((string)$acc['full_name'])); ?></div>
                    <div class="account-card-body">
                        <div class="account-card-top">
                            <span class="account-card-name"><?php echo e($acc['full_name']); ?></span>
                            <span class="position-pill"><?php echo e($acc['position']); ?></span>
                        </div>
                        <div class="account-card-meta-row">
                            <span class="account-card-phone">
                                <i class="bi bi-telephone-fill"></i>
                                <?php echo e($acc['phone']); ?>
                            </span>
                        </div>
                        <div class="account-card-bottom-row">
                            <span class="<?php echo e(statusBadgeClass((string)$acc['visibility_status'])); ?>">
                                <?php echo e(ucfirst((string)$acc['visibility_status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    renderAccountsTable($accounts);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Masterlist - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .accounts-table-wrap {
            max-height: 460px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 18px;
            position: relative;
            transition: opacity 0.15s ease;
        }

        .accounts-table-wrap.loading {
            opacity: 0.55;
            pointer-events: none;
        }

        .accounts-table-wrap table {
            width: 100%;
            min-width: 1300px;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .accounts-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f245c;
        }

        .accounts-table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .accounts-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .accounts-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
        }

        .badge-active-state,
        .badge-inactive-state,
        .badge-terminated-state {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .badge-active-state {
            background: rgba(71, 193, 117, 0.18);
            color: #90f0b3;
        }

        .badge-inactive-state {
            background: rgba(255, 193, 7, 0.18);
            color: #ffd86a;
        }

        .badge-terminated-state {
            background: rgba(255, 107, 107, 0.18);
            color: #ffb3b3;
        }

        .created-col,
        .status-col,
        .position-col,
        .id-col,
        .empno-col {
            white-space: nowrap;
        }

        #accountsSearchInput {
            flex: 0 0 500px !important;
            width: 500px !important;
            max-width: 500px !important;
        }

        #accountsFilterForm {
            padding-top: 20px !important;
        }

        @media (max-width: 768px) {
            #accountsFilterForm {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            #accountsSearchInput {
                flex: 0 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            #accountsFilterForm .btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                flex: 0 0 auto !important;
                width: auto !important;
                min-width: 0 !important;
                max-width: none !important;
            }
        }

        /* Mobile card list */
        .accounts-cards-wrap {
            display: none;
        }

        .account-card-empty {
            padding: 18px;
            text-align: center;
            opacity: 0.7;
        }

        .account-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            margin-bottom: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .account-card-index {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            opacity: 0.55;
            align-self: center;
        }

        .account-card-avatar {
            flex: 0 0 auto;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2e4fa3;
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
            align-self: center;
        }

        .account-card-body {
            flex: 1 1 auto;
            min-width: 0;
        }

        .account-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .account-card-name {
            font-weight: 800;
            font-size: 15px;
            line-height: 1.3;
        }

        .position-pill {
            flex: 0 0 auto;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            background: rgba(91, 141, 239, 0.14);
            color: #8fb4ff;
            border: 1px solid rgba(143, 180, 255, 0.4);
        }

        .account-card-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
            font-size: 13px;
            opacity: 0.75;
        }

        .account-card-phone {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .account-card-bottom-row {
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .accounts-table-wrap {
                display: none;
            }

            .accounts-cards-wrap {
                display: block;
            }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <?php include 'admin_sidebar.php'; ?>

    <main class="admin-content">
        <div class="topbar">
            <div class="page-title">
                <h1><i class="bi bi-person-lines-fill" style="margin-right: 10px;"></i>Accounts Masterlist</h1>
            </div>
            
        </div>

        <?php if ($flash): ?>
            <div class="notice <?php echo e($flash['type']); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2>Accounts Lists</h2>
            </div>

            <form method="GET" class="filters" id="accountsFilterForm" onsubmit="return false;">
                <input
                    class="input flex-3"
                    type="text"
                    name="search"
                    id="accountsSearchInput"
                    placeholder="Search account, role, phone, or email..."
                    value="<?php echo e($search); ?>"
                    autocomplete="off"
                >
                <a class="btn btn-silver" href="accounts_masterlist.php"><i class="bi bi-arrow-repeat"></i> Reset</a>
            </form>

            <div id="accountsContainer">
                <?php renderAccountsTable($accounts); ?>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('accountsFilterForm');
    const searchInput = document.getElementById('accountsSearchInput');
    const container = document.getElementById('accountsContainer');

    let controller = null;

    function updateAccounts() {
        const params = new URLSearchParams(new FormData(form));

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const currentWrap = document.getElementById('accountsTableWrap');
        if (currentWrap) {
            currentWrap.classList.add('loading');
        }

        fetch('accounts_masterlist.php?' + params.toString(), {
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
            window.history.replaceState({}, '', 'accounts_masterlist.php' + (qs ? '?' + qs : ''));
        })
        .catch(function(error) {
            if (error.name !== 'AbortError') {
                console.error('Accounts masterlist live filter error:', error);
            }
        })
        .finally(function() {
            const newWrap = document.getElementById('accountsTableWrap');
            if (newWrap) {
                newWrap.classList.remove('loading');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateAccounts();
            }
        });
    }
});
</script>
</body>
</html>