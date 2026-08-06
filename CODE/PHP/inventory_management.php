<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ((int)($_SESSION['can_inventory'] ?? 0) !== 1) {
    $_SESSION['error'] = 'You do not have access to Inventory Management.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$displayName  = $_SESSION['username'] ?? 'Client';
$fullName     = $_SESSION['full_name'] ?? 'Client';

$userId       = $_SESSION['user_id'];
$role         = $_SESSION['role'] ?? 'employee';
$isOwner      = $role === 'owner';
$isEmployee   = $role === 'employee';

$categories = [];
$categoryQuery = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($categoryQuery) {
    while ($row = $categoryQuery->fetch_assoc()) {
        $categories[] = $row;
    }
}

$search = trim($_GET['search'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT p.*, c.category_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.brand LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
    $types .= "i";
}

if ($statusFilter === 'active') {
    $sql .= " AND p.is_active = 1";
} elseif ($statusFilter === 'inactive' || $statusFilter === 'archived') {
    $sql .= " AND p.is_active = 0";
} elseif ($statusFilter === 'low') {
    $sql .= " AND p.is_active = 1 AND p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
} elseif ($statusFilter === 'out') {
    $sql .= " AND p.is_active = 1 AND p.stock_quantity <= 0";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Query error: " . $conn->error);
}

$allProducts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allProducts[] = $row;
    }
}
$totalFetchedProducts = count($allProducts);

$productDisplayLimit = 10;
$showAllProducts = isset($_GET['show_all']) && $_GET['show_all'] === '1';
$productsToDisplay = $showAllProducts ? $allProducts : array_slice($allProducts, 0, $productDisplayLimit);

$viewAllParams = $_GET;
$viewAllParams['show_all'] = 1;
$viewAllUrl = '/NexGen/CODE/PHP/inventory_management.php?' . http_build_query($viewAllParams);

$showTop10Params = $_GET;
unset($showTop10Params['show_all']);
$showTop10Url = '/NexGen/CODE/PHP/inventory_management.php'
    . (!empty($showTop10Params) ? '?' . http_build_query($showTop10Params) : '');

/* COUNTS */
$totalProducts = 0;
$activeProductsCount = 0;
$inactiveProductsCount = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

$countQuery = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_products,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_products,
        SUM(CASE WHEN is_active = 1 AND stock_quantity <= reorder_level AND stock_quantity > 0 THEN 1 ELSE 0 END) AS low_stock_count,
        SUM(CASE WHEN is_active = 1 AND stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count
    FROM products
");

if ($countQuery && $countRow = $countQuery->fetch_assoc()) {
    $totalProducts = (int)($countRow['total_products'] ?? 0);
    $activeProductsCount = (int)($countRow['active_products'] ?? 0);
    $inactiveProductsCount = (int)($countRow['inactive_products'] ?? 0);
    $lowStockCount = (int)($countRow['low_stock_count'] ?? 0);
    $outOfStockCount = (int)($countRow['out_of_stock_count'] ?? 0);
}

/* LOW STOCK */
$lowStockItems = [];
$lowStockQuery = $conn->query("
    SELECT product_name, product_code, stock_quantity, reorder_level, on_order_level, product_image
    FROM products
    WHERE is_active = 1
      AND stock_quantity <= reorder_level
      AND stock_quantity > 0
    ORDER BY stock_quantity ASC, product_name ASC
    LIMIT 10
");
if ($lowStockQuery) {
    while ($row = $lowStockQuery->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}
$lowStockFeature = $lowStockItems[0] ?? null;

/* OUT OF STOCK - LATEST FIRST */
$outOfStockItems = [];
$outOfStockQuery = $conn->query("
    SELECT
        p.product_name,
        p.product_code,
        p.stock_quantity,
        p.on_order_level,
        p.product_image,
        latest_move.latest_stock_out_at
    FROM products p
    LEFT JOIN (
        SELECT product_id, MAX(created_at) AS latest_stock_out_at
        FROM stock_movements
        WHERE movement_type = 'stock_out'
        GROUP BY product_id
    ) latest_move ON latest_move.product_id = p.id
    WHERE p.is_active = 1
      AND p.stock_quantity <= 0
    ORDER BY
        CASE WHEN latest_move.latest_stock_out_at IS NULL THEN 1 ELSE 0 END ASC,
        latest_move.latest_stock_out_at DESC,
        p.created_at DESC,
        p.id DESC
    LIMIT 10
");
if ($outOfStockQuery) {
    while ($row = $outOfStockQuery->fetch_assoc()) {
        $outOfStockItems[] = $row;
    }
}
$outOfStockFeature = $outOfStockItems[0] ?? null;

/* LAST SOLD / STOCK HISTORY FEATURE */
$latestSoldItem = null;
$latestSoldQuery = $conn->query("
    SELECT
        sm.quantity,
        sm.created_at,
        sm.remarks,
        p.product_name,
        p.product_code,
        p.product_image
    FROM stock_movements sm
    INNER JOIN products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'stock_out'
    ORDER BY sm.created_at DESC
    LIMIT 1
");
if ($latestSoldQuery && $latestSoldQuery->num_rows > 0) {
    $latestSoldItem = $latestSoldQuery->fetch_assoc();
}

/* RECENT MOVEMENTS */
$recentMovements = [];
$movementQuery = $conn->query("
    SELECT sm.*, p.product_name, p.product_code, p.product_image
    FROM stock_movements sm
    INNER JOIN products p ON sm.product_id = p.id
    WHERE sm.created_at >= (NOW() - INTERVAL 7 DAY)
    ORDER BY sm.created_at DESC
    LIMIT 5
");
if ($movementQuery) {
    while ($row = $movementQuery->fetch_assoc()) {
        $recentMovements[] = $row;
    }
}

$popupMessage = $_SESSION['inventory_success'] ?? $_SESSION['inventory_error'] ?? "";
$popupType = isset($_SESSION['inventory_success']) ? "success" : (isset($_SESSION['inventory_error']) ? "error" : "");
unset($_SESSION['inventory_success'], $_SESSION['inventory_error']);

function getInventoryState(array $product): array {
    $stock = (int)$product['stock_quantity'];
    $reorder = (int)$product['reorder_level'];
    $onOrder = (int)($product['on_order_level'] ?? 0);
    $isActive = (int)$product['is_active'] === 1;

    if (!$isActive) {
        return ['label' => 'Archived', 'class' => 'inactive'];
    }

    if ($stock <= 0 && $onOrder > 0) {
        return ['label' => 'Out / Incoming', 'class' => 'warning'];
    }

    if ($stock <= 0) {
        return ['label' => 'Out of Stock', 'class' => 'danger'];
    }

    if ($stock <= $reorder && $onOrder > 0) {
        return ['label' => 'Low / Incoming', 'class' => 'warning'];
    }

    if ($stock <= $reorder) {
        return ['label' => 'Low Stock', 'class' => 'warning'];
    }

    return ['label' => 'Good Stock', 'class' => 'active'];
}

function inventoryImagePath(?string $path): string {
    $clean = trim((string)$path);
    if ($clean === '') {
        return '/NexGen/IMAGES/default-product.png';
    }
    return '/NexGen/CODE/PHP/' . ltrim($clean, '/');
}

function isActiveTab(string $current, string $tab): string {
    return $current === $tab ? 'active' : '';
}

$currentStatusForTab = $statusFilter;
if ($currentStatusForTab === '') {
    $currentStatusForTab = 'all';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - NexGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/inventory_management.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css?v=2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

<div class="inventory-page">

    <?php include 'header.php'; ?>

    <main class="inventory-wrapper">

        <section class="inventory-header">
            <div class="inventory-header-card">
                <div class="inventory-header-row">
                    <div class="inventory-header-left">
                        <h1>Inventory Management</h1>
                    </div>

                    <div class="inventory-header-tools">
                        <div class="inventory-search-box">
                            <i class="bi bi-search"></i>
                            <input
                                type="text"
                                name="search"
                                id="inventorySearchInput"
                                form="inventoryFilterForm"
                                placeholder="Search products..."
                                value="<?php echo htmlspecialchars($search); ?>"
                                autocomplete="off"
                            >
                        </div>

                        <div class="inventory-filter-wrap">
                            <button type="button" class="inventory-filter-btn" id="toggleFilterTool" aria-haspopup="true" aria-expanded="false" aria-label="Filter products">
                                <i class="bi bi-funnel-fill"></i>
                                <span>Filter</span>
                            </button>

                            <div class="floating-filter-box" id="floatingFilterBox">
                                <input type="hidden" name="category" id="inventoryCategoryFilter" form="inventoryFilterForm" value="<?php echo (int)$categoryFilter; ?>">
                                <input type="hidden" name="status" id="inventoryStatusFilter" form="inventoryFilterForm" value="<?php echo htmlspecialchars($statusFilter); ?>">

                                <div class="filter-group">
                                    <span class="filter-group-label">Category</span>
                                    <div class="filter-chip-list">
                                        <button type="button" class="filter-chip <?php echo $categoryFilter === 0 ? 'active' : ''; ?>" data-filter-kind="category" data-filter-value="0">All Categories</button>
                                        <?php foreach ($categories as $category): ?>
                                            <button type="button" class="filter-chip <?php echo ($categoryFilter == $category['id']) ? 'active' : ''; ?>" data-filter-kind="category" data-filter-value="<?php echo (int)$category['id']; ?>">
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="filter-group">
                                    <span class="filter-group-label">Status</span>
                                    <div class="filter-chip-list">
                                        <button type="button" class="filter-chip <?php echo $statusFilter === '' ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="">All Status</button>
                                        <button type="button" class="filter-chip <?php echo $statusFilter === 'active' ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="active">Active</button>
                                        <button type="button" class="filter-chip <?php echo ($statusFilter === 'archived' || $statusFilter === 'inactive') ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="archived">Archived</button>
                                        <button type="button" class="filter-chip <?php echo $statusFilter === 'low' ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="low">Low Stock</button>
                                        <button type="button" class="filter-chip <?php echo $statusFilter === 'out' ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="out">Out of Stock</button>
                                        <button type="button" class="filter-chip <?php echo $statusFilter === 'expired' ? 'active' : ''; ?>" data-filter-kind="status" data-filter-value="expired">Expired</button>
                                    </div>
                                </div>

                                <button type="button" class="clear-filters-link" id="clearFiltersBtn">Clear filters</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="inventory-meta">
                    <i class="bi bi-archive"></i>
                    <span><?php echo $totalProducts; ?> registered products</span>
                    <span class="meta-divider">|</span>
                    <span class="inventory-subtitle">Store, Manage, and Adjust your Inventory Items Easily</span>
                </div>
            </div>

            <div class="header-actions">
                <?php if ($isOwner): ?>
                    <a href="/NexGen/CODE/PHP/inventory_export.php" class="ghost-btn">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Export Report</span>
                    </a>
                <?php endif; ?>

                <button class="ghost-btn" id="openCategoryModal" type="button">
                    <i class="bi bi-folder2"></i>
                    <span>Manage Categories</span>
                </button>
                <button class="primary-btn" id="openProductModal" type="button">
                    <i class="bi bi-plus-lg"></i>
                    <span>Add Product</span>
                </button>
            </div>
        </section>

        <!-- Hidden form: not visually rendered. The search input and category/status
             chips above use the form="inventoryFilterForm" attribute so their values
             are still collected by submitFiltersInstantly() no matter where they sit
             in the page (HTML5 form association doesn't require DOM nesting). -->
        <form method="GET" id="inventoryFilterForm" action="/NexGen/CODE/PHP/inventory_management.php" class="hidden-filter-form"></form>

        <div id="inventoryDynamicArea">
            <section class="inventory-tabs">
                <a href="/NexGen/CODE/PHP/inventory_management.php" class="inventory-tab <?php echo isActiveTab($currentStatusForTab, 'all'); ?>" data-filter-link>
                    All <span><?php echo $totalProducts; ?></span>
                </a>
                <a href="/NexGen/CODE/PHP/inventory_management.php?status=active" class="inventory-tab <?php echo isActiveTab($currentStatusForTab, 'active'); ?>" data-filter-link>
                    Active <span><?php echo $activeProductsCount; ?></span>
                </a>
                <a href="/NexGen/CODE/PHP/inventory_management.php?status=low" class="inventory-tab <?php echo isActiveTab($currentStatusForTab, 'low'); ?>" data-filter-link>
                    Low Stock <span><?php echo $lowStockCount; ?></span>
                </a>
                <a href="/NexGen/CODE/PHP/inventory_management.php?status=out" class="inventory-tab <?php echo isActiveTab($currentStatusForTab, 'out'); ?>" data-filter-link>
                    Out of Stock <span><?php echo $outOfStockCount; ?></span>
                </a>
                <a href="/NexGen/CODE/PHP/inventory_management.php?status=archived" class="inventory-tab <?php echo isActiveTab($currentStatusForTab, 'archived'); ?>" data-filter-link>
                    Archived <span><?php echo $inactiveProductsCount; ?></span>
                </a>
            </section>

            <section class="inventory-summary-grid">

                <div class="summary-card gradient-card">
                    <div class="summary-card-top">
                        <h3>Low Stock Alert</h3>
                        <span class="summary-badge"><?php echo count($lowStockItems); ?></span>
                    </div>

                    <?php if ($lowStockFeature): ?>
                        <div class="summary-card-body with-image">
                            <div class="summary-text">
                                <h4><?php echo htmlspecialchars($lowStockFeature['product_name']); ?></h4>
                                <p>Code: <?php echo htmlspecialchars($lowStockFeature['product_code']); ?></p>
                                <p><?php echo (int)$lowStockFeature['stock_quantity']; ?> in stock</p>
                                <p>Reorder at <?php echo (int)$lowStockFeature['reorder_level']; ?></p>

                                <div class="mini-pill">
                                    <?php echo (int)($lowStockFeature['on_order_level'] ?? 0); ?> incoming
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="summary-empty">No low stock alerts right now.</div>
                    <?php endif; ?>
                </div>

                <div class="summary-card gradient-card">
                    <div class="summary-card-top">
                        <h3>Out of Stock</h3>
                        <span class="summary-badge blue"><?php echo count($outOfStockItems); ?></span>
                    </div>

                    <?php if ($outOfStockFeature): ?>
                        <div class="summary-card-body with-image">
                            <div class="summary-text">
                                <h4><?php echo htmlspecialchars($outOfStockFeature['product_name']); ?></h4>
                                <p>Code: <?php echo htmlspecialchars($outOfStockFeature['product_code']); ?></p>
                                <p><?php echo (int)$outOfStockFeature['stock_quantity']; ?> in stock</p>

                                <div class="mini-pill">
                                    <?php echo (int)($outOfStockFeature['on_order_level'] ?? 0); ?> incoming
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="summary-empty">No out of stock items right now.</div>
                    <?php endif; ?>
                </div>

                <div class="summary-card history-card">
                    <div class="summary-card-top">
                        <h3>Stock History</h3>
                        <div class="history-chip-wrap">
                            <span class="history-chip">Past 7 days</span>
                        </div>
                    </div>

                    <?php if ($latestSoldItem): ?>
                        <div class="history-main">
                            <div class="history-text">
                                <p class="history-caption">Last product sold :</p>
                                <h4><?php echo htmlspecialchars($latestSoldItem['product_name']); ?></h4>
                                <div class="history-row">
                                    <span><?php echo date("M d", strtotime($latestSoldItem['created_at'])); ?></span>
                                    <strong>-<?php echo (int)$latestSoldItem['quantity']; ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="summary-empty">No recent stock-out history found.</div>
                    <?php endif; ?>

                    <div class="history-line">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <div class="history-legend">
                        <span class="legend-dot recent"></span> Gold = today's activity
                        <span class="legend-dot earlier"></span> Blue = earlier this week
                    </div>
                </div>

            </section>

            <section class="inventory-table-shell">
                <div class="product-list-header">
                    <h3>Products</h3>
                    <?php if ($showAllProducts && $totalFetchedProducts > $productDisplayLimit): ?>
                        <a href="<?php echo htmlspecialchars($showTop10Url); ?>" class="view-all-link" data-filter-link>
                            Show Top <?php echo $productDisplayLimit; ?>
                        </a>
                    <?php elseif ($totalFetchedProducts > $productDisplayLimit): ?>
                        <a href="<?php echo htmlspecialchars($viewAllUrl); ?>" class="view-all-link" data-filter-link>
                            View All (<?php echo $totalFetchedProducts; ?>) <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="table-scroll product-table-scroll">
                    <table class="inventory-table">
                        <tbody>
                        <?php if (!empty($productsToDisplay)): ?>
                            <?php foreach ($productsToDisplay as $product): ?>
                                <?php
                                    $stockClass = 'stock-good';
                                    if ((int)$product['stock_quantity'] <= 0) {
                                        $stockClass = 'stock-out';
                                    } elseif ((int)$product['stock_quantity'] <= (int)$product['reorder_level']) {
                                        $stockClass = 'stock-low';
                                    }

                                    $invState = getInventoryState($product);
                                    $productIsArchived = ((int)$product['is_active'] === 0);
                                ?>
                                <tr>
                                    <td class="cell-image">
                                        <div class="product-image-cell">
                                            <img src="<?php echo htmlspecialchars(inventoryImagePath($product['product_image'] ?? '')); ?>" alt="Product" class="product-thumb">
                                        </div>
                                    </td>

                                    <td class="cell-main">
                                        <div class="product-main-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div class="product-sub-brand">
                                            SKU: <?php echo htmlspecialchars($product['product_code']); ?>
                                            <?php if (!empty($product['brand'])): ?>
                                                &nbsp;&bull;&nbsp;<?php echo htmlspecialchars($product['brand']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="stock-line-row">
                                            <span class="stock-line <?php echo $stockClass; ?>">
                                                <?php echo htmlspecialchars($invState['label']); ?> &bull; <?php echo (int)$product['stock_quantity']; ?> <?php echo (int)$product['stock_quantity'] === 1 ? 'Left' : 'in stock'; ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="cell-actions actions-cell">
                                        <div class="action-menu-wrap">
                                            <button type="button" class="action-dots-btn reorder-style-btn" data-action-menu-toggle>
                                                <i class="bi bi-arrow-repeat"></i>
                                                <span>Manage</span>
                                            </button>

                                            <div class="action-dropdown" data-action-menu>
                                                <button
                                                    class="action-item edit-btn"
                                                    type="button"
                                                    data-id="<?php echo (int)$product['id']; ?>"
                                                    data-code="<?php echo htmlspecialchars($product['product_code']); ?>"
                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                    data-category="<?php echo (int)$product['category_id']; ?>"
                                                    data-brand="<?php echo htmlspecialchars($product['brand']); ?>"
                                                    data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
                                                    data-cost="<?php echo htmlspecialchars($product['cost_price']); ?>"
                                                    data-selling="<?php echo htmlspecialchars($product['selling_price']); ?>"
                                                    data-stock="<?php echo (int)$product['stock_quantity']; ?>"
                                                    data-reorder="<?php echo (int)$product['reorder_level']; ?>"
                                                    data-onorder="<?php echo (int)($product['on_order_level'] ?? 0); ?>"
                                                    data-expiry="<?php echo htmlspecialchars($product['expiry_date'] ?? ''); ?>"
                                                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                                    data-active="<?php echo (int)$product['is_active']; ?>"
                                                    data-image="<?php echo htmlspecialchars($product['product_image'] ?? ''); ?>"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                    <span>Edit</span>
                                                </button>

                                                <button
                                                    class="action-item stock-btn"
                                                    type="button"
                                                    data-stock-id="<?php echo (int)$product['id']; ?>"
                                                    data-stock-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                    data-current-onorder="<?php echo (int)($product['on_order_level'] ?? 0); ?>"
                                                >
                                                    <i class="bi bi-arrow-left-right"></i>
                                                    <span>Adjust Stock</span>
                                                </button>

                                                <?php if ($isOwner): ?>
                                                    <?php if ($productIsArchived): ?>
                                                        <a
                                                            href="/NexGen/CODE/PHP/inventory_restore.php?id=<?php echo (int)$product['id']; ?>"
                                                            class="action-item restore-item"
                                                        >
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                            <span>Restore Product</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <a
                                                            href="/NexGen/CODE/PHP/inventory_delete.php?id=<?php echo (int)$product['id']; ?>"
                                                            class="action-item delete-item"
                                                        >
                                                            <i class="bi bi-trash3-fill"></i>
                                                            <span>Archive Product</span>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">No products found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal-overlay" id="productModal">
        <div class="product-modal">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button type="button" class="close-modal-btn" id="closeProductModal" onclick="document.getElementById('productModal').classList.remove('show'); document.body.style.overflow='';">&times;</button>
            </div>

            <div class="wizard-stepper" id="productWizardStepper">
                <div class="step-node is-active" data-step="1">
                    <span class="step-circle">1</span>
                    <span class="step-label">Basic Info</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="2">
                    <span class="step-circle">2</span>
                    <span class="step-label">Pricing</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="3">
                    <span class="step-circle">3</span>
                    <span class="step-label">Stock</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="4">
                    <span class="step-circle">4</span>
                    <span class="step-label">Details</span>
                </div>
            </div>

            <form action="/NexGen/CODE/PHP/inventory_save.php" method="POST" enctype="multipart/form-data" class="product-form wizard-form" id="addProductWizardForm" novalidate>

                <!-- STEP 1: BASIC INFO -->
                <div class="wizard-step is-active" data-step="1">
                    <div class="wizard-step-basic">
                        <div class="form-image-preview">
                            <img src="/NexGen/IMAGES/default-product.png" alt="Preview" id="previewImage">
                            <label for="product_image" class="image-upload-label">Upload Product Image</label>
                            <input type="file" name="product_image" id="product_image" accept="image/*">
                        </div>

                        <div class="form-fields">
                            <div class="form-group">
                                <label>Product Code</label>
                                <input type="text" name="product_code" required>
                            </div>

                            <div class="form-group">
                                <label>Product Name</label>
                                <input type="text" name="product_name" required>
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Brand</label>
                                <input type="text" name="brand">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: PRICING -->
                <div class="wizard-step" data-step="2">
                    <div class="form-fields">
                        <div class="form-group full-width">
                            <label>Unit</label>
                            <input type="text" name="unit" placeholder="e.g. pcs, bottle, pack, kilo" required>
                        </div>

                        <div class="form-group">
                            <label>Cost Price</label>
                            <input type="number" step="0.01" min="0" name="cost_price" required>
                        </div>

                        <div class="form-group">
                            <label>Selling Price</label>
                            <input type="number" step="0.01" min="0" name="selling_price" required>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: STOCK -->
                <div class="wizard-step" data-step="3">
                    <div class="form-fields">
                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input type="number" min="0" name="stock_quantity" required>
                        </div>

                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date">
                        </div>

                        <?php if ($isOwner): ?>
                            <div class="form-group">
                                <label>Reorder Level</label>
                                <input type="number" min="0" name="reorder_level" value="5" required>
                            </div>

                            <div class="form-group">
                                <label>On Order Level</label>
                                <input type="number" min="0" name="on_order_level" value="0" required>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- STEP 4: DETAILS -->
                <div class="wizard-step" data-step="4">
                    <div class="form-fields">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Archived</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <div class="wizard-footer">
                    <div class="step-progress-text">Step <span id="wizardCurrentStepNum">1</span> of 4</div>
                    <div class="step-dots" id="wizardStepDots">
                        <span class="step-dot is-active" data-dot="1"></span>
                        <span class="step-dot" data-dot="2"></span>
                        <span class="step-dot" data-dot="3"></span>
                        <span class="step-dot" data-dot="4"></span>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="wizard-back-btn" id="wizardBackBtn">Back</button>
                    <button type="button" class="save-btn wizard-next-btn is-visible" id="wizardNextBtn">Next Step &rarr;</button>
                    <button type="submit" class="save-btn wizard-submit-btn" id="wizardSubmitBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div class="modal-overlay" id="editProductModal">
        <div class="product-modal">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <button type="button" class="close-modal-btn" id="closeEditProductModal" onclick="document.getElementById('editProductModal').classList.remove('show'); document.body.style.overflow='';">&times;</button>
            </div>

            <div class="wizard-stepper" id="editProductWizardStepper">
                <div class="step-node is-active" data-step="1">
                    <span class="step-circle">1</span>
                    <span class="step-label">Basic Info</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="2">
                    <span class="step-circle">2</span>
                    <span class="step-label">Pricing</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="3">
                    <span class="step-circle">3</span>
                    <span class="step-label">Stock</span>
                </div>
                <div class="step-line"></div>
                <div class="step-node" data-step="4">
                    <span class="step-circle">4</span>
                    <span class="step-label">Details</span>
                </div>
            </div>

            <form action="/NexGen/CODE/PHP/inventory_update.php" method="POST" enctype="multipart/form-data" class="product-form wizard-form" id="editProductWizardForm" novalidate>
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="old_image" id="edit_old_image">

                <!-- STEP 1: BASIC INFO -->
                <div class="wizard-step is-active" data-step="1">
                    <div class="wizard-step-basic">
                        <div class="form-image-preview">
                            <img src="/NexGen/IMAGES/default-product.png" alt="Preview" id="editPreviewImage">
                            <label for="edit_product_image" class="image-upload-label">Change Product Image</label>
                            <input type="file" name="product_image" id="edit_product_image" accept="image/*">
                        </div>

                        <div class="form-fields">
                            <div class="form-group">
                                <label>Product Code</label>
                                <input type="text" name="product_code" id="edit_product_code" required>
                            </div>

                            <div class="form-group">
                                <label>Product Name</label>
                                <input type="text" name="product_name" id="edit_product_name" required>
                            </div>

                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id" id="edit_category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Brand</label>
                                <input type="text" name="brand" id="edit_brand">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: PRICING -->
                <div class="wizard-step" data-step="2">
                    <div class="form-fields">
                        <div class="form-group full-width">
                            <label>Unit</label>
                            <input type="text" name="unit" id="edit_unit" required>
                        </div>

                        <div class="form-group">
                            <label>Cost Price</label>
                            <input type="number" step="0.01" min="0" name="cost_price" id="edit_cost_price" required>
                        </div>

                        <div class="form-group">
                            <label>Selling Price</label>
                            <input type="number" step="0.01" min="0" name="selling_price" id="edit_selling_price" required>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: STOCK -->
                <div class="wizard-step" data-step="3">
                    <div class="form-fields">
                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input type="number" min="0" name="stock_quantity" id="edit_stock_quantity" required>
                        </div>

                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry_date">
                        </div>

                        <?php if ($isOwner): ?>
                            <div class="form-group">
                                <label>Reorder Level</label>
                                <input type="number" min="0" name="reorder_level" id="edit_reorder_level" required>
                            </div>

                            <div class="form-group">
                                <label>On Order Level</label>
                                <input type="number" min="0" name="on_order_level" id="edit_on_order_level" required>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- STEP 4: DETAILS -->
                <div class="wizard-step" data-step="4">
                    <div class="form-fields">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" id="edit_is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Archived</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" rows="4" id="edit_description"></textarea>
                        </div>
                    </div>
                </div>

                <div class="wizard-footer">
                    <div class="step-progress-text">Step <span id="editWizardCurrentStepNum">1</span> of 4</div>
                    <div class="step-dots" id="editWizardStepDots">
                        <span class="step-dot is-active" data-dot="1"></span>
                        <span class="step-dot" data-dot="2"></span>
                        <span class="step-dot" data-dot="3"></span>
                        <span class="step-dot" data-dot="4"></span>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="wizard-back-btn" id="editWizardBackBtn">Back</button>
                    <button type="button" class="save-btn wizard-next-btn is-visible" id="editWizardNextBtn">Next Step &rarr;</button>
                    <button type="submit" class="save-btn wizard-submit-btn" id="editWizardSubmitBtn">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- STOCK MODAL -->
    <div class="modal-overlay" id="stockModal">
        <div class="stock-modal">
            <div class="modal-header">
                <h2>Stock Movement</h2>
                <button type="button" class="close-modal-btn" id="closeStockModal" onclick="document.getElementById('stockModal').classList.remove('show'); document.body.style.overflow='';">&times;</button>
            </div>

            <form action="/NexGen/CODE/PHP/inventory_stock.php" method="POST" class="stock-form">
                <input type="hidden" name="product_id" id="stock_product_id">

                <div class="form-group full-width">
                    <label>Product</label>
                    <input type="text" id="stock_product_name" readonly>
                </div>

                <div class="form-group">
                    <label>Movement Type</label>
                    <select name="movement_type" id="stock_movement_type" required>
                        <option value="stock_in">Stock In</option>
                        <option value="stock_out">Stock Out</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" required>
                </div>

                <?php if ($isOwner): ?>
                    <div class="form-group">
                        <label>Current On Order</label>
                        <input type="number" id="stock_current_on_order" readonly>
                    </div>

                    <div class="form-group" id="onOrderOwnerFields">
                        <label>Add to On Order</label>
                        <input type="number" name="on_order_add" id="on_order_add" min="0" value="0">
                    </div>

                    <div class="form-group full-width" id="deductOnOrderWrap">
                        <label class="checkbox-row">
                            <input type="checkbox" name="deduct_from_on_order" id="deduct_from_on_order" value="1">
                            Deduct this stock-in quantity from On Order
                        </label>
                    </div>
                <?php endif; ?>

                <div class="form-group full-width">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3" placeholder="Optional remarks"></textarea>
                </div>

                <div class="form-group form-actions full-width">
                    <button type="submit" class="save-btn">Save Movement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CATEGORY MODAL -->
    <div class="modal-overlay" id="categoryModal">
        <div class="category-modal">
            <div class="modal-header">
                <h2>Manage Categories</h2>
                <button type="button" class="close-modal-btn" id="closeCategoryModal" onclick="document.getElementById('categoryModal').classList.remove('show'); document.body.style.overflow='';">&times;</button>
            </div>

            <form action="/NexGen/CODE/PHP/category_save.php" method="POST" class="category-form">
                <div class="form-group full-width">
                    <label>New Category Name</label>
                    <input type="text" name="category_name" required>
                </div>

                <div class="form-group form-actions full-width">
                    <button type="submit" class="save-btn">Add Category</button>
                </div>
            </form>

            <div class="category-list-wrap">
                <h3>Existing Categories</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <li><?php echo htmlspecialchars($category['category_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <footer class="footer-section" id="footer-section">
        <div class="footer-top-line"></div>
        <p>Copyright © 2026 NexGen Micro-Enterprise</p>
    </footer>
</div>

<?php include 'chatbot.php'; ?>
<script src="/NexGen/CODE/JS/inventory_management.js?v=<?php echo time(); ?>"></script>
</body>
</html>