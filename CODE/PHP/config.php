<?php
/* =========================================================================
   NEXTGEN CONFIG / SECURITY / HELPERS
   ========================================================================= */

/* SESSION SECURITY: set ini before starting session */
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '600');
    }
    session_start();
}

/* SECURITY: prevent the browser from caching authenticated pages. Without
   this, hitting Back after logging out (or switching accounts on a shared
   till/PC) can redisplay a previous user's cached page - including data
   like Purchase Order History or receivables - straight from disk/memory
   cache instead of re-requesting it from the server. */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/* DATABASE CONNECTION */
$host = "localhost";
$dbname = "nexgen_db";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');

/* SESSION SECURITY: timeout constant */
if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 600); // 10 minutes
}

/* =========================================================================
   COMMON HELPERS
   ========================================================================= */
if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatQty')) {
    /* Formats a decimal(12,3) quantity for display: trims trailing zeros so a
       whole number like 10.000 shows as "10" while a fractional quantity like
       2.500 shows as "2.5" - keeps piece-counted products (groceries, school
       supplies) looking like plain integers without needing per-SME-type
       display logic. */
    function formatQty($value): string
    {
        $formatted = number_format((float)$value, 3, '.', '');
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');
        return $formatted === '' ? '0' : $formatted;
    }
}

/* =========================================================================
   ADAPTIVE SME-TYPE / BATCH INVENTORY HELPERS
   ========================================================================= */
if (!function_exists('nxIsBatchTrackedType')) {
    /* Which SME types manage stock as per-batch/per-expiry lots (product_batches)
       instead of a single flat stock_quantity number. */
    function nxIsBatchTrackedType(?string $businessType): bool
    {
        return in_array($businessType, ['Mini Grocery / Sari-Sari Store', 'Pharmacy / Drugstore'], true);
    }
}

if (!function_exists('nxBlocksExpiredBatches')) {
    /* Pharmacy/Drugstore may never sell or count expired batches as available
       stock. Mini Grocery still allows it - an owner is expected to manually
       pull expired stock rather than have the system block the sale outright. */
    function nxBlocksExpiredBatches(?string $businessType): bool
    {
        return $businessType === 'Pharmacy / Drugstore';
    }
}

if (!function_exists('nxGenerateBatchNumber')) {
    /* Batch numbers are system-generated, not typed by users - avoids typos/
       duplicates and matches the same auto-numbering approach already used
       for sales_no. Timestamp + a short random suffix keeps it unique even
       if two batches are created for the same product in the same second. */
    function nxGenerateBatchNumber(): string
    {
        return 'B-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
    }
}

if (!function_exists('nxDeductFefo')) {
    /**
     * FEFO (first-expiry-first-out) stock deduction for a batch-tracked product.
     * Consumes the soonest-expiring batch(es) first. Depleted batches are removed;
     * products.stock_quantity is kept in sync automatically by the
     * trg_product_batches_after_update/delete triggers. Must run inside the
     * caller's transaction - throws on failure so the caller's rollback applies.
     */
    function nxDeductFefo(mysqli $conn, int $businessId, int $productId, float $quantity, bool $blockExpired): void
    {
        // status = 'active' excludes batches already dropped as expired/disposed -
        // those sit at quantity 0 and must never be touched again (deleting a
        // depleted-looking row here would erase the drop's history).
        // Batches with no expiry date sort last (MySQL puts NULL first in ASC
        // order by default, which is the opposite of "deducted last").
        // A NULL expiry means "never expires" - it must stay sellable even
        // when blocking expired stock, so it's never excluded by this clause.
        $expiryClause = $blockExpired ? "AND (expiry_date IS NULL OR expiry_date >= CURDATE())" : "";
        $batchStmt = $conn->prepare("
            SELECT id, quantity
            FROM product_batches
            WHERE product_id = ? AND business_id = ? AND status = 'active' {$expiryClause}
            ORDER BY (expiry_date IS NULL), expiry_date ASC, id ASC
            FOR UPDATE
        ");
        if (!$batchStmt) {
            throw new Exception('Failed to prepare batch lock query.');
        }
        $batchStmt->bind_param("ii", $productId, $businessId);
        $batchStmt->execute();
        $batches = $batchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $batchStmt->close();

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0.0005) {
                break;
            }

            $batchQty = (float) $batch['quantity'];
            $take = min($batchQty, $remaining);
            $newQty = $batchQty - $take;

            if ($newQty <= 0.0005) {
                $del = $conn->prepare("DELETE FROM product_batches WHERE id = ?");
                if (!$del) {
                    throw new Exception('Failed to prepare batch delete query.');
                }
                $del->bind_param("i", $batch['id']);
                if (!$del->execute()) {
                    throw new Exception('Failed to consume depleted batch.');
                }
                $del->close();
            } else {
                $upd = $conn->prepare("UPDATE product_batches SET quantity = ? WHERE id = ?");
                if (!$upd) {
                    throw new Exception('Failed to prepare batch update query.');
                }
                $upd->bind_param("di", $newQty, $batch['id']);
                if (!$upd->execute()) {
                    throw new Exception('Failed to deduct from batch.');
                }
                $upd->close();
            }

            $remaining -= $take;
        }

        if ($remaining > 0.0005) {
            throw new Exception($blockExpired
                ? 'This product is expired and cannot be sold. Please remove it or check for a newer batch.'
                : 'Not enough stock available for this product.');
        }
    }
}

if (!function_exists('isStrongPassword')) {
    function isStrongPassword(string $password): bool
    {
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
    }
}

if (!function_exists('nxMaskUsername')) {
    /* Masks a username for display when a forgot-password lookup matches more
       than one account under the same email (e.g. an SME owner's branch
       accounts) - shows enough to be recognizable without exposing the full
       username to anyone who isn't already looking at their own inbox. */
    function nxMaskUsername(string $username): string
    {
        $len = strlen($username);

        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        if ($len <= 4) {
            return substr($username, 0, 1) . str_repeat('*', $len - 1);
        }

        return substr($username, 0, 2) . str_repeat('*', $len - 3) . substr($username, -1);
    }
}

if (!function_exists('nxMaskEmail')) {
    /* Masks an email for display after an OTP is sent (e.g. "jo***@gmail.com")
       - lets the user confirm where the code went without echoing the full
       address back on screen. */
    function nxMaskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || $parts[1] === '') {
            return $email;
        }

        [$local, $domain] = $parts;
        $len = strlen($local);

        $maskedLocal = $len <= 2
            ? str_repeat('*', $len)
            : substr($local, 0, 2) . str_repeat('*', max(1, $len - 2));

        return $maskedLocal . '@' . $domain;
    }
}

if (!function_exists('isUserLocked')) {
    function isUserLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time();
    }
}

/* =========================================================================
   CAPTCHA: IMAGE CAPTCHA FUNCTIONS
   ========================================================================= */
if (!function_exists('generateImageCaptcha')) {
    function generateImageCaptcha(string $formKey): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $baseDir = __DIR__ . '/../../IMAGES/captcha';
        $webBase = '/NexGen/IMAGES/captcha';

        if (!is_dir($baseDir)) {
            return [
                'success' => false,
                'message' => 'Captcha image folder not found.',
                'target_label' => '',
                'items' => []
            ];
        }

        $categoryDirs = array_filter(glob($baseDir . '/*'), 'is_dir');
        $categories = [];

        foreach ($categoryDirs as $dir) {
            $category = basename($dir);
            $images = glob($dir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);

            if (!empty($images)) {
                $categories[$category] = array_values($images);
            }
        }

        if (count($categories) < 2) {
            return [
                'success' => false,
                'message' => 'Captcha needs at least 2 category folders with images.',
                'target_label' => '',
                'items' => []
            ];
        }

        $targetCategory = array_rand($categories);
        $targetImages = $categories[$targetCategory];

        if (count($targetImages) < 3) {
            return [
                'success' => false,
                'message' => 'Captcha target category needs at least 3 images.',
                'target_label' => '',
                'items' => []
            ];
        }

        shuffle($targetImages);

        /*
        |--------------------------------------------------------------------------
        | LIMIT TARGET IMAGES SHOWN TO 3 TO 5 ONLY
        |--------------------------------------------------------------------------
        */
        $maxCorrect = min(5, count($targetImages));
        $correctCount = random_int(3, $maxCorrect);
        $correctImages = array_slice($targetImages, 0, $correctCount);

        $otherImages = [];
        foreach ($categories as $categoryName => $images) {
            if ($categoryName === $targetCategory) {
                continue;
            }

            foreach ($images as $img) {
                $otherImages[] = [
                    'category' => $categoryName,
                    'path' => $img
                ];
            }
        }

        $distractorCount = 9 - count($correctImages);

        if (count($otherImages) < $distractorCount) {
            return [
                'success' => false,
                'message' => 'Captcha does not have enough distractor images.',
                'target_label' => '',
                'items' => []
            ];
        }

        shuffle($otherImages);
        $distractors = array_slice($otherImages, 0, $distractorCount);

        $items = [];

        foreach ($correctImages as $imgPath) {
            $items[] = [
                'id' => sha1($imgPath),
                'category' => $targetCategory,
                'relative_path' => $webBase . '/' . $targetCategory . '/' . basename($imgPath),
                'is_correct' => true
            ];
        }

        foreach ($distractors as $item) {
            $items[] = [
                'id' => sha1($item['path']),
                'category' => $item['category'],
                'relative_path' => $webBase . '/' . $item['category'] . '/' . basename($item['path']),
                'is_correct' => false
            ];
        }

        shuffle($items);

        if (empty($_SESSION['image_captcha']) || !is_array($_SESSION['image_captcha'])) {
            $_SESSION['image_captcha'] = [];
        }

        $_SESSION['image_captcha'][$formKey] = [
            'target' => $targetCategory,
            'correct_ids' => array_values(array_map(
                fn($item) => $item['id'],
                array_filter($items, fn($item) => $item['is_correct'] === true)
            ))
        ];

        return [
            'success' => true,
            'message' => '',
            'target_label' => ucwords(str_replace('_', ' ', $targetCategory)),
            'items' => $items
        ];
    }
}

if (!function_exists('validateImageCaptchaSelection')) {
    function validateImageCaptchaSelection(string $formKey, array $selectedIds): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (
            empty($_SESSION['image_captcha']) ||
            !isset($_SESSION['image_captcha'][$formKey]['correct_ids'])
        ) {
            return false;
        }

        $expected = $_SESSION['image_captcha'][$formKey]['correct_ids'];
        unset($_SESSION['image_captcha'][$formKey]);

        $selectedIds = array_values(array_unique(array_map('strval', $selectedIds)));
        sort($selectedIds);
        sort($expected);

        return $selectedIds === $expected;
    }
}

/* =========================================================================
   CSRF
   ========================================================================= */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(string $formName): string
    {
        if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        // Reuse the existing token for this form if one is already pending.
        // Pages like inventory_management.php re-run this on every AJAX
        // filter/tab refresh (not just full loads) to keep their static
        // modals' hidden tokens working - minting a brand-new token every
        // time silently invalidated any modal that was already open,
        // showing "session expired" even though the session was fine.
        if (!empty($_SESSION['csrf_tokens'][$formName])) {
            return $_SESSION['csrf_tokens'][$formName];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$formName] = $token;
        return $token;
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(string $formName, ?string $token): bool
    {
        if (
            empty($_SESSION['csrf_tokens']) ||
            !isset($_SESSION['csrf_tokens'][$formName]) ||
            empty($token)
        ) {
            return false;
        }

        $isValid = hash_equals($_SESSION['csrf_tokens'][$formName], $token);
        unset($_SESSION['csrf_tokens'][$formName]);
        return $isValid;
    }
}

/* =========================================================================
   SESSION TIMEOUT / RBAC
   ========================================================================= */
if (!function_exists('enforceSessionTimeout')) {
    function enforceSessionTimeout(int $timeoutSeconds = SESSION_TIMEOUT_SECONDS): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $now = time();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

        if (($now - $lastActivity) > $timeoutSeconds) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
            session_start();
            $_SESSION['error'] = 'Your session has expired due to inactivity. Please log in again.';
            header("Location: /NexGen/CODE/PHP/index.php");
            exit();
        }

        $_SESSION['last_activity'] = $now;
    }
}

if (!function_exists('requireRole')) {
    function requireRole(array $roles): void
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header("Location: /NexGen/CODE/PHP/index.php");
            exit();
        }

        enforceSessionTimeout();

        if (!in_array($_SESSION['role'], $roles, true)) {
            $_SESSION['error'] = 'Unauthorized access.';
            header("Location: /NexGen/CODE/PHP/dashboard.php");
            exit();
        }
    }
}

/* =========================================================================
   FILE VALIDATION / ANTIVIRUS-STYLE SCANNING
   ========================================================================= */
if (!function_exists('validateUploadedFile')) {
    function validateUploadedFile(array $file, array $allowedExtensions, int $maxSizeBytes, bool $mustBeImage = false): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'Upload failed.'];
        }

        if (($file['size'] ?? 0) > $maxSizeBytes) {
            return [false, 'File exceeds the allowed size limit.'];
        }

        $originalName = (string)($file['name'] ?? '');
        $tmpName = (string)($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return [false, 'File type is not allowed.'];
        }

        if ($mustBeImage) {
            $imageInfo = @getimagesize($tmpName);
            if ($imageInfo === false) {
                return [false, 'Uploaded file is not a valid image.'];
            }
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return [true, $mime];
    }
}

if (!function_exists('nxIsSuspiciousFilename')) {
    function nxIsSuspiciousFilename(string $filename): bool
    {
        $filename = strtolower(trim($filename));

        if ($filename === '') {
            return true;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            return true;
        }

        if (preg_match('/\.(php|phtml|php3|php4|php5|phar|exe|com|bat|cmd|sh|js|jsp|asp|aspx|cgi|pl|py|rb)(\..+)?$/i', $filename)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('nxFileStartsWithPdfSignature')) {
    function nxFileStartsWithPdfSignature(string $tmpPath): bool
    {
        $handle = @fopen($tmpPath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }
}

if (!function_exists('nxScanFileForDangerousPatterns')) {
    function nxScanFileForDangerousPatterns(string $tmpPath): bool
    {
        $content = @file_get_contents($tmpPath, false, null, 0, 4096);
        if ($content === false) {
            return false;
        }

        $patterns = [
            '/<\?php/i',
            '/<script\b/i',
            '/<html\b/i',
            '/javascript:/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/cmd\.exe/i',
            '/powershell/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('nxValidateSecureUpload')) {
    function nxValidateSecureUpload(array $file, array $options = []): array
    {
        $allowedExtensions = $options['allowed_extensions'] ?? [];
        $allowedMimeTypes = $options['allowed_mime_types'] ?? [];
        $maxSizeBytes = (int)($options['max_size'] ?? (5 * 1024 * 1024));
        $requireImage = !empty($options['require_image']);
        $allowPdf = !empty($options['allow_pdf']);

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'Upload failed.'];
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [false, 'Invalid uploaded file.'];
        }

        $originalName = (string)($file['name'] ?? '');
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (nxIsSuspiciousFilename($originalName)) {
            return [false, 'Suspicious filename detected.'];
        }

        if ($size <= 0 || $size > $maxSizeBytes) {
            return [false, 'File exceeds the allowed size limit.'];
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            return [false, 'File type is not allowed.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime === false || $mime === '') {
            return [false, 'Unable to detect file type.'];
        }

        if (!in_array($mime, $allowedMimeTypes, true)) {
            return [false, 'Detected file type is not allowed.'];
        }

        if ($requireImage) {
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) {
                return [false, 'Uploaded file is not a valid image.'];
            }
        }

        if ($allowPdf && $extension === 'pdf' && !nxFileStartsWithPdfSignature($tmpPath)) {
            return [false, 'Uploaded PDF file is invalid or corrupted.'];
        }

        if (nxScanFileForDangerousPatterns($tmpPath)) {
            return [false, 'File failed the security scan.'];
        }

        return [true, $mime];
    }
}

/* =========================================================================
   GENERAL ACTIVITY LOGS
   ========================================================================= */
if (!function_exists('logActivity')) {
    function logActivity(mysqli $conn, ?int $userId, string $username, string $role, string $eventType, string $targetType, int $targetId, string $details): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        $sql = "INSERT INTO activity_logs (
                    actor_user_id,
                    actor_username,
                    actor_role,
                    event_type,
                    target_type,
                    target_id,
                    details,
                    ip_address,
                    user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            "issssisss",
            $userId,
            $username,
            $role,
            $eventType,
            $targetType,
            $targetId,
            $details,
            $ipAddress,
            $userAgent
        );
        $stmt->execute();
        $stmt->close();
    }
}

/* =========================================================================
   ADMIN LOGS - LEGACY + SECURE HASHED
   ========================================================================= */
if (!function_exists('logAdminActivity')) {
    function logAdminActivity(mysqli $conn, int $adminId, string $action, string $targetType, int $targetId, string $description): void
    {
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, description)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param("issis", $adminId, $action, $targetType, $targetId, $description);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('getLatestAdminLogHash')) {
    function getLatestAdminLogHash(mysqli $conn): string
    {
        $sql = "SELECT log_hash FROM admin_logs WHERE log_hash IS NOT NULL AND log_hash <> '' ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (string)($row['log_hash'] ?? '');
        }

        return str_repeat('0', 64);
    }
}

if (!function_exists('buildAdminLogHash')) {
    function buildAdminLogHash(
        int $adminId,
        string $action,
        string $targetType,
        int $targetId,
        string $description,
        string $createdAt,
        string $previousHash,
        string $ipAddress,
        string $userAgent
    ): string {
        $payload = implode('|', [
            $adminId,
            $action,
            $targetType,
            $targetId,
            $description,
            $createdAt,
            $previousHash,
            $ipAddress,
            $userAgent
        ]);

        return hash('sha256', $payload);
    }
}

if (!function_exists('logAdminActivitySecure')) {
    function logAdminActivitySecure(mysqli $conn, int $adminId, string $action, string $targetType, int $targetId, string $description): bool
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $createdAt = date('Y-m-d H:i:s');
        $previousHash = getLatestAdminLogHash($conn);
        $logHash = buildAdminLogHash(
            $adminId,
            $action,
            $targetType,
            $targetId,
            $description,
            $createdAt,
            $previousHash,
            $ipAddress,
            $userAgent
        );

        $stmt = $conn->prepare("
            INSERT INTO admin_logs (
                admin_id,
                action,
                target_type,
                target_id,
                description,
                previous_hash,
                log_hash,
                ip_address,
                user_agent,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "ississssss",
            $adminId,
            $action,
            $targetType,
            $targetId,
            $description,
            $previousHash,
            $logHash,
            $ipAddress,
            $userAgent,
            $createdAt
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('verifyAdminLogRowIntegrity')) {
    function verifyAdminLogRowIntegrity(array $row): bool
    {
        $expected = buildAdminLogHash(
            (int)($row['admin_id'] ?? 0),
            (string)($row['action'] ?? ''),
            (string)($row['target_type'] ?? ''),
            (int)($row['target_id'] ?? 0),
            (string)($row['description'] ?? ''),
            (string)($row['created_at'] ?? ''),
            (string)($row['previous_hash'] ?? str_repeat('0', 64)),
            (string)($row['ip_address'] ?? 'UNKNOWN'),
            (string)($row['user_agent'] ?? 'UNKNOWN')
        );

        return hash_equals((string)($row['log_hash'] ?? ''), $expected);
    }
}
?>