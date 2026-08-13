<?php
require_once 'config.php';

function signupRedirect(string $message, string $type = 'error'): void
{
    $_SESSION[$type] = $message;
    if ($type === 'error') {
        $_SESSION['form_type'] = 'signup';
    }
    header('Location: /NexGen/CODE/PHP/index.php?open=signup');
    exit();
}

function generateRequestCode(mysqli $conn): string
{
    $prefix = 'REQ-' . date('Ymd') . '-';
    $stmt = $conn->prepare(
        'SELECT request_code FROM registration_requests WHERE request_code LIKE ? ORDER BY id DESC LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to generate request code: ' . $conn->error);
    }

    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $nextNumber = 1;
    if ($row && !empty($row['request_code'])) {
        $nextNumber = ((int)substr($row['request_code'], -4)) + 1;
    }

    return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    signupRedirect('Invalid request.');
}

if (!validateCsrfToken('signup_form', $_POST['csrf_token'] ?? '')) {
    signupRedirect('Invalid or expired signup form token. Please reopen the signup form and try again.');
}

$captchaSelection = $_POST['captcha_selection'] ?? [];
if (!validateImageCaptchaSelection('signup_form', (array)$captchaSelection)) {
    signupRedirect('Please complete the image captcha correctly.');
}

if (!isset($_POST['privacy_consent'])) {
    signupRedirect('You must agree to the Privacy Policy before submitting your account request.');
}

$username        = trim($_POST['signup_username'] ?? '');
$fullName        = trim($_POST['fullname'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$address         = trim($_POST['address'] ?? '');
$requestedRole   = trim($_POST['requested_role'] ?? '');
$password        = (string)($_POST['signup_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

$businessName    = trim($_POST['business_name'] ?? '');
$businessType    = trim($_POST['business_type'] ?? '');
$businessAddress = trim($_POST['business_address'] ?? '');
$businessCode    = strtoupper(trim($_POST['business_code'] ?? ''));
$employeeNo      = trim($_POST['employee_no'] ?? '');
$businessId      = null;

if ($username === '' || $fullName === '' || $email === '' || $phone === '' ||
    $address === '' || $requestedRole === '' || $password === '' || $confirmPassword === '') {
    signupRedirect('Please fill in all required account fields.');
}

if (!in_array($requestedRole, ['owner', 'employee'], true)) {
    signupRedirect('Invalid requested role.');
}

if ($requestedRole === 'owner') {
    if ($businessName === '' || $businessType === '' || $businessAddress === '') {
        signupRedirect('SME owners must provide the business name, business type, and business address.');
    }

    $allowedBusinessTypes = [
        'Hardware / Construction Supplies',
        'Mini Grocery / Sari-Sari Store',
        'Pharmacy / Drugstore',
        'School / Office Supplies',
    ];
    if (!in_array($businessType, $allowedBusinessTypes, true)) {
        signupRedirect('Please select a valid business type.');
    }

    // The owner's business does not exist yet, so business_id remains NULL
    // until the system administrator approves the request and creates the business.
    $businessId = null;
    $businessCode = '';
    $employeeNo = '';
} else {
    if ($businessCode === '' || $employeeNo === '') {
        signupRedirect('Employees must provide the SME business code and employee number.');
    }

    $businessStmt = $conn->prepare(
        'SELECT id, business_name, business_type, business_address
         FROM businesses
         WHERE business_code = ?
         LIMIT 1'
    );
    if (!$businessStmt) {
        signupRedirect('Unable to verify the SME business code: ' . $conn->error);
    }
    $businessStmt->bind_param('s', $businessCode);
    $businessStmt->execute();
    $business = $businessStmt->get_result()->fetch_assoc();
    $businessStmt->close();

    if (!$business) {
        signupRedirect('The SME business code was not found. Please ask the business owner for the correct code.');
    }

    $businessId = (int)$business['id'];
    $businessName = (string)$business['business_name'];
    $businessType = (string)$business['business_type'];
    $businessAddress = (string)$business['business_address'];
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    signupRedirect('Please enter a valid email address.');
}

if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    signupRedirect('Username must be 3 to 50 characters and may contain letters, numbers, dots, underscores, and hyphens.');
}

if (!isStrongPassword($password)) {
    signupRedirect('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.');
}

if ($password !== $confirmPassword) {
    signupRedirect('Passwords do not match.');
}

$checkUser = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
if (!$checkUser) {
    signupRedirect('Unable to check existing accounts: ' . $conn->error);
}
$checkUser->bind_param('ss', $username, $email);
$checkUser->execute();
$checkUser->store_result();
if ($checkUser->num_rows > 0) {
    $checkUser->close();
    signupRedirect('That username or email is already used by an existing account.');
}
$checkUser->close();

/*
|--------------------------------------------------------------------------
| CHECK ACTIVE REGISTRATION REQUESTS
|--------------------------------------------------------------------------
| Username and email are globally unique.
| Employee numbers are unique only within the same business.
| Approved requests are not checked here because the users table above
| already protects usernames/emails of approved accounts.
*/
if ($requestedRole === 'employee') {
    $checkRequest = $conn->prepare(
        "SELECT id
         FROM registration_requests
         WHERE request_status IN ('pending', 'resubmit')
           AND (
                username = ?
                OR email = ?
                OR (
                    requested_role = 'employee'
                    AND business_id = ?
                    AND employee_no = ?
                )
           )
         LIMIT 1"
    );

    if (!$checkRequest) {
        signupRedirect('Unable to check existing registration requests: ' . $conn->error);
    }

    $checkRequest->bind_param('ssis', $username, $email, $businessId, $employeeNo);
} else {
    $checkRequest = $conn->prepare(
        "SELECT id
         FROM registration_requests
         WHERE request_status IN ('pending', 'resubmit')
           AND (username = ? OR email = ?)
         LIMIT 1"
    );

    if (!$checkRequest) {
        signupRedirect('Unable to check existing registration requests: ' . $conn->error);
    }

    $checkRequest->bind_param('ss', $username, $email);
}

$checkRequest->execute();
$checkRequest->store_result();

if ($checkRequest->num_rows > 0) {
    $checkRequest->close();

    if ($requestedRole === 'employee') {
        signupRedirect(
            'A pending registration request already exists for this username, email, or employee number within this SME.'
        );
    }

    signupRedirect(
        'A pending registration request already exists for this username or email.'
    );
}

$checkRequest->close();

if (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
    signupRedirect('Please upload a valid ID or proof of employment.');
}

$validIdFile = $_FILES['valid_id'];
[$isValidUpload, $scanResult] = nxValidateSecureUpload($validIdFile, [
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
    'allowed_mime_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'
    ],
    'max_size' => 5 * 1024 * 1024,
    'require_image' => false,
    'allow_pdf' => true
]);

if (!$isValidUpload) {
    signupRedirect('Valid ID upload blocked: ' . $scanResult);
}

$targetDir = __DIR__ . '/uploads/valid_ids/';
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    signupRedirect('The valid-ID upload folder could not be created.');
}

$extension = strtolower(pathinfo($validIdFile['name'], PATHINFO_EXTENSION));
$newFileName = 'valid_id_' . bin2hex(random_bytes(12)) . '.' . $extension;
$targetFile = $targetDir . $newFileName;
$validIdPath = 'uploads/valid_ids/' . $newFileName;

if (!move_uploaded_file($validIdFile['tmp_name'], $targetFile)) {
    signupRedirect('Failed to save the uploaded valid ID.');
}

try {
    $requestCode = generateRequestCode($conn);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $employeeNoForDb = $employeeNo !== '' ? $employeeNo : null;
    $businessCodeForDb = $businessCode !== '' ? $businessCode : null;
    $businessIdForDb = $requestedRole === 'employee' ? $businessId : null;

    $insert = $conn->prepare(
        "INSERT INTO registration_requests (
            request_code, employee_no, full_name, email, phone, address,
            username, password_hash, valid_id_path, requested_role,
            request_status, business_name, business_type, business_address,
            business_code, business_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)"
    );

    if (!$insert) {
        throw new RuntimeException('Unable to prepare registration request: ' . $conn->error);
    }

    $insert->bind_param(
        'ssssssssssssssi',
        $requestCode,
        $employeeNoForDb,
        $fullName,
        $email,
        $phone,
        $address,
        $username,
        $hashedPassword,
        $validIdPath,
        $requestedRole,
        $businessName,
        $businessType,
        $businessAddress,
        $businessCodeForDb,
        $businessIdForDb
    );

    if (!$insert->execute()) {
        throw new RuntimeException('Failed to submit registration request: ' . $insert->error);
    }
    $insert->close();

    signupRedirect(
        "Your account request was submitted successfully. Request code: {$requestCode}. Please wait for administrator approval.",
        'success'
    );
} catch (Throwable $e) {
    if (is_file($targetFile)) {
        @unlink($targetFile);
    }
    signupRedirect($e->getMessage());
}
?>