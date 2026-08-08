<?php
session_start();
require_once("config.php");
require_once("tenant_helper.php");



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
$businessId = nxRequireBusinessId($conn);

$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    $_SESSION['inventory_error'] = "Invalid product.";
    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

// "Place Order" is its own lightweight flow: it ONLY changes on_order_level
// and never touches stock_quantity or the stock_movements log, since no
// physical stock has moved yet. Owner-only, since placing a PO is a
// purchasing decision.
$placeOrder = $isOwner && isset($_POST['place_order']) && $_POST['place_order'] === '1';

if ($placeOrder) {
    $orderQty = max(0, intval($_POST['on_order_add'] ?? 0));
    $orderRemarks = trim($_POST['remarks'] ?? '');

    if ($orderQty <= 0) {
        $_SESSION['inventory_error'] = "Order quantity must be greater than 0.";
        header("Location: /NexGen/CODE/PHP/inventory_management.php");
        exit();
    }

    try {
        $conn->begin_transaction();

        $productStmt = $conn->prepare("
            SELECT id, product_name, on_order_level
            FROM products
            WHERE id = ? AND business_id = ?
            FOR UPDATE
        ");

        if (!$productStmt) {
            throw new Exception("Failed to prepare product lock query.");
        }

        $productStmt->bind_param("ii", $product_id, $businessId);
        $productStmt->execute();
        $productResult = $productStmt->get_result();
        $product = $productResult->fetch_assoc();
        $productStmt->close();

        if (!$product) {
            throw new Exception("Invalid product.");
        }

        $productName = $product['product_name'] ?? 'Product';
        $newOnOrder = (int)($product['on_order_level'] ?? 0) + $orderQty;

        $updateStmt = $conn->prepare("
            UPDATE products
            SET on_order_level = ?
            WHERE id = ? AND business_id = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Failed to prepare on-order update query.");
        }

        $updateStmt->bind_param("iii", $newOnOrder, $product_id, $businessId);

        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update on-order level.");
        }

        $updateStmt->close();

        $orderHistoryStmt = $conn->prepare("
            INSERT INTO stock_movements
                (business_id, product_id, movement_type, quantity, remarks, created_by, created_at)
            VALUES (?, ?, 'order_placed', ?, ?, ?, NOW())
        ");

        if (!$orderHistoryStmt) {
            throw new Exception("Failed to prepare purchase order history query.");
        }

        $orderHistoryStmt->bind_param(
            "iiisi",
            $businessId,
            $product_id,
            $orderQty,
            $orderRemarks,
            $user_id
        );

        if (!$orderHistoryStmt->execute()) {
            throw new Exception("Failed to save purchase order history.");
        }

        $orderHistoryStmt->close();
        $conn->commit();

        $_SESSION['inventory_success'] = "Order placed: {$orderQty} unit(s) added to on-order for {$productName}.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['inventory_error'] = $e->getMessage();
    }

    header("Location: /NexGen/CODE/PHP/inventory_management.php");
    exit();
}

// "Receive Shipment" is a dedicated flow: it always logs a stock-in and
// always matches the received quantity against on-order stock, regardless
// of who is logging it (employees routinely receive shipments).
$receiveShipment = isset($_POST['receive_shipment']) && $_POST['receive_shipment'] === '1';

$movement_type = $receiveShipment ? 'stock_in' : trim($_POST['movement_type'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');

// Placing a NEW purchase order is handled above (place_order branch), so by
// the time we get here, on_order_level can only move via deduction below.
$on_order_add = 0;

// Deducting from on-order (i.e. a shipment arrived) is routine receiving
// work, not a purchasing decision, so any logged-in user can do it.
$deduct_from_on_order = $receiveShipment || isset($_POST['deduct_from_on_order']);

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
        WHERE id = ? AND business_id = ?
        FOR UPDATE
    ");

    if (!$productStmt) {
        throw new Exception("Failed to prepare product lock query.");
    }

    $productStmt->bind_param("ii", $product_id, $businessId);
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
        $newOnOrder += $on_order_add;

        if ($deduct_from_on_order) {
            $deductQty = min($quantity, $newOnOrder);
            $newOnOrder -= $deductQty;

            // Supplier sent more than what was actually on order for this
            // product — still receive it into stock, but flag it in the
            // remarks instead of silently losing the discrepancy.
            if ($receiveShipment && $quantity > $currentOnOrder) {
                $overage = $quantity - $currentOnOrder;
                $remarks = trim($remarks . " [Note: received {$overage} more than was on order.]");
            }
        }
    } else {
        if ($quantity > $currentStock) {
            throw new Exception("Not enough stock available for stock out.");
        }

        $newStock -= $quantity;
        $newOnOrder += $on_order_add;
    }

    $updateStmt = $conn->prepare("
        UPDATE products
        SET stock_quantity = ?, on_order_level = ?
        WHERE id = ? AND business_id = ?
    ");

    if (!$updateStmt) {
        throw new Exception("Failed to prepare stock update query.");
    }

    $updateStmt->bind_param("iiii", $newStock, $newOnOrder, $product_id, $businessId);

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update product stock.");
    }

    $updateStmt->close();

    $movementStmt = $conn->prepare("
        INSERT INTO stock_movements (business_id, product_id, movement_type, quantity, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$movementStmt) {
        throw new Exception("Failed to prepare stock movement history query.");
    }

    $movementStmt->bind_param("iisisi", $businessId, $product_id, $movement_type, $quantity, $remarks, $user_id);

    if (!$movementStmt->execute()) {
        throw new Exception("Failed to save stock movement history.");
    }

    $movementStmt->close();

    $conn->commit();

    if ($receiveShipment) {
        $_SESSION['inventory_success'] = "Shipment received: {$quantity} unit(s) added to stock for {$productName}.";
    } else {
        $_SESSION['inventory_success'] = "Stock movement saved successfully for {$productName}.";
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['inventory_error'] = $e->getMessage();
}

header("Location: /NexGen/CODE/PHP/inventory_management.php");
exit();
?>