<?php

declare(strict_types=1);

function esc(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusBadge(string $status): string
{
    return match ($status) {
        'paid'      => '<span class="badge badge-paid">Paid</span>',
        'pending'   => '<span class="badge badge-pending">Pending</span>',
        'failed'    => '<span class="badge badge-failed">Failed</span>',
        'cancelled' => '<span class="badge badge-cancelled">Cancelled</span>',
        default     => '<span class="badge bg-light text-dark border">' . esc($status) . '</span>',
    };
}

function sanitizeInput(string $value): string
{
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function validateCsrfToken(string $posted): bool
{
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    return $posted !== '' && $sessionToken !== '' && hash_equals($sessionToken, $posted);
}

function requireAdminAuth(): void
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ./index.php');
        exit;
    }
}

function requireAdminPostCsrf(): void
{
    $posted = (string)($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($posted)) {
        http_response_code(403);
        if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'CSRF validation failed.']);
        } else {
            echo 'CSRF validation failed.';
        }
        exit;
    }
}

function appLog(string $message, array $context = []): void
{
    if ($context !== []) {
        $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log($message);
}
