<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/resend_config.php';

$fpPortal = nxNormalizeLoginPortal(
    $_POST['portal']
        ?? $_GET['portal']
        ?? $_SESSION['fp_portal']
        ?? $_SESSION['login_portal']
        ?? 'client'
);
$_SESSION['fp_portal'] = $fpPortal;
$forgotStartPage = 'forgot_password.php?portal=' . rawurlencode($fpPortal);

date_default_timezone_set('Asia/Manila');

const NX_FP_OTP_TTL_SECONDS = 600;           // 10 minutes
const NX_FP_OTP_RESEND_COOLDOWN_SECONDS = 60;

/* AJAX callers (the in-modal forgot-password wizard on index.php) send this
   header and get a JSON reply instead of the classic flash+redirect. */
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function forgotPasswordRedirect(
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
    header('Location: ' . nxAppUrl($page));
    exit();
}

/*
|--------------------------------------------------------------------------
| RESOLVE WHICH ACCOUNT THIS OTP IS FOR
|--------------------------------------------------------------------------
| Reached three ways: automatically after a single-match email lookup,
| after the user picks one account on forgot_password_select.php (posts
| selected_user_id, which must be one of the candidates that lookup step
| actually found), or via the "Resend OTP" button on reset_password.php
| (no new POST data - reuses the account already resolved in session).
*/

if (isset($_POST['selected_user_id'])) {
    $candidates = $_SESSION['fp_candidates'] ?? [];
    $selectedId = (int) $_POST['selected_user_id'];

    if (!isset($candidates[$selectedId])) {
        forgotPasswordRedirect('That selection is no longer valid. Please start again.', 'error', $forgotStartPage, ['restart' => true]);
    }

    $_SESSION['fp_selected_user_id'] = $selectedId;
    unset($_SESSION['fp_candidates']);
}

$userId = isset($_SESSION['fp_selected_user_id']) ? (int) $_SESSION['fp_selected_user_id'] : 0;

if ($userId <= 0) {
    forgotPasswordRedirect('Please start the password reset process again.', 'error', $forgotStartPage, ['restart' => true]);
}

/*
|--------------------------------------------------------------------------
| LOAD ACCOUNT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, email, full_name, role, otp_expires_at
     FROM users
     WHERE id = ? AND account_status = 'active'
     LIMIT 1"
);

if (!$stmt) {
    error_log('Forgot password user lookup prepare error: ' . $conn->error);
    forgotPasswordRedirect(
        'Unable to process your request right now. Please try again.',
        'error',
        'reset_password.php'
    );
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    unset($_SESSION['fp_selected_user_id']);
    forgotPasswordRedirect('Account not found. Please start again.', 'error', $forgotStartPage, ['restart' => true]);
}

/* Never let an account selected in one portal cross into the other portal's
   recovery flow, even if session state or POST data is altered. */
if (!nxRoleMatchesLoginPortal((string)($user['role'] ?? ''), $fpPortal)) {
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_candidates'], $_SESSION['fp_email']);
    forgotPasswordRedirect('Account not found. Please start again.', 'error', $forgotStartPage, ['restart' => true]);
}

/*
|--------------------------------------------------------------------------
| RESEND COOLDOWN
|--------------------------------------------------------------------------
| otp_expires_at is always set NX_FP_OTP_TTL_SECONDS after an OTP is sent,
| so the last-send time can be derived from it without a separate column.
*/

if (!empty($user['otp_expires_at'])) {
    $lastSentAt = strtotime($user['otp_expires_at']) - NX_FP_OTP_TTL_SECONDS;
    $secondsSinceSend = time() - $lastSentAt;

    if ($secondsSinceSend < NX_FP_OTP_RESEND_COOLDOWN_SECONDS) {
        $wait = NX_FP_OTP_RESEND_COOLDOWN_SECONDS - $secondsSinceSend;
        forgotPasswordRedirect(
            "Please wait {$wait} second" . ($wait === 1 ? '' : 's') . " before requesting another OTP.",
            'error',
            'reset_password.php',
            ['cooldown_remaining' => $wait]
        );
    }
}

/*
|--------------------------------------------------------------------------
| GENERATE OTP
|--------------------------------------------------------------------------
*/

$otpCode = str_pad(
    (string) random_int(0, 999999),
    6,
    '0',
    STR_PAD_LEFT
);

$otpExpiresAt = date('Y-m-d H:i:s', time() + NX_FP_OTP_TTL_SECONDS);

$recipientName = trim((string) ($user['full_name'] ?? ''));

if ($recipientName === '') {
    $recipientName = 'NexGen User';
}

$emailBody =
    "Hello {$recipientName},\n\n" .
    "Your NexGen password reset OTP is: {$otpCode}\n\n" .
    "This OTP will expire in 10 minutes.\n\n" .
    "If you did not request a password reset, you can ignore this email.";


/*
|--------------------------------------------------------------------------
| SEND THROUGH RESEND HTTPS API
|--------------------------------------------------------------------------
*/

try {
    nxSendResendEmail(
        (string)$user['email'],
        $recipientName,
        'NexGen Password Reset OTP',
        $emailBody
    );
} catch (Throwable $e) {
    error_log(
        'Forgot password Resend error for user ID ' .
        (int) $user['id'] .
        ': ' .
        $e->getMessage()
    );

    forgotPasswordRedirect(
        "We couldn't send the OTP right now. Please try again in a moment.",
        'error',
        'reset_password.php'
    );
}


/*
|--------------------------------------------------------------------------
| STORE OTP ONLY AFTER EMAIL SENT SUCCESSFULLY
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare(
    "UPDATE users
     SET otp_code = ?,
         otp_expires_at = ?,
         otp_preference = 'email'
     WHERE id = ?"
);

if (!$updateStmt) {
    error_log(
        'Forgot password OTP update prepare error: ' . $conn->error
    );

    forgotPasswordRedirect(
        'The email was sent, but the reset request could not be saved. Please request a new OTP.',
        'error',
        'reset_password.php'
    );
}

$updateStmt->bind_param(
    'ssi',
    $otpCode,
    $otpExpiresAt,
    $userId
);

if (!$updateStmt->execute()) {
    error_log(
        'Forgot password OTP update error: ' .
        $updateStmt->error
    );

    $updateStmt->close();

    forgotPasswordRedirect(
        'The email was sent, but the reset request could not be saved. Please request a new OTP.',
        'error',
        'reset_password.php'
    );
}

$updateStmt->close();

$_SESSION['fp_email'] = (string) $user['email'];
$_SESSION['success'] = 'OTP sent to your email address.';

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'message' => 'OTP sent to your email address.',
        'masked_email' => nxMaskEmail((string) $user['email']),
        'cooldown' => NX_FP_OTP_RESEND_COOLDOWN_SECONDS,
    ]);
    exit();
}

header('Location: ' . nxAppUrl('reset_password.php'));
exit();
