<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

if (!isset($_FILES['new_profile_image']) || $_FILES['new_profile_image']['error'] !== 0) {
    $_SESSION['error'] = "Please select an image first.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$file_name = $_FILES['new_profile_image']['name'];
$file_tmp = $_FILES['new_profile_image']['tmp_name'];
$file_size = $_FILES['new_profile_image']['size'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($file_ext, $allowed)) {
    $_SESSION['error'] = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$file_info = getimagesize($file_tmp);
if ($file_info === false) {
    $_SESSION['error'] = "Uploaded file is not a valid image.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

if ($file_size > 5 * 1024 * 1024) {
    $_SESSION['error'] = "Profile image must not exceed 5MB.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$target_dir = __DIR__ . "/uploads/";

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$new_file_name = uniqid("profile_", true) . "." . $file_ext;
$target_file = $target_dir . $new_file_name;
$db_file_path = "uploads/" . $new_file_name;

if (!move_uploaded_file($file_tmp, $target_file)) {
    $_SESSION['error'] = "Failed to upload image.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "UPDATE users SET profile_image = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = "Prepare failed: " . $conn->error;
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$stmt->bind_param("si", $db_file_path, $user_id);

if ($stmt->execute()) {
    $_SESSION['profile_image'] = $db_file_path;
    $_SESSION['success'] = "Profile picture updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update profile picture.";
}

header("Location: /NexGen/CODE/PHP/dashboard.php");
exit();
?>