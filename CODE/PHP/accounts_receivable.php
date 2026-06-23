<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ((int)($_SESSION['can_accounts_receivable'] ?? 0) !== 1) {
    $_SESSION['error'] = 'You do not have access to Accounts Receivable.';
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

$displayName = $_SESSION['username'] ?? 'Client';
$fullName = $_SESSION['full_name'] ?? 'Client';
$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$role = $_SESSION['role'];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

$allowedStatuses = ['Unpaid', 'Partially Paid', 'Paid', 'Overdue'];

/*
|--------------------------------------------------------------------------
| OPTIMIZED MAIN RECEIVABLE QUERY
| - Computes live_status once only
| - Avoids repeating the same CASE in WHERE and ORDER BY
| - Selects only needed columns instead of ar.*
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        x.id,
        x.sale_id,
        x.customer_id,
        x.total_amount,
        x.amount_paid,
        x.balance_due,
        x.due_date,
        x.status,
        x.notes,
        x.created_at,
        x.updated_at,
        x.customer_name,
        x.customer_code,
        x.sales_no,
        x.live_status
    FROM (
        SELECT
            ar.id,
            ar.sale_id,
            ar.customer_id,
            ar.total_amount,
            ar.amount_paid,
            ar.balance_due,
            ar.due_date,
            ar.status,
            ar.notes,
            ar.created_at,
            ar.updated_at,
            c.customer_name,
            c.customer_code,
            s.sales_no,
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
        INNER JOIN customers c ON ar.customer_id = c.id
        INNER JOIN sales s ON ar.sale_id = s.id
    ) x
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (x.customer_name LIKE ? OR x.sales_no LIKE ?) ";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND x.live_status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= "
    ORDER BY
        CASE x.live_status
            WHEN 'Overdue' THEN 1
            WHEN 'Unpaid' THEN 2
            WHEN 'Partially Paid' THEN 3
            ELSE 4
        END,
        x.due_date ASC,
        x.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
$stmt->close();

$summary = [
    'total_receivables' => 0,
    'total_balance_due' => 0,
    'overdue_count' => 0,
    'unpaid_count' => 0,
    'partial_count' => 0
];

$summaryQuery = $conn->query("
    SELECT
        COUNT(*) AS total_receivables,
        COALESCE(SUM(balance_due), 0) AS total_balance_due,
        SUM(
            CASE
                WHEN due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE() AND balance_due > 0 THEN 1
                ELSE 0
            END
        ) AS overdue_count,
        SUM(
            CASE
                WHEN balance_due > 0
                 AND (amount_paid IS NULL OR amount_paid <= 0)
                 AND NOT (due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE())
                THEN 1
                ELSE 0
            END
        ) AS unpaid_count,
        SUM(
            CASE
                WHEN amount_paid > 0 AND balance_due > 0
                 AND NOT (due_date IS NOT NULL AND due_date <> '' AND due_date < CURDATE())
                THEN 1
                ELSE 0
            END
        ) AS partial_count
    FROM accounts_receivable
");
if ($summaryQuery) {
    $summary = $summaryQuery->fetch_assoc();
}

$popupMessage = $_SESSION['success'] ?? $_SESSION['error'] ?? "";
$popupType = isset($_SESSION['success']) ? "success" : (isset($_SESSION['error']) ? "error" : "");
unset($_SESSION['success'], $_SESSION['error']);

function arBadge($status) {
    return match ($status) {
        'Paid' => 'badge paid',
        'Unpaid' => 'badge unpaid',
        'Partially Paid' => 'badge partial',
        'Overdue' => 'badge overdue',
        default => 'badge'
    };
}

function renderReceivableDynamicArea(array $summary, array $rows): void
{
    ?>
    <section class="receivable-summary">
        <div class="summary-card">
            <span class="summary-label">Total Receivables</span>
            <strong class="summary-value"><?php echo (int)($summary['total_receivables'] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">Total Balance Due</span>
            <strong class="summary-value">₱<?php echo number_format((float)($summary['total_balance_due'] ?? 0), 2); ?></strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">Overdue</span>
            <strong class="summary-value"><?php echo (int)($summary['overdue_count'] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">Unpaid</span>
            <strong class="summary-value"><?php echo (int)($summary['unpaid_count'] ?? 0); ?></strong>
        </div>

        <div class="summary-card">
            <span class="summary-label">Partially Paid</span>
            <strong class="summary-value"><?php echo (int)($summary['partial_count'] ?? 0); ?></strong>
        </div>
    </section>

    <section class="receivable-table-card">
        <div class="table-scroll table-scroll-7">
            <table class="receivable-table">
                <thead>
                    <tr>
                        <th>Sales No.</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Amount Paid</th>
                        <th>Balance Due</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <?php $liveStatus = $row['live_status'] ?? $row['status'] ?? 'Unpaid'; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sales_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? ''); ?></td>
                            <td>₱<?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?></td>
                            <td>₱<?php echo number_format((float)($row['amount_paid'] ?? 0), 2); ?></td>
                            <td>₱<?php echo number_format((float)($row['balance_due'] ?? 0), 2); ?></td>
                            <td><?php echo !empty($row['due_date']) ? htmlspecialchars($row['due_date']) : 'N/A'; ?></td>
                            <td>
                                <span class="<?php echo arBadge($liveStatus); ?>">
                                    <?php echo htmlspecialchars($liveStatus); ?>
                                </span>
                            </td>
                            <td>
                                <a href="/NexGen/CODE/PHP/receivable_payment.php?id=<?php echo (int)($row['id'] ?? 0); ?>" class="action-btn">
                                    Update Payment
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">No receivable records found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

if ($isAjax) {
    renderReceivableDynamicArea($summary, $rows);
    exit;
}

$overdueCount = (int)($summary['overdue_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Receivable - NexGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/header.css?v=2">
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/accounts_receivable.css?v=4">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .overdue-alert-wrap {
            position: fixed;
            top: 92px;
            right: 22px;
            z-index: 9999;
            width: min(380px, calc(100vw - 24px));
        }

        .overdue-alert {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: linear-gradient(135deg, #fff4d6, #ffe4a8);
            color: #6b3f00;
            border: 1px solid rgba(210, 146, 0, 0.35);
            border-left: 6px solid #d48b00;
            border-radius: 18px;
            padding: 16px 18px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
            opacity: 0;
            transform: translateY(-12px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .overdue-alert.show {
            opacity: 1;
            transform: translateY(0);
        }

        .overdue-alert-icon {
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(212, 139, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .overdue-alert-content h4 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 800;
        }

        .overdue-alert-content p {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
        }

        .overdue-alert-close {
            margin-left: auto;
            border: none;
            background: transparent;
            color: #6b3f00;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            padding: 2px;
        }

        @media (max-width: 640px) {
            .overdue-alert-wrap {
                top: 84px;
                right: 12px;
                left: 12px;
                width: auto;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<?php if (!empty($popupMessage)): ?>
<div class="popup-overlay" id="popupOverlay">
    <div class="popup-box <?php echo $popupType; ?>" id="popupBox">
        <h3><?php echo $popupType === 'success' ? 'Success' : 'Error'; ?></h3>
        <p><?php echo htmlspecialchars($popupMessage); ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($overdueCount > 0): ?>
<div class="overdue-alert-wrap" id="overdueAlertWrap">
    <div class="overdue-alert" id="overdueAlertBox">
        <div class="overdue-alert-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>

        <div class="overdue-alert-content">
            <h4>Overdue Payment Alert</h4>
            <p>
                You currently have
                <strong><?php echo $overdueCount; ?></strong>
                overdue payment<?php echo $overdueCount > 1 ? 's' : ''; ?> that need<?php echo $overdueCount > 1 ? '' : 's'; ?> attention.
            </p>
        </div>

        <button type="button" class="overdue-alert-close" id="overdueAlertClose">&times;</button>
    </div>
</div>
<?php endif; ?>

<div class="receivable-page">
    <div class="receivable-shell">

        <div class="receivable-breadcrumb">
            <a href="/NexGen/CODE/PHP/dashboard.php">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <section class="receivable-hero">
            <div class="receivable-hero-text">
                <h1>Accounts Receivable</h1>
                <p>Monitor unpaid, partial, and overdue customer balances.</p>
            </div>
        </section>

        <section class="receivable-toolbar-card">
            <form method="GET" class="receivable-toolbar" id="receivableFilterForm">
                <input
                    type="text"
                    name="search"
                    class="toolbar-input"
                    id="receivableSearchInput"
                    placeholder="Search customer or sales no..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

                <select name="status" class="toolbar-select" id="receivableStatusSelect">
                    <option value="">All Status</option>
                    <option value="Unpaid" <?php echo $statusFilter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="Partially Paid" <?php echo $statusFilter === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                    <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Overdue" <?php echo $statusFilter === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>

                <button type="submit" class="toolbar-btn primary-btn">Apply</button>
                <a href="/NexGen/CODE/PHP/accounts_receivable.php" class="toolbar-btn secondary-btn" id="receivableResetBtn">Reset</a>
            </form>
        </section>

        <div id="receivableDynamicArea">
            <?php renderReceivableDynamicArea($summary, $rows); ?>
        </div>

    </div>
</div>

<script src="/NexGen/CODE/JS/header.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const popupOverlay = document.getElementById('popupOverlay');
    const popupBox = document.getElementById('popupBox');
    const overdueAlertWrap = document.getElementById('overdueAlertWrap');
    const overdueAlertBox = document.getElementById('overdueAlertBox');
    const overdueAlertClose = document.getElementById('overdueAlertClose');

    const filterForm = document.getElementById('receivableFilterForm');
    const searchInput = document.getElementById('receivableSearchInput');
    const statusSelect = document.getElementById('receivableStatusSelect');
    const dynamicArea = document.getElementById('receivableDynamicArea');
    const resetBtn = document.getElementById('receivableResetBtn');

    let activeRequest = null;

    if (popupOverlay) {
        setTimeout(() => {
            if (popupBox) {
                popupBox.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                popupBox.style.opacity = '0';
                popupBox.style.transform = 'translateY(-10px)';
            }

            popupOverlay.style.transition = 'opacity 0.4s ease';
            popupOverlay.style.opacity = '0';

            setTimeout(() => {
                popupOverlay.remove();
            }, 450);
        }, 2500);

        popupOverlay.addEventListener('click', function () {
            popupOverlay.remove();
        });
    }

    if (overdueAlertBox) {
        requestAnimationFrame(() => {
            overdueAlertBox.classList.add('show');
        });

        const removeOverdueAlert = () => {
            if (!overdueAlertWrap) return;
            overdueAlertBox.classList.remove('show');
            setTimeout(() => {
                if (overdueAlertWrap.parentNode) {
                    overdueAlertWrap.parentNode.removeChild(overdueAlertWrap);
                }
            }, 300);
        };

        if (overdueAlertClose) {
            overdueAlertClose.addEventListener('click', removeOverdueAlert);
        }

        setTimeout(removeOverdueAlert, 5000);
    }

    async function loadReceivables(pushUrl = true) {
        if (!filterForm || !dynamicArea) return;

        const formData = new FormData(filterForm);
        const params = new URLSearchParams();

        const searchValue = (formData.get('search') || '').toString().trim();
        const statusValue = (formData.get('status') || '').toString().trim();

        if (searchValue !== '') {
            params.set('search', searchValue);
        }

        if (statusValue !== '') {
            params.set('status', statusValue);
        }

        params.set('ajax', '1');

        dynamicArea.style.opacity = '0.55';
        dynamicArea.style.pointerEvents = 'none';

        try {
            if (activeRequest) {
                activeRequest.abort();
            }

            activeRequest = new AbortController();

            const response = await fetch('/NexGen/CODE/PHP/accounts_receivable.php?' + params.toString(), {
                method: 'GET',
                signal: activeRequest.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load receivables.');
            }

            const html = await response.text();
            dynamicArea.innerHTML = html;

            if (pushUrl) {
                const cleanParams = new URLSearchParams();
                if (searchValue !== '') cleanParams.set('search', searchValue);
                if (statusValue !== '') cleanParams.set('status', statusValue);

                const newUrl = cleanParams.toString()
                    ? '/NexGen/CODE/PHP/accounts_receivable.php?' + cleanParams.toString()
                    : '/NexGen/CODE/PHP/accounts_receivable.php';

                window.history.replaceState({}, '', newUrl);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
        } finally {
            dynamicArea.style.opacity = '1';
            dynamicArea.style.pointerEvents = 'auto';
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            loadReceivables(true);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            loadReceivables(true);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadReceivables(true);
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function (event) {
            event.preventDefault();

            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = '';

            loadReceivables(true);
        });
    }
});
</script>
</body>
</html>