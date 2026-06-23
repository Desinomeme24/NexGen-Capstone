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

$profileFile = $_FILES['new_profile_image'];

[$isValidUpload, $scanResult] = nxValidateSecureUpload($profileFile, [
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ],
    'max_size' => 5 * 1024 * 1024,
    'require_image' => true,
    'allow_pdf' => false
]);

if (!$isValidUpload) {
    $_SESSION['error'] = "Profile image upload blocked: " . $scanResult;
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$file_ext = strtolower(pathinfo($profileFile['name'], PATHINFO_EXTENSION));
$target_dir = __DIR__ . "/uploads/";

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$new_file_name = uniqid("profile_", true) . "." . $file_ext;
$target_file = $target_dir . $new_file_name;
$db_file_path = "uploads/" . $new_file_name;

if (!move_uploaded_file($profileFile['tmp_name'], $target_file)) {
    $_SESSION['error'] = "Failed to upload image.";
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$getOldSql = "SELECT profile_image FROM users WHERE id = ? LIMIT 1";
$getOldStmt = $conn->prepare($getOldSql);
$getOldStmt->bind_param("i", $user_id);
$getOldStmt->execute();
$getOldResult = $getOldStmt->get_result();
$oldUser = $getOldResult->fetch_assoc();
$getOldStmt->close();

$sql = "UPDATE users SET profile_image = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    @unlink($target_file);
    $_SESSION['error'] = "Prepare failed: " . $conn->error;
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$stmt->bind_param("si", $db_file_path, $user_id);

if ($stmt->execute()) {
    $_SESSION['profile_image'] = $db_file_path;
    $_SESSION['success'] = "Profile picture updated successfully.";

    $oldPath = $oldUser['profile_image'] ?? '';
    if (!empty($oldPath) && $oldPath !== 'uploads/default.png') {
        $oldFullPath = __DIR__ . '/' . $oldPath;
        if (is_file($oldFullPath)) {
            @unlink($oldFullPath);
        }
    }
} else {
    @unlink($target_file);
    $_SESSION['error'] = "Failed to update profile picture.";
}

$stmt->close();
header("Location: /NexGen/CODE/PHP/dashboard.php");
exit();
?>