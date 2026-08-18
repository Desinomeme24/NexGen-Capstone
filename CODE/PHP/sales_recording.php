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
require_once __DIR__ . '/tenant_helper.php';
$businessId = nxRequireBusinessId($conn);
$csrfSaleForm = generateCsrfToken('sale_form');
$csrfCustomerForm = generateCsrfToken('customer_form');

$user_id = $_SESSION['user_id'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
if (!in_array($period, ['daily', 'range'], true)) {
    $period = 'daily';
}

$refDateInput = isset($_GET['ref_date']) ? trim($_GET['ref_date']) : date('Y-m-d');
$refDate = DateTime::createFromFormat('Y-m-d', $refDateInput);
if (!$refDate) {
    $refDate = new DateTime();
    $refDateInput = $refDate->format('Y-m-d');
}

$dateFromInput = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateToInput = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$cashierId = isset($_GET['cashier_id']) ? (int)$_GET['cashier_id'] : 0;

$dateStart = null;
$dateEnd = null;
$periodLabel = 'All Time';

switch ($period) {
    case 'daily':
        $dateStart = (clone $refDate)->setTime(0, 0, 0);
        $dateEnd = (clone $refDate)->setTime(23, 59, 59);
        $periodLabel = ($refDateInput === date('Y-m-d')) ? 'Today' : 'Day: ' . $dateStart->format('M d, Y');
        break;
    case 'weekly':
        $dateStart = (clone $refDate)->modify('monday this week')->setTime(0, 0, 0);
        $dateEnd = (clone $dateStart)->modify('+6 days')->setTime(23, 59, 59);
        $periodLabel = 'Week: ' . $dateStart->format('M d') . ' - ' . $dateEnd->format('M d, Y');
        break;
    case 'monthly':
        $dateStart = (clone $refDate)->modify('first day of this month')->setTime(0, 0, 0);
        $dateEnd = (clone $refDate)->modify('last day of this month')->setTime(23, 59, 59);
        $periodLabel = 'Month: ' . $dateStart->format('F Y');
        break;
    case 'annual':
        $dateStart = (clone $refDate)->setDate((int)$refDate->format('Y'), 1, 1)->setTime(0, 0, 0);
        $dateEnd = (clone $refDate)->setDate((int)$refDate->format('Y'), 12, 31)->setTime(23, 59, 59);
        $periodLabel = 'Year: ' . $dateStart->format('Y');
        break;
    case 'range':
        $fromObj = DateTime::createFromFormat('Y-m-d', $dateFromInput);
        $toObj = DateTime::createFromFormat('Y-m-d', $dateToInput);
        if ($fromObj && $toObj) {
            if ($fromObj > $toObj) {
                [$fromObj, $toObj] = [$toObj, $fromObj];
            }
            $dateStart = (clone $fromObj)->setTime(0, 0, 0);
            $dateEnd = (clone $toObj)->setTime(23, 59, 59);
            $periodLabel = 'Range: ' . $dateStart->format('M d, Y') . ' - ' . $dateEnd->format('M d, Y');
        } else {
            $period = 'daily';
            $dateStart = (clone $refDate)->setTime(0, 0, 0);
            $dateEnd = (clone $refDate)->setTime(23, 59, 59);
            $periodLabel = ($refDateInput === date('Y-m-d')) ? 'Today' : 'Day: ' . $dateStart->format('M d, Y');
        }
        break;
}

$where = " WHERE s.business_id = ? ";
$params = [$businessId];
$types = "i";

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

if ($cashierId > 0) {
    $where .= " AND s.salesperson_id = ? ";
    $params[] = $cashierId;
    $types .= "i";
}

if ($dateStart && $dateEnd) {
    $where .= " AND s.sale_date BETWEEN ? AND ? ";
    $params[] = $dateStart->format('Y-m-d H:i:s');
    $params[] = $dateEnd->format('Y-m-d H:i:s');
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

$summaryWhere = " WHERE business_id = ? ";
$summaryParams = [$businessId];
$summaryTypes = "i";
if ($cashierId > 0) {
    $summaryWhere .= " AND salesperson_id = ? ";
    $summaryParams[] = $cashierId;
    $summaryTypes .= "i";
}
if ($dateStart && $dateEnd) {
    $summaryWhere .= " AND sale_date BETWEEN ? AND ? ";
    $summaryParams[] = $dateStart->format('Y-m-d H:i:s');
    $summaryParams[] = $dateEnd->format('Y-m-d H:i:s');
    $summaryTypes .= "ss";
}

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
    $summaryWhere
";
$summaryStmt = $conn->prepare($summarySql);
if (!empty($summaryParams)) {
    $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
}
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
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

$productList = [];
$productStmt = $conn->prepare("
    SELECT id, product_name, selling_price, stock_quantity
    FROM products
    WHERE business_id = ? AND is_active = 1
    ORDER BY product_name ASC
");
if ($productStmt) {
    $productStmt->bind_param("i", $businessId);
    $productStmt->execute();
    $products = $productStmt->get_result();
    while ($row = $products->fetch_assoc()) {
        $productList[] = $row;
    }
    $productStmt->close();
}

$customerList = [];
$customerStmt = $conn->prepare("
    SELECT id, customer_name, customer_code
    FROM customers
    WHERE business_id = ? AND status = 1
    ORDER BY customer_name ASC
");
if ($customerStmt) {
    $customerStmt->bind_param("i", $businessId);
    $customerStmt->execute();
    $customers = $customerStmt->get_result();
    while ($row = $customers->fetch_assoc()) {
        $customerList[] = $row;
    }
    $customerStmt->close();
}

$cashierList = [];
$cashierStmt = $conn->prepare("
    SELECT id, full_name
    FROM users
    WHERE business_id = ? AND can_sales = 1
    ORDER BY full_name ASC
");
if ($cashierStmt) {
    $cashierStmt->bind_param("i", $businessId);
    $cashierStmt->execute();
    $cashiers = $cashierStmt->get_result();
    while ($row = $cashiers->fetch_assoc()) {
        $cashierList[] = $row;
    }
    $cashierStmt->close();
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
    <?php include __DIR__ . '/theme_init.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/sales_recording.css?v=8">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css?v=5">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/module_footer.css">
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
    background: linear-gradient(180deg, rgba(0, 51, 255, 0.98), rgba(0, 51, 255, 0.92));
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
    background: linear-gradient(180deg, #0b1f73 0%, #00033d 100%);
    border-radius: 22px;
    padding: 26px 22px;
    box-shadow: 0 22px 50px rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255,0.12);
    text-align: center;
    color: #ffffff;
}

.sale-confirm-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: rgba(255, 221, 85, 0.16);
    color: #ffdd55;
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
    color: rgba(255, 255, 255,0.88);
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
    background: rgba(255, 255, 255,0.14);
    color: #ffffff;
}

.sale-confirm-cancel:hover {
    background: rgba(255, 255, 255,0.22);
}

.sale-confirm-yes {
    background: #ffdd55;
    color: #0b1f73;
}

.sale-confirm-yes:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(255, 221, 85, 0.25);
}

@media (max-width: 768px) {
    .customer-inline-row {
        grid-template-columns: 1fr;
    }

    .customer-inline-btn {
        min-width: 100%;
    }
}

.sales-ledger-subtitle {
    margin-top: 4px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim, #b9d4f5);
}

.date-jump-form {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    margin-top: 4px;
    padding-top: 10px;
    border-top: 1px dashed rgba(255, 255, 255, 0.16);
}

.date-jump-label {
    font-size: 12px;
    font-weight: 700;
    color: #eef5ff;
    display: flex;
    align-items: center;
    gap: 6px;
}

.date-jump-form input[type="date"] {
    width: 100%;
    background: rgba(255, 255, 255, 0.92);
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    color: #0b1f73;
    font-size: 13px;
    font-weight: 600;
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

        <?php
            $filters = ['All', 'Paid', 'Unpaid', 'Partially Paid', 'Fulfilled', 'Pending'];
            $statusMeta = [
                'All'             => ['class' => 'status-all',      'icon' => 'bi-asterisk'],
                'Paid'            => ['class' => 'status-paid',     'icon' => 'bi-check-circle-fill'],
                'Unpaid'          => ['class' => 'status-unpaid',   'icon' => 'bi-x-circle-fill'],
                'Partially Paid'  => ['class' => 'status-partial',  'icon' => 'bi-hourglass-split'],
                'Fulfilled'       => ['class' => 'status-fulfilled','icon' => 'bi-box-seam-fill'],
                'Pending'         => ['class' => 'status-pending',  'icon' => 'bi-clock-fill'],
            ];
            $salesBaseParams = [
                'filter' => $filter,
                'search' => $search,
                'period' => $period,
                'ref_date' => $refDateInput,
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'cashier_id' => $cashierId,
            ];
            function salesHref($overrides, $base) {
                return '?' . http_build_query(array_merge($base, $overrides));
            }
        ?>

        <!-- KPI OVERVIEW -->
        <div class="kpi-strip">
            <a href="<?php echo salesHref(['filter' => 'All'], $salesBaseParams); ?>" class="kpi-tile kpi-neutral <?php echo ($filter === 'All') ? 'active' : ''; ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <div>
                    <small>Total Orders</small>
                    <strong><?php echo (int)($summary['total_orders'] ?? 0); ?></strong>
                </div>
            </a>
            <a href="<?php echo salesHref(['filter' => 'Paid'], $salesBaseParams); ?>" class="kpi-tile kpi-green <?php echo ($filter === 'Paid') ? 'active' : ''; ?>">
                <i class="bi bi-check-circle"></i>
                <div>
                    <small>Paid</small>
                    <strong><?php echo (int)($summary['paid_count'] ?? 0); ?></strong>
                </div>
            </a>
            <a href="<?php echo salesHref(['filter' => 'Unpaid'], $salesBaseParams); ?>" class="kpi-tile kpi-slate <?php echo ($filter === 'Unpaid') ? 'active' : ''; ?>">
                <i class="bi bi-x-circle"></i>
                <div>
                    <small>Unpaid</small>
                    <strong><?php echo (int)($summary['unpaid_count'] ?? 0); ?></strong>
                </div>
            </a>
            <a href="<?php echo salesHref(['filter' => 'Partially Paid'], $salesBaseParams); ?>" class="kpi-tile kpi-gold <?php echo ($filter === 'Partially Paid') ? 'active' : ''; ?>">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <small>Partially Paid</small>
                    <strong><?php echo (int)($summary['partial_count'] ?? 0); ?></strong>
                </div>
            </a>
            <a href="<?php echo salesHref(['filter' => 'Fulfilled'], $salesBaseParams); ?>" class="kpi-tile kpi-gold <?php echo ($filter === 'Fulfilled') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i>
                <div>
                    <small>Fulfilled</small>
                    <strong><?php echo (int)($summary['fulfilled_count'] ?? 0); ?></strong>
                </div>
            </a>
            <a href="<?php echo salesHref(['filter' => 'Pending'], $salesBaseParams); ?>" class="kpi-tile kpi-blue <?php echo ($filter === 'Pending') ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i>
                <div>
                    <small>Pending</small>
                    <strong><?php echo (int)($summary['pending_count'] ?? 0); ?></strong>
                </div>
            </a>
            <span class="kpi-tile kpi-green kpi-static">
                <i class="bi bi-cash-coin"></i>
                <div>
                    <small>Cash</small>
                    <strong><?php echo (int)($summary['cash_count'] ?? 0); ?></strong>
                </div>
            </span>
            <span class="kpi-tile kpi-blue kpi-static">
                <i class="bi bi-phone"></i>
                <div>
                    <small>GCash</small>
                    <strong><?php echo (int)($summary['gcash_count'] ?? 0); ?></strong>
                </div>
            </span>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <form class="search-box" method="GET" action="">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <input type="hidden" name="ref_date" value="<?php echo htmlspecialchars($refDateInput); ?>">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFromInput); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateToInput); ?>">
                <input type="hidden" name="cashier_id" value="<?php echo (int)$cashierId; ?>">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search sales no. or cashier..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn"><i class="bi bi-arrow-right-circle"></i></button>
            </form>

            <div class="toolbar-icon-filters">
                <div class="icon-filter-wrap">
                    <button type="button" class="icon-filter-btn <?php echo ($filter !== 'All') ? 'has-active' : ''; ?>" data-dropdown-toggle="statusFilterDropdown" aria-haspopup="true" aria-expanded="false" aria-label="Filter by status">
                        <i class="bi bi-funnel-fill"></i>
                        <span class="icon-filter-label">Status</span>
                    </button>
                    <div class="icon-filter-dropdown" id="statusFilterDropdown">
                        <div class="icon-filter-dropdown-head">
                            <span class="icon-filter-dropdown-icon status-head-icon"><i class="bi bi-funnel-fill"></i></span>
                            <span class="icon-filter-dropdown-title">Filter by Status</span>
                        </div>
                        <div class="status-chip-grid">
                            <?php foreach ($filters as $f):
                                $meta = $statusMeta[$f] ?? ['class' => 'status-all', 'icon' => 'bi-circle-fill'];
                            ?>
                                <a href="<?php echo salesHref(['filter' => $f], $salesBaseParams); ?>" class="filter-pill status-chip <?php echo $meta['class']; ?> <?php echo ($filter === $f) ? 'active' : ''; ?>">
                                    <i class="bi <?php echo $meta['icon']; ?>"></i>
                                    <?php echo htmlspecialchars($f); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="icon-filter-wrap">
                    <button type="button" class="icon-filter-btn <?php echo ($period === 'range') ? 'has-active' : ''; ?>" data-dropdown-toggle="dateFilterDropdown" aria-haspopup="true" aria-expanded="false" aria-label="Filter by date">
                        <i class="bi bi-calendar3"></i>
                        <span class="icon-filter-label">Date</span>
                    </button>
                    <div class="icon-filter-dropdown" id="dateFilterDropdown">
                        <div class="icon-filter-dropdown-head">
                            <span class="icon-filter-dropdown-icon date-head-icon"><i class="bi bi-calendar3"></i></span>
                            <span class="icon-filter-dropdown-title">Filter by Date</span>
                        </div>

                        <form class="date-range-form" method="GET" action="">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="cashier_id" value="<?php echo (int)$cashierId; ?>">
                            <input type="hidden" name="ref_date" value="<?php echo htmlspecialchars($refDateInput); ?>">
                            <input type="hidden" name="period" value="range">
                            <label class="date-jump-label"><i class="bi bi-calendar-range"></i> Date range</label>
                            <div class="date-range-inputs">
                                <div class="date-input-wrap">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFromInput); ?>" required>
                                </div>
                                <span class="date-range-sep">to</span>
                                <div class="date-input-wrap">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateToInput); ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="date-range-apply"><i class="bi bi-check2"></i> Apply Range</button>
                        </form>

                        <a href="<?php echo salesHref(['period' => 'daily', 'ref_date' => date('Y-m-d'), 'date_from' => '', 'date_to' => ''], $salesBaseParams); ?>" class="date-range-reset">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Today
                        </a>
                    </div>
                </div>

                <div class="icon-filter-wrap">
                    <button type="button" class="icon-filter-btn <?php echo ($cashierId > 0) ? 'has-active' : ''; ?>" data-dropdown-toggle="cashierFilterDropdown" aria-haspopup="true" aria-expanded="false" aria-label="Filter by cashier">
                        <i class="bi bi-person-badge-fill"></i>
                        <span class="icon-filter-label">Cashier</span>
                    </button>
                    <div class="icon-filter-dropdown" id="cashierFilterDropdown">
                        <div class="icon-filter-dropdown-head">
                            <span class="icon-filter-dropdown-icon cashier-head-icon"><i class="bi bi-person-badge-fill"></i></span>
                            <span class="icon-filter-dropdown-title">Filter by Cashier</span>
                        </div>
                        <div class="cashier-pill-list">
                            <a href="<?php echo salesHref(['cashier_id' => 0], $salesBaseParams); ?>" class="filter-pill cashier-chip <?php echo ($cashierId === 0) ? 'active' : ''; ?>">
                                <span class="cashier-chip-avatar cashier-chip-avatar-all"><i class="bi bi-people-fill"></i></span>
                                <span class="cashier-chip-name">All Cashiers</span>
                            </a>
                            <?php foreach ($cashierList as $c):
                                $cInitial = strtoupper(substr($c['full_name'], 0, 1)) ?: '?';
                            ?>
                                <a href="<?php echo salesHref(['cashier_id' => (int)$c['id']], $salesBaseParams); ?>" class="filter-pill cashier-chip <?php echo ($cashierId === (int)$c['id']) ? 'active' : ''; ?>">
                                    <span class="cashier-chip-avatar"><?php echo htmlspecialchars($cInitial); ?></span>
                                    <span class="cashier-chip-name"><?php echo htmlspecialchars($c['full_name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($cashierList)): ?>
                                <p class="cashier-pill-empty"><i class="bi bi-info-circle"></i> No employees with Sales access yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="toolbar-actions">
                <button type="button" class="btn-primary" onclick="openSaleModal()">＋ New Sale</button>
                <a href="/NexGen/CODE/PHP/dashboard.php" class="btn-secondary">Back</a>
            </div>
        </div>

        <!-- SALES LEDGER GRID -->
        <div class="card sales-ledger-card">
            <div class="sales-ledger-header">
                <div>
                    <h2>Sales Ledger</h2>
                    <?php
                        $subtitleParts = [];
                        if ($period !== 'all') {
                            $subtitleParts[] = $periodLabel;
                        }
                        if ($cashierId > 0) {
                            $cashierName = 'Selected Cashier';
                            foreach ($cashierList as $c) {
                                if ((int)$c['id'] === $cashierId) {
                                    $cashierName = $c['full_name'];
                                    break;
                                }
                            }
                            $subtitleParts[] = 'Cashier: ' . $cashierName;
                        }
                    ?>
                    <?php if (!empty($subtitleParts)): ?>
                        <p class="sales-ledger-subtitle"><?php echo htmlspecialchars(implode(' · ', $subtitleParts)); ?></p>
                    <?php endif; ?>
                </div>
                <span class="sales-count-chip"><?php echo (int)$result->num_rows; ?> record<?php echo ($result->num_rows === 1) ? '' : 's'; ?></span>
            </div>

            <div class="sales-grid" id="salesGrid">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()):
                        $items = $row['items_sold'] ?: 'No item details';
                        $itemsShort = strlen($items) > 58 ? substr($items, 0, 58) . '…' : $items;
                        $dateFmt = date("M d, Y", strtotime($row['sale_date']));
                        $cashierName = $row['salesperson'] ?: 'N/A';
                        $initial = strtoupper(substr($cashierName, 0, 1)) ?: '?';
                        $methodIcon = (stripos($row['payment_method'], 'cash') === 0) ? 'bi-cash-coin' : 'bi-phone';
                    ?>
                    <article class="sale-card"
                        data-search="<?php echo htmlspecialchars(strtolower($row['sales_no'] . ' ' . $cashierName)); ?>"
                        data-id="<?php echo (int)$row['id']; ?>"
                        data-sales-no="<?php echo htmlspecialchars($row['sales_no']); ?>"
                        data-date="<?php echo htmlspecialchars($dateFmt); ?>"
                        data-cashier="<?php echo htmlspecialchars($cashierName); ?>"
                        data-items="<?php echo htmlspecialchars($items); ?>"
                        data-qty="<?php echo htmlspecialchars($row['total_qty']); ?>"
                        data-amount="<?php echo number_format($row['total_amount'], 2); ?>"
                        data-payment-status="<?php echo htmlspecialchars($row['payment_status']); ?>"
                        data-payment-method="<?php echo htmlspecialchars($row['payment_method']); ?>"
                        data-order-status="<?php echo htmlspecialchars($row['order_status']); ?>">

                        <div class="sale-card-top">
                            <div class="sale-card-id">
                                <span class="sale-card-no" title="<?php echo htmlspecialchars($row['sales_no']); ?>"><?php echo htmlspecialchars($row['sales_no']); ?></span>
                                <span class="sale-card-date"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($dateFmt); ?></span>
                            </div>
                            <span class="<?php echo badgeClassPayment($row['payment_status']); ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span>
                        </div>

                        <div class="sale-card-items">
                            <i class="bi bi-bag-check"></i>
                            <span title="<?php echo htmlspecialchars($items); ?>"><?php echo htmlspecialchars($itemsShort); ?></span>
                        </div>

                        <div class="sale-card-mid">
                            <div class="sale-card-person">
                                <span class="mini-avatar"><?php echo htmlspecialchars($initial); ?></span>
                                <div>
                                    <small>Cashier</small>
                                    <strong><?php echo htmlspecialchars($cashierName); ?></strong>
                                </div>
                            </div>
                            <div class="sale-card-qty">
                                <small>Qty</small>
                                <strong><?php echo formatQty($row['total_qty']); ?></strong>
                            </div>
                        </div>

                        <div class="sale-card-stub">
                            <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                        </div>

                        <div class="sale-card-bottom">
                            <div class="sale-card-amount">
                                <small>Total</small>
                                <strong>₱<?php echo number_format($row['total_amount'], 2); ?></strong>
                            </div>
                            <span class="<?php echo badgeClassOrder($row['order_status']); ?>"><?php echo htmlspecialchars($row['order_status']); ?></span>
                        </div>

                        <div class="sale-card-footer">
                            <span class="sale-card-method"><i class="bi <?php echo $methodIcon; ?>"></i> <?php echo htmlspecialchars($row['payment_method']); ?></span>
                            <div class="sale-card-actions">
                                <button type="button" class="btn-quickview" onclick="openQuickView(this)"><i class="bi bi-eye"></i> Quick View</button>
                                <a href="sale_view.php?id=<?php echo (int)$row['id']; ?>" class="btn-view">Open</a>
                            </div>
                        </div>
                    </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="sales-empty">
                        <i class="bi bi-inboxes"></i>
                        <h3>No sales records found</h3>
                        <p>Try a different filter or search term, or record a new sale to get started.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sales-pagination" id="salesPagination"></div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <span class="legend-dot" style="background: rgba(94, 234, 212, 0.65);"></span>
                Paid / Fulfilled / Cash
            </div>
            <div class="legend-item">
                <span class="legend-dot" style="background: rgba(255, 85, 119, 0.65);"></span>
                Unpaid
            </div>
            <div class="legend-item">
                <span class="legend-dot" style="background: rgba(255, 221, 85, 0.65);"></span>
                Partial / Pending
            </div>
        </div>
    </div>
</div>

<!-- QUICK VIEW MODAL -->
<div class="modal-overlay" id="quickViewModal">
    <div class="sale-modal quickview-modal">
        <button type="button" class="modal-close" onclick="closeQuickView()">&times;</button>
        <div class="modal-title">Sale Details</div>

        <div class="qv-header">
            <div>
                <small>Sales No.</small>
                <strong id="qvSalesNo">—</strong>
            </div>
            <div class="qv-badges">
                <span id="qvPaymentBadge" class="badge">—</span>
                <span id="qvOrderBadge" class="badge">—</span>
            </div>
        </div>

        <div class="qv-grid">
            <div class="qv-row"><span>Date</span><strong id="qvDate">—</strong></div>
            <div class="qv-row"><span>Cashier</span><strong id="qvCashier">—</strong></div>
            <div class="qv-row"><span>Quantity</span><strong id="qvQty">—</strong></div>
            <div class="qv-row"><span>Payment Method</span><strong id="qvMethod">—</strong></div>
            <div class="qv-row full-span"><span>Items</span><strong id="qvItems">—</strong></div>
            <div class="qv-row full-span qv-total-row"><span>Grand Total</span><strong id="qvAmount">₱0.00</strong></div>
        </div>

        <a href="#" id="qvFullRecordLink" class="btn-primary qv-full-link">Open Full Record</a>
    </div>
</div>

<div class="modal-overlay" id="saleModal">
    <div class="sale-modal wizard-modal">
        <button type="button" class="modal-close" onclick="closeSaleModal()">&times;</button>
        <div class="modal-title">Record New Sale</div>

        <div class="wizard-steps" id="wizardSteps">
            <button type="button" class="wizard-step-dot" data-goto="1">
                <span class="dot-num">1</span><span class="dot-label">Sale Info</span>
            </button>
            <span class="wizard-step-line"></span>
            <button type="button" class="wizard-step-dot" data-goto="2">
                <span class="dot-num">2</span><span class="dot-label">Items</span>
            </button>
            <span class="wizard-step-line"></span>
            <button type="button" class="wizard-step-dot" data-goto="3">
                <span class="dot-num">3</span><span class="dot-label">Review</span>
            </button>
        </div>

        <form id="saleForm" method="POST" action="/NexGen/CODE/PHP/process_sale_ajax.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfSaleForm); ?>">

            <!-- STEP 1: SALE INFO -->
            <div class="wizard-step active" data-step="1">
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
                            <?php if ((int)($_SESSION['can_accounts_receivable'] ?? 0) === 1): ?>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" id="paymentMethod" required>
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

                    <div class="form-group" id="dueDateGroup">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="dueDateInput">
                    </div>
                </div>
            </div>

            <!-- STEP 2: ITEMS + LIVE RECEIPT -->
            <div class="wizard-step" data-step="2">
                <div class="items-wizard-grid">
                    <div class="receipt-panel">
                        <div class="receipt-box">
                            <div class="receipt-box-head"><i class="bi bi-receipt"></i> Live Receipt</div>
                            <div class="receipt-lines" id="receiptLines">
                                <p class="receipt-empty">No items yet — add a product to see it here.</p>
                            </div>
                            <div class="receipt-total">
                                <span>Total</span>
                                <strong>₱<span id="receiptTotal">0.00</span></strong>
                            </div>
                        </div>
                        <label class="attach-receipt-btn" for="receiptImageInput">
                            <i class="bi bi-paperclip"></i> <span id="attachReceiptText">Attach Receipt Photo (optional)</span>
                        </label>
                        <input type="file" name="receipt_image" id="receiptImageInput" accept="image/*" hidden>
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

                        <div class="sale-total-card sale-total-inline">
                            <small>Grand Total</small>
                            <strong>₱<span id="grandTotal">0.00</span></strong>
                        </div>

                        <div class="form-group" id="amountPaidGroup">
                            <label>Amount Paid</label>
                            <input type="number" step="0.01" min="0" name="amount_paid" id="amountPaidInput" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: REVIEW & CONFIRM -->
            <div class="wizard-step" data-step="3">
                <div class="review-intro">Double-check the details below, then save the sale.</div>
                <div class="review-grid" id="reviewGrid"></div>

                <div class="sale-footer">
                    <div class="sale-total-card">
                        <small>Grand Total</small>
                        <strong>₱<span id="reviewTotal">0.00</span></strong>
                    </div>

                    <div class="sale-actions sale-actions-full">
                        <button type="submit" class="save-btn" id="saveSaleBtn">Save Sale</button>
                    </div>
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="wizard-back-btn" id="wizardBackBtn"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="wizard-next-btn" id="wizardNextBtn">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="customerModal">
    <div class="sale-modal">
        <button type="button" class="modal-close" id="closeCustomerBtn">&times;</button>
        <div class="modal-title">Add Customer</div>

        <form id="customerForm" method="POST" action="/NexGen/CODE/PHP/customer_save.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfCustomerForm); ?>">
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

<footer class="nx-footer">
    <div class="nx-footer-line"></div>
    <p class="nx-footer-copy">Copyright &copy; 2026 NexGen.</p>
</footer>

<?php include 'chatbot.php'; ?>

<script>
window.products = <?php echo json_encode($productList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/NexGen/CODE/JS/header.js?v=5"></script>
<script src="/NexGen/CODE/JS/sales_recording.js?v=6"></script>
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