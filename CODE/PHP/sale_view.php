<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid sale ID.");
}

$sale_id = (int) $_GET['id'];

function badgeClassPayment($status) {
    return match($status) {
        'Paid' => 'badge paid',
        'Unpaid' => 'badge unpaid',
        'Partially Paid' => 'badge partial',
        default => 'badge'
    };
}

function badgeClassOrder($status) {
    return match($status) {
        'Fulfilled' => 'badge fulfilled',
        'Pending' => 'badge pending',
        default => 'badge'
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $additional_payment = (float)($_POST['additional_payment'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');

    if ($additional_payment <= 0) {
        $_SESSION['error'] = 'Please enter a valid payment amount.';
        header("Location: sale_view.php?id=" . $sale_id);
        exit();
    }

    $conn->begin_transaction();

    try {
        $saleCheckStmt = $conn->prepare("
            SELECT id, customer_id, total_amount, payment_status, order_status
            FROM sales
            WHERE id = ?
            LIMIT 1
        ");
        $saleCheckStmt->bind_param("i", $sale_id);
        $saleCheckStmt->execute();
        $saleCheckResult = $saleCheckStmt->get_result();

        if ($saleCheckResult->num_rows === 0) {
            throw new Exception('Sale not found.');
        }

        $saleRow = $saleCheckResult->fetch_assoc();
        $saleCheckStmt->close();

        if ($saleRow['payment_status'] === 'Paid') {
            throw new Exception('This sale is already fully paid.');
        }

        $arStmt = $conn->prepare("
            SELECT id, total_amount, amount_paid, balance_due, due_date, status
            FROM accounts_receivable
            WHERE sale_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $arStmt->bind_param("i", $sale_id);
        $arStmt->execute();
        $arResult = $arStmt->get_result();

        if ($arResult->num_rows === 0) {
            throw new Exception('No receivable record found for this sale.');
        }

        $receivable = $arResult->fetch_assoc();
        $arStmt->close();

        $currentAmountPaid = (float)$receivable['amount_paid'];
        $totalAmount = (float)$receivable['total_amount'];
        $newAmountPaid = $currentAmountPaid + $additional_payment;

        if ($newAmountPaid < 0) {
            $newAmountPaid = 0;
        }

        if ($newAmountPaid > $totalAmount) {
            $newAmountPaid = $totalAmount;
        }

        $newBalance = max(0, $totalAmount - $newAmountPaid);

        if ($newAmountPaid <= 0) {
            $newPaymentStatus = 'Unpaid';
            $newOrderStatus = 'Pending';
            $newArStatus = 'Unpaid';
        } elseif ($newAmountPaid < $totalAmount) {
            $newPaymentStatus = 'Partially Paid';
            $newOrderStatus = 'Pending';
            $newArStatus = 'Partially Paid';
        } else {
            $newPaymentStatus = 'Paid';
            $newOrderStatus = 'Fulfilled';
            $newArStatus = 'Paid';
            $due_date = '';
        }

        $finalDueDate = $receivable['due_date'];
        if ($newPaymentStatus !== 'Paid' && $due_date !== '') {
            $finalDueDate = $due_date;
        }
        if ($newPaymentStatus === 'Paid') {
            $finalDueDate = null;
        }

        $updateAr = $conn->prepare("
            UPDATE accounts_receivable
            SET amount_paid = ?, balance_due = ?, due_date = ?, status = ?
            WHERE id = ?
        ");
        $updateAr->bind_param(
            "ddssi",
            $newAmountPaid,
            $newBalance,
            $finalDueDate,
            $newArStatus,
            $receivable['id']
        );
        $updateAr->execute();
        $updateAr->close();

        $updateSale = $conn->prepare("
            UPDATE sales
            SET payment_status = ?, order_status = ?
            WHERE id = ?
        ");
        $updateSale->bind_param("ssi", $newPaymentStatus, $newOrderStatus, $sale_id);
        $updateSale->execute();
        $updateSale->close();

        $conn->commit();
        $_SESSION['success'] = 'Payment updated successfully.';
        header("Location: sale_view.php?id=" . $sale_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: sale_view.php?id=" . $sale_id);
        exit();
    }
}

$saleStmt = $conn->prepare("
    SELECT 
        s.*,
        u.full_name AS salesperson,
        c.customer_name
    FROM sales s
    LEFT JOIN users u ON s.salesperson_id = u.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$saleStmt->bind_param("i", $sale_id);
$saleStmt->execute();
$saleResult = $saleStmt->get_result();

if ($saleResult->num_rows === 0) {
    die("Sale not found.");
}

$sale = $saleResult->fetch_assoc();

$itemStmt = $conn->prepare("
    SELECT 
        si.quantity,
        si.unit_price,
        si.subtotal,
        p.product_name
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");
$itemStmt->bind_param("i", $sale_id);
$itemStmt->execute();
$itemResult = $itemStmt->get_result();

$arInfo = null;
$arStmt = $conn->prepare("
    SELECT id, total_amount, amount_paid, balance_due, due_date, status
    FROM accounts_receivable
    WHERE sale_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$arStmt->bind_param("i", $sale_id);
$arStmt->execute();
$arResult = $arStmt->get_result();
if ($arResult->num_rows > 0) {
    $arInfo = $arResult->fetch_assoc();
}
$arStmt->close();

$popupMessage = $_SESSION['success'] ?? $_SESSION['error'] ?? '';
$popupType = isset($_SESSION['success']) ? 'success' : (isset($_SESSION['error']) ? 'error' : '');
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sale</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            color: #f3f6ff;
            background:
                radial-gradient(circle at top left, rgba(80,120,255,0.14), transparent 25%),
                radial-gradient(circle at top right, rgba(255,255,255,0.05), transparent 18%),
                linear-gradient(180deg, #071533 0%, #0b1f4d 45%, #102554 100%);
            min-height: 100vh;
            padding: 34px;
        }

        .page-shell {
            max-width: 1100px;
            margin: 0 auto;
            background: rgba(22, 36, 76, 0.58);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 18px 55px rgba(0,0,0,0.35);
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .menu-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(180deg, #5890ff, #386cd8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
        }

        .content {
            padding: 24px;
        }

        .card {
            background: rgba(255,255,255,0.035);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .info-box {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px;
            padding: 16px;
        }

        .info-box small {
            display: block;
            color: #c8d5ff;
            margin-bottom: 6px;
        }

        .info-box strong {
            font-size: 18px;
            color: #fff;
        }

        .receivable-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-top: 12px;
        }

        .receivable-box {
            background: rgba(95,149,255,0.08);
            border: 1px solid rgba(95,149,255,0.22);
            border-radius: 14px;
            padding: 16px;
        }

        .receivable-box small {
            display: block;
            color: #c8d5ff;
            margin-bottom: 6px;
        }

        .receivable-box strong {
            font-size: 18px;
            color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        th {
            background: rgba(255,255,255,0.04);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            min-width: 100px;
        }

        .paid {
            background: rgba(107, 190, 145, 0.27);
            color: #b9f0cb;
        }

        .unpaid {
            background: rgba(220, 97, 97, 0.25);
            color: #ffb3b3;
        }

        .partial {
            background: rgba(229, 165, 83, 0.25);
            color: #ffd290;
        }

        .fulfilled {
            background: rgba(107, 190, 145, 0.27);
            color: #b9f0cb;
        }

        .pending {
            background: rgba(229, 165, 83, 0.25);
            color: #ffd290;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: white;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .btn-primary {
            background: linear-gradient(135deg, #f5d78f, #c99f45);
            color: #102554;
            border: none;
            min-width: 200px;
        }

        .total-panel {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }

        .total-card {
            min-width: 260px;
            background: rgba(95,149,255,0.10);
            border: 1px solid rgba(95,149,255,0.25);
            border-radius: 16px;
            padding: 18px 20px;
            text-align: right;
        }

        .total-card small {
            color: #cad8ff;
            display: block;
            margin-bottom: 6px;
        }

        .total-card strong {
            font-size: 30px;
            color: #fff;
        }

        .payment-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
            margin-top: 8px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #dbe6ff;
            font-weight: 700;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            height: 50px;
            padding: 0 16px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.08);
            color: #ffffff;
            outline: none;
        }

        .form-note {
            margin-top: 10px;
            color: #cbd8ff;
            font-size: 13px;
        }

        .empty-note {
            color: #c8d5ff;
            font-size: 14px;
        }

        .nx-toast-wrap {
            position: fixed;
            top: 22px;
            right: 22px;
            z-index: 99999;
            width: min(380px, calc(100vw - 24px));
        }

        .nx-toast {
            display: flex;
            align-items: stretch;
            border-radius: 18px;
            overflow: hidden;
            background: rgba(12, 22, 52, 0.92);
            box-shadow: 0 24px 50px rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.10);
            animation: slideIn .25s ease;
        }

        .nx-toast-bar {
            width: 7px;
            flex: 0 0 7px;
        }

        .nx-toast.success .nx-toast-bar {
            background: linear-gradient(180deg, #5ee5a1, #2bb673);
        }

        .nx-toast.error .nx-toast-bar {
            background: linear-gradient(180deg, #ff9b9b, #ea4d4d);
        }

        .nx-toast-body {
            flex: 1;
            padding: 14px 16px;
        }

        .nx-toast-title {
            font-weight: 800;
            margin-bottom: 5px;
            color: #fff;
        }

        .nx-toast-message {
            font-size: 14px;
            line-height: 1.45;
            color: #dce7ff;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-8px) translateX(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0) translateX(0);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .content {
                padding: 16px;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .nx-toast-wrap {
                right: 12px;
                top: 12px;
                width: calc(100vw - 24px);
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($popupMessage)): ?>
        <div class="nx-toast-wrap" id="nxToastWrap">
            <div class="nx-toast <?php echo htmlspecialchars($popupType ?: 'success'); ?>">
                <div class="nx-toast-bar"></div>
                <div class="nx-toast-body">
                    <div class="nx-toast-title"><?php echo $popupType === 'error' ? 'Attention' : 'Success'; ?></div>
                    <div class="nx-toast-message"><?php echo htmlspecialchars($popupMessage); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-shell">
        <div class="header">
            <div class="header-left">
                <div class="menu-icon">👁</div>
                <div class="header-title">Sale Details</div>
            </div>
            <a href="sales_recording.php" class="btn btn-secondary">Back to Sales</a>
        </div>

        <div class="content">
            <div class="card">
                <div class="section-title">Sale Information</div>

                <div class="info-grid">
                    <div class="info-box">
                        <small>Sales No.</small>
                        <strong><?php echo htmlspecialchars($sale['sales_no']); ?></strong>
                    </div>

                    <div class="info-box">
                        <small>Salesperson</small>
                        <strong><?php echo htmlspecialchars($sale['salesperson'] ?: 'N/A'); ?></strong>
                    </div>

                    <div class="info-box">
                        <small>Customer</small>
                        <strong><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></strong>
                    </div>

                    <div class="info-box">
                        <small>Sale Date</small>
                        <strong><?php echo date("M d, Y h:i A", strtotime($sale['sale_date'])); ?></strong>
                    </div>

                    <div class="info-box">
                        <small>Payment Method</small>
                        <strong><?php echo htmlspecialchars($sale['payment_method']); ?></strong>
                    </div>

                    <div class="info-box">
                        <small>Payment Status</small>
                        <strong>
                            <span class="<?php echo badgeClassPayment($sale['payment_status']); ?>">
                                <?php echo htmlspecialchars($sale['payment_status']); ?>
                            </span>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Order Status</small>
                        <strong>
                            <span class="<?php echo badgeClassOrder($sale['order_status']); ?>">
                                <?php echo htmlspecialchars($sale['order_status']); ?>
                            </span>
                        </strong>
                    </div>
                </div>
            </div>

            <?php if ($arInfo): ?>
                <div class="card">
                    <div class="section-title">Receivable Status</div>

                    <div class="receivable-grid">
                        <div class="receivable-box">
                            <small>Total Amount</small>
                            <strong>₱<?php echo number_format((float)$arInfo['total_amount'], 2); ?></strong>
                        </div>

                        <div class="receivable-box">
                            <small>Amount Paid</small>
                            <strong>₱<?php echo number_format((float)$arInfo['amount_paid'], 2); ?></strong>
                        </div>

                        <div class="receivable-box">
                            <small>Balance Due</small>
                            <strong>₱<?php echo number_format((float)$arInfo['balance_due'], 2); ?></strong>
                        </div>

                        <div class="receivable-box">
                            <small>Receivable Status</small>
                            <strong><?php echo htmlspecialchars($arInfo['status']); ?></strong>
                        </div>

                        <div class="receivable-box">
                            <small>Due Date</small>
                            <strong><?php echo !empty($arInfo['due_date']) ? htmlspecialchars($arInfo['due_date']) : 'N/A'; ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($arInfo && $sale['payment_status'] !== 'Paid'): ?>
                <div class="card">
                    <div class="section-title">Update Payment</div>

                    <form method="POST" class="payment-form">
                        <input type="hidden" name="update_payment" value="1">

                        <div class="form-group">
                            <label>Additional Payment</label>
                            <input type="number" step="0.01" min="0.01" name="additional_payment" placeholder="Enter additional payment" required>
                        </div>

                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date" value="<?php echo !empty($arInfo['due_date']) ? htmlspecialchars($arInfo['due_date']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Payment</button>
                        </div>
                    </form>

                    <div class="form-note">
                        Rule applied: Unpaid or Partially Paid stays <strong>Pending</strong>. Once fully paid, it automatically becomes <strong>Paid</strong> and <strong>Fulfilled</strong>.
                    </div>
                </div>
            <?php elseif (!$arInfo): ?>
                <div class="card">
                    <div class="section-title">Update Payment</div>
                    <div class="empty-note">This sale has no accounts receivable record, so there is no payment update form available.</div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="section-title">Items Sold</div>

                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($itemResult->num_rows > 0): ?>
                            <?php while ($item = $itemResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo (int)$item['quantity']; ?></td>
                                    <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No items found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="total-panel">
                    <div class="total-card">
                        <small>Total Amount</small>
                        <strong>₱<?php echo number_format($sale['total_amount'], 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const nxToastWrap = document.getElementById('nxToastWrap');
        if (nxToastWrap) {
            setTimeout(() => {
                nxToastWrap.style.transition = 'opacity .25s ease, transform .25s ease';
                nxToastWrap.style.opacity = '0';
                nxToastWrap.style.transform = 'translateY(-8px)';
                setTimeout(() => {
                    nxToastWrap.remove();
                }, 260);
            }, 3200);
        }
    </script>
</body>
</html>