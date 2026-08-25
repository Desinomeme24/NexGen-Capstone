<?php
/* Shared Accounts Receivable (AR) enablement helper.
   AR is a per-account flag (users.can_accounts_receivable). It is never removed,
   only hidden/disabled in the UI and skipped in validation when off. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!function_exists('nxArEnabled')) {
    function nxArEnabled(): bool
    {
        return (int)($_SESSION['can_accounts_receivable'] ?? 0) === 1;
    }
}
?>
