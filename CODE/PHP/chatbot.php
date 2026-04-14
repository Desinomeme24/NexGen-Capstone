<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("config.php");
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'ask') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'reply' => 'Session expired. Please log in again.'
        ]);
        exit();
    }
    return;
}

$chatbotRole = $_SESSION['role'] ?? '';
$chatbotIsOwner = $chatbotRole === 'owner';
$chatbotIsEmployee = $chatbotRole === 'employee';

/*
|--------------------------------------------------------------------------
| AJAX ENDPOINT
|--------------------------------------------------------------------------
*/
if (isset($_GET['action']) && $_GET['action'] === 'ask') {
    header('Content-Type: application/json');

    $question = trim($_POST['message'] ?? '');

    if ($question === '') {
        echo json_encode([
            'success' => true,
            'reply' => "Please type your question first."
        ]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_normalize($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    function cb_contains_any($text, array $needles) {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    function cb_money($amount) {
        return '₱' . number_format((float)$amount, 2);
    }

    function cb_percent($value) {
        return number_format((float)$value, 2) . '%';
    }

    function cb_date_range_today() {
        return [
            date('Y-m-d 00:00:00'),
            date('Y-m-d 23:59:59'),
            'Today'
        ];
    }

    function cb_date_range_yesterday() {
        return [
            date('Y-m-d 00:00:00', strtotime('-1 day')),
            date('Y-m-d 23:59:59', strtotime('-1 day')),
            'Yesterday'
        ];
    }

    function cb_date_range_week() {
        $start = new DateTime();
        $start->modify('monday this week');
        $end = new DateTime();
        $end->modify('sunday this week');
        return [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
            'This Week'
        ];
    }

    function cb_date_range_last_week() {
        $start = new DateTime();
        $start->modify('monday last week');
        $end = new DateTime();
        $end->modify('sunday last week');
        return [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
            'Last Week'
        ];
    }

    function cb_date_range_month() {
        $start = new DateTime('first day of this month');
        $end = new DateTime('last day of this month');
        return [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
            'This Month'
        ];
    }

    function cb_date_range_last_month() {
        $start = new DateTime('first day of last month');
        $end = new DateTime('last day of last month');
        return [
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
            'Last Month'
        ];
    }

    function cb_try_custom_range($text) {
        if (preg_match('/from\s+([a-zA-Z]+\s+\d{1,2}|\d{4}\-\d{2}\-\d{2})\s+to\s+([a-zA-Z]+\s+\d{1,2}|\d{4}\-\d{2}\-\d{2})/i', $text, $m)) {
            $start = strtotime($m[1] . ' ' . date('Y'));
            $end = strtotime($m[2] . ' ' . date('Y'));

            if ($start && $end) {
                return [
                    date('Y-m-d 00:00:00', $start),
                    date('Y-m-d 23:59:59', $end),
                    date('M d, Y', $start) . ' to ' . date('M d, Y', $end)
                ];
            }
        }
        return null;
    }

    function cb_get_summary($conn, $startDate, $endDate) {
        $grossRevenue = 0;
        $cogs = 0;
        $transactions = 0;
        $netProfit = 0;

        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) AS gross_revenue,
                COUNT(*) AS total_transactions
            FROM sales
            WHERE sale_date BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $grossRevenue = (float)($row['gross_revenue'] ?? 0);
        $transactions = (int)($row['total_transactions'] ?? 0);

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(p.cost_price * si.quantity), 0) AS total_cogs
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            WHERE s.sale_date BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $cogs = (float)($row['total_cogs'] ?? 0);
        $netProfit = $grossRevenue - $cogs;

        return [
            'gross_revenue' => $grossRevenue,
            'cogs' => $cogs,
            'net_profit' => $netProfit,
            'transactions' => $transactions
        ];
    }

    function cb_growth_from_summaries($currentRevenue, $previousRevenue) {
        if ($previousRevenue > 0) {
            return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        }
        if ($currentRevenue > 0) {
            return 100;
        }
        return 0;
    }

    function cb_compare_label($current, $previous) {
        if ($current > $previous) return 'increased';
        if ($current < $previous) return 'decreased';
        return 'stayed the same';
    }

    function cb_get_top_products_by_qty($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT p.product_name, COALESCE(SUM(si.quantity), 0) AS qty_sold
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY p.id, p.product_name
            ORDER BY qty_sold DESC, p.product_name ASC
            LIMIT ?
        ");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_top_products_by_revenue($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT p.product_name, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY p.id, p.product_name
            ORDER BY revenue DESC, p.product_name ASC
            LIMIT ?
        ");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_slow_products($conn, $startDate, $endDate, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT p.product_name, COALESCE(SUM(CASE WHEN s.sale_date BETWEEN ? AND ? THEN si.quantity ELSE 0 END), 0) AS qty_sold
            FROM products p
            LEFT JOIN sale_items si ON p.id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.id
            GROUP BY p.id, p.product_name
            ORDER BY qty_sold ASC, p.product_name ASC
            LIMIT ?
        ");
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_no_recent_sales($conn, $days = 30, $limit = 10) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT p.product_name
            FROM products p
            LEFT JOIN sale_items si ON p.id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.id AND s.sale_date >= (NOW() - INTERVAL ? DAY)
            GROUP BY p.id, p.product_name
            HAVING COUNT(s.id) = 0
            ORDER BY p.product_name ASC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $days, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_category_units($conn, $startDate, $endDate) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT c.category_name, COALESCE(SUM(si.quantity), 0) AS units_sold
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            INNER JOIN categories c ON p.category_id = c.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY c.id, c.category_name
            ORDER BY units_sold DESC, c.category_name ASC
        ");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_category_revenue($conn, $startDate, $endDate) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT c.category_name, COALESCE(SUM(si.quantity * si.unit_price), 0) AS revenue
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            INNER JOIN products p ON si.product_id = p.id
            INNER JOIN categories c ON p.category_id = c.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY c.id, c.category_name
            ORDER BY revenue DESC, c.category_name ASC
        ");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_low_stock($conn, $limit = 10) {
        $items = [];
        $result = $conn->query("
            SELECT product_name, product_code, stock_quantity, reorder_level
            FROM products
            WHERE is_active = 1
              AND stock_quantity <= reorder_level
              AND stock_quantity > 0
            ORDER BY stock_quantity ASC, product_name ASC
            LIMIT {$limit}
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        return $items;
    }

    function cb_get_out_of_stock($conn, $limit = 10) {
        $items = [];
        $result = $conn->query("
            SELECT product_name, product_code, stock_quantity
            FROM products
            WHERE is_active = 1
              AND stock_quantity <= 0
            ORDER BY product_name ASC
            LIMIT {$limit}
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        return $items;
    }

    function cb_get_nearing_depletion($conn, $limit = 10) {
        $items = [];
        $result = $conn->query("
            SELECT product_name, product_code, stock_quantity, reorder_level
            FROM products
            WHERE is_active = 1
              AND stock_quantity > reorder_level
              AND stock_quantity <= (reorder_level + 3)
            ORDER BY stock_quantity ASC, product_name ASC
            LIMIT {$limit}
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        return $items;
    }

    function cb_get_recent_stock_movements($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT sm.movement_type, sm.quantity, sm.created_at, p.product_name, p.product_code
            FROM stock_movements sm
            INNER JOIN products p ON sm.product_id = p.id
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_recent_products($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT product_name, product_code, created_at
            FROM products
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_product_stock_by_name($conn, $name) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT product_name, product_code, stock_quantity, reorder_level, on_order_level
            FROM products
            WHERE product_name LIKE ?
            ORDER BY product_name ASC
            LIMIT 5
        ");
        $search = '%' . $name . '%';
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    function cb_get_sales_drop_alert($conn) {
        [$curStart, $curEnd] = cb_date_range_week();
        [$prevStart, $prevEnd] = cb_date_range_last_week();

        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);

        if ($current['gross_revenue'] < $previous['gross_revenue']) {
            $diff = $previous['gross_revenue'] - $current['gross_revenue'];
            return "Sales dropped this week by " . cb_money($diff) . " compared to last week.";
        }

        return null;
    }

    function cb_get_unusual_stock_movements($conn, $limit = 5) {
        $items = [];
        $stmt = $conn->prepare("
            SELECT sm.movement_type, sm.quantity, sm.created_at, p.product_name
            FROM stock_movements sm
            INNER JOIN products p ON sm.product_id = p.id
            WHERE sm.quantity >= 20
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | RECEIVABLE HELPERS
    |--------------------------------------------------------------------------
    */
    function cb_table_exists($conn, $tableName) {
        $tableName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
        return $result && $result->num_rows > 0;
    }

    function cb_get_receivable_summary($conn) {
        if (!cb_table_exists($conn, 'accounts_receivable')) {
            return null;
        }

        $summary = [
            'total_receivables' => 0,
            'total_balance_due' => 0,
            'overdue_count' => 0,
            'unpaid_count' => 0,
            'partial_count' => 0
        ];

        $result = $conn->query("
            SELECT
                COUNT(*) AS total_receivables,
                COALESCE(SUM(balance_due), 0) AS total_balance_due,
                SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN status = 'Partially Paid' THEN 1 ELSE 0 END) AS partial_count
            FROM accounts_receivable
        ");

        if ($result) {
            $summary = $result->fetch_assoc();
        }

        return $summary;
    }

    function cb_get_overdue_receivables($conn, $limit = 10) {
        $items = [];
        if (!cb_table_exists($conn, 'accounts_receivable')) {
            return $items;
        }

        $stmt = $conn->prepare("
            SELECT ar.balance_due, ar.due_date, c.customer_name, s.sales_no
            FROM accounts_receivable ar
            INNER JOIN customers c ON ar.customer_id = c.id
            INNER JOIN sales s ON ar.sale_id = s.id
            WHERE ar.status = 'Overdue'
               OR (ar.balance_due > 0 AND ar.due_date IS NOT NULL AND ar.due_date < CURDATE())
            ORDER BY ar.due_date ASC, ar.balance_due DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();

        return $items;
    }

    function cb_get_followup_priority($conn, $limit = 5) {
        $items = [];
        if (!cb_table_exists($conn, 'accounts_receivable')) {
            return $items;
        }

        $stmt = $conn->prepare("
            SELECT c.customer_name, s.sales_no, ar.balance_due, ar.due_date, ar.status
            FROM accounts_receivable ar
            INNER JOIN customers c ON ar.customer_id = c.id
            INNER JOIN sales s ON ar.sale_id = s.id
            WHERE ar.balance_due > 0
            ORDER BY
                CASE WHEN ar.status = 'Overdue' THEN 1 ELSE 2 END,
                ar.due_date ASC,
                ar.balance_due DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | COMMAND ROUTER
    |--------------------------------------------------------------------------
    */
    $normalized = cb_normalize($question);
    $reply = "I’m sorry, I couldn’t understand that yet. Try asking about sales, inventory, receivables, alerts, recommendations, or system help.";

    /*
    |--------------------------------------------------------------------------
    | NAVIGATION
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['open inventory', 'open inventory management', 'go to inventory'])) {
        $reply = "I can open Inventory Management for you.\n\n[OPEN_CONFIRM]|Inventory Management|/NexGen/CODE/PHP/inventory_management.php";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['open sales analytics', 'open analytics', 'go to analytics'])) {
        if ($chatbotIsOwner) {
            $reply = "I can open Sales Analytics for you.\n\n[OPEN_CONFIRM]|Sales Analytics|/NexGen/CODE/PHP/sales_analytics.php";
        } else {
            $reply = "Sales Analytics is only available for the owner account.";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['open sales recording', 'go to sales recording', 'open sales page'])) {
        if ($chatbotIsEmployee) {
            $reply = "I can open Sales Recording for you.\n\n[OPEN_CONFIRM]|Sales Recording|/NexGen/CODE/PHP/sales_recording.php";
        } elseif ($chatbotIsOwner) {
            $reply = "Your owner account mainly uses Sales Analytics, but I can still open the Sales page if your permissions allow it.\n\n[OPEN_CONFIRM]|Sales Recording|/NexGen/CODE/PHP/sales_recording.php";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['open receivables', 'open accounts receivable', 'go to receivables'])) {
        $reply = "I can open Accounts Receivable for you.\n\n[OPEN_CONFIRM]|Accounts Receivable|/NexGen/CODE/PHP/accounts_receivable.php";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | HELP / TRAINING
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['how do i add a new product', 'how to add a new product', 'how do i add product'])) {
        $reply = "To add a new product:\n1. Open Inventory Management.\n2. Click '+ Add Product'.\n3. Fill in product code, product name, category, unit, cost price, selling price, and stock quantity.\n4. If you are the owner, also set reorder level and on-order level.\n5. Click 'Save Product'.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how do i record a sale', 'how to record a sale'])) {
        $reply = "To record a sale:\n1. Open Sales Recording.\n2. Click 'New Sale'.\n3. Select the customer.\n4. Choose payment status, payment method, and order status.\n5. Add product items and quantities.\n6. Review the grand total.\n7. Click 'Save Sale'.\nIf the payment status is Unpaid or Partially Paid, a receivable record will be created automatically.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how do i update stock', 'how to update stock', 'how do i record a stock out', 'how do i record stock out', 'how do i record stock in'])) {
        $reply = "To update stock:\n1. Open Inventory Management.\n2. Find the product in the table.\n3. Click 'Stock In/Out'.\n4. Choose movement type: Stock In or Stock Out.\n5. Enter quantity and optional remarks.\n6. Click 'Save Movement'.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how do i manage categories', 'how to manage categories'])) {
        $reply = "To manage categories:\n1. Open Inventory Management.\n2. Click 'Manage Categories'.\n3. Add a new category name.\n4. Save it, and it will appear in your category list.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how do i filter analytics', 'how to filter analytics'])) {
        $reply = "To filter analytics:\n1. Open Sales Analytics.\n2. Choose Today, This Week, This Month, or Custom Range.\n3. The summary cards, charts, category results, and top products will update based on that selected period.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how do i view receivables', 'how to view receivables'])) {
        $reply = "To view receivables:\n1. Open Accounts Receivable.\n2. Review the summary cards for total receivables, overdue, unpaid, and partial balances.\n3. Use search or status filters if needed.\n4. Click 'Update Payment' to record a follow-up payment.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS INSIGHT EXPLANATION
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['what is gross revenue', 'explain gross revenue'])) {
        $reply = "Gross revenue is the total amount of money earned from sales before subtracting any costs such as product cost or expenses.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is cogs', 'explain cogs'])) {
        $reply = "COGS means Cost of Goods Sold. It is the total product cost of the items that were sold. It helps measure how much the sold inventory cost your business.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is net profit', 'explain net profit'])) {
        $reply = "Net profit is the amount left after subtracting Cost of Goods Sold from gross revenue in your current analytics computation.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is reorder level', 'explain reorder level'])) {
        $reply = "Reorder level is the minimum stock quantity that tells you when to restock a product. When stock reaches or falls below that level, the item should be replenished.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is sales growth', 'explain sales growth'])) {
        $reply = "Sales growth measures whether your sales increased or decreased compared to a previous period, such as last week or last month. It is shown as a percentage.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is top selling products', 'explain top selling products'])) {
        $reply = "Top-selling products are the items with the highest sales performance, either by quantity sold or by revenue contribution, depending on the report.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is stock movement', 'explain stock movement'])) {
        $reply = "Stock movement records inventory changes such as Stock In and Stock Out. It helps track why product quantity increased or decreased.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what is accounts receivable', 'explain accounts receivable'])) {
        $reply = "Accounts receivable refers to the money customers still owe your business for unpaid or partially paid sales.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY MONITORING
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['which items are low stock', 'low stock', 'products are low stock'])) {
        $items = cb_get_low_stock($conn, 10);
        if (!empty($items)) {
            $lines = ["These are the low-stock items:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']} ({$item['product_code']}) - {$item['stock_quantity']} left, reorder at {$item['reorder_level']}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "There are no low-stock items right now.";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['out of stock', 'out-of-stock', 'do i have any out of stock products'])) {
        $items = cb_get_out_of_stock($conn, 10);
        if (!empty($items)) {
            $lines = ["These items are out of stock:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']} ({$item['product_code']})";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "You currently have no out-of-stock products.";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['nearing depletion', 'products nearing depletion'])) {
        $items = cb_get_nearing_depletion($conn, 10);
        if (!empty($items)) {
            $lines = ["These items are nearing depletion:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']} - Stock: {$item['stock_quantity']}, Reorder: {$item['reorder_level']}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No products are currently nearing depletion beyond the low-stock list.";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['recent stock movement', 'recent stock movements', 'show recent stock movement'])) {
        $items = cb_get_recent_stock_movements($conn, 5);
        if (!empty($items)) {
            $lines = ["Here are the recent stock movements:"];
            foreach ($items as $item) {
                $label = $item['movement_type'] === 'stock_in' ? 'Stock In' : 'Stock Out';
                $lines[] = "• {$label} - {$item['product_name']} ({$item['product_code']}) | Qty: {$item['quantity']} | " . date('M d, Y h:i A', strtotime($item['created_at']));
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No recent stock movements were found.";
        }
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (preg_match('/stock of (.+)$/i', $question, $matches) || preg_match('/reorder level of (.+)$/i', $question, $matches)) {
        $productNameSearch = trim($matches[1]);
        $items = cb_get_product_stock_by_name($conn, $productNameSearch);

        if (!empty($items)) {
            $lines = ["Here’s what I found:"];
            foreach ($items as $item) {
                $onOrder = isset($item['on_order_level']) ? (int)$item['on_order_level'] : 0;
                $lines[] = "• {$item['product_name']} ({$item['product_code']}) - Stock: {$item['stock_quantity']}, Reorder: {$item['reorder_level']}, On Order: {$onOrder}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "I could not find a matching product for \"{$productNameSearch}\".";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | ALERTS
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['what alerts do i have today', 'any urgent inventory issue', 'alerts today', 'urgent issues'])) {
        $alerts = [];

        $lowStock = cb_get_low_stock($conn, 5);
        if (!empty($lowStock)) {
            $alerts[] = "Low-stock alert: " . count($lowStock) . " item(s) need attention.";
        }

        $outStock = cb_get_out_of_stock($conn, 5);
        if (!empty($outStock)) {
            $alerts[] = "Out-of-stock alert: " . count($outStock) . " item(s) are out of stock.";
        }

        $salesDrop = cb_get_sales_drop_alert($conn);
        if (!empty($salesDrop)) {
            $alerts[] = $salesDrop;
        }

        $overdue = cb_get_overdue_receivables($conn, 5);
        if (!empty($overdue)) {
            $alerts[] = "Overdue receivable alert: " . count($overdue) . " account(s) need follow-up.";
        }

        $unusual = cb_get_unusual_stock_movements($conn, 5);
        if (!empty($unusual)) {
            $alerts[] = "Unusual movement alert: " . count($unusual) . " stock movement(s) had unusually large quantities.";
        }

        $recentProducts = cb_get_recent_products($conn, 3);
        if (!empty($recentProducts)) {
            $alerts[] = "New product summary: " . count($recentProducts) . " recently added product(s).";
        }

        if (!empty($alerts)) {
            $reply = "Here are your current alerts:\n• " . implode("\n• ", $alerts);
        } else {
            $reply = "You have no urgent alerts right now.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | DATE RANGE DETECTION
    |--------------------------------------------------------------------------
    */
    $range = null;
    if (cb_contains_any($normalized, ['today'])) {
        $range = cb_date_range_today();
    } elseif (cb_contains_any($normalized, ['last week'])) {
        $range = cb_date_range_last_week();
    } elseif (cb_contains_any($normalized, ['this week', 'weekly'])) {
        $range = cb_date_range_week();
    } elseif (cb_contains_any($normalized, ['last month'])) {
        $range = cb_date_range_last_month();
    } elseif (cb_contains_any($normalized, ['this month', 'monthly'])) {
        $range = cb_date_range_month();
    } else {
        $range = cb_try_custom_range($question);
    }

    /*
    |--------------------------------------------------------------------------
    | OWNER-HEAVY SALES INTELLIGENCE
    |--------------------------------------------------------------------------
    */
    if (
        !$chatbotIsOwner &&
        cb_contains_any($normalized, [
            'sales summary', 'gross revenue', 'cogs', 'net profit', 'sales growth',
            'compare this month', 'top products', 'top selling', 'weakest category',
            'show my profit', 'highest selling product'
        ])
    ) {
        echo json_encode([
            'success' => true,
            'reply' => 'Detailed sales analytics questions are mainly available for the owner account.'
        ]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | SALES SUMMARY / DATE FILTERING
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['sales summary', 'show my profit', 'what are my sales', 'gross revenue', 'net profit', 'cogs', 'transactions'])) {
        if (!$range) {
            $range = cb_date_range_today();
        }

        [$start, $end, $label] = $range;
        $summary = cb_get_summary($conn, $start, $end);

        if (cb_contains_any($normalized, ['gross revenue'])) {
            $reply = "Your gross revenue for {$label} is " . cb_money($summary['gross_revenue']) . ".";
        } elseif (cb_contains_any($normalized, ['cogs'])) {
            $reply = "Your COGS for {$label} is " . cb_money($summary['cogs']) . ".";
        } elseif (cb_contains_any($normalized, ['net profit', 'profit'])) {
            $reply = "Your net profit for {$label} is " . cb_money($summary['net_profit']) . ".";
        } elseif (cb_contains_any($normalized, ['transactions'])) {
            $reply = "You have " . number_format($summary['transactions']) . " transaction(s) for {$label}.";
        } else {
            $reply =
                "{$label} sales summary:\n" .
                "• Gross Revenue: " . cb_money($summary['gross_revenue']) . "\n" .
                "• COGS: " . cb_money($summary['cogs']) . "\n" .
                "• Net Profit: " . cb_money($summary['net_profit']) . "\n" .
                "• Total Transactions: " . number_format($summary['transactions']);
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | COMPARISONS
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['compare today vs yesterday', 'today vs yesterday'])) {
        [$curStart, $curEnd] = cb_date_range_today();
        [$prevStart, $prevEnd] = cb_date_range_yesterday();

        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);

        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);

        $reply =
            "Today vs Yesterday:\n" .
            "• Today Gross Revenue: " . cb_money($current['gross_revenue']) . "\n" .
            "• Yesterday Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n" .
            "• Sales {$label}\n" .
            "• Growth: " . cb_percent($growth);

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['compare this week vs last week', 'this week vs last week', 'why did sales drop this week'])) {
        [$curStart, $curEnd] = cb_date_range_week();
        [$prevStart, $prevEnd] = cb_date_range_last_week();

        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);

        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);

        $explain = $label === 'decreased'
            ? "Sales dropped this week compared to last week."
            : ($label === 'increased' ? "Sales improved this week compared to last week." : "Sales stayed at the same level this week.");

        $reply =
            "This Week vs Last Week:\n" .
            "• This Week Gross Revenue: " . cb_money($current['gross_revenue']) . "\n" .
            "• Last Week Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n" .
            "• Result: {$explain}\n" .
            "• Growth: " . cb_percent($growth);

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['compare this month to last month', 'compare this month vs last month', 'this month vs last month'])) {
        [$curStart, $curEnd] = cb_date_range_month();
        [$prevStart, $prevEnd] = cb_date_range_last_month();

        $current = cb_get_summary($conn, $curStart, $curEnd);
        $previous = cb_get_summary($conn, $prevStart, $prevEnd);

        $growth = cb_growth_from_summaries($current['gross_revenue'], $previous['gross_revenue']);
        $label = cb_compare_label($current['gross_revenue'], $previous['gross_revenue']);

        $reply =
            "This Month vs Last Month:\n" .
            "• This Month Gross Revenue: " . cb_money($current['gross_revenue']) . "\n" .
            "• Last Month Gross Revenue: " . cb_money($previous['gross_revenue']) . "\n" .
            "• Sales {$label}\n" .
            "• Growth: " . cb_percent($growth);

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT PERFORMANCE
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['top 5 products', 'top products', 'top selling products', 'highest selling product'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_top_products_by_qty($conn, $start, $end, 5);

        if (!empty($items)) {
            $lines = ["Top-selling products for {$label} by quantity:"];
            $rank = 1;
            foreach ($items as $item) {
                $lines[] = "{$rank}. {$item['product_name']} - {$item['qty_sold']} unit(s)";
                $rank++;
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No top-selling products were found for {$label}.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['rank products by revenue', 'revenue contribution'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_top_products_by_revenue($conn, $start, $end, 5);

        if (!empty($items)) {
            $lines = ["Top products for {$label} by revenue contribution:"];
            $rank = 1;
            foreach ($items as $item) {
                $lines[] = "{$rank}. {$item['product_name']} - " . cb_money($item['revenue']);
                $rank++;
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No product revenue data was found for {$label}.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['slow moving products', 'products are not selling well', 'not selling well'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_slow_products($conn, $start, $end, 5);

        if (!empty($items)) {
            $lines = ["Slow-moving products for {$label}:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']} - {$item['qty_sold']} unit(s)";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No slow-moving product data was found.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['no recent sales', 'products with no recent sales'])) {
        $items = cb_get_no_recent_sales($conn, 30, 10);

        if (!empty($items)) {
            $lines = ["Products with no sales in the last 30 days:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['product_name']}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "All listed products had sales activity within the last 30 days.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY PERFORMANCE
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['which category sold the most', 'top selling category', 'best category'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);

        if (!empty($items)) {
            $top = $items[0];
            $reply = "The top-selling category for {$label} is {$top['category_name']} with {$top['units_sold']} unit(s) sold.";
        } else {
            $reply = "No category sales data was found for {$label}.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['lowest performing category', 'weakest category', 'which category is the weakest'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);

        if (!empty($items)) {
            $lowest = $items[count($items) - 1];
            $reply = "The weakest category for {$label} is {$lowest['category_name']} with {$lowest['units_sold']} unit(s) sold.";
        } else {
            $reply = "No category data was found for {$label}.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['compare categories', 'categories by units sold'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_category_units($conn, $start, $end);

        if (!empty($items)) {
            $lines = ["Category comparison by units sold for {$label}:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['category_name']} - {$item['units_sold']} unit(s)";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No category comparison data was found.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['compare categories by revenue', 'category revenue contribution'])) {
        if (!$range) {
            $range = cb_date_range_month();
        }
        [$start, $end, $label] = $range;
        $items = cb_get_category_revenue($conn, $start, $end);

        if (!empty($items)) {
            $lines = ["Category comparison by revenue for {$label}:"];
            foreach ($items as $item) {
                $lines[] = "• {$item['category_name']} - " . cb_money($item['revenue']);
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "No category revenue data was found.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | RECOMMENDATIONS
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['which product should i promote', 'recommend which products to promote', 'marketing focus'])) {
        [$start, $end] = cb_date_range_month();
        $slow = cb_get_slow_products($conn, $start, $end, 3);

        if (!empty($slow)) {
            $lines = ["Products that may need promotion:"];
            foreach ($slow as $item) {
                $lines[] = "• {$item['product_name']} - only {$item['qty_sold']} unit(s) sold";
            }
            $reply = implode("\n", $lines) . "\nThese items may benefit from discounts, bundling, or better product visibility.";
        } else {
            $reply = "I could not determine promotion priorities right now.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['recommend which products to restock', 'which products should i restock'])) {
        $low = cb_get_low_stock($conn, 5);
        $out = cb_get_out_of_stock($conn, 5);

        $lines = [];
        if (!empty($out)) {
            $lines[] = "Restock immediately:";
            foreach ($out as $item) {
                $lines[] = "• {$item['product_name']} - currently out of stock";
            }
        }
        if (!empty($low)) {
            $lines[] = "Restock soon:";
            foreach ($low as $item) {
                $lines[] = "• {$item['product_name']} - {$item['stock_quantity']} left";
            }
        }

        $reply = !empty($lines)
            ? implode("\n", $lines)
            : "You currently have no urgent restocking recommendation.";
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['what categories need attention', 'recommend what categories need attention'])) {
        [$start, $end, $label] = cb_date_range_month();
        $items = cb_get_category_units($conn, $start, $end);

        if (!empty($items)) {
            $lowest = $items[count($items) - 1];
            $reply = "The category that needs the most attention for {$label} is {$lowest['category_name']}, because it has the lowest units sold.";
        } else {
            $reply = "I could not determine category priorities right now.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | RECEIVABLES
    |--------------------------------------------------------------------------
    */
    if (cb_contains_any($normalized, ['overdue receivable', 'overdue receivables', 'do i have overdue receivables'])) {
        $items = cb_get_overdue_receivables($conn, 10);

        if (!empty($items)) {
            $lines = ["These receivables are overdue:"];
            foreach ($items as $item) {
                $due = !empty($item['due_date']) ? $item['due_date'] : 'No due date';
                $lines[] = "• {$item['customer_name']} | {$item['sales_no']} | Balance: " . cb_money($item['balance_due']) . " | Due: {$due}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "You currently have no overdue receivables.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['how many unpaid balances do i have', 'unpaid balances', 'receivable summary'])) {
        $summary = cb_get_receivable_summary($conn);

        if ($summary !== null) {
            $reply =
                "Accounts receivable summary:\n" .
                "• Total Receivables: " . (int)$summary['total_receivables'] . "\n" .
                "• Total Balance Due: " . cb_money($summary['total_balance_due']) . "\n" .
                "• Overdue: " . (int)$summary['overdue_count'] . "\n" .
                "• Unpaid: " . (int)$summary['unpaid_count'] . "\n" .
                "• Partially Paid: " . (int)$summary['partial_count'];
        } else {
            $reply = "The accounts receivable table is not available yet.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    if (cb_contains_any($normalized, ['which customer account should i follow up first', 'follow up priority', 'follow-up priorities'])) {
        $items = cb_get_followup_priority($conn, 5);

        if (!empty($items)) {
            $lines = ["These accounts should be followed up first:"];
            foreach ($items as $item) {
                $due = !empty($item['due_date']) ? $item['due_date'] : 'No due date';
                $lines[] = "• {$item['customer_name']} | {$item['sales_no']} | Balance: " . cb_money($item['balance_due']) . " | Status: {$item['status']} | Due: {$due}";
            }
            $reply = implode("\n", $lines);
        } else {
            $reply = "There are no receivable follow-up priorities at the moment.";
        }

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | FALLBACK
    |--------------------------------------------------------------------------
    */
    echo json_encode([
        'success' => true,
        'reply' => $reply
    ]);
    exit();
}
?>

<div class="nx-chatbot-widget" id="nxChatbotWidget">
    <button type="button" class="nx-chatbot-toggle" id="nxChatbotToggle">
        <span class="nx-chatbot-toggle-text">Ask NextGen AI</span>
        <span class="nx-chatbot-toggle-icon-wrap">
            <img src="/NexGen/IMAGES/chatbot.png" alt="Chatbot" class="nx-chatbot-toggle-logo">
        </span>
    </button>

    <div class="nx-chatbot-box" id="nxChatbotBox">
        <div class="nx-chatbot-header">
            <div class="nx-chatbot-title">
                <img src="/NexGen/IMAGES/chatbot.png" alt="Bot">
                <div>
                    <h4>NextGen Assistant</h4>
                    <small><?php echo $chatbotIsOwner ? 'Owner AI Helper' : 'System Helper'; ?></small>
                </div>
            </div>
            <button type="button" class="nx-chatbot-close" id="nxChatbotClose">&times;</button>
        </div>

        <div class="nx-chatbot-body" id="nxChatbotMessages">
            <div class="nx-msg bot">
                Hello! I can help you with:
                <br><br>
                • Inventory monitoring
                <br>• Alerts and notifications
                <br>• Sales summary and trends
                <br>• Product and category analysis
                <br>• Business explanations
                <br>• Accounts receivable
                <br>• System help and navigation
            </div>
        </div>

        <div class="nx-chatbot-suggestions">
            <?php if ($chatbotIsOwner): ?>
                <button type="button" class="nx-chip">What are my sales today?</button>
                <button type="button" class="nx-chip">Which items are low stock?</button>
                <button type="button" class="nx-chip">What alerts do I have today?</button>
                <button type="button" class="nx-chip">Which customer account should I follow up first?</button>
            <?php else: ?>
                <button type="button" class="nx-chip">Which items are low stock?</button>
                <button type="button" class="nx-chip">Show recent stock movement</button>
                <button type="button" class="nx-chip">How do I record a sale?</button>
                <button type="button" class="nx-chip">Open accounts receivable</button>
            <?php endif; ?>
        </div>

        <form class="nx-chatbot-input-wrap" id="nxChatbotForm">
            <input type="text" id="nxChatbotInput" placeholder="Ask something..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<style>
.nx-chatbot-widget{
    position:fixed;
    right:18px;
    bottom:18px;
    z-index:9999;
    font-family:Arial,sans-serif;
}

.nx-chatbot-toggle{
    min-width:165px;
    height:58px;
    border:none;
    border-radius:999px;
    cursor:pointer;
    background:rgba(20,40,90,.42);
    backdrop-filter:blur(12px) saturate(140%);
    -webkit-backdrop-filter:blur(12px) saturate(140%);
    border:1px solid rgba(255,255,255,.12);
    box-shadow:0 12px 24px rgba(0,0,0,.20);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    overflow:visible;
    padding:7px 10px 7px 12px;
    transition:.22s ease;
}

.nx-chatbot-toggle:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 30px rgba(0,0,0,.24);
    filter:brightness(1.03);
}

.nx-chatbot-toggle-text{
    color:#fff;
    font-size:12px;
    font-weight:600;
    line-height:1;
    white-space:nowrap;
}

.nx-chatbot-toggle-icon-wrap{
    position:relative;
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    border-radius:50%;
}

.nx-chatbot-toggle-icon-wrap::after{
    content:"";
    position:absolute;
    inset:-5px;
    border-radius:50%;
    border:2px solid rgba(120,190,255,.42);
    animation:nxGlowPulse 2s infinite ease-out;
}

@keyframes nxGlowPulse{
    0%{ transform:scale(.92); opacity:.85; }
    70%{ transform:scale(1.14); opacity:.16; }
    100%{ transform:scale(1.2); opacity:0; }
}

.nx-chatbot-toggle-logo{
    width:44px;
    height:44px;
    object-fit:contain;
    display:block;
    background:transparent;
    mix-blend-mode:screen;
    filter:drop-shadow(0 0 10px rgba(79,125,241,.45));
}

.nx-chatbot-box{
    position:absolute;
    right:0;
    bottom:72px;
    width:390px;
    max-width:calc(100vw - 30px);
    height:560px;
    background:rgba(10,18,40,.96);
    border:1px solid rgba(255,255,255,.12);
    border-radius:22px;
    box-shadow:0 20px 50px rgba(0,0,0,.35);
    display:none;
    overflow:hidden;
    backdrop-filter:blur(12px);
}

.nx-chatbot-box.show{
    display:flex;
    flex-direction:column;
}

.nx-chatbot-header{
    padding:16px 18px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:1px solid rgba(255,255,255,.08);
    background:linear-gradient(135deg,rgba(37,99,235,.95),rgba(59,130,246,.8));
    color:#fff;
}

.nx-chatbot-title{
    display:flex;
    align-items:center;
    gap:12px;
}

.nx-chatbot-title img{
    width:56px;
    height:56px;
    object-fit:contain;
    background:transparent;
    border-radius:50%;
    padding:0;
    display:block;
    flex-shrink:0;
    mix-blend-mode:screen;
    filter:drop-shadow(0 0 10px rgba(255,255,255,.22));
}

.nx-chatbot-title h4{
    margin:0;
    font-size:16px;
}

.nx-chatbot-title small{
    opacity:.9;
}

.nx-chatbot-close{
    background:transparent;
    border:none;
    color:#fff;
    font-size:28px;
    cursor:pointer;
    line-height:1;
}

.nx-chatbot-body{
    flex:1;
    padding:14px;
    overflow-y:auto;
    background:
        radial-gradient(circle at top left, rgba(96,165,250,.12), transparent 25%),
        linear-gradient(180deg, rgba(7,18,45,.95), rgba(12,25,58,.98));
}

.nx-msg{
    max-width:88%;
    margin-bottom:12px;
    padding:12px 14px;
    border-radius:16px;
    line-height:1.5;
    white-space:pre-line;
    font-size:14px;
}

.nx-msg.bot{
    background:rgba(255,255,255,.08);
    color:#f5f7ff;
    border-top-left-radius:8px;
}

.nx-msg.user{
    background:linear-gradient(180deg,#5b84ff,#3766eb);
    color:#fff;
    margin-left:auto;
    border-top-right-radius:8px;
}

.nx-chatbot-suggestions{
    padding:12px 12px 8px;
    display:flex;
    gap:8px;
    overflow-x:auto;
    border-top:1px solid rgba(255,255,255,.06);
    background:rgba(255,255,255,.03);
}

.nx-chatbot-suggestions::-webkit-scrollbar{
    display:none;
}

.nx-chip{
    flex:0 0 auto;
    border:none;
    border-radius:999px;
    background:rgba(255,255,255,.10);
    color:#fff;
    padding:8px 12px;
    font-size:12px;
    cursor:pointer;
    white-space:nowrap;
}

.nx-chip:hover{
    background:rgba(255,255,255,.16);
}

.nx-chatbot-input-wrap{
    display:flex;
    gap:8px;
    padding:12px;
    background:rgba(255,255,255,.03);
    border-top:1px solid rgba(255,255,255,.08);
}

.nx-chatbot-input-wrap input{
    flex:1;
    border:none;
    outline:none;
    border-radius:14px;
    padding:12px 14px;
    background:rgba(255,255,255,.08);
    color:#fff;
    font-size:14px;
}

.nx-chatbot-input-wrap input::placeholder{
    color:rgba(255,255,255,.55);
}

.nx-chatbot-input-wrap button{
    border:none;
    border-radius:14px;
    padding:0 16px;
    background:linear-gradient(180deg,#5b84ff,#3766eb);
    color:#fff;
    font-weight:700;
    cursor:pointer;
}

.nx-open-card{
    margin-top:10px;
    padding:12px;
    border-radius:14px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
}

.nx-open-card strong{
    display:block;
    margin-bottom:8px;
}

.nx-open-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.nx-open-btn,
.nx-cancel-btn{
    border:none;
    border-radius:12px;
    padding:10px 12px;
    cursor:pointer;
    font-size:12px;
    font-weight:700;
}

.nx-open-btn{
    background:linear-gradient(180deg,#5b84ff,#3766eb);
    color:#fff;
}

.nx-cancel-btn{
    background:rgba(255,255,255,.10);
    color:#fff;
}

.nx-typing{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:rgba(255,255,255,.08);
    padding:12px 14px;
    border-radius:16px;
    border-top-left-radius:8px;
    margin-bottom:12px;
}

.nx-typing span{
    width:8px;
    height:8px;
    border-radius:50%;
    background:rgba(255,255,255,.75);
    animation:nxTyping 1.2s infinite ease-in-out;
}

.nx-typing span:nth-child(2){ animation-delay:.15s; }
.nx-typing span:nth-child(3){ animation-delay:.3s; }

@keyframes nxTyping{
    0%,80%,100%{ transform:scale(.7); opacity:.5; }
    40%{ transform:scale(1); opacity:1; }
}

@media (max-width:600px){
    .nx-chatbot-widget{
        right:12px;
        bottom:12px;
    }

    .nx-chatbot-toggle{
        min-width:150px;
        height:52px;
        padding:6px 8px 6px 10px;
    }

    .nx-chatbot-toggle-text{
        font-size:11px;
    }

    .nx-chatbot-toggle-icon-wrap{
        width:40px;
        height:40px;
    }

    .nx-chatbot-toggle-logo{
        width:40px;
        height:40px;
    }

    .nx-chatbot-box{
        width:min(92vw,390px);
        height:72vh;
        bottom:62px;
    }

    .nx-chatbot-title img{
        width:48px;
        height:48px;
    }
}
</style>

<script>
(function(){
    const toggleBtn = document.getElementById("nxChatbotToggle");
    const chatBox = document.getElementById("nxChatbotBox");
    const closeBtn = document.getElementById("nxChatbotClose");
    const form = document.getElementById("nxChatbotForm");
    const input = document.getElementById("nxChatbotInput");
    const messages = document.getElementById("nxChatbotMessages");
    const chips = document.querySelectorAll(".nx-chip");

    const ENDPOINT = "/NexGen/CODE/PHP/chatbot.php?action=ask";

    function escapeHtml(text){
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    function appendMessage(type, html){
        const msg = document.createElement("div");
        msg.className = "nx-msg " + type;
        msg.innerHTML = html;
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendTyping(){
        const typing = document.createElement("div");
        typing.className = "nx-typing";
        typing.id = "nxTyping";
        typing.innerHTML = "<span></span><span></span><span></span>";
        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping(){
        const typing = document.getElementById("nxTyping");
        if(typing) typing.remove();
    }

    function renderBotReply(reply){
        if (!reply) {
            return escapeHtml("No response received.");
        }

        const trimmed = String(reply).trim();

        if (trimmed.includes("[OPEN_CONFIRM]|")) {
            const lines = trimmed.split("\n");
            const markerLine = lines.find(line => line.includes("[OPEN_CONFIRM]|"));
            const normalLines = lines.filter(line => !line.includes("[OPEN_CONFIRM]|"));
            const parts = markerLine.split("|");

            const label = parts[1] || "Module";
            const url = parts[2] || "#";

            let html = "";
            if (normalLines.length > 0) {
                html += escapeHtml(normalLines.join("\n")).replace(/\n/g, "<br>") + "<br>";
            }

            html += `
                <div class="nx-open-card">
                    <strong>${escapeHtml(label)}</strong>
                    <div class="nx-open-actions">
                        <button type="button" class="nx-open-btn" data-open-url="${escapeHtml(url)}">Open Now</button>
                        <button type="button" class="nx-cancel-btn">Cancel</button>
                    </div>
                </div>
            `;
            return html;
        }

        return escapeHtml(trimmed).replace(/\n/g, "<br>");
    }

    async function sendMessage(text){
        const message = (text || input.value).trim();
        if(!message) return;

        appendMessage("user", escapeHtml(message).replace(/\n/g, "<br>"));
        input.value = "";
        appendTyping();

        try{
            const formData = new FormData();
            formData.append("message", message);

            const response = await fetch(ENDPOINT, {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });

            const data = await response.json();
            removeTyping();

            if(data && data.reply){
                appendMessage("bot", renderBotReply(data.reply));
            }else{
                appendMessage("bot", "Sorry, something went wrong while getting a reply.");
            }
        }catch(error){
            removeTyping();
            appendMessage("bot", "Sorry, I couldn’t connect right now. Please try again.");
        }
    }

    toggleBtn.addEventListener("click", function(){
        chatBox.classList.toggle("show");
        if(chatBox.classList.contains("show")){
            setTimeout(() => input.focus(), 120);
        }
    });

    closeBtn.addEventListener("click", function(){
        chatBox.classList.remove("show");
    });

    form.addEventListener("submit", function(e){
        e.preventDefault();
        sendMessage();
    });

    chips.forEach(chip => {
        chip.addEventListener("click", function(){
            const text = this.textContent.trim();
            if (!chatBox.classList.contains("show")) {
                chatBox.classList.add("show");
            }
            sendMessage(text);
        });
    });

    messages.addEventListener("click", function(e){
        const openBtn = e.target.closest(".nx-open-btn");
        const cancelBtn = e.target.closest(".nx-cancel-btn");

        if(openBtn){
            const url = openBtn.getAttribute("data-open-url");
            if(url){
                window.location.href = url;
            }
        }

        if(cancelBtn){
            const card = cancelBtn.closest(".nx-open-card");
            if(card) card.remove();
        }
    });
})();
</script>