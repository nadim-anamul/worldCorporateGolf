<?php
/**
 * Admin Action Handler: Delete Registration Record
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

$uniqueId = trim((string)($_POST['unique_id'] ?? ''));
$regType = trim((string)($_POST['registration_type'] ?? 'golfer'));

if ($uniqueId === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing registration unique_id']);
    exit;
}

$dbDeleted = false;
$targetTable = ($regType === 'non_golfer') ? 'registrations_non_golfer' : 'registrations';

try {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM {$targetTable} WHERE unique_id = ?");
    $stmt->execute([$uniqueId]);
    $dbDeleted = ($stmt->rowCount() > 0);
} catch (Throwable $e) {
    error_log('[delete_registration.php] DB delete failed: ' . $e->getMessage());
}

if ($dbDeleted) {
    echo json_encode(['ok' => true, 'message' => 'Registration deleted successfully.']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Record not found or could not be deleted.']);
}
exit;
