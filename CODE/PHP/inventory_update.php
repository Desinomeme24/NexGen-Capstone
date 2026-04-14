<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$role = $_SESSION['role'] ?? 'employee';
$isOwner = $role === 'owner';

$id             = intval($_POST['id'] ?? 0);
$product_code   = trim($_POST['product_code'] ?? '');
$product_name   = trim($_POST['product_name'] ?? '');
$category_id    = intval($_POST['category_id'] ?? 0);
$brand          = trim($_POST['brand'] ?? '');
$unit           = trim($_POST['unit'] ?? '');
$cost_price     = floatval($_POST['cost_price'] ?? 0);
$selling_price  = floatval($_POST['selling_price'] ?? 0);
$stock_quantity = intval($_POST['stock_quantity'] ?? 0);
$reorder_level  = $isOwner ? max(0, intval($_POST['reorder_level'] ?? 5)) : 5;
$on_order_level = $isOwner ? max(0, intval($_POST['on_order_level'] ?? 0)) : 0;
$expiry_date    = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$description    = trim($_POST['description'] ?? '');
$is_active      = intval($_POST['is_active'] ?? 1);
$oldImage       = trim($_POST['old_image'] ?? 'uploads/products/default.png');

if ($id <= 0) {
    $_SESSION['inventory_error'] = "Invalid product.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

if ($product_code === '' || $product_name === '' || $category_id <= 0 || $unit === '') {
    $_SESSION['inventory_error'] = "Please complete the required product fields.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

if ($cost_price < 0 || $selling_price < 0 || $stock_quantity < 0) {
    $_SESSION['inventory_error'] = "Cost, selling price, and stock must not be negative.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$imagePath = $oldImage;

if (!empty($_FILES['product_image']['name'])) {
    $uploadDir = __DIR__ . "/uploads/products/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmp = $_FILES['product_image']['tmp_name'];
    $fileName = $_FILES['product_image']['name'];
    $fileSize = $_FILES['product_image']['size'];
    $fileError = $_FILES['product_image']['error'];
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileError === 0 && in_array($extension, $allowed, true) && $fileSize <= 5 * 1024 * 1024) {
        $newFileName = "product_" . time() . "_" . uniqid() . "." . $extension;
        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $imagePath = "uploads/products/" . $newFileName;
        }
    }
}

if ($isOwner) {
    $stmt = $conn->prepare("
        UPDATE products SET
            product_code = ?,
            product_name = ?,
            category_id = ?,
            brand = ?,
            unit = ?,
            cost_price = ?,
            selling_price = ?,
            stock_quantity = ?,
            reorder_level = ?,
            on_order_level = ?,
            expiry_date = ?,
            product_image = ?,
            description = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssissddiiisssii",
        $product_code,
        $product_name,
        $category_id,
        $brand,
        $unit,
        $cost_price,
        $selling_price,
        $stock_quantity,
        $reorder_level,
        $on_order_level,
        $expiry_date,
        $imagePath,
        $description,
        $is_active,
        $id
    );
} else {
    $stmt = $conn->prepare("
        UPDATE products SET
            product_code = ?,
            product_name = ?,
            category_id = ?,
            brand = ?,
            unit = ?,
            cost_price = ?,
            selling_price = ?,
            stock_quantity = ?,
            expiry_date = ?,
            product_image = ?,
            description = ?,
            is_active = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssissddisssii",
        $product_code,
        $product_name,
        $category_id,
        $brand,
        $unit,
        $cost_price,
        $selling_price,
        $stock_quantity,
        $expiry_date,
        $imagePath,
        $description,
        $is_active,
        $id
    );
}

if ($stmt->execute()) {
    $_SESSION['inventory_success'] = "Product updated successfully.";
} else {
    $_SESSION['inventory_error'] = "Failed to update product.";
}

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();
?>