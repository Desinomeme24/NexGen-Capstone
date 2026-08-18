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

if (!validateCsrfToken('admin_request_action', $_POST['csrf_token'] ?? null)) {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Your session expired. Please try again.'];
    header("Location: pending_requests.php");
    exit();
}

$requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$remarks   = trim($_POST['remarks'] ?? '');
$adminId   = (int)$_SESSION['user_id'];

if ($requestId <= 0 || $remarks === '') {
    $_SESSION['flash'] = [
        'type'    => 'notice-error',
        'message' => 'Request ID and correction instructions are required.'
    ];
    header("Location: pending_requests.php");
    exit();
}

$reviewedAt = date('Y-m-d H:i:s');

// Fetch the request before updating. Only a currently pending request may be
// sent for resubmission; approved/rejected requests are final, and an existing
// resubmit request must first be corrected by the applicant.
$requestData = null;
$stmtFetch = $conn->prepare("SELECT full_name, email, request_status FROM registration_requests WHERE id = ? LIMIT 1");
if (!$stmtFetch) {
    $_SESSION['flash'] = [
        'type' => 'notice-error',
        'message' => 'Failed to read the registration request.'
    ];
    header("Location: pending_requests.php");
    exit();
}

$stmtFetch->bind_param("i", $requestId);
$stmtFetch->execute();
$resFetch = $stmtFetch->get_result();
$requestData = $resFetch->fetch_assoc();
$stmtFetch->close();

if (!$requestData) {
    $_SESSION['flash'] = [
        'type' => 'notice-error',
        'message' => 'Registration request not found.'
    ];
    header("Location: pending_requests.php");
    exit();
}

if ($requestData['request_status'] !== 'pending') {
    $_SESSION['flash'] = [
        'type' => 'notice-error',
        'message' => 'Only pending requests can be sent for resubmission.'
    ];
    header("Location: view_request.php?id=" . $requestId);
    exit();
}

$stmt = $conn->prepare("
    UPDATE registration_requests
    SET request_status = 'resubmit',
        admin_remarks = ?,
        reviewed_by = ?,
        reviewed_at = ?
    WHERE id = ?
      AND request_status = 'pending'
");

if (!$stmt) {
    $_SESSION['flash'] = [
        'type'    => 'notice-error',
        'message' => 'Failed to prepare the resubmit request update.'
    ];
    header("Location: pending_requests.php");
    exit();
}

$stmt->bind_param("sisi", $remarks, $adminId, $reviewedAt, $requestId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $stmt->close();

    $description = "Marked request #{$requestId} for resubmission";
    logAdminActivitySecure($conn, $adminId, 'resubmit_request', 'registration_request', $requestId, $description);

    // =========================================================================
    // SEND RESUBMIT EMAIL
    // =========================================================================
    if ($requestData) {
        try {
            $mail = createMailer();
            $mail->addAddress($requestData['email'], $requestData['full_name']);
            $mail->Subject = 'NexGen Account Registration — Action Required';
            $mail->Body =
                "Hello,\n\n" .
                "Your NexGen account registration requires corrections before it can be approved.\n\n" .
                "Instructions from the administrator:\n" . $remarks . "\n\n" .
                "Please log in to the registration portal and resubmit your application with the requested changes.\n\n" .
                "Thank you.\n" .
                "— NexGen System";

            $mail->send();
        } catch (Exception $mailEx) {
            error_log("Resubmit email failed for request #{$requestId}: " . $mailEx->getMessage());
        }
    }
    // =========================================================================

    $_SESSION['flash'] = [
        'type'    => 'notice-success',
        'message' => 'Request marked for resubmission successfully.'
    ];
} else {
    $stmt->close();

    $_SESSION['flash'] = [
        'type'    => 'notice-error',
        'message' => 'The request was not sent for resubmission. Its status may have already been changed.'
    ];
}

header("Location: pending_requests.php");
exit();
?>