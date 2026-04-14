<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($full_name) || empty($email) || empty($phone)) {
    $_SESSION['error'] = "Name, email, and phone number are required.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("si", $email, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['error'] = "That email is already used by another account.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, otp_preference = 'email' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);

if ($stmt->execute()) {
    $_SESSION['full_name'] = $full_name;
    $_SESSION['success'] = "Account settings updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update account settings.";
}

header("Location: /NexGen/CODE/PHP/settings.php");
exit();
?>