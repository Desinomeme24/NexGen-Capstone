<?php
session_start();
require_once("config.php");
require_once("mailer_config.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pending_requests.php");
    exit();
}

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$remarks   = trim($_POST['remarks'] ?? '');
$adminId   = (int)$_SESSION['user_id'];

if ($requestId <= 0 || $remarks === '') {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Request ID and rejection reason are required.'];
    header("Location: pending_requests.php");
    exit();
}

$reviewedAt = date('Y-m-d H:i:s');

// Fetch request details for the email BEFORE updating
$requestData = null;
$stmtFetch = $conn->prepare("SELECT full_name, email FROM registration_requests WHERE id = ? LIMIT 1");
if ($stmtFetch) {
    $stmtFetch->bind_param("i", $requestId);
    $stmtFetch->execute();
    $resFetch = $stmtFetch->get_result();
    $requestData = $resFetch->fetch_assoc();
    $stmtFetch->close();
}

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

    $description = "Rejected request #{$requestId}";
    logAdminActivitySecure($conn, $adminId, 'reject_request', 'registration_request', $requestId, $description);

    // =========================================================================
    // SEND REJECTION EMAIL
    // =========================================================================
    if ($requestData) {
        try {
            $mail = createMailer();
            $mail->addAddress($requestData['email'], $requestData['full_name']);
            $mail->Subject = 'NexGen Account Registration Rejected';
            $mail->Body =
                "Hello,\n\n" .
                "We regret to inform you that your NexGen account registration has been rejected by the administrator.\n\n" .
                "Reason:\n" . $remarks . "\n\n" .
                "If you believe this was a mistake or need further assistance, please contact the administrator.\n\n" .
                "Thank you.\n" .
                "— NexGen System";

            $mail->send();
        } catch (Exception $mailEx) {
            error_log("Rejection email failed for request #{$requestId}: " . $mailEx->getMessage());
        }
    }
    // =========================================================================

} else {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Failed to reject the request.'];
}

header("Location: pending_requests.php");
exit();
?>