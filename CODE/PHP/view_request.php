<?php
session_start();
require_once("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: /NexGen/CODE/PHP/dashboard.php");
    exit();
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: pending_requests.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    $_SESSION['flash'] = ['type' => 'notice-error', 'message' => 'Registration request not found.'];
    header("Location: pending_requests.php");
    exit();
}

$employeeMatched = false;
$employeeData = null;

$stmt = $conn->prepare("SELECT * FROM accounts_masterlist WHERE employee_no = ? LIMIT 1");
$stmt->bind_param("s", $request['employee_no']);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $employeeMatched = true;
    $employeeData = $res->fetch_assoc();
}
$stmt->close();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$filePath = $request['valid_id_path'] ?? '';
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
$isPdf = ($ext === 'pdf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - NextGen</title>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">
    <style>
        .custom-confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: 0.25s ease;
            z-index: 99999;
            padding: 20px;
        }

        .custom-confirm-overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .custom-confirm-box {
            width: 100%;
            max-width: 380px;
            background: linear-gradient(180deg, #1f3c88 0%, #1a3578 100%);
            border-radius: 20px;
            padding: 26px 22px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.30);
            text-align: center;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.10);
            transform: translateY(15px) scale(0.96);
            transition: 0.25s ease;
        }

        .custom-confirm-overlay.show .custom-confirm-box {
            transform: translateY(0) scale(1);
        }

        .custom-confirm-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #f7d98b;
        }

        .custom-confirm-box h3 {
            margin: 0 0 8px;
            font-size: 25px;
            font-weight: 800;
            color: #fff;
        }

        .custom-confirm-box p {
            margin: 0 0 22px;
            font-size: 15px;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.5;
        }

        .custom-confirm-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .custom-btn-cancel,
        .custom-btn-confirm {
            border: none;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            min-width: 120px;
            transition: 0.2s ease;
        }

        .custom-btn-cancel {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        .custom-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        .custom-btn-confirm {
            background: #f7d98b;
            color: #17306b;
        }

        .custom-btn-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
        }
    </style>
</head>
<body>
<div class="admin-shell">
         <?php include 'admin_sidebar.php'; ?>

    <main class="admin-content">
        <div class="topbar">
            <div class="page-title">
                <h1>Request #<?php echo (int)$request['id']; ?></h1>
                <p>Review the registration details and uploaded ID</p>
            </div>
            <div class="user-pill">
                <img src="/NexGen/CODE/PHP/<?php echo e($profileImage); ?>" alt="Profile">
                <span><?php echo e($_SESSION['full_name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="notice <?php echo e($flash['type']); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2>Applicant Information</h2>
                <a class="btn btn-silver" href="pending_requests.php">Back</a>
            </div>
            <div class="panel-body">
                <div class="kv-grid">
                    <div class="kv-item"><label>Employee Number</label><div class="value"><?php echo e($request['employee_no']); ?></div></div>
                    <div class="kv-item"><label>Full Name</label><div class="value"><?php echo e($request['full_name']); ?></div></div>
                    <div class="kv-item"><label>Username</label><div class="value"><?php echo e($request['username']); ?></div></div>
                    <div class="kv-item"><label>Email</label><div class="value"><?php echo e($request['email']); ?></div></div>
                    <div class="kv-item"><label>Phone</label><div class="value"><?php echo e($request['phone']); ?></div></div>
                    <div class="kv-item"><label>Address</label><div class="value"><?php echo e($request['address']); ?></div></div>
                    <div class="kv-item"><label>Requested Role</label><div class="value"><?php echo e(ucwords(str_replace('_', ' ', $request['requested_role']))); ?></div></div>
                    <div class="kv-item"><label>Status</label><div class="value"><span class="badge badge-<?php echo e($request['request_status']); ?>"><?php echo e(ucfirst($request['request_status'])); ?></span></div></div>
                    <div class="kv-item"><label>Submitted At</label><div class="value"><?php echo e(date('M d, Y h:i A', strtotime($request['created_at']))); ?></div></div>
                    <div class="kv-item"><label>Masterlist Match</label><div class="value"><?php echo $employeeMatched ? 'Matched' : 'Not Found'; ?></div></div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Employee Masterlist Check</h2>
            </div>
            <div class="panel-body">
                <?php if ($employeeMatched && $employeeData): ?>
                    <div class="kv-grid">
                        <div class="kv-item"><label>Masterlist Full Name</label><div class="value"><?php echo e($employeeData['full_name']); ?></div></div>
                        <div class="kv-item"><label>Department</label><div class="value"><?php echo e($employeeData['department']); ?></div></div>
                        <div class="kv-item"><label>Position</label><div class="value"><?php echo e($employeeData['position']); ?></div></div>
                        <div class="kv-item"><label>Employment Status</label><div class="value"><?php echo e(ucfirst($employeeData['employment_status'])); ?></div></div>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        This employee number was not found in the employee masterlist. Manual admin approval is still allowed based on the submitted valid ID and applicant details.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Uploaded Valid ID</h2>
            </div>
            <div class="panel-body">
                <?php if (!empty($filePath)): ?>
                    <div class="request-id-preview">
                        <?php if ($isImage): ?>
                            <img src="/NexGen/CODE/PHP/<?php echo e($filePath); ?>" alt="Valid ID">
                        <?php elseif ($isPdf): ?>
                            <iframe src="/NexGen/CODE/PHP/<?php echo e($filePath); ?>"></iframe>
                        <?php else: ?>
                            <p><a class="link-white" target="_blank" href="/NexGen/CODE/PHP/<?php echo e($filePath); ?>">Open uploaded ID file</a></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error">No uploaded ID file found.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Admin Actions</h2>
            </div>
            <div class="panel-body">
                <div class="form-grid">
                    <form action="approve_request.php" method="POST" class="kv-item request-action-form" data-confirm-message="Approve this registration request?">
                        <label>Approval Remarks</label>
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                        <textarea name="remarks" class="textarea" placeholder="Optional remarks for approval"></textarea>
                        <div class="form-actions">
                            <button class="btn btn-success" type="submit">Approve Request</button>
                        </div>
                    </form>

                    <form action="reject_request.php" method="POST" class="kv-item request-action-form" data-confirm-message="Reject this registration request?">
                        <label>Rejection Reason</label>
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                        <textarea name="remarks" class="textarea" placeholder="State why this request is rejected" required></textarea>
                        <div class="form-actions">
                            <button class="btn btn-danger" type="submit">Reject Request</button>
                        </div>
                    </form>
                </div>

                <div style="margin-top:16px;">
                    <form action="resubmit_request.php" method="POST" class="kv-item request-action-form" data-confirm-message="Mark this request for resubmission?">
                        <label>Correction / Resubmission Instructions</label>
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                        <textarea name="remarks" class="textarea" placeholder="Tell the applicant what needs to be corrected" required></textarea>
                        <div class="form-actions">
                            <button class="btn btn-warning" type="submit">Send for Resubmission</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>

<div class="custom-confirm-overlay" id="customConfirmOverlay">
    <div class="custom-confirm-box">
        <div class="custom-confirm-icon">?</div>
        <h3>Confirmation</h3>
        <p id="customConfirmMessage">Are you sure?</p>
        <div class="custom-confirm-actions">
            <button type="button" class="custom-btn-cancel" id="customConfirmCancel">Cancel</button>
            <button type="button" class="custom-btn-confirm" id="customConfirmOk">Confirm</button>
        </div>
    </div>
</div>

<script src="/NexGen/CODE/JS/admin_module.js"></script>
<script>
function showCustomConfirm(message, onConfirm) {
    const overlay = document.getElementById('customConfirmOverlay');
    const messageBox = document.getElementById('customConfirmMessage');
    const okBtn = document.getElementById('customConfirmOk');
    const cancelBtn = document.getElementById('customConfirmCancel');

    if (!overlay || !messageBox || !okBtn || !cancelBtn) return;

    messageBox.textContent = message;
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';

    const close = function() {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        okBtn.onclick = null;
        cancelBtn.onclick = null;
    };

    cancelBtn.onclick = close;
    okBtn.onclick = function() {
        close();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.request-action-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
            showCustomConfirm(message, function() {
                form.submit();
            });
        });
    });
});

document.addEventListener('click', function(e) {
    const confirmModal = document.getElementById('customConfirmOverlay');
    if (e.target === confirmModal) {
        confirmModal.classList.remove('show');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const confirmModal = document.getElementById('customConfirmOverlay');
        if (confirmModal) {
            confirmModal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
});
</script>
</body>
</html>