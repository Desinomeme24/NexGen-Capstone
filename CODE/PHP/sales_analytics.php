<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen_Eval/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    $_SESSION['error'] = 'Only owners can access Sales Analytics.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$displayName = $_SESSION['username'] ?? 'Client';
$fullName = $_SESSION['full_name'] ?? 'Client';
$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$userId = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| NOTIFICATION READ TRACKING
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['notifications_last_seen'])) {
    $_SESSION['notifications_last_seen'] = date('Y-m-d H:i:s');
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_notifications_seen') {
    $_SESSION['notifications_last_seen'] = date('Y-m-d H:i:s');

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as seen.'
    ]);
    exit();
}

$notificationsLastSeen = $_SESSION['notifications_last_seen'];

/*
|--------------------------------------------------------------------------
| FILTER SETUP
|--------------------------------------------------------------------------
*/
$filter = $_GET['filter'] ?? 'today';
$allowedFilters = ['today', 'week', 'month', 'custom'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'today';
}

$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

$today = new DateTime();
$rangeLabel = 'Today';

switch ($filter) {
    case 'today':
        $startDate = $today->format('Y-m-d 00:00:00');
        $endDate   = $today->format('Y-m-d 23:59:59');
        $rangeLabel = 'Today';
        break;

    case 'week':
        $monday = new DateTime();
        $monday->modify('monday this week');
        $sunday = new DateTime();
        $sunday->modify('sunday this week');
        $startDate = $monday->format('Y-m-d 00:00:00');
        $endDate   = $sunday->format('Y-m-d 23:59:59');
        $rangeLabel = 'This Week';
        break;

    case 'month':
        $firstDay = new DateTime('first day of this month');
        $lastDay  = new DateTime('last day of this month');
        $startDate = $firstDay->format('Y-m-d 00:00:00');
        $endDate   = $lastDay->format('Y-m-d 23:59:59');
        $rangeLabel = 'This Month';
        break;

    case 'custom':
        if (!empty($customStart) && !empty($customEnd)) {
            $startDate = date('Y-m-d 00:00:00', strtotime($customStart));
            $endDate   = date('Y-m-d 23:59:59', strtotime($customEnd));
            $rangeLabel = date('M d, Y', strtotime($customStart)) . ' - ' . date('M d, Y', strtotime($customEnd));
        } else {
            $startDate = $today->format('Y-m-d 00:00:00');
            $endDate   = $today->format('Y-m-d 23:59:59');
            $rangeLabel = 'Today';
            $filter = 'today';
        }
        break;
}

/*
|--------------------------------------------------------------------------
| SUMMARY CARD COMPUTATION
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| SUMMARY CARD COMPUTATION
|--------------------------------------------------------------------------
*/
$netProfit = 0.00;
$grossRevenue = 0.00;
$costOfGoodsSold = 0.00;
$totalTransactions = 0;
$salesGrowth = 0.00;

/*
|--------------------------------------------------------------------------
| MAIN SUMMARY FOR SELECTED RANGE
|--------------------------------------------------------------------------
*/
$stmtSummary = $conn->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS gross_revenue,
        COUNT(*) AS total_transactions
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmtSummary->bind_param("ss", $startDate, $endDate);
$stmtSummary->execute();
$summaryRow = $stmtSummary->get_result()->fetch_assoc();
$stmtSummary->close();

$grossRevenue = (float)($summaryRow['gross_revenue'] ?? 0);
$totalTransactions = (int)($summaryRow['total_transactions'] ?? 0);

$stmtCogs = $conn->prepare("
    SELECT 
        COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
");
$stmtCogs->bind_param("ss", $startDate, $endDate);
$stmtCogs->execute();
$cogsRow = $stmtCogs->get_result()->fetch_assoc();
$stmtCogs->close();

$costOfGoodsSold = (float)($cogsRow['cost_of_goods_sold'] ?? 0);
$netProfit = $grossRevenue - $costOfGoodsSold;

/*
|--------------------------------------------------------------------------
| SALES GROWTH
|--------------------------------------------------------------------------
| Still based on gross revenue comparison
|--------------------------------------------------------------------------
*/
$currentStartTs = strtotime($startDate);
$currentEndTs = strtotime($endDate);
$rangeSeconds = max(1, ($currentEndTs - $currentStartTs) + 1);

$previousEndTs = $currentStartTs - 1;
$previousStartTs = $previousEndTs - $rangeSeconds + 1;

$previousStart = date('Y-m-d H:i:s', $previousStartTs);
$previousEnd   = date('Y-m-d H:i:s', $previousEndTs);

$stmtPrev = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS previous_revenue
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmtPrev->bind_param("ss", $previousStart, $previousEnd);
$stmtPrev->execute();
$prevRow = $stmtPrev->get_result()->fetch_assoc();
$stmtPrev->close();

$previousRevenue = (float)($prevRow['previous_revenue'] ?? 0);

if ($previousRevenue > 0) {
    $salesGrowth = (($grossRevenue - $previousRevenue) / $previousRevenue) * 100;
} elseif ($grossRevenue > 0) {
    $salesGrowth = 100.00;
} else {
    $salesGrowth = 0.00;
}

/*
|--------------------------------------------------------------------------
| FIXED PERIOD VALUES
|--------------------------------------------------------------------------
*/
$todayProfit = 0.00;
$weekProfit = 0.00;
$monthProfit = 0.00;

/* Today */
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

$stmtTodayRevenue = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS gross_revenue
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmtTodayRevenue->bind_param("ss", $todayStart, $todayEnd);
$stmtTodayRevenue->execute();
$todayRevenueRow = $stmtTodayRevenue->get_result()->fetch_assoc();
$stmtTodayRevenue->close();

$stmtTodayCogs = $conn->prepare("
    SELECT COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
");
$stmtTodayCogs->bind_param("ss", $todayStart, $todayEnd);
$stmtTodayCogs->execute();
$todayCogsRow = $stmtTodayCogs->get_result()->fetch_assoc();
$stmtTodayCogs->close();

$todayRevenue = (float)($todayRevenueRow['gross_revenue'] ?? 0);
$todayCogs = (float)($todayCogsRow['cost_of_goods_sold'] ?? 0);
$todayProfit = $todayRevenue - $todayCogs;

/* Week */
$weekStartObj = new DateTime();
$weekStartObj->modify('monday this week');
$weekEndObj = new DateTime();
$weekEndObj->modify('sunday this week');

$weekStart = $weekStartObj->format('Y-m-d 00:00:00');
$weekEnd   = $weekEndObj->format('Y-m-d 23:59:59');

$stmtWeekRevenue = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS gross_revenue
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmtWeekRevenue->bind_param("ss", $weekStart, $weekEnd);
$stmtWeekRevenue->execute();
$weekRevenueRow = $stmtWeekRevenue->get_result()->fetch_assoc();
$stmtWeekRevenue->close();

$stmtWeekCogs = $conn->prepare("
    SELECT COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
");
$stmtWeekCogs->bind_param("ss", $weekStart, $weekEnd);
$stmtWeekCogs->execute();
$weekCogsRow = $stmtWeekCogs->get_result()->fetch_assoc();
$stmtWeekCogs->close();

$weekRevenue = (float)($weekRevenueRow['gross_revenue'] ?? 0);
$weekCogs = (float)($weekCogsRow['cost_of_goods_sold'] ?? 0);
$weekProfit = $weekRevenue - $weekCogs;

/* Month */
$monthStartObj = new DateTime('first day of this month');
$monthEndObj = new DateTime('last day of this month');

$monthStart = $monthStartObj->format('Y-m-d 00:00:00');
$monthEnd   = $monthEndObj->format('Y-m-d 23:59:59');

$stmtMonthRevenue = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS gross_revenue
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmtMonthRevenue->bind_param("ss", $monthStart, $monthEnd);
$stmtMonthRevenue->execute();
$monthRevenueRow = $stmtMonthRevenue->get_result()->fetch_assoc();
$stmtMonthRevenue->close();

$stmtMonthCogs = $conn->prepare("
    SELECT COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
");
$stmtMonthCogs->bind_param("ss", $monthStart, $monthEnd);
$stmtMonthCogs->execute();
$monthCogsRow = $stmtMonthCogs->get_result()->fetch_assoc();
$stmtMonthCogs->close();

$monthRevenue = (float)($monthRevenueRow['gross_revenue'] ?? 0);
$monthCogs = (float)($monthCogsRow['cost_of_goods_sold'] ?? 0);
$monthProfit = $monthRevenue - $monthCogs;

/*
|--------------------------------------------------------------------------
| DAILY NET PROFIT (Mon-Sun) FOR CURRENT WEEK
|--------------------------------------------------------------------------
*/
$dailyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dailySalesData = [0, 0, 0, 0, 0, 0, 0];

$stmtDailyRevenue = $conn->prepare("
    SELECT DAYOFWEEK(sale_date) AS weekday_num,
           COALESCE(SUM(total_amount), 0) AS gross_revenue
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(sale_date)
");
$stmtDailyRevenue->bind_param("ss", $weekStart, $weekEnd);
$stmtDailyRevenue->execute();
$dailyRevenueResult = $stmtDailyRevenue->get_result();

$dailyRevenueMap = [];
while ($row = $dailyRevenueResult->fetch_assoc()) {
    $dailyRevenueMap[(int)$row['weekday_num']] = (float)$row['gross_revenue'];
}
$stmtDailyRevenue->close();

$stmtDailyCogs = $conn->prepare("
    SELECT DAYOFWEEK(s.sale_date) AS weekday_num,
           COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(s.sale_date)
");
$stmtDailyCogs->bind_param("ss", $weekStart, $weekEnd);
$stmtDailyCogs->execute();
$dailyCogsResult = $stmtDailyCogs->get_result();

$dailyCogsMap = [];
while ($row = $dailyCogsResult->fetch_assoc()) {
    $dailyCogsMap[(int)$row['weekday_num']] = (float)$row['cost_of_goods_sold'];
}
$stmtDailyCogs->close();

$map = [
    2 => 0,
    3 => 1,
    4 => 2,
    5 => 3,
    6 => 4,
    7 => 5,
    1 => 6
];

foreach ($map as $mysqlDay => $index) {
    $revenue = $dailyRevenueMap[$mysqlDay] ?? 0;
    $cogs = $dailyCogsMap[$mysqlDay] ?? 0;
    $dailySalesData[$index] = $revenue - $cogs;
}

/*
|--------------------------------------------------------------------------
| MONTHLY NET PROFIT (Jan-Dec) FOR CURRENT YEAR
|--------------------------------------------------------------------------
*/
$monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyRevenueData = array_fill(0, 12, 0);

$currentYear = (int)date('Y');

$stmtMonthlyRevenue = $conn->prepare("
    SELECT MONTH(sale_date) AS sale_month,
           COALESCE(SUM(total_amount), 0) AS gross_revenue
    FROM sales
    WHERE YEAR(sale_date) = ?
    GROUP BY MONTH(sale_date)
    ORDER BY MONTH(sale_date)
");
$stmtMonthlyRevenue->bind_param("i", $currentYear);
$stmtMonthlyRevenue->execute();
$monthlyRevenueResult = $stmtMonthlyRevenue->get_result();

$monthlyRevenueMap = [];
while ($row = $monthlyRevenueResult->fetch_assoc()) {
    $monthlyRevenueMap[(int)$row['sale_month']] = (float)$row['gross_revenue'];
}
$stmtMonthlyRevenue->close();

$stmtMonthlyCogs = $conn->prepare("
    SELECT MONTH(s.sale_date) AS sale_month,
           COALESCE(SUM(p.cost_price * si.quantity), 0) AS cost_of_goods_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE YEAR(s.sale_date) = ?
    GROUP BY MONTH(s.sale_date)
    ORDER BY MONTH(s.sale_date)
");
$stmtMonthlyCogs->bind_param("i", $currentYear);
$stmtMonthlyCogs->execute();
$monthlyCogsResult = $stmtMonthlyCogs->get_result();

$monthlyCogsMap = [];
while ($row = $monthlyCogsResult->fetch_assoc()) {
    $monthlyCogsMap[(int)$row['sale_month']] = (float)$row['cost_of_goods_sold'];
}
$stmtMonthlyCogs->close();

for ($m = 1; $m <= 12; $m++) {
    $revenue = $monthlyRevenueMap[$m] ?? 0;
    $cogs = $monthlyCogsMap[$m] ?? 0;
    $monthlyRevenueData[$m - 1] = $revenue - $cogs;
}

/*
|--------------------------------------------------------------------------
| CATEGORY DATA (SELECTED RANGE)
|--------------------------------------------------------------------------
*/
$categoryLabels = [];
$categorySalesData = [];
$categoryBreakdown = [];

$stmtCategory = $conn->prepare("
    SELECT 
        c.category_name,
        COALESCE(SUM(si.quantity), 0) AS units_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    INNER JOIN categories c ON p.category_id = c.id
    WHERE s.sale_date BETWEEN ? AND ?
    GROUP BY c.id, c.category_name
    ORDER BY units_sold DESC, c.category_name ASC
");
$stmtCategory->bind_param("ss", $startDate, $endDate);
$stmtCategory->execute();
$categoryResult = $stmtCategory->get_result();

$totalCategoryUnits = 0;
$tempCategories = [];

while ($row = $categoryResult->fetch_assoc()) {
    $units = (int)$row['units_sold'];
    $tempCategories[] = [
        'name' => $row['category_name'],
        'units_sold' => $units
    ];
    $totalCategoryUnits += $units;
}
$stmtCategory->close();

if (!empty($tempCategories)) {
    foreach ($tempCategories as $item) {
        $percentage = $totalCategoryUnits > 0 ? ($item['units_sold'] / $totalCategoryUnits) * 100 : 0;
        $categoryLabels[] = $item['name'];
        $categorySalesData[] = $item['units_sold'];
        $categoryBreakdown[] = [
            'name' => $item['name'],
            'units_sold' => $item['units_sold'],
            'percentage' => $percentage
        ];
    }
} else {
    $categoryLabels = ['No Category'];
    $categorySalesData = [0];
    $categoryBreakdown[] = [
        'name' => 'No Category',
        'units_sold' => 0,
        'percentage' => 0
    ];
}

/*
|--------------------------------------------------------------------------
| TOP PRODUCTS
|--------------------------------------------------------------------------
*/
$topProductsBySales = [];

$stmtTopProducts = $conn->prepare("
    SELECT 
        p.product_name,
        COALESCE(SUM(si.quantity * si.unit_price), 0) AS total_sales
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
    GROUP BY p.id, p.product_name
    ORDER BY total_sales DESC, p.product_name ASC
    LIMIT 5
");
$stmtTopProducts->bind_param("ss", $startDate, $endDate);
$stmtTopProducts->execute();
$topProductsResult = $stmtTopProducts->get_result();

while ($row = $topProductsResult->fetch_assoc()) {
    $topProductsBySales[] = [
        'name' => $row['product_name'],
        'sales' => (float)$row['total_sales']
    ];
}
$stmtTopProducts->close();

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/
$notifications = [];
$notificationCount = 0;

/* Low stock - visible in modal, not counted as unread badge */
$lowStockQuery = $conn->query("
    SELECT product_name, stock_quantity, reorder_level
    FROM products
    WHERE stock_quantity > 0 AND stock_quantity <= reorder_level
    ORDER BY stock_quantity ASC
    LIMIT 5
");
if ($lowStockQuery) {
    while ($row = $lowStockQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'bi-exclamation-triangle-fill',
            'title' => 'Low Stock Alert',
            'message' => $row['product_name'] . ' is low on stock (' . (int)$row['stock_quantity'] . ' left, reorder at ' . (int)$row['reorder_level'] . ').',
            'time' => 'Inventory',
            'is_unread' => false
        ];
    }
}

/* Out of stock - visible in modal, not counted as unread badge */
$outStockQuery = $conn->query("
    SELECT product_name
    FROM products
    WHERE stock_quantity <= 0
    ORDER BY updated_at DESC
    LIMIT 5
");
if ($outStockQuery) {
    while ($row = $outStockQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'danger',
            'icon' => 'bi-bell-fill',
            'title' => 'Out of Stock',
            'message' => $row['product_name'] . ' is currently out of stock.',
            'time' => 'Inventory',
            'is_unread' => false
        ];
    }
}

/* Recent stock movements - counted if newer than last seen */
$movementStmt = $conn->prepare("
    SELECT sm.movement_type, sm.quantity, sm.created_at, p.product_name
    FROM stock_movements sm
    INNER JOIN products p ON p.id = sm.product_id
    ORDER BY sm.created_at DESC
    LIMIT 6
");
$movementStmt->execute();
$movementResult = $movementStmt->get_result();

while ($row = $movementResult->fetch_assoc()) {
    $actionText = $row['movement_type'] === 'stock_in' ? 'Stock In' : 'Stock Out';
    $isUnread = strtotime($row['created_at']) > strtotime($notificationsLastSeen);

    if ($isUnread) {
        $notificationCount++;
    }

    $notifications[] = [
        'type' => $row['movement_type'] === 'stock_in' ? 'success' : 'danger',
        'icon' => $row['movement_type'] === 'stock_in' ? 'bi-box-seam-fill' : 'bi-arrow-down-square-fill',
        'title' => $actionText . ' Recorded',
        'message' => $actionText . ' for ' . $row['product_name'] . ' (' . (int)$row['quantity'] . ' item/s).',
        'time' => date('M d, Y h:i A', strtotime($row['created_at'])),
        'is_unread' => $isUnread
    ];
}
$movementStmt->close();

/* Recently added products - counted if newer than last seen */
$newProductStmt = $conn->prepare("
    SELECT product_name, created_at
    FROM products
    ORDER BY created_at DESC
    LIMIT 4
");
$newProductStmt->execute();
$newProductResult = $newProductStmt->get_result();

while ($row = $newProductResult->fetch_assoc()) {
    $isUnread = strtotime($row['created_at']) > strtotime($notificationsLastSeen);

    if ($isUnread) {
        $notificationCount++;
    }

    $notifications[] = [
        'type' => 'info',
        'icon' => 'bi-plus-circle-fill',
        'title' => 'New Product Added',
        'message' => $row['product_name'] . ' was added to inventory.',
        'time' => date('M d, Y', strtotime($row['created_at'])),
        'is_unread' => $isUnread
    ];
}
$newProductStmt->close();

$displayNotifications = $notifications;
if (empty($displayNotifications)) {
    $displayNotifications[] = [
        'type' => 'empty',
        'icon' => 'bi-info-circle-fill',
        'title' => 'No Notifications Yet',
        'message' => 'No stock changes, alerts, or new products available yet.',
        'time' => 'Just now',
        'is_unread' => false
    ];
}

/*
|--------------------------------------------------------------------------
| PAGE MESSAGES
|--------------------------------------------------------------------------
*/
$popupMessage = "";
$popupType = "";

if (isset($_SESSION['success'])) {
    $popupMessage = $_SESSION['success'];
    $popupType = "success";
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $popupMessage = $_SESSION['error'];
    $popupType = "error";
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - NexGen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/sales_analytics.css">
</head>
<body>

<?php if (!empty($popupMessage)): ?>
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
            <div class="popup-icon"><?php echo $popupType === 'success' ? '✓' : '!'; ?></div>
            <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
            <p><?php echo htmlspecialchars($popupMessage); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="sales-analytics-page">
    <div class="container-fluid px-0">
        <div class="row g-0 min-vh-100">

            <aside class="col-12 col-lg-3 col-xl-2 sidebar-panel">
                <div class="sidebar-content h-100 d-flex flex-column">

                    <div class="sidebar-profile-card">
                        <form action="/NexGen/CODE/PHP/update_profile_image.php" method="POST" enctype="multipart/form-data" class="profile-edit-form">
                            <div class="profile-image-wrapper">
                                <img src="/NexGen/CODE/PHP/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="sidebar-profile-img">

                                <label for="new_profile_image" class="profile-edit-btn">
                                    <i class="bi bi-pencil-fill"></i>
                                </label>

                                <input type="file" name="new_profile_image" id="new_profile_image" accept="image/*" hidden>
                            </div>

                            <button type="submit" class="hidden-upload-btn" id="submitProfileBtn">Upload</button>
                        </form>

                        <h2 class="sidebar-username"><?php echo htmlspecialchars($displayName); ?></h2>
                        <p class="sidebar-fullname"><?php echo htmlspecialchars($fullName); ?></p>
                    </div>

                    <nav class="sidebar-menu">
                        <a href="/NexGen/CODE/PHP/dashboard.php" class="menu-item">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Home</span>
                        </a>

                        <button class="dropdown-btn notification-btn" type="button" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                            <span class="d-flex align-items-center gap-3">
                                <i class="bi bi-bell-fill"></i>
                                <span>Notification</span>
                            </span>
                            <span class="notif-badge"><?php echo $notificationCount; ?></span>
                        </button>

                        <button class="dropdown-btn appendices-btn" type="button" id="appendicesToggleBtn">
                            <span class="d-flex align-items-center gap-3">
                                <i class="bi bi-journal-text"></i>
                                <span>Appendices</span>
                            </span>
                        </button>

                        <a href="/NexGen/CODE/PHP/settings.php" class="menu-item">
                            <i class="bi bi-gear-wide-connected"></i>
                            <span>Settings</span>
                        </a>
                    </nav>

                    <div class="sidebar-bottom mt-auto">
                        <a href="/NexGen/CODE/PHP/logout.php" class="menu-item logout-item">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            </aside>

            <main class="col-12 col-lg-9 col-xl-10 main-panel">
                <div class="main-content" id="analyticsCaptureArea">

                    <section id="dashboardSection">
                        <section class="analytics-topbar">
                            <div class="analytics-title-wrap">
                                <div class="analytics-title-icon">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div>
                                    <h1>Sales & Analytics Dashboard</h1>
                                    <p>Showing data for: <strong><?php echo htmlspecialchars($rangeLabel); ?></strong></p>
                                </div>
                            </div>
                            <div class="analytics-divider"></div>
                        </section>

                        <section class="summary-section">
                            <div class="row g-4">
                                <div class="col-md-6 col-xl">
                                    <div class="summary-card card-blue">
                                        <div class="summary-card-top">
                                            <div>
                                                <div class="summary-label">Net Profit</div>
                                                <div class="summary-value">₱<?php echo number_format($netProfit, 2); ?></div>
                                            </div>
                                            <div class="summary-icon-box">
                                                <i class="bi bi-cash-stack"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-xl">
                                    <div class="summary-card card-blue">
                                        <div class="summary-card-top">
                                            <div>
                                                <div class="summary-label">Gross Revenue</div>
                                                <div class="summary-value">₱<?php echo number_format($grossRevenue, 2); ?></div>
                                            </div>
                                            <div class="summary-icon-box">
                                                <i class="bi bi-wallet2"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-xl">
                                    <div class="summary-card card-blue">
                                        <div class="summary-card-top">
                                            <div>
                                                <div class="summary-label">COGS</div>
                                                <div class="summary-value">₱<?php echo number_format($costOfGoodsSold, 2); ?></div>
                                            </div>
                                            <div class="summary-icon-box">
                                                <i class="bi bi-box-seam-fill"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-xl">
                                    <div class="summary-card card-gray">
                                        <div class="summary-card-top">
                                            <div>
                                                <div class="summary-label">Sales Growth</div>
                                                <div class="summary-value"><?php echo number_format($salesGrowth, 2); ?>%</div>
                                            </div>
                                            <div class="summary-icon-box">
                                                <i class="bi bi-bar-chart-fill"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-xl">
                                    <div class="summary-card card-gray">
                                        <div class="summary-card-top">
                                            <div>
                                                <div class="summary-label">Total Transactions</div>
                                                <div class="summary-value"><?php echo number_format($totalTransactions); ?></div>
                                            </div>
                                            <div class="summary-icon-box">
                                                <i class="bi bi-receipt-cutoff"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="charts-section mt-4">
                            <div class="row g-3">
                                <div class="col-xl-6">
                                    <div class="analytics-card">
                                        <div class="analytics-card-title">Daily Net Profit (Monday to Sunday)</div>
                                        <div class="canvas-holder">
                                            <canvas id="dailySalesChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-6">
                                    <div class="analytics-card">
                                        <div class="analytics-card-title">Monthly Net Profit (January to December)</div>
                                        <div class="canvas-holder">
                                            <canvas id="monthlyRevenueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="category-products-section mt-3">
                            <div class="row g-3">
                                <div class="col-xl-6">
                                    <div class="analytics-card h-100">
                                        <div class="analytics-card-title">Sales by Category</div>

                                        <div class="row g-3 align-items-center">
                                            <div class="col-lg-6">
                                                <div class="chart-wrap">
                                                    <canvas id="categoryChart"></canvas>
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="mini-table-wrap">
                                                    <div class="mini-table-title">Top 3 Categories / Units Sold</div>

                                                    <div class="table-responsive">
                                                        <table class="table mini-sales-table mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>Category</th>
                                                                    <th class="text-center">Units</th>
                                                                    <th class="text-end">%</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php
                                                            $topCategoryRows = array_slice($categoryBreakdown, 0, 3);
                                                            foreach ($topCategoryRows as $item):
                                                            ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                                    <td class="text-center"><?php echo (int)$item['units_sold']; ?></td>
                                                                    <td class="text-end"><?php echo number_format($item['percentage'], 2); ?>%</td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-6">
                                    <div class="analytics-card h-100">
                                        <div class="analytics-card-title">Top Products</div>

                                        <?php if (!empty($topProductsBySales)): ?>
                                            <div class="table-responsive">
                                                <table class="table top-product-table align-middle mb-0" id="topProductsTable">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th class="text-end">Total Sales</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($topProductsBySales as $product): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                            <td class="text-end">₱<?php echo number_format($product['sales'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-box large-empty">
                                                <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
                                                <h5>No Top Products Yet</h5>
                                                <p>Top products will appear here after you start adding real sales in Sales Recording.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="bottom-toolbar mt-4">
                            <div class="toolbar-box">
                                <div class="toolbar-left">
                                    <a href="?filter=today" class="filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>">Today</a>
                                    <a href="?filter=week" class="filter-btn <?php echo $filter === 'week' ? 'active' : ''; ?>">This Week</a>
                                    <a href="?filter=month" class="filter-btn <?php echo $filter === 'month' ? 'active' : ''; ?>">This Month</a>
                                    <button type="button" class="filter-btn <?php echo $filter === 'custom' ? 'active' : ''; ?>" data-bs-toggle="modal" data-bs-target="#customRangeModal">
                                        Custom Range
                                    </button>
                                </div>

                                <div class="toolbar-right">
                                    <button type="button" class="export-btn pdf-btn" id="exportPdfBtn">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                                    </button>
                                    <button type="button" class="export-btn excel-btn" id="exportExcelBtn">
                                        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                        </section>
                    </section>

                    <section id="appendicesSection" class="d-none">
                        <section class="analytics-topbar">
                            <div class="analytics-title-wrap">
                                <div class="analytics-title-icon">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <div>
                                    <h1>Key Components</h1>
                                    <p>Sales analytics feature guide</p>
                                </div>
                            </div>
                            <div class="analytics-divider"></div>
                        </section>

                        <div class="appendices-card">
                            <div class="appendices-header">
                                <i class="bi bi-search"></i>
                                <span>Key Components</span>
                            </div>

                            <div class="row g-4 appendices-grid">
                                <div class="col-md-6">
                                    <div class="appendix-item">
                                        <h3><i class="bi bi-box-seam"></i> Sales Summary</h3>
                                        <ul>
                                            <li>Net Profit</li>
                                            <li>Gross Revenue</li>
                                            <li>COGS</li>
                                            <li>Total Transactions</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="appendix-item">
                                        <h3><i class="bi bi-calendar3"></i> Time Filters</h3>
                                        <ul>
                                            <li>Today / This Week</li>
                                            <li>This Month</li>
                                            <li>Custom Date Range</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="appendix-item">
                                        <h3><i class="bi bi-bar-chart-line"></i> Sales Trends</h3>
                                        <ul>
                                            <li>Daily Net Profit Chart</li>
                                            <li>Monthly Net Profit Graph</li>
                                            <li>Sales by Category</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="appendix-item">
                                        <h3><i class="bi bi-trophy-fill"></i> Performance Analysis</h3>
                                        <ul>
                                            <li>Top Selling Products</li>
                                            <li>Category Comparison</li>
                                            <li>Sales Distribution</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="appendices-actions">
                                <button type="button" class="btn btn-primary" id="backToDashboardBtn">Back to Dashboard</button>
                            </div>
                        </div>
                    </section>

            
                </div>
            </main>
        </div>
    </div>
</div>

<div class="modal fade" id="notificationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content notifications-modal-content">
            <div class="notifications-modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill"></i>
                    <span>Notifications</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="view-all-label">View All</span>
                    <span class="notif-badge-modal"><?php echo $notificationCount; ?></span>
                </div>
            </div>

            <div class="notifications-modal-body">
                <?php foreach ($displayNotifications as $item): ?>
                    <div class="notification-card">
                        <div class="notification-card-icon <?php echo htmlspecialchars($item['type']); ?>">
                            <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                        </div>

                        <div class="notification-card-content">
                            <div class="notification-card-top">
                                <h5><?php echo htmlspecialchars($item['title']); ?></h5>
                                <span><?php echo htmlspecialchars($item['time']); ?></span>
                            </div>
                            <p><?php echo htmlspecialchars($item['message']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="notifications-modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customRangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-range-content">
            <form method="GET" action="">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Choose Custom Range</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="filter" value="custom">

                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($customStart); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($customEnd); ?>" required>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary">Apply Range</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'chatbot.php'; ?>
<script>
window.salesAnalyticsData = {
    dailyLabels: <?php echo json_encode($dailyLabels); ?>,
    dailySalesData: <?php echo json_encode($dailySalesData); ?>,
    monthlyLabels: <?php echo json_encode($monthlyLabels); ?>,
    monthlyRevenueData: <?php echo json_encode($monthlyRevenueData); ?>,
    categoryLabels: <?php echo json_encode($categoryLabels); ?>,
    categorySalesData: <?php echo json_encode($categorySalesData); ?>,
    rangeLabel: <?php echo json_encode($rangeLabel); ?>,
    todaySales: <?php echo json_encode($todayProfit); ?>,
    weekSales: <?php echo json_encode($weekProfit); ?>,
    monthSales: <?php echo json_encode($monthProfit); ?>,
    netProfit: <?php echo json_encode($netProfit); ?>,
    grossRevenue: <?php echo json_encode($grossRevenue); ?>,
    costOfGoodsSold: <?php echo json_encode($costOfGoodsSold); ?>,
    totalTransactions: <?php echo json_encode($totalTransactions); ?>,
    salesGrowth: <?php echo json_encode(round($salesGrowth, 2)); ?>,

    /* legacy compatibility if your JS still references old names */
    netSales: <?php echo json_encode($netProfit); ?>,
    totalProfit: <?php echo json_encode($netProfit); ?>
};
</script>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="/NexGen/CODE/JS/sales_analytics.js?v=wave2026eval"></script>
<?php include 'footer.php'; ?>
</body>
</html>