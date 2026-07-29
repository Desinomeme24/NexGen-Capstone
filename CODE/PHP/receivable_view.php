<?php
session_start();
require_once("config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

if ((int)($_SESSION['can_accounts_receivable'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid receivable ID.']);
    exit();
}

// Confirm the receivable exists before querying its history
$check = $conn->prepare("SELECT id, customer_id FROM accounts_receivable WHERE id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'Receivable not found.']);
    exit();
}

$customerId = (int)$exists['customer_id'];

$stmt = $conn->prepare("
    SELECT payment_date, amount, payment_method, reference_number, remarks
    FROM payment_history
    WHERE receivable_id = ?
    ORDER BY payment_date DESC, id DESC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load payment history.']);
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = [
        'date'      => !empty($row['payment_date']) ? date('M d, Y', strtotime($row['payment_date'])) : 'N/A',
        'amount'    => number_format((float)$row['amount'], 2),
        'method'    => $row['payment_method'] ?? '',
        'reference' => !empty($row['reference_number']) ? $row['reference_number'] : '-',
        'remarks'   => $row['remarks'] ?? '',
    ];
}
$stmt->close();

/*
| Other transactions by the same customer — lets the cashier see this
| customer's history at a glance instead of hunting through the table.
| Excludes the record currently being viewed.
*/
$otherStmt = $conn->prepare("
    SELECT
        ar.id,
        s.sales_no,
        ar.total_amount,
        ar.amount_paid,
        ar.balance_due,
        ar.due_date,
        ar.created_at,
        CASE
            WHEN ar.balance_due <= 0 THEN 'Paid'
            WHEN ar.due_date IS NOT NULL
                 AND ar.due_date <> ''
                 AND ar.due_date < CURDATE()
                 AND ar.balance_due > 0 THEN 'Overdue'
            WHEN ar.amount_paid > 0
                 AND ar.balance_due > 0 THEN 'Partially Paid'
            ELSE 'Unpaid'
        END AS live_status
    FROM accounts_receivable ar
    INNER JOIN sales s ON ar.sale_id = s.id
    WHERE ar.customer_id = ?
      AND ar.id <> ?
    ORDER BY ar.created_at DESC, ar.id DESC
    LIMIT 10
");

$otherTransactions = [];

if ($otherStmt) {
    $otherStmt->bind_param("ii", $customerId, $id);
    $otherStmt->execute();
    $otherResult = $otherStmt->get_result();

    while ($row = $otherResult->fetch_assoc()) {
        $otherTransactions[] = [
            'id'          => (int)$row['id'],
            'sales_no'    => $row['sales_no'] ?? '-',
            'date'        => !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A',
            'due_date'    => !empty($row['due_date']) ? $row['due_date'] : 'N/A',
            'total'       => number_format((float)$row['total_amount'], 2),
            'paid'        => number_format((float)$row['amount_paid'], 2),
            'balance'     => number_format((float)$row['balance_due'], 2),
            'status'      => $row['live_status'],
        ];
    }
    $otherStmt->close();
}

echo json_encode([
    'payments'           => $payments,
    'other_transactions' => $otherTransactions,
]);