<?php

declare(strict_types=1);

ob_start();
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';
require_once dirname(__DIR__) . '/src/ScheduleService.php';
require_once dirname(__DIR__) . '/src/RegistrationRepository.php';
require_once dirname(__DIR__) . '/src/RegistrationValidator.php';
require_once dirname(__DIR__) . '/src/ProfilePhotoService.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

function bail(string $msg): void
{
    ob_clean();
    echo json_encode(['status' => 'fail', 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bail('Method not allowed.');
}

if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    bail('Upload too large. Please use a smaller profile photo (max 5MB) and try again.');
}

$raw = $_POST['cart_json'] ?? '';
$input = json_decode($raw, true);
if (!is_array($input) || empty($input)) {
    bail('Invalid request payload.');
}

$postedToken = (string)($input['csrf_token'] ?? '');
if (!validateCsrfToken($postedToken)) {
    bail('Security check failed. Please refresh and try again.');
}

$regType = sanitizeInput((string)($input['registration_type'] ?? 'golfer'));

try {
    $validator = new RegistrationValidator();
    $data = $validator->validate($input, $regType);
} catch (RuntimeException $e) {
    bail($e->getMessage());
}

if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    bail('Please upload a profile photo.');
}

$file = $_FILES['profile_photo'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
    bail('Please upload a profile photo. If you already selected one, choose the file again before submitting.');
}
$photoService = new ProfilePhotoService();
try {
    $photoService->validateUpload($file);
} catch (RuntimeException $e) {
    bail($e->getMessage());
}

$pdo = db();
$repo = new RegistrationRepository($pdo);
$schedule = new ScheduleService($pdo);

try {
    if ($repo->hasPaidEmail($regType, $data['email'], ACTIVE_TOURNAMENT_ID)) {
        bail($regType === 'golfer'
            ? 'This email has already completed golfer registration.'
            : 'This email has already completed non-golfer registration.');
    }
} catch (Throwable $e) {
    appLog('[initiate.php] DB duplicate check failed', ['error' => $e->getMessage()]);
    bail('Could not verify registration availability. Please try again.');
}

$uniqueId = bin2hex(random_bytes(16));
$tranId = 'WCC-' . strtoupper(substr($uniqueId, 0, 24));
$now = date('Y-m-d H:i:s');
$amount = (float)CURRENT_FEE;
$currency = EVENT_CURRENCY;

$uploadDir = dirname(__DIR__) . '/uploads/profile_pics/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$fileName = $uniqueId . '.jpg';
$targetPath = $uploadDir . $fileName;
$relativeWebPath = 'uploads/profile_pics/' . $fileName;

if (!$photoService->saveOptimized($file, $targetPath)) {
    bail('Failed to process profile photo. Please upload a JPG or PNG image.');
}

$slotId = $data['schedule_group'];

try {
    $saveRegistration = function () use (
        $repo, $regType, $data, $uniqueId, $tranId, $now, $amount, $currency, $relativeWebPath, $slotId
    ): void {
        $repo->deleteAbandonedByEmail($regType, $data['email'], ACTIVE_TOURNAMENT_ID);

        $payload = [
            'tournament_id' => ACTIVE_TOURNAMENT_ID,
            'unique_id'     => $uniqueId,
            'tran_id'       => $tranId,
            'full_name'     => $data['full_name'],
            'designation'   => $data['designation'],
            'organization'  => $data['organization'],
            'nationality'   => $data['nationality'],
            'profile_photo' => $relativeWebPath,
            'name_on_polo'  => $data['name_on_polo'],
            'contact'       => $data['contact'],
            'email'         => $data['email'],
            'mailing_address' => $data['mailing_address'],
            'tshirt_size'   => $data['tshirt_size'],
            'player_category' => $data['player_category'],
            'reference_name' => $data['reference_name'],
            'reference_mission' => $data['reference_mission'],
            'reference_contact' => $data['reference_contact'],
            'amount'        => $amount,
            'currency'      => $currency,
            'submitted_at'  => $now,
        ];

        if ($regType === 'golfer') {
            $payload['schedule_group'] = $slotId;
            $payload['handicap'] = $data['handicap'];
            $payload['golf_set_brand'] = $data['golf_set_brand'];
        } else {
            $payload['arrival_window'] = $slotId;
            $payload['putting_contest_interest'] = $data['putting_contest'];
        }

        $repo->createPending($regType, $payload);
    };

    if ($regType === 'non_golfer') {
        $pdo->beginTransaction();
        try {
            $saveRegistration();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    } else {
        $schedule->withSlotReservation($regType, $slotId, ACTIVE_TOURNAMENT_ID, $saveRegistration);
    }
} catch (RuntimeException $e) {
    @unlink($targetPath);
    bail($e->getMessage());
} catch (Throwable $e) {
    @unlink($targetPath);
    appLog('[initiate.php] Registration failed', ['error' => $e->getMessage()]);
    bail('Could not save registration details. Please try again.');
}

$_SESSION['pending_tran_id'] = $tranId;
$_SESSION['pending_unique_id'] = $uniqueId;
$_SESSION['pending_reg_type'] = $regType;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

try {
    $sslCommerz = new SSLCommerz();
    $base = rtrim(APP_BASE_URL, '/');
    $paymentUrl = $sslCommerz->initiatePayment([
        'total_amount'     => $amount,
        'currency'         => $currency,
        'tran_id'          => $tranId,
        'cancel_url'       => $base . '/payment/cancel.php?tran_id=' . rawurlencode($tranId),
        'fail_url'         => $base . '/payment/fail.php?tran_id=' . rawurlencode($tranId),
        'product_category' => 'Sports Event',
        'product_name'     => EVENT_NAME . ' Registration',
        'product_profile'  => 'non-physical-goods',
        'num_of_item'      => 1,
        'cus_name'         => $data['full_name'],
        'cus_email'        => $data['email'],
        'cus_phone'        => $data['contact'],
        'cus_add1'         => $data['mailing_address'] !== '' ? $data['mailing_address'] : 'N/A',
        'cus_city'         => 'Dhaka',
        'cus_country'      => 'Bangladesh',
        'ship_name'        => $data['full_name'],
        'ship_add1'        => $data['mailing_address'] !== '' ? $data['mailing_address'] : 'N/A',
        'ship_city'        => 'Dhaka',
        'ship_country'     => 'Bangladesh',
        'value_a'          => $uniqueId,
        'value_b'          => $regType,
        'value_c'          => $slotId,
    ]);

    ob_clean();
    echo json_encode(['status' => 'success', 'payment_page_url' => $paymentUrl]);
    exit;
} catch (Throwable $e) {
    appLog('[initiate.php] SSLCommerz call failed', ['error' => $e->getMessage(), 'tran_id' => $tranId]);
    bail('Could not establish connection with the payment gateway. Please try again.');
}
