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
    }
    session_start();
}

/* DATABASE CONNECTION */
$host = "localhost";
$dbname = "nextgen_db";
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
    define('SESSION_TIMEOUT_SECONDS', 300); // 5 minutes
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

if (!function_exists('isStrongPassword')) {
    function isStrongPassword(string $password): bool
    {
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
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
                    target_id,12
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