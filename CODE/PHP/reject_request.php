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

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$remarks = trim($_POST['remarks'] ?? '');
$adminId = (int)$_SESSION['user_id'];

if ($requestId <= 0 || $remarks === '') {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Request ID and rejection reason are required.'];
    header("Location: pending_requests.php");
    exit();
}

$reviewedAt = date('Y-m-d H:i:s');

$stmt = $conn->prepare("
    UPDATE registration_requests
    SET request_status = 'rejected',
        admin_remarks = ?,
        reviewed_by = ?,
        reviewed_at = ?
    WHERE id = ?
");
$stmt->bind_param("sisi", $remarks, $adminId, $reviewedAt, $requestId);

if ($stmt->execute()) {
    $stmt->close();

    $action = 'reject_request';
    $targetType = 'registration_request';
    $description = "Rejected request #{$requestId}";
    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, description) VALUES (?, ?, ?, ?, ?)");
    $log->bind_param("issis", $adminId, $action, $targetType, $requestId, $description);
    $log->execute();
    $log->close();
} else {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Failed to reject the request.'];
}

header("Location: pending_requests.php");
exit();
?>