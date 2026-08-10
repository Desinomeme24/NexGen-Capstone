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

// Fetch the request before updating so we can validate its current status
// and use the applicant details for the notification email.
$requestData = null;
$stmtFetch = $conn->prepare("SELECT full_name, email, request_status FROM registration_requests WHERE id = ? LIMIT 1");
if (!$stmtFetch) {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Failed to read the registration request.'];
    header("Location: pending_requests.php");
    exit();
}

$stmtFetch->bind_param("i", $requestId);
$stmtFetch->execute();
$resFetch = $stmtFetch->get_result();
$requestData = $resFetch->fetch_assoc();
$stmtFetch->close();

if (!$requestData) {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Registration request not found.'];
    header("Location: pending_requests.php");
    exit();
}

if (!in_array($requestData['request_status'], ['pending', 'resubmit'], true)) {
    $_SESSION['flash'] = [
        'type' => 'notice-error',
        'message' => 'This request is already finalized and can no longer be rejected.'
    ];
    header("Location: view_request.php?id=" . $requestId);
    exit();
}

$stmt = $conn->prepare("
    UPDATE registration_requests
    SET request_status = 'rejected',
        admin_remarks = ?,
        reviewed_by = ?,
        reviewed_at = ?
    WHERE id = ?
      AND request_status IN ('pending', 'resubmit')
");

if (!$stmt) {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Failed to prepare the rejection update.'];
    header("Location: pending_requests.php");
    exit();
}

$stmt->bind_param("sisi", $remarks, $adminId, $reviewedAt, $requestId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
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
    $stmt->close();
    $_SESSION['flash'] = [
        'type' => 'notice-error',
        'message' => 'The request was not rejected. Its status may have already been changed or finalized.'
    ];
}

header("Location: pending_requests.php");
exit();
?>