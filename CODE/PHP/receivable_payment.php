<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['owner', 'employee'], true)) {
    $_SESSION['error'] = 'Unauthorized access.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid receivable ID.';
    header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $additional_payment = (float)($_POST['additional_payment'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($additional_payment <= 0) {
        $_SESSION['error'] = 'Additional payment must be greater than 0.';
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            SELECT id, sale_id, total_amount, amount_paid, balance_due, due_date
            FROM accounts_receivable
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('Receivable not found.');
        }

        $saleId = (int)$row['sale_id'];
        $totalAmount = (float)$row['total_amount'];
        $currentAmountPaid = (float)$row['amount_paid'];

        $newAmountPaid = $currentAmountPaid + $additional_payment;
        if ($newAmountPaid > $totalAmount) {
            $newAmountPaid = $totalAmount;
        }

        if ($newAmountPaid < 0) {
            $newAmountPaid = 0;
        }

        $newBalance = max(0, $totalAmount - $newAmountPaid);

        $newReceivableStatus = 'Unpaid';
        $newSalesPaymentStatus = 'Unpaid';
        $newSalesOrderStatus = 'Pending';

        if ($newBalance <= 0) {
            $newReceivableStatus = 'Paid';
            $newSalesPaymentStatus = 'Paid';
            $newSalesOrderStatus = 'Fulfilled';
        } elseif ($newAmountPaid > 0) {
            $newReceivableStatus = 'Partially Paid';
            $newSalesPaymentStatus = 'Partially Paid';
            $newSalesOrderStatus = 'Pending';
        }

        if (
            $newReceivableStatus !== 'Paid' &&
            !empty($row['due_date']) &&
            strtotime($row['due_date']) < strtotime(date('Y-m-d'))
        ) {
            $newReceivableStatus = 'Overdue';
        }

        $updateReceivable = $conn->prepare("
            UPDATE accounts_receivable
            SET amount_paid = ?, balance_due = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $updateReceivable->bind_param(
            "ddssi",
            $newAmountPaid,
            $newBalance,
            $newReceivableStatus,
            $notes,
            $id
        );

        if (!$updateReceivable->execute()) {
            throw new Exception('Failed to update receivable payment.');
        }
        $updateReceivable->close();

        $updateSale = $conn->prepare("
            UPDATE sales
            SET payment_status = ?, order_status = ?
            WHERE id = ?
        ");
        $updateSale->bind_param(
            "ssi",
            $newSalesPaymentStatus,
            $newSalesOrderStatus,
            $saleId
        );

        if (!$updateSale->execute()) {
            throw new Exception('Failed to sync sales payment status.');
        }
        $updateSale->close();

        $conn->commit();
        $_SESSION['success'] = 'Receivable payment updated successfully.';
        header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }
}

$stmt = $conn->prepare("
    SELECT ar.*, c.customer_name, s.sales_no
    FROM accounts_receivable ar
    INNER JOIN customers c ON ar.customer_id = c.id
    INNER JOIN sales s ON ar.sale_id = s.id
    WHERE ar.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['error'] = 'Receivable record not found.';
    header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Receivable Payment</title>
    <style>
        body{
            margin:0;
            font-family:Arial,sans-serif;
            background:linear-gradient(180deg,#08152f,#102554);
            color:#fff;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .card{
            width:100%;
            max-width:560px;
            background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.14);
            border-radius:20px;
            padding:24px;
        }
        h1{margin-top:0}
        .row{margin-bottom:14px}
        label{display:block;margin-bottom:8px}
        input, textarea{
            width:100%;
            padding:12px;
            border:none;
            border-radius:12px;
        }
        .actions{
            display:flex;
            gap:12px;
            margin-top:16px;
        }
        .actions button,.actions a{
            flex:1;
            text-align:center;
            padding:12px;
            border:none;
            border-radius:12px;
            text-decoration:none;
        }
        .actions button{
            background:#facc15;
            color:#111;
            font-weight:700;
            cursor:pointer;
        }
        .actions a{
            background:#cbd5e1;
            color:#111;
        }
        .info{
            background:rgba(255,255,255,.06);
            border-radius:14px;
            padding:16px;
            margin-bottom:18px;
        }
    </style>
</head>
<body>
    <form method="POST" class="card">
        <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">

        <h1>Update Receivable</h1>

        <div class="info">
            <p><strong>Sales No:</strong> <?php echo htmlspecialchars($data['sales_no']); ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($data['customer_name']); ?></p>
            <p><strong>Total Amount:</strong> ₱<?php echo number_format((float)$data['total_amount'], 2); ?></p>
            <p><strong>Amount Paid:</strong> ₱<?php echo number_format((float)$data['amount_paid'], 2); ?></p>
            <p><strong>Balance Due:</strong> ₱<?php echo number_format((float)$data['balance_due'], 2); ?></p>
        </div>

        <div class="row">
            <label>Additional Payment</label>
            <input type="number" step="0.01" min="0.01" name="additional_payment" required>
        </div>

        <div class="row">
            <label>Notes</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($data['notes'] ?? ''); ?></textarea>
        </div>

        <div class="actions">
            <button type="submit">Save Payment</button>
            <a href="/NexGen/CODE/PHP/accounts_receivable.php">Cancel</a>
        </div>
    </form>
</body>
</html>