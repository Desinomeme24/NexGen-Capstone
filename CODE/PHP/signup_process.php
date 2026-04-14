<?php
session_start();
include 'config.php';

function generateRequestCode(mysqli $conn): string {
    $prefix = "REQ-" . date("Ymd") . "-";

    $sql = "SELECT request_code
            FROM registration_requests
            WHERE request_code LIKE ?
            ORDER BY id DESC
            LIMIT 1";

    $like = $prefix . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $nextNumber = 1;

    if ($row = $result->fetch_assoc()) {
        $lastCode = $row['request_code'];
        $lastSeq = (int) substr($lastCode, -4);
        $nextNumber = $lastSeq + 1;
    }

    $stmt->close();

    return $prefix . str_pad((string)$nextNumber, 4, "0", STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$employee_no      = trim($_POST['employee_no'] ?? '');
$signup_username  = trim($_POST['signup_username'] ?? '');
$full_name        = trim($_POST['fullname'] ?? '');
$email            = trim($_POST['email'] ?? '');
$phone            = trim($_POST['phone'] ?? '');
$address          = trim($_POST['address'] ?? '');
$requested_role   = trim($_POST['requested_role'] ?? '');
$signup_password  = trim($_POST['signup_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');
$request_code     = generateRequestCode($conn);

if (
    $employee_no === '' || $signup_username === '' || $full_name === '' ||
    $email === '' || $phone === '' || $address === '' ||
    $requested_role === '' || $signup_password === '' || $confirm_password === ''
) {
    $_SESSION['error'] = "Please fill in all fields.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!in_array($requested_role, ['owner', 'employee'], true)) {
    $_SESSION['error'] = "Invalid requested role.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($signup_password !== $confirm_password) {
    $_SESSION['error'] = "Passwords do not match.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email address.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$checkUserSql = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
$checkUserStmt = $conn->prepare($checkUserSql);
$checkUserStmt->bind_param("ss", $signup_username, $email);
$checkUserStmt->execute();
$checkUserStmt->store_result();

if ($checkUserStmt->num_rows > 0) {
    $checkUserStmt->close();
    $_SESSION['error'] = "Username or email already exists.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}
$checkUserStmt->close();

$checkReqSql = "SELECT id FROM registration_requests WHERE username = ? OR email = ? OR employee_no = ? LIMIT 1";
$checkReqStmt = $conn->prepare($checkReqSql);
$checkReqStmt->bind_param("sss", $signup_username, $email, $employee_no);
$checkReqStmt->execute();
$checkReqStmt->store_result();

if ($checkReqStmt->num_rows > 0) {
    $checkReqStmt->close();
    $_SESSION['error'] = "A registration request already exists for this username, email, or employee number.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}
$checkReqStmt->close();

if (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== 0) {
    $_SESSION['error'] = "Please upload your valid ID.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$target_dir = __DIR__ . "/uploads/valid_ids/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$file_name = $_FILES['valid_id']['name'];
$file_tmp  = $_FILES['valid_id']['tmp_name'];
$file_size = $_FILES['valid_id']['size'];
$file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

if (!in_array($file_ext, $allowed, true)) {
    $_SESSION['error'] = "Only JPG, JPEG, PNG, GIF, WEBP, and PDF files are allowed.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($file_size > 5 * 1024 * 1024) {
    $_SESSION['error'] = "Valid ID must not exceed 5MB.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($file_ext !== 'pdf') {
    $file_info = getimagesize($file_tmp);
    if ($file_info === false) {
        $_SESSION['error'] = "Uploaded file is not a valid image.";
        $_SESSION['form_type'] = 'signup';
        header("Location: /NexGen/CODE/PHP/index.php");
        exit();
    }
}

$new_file_name = uniqid("valid_id_", true) . "." . $file_ext;
$target_file = $target_dir . $new_file_name;
$valid_id_path = "uploads/valid_ids/" . $new_file_name;

if (!move_uploaded_file($file_tmp, $target_file)) {
    $_SESSION['error'] = "Failed to upload valid ID.";
    $_SESSION['form_type'] = 'signup';
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$hashed_password = password_hash($signup_password, PASSWORD_DEFAULT);

$insertSql = "INSERT INTO registration_requests (
                request_code,
                employee_no,
                full_name,
                email,
                phone,
                address,
                username,
                password_hash,
                valid_id_path,
                requested_role,
                request_status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param(
    "ssssssssss",
    $request_code,
    $employee_no,
    $full_name,
    $email,
    $phone,
    $address,
    $signup_username,
    $hashed_password,
    $valid_id_path,
    $requested_role
);

if ($insertStmt->execute()) {
    $_SESSION['success'] = "Your account request has been submitted successfully. Request code: {$request_code}. Please wait for admin approval before logging in.";
} else {
    $_SESSION['error'] = "Failed to submit registration request: " . $insertStmt->error;
    $_SESSION['form_type'] = 'signup';
}

$insertStmt->close();
header("Location: /NexGen/CODE/PHP/index.php");
exit();
?>