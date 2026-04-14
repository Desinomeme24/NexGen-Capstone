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

function moduleAccessSummary(array $user): string
{
    $modules = [];

    if (!empty($user['can_inventory'])) {
        $modules[] = 'Inventory';
    }

    if (!empty($user['can_sales'])) {
        $modules[] = 'Sales';
    }

    if (!empty($user['can_sales_analytics'])) {
        $modules[] = 'Sales Analytics';
    }

    if (!empty($user['can_accounts_receivable'])) {
        $modules[] = 'Accounts Receivable';
    }

    return empty($modules) ? 'No Access' : implode(', ', $modules);
}

function getDefaultModulePermissions(string $role): array
{
    switch ($role) {
        case 'owner':
            return [1, 1, 1, 1];
        case 'employee':
            return [1, 1, 0, 1];
        case 'customer':
        default:
            return [0, 0, 0, 0];
    }
}

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$fullName     = $_SESSION['full_name'] ?? 'System Administrator';
$adminId      = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| BULK ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_module_action') {
    $bulkAction  = trim($_POST['bulk_action'] ?? '');
    $selectedIds = $_POST['selected_user_ids'] ?? [];

    $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array)$selectedIds))));

    try {
        if (empty($selectedIds)) {
            throw new Exception('Please select at least one user first.');
        }

        if (!in_array($bulkAction, ['grant_access', 'revoke_access'], true)) {
            throw new Exception('Please choose a valid action.');
        }

        $conn->begin_transaction();

        $selectStmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ? FOR UPDATE");
        if (!$selectStmt) {
            throw new Exception('Failed to prepare selected user lookup: ' . $conn->error);
        }

        $updateStmt = $conn->prepare("
            UPDATE users
            SET
                can_inventory = ?,
                can_sales = ?,
                can_sales_analytics = ?,
                can_accounts_receivable = ?
            WHERE id = ?
              AND role <> 'system_admin'
        ");
        if (!$updateStmt) {
            throw new Exception('Failed to prepare selected user update: ' . $conn->error);
        }

        $processedCount = 0;
        $processedUsers = [];

        foreach ($selectedIds as $userId) {
            $selectStmt->bind_param("i", $userId);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                continue;
            }

            if ($user['role'] === 'system_admin') {
                continue;
            }

            if ($bulkAction === 'grant_access') {
                [$canInventory, $canSales, $canSalesAnalytics, $canAccountsReceivable] = getDefaultModulePermissions($user['role']);
            } else {
                $canInventory = 0;
                $canSales = 0;
                $canSalesAnalytics = 0;
                $canAccountsReceivable = 0;
            }

            $updateStmt->bind_param(
                "iiiii",
                $canInventory,
                $canSales,
                $canSalesAnalytics,
                $canAccountsReceivable,
                $userId
            );

            if (!$updateStmt->execute()) {
                throw new Exception('Failed to update selected users: ' . $updateStmt->error);
            }

            $processedCount++;
            $processedUsers[] = $user['username'];
        }

        $selectStmt->close();
        $updateStmt->close();

        if ($processedCount === 0) {
            throw new Exception('No valid non-admin user accounts were selected.');
        }

        $description = ($bulkAction === 'grant_access'
            ? 'Granted default module access to: '
            : 'Revoked module access from: ')
            . implode(', ', $processedUsers);

        $logStmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, description)
            VALUES (?, ?, 'user_batch', 0, ?)
        ");
        if ($logStmt) {
            $actionName = $bulkAction;
            $logStmt->bind_param("iss", $adminId, $actionName, $description);
            $logStmt->execute();
            $logStmt->close();
        }

        $conn->commit();

        $_SESSION['success'] = $bulkAction === 'grant_access'
            ? "Access granted successfully to {$processedCount} selected user(s)."
            : "Access revoked successfully from {$processedCount} selected user(s).";

        header("Location: manage_users.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: manage_users.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| SINGLE USER UPDATE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $userId                = (int)($_POST['user_id'] ?? 0);
    $employeeNo            = trim($_POST['employee_no'] ?? '');
    $userFullName          = trim($_POST['full_name'] ?? '');
    $username              = trim($_POST['username'] ?? '');
    $email                 = trim($_POST['email'] ?? '');
    $phone                 = trim($_POST['phone'] ?? '');
    $address               = trim($_POST['address'] ?? '');
    $role                  = trim($_POST['role'] ?? '');
    $accountStatus         = trim($_POST['account_status'] ?? '');
    $isVerified            = isset($_POST['is_verified']) ? (int)$_POST['is_verified'] : 0;

    $canInventory          = isset($_POST['can_inventory']) ? 1 : 0;
    $canSales              = isset($_POST['can_sales']) ? 1 : 0;
    $canSalesAnalytics     = isset($_POST['can_sales_analytics']) ? 1 : 0;
    $canAccountsReceivable = isset($_POST['can_accounts_receivable']) ? 1 : 0;

    try {
        if ($userId <= 0) {
            throw new Exception('Invalid user ID.');
        }

        if ($userFullName === '' || $username === '' || $email === '' || $role === '' || $accountStatus === '') {
            throw new Exception('Please fill in all required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        $allowedRoles = ['owner', 'employee', 'customer'];
        if (!in_array($role, $allowedRoles, true)) {
            throw new Exception('Invalid role selected.');
        }

        if ($role === 'customer') {
            $canInventory = 0;
            $canSales = 0;
            $canSalesAnalytics = 0;
            $canAccountsReceivable = 0;
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT id, username, email, role, account_status FROM users WHERE id = ? FOR UPDATE");
        if (!$stmt) {
            throw new Exception('Failed to read user record: ' . $conn->error);
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $currentResult = $stmt->get_result();
        $currentUser = $currentResult->fetch_assoc();
        $stmt->close();

        if (!$currentUser) {
            throw new Exception('User not found.');
        }

        if ($currentUser['role'] === 'system_admin') {
            throw new Exception('System Administrator accounts cannot be modified from Manage Users.');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Failed to validate duplicate username/email: ' . $conn->error);
        }

        $stmt->bind_param("ssi", $username, $email, $userId);
        $stmt->execute();
        $duplicateResult = $stmt->get_result();

        if ($duplicateResult->num_rows > 0) {
            $stmt->close();
            throw new Exception('Username or email already exists for another account.');
        }
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE users
            SET
                employee_no = ?,
                full_name = ?,
                username = ?,
                email = ?,
                phone = ?,
                address = ?,
                role = ?,
                account_status = ?,
                is_verified = ?,
                can_inventory = ?,
                can_sales = ?,
                can_sales_analytics = ?,
                can_accounts_receivable = ?
            WHERE id = ?
              AND role <> 'system_admin'
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare user update: ' . $conn->error);
        }

        $stmt->bind_param(
            "ssssssssiiiiii",
            $employeeNo,
            $userFullName,
            $username,
            $email,
            $phone,
            $address,
            $role,
            $accountStatus,
            $isVerified,
            $canInventory,
            $canSales,
            $canSalesAnalytics,
            $canAccountsReceivable,
            $userId
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to update user account: ' . $stmt->error);
        }
        $stmt->close();

        $description = "Updated user #{$userId} ({$username}) - role: {$role}, status: {$accountStatus}, verified: {$isVerified}, inventory: {$canInventory}, sales: {$canSales}, analytics: {$canSalesAnalytics}, accounts_receivable: {$canAccountsReceivable}";

        $stmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, description)
            VALUES (?, 'update_user_permissions', 'user', ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("iis", $adminId, $userId, $description);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        $_SESSION['success'] = 'User account and module access updated successfully.';
        header('Location: manage_users.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header('Location: manage_users.php' . ($userId > 0 ? '?edit=' . $userId : ''));
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$search       = trim($_GET['search'] ?? '');
$roleFilter   = trim($_GET['role_filter'] ?? 'all');
$statusFilter = trim($_GET['status_filter'] ?? 'all');
$editId       = (int)($_GET['edit'] ?? 0);

$roles = [];
$statuses = [];

$roleResult = $conn->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role <> '' AND role <> 'system_admin' ORDER BY role ASC");
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        $roles[] = $row['role'];
    }
}

$statusResult = $conn->query("SELECT DISTINCT account_status FROM users WHERE account_status IS NOT NULL AND account_status <> '' ORDER BY account_status ASC");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $statuses[] = $row['account_status'];
    }
}

$where = " WHERE role <> 'system_admin' ";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ? OR employee_no LIKE ? OR phone LIKE ?) ";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssss";
}

if ($roleFilter !== '' && $roleFilter !== 'all') {
    $where .= " AND role = ? ";
    $params[] = $roleFilter;
    $types .= "s";
}

if ($statusFilter !== '' && $statusFilter !== 'all') {
    $where .= " AND account_status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

$users = [];
$sql = "
    SELECT
        id,
        employee_no,
        full_name,
        username,
        email,
        phone,
        address,
        role,
        account_status,
        is_verified,
        can_inventory,
        can_sales,
        can_sales_analytics,
        can_accounts_receivable
    FROM users
    {$where}
    ORDER BY id DESC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

$editUser = null;
if ($editId > 0) {
    $stmt = $conn->prepare("
        SELECT
            id,
            employee_no,
            full_name,
            username,
            email,
            phone,
            address,
            role,
            account_status,
            is_verified,
            can_inventory,
            can_sales,
            can_sales_analytics,
            can_accounts_receivable
        FROM users
        WHERE id = ?
          AND role <> 'system_admin'
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editUser = $result->fetch_assoc();
        $stmt->close();

        if ($editId > 0 && !$editUser) {
            $_SESSION['error'] = 'System Administrator accounts are excluded from Manage Users.';
            header("Location: manage_users.php");
            exit();
        }
    }
}

function renderManageUsersTable(array $users, string $search, string $roleFilter, string $statusFilter): void
{
    ?>
    <div id="manageUsersContainer">
        <form method="POST" id="bulkActionForm">
            <input type="hidden" name="action" value="bulk_module_action">

            <div class="table-wrap manage-users-table-wrap" id="manageUsersTableWrap">
                <table>
                    <colgroup>
                        <col style="width: 56px;">
                        <col style="width: 72px;">
                        <col style="width: 150px;">
                        <col style="width: 200px;">
                        <col style="width: 160px;">
                        <col style="width: 220px;">
                        <col style="width: 140px;">
                        <col style="width: 130px;">
                        <col style="width: 130px;">
                        <col style="width: 140px;">
                        <col style="width: 240px;">
                        <col style="width: 110px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="select-col">
                                <input type="checkbox" class="select-all-check" id="selectAllRows">
                            </th>
                            <th>ID</th>
                            <th>EMPLOYEE NO</th>
                            <th>FULL NAME</th>
                            <th>USERNAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>ROLE</th>
                            <th>VERIFIED</th>
                            <th>STATUS</th>
                            <th>MODULE ACCESS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="12">No user accounts found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="select-col">
                                    <input
                                        type="checkbox"
                                        class="row-check"
                                        name="selected_user_ids[]"
                                        value="<?php echo (int)$user['id']; ?>"
                                    >
                                </td>
                                <td class="table-id"><?php echo (int)$user['id']; ?></td>
                                <td class="table-employee"><?php echo e($user['employee_no']); ?></td>
                                <td><?php echo e($user['full_name']); ?></td>
                                <td><?php echo e($user['username']); ?></td>
                                <td class="table-email"><?php echo e($user['email']); ?></td>
                                <td class="table-phone"><?php echo e($user['phone']); ?></td>
                                <td class="table-role"><?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?></td>
                                <td class="table-verified">
                                    <span class="mini-badge <?php echo (int)$user['is_verified'] === 1 ? 'yes' : 'no'; ?>">
                                        <?php echo (int)$user['is_verified'] === 1 ? 'Verified' : 'Not Verified'; ?>
                                    </span>
                                </td>
                                <td class="table-status">
                                    <span class="badge badge-<?php echo e($user['account_status']); ?>">
                                        <?php echo e(ucfirst($user['account_status'])); ?>
                                    </span>
                                </td>
                                <td class="table-modules modules-cell"><?php echo e(moduleAccessSummary($user)); ?></td>
                                <td class="table-action action-cell">
                                    <a class="btn btn-silver" href="manage_users.php?<?php echo http_build_query([
                                        'search' => $search,
                                        'role_filter' => $roleFilter,
                                        'status_filter' => $statusFilter,
                                        'edit' => (int)$user['id']
                                    ]); ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    <?php
}

if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    renderManageUsersTable($users, $search, $roleFilter, $statusFilter);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">
    <style>
        .manage-users-table-wrap {
            max-height: 470px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 18px;
            position: relative;
            transition: opacity 0.15s ease;
        }

        .manage-users-table-wrap.loading {
            opacity: 0.62;
            pointer-events: none;
        }

        .manage-users-table-wrap table {
            width: 100%;
            min-width: 1380px;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .manage-users-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #0f245c;
        }

        .manage-users-table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .manage-users-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 999px;
        }

        .manage-users-table-wrap::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 999px;
        }

        .toolbar-form {
            display: grid;
            grid-template-columns: minmax(280px, 1.8fr) 180px 180px 180px auto auto;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
        }

        .toolbar-form .input,
        .toolbar-form .select,
        .toolbar-form .btn {
            width: 100%;
            box-sizing: border-box;
        }

        .select-col {
            width: 56px;
            text-align: center;
        }

        .row-check,
        .select-all-check {
            width: 18px;
            height: 18px;
            accent-color: #f7d98b;
            cursor: pointer;
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
        }

        .mini-badge.yes {
            background: rgba(70, 194, 126, 0.18);
            color: #8df0b3;
        }

        .mini-badge.no {
            background: rgba(255, 107, 107, 0.18);
            color: #ffb0b0;
        }

        .modules-cell {
            line-height: 1.5;
            white-space: normal;
            word-break: break-word;
        }

        .action-cell {
            text-align: center;
        }

        .action-cell .btn {
            min-width: 90px;
        }

        .table-id,
        .table-employee,
        .table-role,
        .table-status,
        .table-verified,
        .table-action {
            white-space: nowrap;
        }

        .table-email,
        .table-phone,
        .table-modules {
            word-break: break-word;
        }

        .editor-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .editor-grid .full {
            grid-column: 1 / -1;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field-group label {
            color: #fff;
            font-weight: 700;
            font-size: 14px;
        }

        .field-group input,
        .field-group select,
        .field-group textarea {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            outline: none;
            box-sizing: border-box;
        }

        .field-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .field-group input::placeholder,
        .field-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .field-group select option {
            color: #111;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .access-box {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 18px;
        }

        .access-box h3 {
            margin: 0 0 8px;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }

        .access-box p {
            margin: 0 0 16px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 14px;
            line-height: 1.5;
        }

        .access-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .check-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 14px;
            padding: 14px;
        }

        .check-card input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #f7d98b;
            cursor: pointer;
            flex-shrink: 0;
        }

        .check-card input[type="checkbox"]:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .check-card label {
            margin: 0;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .role-note {
            grid-column: 1 / -1;
            margin-top: 6px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.75);
        }

        .edit-modal-overlay,
        .custom-confirm-overlay,
        .custom-alert-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.50);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99998;
            padding: 20px;
        }

        .edit-modal-overlay.show,
        .custom-confirm-overlay.show,
        .custom-alert-overlay.show {
            display: flex;
        }

        .edit-modal-box,
        .custom-confirm-box,
        .custom-alert-box {
            background: linear-gradient(180deg, #264ba6 0%, #1c3f99 100%);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 24px;
            box-shadow: 0 22px 50px rgba(0, 0, 0, 0.32);
        }

        .edit-modal-box {
            width: min(1100px, 96vw);
            max-height: 90vh;
            overflow-y: auto;
        }

        .custom-confirm-box,
        .custom-alert-box {
            width: 100%;
            max-width: 380px;
            padding: 26px 22px;
            text-align: center;
            color: #fff;
        }

        .edit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 22px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        }

        .edit-modal-header h2 {
            margin: 0;
            color: #fff;
            font-size: 30px;
            font-weight: 900;
        }

        .edit-modal-body {
            padding: 24px;
        }

        .modal-close-btn {
            appearance: none;
            border: none;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        .custom-confirm-icon,
        .custom-alert-icon {
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

        .custom-confirm-box h3,
        .custom-alert-box h3 {
            margin: 0 0 8px;
            font-size: 25px;
            font-weight: 800;
            color: #fff;
        }

        .custom-confirm-box p,
        .custom-alert-box p {
            margin: 0 0 22px;
            font-size: 15px;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.5;
        }

        .custom-confirm-actions,
        .custom-alert-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .custom-btn-cancel,
        .custom-btn-confirm,
        .custom-btn-ok {
            border: none;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            min-width: 120px;
            transition: 0.2s ease;
        }

        .custom-btn-cancel {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        .custom-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        .custom-btn-confirm,
        .custom-btn-ok {
            background: #f7d98b;
            color: #17306b;
        }

        .custom-btn-confirm:hover,
        .custom-btn-ok:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
        }

        @media (max-width: 1350px) {
            .toolbar-form {
                grid-template-columns: minmax(220px, 1fr) 170px 170px 170px auto auto;
            }
        }

        @media (max-width: 1100px) {
            .toolbar-form {
                grid-template-columns: 1fr 1fr;
            }

            .toolbar-form > * {
                min-width: 0;
            }
        }

        @media (max-width: 900px) {
            .editor-grid,
            .access-grid,
            .toolbar-form {
                grid-template-columns: 1fr;
            }

            .edit-modal-body {
                padding: 18px;
            }

            .edit-modal-header {
                padding: 18px;
            }

            .edit-modal-header h2 {
                font-size: 24px;
            }
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
                <h1>Manage Users</h1>
            </div>
            <div class="user-pill">
                <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
                <span><?php echo e($fullName); ?></span>
            </div>
        </div>

        <section class="panel">
            <div class="panel-header">
                <h2>User Accounts</h2>
            </div>
            <div class="panel-body">

                <form method="GET" class="toolbar-form" id="filterForm" onsubmit="return false;">
                    <input
                        class="input"
                        type="text"
                        name="search"
                        id="searchInput"
                        placeholder="Search by name, username, email, employee number, or phone..."
                        value="<?php echo e($search); ?>"
                        autocomplete="off"
                    >

                    <select class="select" name="role_filter" id="roleFilter">
                        <option value="all">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo e($role); ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                                <?php echo e(ucwords(str_replace('_', ' ', $role))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" name="status_filter" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                <?php echo e(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" id="bulkActionSelect" form="bulkActionForm" name="bulk_action">
                        <option value="">Actions</option>
                        <option value="grant_access">Grant Access</option>
                        <option value="revoke_access">Revoke Access</option>
                    </select>

                    <button class="btn btn-gold" type="submit" form="bulkActionForm">Apply</button>
                    <a class="btn btn-silver" href="manage_users.php">Reset</a>
                </form>

                <?php renderManageUsersTable($users, $search, $roleFilter, $statusFilter); ?>

            </div>
        </section>
    </main>
</div>

<?php if ($editUser): ?>
<div class="edit-modal-overlay show" id="editUserModal">
    <div class="edit-modal-box">
        <div class="edit-modal-header">
            <h2>Edit User #<?php echo (int)$editUser['id']; ?></h2>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()">&times;</button>
        </div>

        <div class="edit-modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" value="<?php echo (int)$editUser['id']; ?>">

                <div class="editor-grid">
                    <div class="field-group">
                        <label>Employee No</label>
                        <input type="text" name="employee_no" value="<?php echo e($editUser['employee_no']); ?>" placeholder="Enter employee number">
                    </div>

                    <div class="field-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo e($editUser['full_name']); ?>" required>
                    </div>

                    <div class="field-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo e($editUser['username']); ?>" required>
                    </div>

                    <div class="field-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo e($editUser['email']); ?>" required>
                    </div>

                    <div class="field-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo e($editUser['phone']); ?>" placeholder="Enter phone number">
                    </div>

                    <div class="field-group">
                        <label>Role</label>
                        <select name="role" id="roleSelect" required onchange="handleRoleChange()">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo e($role); ?>" <?php echo $editUser['role'] === $role ? 'selected' : ''; ?>>
                                    <?php echo e(ucwords(str_replace('_', ' ', $role))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Account Status</label>
                        <select name="account_status" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $editUser['account_status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo e(ucfirst($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Verification Status</label>
                        <select name="is_verified" required>
                            <option value="1" <?php echo (int)$editUser['is_verified'] === 1 ? 'selected' : ''; ?>>Verified</option>
                            <option value="0" <?php echo (int)$editUser['is_verified'] === 0 ? 'selected' : ''; ?>>Not Verified</option>
                        </select>
                    </div>

                    <div class="field-group full">
                        <label>Address</label>
                        <textarea name="address" placeholder="Enter address"><?php echo e($editUser['address']); ?></textarea>
                    </div>

                    <div class="access-box">
                        <h3>Module Access</h3>
                        <p>
                            Owner has access to all 4 modules. Employee has access to all except Sales Analytics.
                            Customer has no access to the 4 business modules.
                        </p>

                        <div class="access-grid">
                            <div class="check-card">
                                <input type="checkbox" id="can_inventory" name="can_inventory" <?php echo !empty($editUser['can_inventory']) ? 'checked' : ''; ?>>
                                <label for="can_inventory">Inventory Management</label>
                            </div>

                            <div class="check-card">
                                <input type="checkbox" id="can_sales" name="can_sales" <?php echo !empty($editUser['can_sales']) ? 'checked' : ''; ?>>
                                <label for="can_sales">Sales</label>
                            </div>

                            <div class="check-card">
                                <input type="checkbox" id="can_sales_analytics" name="can_sales_analytics" <?php echo !empty($editUser['can_sales_analytics']) ? 'checked' : ''; ?>>
                                <label for="can_sales_analytics">Sales Analytics</label>
                            </div>

                            <div class="check-card">
                                <input type="checkbox" id="can_accounts_receivable" name="can_accounts_receivable" <?php echo !empty($editUser['can_accounts_receivable']) ? 'checked' : ''; ?>>
                                <label for="can_accounts_receivable">Accounts Receivable</label>
                            </div>
                        </div>

                        <div class="role-note" id="roleNote">
                            You can manage module access here based on the selected non-admin role.
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-gold">Save Changes</button>
                    <button type="button" class="btn btn-silver" onclick="closeEditModal()">Cancel</button>
                    <button type="button" class="btn btn-silver" onclick="applyRoleDefaults()">Apply Role Default Access</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="custom-confirm-overlay" id="customConfirmOverlay">
    <div class="custom-confirm-box">
        <div class="custom-confirm-icon">?</div>
        <h3>Confirmation</h3>
        <p id="customConfirmMessage">Are you sure?</p>
        <div class="custom-confirm-actions">
            <button type="button" class="custom-btn-cancel" id="customConfirmCancel">Cancel</button>
            <button type="button" class="custom-btn-confirm" id="customConfirmOk">Confirm</button>
        </div>
    </div>
</div>

<div class="custom-alert-overlay" id="customAlertOverlay">
    <div class="custom-alert-box">
        <div class="custom-alert-icon">!</div>
        <h3>Notice</h3>
        <p id="customAlertMessage">Message</p>
        <div class="custom-alert-actions">
            <button type="button" class="custom-btn-ok" id="customAlertOk">OK</button>
        </div>
    </div>
</div>

<script src="/NexGen/CODE/JS/admin_module.js"></script>
<script>
function setModuleCheckboxState(disabled) {
    const ids = [
        'can_inventory',
        'can_sales',
        'can_sales_analytics',
        'can_accounts_receivable'
    ];

    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.disabled = disabled;
        }
    });
}

function applyRoleDefaults() {
    const role = document.getElementById('roleSelect') ? document.getElementById('roleSelect').value : '';
    const inventory = document.getElementById('can_inventory');
    const sales = document.getElementById('can_sales');
    const analytics = document.getElementById('can_sales_analytics');
    const receivable = document.getElementById('can_accounts_receivable');
    const roleNote = document.getElementById('roleNote');

    if (!inventory || !sales || !analytics || !receivable) {
        return;
    }

    if (role === 'owner') {
        inventory.checked = true;
        sales.checked = true;
        analytics.checked = true;
        receivable.checked = true;
        setModuleCheckboxState(false);
        if (roleNote) roleNote.textContent = 'Owner default access: all 4 modules are enabled.';
        return;
    }

    if (role === 'employee') {
        inventory.checked = true;
        sales.checked = true;
        analytics.checked = false;
        receivable.checked = true;
        setModuleCheckboxState(false);
        if (roleNote) roleNote.textContent = 'Employee default access: Inventory, Sales, and Accounts Receivable only.';
        return;
    }

    if (role === 'customer') {
        inventory.checked = false;
        sales.checked = false;
        analytics.checked = false;
        receivable.checked = false;
        setModuleCheckboxState(true);
        if (roleNote) roleNote.textContent = 'Customer has no access to the 4 business modules.';
        return;
    }

    inventory.checked = false;
    sales.checked = false;
    analytics.checked = false;
    receivable.checked = false;
    setModuleCheckboxState(false);
    if (roleNote) roleNote.textContent = 'Select a role to set default module access.';
}

function handleRoleChange() {
    applyRoleDefaults();
}

function closeEditModal() {
    const url = new URL(window.location.href);
    url.searchParams.delete('edit');
    window.location.href = url.toString();
}

function showCustomAlert(message) {
    const overlay = document.getElementById('customAlertOverlay');
    const messageBox = document.getElementById('customAlertMessage');
    if (!overlay || !messageBox) return;
    messageBox.textContent = message;
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeCustomAlert() {
    const overlay = document.getElementById('customAlertOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function showCustomConfirm(message, onConfirm) {
    const overlay = document.getElementById('customConfirmOverlay');
    const messageBox = document.getElementById('customConfirmMessage');
    const okBtn = document.getElementById('customConfirmOk');
    const cancelBtn = document.getElementById('customConfirmCancel');

    if (!overlay || !messageBox || !okBtn || !cancelBtn) return;

    messageBox.textContent = message;
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';

    const close = function() {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        okBtn.onclick = null;
        cancelBtn.onclick = null;
    };

    cancelBtn.onclick = close;
    okBtn.onclick = function() {
        close();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    applyRoleDefaults();

    const filterForm = document.getElementById('filterForm');
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    const alertOk = document.getElementById('customAlertOk');

    let controller = null;

    function bindSelectAll() {
        const selectAll = document.getElementById('selectAllRows');
        const rowChecks = document.querySelectorAll('.row-check');

        if (selectAll) {
            selectAll.onchange = function() {
                rowChecks.forEach(function(check) {
                    check.checked = selectAll.checked;
                });
            };
        }
    }

    function bindBulkForm() {
        const bulkForm = document.getElementById('bulkActionForm');
        if (!bulkForm) return;

        bulkForm.onsubmit = function(e) {
            if (bulkForm.dataset.confirmed === '1') {
                bulkForm.dataset.confirmed = '0';
                return true;
            }

            e.preventDefault();

            const selected = document.querySelectorAll('.row-check:checked').length;
            const action = bulkActionSelect ? bulkActionSelect.value : '';

            if (selected === 0) {
                showCustomAlert('Please select at least one user first.');
                return false;
            }

            if (!action) {
                showCustomAlert('Please select an action first.');
                return false;
            }

            let message = '';
            if (action === 'grant_access') {
                message = 'Are you sure you want to grant default module access to the selected user account(s)?';
            } else if (action === 'revoke_access') {
                message = 'Are you sure you want to revoke module access from the selected user account(s)?';
            } else {
                message = 'Are you sure you want to continue?';
            }

            showCustomConfirm(message, function() {
                bulkForm.dataset.confirmed = '1';
                bulkForm.submit();
            });

            return false;
        };
    }

    function rebindTableArea() {
        bindSelectAll();
        bindBulkForm();
    }

    function updateUsers() {
        const params = new URLSearchParams(new FormData(filterForm));
        params.delete('edit');

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

        const currentWrap = document.getElementById('manageUsersTableWrap');
        if (currentWrap) currentWrap.classList.add('loading');

        fetch('manage_users.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContainer = doc.getElementById('manageUsersContainer');

            if (newContainer) {
                const oldContainer = document.getElementById('manageUsersContainer');
                oldContainer.outerHTML = newContainer.outerHTML;
                const qs = params.toString();
                window.history.replaceState({}, '', 'manage_users.php' + (qs ? '?' + qs : ''));
                rebindTableArea();
            }
        })
        .catch(function(error) {
            if (error.name !== 'AbortError') {
                console.error('Manage users live filter error:', error);
            }
        })
        .finally(function() {
            const newWrap = document.getElementById('manageUsersTableWrap');
            if (newWrap) newWrap.classList.remove('loading');
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                updateUsers();
            }
        });
    }

    if (roleFilter) {
        roleFilter.addEventListener('change', updateUsers);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', updateUsers);
    }

    if (alertOk) {
        alertOk.addEventListener('click', closeCustomAlert);
    }

    rebindTableArea();
});

document.addEventListener('click', function(e) {
    const confirmModal = document.getElementById('customConfirmOverlay');
    const alertModal = document.getElementById('customAlertOverlay');

    if (e.target === confirmModal) {
        confirmModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (e.target === alertModal) {
        closeCustomAlert();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCustomAlert();
        const confirmModal = document.getElementById('customConfirmOverlay');
        if (confirmModal) {
            confirmModal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
});
</script>
</body>
</html>