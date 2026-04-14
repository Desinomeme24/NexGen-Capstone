<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    $_SESSION['inventory_error'] = "Only owners can restore products.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['inventory_error'] = "Invalid product.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$productStmt = $conn->prepare("
    SELECT id, product_name, is_active
    FROM products
    WHERE id = ?
    LIMIT 1
");
$productStmt->bind_param("i", $id);
$productStmt->execute();
$productResult = $productStmt->get_result();
$product = $productResult ? $productResult->fetch_assoc() : null;
$productStmt->close();

if (!$product) {
    $_SESSION['inventory_error'] = "Product not found.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

if ((int)$product['is_active'] === 1) {
    $_SESSION['inventory_success'] = "Product is already active.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$stmt = $conn->prepare("
    UPDATE products
    SET is_active = 1
    WHERE id = ?
");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['inventory_success'] = "Product restored successfully.";
} else {
    $_SESSION['inventory_error'] = "Failed to restore product.";
}

$stmt->close();

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();
?>