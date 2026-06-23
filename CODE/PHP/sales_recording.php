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

$user_id = $_SESSION['user_id'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($filter !== 'All') {
    if (in_array($filter, ['Paid', 'Unpaid', 'Partially Paid', 'Fulfilled', 'Pending'], true)) {
        $where .= " AND (s.payment_status = ? OR s.order_status = ?) ";
        $params[] = $filter;
        $params[] = $filter;
        $types .= "ss";
    }
}

if (!empty($search)) {
    $where .= " AND (s.sales_no LIKE ? OR u.full_name LIKE ?) ";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql = "
    SELECT 
        s.id,
        s.sales_no,
        s.total_amount,
        s.payment_status,
        s.payment_method,
        s.order_status,
        s.sale_date,
        u.full_name AS salesperson,
        COALESCE(SUM(si.quantity), 0) AS total_qty,
        GROUP_CONCAT(p.product_name SEPARATOR ', ') AS items_sold
    FROM sales s
    LEFT JOIN users u ON s.salesperson_id = u.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    $where
    GROUP BY s.id
    ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$summarySql = "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN payment_status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
        SUM(CASE WHEN payment_status = 'Partially Paid' THEN 1 ELSE 0 END) AS partial_count,
        SUM(CASE WHEN order_status = 'Fulfilled' THEN 1 ELSE 0 END) AS fulfilled_count,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN payment_method = 'Cash' THEN 1 ELSE 0 END) AS cash_count,
        SUM(CASE WHEN payment_method = 'Gcash' OR payment_method = 'GCash' THEN 1 ELSE 0 END) AS gcash_count
    FROM sales
";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_assoc() : [
    'total_orders' => 0,
    'paid_count' => 0,
    'unpaid_count' => 0,
    'partial_count' => 0,
    'fulfilled_count' => 0,
    'pending_count' => 0,
    'cash_count' => 0,
    'gcash_count' => 0
];

$products = $conn->query("
    SELECT id, product_name, selling_price, stock_quantity
    FROM products
    WHERE is_active = 1
    ORDER BY product_name ASC
");

$productList = [];
while ($row = $products->fetch_assoc()) {
    $productList[] = $row;
}

$customers = $conn->query("
    SELECT id, customer_name, customer_code
    FROM customers
    WHERE status = 1
    ORDER BY customer_name ASC
");

$customerList = [];
if ($customers) {
    while ($row = $customers->fetch_assoc()) {
        $customerList[] = $row;
    }
}

function generateSalesNo() {
    return 'SAL-' . date('Ymd-His');
}

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

$popupMessage = $_SESSION['success'] ?? $_SESSION['error'] ?? '';
$popupType = isset($_SESSION['success']) ? 'success' : (isset($_SESSION['error']) ? 'error' : '');
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/sales_recording.css?v=5">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css?v=5">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        .toolbar-actions {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .toolbar-actions .btn-primary,
        .toolbar-actions .btn-secondary {
            min-width: 140px;
        }

        .sale-actions {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: 12px;
            width: 100%;
        }

        .sale-actions-full {
            justify-content: flex-end;
        }

        .sale-actions-full .save-btn {
            min-width: 220px;
        }

        .cancel-btn {
            display: none !important;
        }
        .customer-block {
    position: relative;
}

.customer-block {
    position: relative;
}

.customer-sticky-bar {
    position: sticky;
    top: 0;
    z-index: 15;
    background: linear-gradient(180deg, rgba(23, 62, 170, 0.98), rgba(23, 62, 170, 0.92));
    padding: 0 0 12px 0;
    margin-bottom: 6px;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    border-radius: 14px 14px 0 0;
}

.customer-sticky-bar .btn-primary {
    min-width: 208px;
}
.customer-inline-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.customer-inline-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px;
    gap: 12px;
    align-items: center;
    width: 100%;
}

.customer-inline-row select,
.customer-inline-row .customer-inline-btn {
    margin: 0;
}

.customer-inline-btn {
    width: 100%;
    min-width: 220px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.sale-confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 100001;
    padding: 20px;
}

.sale-confirm-overlay.show {
    display: flex;
}

.sale-confirm-box {
    width: 100%;
    max-width: 400px;
    background: linear-gradient(180deg, #1f3c88 0%, #1a3578 100%);
    border-radius: 22px;
    padding: 26px 22px;
    box-shadow: 0 22px 50px rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255,255,255,0.12);
    text-align: center;
    color: #fff;
}

.sale-confirm-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: rgba(247, 217, 139, 0.16);
    color: #f7d98b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 900;
}

.sale-confirm-box h3 {
    margin: 0 0 10px;
    font-size: 26px;
    font-weight: 900;
}

.sale-confirm-box p {
    margin: 0 0 20px;
    font-size: 15px;
    line-height: 1.6;
    color: rgba(255,255,255,0.88);
}

.sale-confirm-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.sale-confirm-cancel,
.sale-confirm-yes {
    border: none;
    border-radius: 12px;
    padding: 12px 18px;
    min-width: 130px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.sale-confirm-cancel {
    background: rgba(255,255,255,0.14);
    color: #fff;
}

.sale-confirm-cancel:hover {
    background: rgba(255,255,255,0.22);
}

.sale-confirm-yes {
    background: #f7d98b;
    color: #17306b;
}

.sale-confirm-yes:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
}

@media (max-width: 768px) {
    .customer-inline-row {
        grid-template-columns: 1fr;
    }

    .customer-inline-btn {
        min-width: 100%;
    }
}
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<?php if (!empty($popupMessage)): ?>
<div class="popup-overlay" id="popupOverlay">
    <div class="popup-box <?php echo $popupType; ?>">
        <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
        <p><?php echo htmlspecialchars($popupMessage); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="page-shell">
    <div class="header">
        <div class="header-left">
            <div class="header-title">Sales</div>
        </div>
    </div>

    <div class="content">
        <div class="toolbar">
            <div class="filter-group">
                <?php
                $filters = ['All', 'Paid', 'Unpaid', 'Partially Paid', 'Fulfilled', 'Pending'];
                foreach ($filters as $f):
                    $query = http_build_query([
                        'filter' => $f,
                        'search' => $search
                    ]);
                ?>
                    <a href="?<?php echo $query; ?>" class="filter-pill <?php echo ($filter === $f) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($f); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="toolbar-actions">
                <button type="button" class="btn-primary" onclick="openSaleModal()">＋ New Sale</button>
                <a href="/NexGen/CODE/PHP/dashboard.php" class="btn-secondary">Back</a>
            </div>
        </div>

        <div class="card sales-table-card">
            <div class="table-wrap scrollable-sales-table">
                <table>
                    <thead>
                        <tr>
                            <th>Sales No.</th>
                            <th>Items Sold</th>
                            <th>Qty</th>
                            <th>Cashier</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Date</th>
                            <th>Payment Method</th>
                            <th>Order Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['sales_no']); ?></strong></td>
                                    <td class="items-cell">
                                        <?php
                                        $items = $row['items_sold'] ?: 'No item details';
                                        echo htmlspecialchars(strlen($items) > 35 ? substr($items, 0, 35) . '...' : $items);
                                        ?>
                                    </td>
                                    <td><?php echo (int)$row['total_qty']; ?></td>
                                    <td><?php echo htmlspecialchars($row['salesperson'] ?: 'N/A'); ?></td>
                                    <td class="amount">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="<?php echo badgeClassPayment($row['payment_status']); ?>">
                                            <?php echo htmlspecialchars($row['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?php echo date("m/d/Y", strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                    <td>
                                        <span class="<?php echo badgeClassOrder($row['order_status']); ?>">
                                            <?php echo htmlspecialchars($row['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="sale_view.php?id=<?php echo $row['id']; ?>" class="btn-view">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="empty">No sales records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card flow-card">
            <div class="flow-title">Sales Flow</div>

            <div class="flow-grid">
                <div class="flow-total">
                    <small>Total Orders</small>
                    <strong><?php echo (int)($summary['total_orders'] ?? 0); ?></strong>
                </div>

                <div class="flow-columns">
                    <div class="flow-block">
                        <h3>Payment Status</h3>
                        <div class="flow-row flow-green">
                            <span>Paid</span>
                            <span><?php echo (int)($summary['paid_count'] ?? 0); ?></span>
                        </div>
                        <div class="flow-row flow-red">
                            <span>Unpaid</span>
                            <span><?php echo (int)($summary['unpaid_count'] ?? 0); ?></span>
                        </div>
                        <div class="flow-row flow-orange">
                            <span>Partially Paid</span>
                            <span><?php echo (int)($summary['partial_count'] ?? 0); ?></span>
                        </div>
                    </div>

                    <div class="flow-block">
                        <h3>Order Status</h3>
                        <div class="flow-row flow-green">
                            <span>Fulfilled</span>
                            <span><?php echo (int)($summary['fulfilled_count'] ?? 0); ?></span>
                        </div>
                        <div class="flow-row flow-orange">
                            <span>Pending</span>
                            <span><?php echo (int)($summary['pending_count'] ?? 0); ?></span>
                        </div>
                    </div>

                    <div class="flow-block">
                        <h3>Payment Method</h3>
                        <div class="flow-row flow-green">
                            <span>Cash</span>
                            <span><?php echo (int)($summary['cash_count'] ?? 0); ?></span>
                        </div>
                        <div class="flow-row flow-orange">
                            <span>Gcash</span>
                            <span><?php echo (int)($summary['gcash_count'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <span class="legend-dot" style="background: rgba(107, 190, 145, 0.65);"></span>
                    Paid / Fulfilled / Cash
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background: rgba(220, 97, 97, 0.65);"></span>
                    Unpaid
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background: rgba(229, 165, 83, 0.65);"></span>
                    Partial / Pending
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="saleModal">
    <div class="sale-modal">
        <button type="button" class="modal-close" onclick="closeSaleModal()">&times;</button>
        <div class="modal-title">Add New Sale</div>

        <form id="saleForm" method="POST" action="/NexGen/CODE/PHP/process_sale_ajax.php" novalidate>
            <div class="modal-grid">
                <div class="preview-panel">
                    <div class="preview-box">Preview</div>
                    <button type="button" class="upload-btn">Upload Receipt Image</button>
                </div>

                <div class="fields-panel">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Sales No.</label>
                            <input type="text" name="sales_no" value="<?php echo generateSalesNo(); ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Cashier</label>
                            <input type="text" value="<?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Current User'; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" id="paymentStatus" required>
                                <option value="Paid">Paid</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Partially Paid">Partially Paid</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>

                        <div class="form-group full-span">
                            <label>Order Status</label>
                            <select name="order_status" id="orderStatus" required>
                                <option value="Fulfilled">Fulfilled</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>

   <div class="form-group full-span customer-inline-group">
    <label>Customer</label>

    <div class="customer-inline-row">
        <select name="customer_id" id="customerSelect">
            <option value="">Walk-in / Optional for Paid</option>
            <?php foreach ($customerList as $customer): ?>
                <option value="<?php echo (int)$customer['id']; ?>">
                    <?php echo htmlspecialchars($customer['customer_name'] . ' (' . $customer['customer_code'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn-primary customer-inline-btn" id="openCustomerBtn">＋ New Customer</button>
    </div>
</div>

                        <div class="form-group" id="amountPaidGroup">
                            <label>Amount Paid</label>
                            <input type="number" step="0.01" min="0" name="amount_paid" id="amountPaidInput" value="0">
                        </div>

                        <div class="form-group" id="dueDateGroup">
                            <label>Due Date</label>
                            <input type="date" name="due_date" id="dueDateInput">
                        </div>
                    </div>

                    <div class="items-section">
                        <div class="items-title">Product Items</div>
                        <div class="items-header">
                            <div>Product</div>
                            <div>Qty</div>
                            <div>Unit Price</div>
                            <div>Subtotal</div>
                            <div>Remove</div>
                        </div>

                        <div id="itemsContainer"></div>

                        <button type="button" class="item-add-btn" onclick="addItemRow()">＋ Add Item</button>

                        <div class="sale-footer">
                            <div class="sale-total-card">
                                <small>Grand Total</small>
                                <strong>₱<span id="grandTotal">0.00</span></strong>
                            </div>

                            <div class="sale-actions sale-actions-full">
                                <button type="submit" class="save-btn" id="saveSaleBtn">Save Sale</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="customerModal">
    <div class="sale-modal">
        <button type="button" class="modal-close" id="closeCustomerBtn">&times;</button>
        <div class="modal-title">Add Customer</div>

        <form id="customerForm" method="POST" action="/NexGen/CODE/PHP/customer_save.php" novalidate>
            <input type="hidden" name="payment_status_context" id="paymentStatusContext" value="Paid">

            <div class="form-grid">
                <div class="form-group full-span">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" id="customerNameField" required>
                </div>

                <div class="form-group" id="phoneGroup">
                    <label>Phone</label>
                    <input type="text" name="phone" id="phoneField">
                </div>

                <div class="form-group" id="emailGroup">
                    <label>Email</label>
                    <input type="email" name="email" id="emailField">
                </div>

                <div class="form-group full-span" id="addressGroup">
                    <label>Address</label>
                    <textarea name="address" id="addressField" rows="3"></textarea>
                </div>

                <div class="form-group full-span">
                    <button type="submit" class="save-btn" id="saveCustomerBtn">Save Customer</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="sale-confirm-overlay" id="saleConfirmOverlay">
    <div class="sale-confirm-box">
        <div class="sale-confirm-icon">?</div>
        <h3>Confirm Sale</h3>
        <p>Are you sure you want to save this new sale?</p>

        <div class="sale-confirm-actions">
            <button type="button" class="sale-confirm-cancel" id="saleConfirmCancel">Cancel</button>
            <button type="button" class="sale-confirm-yes" id="saleConfirmYes">Yes</button>
        </div>
    </div>
</div>

<footer class="footer-section" id="footer-section">
    <div class="footer-top-line"></div>
    <p>Copyright © 2026 NexGen Micro-Enterprise</p>
</footer>

<?php include 'chatbot.php'; ?>

<script>
window.products = <?php echo json_encode($productList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/NexGen/CODE/JS/header.js?v=5"></script>
<script src="/NexGen/CODE/JS/sales_recording.js?v=5"></script>
<script>
const popupOverlay = document.getElementById("popupOverlay");
if (popupOverlay) {
    setTimeout(() => {
        popupOverlay.remove();
    }, 3000);
}
</script>
</body>
</html>