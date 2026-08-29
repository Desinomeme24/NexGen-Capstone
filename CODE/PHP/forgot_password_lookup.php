<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Manila');

/* AJAX callers (the in-modal forgot-password wizard on index.php) send this
   header and get a JSON reply instead of the classic flash+redirect, so the
   same lookup logic can drive both the standalone pages and the modal. */
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function fpLookupRedirect(
    string $message,
    string $type = 'error',
    string $page = 'forgot_password.php',
    array $ajaxExtra = []
): void {
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(
            ['success' => $type !== 'error', 'message' => $message],
            $ajaxExtra
        ));
        exit();
    }

    $_SESSION[$type] = $message;
    header('Location: /NexGen/CODE/PHP/' . $page);
    exit();
}

/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fpLookupRedirect('Invalid request.');
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fpLookupRedirect('Please enter a valid email address.');
}

/* Start each lookup clean - do not carry over a previous attempt's account. */
unset($_SESSION['fp_candidates'], $_SESSION['fp_selected_user_id'], $_SESSION['fp_email']);

/*
|--------------------------------------------------------------------------
| FIND ALL ACCOUNTS UNDER THIS EMAIL
|--------------------------------------------------------------------------
| An SME owner can have several branch accounts registered under one
| Gmail, so this can legitimately match more than one row.
*/

$stmt = $conn->prepare(
    "SELECT u.id, u.username, b.business_name
     FROM users u
     LEFT JOIN businesses b ON b.id = u.business_id
     WHERE u.email = ? AND u.account_status = 'active'"
);

if (!$stmt) {
    error_log('Forgot password lookup prepare error: ' . $conn->error);
    fpLookupRedirect('Unable to process your request right now. Please try again.');
}

$stmt->bind_param('s', $email);
$stmt->execute();
$accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Same neutral message whether the email exists or not - do not reveal it. */
if (count($accounts) === 0) {
    fpLookupRedirect(
        'If an account matches that email, an OTP has been sent to it.',
        'success',
        'forgot_password.php',
        ['matched' => 0]
    );
}

if (count($accounts) === 1) {
    $_SESSION['fp_selected_user_id'] = (int) $accounts[0]['id'];

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'matched' => 1]);
        exit();
    }

    header('Location: /NexGen/CODE/PHP/send_forgot_otp.php');
    exit();
}

/* More than one account shares this email - let the user pick which one. */
$candidates = [];
foreach ($accounts as $account) {
    $candidates[(int) $account['id']] = [
        'id' => (int) $account['id'],
        'masked_username' => nxMaskUsername((string) $account['username']),
        'business_name' => (string) ($account['business_name'] ?? ''),
    ];
}

$_SESSION['fp_candidates'] = $candidates;

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'matched' => count($candidates),
        'candidates' => array_values($candidates),
    ]);
    exit();
}

header('Location: /NexGen/CODE/PHP/forgot_password_select.php');
exit();