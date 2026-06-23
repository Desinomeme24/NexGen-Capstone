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
$isOwner = ($role === 'owner');
$user_id = (int)($_SESSION['user_id'] ?? 0);

$product_id = intval($_POST['product_id'] ?? 0);
$movement_type = trim($_POST['movement_type'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');

$on_order_add = $isOwner ? max(0, intval($_POST['on_order_add'] ?? 0)) : 0;
$deduct_from_on_order = ($isOwner && isset($_POST['deduct_from_on_order'])) ? 1 : 0;

if ($product_id <= 0) {
    $_SESSION['inventory_error'] = "Invalid product.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

if (!in_array($movement_type, ['stock_in', 'stock_out'], true)) {
    $_SESSION['inventory_error'] = "Invalid movement type.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

if ($quantity <= 0) {
    $_SESSION['inventory_error'] = "Quantity must be greater than 0.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

try {
    $conn->begin_transaction();

    
    $productStmt = $conn->prepare("
        SELECT id, product_name, stock_quantity, on_order_level, is_active
        FROM products
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$productStmt) {
        throw new Exception("Failed to prepare product lock query.");
    }

    $productStmt->bind_param("i", $product_id);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    $product = $productResult->fetch_assoc();
    $productStmt->close();

    if (!$product) {
        throw new Exception("Invalid product.");
    }

    $currentStock = (int)($product['stock_quantity'] ?? 0);
    $currentOnOrder = (int)($product['on_order_level'] ?? 0);
    $productName = $product['product_name'] ?? 'Product';

    $newStock = $currentStock;
    $newOnOrder = $currentOnOrder;

    if ($movement_type === 'stock_in') {
        $newStock += $quantity;

        if ($isOwner) {
            $newOnOrder += $on_order_add;

            if ($deduct_from_on_order) {
                $newOnOrder -= $quantity;

                if ($newOnOrder < 0) {
                    $newOnOrder = 0;
                }
            }
        }
    } else {
        if ($quantity > $currentStock) {
            throw new Exception("Not enough stock available for stock out.");
        }

        $newStock -= $quantity;

        if ($isOwner && $on_order_add > 0) {
            $newOnOrder += $on_order_add;
        }
    }

    if ($isOwner) {
        $updateStmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = ?, on_order_level = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Failed to prepare owner stock update query.");
        }

        $updateStmt->bind_param("iii", $newStock, $newOnOrder, $product_id);
    } else {
        $updateStmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Failed to prepare stock update query.");
        }

        $updateStmt->bind_param("ii", $newStock, $product_id);
    }

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update product stock.");
    }

    $updateStmt->close();

    $movementStmt = $conn->prepare("
        INSERT INTO stock_movements (product_id, movement_type, quantity, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    if (!$movementStmt) {
        throw new Exception("Failed to prepare stock movement history query.");
    }

    $movementStmt->bind_param("isisi", $product_id, $movement_type, $quantity, $remarks, $user_id);

    if (!$movementStmt->execute()) {
        throw new Exception("Failed to save stock movement history.");
    }

    $movementStmt->close();

    $conn->commit();

    $_SESSION['inventory_success'] = "Stock movement saved successfully for {$productName}.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['inventory_error'] = $e->getMessage();
}

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();
?>