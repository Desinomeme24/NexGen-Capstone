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

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$profileImage = !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'uploads/default.png';
$filePath = $request['valid_id_path'] ?? '';
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
$isPdf = ($ext === 'pdf');

// Request workflow rules:
// pending  -> approve / reject / resubmit
// resubmit -> approve / reject
// approved/rejected are final and cannot be changed.
$currentStatus = strtolower((string)($request['request_status'] ?? 'pending'));
$isFinalized = in_array($currentStatus, ['approved', 'rejected'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - NextGen</title>
    <script>
        /* THEME: apply saved preference before first paint to avoid a flash */
        (function () {
            try {
                var saved = localStorage.getItem('nexgen-theme');
                if (saved === 'light' || saved === 'dark') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="/NexGen/CODE/STYLE/admin_module.css">
        
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.16) 0%, rgba(5, 11, 36, 0.96) 100%);
            border-radius: 20px;
            padding: 26px 22px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.30);
            text-align: center;
            color: var(--text);
            border: 1px solid rgba(125, 211, 252, 0.28);
            transform: translateY(15px) scale(0.96);
            transition: 0.25s ease;
        }

        html[data-theme="light"] .custom-confirm-box {
            background: linear-gradient(180deg, #ffffff 0%, #eaf4ff 100%);
            border-color: rgba(59, 130, 246, 0.25);
        }

        .custom-confirm-overlay.show .custom-confirm-box {
            transform: translateY(0) scale(1);
        }

        .custom-confirm-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: var(--gold-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--gold);
        }

        .custom-confirm-box h3 {
            margin: 0 0 8px;
            font-size: 25px;
            font-weight: 800;
            color: var(--text);
        }

        .custom-confirm-box p {
            margin: 0 0 22px;
            font-size: 15px;
            color: var(--muted);
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
            background: var(--card);
            color: var(--text);
        }

        .custom-btn-cancel:hover {
            background: var(--card-hover);
        }

        .custom-btn-confirm {
            background: #f7d98b;
            color: #17306b;
        }

        .custom-btn-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(247, 217, 139, 0.25);
        }

        /* =========================
           REQUEST REVIEW LAYOUT
        ========================= */
        .request-layout {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 18px;
            align-items: start;
        }

        .request-main-col,
        .request-side-col {
            min-width: 0;
        }

        .request-side-col {
            position: sticky;
            top: 18px;
        }

        .section-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 18px 0 12px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        .section-divider span {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            white-space: nowrap;
        }

        /* Compact spec-sheet style for applicant/masterlist details */
        .kv-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(170px, 1fr));
            gap: 0 22px;
        }

        .kv-item {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 9px 0;
            border-bottom: 1px solid var(--line);
        }

        .kv-item label {
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--muted);
            margin-bottom: 3px;
            font-weight: 700;
        }

        .kv-item .value {
            font-size: 13.5px;
            font-weight: 600;
        }

        .id-preview-frame {
            display: block;
            border-radius: 14px;
            overflow: hidden;
            line-height: 0;
        }

        .id-preview-frame img {
            cursor: zoom-in;
        }

        .request-id-preview img,
        .request-id-preview iframe {
            max-height: 62vh;
        }

        .admin-actions-extra {
            margin-top: 14px;
        }

        /* =========================
           ATTACHMENT CHIP (Valid ID)
        ========================= */
        .attachment-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 9px 14px;
            text-decoration: none;
            transition: 0.2s ease;
            max-width: 100%;
        }

        .attachment-chip:hover {
            background: var(--card-hover);
            transform: translateY(-1px);
        }

        .attachment-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 9px;
            background: var(--gold-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .attachment-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .attachment-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        .attachment-sub {
            font-size: 11px;
            color: var(--muted);
        }

        .attachment-action {
            margin-left: 6px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gold-dim);
            white-space: nowrap;
        }

        /* =========================
           ADMIN ACTIONS — COMPACT MODERN CARD
        ========================= */
        .admin-actions-panel .panel-header {
            padding-bottom: 8px;
        }

        .admin-actions-panel .panel-header h2 {
            font-size: 15px;
        }

        .admin-actions-panel .panel-body {
            padding-top: 6px;
        }

        .modern-action-form {
            max-width: 360px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .modern-field label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .modern-select-shell {
            position: relative;
        }

        .modern-select-shell::after {
            content: "";
            position: absolute;
            right: 12px;
            top: 50%;
            width: 7px;
            height: 7px;
            border-right: 2px solid var(--muted);
            border-bottom: 2px solid var(--muted);
            transform: translateY(-65%) rotate(45deg);
            pointer-events: none;
        }

        .modern-select-shell select {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 30px 8px 11px;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .modern-select-shell select:hover {
            background: var(--card-hover);
        }

        .modern-select-shell select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-soft);
        }

        .modern-select-shell select option {
            background: var(--panel-2);
            color: var(--text);
        }

        #remarksWrapper {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            margin: 0;
            transition: max-height 0.25s ease, opacity 0.2s ease, margin 0.25s ease;
        }

        #remarksWrapper.is-visible {
            max-height: 140px;
            opacity: 1;
        }

        .modern-textarea {
            width: 100%;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-size: 12.5px;
            font-family: inherit;
            padding: 9px 11px;
            resize: vertical;
            min-height: 56px;
            transition: 0.18s ease;
        }

        .modern-textarea::placeholder {
            color: var(--faint);
        }

        .modern-textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-soft);
        }

        .modern-submit-btn {
            align-self: flex-start;
            border: none;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 12.5px;
            font-weight: 700;
            color: #17306b;
            background: var(--gold);
            cursor: pointer;
            transition: 0.18s ease;
        }

        .modern-submit-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(247, 217, 139, 0.25);
        }

        .modern-submit-btn:disabled {
            background: var(--card);
            color: var(--faint);
            cursor: not-allowed;
        }

        .modern-submit-btn.is-success { background: #7ce0a8; }
        .modern-submit-btn.is-danger  { background: #f28b8b; }
        .modern-submit-btn.is-warning { background: #f7c873; }

        @media (max-width: 1080px) {
            .request-layout {
                grid-template-columns: 1fr;
            }

            .request-side-col {
                position: static;
                top: auto;
            }
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
                    <div class="kv-item"><label>Full Name</label><div class="value"><?php echo e($request['full_name']); ?></div></div>
                    <div class="kv-item"><label>Username</label><div class="value"><?php echo e($request['username']); ?></div></div>
                    <div class="kv-item"><label>Email</label><div class="value"><?php echo e($request['email']); ?></div></div>
                    <div class="kv-item"><label>Phone</label><div class="value"><?php echo e($request['phone']); ?></div></div>
                    <div class="kv-item"><label>Address</label><div class="value"><?php echo e($request['address']); ?></div></div>
                    <div class="kv-item"><label>Requested Role</label><div class="value"><?php echo e(ucwords(str_replace('_', ' ', $request['requested_role']))); ?></div></div>
                    <div class="kv-item"><label>Status</label><div class="value"><span class="badge badge-<?php echo e($request['request_status']); ?>"><?php echo e(ucfirst($request['request_status'])); ?></span></div></div>
                    <div class="kv-item"><label>Submitted At</label><div class="value"><?php echo e(date('M d, Y h:i A', strtotime($request['created_at']))); ?></div></div>
                    <div class="kv-item" style="grid-column: 1 / -1;">
                        <label>Valid ID</label>
                        <div class="value">
                            <?php if (!empty($filePath)): ?>
                                <a class="attachment-chip" href="/NexGen/CODE/PHP/<?php echo e($filePath); ?>" target="_blank" rel="noopener" title="Open uploaded valid ID">
                                    <span class="attachment-icon"><?php echo $isImage ? '🖼️' : ($isPdf ? '📄' : '📎'); ?></span>
                                    <span class="attachment-meta">
                                        <span class="attachment-name">Valid_ID.<?php echo e($ext ?: 'file'); ?></span>
                                        <span class="attachment-sub"><?php echo $isImage ? 'Image file' : ($isPdf ? 'PDF document' : 'Uploaded file'); ?></span>
                                    </span>
                                    <span class="attachment-action">View &rarr;</span>
                                </a>
                            <?php else: ?>
                                <span class="notice notice-error" style="display:inline-block;">No uploaded ID file found.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Business Information</h2>
            </div>
            <div class="panel-body">
                <div class="kv-grid">
                    <div class="kv-item"><label>Business Name</label><div class="value"><?php echo e($request['business_name'] ?? ''); ?></div></div>
                    <div class="kv-item"><label>Business Type</label><div class="value"><?php echo e($request['business_type'] ?? ''); ?></div></div>
                    <div class="kv-item"><label>Business Address</label><div class="value"><?php echo e($request['business_address'] ?? ''); ?></div></div>
                    <div class="kv-item">
                        <label>SME Business Code</label>
                        <div class="value"><?php echo !empty($request['business_code']) ? e($request['business_code']) : '<em>Not yet assigned (generated on approval)</em>'; ?></div>
                    </div>
                    <?php if ($request['requested_role'] === 'employee'): ?>
                        <div class="kv-item"><label>Employee Number</label><div class="value"><?php echo e($request['employee_no'] ?? ''); ?></div></div>
                        <div class="kv-item"><label>Joining Business ID</label><div class="value"><?php echo $request['business_id'] ? '#' . (int)$request['business_id'] : '<em>Unresolved</em>'; ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="panel admin-actions-panel">
            <div class="panel-header">
                <h2>Admin Actions</h2>
            </div>
            <div class="panel-body">
                <?php if ($isFinalized): ?>
                    <div class="notice <?php echo $currentStatus === 'approved' ? 'notice-success' : 'notice-error'; ?>">
                        This request has already been <strong><?php echo e($currentStatus); ?></strong> and is final. No further status changes are allowed.
                    </div>
                <?php else: ?>
                    <form id="adminActionForm" method="POST" class="request-action-form modern-action-form" data-confirm-message="Are you sure you want to continue?">
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">

                        <div class="modern-field">
                            <label>Select Action</label>
                            <div class="modern-select-shell">
                                <select name="action_type" id="actionSelect" required>
                                    <option value="" disabled selected>Choose an action</option>
                                    <option value="approve">Approve Request</option>
                                    <option value="reject">Reject Request</option>
                                    <?php if ($currentStatus === 'pending'): ?>
                                        <option value="resubmit">Send for Resubmission</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="modern-field" id="remarksWrapper">
                            <label id="remarksLabel">Remarks</label>
                            <textarea name="remarks" id="remarksInput" class="modern-textarea" placeholder="Add remarks" rows="2"></textarea>
                        </div>

                        <div class="form-actions">
                            <button class="modern-submit-btn" id="submitActionBtn" type="submit" disabled>Submit</button>
                        </div>
                    </form>
                <?php endif; ?>
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

const ADMIN_ACTION_CONFIG = {
    approve: {
        endpoint: 'approve_request.php',
        remarksLabel: 'Approval Remarks',
        remarksPlaceholder: 'Optional remarks for approval',
        remarksRequired: false,
        confirmMessage: 'Approve this registration request?',
        buttonText: 'Approve Request',
        buttonClass: 'is-success'
    },
    reject: {
        endpoint: 'reject_request.php',
        remarksLabel: 'Rejection Reason',
        remarksPlaceholder: 'State why this request is rejected',
        remarksRequired: true,
        confirmMessage: 'Reject this registration request?',
        buttonText: 'Reject Request',
        buttonClass: 'is-danger'
    },
    resubmit: {
        endpoint: 'resubmit_request.php',
        remarksLabel: 'Correction / Resubmission Instructions',
        remarksPlaceholder: 'Tell the applicant what needs to be corrected',
        remarksRequired: true,
        confirmMessage: 'Mark this request for resubmission?',
        buttonText: 'Send for Resubmission',
        buttonClass: 'is-warning'
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const actionSelect = document.getElementById('actionSelect');
    const adminActionForm = document.getElementById('adminActionForm');
    const remarksWrapper = document.getElementById('remarksWrapper');
    const remarksLabel = document.getElementById('remarksLabel');
    const remarksInput = document.getElementById('remarksInput');
    const submitBtn = document.getElementById('submitActionBtn');

    if (actionSelect && adminActionForm && remarksWrapper && remarksLabel && remarksInput && submitBtn) {
        actionSelect.addEventListener('change', function() {
            const config = ADMIN_ACTION_CONFIG[actionSelect.value];

            if (!config) {
                remarksWrapper.classList.remove('is-visible');
                submitBtn.disabled = true;
                return;
            }

            adminActionForm.setAttribute('action', config.endpoint);
            adminActionForm.setAttribute('data-confirm-message', config.confirmMessage);

            remarksWrapper.classList.add('is-visible');
            remarksLabel.textContent = config.remarksLabel;
            remarksInput.placeholder = config.remarksPlaceholder;
            remarksInput.required = config.remarksRequired;

            submitBtn.textContent = config.buttonText;
            submitBtn.classList.remove('is-success', 'is-danger', 'is-warning');
            submitBtn.classList.add(config.buttonClass);
            submitBtn.disabled = false;
        });
    }

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