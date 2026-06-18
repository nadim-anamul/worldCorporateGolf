<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';
require_once dirname(__DIR__) . '/src/RegistrationRepository.php';
require_once dirname(__DIR__) . '/src/ScheduleService.php';
require_once dirname(__DIR__) . '/src/PaymentCompletionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$tranId = trim((string)($_POST['tran_id'] ?? ''));
$valId  = trim((string)($_POST['val_id'] ?? ''));
$status = strtoupper(trim((string)($_POST['status'] ?? '')));

if ($tranId === '' || $valId === '') {
    appLog('[payment/ipn.php] Missing tran_id or val_id');
    http_response_code(400);
    exit('Bad Request');
}

try {
    $sslCommerz = new SSLCommerz();
    if (!$sslCommerz->validateIpnHash($_POST)) {
        appLog('[payment/ipn.php] Invalid hash', ['tran_id' => $tranId]);
        http_response_code(400);
        exit('Invalid Hash Signature');
    }
} catch (Throwable $e) {
    appLog('[payment/ipn.php] Hash exception', ['error' => $e->getMessage()]);
    http_response_code(500);
    exit('Internal Server Error');
}

$pdo = db();
$repo = new RegistrationRepository($pdo);
$registration = $repo->findByTranId($tranId);

if (!$registration) {
    appLog('[payment/ipn.php] Record not found', ['tran_id' => $tranId]);
    http_response_code(404);
    exit('Record Not Found');
}

if ($registration['payment_status'] === 'paid') {
    http_response_code(200);
    exit('IPN processed previously');
}

$type = (string)$registration['registration_type'];

if ($status === 'VALID' || $status === 'VALIDATED') {
    $expectedAmount = (float)$registration['amount'];
    $returnedAmount = (float)(($_POST['currency'] ?? 'BDT') === 'BDT' ? ($_POST['amount'] ?? 0) : ($_POST['currency_amount'] ?? 0));

    if (abs($returnedAmount - $expectedAmount) >= 1) {
        appLog('[payment/ipn.php] Amount mismatch', ['tran_id' => $tranId, 'expected' => $expectedAmount, 'got' => $returnedAmount]);
        http_response_code(400);
        exit('Amount Mismatch');
    }

    try {
        $ssl = new SSLCommerz();
        if (!$ssl->validatePayment($valId, $tranId, $expectedAmount, (string)$registration['currency'])) {
            $repo->updatePaymentStatus($type, $tranId, 'failed');
            http_response_code(400);
            exit('Payment Validation Failed');
        }

        $completion = new PaymentCompletionService($pdo, $repo, new ScheduleService($pdo));
        $completion->markPaidAndNotify($tranId, $valId, false);
        http_response_code(200);
        exit('IPN success processed');
    } catch (Throwable $e) {
        appLog('[payment/ipn.php] Paid update failed', ['error' => $e->getMessage(), 'tran_id' => $tranId]);
        http_response_code(500);
        exit('DB Update Failed');
    }
}

if ($status === 'FAILED') {
    $repo->updatePaymentStatus($type, $tranId, 'failed');
    http_response_code(200);
    exit('IPN fail processed');
}

if ($status === 'CANCELLED') {
    $repo->updatePaymentStatus($type, $tranId, 'cancelled');
    http_response_code(200);
    exit('IPN cancel processed');
}

http_response_code(200);
exit('IPN status ignored');
