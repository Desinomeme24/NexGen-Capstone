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
    $_SESSION['flash'] = [
        'type'    => 'notice-error',
        'message' => 'Request ID and correction instructions are required.'
    ];
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
    SET request_status = 'resubmit',
        admin_remarks = ?,
        reviewed_by = ?,
        reviewed_at = ?
    WHERE id = ?
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

if ($stmt->execute()) {
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
        'message' => 'Failed to update the request.'
    ];
}

header("Location: pending_requests.php");
exit();
?>