<?php
/* Shared business/tenant isolation helpers. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!function_exists('nxRequireBusinessId')) {
    function nxRequireBusinessId(mysqli $conn): int
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /NexGen/CODE/PHP/index.php'); exit();
        }
        $role = (string)($_SESSION['role'] ?? '');
        if ($role === 'system_admin') { return 0; }
        $businessId = (int)($_SESSION['business_id'] ?? 0);
        if ($businessId <= 0) {
            $uid=(int)$_SESSION['user_id'];
            $st=$conn->prepare('SELECT business_id FROM users WHERE id=? LIMIT 1');
            if ($st) { $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close(); $businessId=(int)($r['business_id']??0); }
            if ($businessId>0) $_SESSION['business_id']=$businessId;
        }
        if ($businessId<=0) {
            $_SESSION['error']='Your account is not connected to an SME business. Please contact the administrator.';
            header('Location: /NexGen/CODE/PHP/dashboard.php'); exit();
        }
        return $businessId;
    }
}
?>