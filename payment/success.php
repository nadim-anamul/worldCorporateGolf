<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';
require_once dirname(__DIR__) . '/src/RegistrationRepository.php';
require_once dirname(__DIR__) . '/src/ScheduleService.php';
require_once dirname(__DIR__) . '/src/PaymentCompletionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/index.php');
    exit;
}

$tranId = trim((string)($_POST['tran_id'] ?? ''));
$valId  = trim((string)($_POST['val_id'] ?? ''));

if ($tranId === '' || $valId === '') {
    appLog('[payment/success.php] Missing tran_id or val_id');
    header('Location: ' . APP_BASE_URL . '/payment/fail.php');
    exit;
}

$pdo = db();
$repo = new RegistrationRepository($pdo);
$registration = $repo->findByTranId($tranId);

if (!$registration) {
    appLog('[payment/success.php] Registration not found', ['tran_id' => $tranId]);
    header('Location: ' . APP_BASE_URL . '/payment/fail.php');
    exit;
}

if ($registration['payment_status'] === 'paid') {
    header('Location: ' . APP_BASE_URL . '/success.php?uid=' . urlencode($registration['unique_id']));
    exit;
}

$type = (string)$registration['registration_type'];
$completion = new PaymentCompletionService($pdo, $repo, new ScheduleService($pdo));

try {
    $ssl = new SSLCommerz();
    $verified = $ssl->validatePayment($valId, $tranId, (float)$registration['amount'], (string)$registration['currency']);
    if (!$verified) {
        $repo->updatePaymentStatus($type, $tranId, 'failed');
        header('Location: ' . APP_BASE_URL . '/payment/fail.php?reason=validation_failed');
        exit;
    }
} catch (Throwable $e) {
    appLog('[payment/success.php] Validation exception', ['error' => $e->getMessage(), 'tran_id' => $tranId]);
    header('Location: ' . APP_BASE_URL . '/payment/fail.php?reason=validation_exception');
    exit;
}

try {
    $completion->markPaidAndNotify($tranId, $valId, false);
} catch (Throwable $e) {
    appLog('[payment/success.php] Mark paid failed', ['error' => $e->getMessage(), 'tran_id' => $tranId]);
}

header('Location: ' . APP_BASE_URL . '/success.php?uid=' . urlencode($registration['unique_id']));
exit;
