<?php
require_once 'config.php';
require_once 'mailer_config.php';

/*
 * NexGen registration approval handler.
 * Revalidates the administrator against the database so an older session
 * still using admin ID 0 can recover after the database admin ID was moved to 1.
 */

if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'system_admin') {
    $_SESSION['error'] = 'Your administrator session is no longer valid. Please log in again.';
    $_SESSION['form_type'] = 'login';
    header('Location: /NexGen/CODE/PHP/index.php?open=login');
    exit();
}

enforceSessionTimeout();

/* Revalidate and repair a stale administrator ID stored in the session. */
$sessionAdminId = (int) $_SESSION['user_id'];
$sessionUsername = trim((string) $_SESSION['username']);

$adminStmt = $conn->prepare(
    "SELECT id, username, role, account_status
     FROM users
     WHERE (id = ? OR username = ?)
       AND role = 'system_admin'
     LIMIT 1"
);

if (!$adminStmt) {
    $_SESSION['error'] = 'Unable to verify the administrator account: ' . $conn->error;
    header('Location: /NexGen/CODE/PHP/pending_requests.php');
    exit();
}

$adminStmt->bind_param('is', $sessionAdminId, $sessionUsername);
$adminStmt->execute();
$adminAccount = $adminStmt->get_result()->fetch_assoc();
$adminStmt->close();

if (!$adminAccount || $adminAccount['account_status'] !== 'active') {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['error'] = 'Your administrator account could not be verified. Please log in again.';
    $_SESSION['form_type'] = 'login';
    header('Location: /NexGen/CODE/PHP/index.php?open=login');
    exit();
}

/* Synchronize an old session value, such as user_id 0, with the real DB ID. */
$adminId = (int) $adminAccount['id'];
$_SESSION['user_id'] = $adminId;
$_SESSION['username'] = $adminAccount['username'];
$_SESSION['role'] = $adminAccount['role'];
$_SESSION['account_status'] = $adminAccount['account_status'];
$_SESSION['last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /NexGen/CODE/PHP/pending_requests.php');
    exit();
}

if (!validateCsrfToken('admin_request_action', $_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = 'Your session expired. Please try again.';
    header('Location: /NexGen/CODE/PHP/pending_requests.php');
    exit();
}

$requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
$remarks = trim((string) ($_POST['remarks'] ?? ''));

if ($requestId <= 0) {
    $_SESSION['error'] = 'Invalid registration request ID.';
    header('Location: /NexGen/CODE/PHP/pending_requests.php');
    exit();
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        "SELECT * FROM registration_requests WHERE id = ? FOR UPDATE"
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to read the request: ' . $conn->error);
    }

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        throw new RuntimeException('Registration request not found.');
    }

    if (!in_array($request['request_status'], ['pending', 'resubmit'], true)) {
        throw new RuntimeException('Only pending or resubmitted requests can be approved.');
    }

    $stmt = $conn->prepare(
        'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to check existing accounts: ' . $conn->error);
    }

    $stmt->bind_param('ss', $request['username'], $request['email']);
    $stmt->execute();
    $alreadyExists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($alreadyExists) {
        throw new RuntimeException('A user with the same username or email already exists.');
    }

    $role = (string) ($request['requested_role'] ?? 'employee');
    if (!in_array($role, ['owner', 'employee'], true)) {
        throw new RuntimeException('The requested role is invalid.');
    }

    $businessId = null;
    $businessCode = strtoupper(trim((string) ($request['business_code'] ?? '')));

    if ($role === 'owner') {
        if (trim((string) $request['business_name']) === '' ||
            trim((string) $request['business_type']) === '' ||
            trim((string) $request['business_address']) === '') {
            throw new RuntimeException('The owner request has incomplete SME business information.');
        }

        do {
            $businessCode = 'SME-' . strtoupper(bin2hex(random_bytes(3)));
            $codeStmt = $conn->prepare(
                'SELECT id FROM businesses WHERE business_code = ? LIMIT 1'
            );
            if (!$codeStmt) {
                throw new RuntimeException('Unable to generate a business code: ' . $conn->error);
            }
            $codeStmt->bind_param('s', $businessCode);
            $codeStmt->execute();
            $codeExists = $codeStmt->get_result()->num_rows > 0;
            $codeStmt->close();
        } while ($codeExists);

        $stmt = $conn->prepare(
            'INSERT INTO businesses
                (business_name, business_type, business_address, business_code)
             VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to create the SME business: ' . $conn->error);
        }

        $stmt->bind_param(
            'ssss',
            $request['business_name'],
            $request['business_type'],
            $request['business_address'],
            $businessCode
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to create the SME business: ' . $stmt->error);
        }

        $businessId = (int) $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            'UPDATE registration_requests SET business_code = ? WHERE id = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to save the generated business code: ' . $conn->error);
        }
        $stmt->bind_param('si', $businessCode, $requestId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to save the generated business code: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        if ($businessCode === '') {
            throw new RuntimeException('The employee request has no SME business code.');
        }

        $stmt = $conn->prepare(
            'SELECT id FROM businesses WHERE business_code = ? LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to verify the SME business code: ' . $conn->error);
        }
        $stmt->bind_param('s', $businessCode);
        $stmt->execute();
        $business = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$business) {
            throw new RuntimeException('The SME business code linked to this employee no longer exists.');
        }

        $businessId = (int) $business['id'];
    }

    $canInventory = 1;
    $canSales = 1;
    $canSalesAnalytics = $role === 'owner' ? 1 : 0;
    $canAccountsReceivable = 1;
    $approvedAt = date('Y-m-d H:i:s');
    $employeeNo = trim((string) ($request['employee_no'] ?? ''));
    $employeeNoForDb = $employeeNo !== '' ? $employeeNo : null;

    $stmt = $conn->prepare(
        "INSERT INTO users (
            business_id, username, full_name, employee_no, email, phone, address,
            otp_preference, password, role, account_status, profile_image,
            valid_id_path, is_verified, can_inventory, can_sales,
            can_sales_analytics, can_accounts_receivable, approved_by, approved_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 'email', ?, ?, 'active',
            'uploads/default.png', ?, 1, ?, ?, ?, ?, ?, ?
        )"
    );

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the approved user account: ' . $conn->error);
    }

    $stmt->bind_param(
        'isssssssssiiiiis',
        $businessId,
        $request['username'],
        $request['full_name'],
        $employeeNoForDb,
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
        throw new RuntimeException('Failed to create the approved user: ' . $stmt->error);
    }

    $newUserId = (int) $stmt->insert_id;
    $stmt->close();

    $finalRemarks = $remarks !== ''
        ? $remarks
        : 'Approved by system administrator';

    $stmt = $conn->prepare(
        "UPDATE registration_requests
         SET request_status = 'approved',
             admin_remarks = ?,
             reviewed_by = ?,
             reviewed_at = ?
         WHERE id = ? AND request_status IN ('pending', 'resubmit')"
    );

    if (!$stmt) {
        throw new RuntimeException('Unable to update the request status: ' . $conn->error);
    }

    $stmt->bind_param('sisi', $finalRemarks, $adminId, $approvedAt, $requestId);

    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update the request status: ' . $stmt->error);
    }

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('The request status was not updated. It may have already been processed.');
    }
    $stmt->close();

    $description = "Approved request #{$requestId}" .
        (!empty($request['request_code']) ? " ({$request['request_code']})" : '') .
        " and created user #{$newUserId}";

    if (!logAdminActivitySecure(
        $conn,
        $adminId,
        'approve_request',
        'registration_request',
        $requestId,
        $description
    )) {
        throw new RuntimeException('Failed to save the administrator activity log.');
    }

    $conn->commit();

    $_SESSION['success'] = 'Registration request approved successfully.';
    $_SESSION['last_activity'] = time();

    /* Email errors must never reverse or hide the successful approval. */
    try {
        $mail = createMailer();
        $mail->addAddress($request['email'], $request['full_name']);
        $mail->Subject = 'NexGen Account Registration Approved';
        $mail->Body =
            "Hello,\n\n" .
            "Your NexGen account registration has been approved.\n" .
            ($role === 'owner'
                ? "Your SME business code is: {$businessCode}\n"
                : "Your account is linked to SME code: {$businessCode}\n") .
            "You may now log in using your registered username and password.\n\n" .
            "Thank you.\n— NexGen System";
        $mail->send();
    } catch (Throwable $mailError) {
        error_log(
            "Approval email failed for request #{$requestId}: " .
            $mailError->getMessage()
        );
    }

    header('Location: /NexGen/CODE/PHP/pending_requests.php');
    exit();
} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        "Approval failed for request #{$requestId}, admin #{$adminId}: " .
        $e->getMessage()
    );

    $_SESSION['error'] = 'Approval failed: ' . $e->getMessage();
    $_SESSION['last_activity'] = time();
    header('Location: /NexGen/CODE/PHP/view_request.php?id=' . $requestId);
    exit();
}