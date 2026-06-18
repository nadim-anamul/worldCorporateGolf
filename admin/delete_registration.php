<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/RegistrationRepository.php';

header('Content-Type: application/json; charset=utf-8');
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

requireAdminPostCsrf();

$uniqueId = trim((string)($_POST['unique_id'] ?? ''));
$regType = trim((string)($_POST['registration_type'] ?? 'golfer'));

if ($uniqueId === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing registration unique_id']);
    exit;
}

try {
    $repo = new RegistrationRepository(db());
    $deleted = $repo->deleteByUniqueId($regType, $uniqueId);
    if ($deleted) {
        echo json_encode(['ok' => true, 'message' => 'Registration deleted successfully.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Record not found or could not be deleted.']);
    }
} catch (Throwable $e) {
    appLog('[delete_registration.php] failed', ['error' => $e->getMessage(), 'unique_id' => $uniqueId]);
    echo json_encode(['ok' => false, 'message' => 'Could not delete record.']);
}
exit;
