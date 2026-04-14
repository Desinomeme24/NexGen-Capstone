<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ((int)($_SESSION['can_sales'] ?? 0) !== 1) {
    $_SESSION['error'] = 'You do not have access to Sales.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$sales_no = trim($_POST['sales_no'] ?? '');
$customer_id = (int)($_POST['customer_id'] ?? 0);
$payment_status = trim($_POST['payment_status'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$order_status = trim($_POST['order_status'] ?? '');
$due_date = trim($_POST['due_date'] ?? '');
$amount_paid_input = (float)($_POST['amount_paid'] ?? 0);

$product_ids = $_POST['product_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$unit_prices = $_POST['unit_price'] ?? [];

$allowedPaymentStatus = ['Paid', 'Unpaid', 'Partially Paid'];
$allowedPaymentMethod = ['Cash', 'GCash', 'Gcash', 'Maya', 'Bank Transfer'];
$allowedOrderStatus = ['Fulfilled', 'Pending'];

if ($sales_no === '' || empty($product_ids)) {
    $_SESSION['error'] = 'Invalid sale data.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

if (!in_array($payment_status, $allowedPaymentStatus, true)) {
    $_SESSION['error'] = 'Invalid payment status.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

if (!in_array($payment_method, $allowedPaymentMethod, true)) {
    $_SESSION['error'] = 'Invalid payment method.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

if (($payment_status === 'Unpaid' || $payment_status === 'Partially Paid') && $customer_id <= 0) {
    $_SESSION['error'] = 'Customer is required for unpaid or partially paid sales.';
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}

$conn->begin_transaction();

try {
    $total_amount = 0;
    $items = [];

    for ($i = 0; $i < count($product_ids); $i++) {
        $product_id = (int)($product_ids[$i] ?? 0);
        $qty = (int)($quantities[$i] ?? 0);
        $price = (float)($unit_prices[$i] ?? 0);

        if ($product_id <= 0 || $qty <= 0 || $price < 0) {
            throw new Exception("Invalid product, quantity, or unit price.");
        }

        $checkStmt = $conn->prepare("SELECT stock_quantity, product_name FROM products WHERE id = ? LIMIT 1");
        $checkStmt->bind_param("i", $product_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows === 0) {
            throw new Exception("Product not found.");
        }

        $product = $checkResult->fetch_assoc();
        if ((int)$product['stock_quantity'] < $qty) {
            throw new Exception("Not enough stock for " . $product['product_name']);
        }
        $checkStmt->close();

        $subtotal = $qty * $price;
        $total_amount += $subtotal;

        $items[] = [
            'product_id' => $product_id,
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $subtotal
        ];
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
    } elseif ($payment_status === 'Partially Paid') {
        $order_status = 'Pending';
        if ($amount_paid_input <= 0 || $amount_paid_input >= $total_amount) {
            throw new Exception('For partially paid sales, amount paid must be greater than 0 and less than the total amount.');
        }
        if ($due_date === '') {
            throw new Exception('Due date is required for partially paid sales.');
        }
    }

    if (!in_array($order_status, $allowedOrderStatus, true)) {
        throw new Exception('Invalid order status.');
    }

    $customerParam = $customer_id > 0 ? $customer_id : null;

    $saleStmt = $conn->prepare("
        INSERT INTO sales (
            sales_no, salesperson_id, customer_id, total_amount, payment_status, payment_method, order_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $saleStmt->bind_param("siidsss", $sales_no, $user_id, $customerParam, $total_amount, $payment_status, $payment_method, $order_status);
    $saleStmt->execute();
    $sale_id = $conn->insert_id;
    $saleStmt->close();

    foreach ($items as $item) {
        $itemStmt = $conn->prepare("
            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $itemStmt->bind_param("iiidd", $sale_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
        $itemStmt->execute();
        $itemStmt->close();

        $updateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $updateStock->bind_param("ii", $item['quantity'], $item['product_id']);
        $updateStock->execute();
        $updateStock->close();

        $remarks = "Sale recorded: " . $sales_no;
        $stockStmt = $conn->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, remarks, created_by)
            VALUES (?, 'stock_out', ?, ?, ?)
        ");
        $stockStmt->bind_param("iisi", $item['product_id'], $item['quantity'], $remarks, $user_id);
        $stockStmt->execute();
        $stockStmt->close();
    }

    if ($payment_status === 'Unpaid' || $payment_status === 'Partially Paid') {
        $amount_paid = max(0, min($amount_paid_input, $total_amount));
        $balance_due = max(0, $total_amount - $amount_paid);

        $receivableStatus = 'Unpaid';
        if ($amount_paid > 0 && $balance_due > 0) {
            $receivableStatus = 'Partially Paid';
        } elseif ($balance_due <= 0) {
            $receivableStatus = 'Paid';
        }

        $stmtAr = $conn->prepare("
            INSERT INTO accounts_receivable (
                sale_id, customer_id, total_amount, amount_paid, balance_due, due_date, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtAr->bind_param(
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
        $stmtAr->execute();
        $stmtAr->close();
    }

    $conn->commit();
    $_SESSION['success'] = 'Sale recorded successfully.';
    header("Location: sale_view.php?id=" . $sale_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error saving sale: " . $e->getMessage();
    header("Location: /NexGen/CODE/PHP/sales_recording.php");
    exit();
}
?>