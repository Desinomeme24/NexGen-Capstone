<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.'
    ]);
    exit();
}

if ((int)($_SESSION['can_sales'] ?? 0) !== 1) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to save sales.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$sales_no = trim($_POST['sales_no'] ?? '');
$customer_id = (int) ($_POST['customer_id'] ?? 0);
$payment_status = trim($_POST['payment_status'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$due_date = trim($_POST['due_date'] ?? '');
$amount_paid_input = (float) ($_POST['amount_paid'] ?? 0);

$product_ids = $_POST['product_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$unit_prices = $_POST['unit_price'] ?? [];

$allowedPaymentStatus = ['Paid', 'Unpaid', 'Partially Paid'];
$allowedPaymentMethod = ['Cash', 'GCash', 'Gcash', 'Maya', 'Bank Transfer'];

if ($sales_no === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Sales number is required.'
    ]);
    exit();
}

if (!in_array($payment_status, $allowedPaymentStatus, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment status.'
    ]);
    exit();
}

if (!in_array($payment_method, $allowedPaymentMethod, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment method.'
    ]);
    exit();
}

if (!is_array($product_ids) || !is_array($quantities) || !is_array($unit_prices) || count($product_ids) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Please add at least one sale item.'
    ]);
    exit();
}

if (count($product_ids) !== count($quantities) || count($product_ids) !== count($unit_prices)) {
    echo json_encode([
        'success' => false,
        'message' => 'Sale item data is incomplete or mismatched.'
    ]);
    exit();
}

if (in_array($payment_status, ['Unpaid', 'Partially Paid'], true) && $customer_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Customer is required for unpaid or partially paid sales.'
    ]);
    exit();
}

$conn->begin_transaction();

try {
    $total_amount = 0;
    $items = [];
    $requiredByProduct = [];

    for ($i = 0; $i < count($product_ids); $i++) {
        $product_id = (int) ($product_ids[$i] ?? 0);
        $quantity = (int) ($quantities[$i] ?? 0);
        $unit_price = (float) ($unit_prices[$i] ?? 0);

        if ($product_id <= 0 || $quantity <= 0 || $unit_price < 0) {
            throw new Exception('Invalid product, quantity, or unit price detected.');
        }

        $subtotal = $quantity * $unit_price;
        $total_amount += $subtotal;

        $items[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'subtotal' => $subtotal
        ];

        if (!isset($requiredByProduct[$product_id])) {
            $requiredByProduct[$product_id] = 0;
        }

        $requiredByProduct[$product_id] += $quantity;
    }

    ksort($requiredByProduct);

    $productCheckStmt = $conn->prepare("
        SELECT id, product_name, stock_quantity
        FROM products
        WHERE id = ? AND is_active = 1
        FOR UPDATE
    ");

    if (!$productCheckStmt) {
        throw new Exception('Failed to prepare product lock query.');
    }

    $productNames = [];

    foreach ($requiredByProduct as $product_id => $requiredQty) {
        $productCheckStmt->bind_param("i", $product_id);
        $productCheckStmt->execute();
        $productResult = $productCheckStmt->get_result();

        if ($productResult->num_rows === 0) {
            throw new Exception('One of the selected products does not exist or is inactive.');
        }

        $product = $productResult->fetch_assoc();
        $availableStock = (int) $product['stock_quantity'];
        $productNames[$product_id] = $product['product_name'];

        if ($requiredQty > $availableStock) {
            throw new Exception('Insufficient stock for one of the selected products.');
        }
    }

    $productCheckStmt->close();

    foreach ($items as $index => $item) {
        $items[$index]['product_name'] = $productNames[$item['product_id']] ?? 'Product';
    }

    if ($payment_status === 'Paid') {
        $amount_paid_input = $total_amount;
        $due_date = '';
        $order_status = 'Fulfilled';
    } elseif ($payment_status === 'Unpaid') {
        $amount_paid_input = 0;
        $order_status = 'Pending';

        if ($due_date === '') {
            throw new Exception('Due date is required for unpaid sales.');
        }
    } else {
        $order_status = 'Pending';

        if ($amount_paid_input <= 0 || $amount_paid_input >= $total_amount) {
            throw new Exception('For partially paid sales, amount paid must be greater than 0 and less than the total amount.');
        }

        if ($due_date === '') {
            throw new Exception('Due date is required for partially paid sales.');
        }
    }

    $customerParam = $customer_id > 0 ? $customer_id : null;

    $saleStmt = $conn->prepare("
        INSERT INTO sales (
            sales_no,
            salesperson_id,
            customer_id,
            total_amount,
            payment_status,
            payment_method,
            order_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$saleStmt) {
        throw new Exception('Failed to prepare sales insert query.');
    }

    $saleStmt->bind_param(
        "siidsss",
        $sales_no,
        $user_id,
        $customerParam,
        $total_amount,
        $payment_status,
        $payment_method,
        $order_status
    );

    if (!$saleStmt->execute()) {
        throw new Exception('Failed to save sale record.');
    }

    $sale_id = $conn->insert_id;
    $saleStmt->close();

    foreach ($items as $item) {
        $itemStmt = $conn->prepare("
            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$itemStmt) {
            throw new Exception('Failed to prepare sale items insert query.');
        }

        $itemStmt->bind_param(
            "iiidd",
            $sale_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['subtotal']
        );

        if (!$itemStmt->execute()) {
            throw new Exception('Failed to save sale item.');
        }

        $itemStmt->close();

        $stockStmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE id = ?
        ");

        if (!$stockStmt) {
            throw new Exception('Failed to prepare stock update query.');
        }

        $stockStmt->bind_param("ii", $item['quantity'], $item['product_id']);

        if (!$stockStmt->execute()) {
            throw new Exception('Failed to deduct product stock.');
        }

        $stockStmt->close();

        $remarks = "Sale recorded: " . $sales_no;

        $movementStmt = $conn->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, remarks, created_by)
            VALUES (?, 'stock_out', ?, ?, ?)
        ");

        if (!$movementStmt) {
            throw new Exception('Failed to prepare stock movement query.');
        }

        $movementStmt->bind_param(
            "iisi",
            $item['product_id'],
            $item['quantity'],
            $remarks,
            $user_id
        );

        if (!$movementStmt->execute()) {
            throw new Exception('Failed to save stock movement history.');
        }

        $movementStmt->close();
    }

    if ($payment_status === 'Unpaid' || $payment_status === 'Partially Paid') {
        $amount_paid = max(0, min($amount_paid_input, $total_amount));
        $balance_due = max(0, $total_amount - $amount_paid);

        if ($amount_paid <= 0) {
            $receivableStatus = 'Unpaid';
        } elseif ($amount_paid < $total_amount) {
            $receivableStatus = 'Partially Paid';
        } else {
            $receivableStatus = 'Paid';
        }

        $arStmt = $conn->prepare("
            INSERT INTO accounts_receivable
            (sale_id, customer_id, total_amount, amount_paid, balance_due, due_date, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$arStmt) {
            throw new Exception('Failed to prepare accounts receivable insert query.');
        }

        $arStmt->bind_param(
            "iidddssi",
            $sale_id,
            $customer_id,
            $total_amount,
            $amount_paid,
            $balance_due,
            $due_date,
            $receivableStatus,
            $user_id
        );

        if (!$arStmt->execute()) {
            throw new Exception('Failed to save accounts receivable record.');
        }

        $arStmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale recorded successfully.',
        'sale_id' => $sale_id
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit();

} catch (Exception $e) {
    $conn->rollback();

    error_log('process_sale_ajax.php error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save the sale. Please check the form and try again.'
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit();
}
?>