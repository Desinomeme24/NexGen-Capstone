<?php
session_start();
require_once("config.php");
require_once(__DIR__ . "/audit_helper.php");
set_audit_context($conn); 


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

// Block any attempt (GET display or POST submit) to add a payment to a
// receivable that's already fully paid — mirrors the disabled button in
// accounts_receivable.php, but enforced server-side too.
$statusCheckStmt = $conn->prepare("SELECT status FROM accounts_receivable WHERE id = ? LIMIT 1");
$statusCheckStmt->bind_param("i", $id);
$statusCheckStmt->execute();
$statusRow = $statusCheckStmt->get_result()->fetch_assoc();
$statusCheckStmt->close();

if (!$statusRow) {
    $_SESSION['error'] = 'Receivable not found.';
    header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
    exit();
}

if (strcasecmp($statusRow['status'], 'Paid') === 0) {
    $_SESSION['error'] = 'This receivable is already fully paid.';
    header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $additional_payment = (float)($_POST['additional_payment'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');

    $allowedPaymentMethods = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];

    if ($additional_payment <= 0) {
        $_SESSION['error'] = 'Additional payment must be greater than 0.';
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }

    if ($payment_date === '' || strtotime($payment_date) === false) {
        $_SESSION['error'] = 'A valid payment date is required.';
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }

    if (!in_array($payment_method, $allowedPaymentMethods, true)) {
        $_SESSION['error'] = 'Invalid payment method.';
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }

    $maxRetries = 3;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {

    $conn->begin_transaction();

    try {
        // Step 1: Read WITHOUT lock to get sale_id (no lock held yet)
        $stmtRead = $conn->prepare("
            SELECT id, sale_id, total_amount, amount_paid, balance_due, due_date
            FROM accounts_receivable
            WHERE id = ?
        ");

        if (!$stmtRead) {
            throw new Exception('Failed to prepare receivable read query.');
        }

        $stmtRead->bind_param("i", $id);
        $stmtRead->execute();
        $rowRead = $stmtRead->get_result()->fetch_assoc();
        $stmtRead->close();

        if (!$rowRead) {
            throw new Exception('Receivable not found.');
        }

        $saleId = (int)$rowRead['sale_id'];

        // Step 2: Lock SALES first (global order: sales → accounts_receivable)
        $saleLock = $conn->prepare("
            SELECT id
            FROM sales
            WHERE id = ?
            FOR UPDATE
        ");

        if (!$saleLock) {
            throw new Exception('Failed to prepare sales lock query.');
        }

        $saleLock->bind_param("i", $saleId);
        $saleLock->execute();
        $saleRow = $saleLock->get_result()->fetch_assoc();
        $saleLock->close();

        if (!$saleRow) {
            throw new Exception('Related sale record not found.');
        }

        // Step 3: Lock ACCOUNTS_RECEIVABLE second (correct global order)
        $stmt = $conn->prepare("
            SELECT id, sale_id, total_amount, amount_paid, balance_due, due_date
            FROM accounts_receivable
            WHERE id = ?
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare receivable lock query.');
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('Receivable not found.');
        }

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

        if (!$updateReceivable) {
            throw new Exception('Failed to prepare receivable update query.');
        }

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

        if (!$updateSale) {
            throw new Exception('Failed to prepare sales sync query.');
        }

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

        // Record this payment in payment_history so past payments are never
        // overwritten/lost (needed for Statement of Account / Recent Payments later)
        $insertHistory = $conn->prepare("
            INSERT INTO payment_history (
                receivable_id, payment_date, amount, payment_method, reference_number, remarks, received_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insertHistory) {
            throw new Exception('Failed to prepare payment history query.');
        }

        $userId = (int)$_SESSION['user_id'];

        $insertHistory->bind_param(
            "isdsssi",
            $id,
            $payment_date,
            $additional_payment,
            $payment_method,
            $reference_number,
            $notes,
            $userId
        );

        if (!$insertHistory->execute()) {
            throw new Exception('Failed to save payment history.');
        }

        $insertHistory->close();

        $conn->commit();

        $_SESSION['success'] = 'Receivable payment updated successfully.';
        header("Location: /NexGen/CODE/PHP/accounts_receivable.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();

        // Retry on deadlock (MySQL error 1213), otherwise fail immediately
        if ($conn->errno === 1213 && $attempt < $maxRetries) {
            usleep(100000 * $attempt); // wait 100ms, 200ms before retrying
            continue;
        }

        $_SESSION['error'] = $e->getMessage();
        header("Location: /NexGen/CODE/PHP/receivable_payment.php?id=" . $id);
        exit();
    }

    } // end retry loop
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap');

        :root{
            --royal:#0033ff;
            --azure:#3b82f6;
            --sky:#7dd3fc;
            --indigo:#0b1f73;
            --midnight:#00033d;
            --gold:#f6cb08;
            --gold-light:#ffdd55;
            --ink:#eef5ff;
            --ink-soft:#b9d4f5;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:'Inter',Arial,sans-serif;
            background:
                radial-gradient(circle at 12% 8%, rgba(59,130,246,0.32) 0%, rgba(59,130,246,0) 34%),
                radial-gradient(circle at 88% 92%, rgba(125,211,252,0.16) 0%, rgba(125,211,252,0) 32%),
                linear-gradient(180deg,#050b24 0%,#0a1550 45%,#02040f 100%);
            background-attachment:fixed;
            color:var(--ink);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:32px 24px;
        }

        .card{
            position:relative;
            width:100%;
            max-width:560px;
            background:linear-gradient(180deg, rgba(59,130,246,0.1) 0%, rgba(11,31,115,0.18) 100%);
            border:1px solid rgba(59,130,246,0.26);
            border-radius:28px;
            padding:30px;
            box-shadow:
                0 20px 46px rgba(2,4,20,0.5),
                inset 0 1px 0 rgba(255,255,255,0.07);
            backdrop-filter:blur(16px);
            -webkit-backdrop-filter:blur(16px);
        }

        .card-header{
            display:flex;
            align-items:center;
            gap:14px;
            margin-bottom:22px;
        }

        .card-header .icon-wrap{
            flex:0 0 48px;
            width:48px;
            height:48px;
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:22px;
            background:linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
            color:#2b1a00;
            box-shadow:0 10px 22px rgba(246,203,8,0.3);
        }

        h1{
            margin:0;
            font-family:'Poppins','Inter',sans-serif;
            font-size:1.4rem;
            font-weight:800;
            line-height:1.2;
        }

        .card-header p{
            margin:2px 0 0;
            font-size:0.88rem;
            color:var(--ink-soft);
            font-weight:600;
        }

        /* ---------- Receivable info summary ---------- */
        .info{
            background:rgba(11,31,115,0.28);
            border:1px solid rgba(59,130,246,0.24);
            border-radius:18px;
            padding:18px 18px 6px;
            margin-bottom:22px;
        }

        .info-line{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding-bottom:12px;
            margin-bottom:12px;
            border-bottom:1px solid rgba(59,130,246,0.18);
        }

        .info-line .label{
            font-size:0.82rem;
            color:var(--ink-soft);
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.3px;
        }

        .info-line .value{
            font-size:0.95rem;
            font-weight:700;
            color:var(--ink);
            text-align:right;
            word-break:break-word;
        }

        .info-stats{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            padding-bottom:16px;
        }

        .info-stat{
            background:rgba(255,255,255,0.04);
            border:1px solid rgba(59,130,246,0.18);
            border-radius:14px;
            padding:12px 10px;
            text-align:center;
        }

        .info-stat span{
            display:block;
        }

        .info-stat .stat-label{
            font-size:0.72rem;
            color:var(--ink-soft);
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:0.3px;
            margin-bottom:6px;
        }

        .info-stat .stat-value{
            font-family:'Poppins','Inter',sans-serif;
            font-size:1.05rem;
            font-weight:800;
        }

        .info-stat.balance .stat-value{
            color:var(--gold-light);
        }

        /* ---------- Form ---------- */
        .row{margin-bottom:16px}

        label{
            display:flex;
            align-items:center;
            gap:7px;
            margin-bottom:8px;
            font-size:0.9rem;
            font-weight:700;
            color:var(--ink);
        }

        label i{
            color:var(--sky);
            font-size:0.95rem;
        }

        .input-wrap{
            position:relative;
        }

        .input-wrap .prefix{
            position:absolute;
            left:16px;
            top:50%;
            transform:translateY(-50%);
            font-weight:700;
            color:#4a5a7a;
            pointer-events:none;
        }

        .input-wrap input[type="number"]{
            padding-left:34px;
        }

        input, textarea, select{
            width:100%;
            padding:13px 16px;
            border:1px solid rgba(59,130,246,0.3);
            border-radius:14px;
            font-size:15px;
            font-family:inherit;
            background:rgba(8,16,50,0.55);
            color:var(--ink);
            appearance:none;
            -webkit-appearance:none;
            transition:border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input::placeholder, textarea::placeholder{
            color:rgba(185,212,245,0.5);
        }

        input:focus, textarea:focus, select:focus{
            outline:none;
            border-color:rgba(246,203,8,0.6);
            box-shadow:0 0 0 4px rgba(246,203,8,0.14);
        }

        select{
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%237dd3fc'><path d='M7 10l5 5 5-5z'/></svg>");
            background-repeat:no-repeat;
            background-position:right 14px center;
            background-size:18px;
            padding-right:40px;
            cursor:pointer;
        }

        select option{
            background:#0a1440;
            color:var(--ink);
        }

        textarea{
            resize:vertical;
            min-height:88px;
        }

        .actions{
            display:flex;
            gap:12px;
            margin-top:22px;
        }

        .actions button, .actions a{
            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            text-align:center;
            padding:14px;
            border:none;
            border-radius:14px;
            text-decoration:none;
            font-size:15px;
            font-weight:700;
            font-family:inherit;
            cursor:pointer;
            transition:transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .actions button{
            background:linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
            color:#2b1a00;
            box-shadow:0 10px 22px rgba(246,203,8,0.3);
        }

        .actions button:hover{
            transform:translateY(-2px);
            box-shadow:0 14px 26px rgba(246,203,8,0.4);
        }

        .actions a{
            background:rgba(59,130,246,0.12);
            color:var(--ink);
            border:1px solid rgba(59,130,246,0.4);
        }

        .actions a:hover{
            transform:translateY(-2px);
            background:rgba(59,130,246,0.2);
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 480px){
            body{
                padding:16px;
                align-items:flex-start;
            }
            .card{
                padding:20px;
                border-radius:20px;
            }
            h1{font-size:1.2rem;}
            .info-stats{
                grid-template-columns:1fr 1fr;
            }
            .info-stats .info-stat.balance{
                grid-column:1 / -1;
            }
            .actions{
                flex-direction:column;
            }
            .actions button, .actions a{
                width:100%;
            }
        }
    </style>
</head>
<body>
    <form method="POST" class="card">
        <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">

        <div class="card-header">
            <div class="icon-wrap"><i class="bi bi-cash-coin"></i></div>
            <div>
                <h1>Update Receivable</h1>
                <p>Record a new payment against this sale</p>
            </div>
        </div>

        <div class="info">
            <div class="info-line">
                <span class="label">Sales No.</span>
                <span class="value"><?php echo htmlspecialchars($data['sales_no']); ?></span>
            </div>
            <div class="info-line" style="border-bottom:none; margin-bottom:14px;">
                <span class="label">Customer</span>
                <span class="value"><?php echo htmlspecialchars($data['customer_name']); ?></span>
            </div>

            <div class="info-stats">
                <div class="info-stat">
                    <span class="stat-label">Total</span>
                    <span class="stat-value">₱<?php echo number_format((float)$data['total_amount'], 2); ?></span>
                </div>
                <div class="info-stat">
                    <span class="stat-label">Paid</span>
                    <span class="stat-value">₱<?php echo number_format((float)$data['amount_paid'], 2); ?></span>
                </div>
                <div class="info-stat balance">
                    <span class="stat-label">Balance</span>
                    <span class="stat-value">₱<?php echo number_format((float)$data['balance_due'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <label><i class="bi bi-wallet2"></i>Additional Payment</label>
            <div class="input-wrap">
                <span class="prefix">₱</span>
                <input type="number" step="0.01" min="0.01" name="additional_payment" placeholder="0.00" required>
            </div>
        </div>

        <div class="row">
            <label><i class="bi bi-calendar-event"></i>Payment Date</label>
            <input type="date" name="payment_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
        </div>

        <div class="row">
            <label><i class="bi bi-credit-card"></i>Payment Method</label>
            <select name="payment_method" required>
                <option value="">Select method</option>
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
                <option value="Maya">Maya</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="row">
            <label><i class="bi bi-hash"></i>Reference Number (optional)</label>
            <input type="text" name="reference_number" placeholder="e.g. transaction/receipt no.">
        </div>

        <div class="row">
            <label><i class="bi bi-chat-left-text"></i>Notes / Remarks</label>
            <textarea name="notes" rows="4" placeholder="Add any notes about this payment..."><?php echo htmlspecialchars($data['notes'] ?? ''); ?></textarea>
        </div>

        <div class="actions">
            <a href="/NexGen/CODE/PHP/accounts_receivable.php"><i class="bi bi-x-lg"></i>Cancel</a>
            <button type="submit"><i class="bi bi-check-lg"></i>Save Payment</button>
        </div>
    </form>
</body>
</html>