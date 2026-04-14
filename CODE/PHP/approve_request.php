<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pending_requests.php");
    exit();
}

$requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
$remarks   = trim($_POST['remarks'] ?? '');
$adminId   = (int) $_SESSION['user_id'];

if ($requestId <= 0) {
    $_SESSION['error'] = "Invalid request ID.";
    header("Location: pending_requests.php");
    exit();
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception("Prepare failed while reading request: " . $conn->error);
    }

    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $res = $stmt->get_result();
    $request = $res->fetch_assoc();
    $stmt->close();

    if (!$request) {
        throw new Exception("Registration request not found.");
    }

    if (!in_array($request['request_status'], ['pending', 'resubmit'], true)) {
        throw new Exception("Only pending or resubmit requests can be approved.");
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Prepare failed while checking existing user: " . $conn->error);
    }

    $stmt->bind_param("ss", $request['username'], $request['email']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $stmt->close();
        throw new Exception("A user with the same username or email already exists.");
    }
    $stmt->close();

    $requestedRole = $request['requested_role'] ?? 'employee';

    if (!in_array($requestedRole, ['owner', 'employee', 'customer'], true)) {
        $requestedRole = 'employee';
    }

    $role = $requestedRole;
    $approvedAt = date('Y-m-d H:i:s');

    if ($role === 'owner') {
        $canInventory = 1;
        $canSales = 1;
        $canSalesAnalytics = 1;
        $canAccountsReceivable = 1;
    } elseif ($role === 'employee') {
        $canInventory = 1;
        $canSales = 1;
        $canSalesAnalytics = 0;
        $canAccountsReceivable = 1;
    } else {
        $canInventory = 0;
        $canSales = 0;
        $canSalesAnalytics = 0;
        $canAccountsReceivable = 0;
    }

    $stmt = $conn->prepare("
        INSERT INTO users (
            username,
            full_name,
            employee_no,
            email,
            phone,
            address,
            otp_preference,
            password,
            role,
            account_status,
            profile_image,
            valid_id_path,
            is_verified,
            can_inventory,
            can_sales,
            can_sales_analytics,
            can_accounts_receivable,
            approved_by,
            approved_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'email', ?, ?, 'active', 'uploads/default.png', ?, 1, ?, ?, ?, ?, ?, ?
        )
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed while creating approved user: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssiiiiis",
        $request['username'],
        $request['full_name'],
        $request['employee_no'],
        $request['email'],
        $request['phone'],
        $request['address'],
        $request['password_hash'],
        $role,
        $request['valid_id_path'],
        $canInventory,
        $canSales,
        $canSalesAnalytics,
        $canAccountsReceivable,
        $adminId,
        $approvedAt
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to create approved user: " . $stmt->error);
    }

    $newUserId = $stmt->insert_id;
    $stmt->close();

    $finalRemarks = $remarks !== '' ? $remarks : 'Approved by system administrator';

    $stmt = $conn->prepare("
        UPDATE registration_requests
        SET request_status = 'approved',
            admin_remarks = ?,
            reviewed_by = ?,
            reviewed_at = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed while updating request status: " . $conn->error);
    }

    $stmt->bind_param("sisi", $finalRemarks, $adminId, $approvedAt, $requestId);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update request status: " . $stmt->error);
    }
    $stmt->close();

    $action = 'approve_request';
    $targetType = 'registration_request';
    $description = "Approved request #{$requestId}" .
                   (!empty($request['request_code']) ? " ({$request['request_code']})" : "") .
                   " and created user #{$newUserId} with role-based module permissions";

    $stmt = $conn->prepare("
        INSERT INTO admin_logs (admin_id, action, target_type, target_id, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed while writing admin log: " . $conn->error);
    }

    $stmt->bind_param("issis", $adminId, $action, $targetType, $requestId, $description);

    if (!$stmt->execute()) {
        throw new Exception("Failed to write admin log: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    header("Location: pending_requests.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: view_request.php?id=" . $requestId);
    exit();
}
?>