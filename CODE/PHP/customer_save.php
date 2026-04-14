<?php
session_start();
require_once("config.php");

function isAjaxRequest() {
    return (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    );
}

function sendJson($success, $message, $extra = []) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit();
}

if (!isset($_SESSION['user_id'])) {
    if (isAjaxRequest()) {
        sendJson(false, 'Session expired. Please log in again.');
    }

    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'employee'], true)) {
    if (isAjaxRequest()) {
        sendJson(false, 'Unauthorized access.');
    }

    $_SESSION['error'] = 'Unauthorized access.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isAjaxRequest()) {
        sendJson(false, 'Invalid request method.');
    }

    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

$customer_name = trim($_POST['customer_name'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$address       = trim($_POST['address'] ?? '');
$payment_status_context = trim($_POST['payment_status_context'] ?? 'Paid');

if ($customer_name === '') {
    if (isAjaxRequest()) {
        sendJson(false, 'Customer name is required.');
    }

    $_SESSION['error'] = 'Customer name is required.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

if (in_array($payment_status_context, ['Unpaid', 'Partially Paid'], true)) {
    if ($phone === '' || $email === '' || $address === '') {
        if (isAjaxRequest()) {
            sendJson(false, 'Phone, email, and address are required for unpaid or partially paid customers.');
        }

        $_SESSION['error'] = 'Phone, email, and address are required for unpaid or partially paid customers.';
        header("Location: /NexGen/CODE/PHP/sales_recording.php");
        exit();
    }
}

function generateCustomerCode($conn) {
    $prefix = 'CUS-' . date('Ymd');

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM customers
        WHERE customer_code LIKE CONCAT(?, '%')
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $stmt->close();

    $next = ((int)($row['total'] ?? 0)) + 1;
    return $prefix . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

$customer_code = generateCustomerCode($conn);

if ($customer_code === false) {
    if (isAjaxRequest()) {
        sendJson(false, 'Failed to generate customer code.');
    }

    $_SESSION['error'] = 'Failed to generate customer code.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO customers (
        customer_code,
        customer_name,
        phone,
        email,
        address,
        status
    ) VALUES (?, ?, ?, ?, ?, 1)
");

if (!$stmt) {
    if (isAjaxRequest()) {
        sendJson(false, 'Failed to prepare save query.');
    }

    $_SESSION['error'] = 'Failed to prepare save query.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

$stmt->bind_param("sssss", $customer_code, $customer_name, $phone, $email, $address);

if ($stmt->execute()) {
    $customer_id = $conn->insert_id;
    $stmt->close();

    if (isAjaxRequest()) {
        sendJson(true, 'Customer added successfully.', [
            'customer' => [
                'id' => $customer_id,
                'customer_code' => $customer_code,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ]
        ]);
    }

    $_SESSION['success'] = 'Customer added successfully.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
} else {
    $error = $stmt->error ?: 'Failed to add customer.';
    $stmt->close();

    if (isAjaxRequest()) {
        sendJson(false, $error);
    }

    $_SESSION['error'] = $error;
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}
?>