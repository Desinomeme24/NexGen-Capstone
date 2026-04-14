<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$category_name = trim($_POST['category_name'] ?? '');

if ($category_name === '') {
    $_SESSION['inventory_error'] = "Category name is required.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
$stmt->bind_param("s", $category_name);

if ($stmt->execute()) {
    $_SESSION['inventory_success'] = "Category added successfully.";
} else {
    $_SESSION['inventory_error'] = "Category already exists or failed to save.";
}

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();