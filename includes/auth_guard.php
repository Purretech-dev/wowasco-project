<?php
if (PHP_SAPI === 'cli') {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$publicAuthPage = preg_match('#/auth/(login|register|logout)\.php$#i', $scriptName);

if ($publicAuthPage) {
    return;
}

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    $appRoot = preg_replace('#/(api|includes|modules|tools)(/.*)?$#i', '', $scriptName);

    if ($appRoot === $scriptName) {
        $appRoot = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }

    if ($appRoot === '/' || $appRoot === '.') {
        $appRoot = '';
    }

    header('Location: ' . $appRoot . '/auth/login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$normalizedScript = ltrim(preg_replace('#^.*?/modules/#i', 'modules/', $scriptName), '/');

if (stripos($normalizedScript, 'modules/') === 0) {
    $role = $_SESSION['role'] ?? 'customer';
    $role = $role === 'admin' ? 'super_admin' : $role;

    if ($role === 'super_admin') {
        return;
    }

    if ($role === 'customer') {
        if ($normalizedScript === 'modules/customer/customer_portal.php') {
            return;
        }

        http_response_code(403);
        exit('Access denied.');
    }

    $allowedPages = $_SESSION['allowed_pages'] ?? [];
    $allowedPages = is_array($allowedPages) ? $allowedPages : [];

    if (!in_array($normalizedScript, $allowedPages, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
