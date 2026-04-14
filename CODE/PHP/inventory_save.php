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

$product_code   = trim($_POST['product_code'] ?? '');
$product_name   = trim($_POST['product_name'] ?? '');
$category_id    = intval($_POST['category_id'] ?? 0);
$brand          = trim($_POST['brand'] ?? '');
$unit           = trim($_POST['unit'] ?? '');
$cost_price     = floatval($_POST['cost_price'] ?? 0);
$selling_price  = floatval($_POST['selling_price'] ?? 0);
$stock_quantity = intval($_POST['stock_quantity'] ?? 0);
$reorder_level  = $isOwner ? intval($_POST['reorder_level'] ?? 5) : 5;
$on_order_level = $isOwner ? intval($_POST['on_order_level'] ?? 0) : 0;
$expiry_date    = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$description    = trim($_POST['description'] ?? '');
$is_active      = intval($_POST['is_active'] ?? 1);

if ($product_code === '' || $product_name === '' || $category_id <= 0 || $unit === '') {
    $_SESSION['inventory_error'] = "Please fill in all required fields.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

$imagePath = "uploads/products/default.png";

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

$stmt = $conn->prepare("
    INSERT INTO products (
        product_code, product_name, category_id, brand, unit,
        cost_price, selling_price, stock_quantity, reorder_level, on_order_level,
        expiry_date, product_image, description, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssissdiiiisssi",
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
    $is_active
);

if ($stmt->execute()) {
    $_SESSION['inventory_success'] = "Product added successfully.";
} else {
    $_SESSION['inventory_error'] = "Failed to save product. Product code may already exist.";
}

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();