<?php
/**
 * Admin Authentication Handler
 */

declare(strict_types=1);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./index.php');
    exit;
}

// CSRF check
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    header('Location: ./index.php?error=Security+check+failed');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === ADMIN_USER && $password === ADMIN_PASS) {
    $_SESSION['admin_logged_in'] = true;
    unset($_SESSION['csrf_token']);
    header('Location: ./view_registration.php');
    exit;
}

error_log('[admin_authenticate] Failed login attempt for username: ' . htmlspecialchars($username));
header('Location: ./index.php?error=Invalid+credentials');
exit;
