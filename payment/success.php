<?php
/**
 * SSLCommerz Success Callback Page
 */

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';
require_once dirname(__DIR__) . '/src/SMSGateway.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE_URL . '/index.php');
    exit;
}

$tranId = trim((string)($_POST['tran_id'] ?? ''));
$valId  = trim((string)($_POST['val_id']  ?? ''));

if ($tranId === '' || $valId === '') {
    error_log('[payment/success.php] Missing tran_id or val_id in callback POST.');
    header('Location: ' . APP_BASE_URL . '/payment/fail.php');
    exit;
}

$registration = null;
$type = ''; // golfer or non_golfer
$targetTable = 'registrations';

try {
    $pdo = db();
    
    // Check golfer table
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE tran_id = ? LIMIT 1');
    $stmt->execute([$tranId]);
    $row = $stmt->fetch();
    
    if ($row) {
        $registration = $row;
        $type = 'golfer';
        $targetTable = 'registrations';
    } else {
        // Check non-golfer table
        $stmt2 = $pdo->prepare('SELECT * FROM registrations_non_golfer WHERE tran_id = ? LIMIT 1');
        $stmt2->execute([$tranId]);
        $row2 = $stmt2->fetch();
        if ($row2) {
            $registration = $row2;
            $type = 'non_golfer';
            $targetTable = 'registrations_non_golfer';
        }
    }
} catch (Throwable $e) {
    error_log('[payment/success.php] DB registration lookup failed: ' . $e->getMessage());
}

if (!$registration) {
    error_log('[payment/success.php] Registration not found for transaction: ' . $tranId);
    header('Location: ' . APP_BASE_URL . '/payment/fail.php');
    exit;
}

// Check if already paid (in case IPN request was received first)
if ($registration['payment_status'] === 'paid') {
    header('Location: ' . APP_BASE_URL . '/success.php?uid=' . urlencode($registration['unique_id']));
    exit;
}

// Re-verify payment with SSLCommerz API
try {
    $sslCommerz = new SSLCommerz();
    $verified = $sslCommerz->validatePayment(
        $valId,
        $tranId,
        (float)$registration['amount'],
        $registration['currency']
    );
    
    if (!$verified) {
        error_log('[payment/success.php] Payment validation failed for transaction: ' . $tranId);
        
        // Mark failed in DB
        $pdo->prepare("UPDATE {$targetTable} SET payment_status = 'failed' WHERE tran_id = ?")->execute([$tranId]);
        sync_json_status($tranId, 'failed', $type);
        
        header('Location: ' . APP_BASE_URL . '/payment/fail.php?reason=validation_failed');
        exit;
    }
} catch (Throwable $e) {
    error_log('[payment/success.php] SSLCommerz validation exception: ' . $e->getMessage());
    header('Location: ' . APP_BASE_URL . '/payment/fail.php?reason=validation_exception');
    exit;
}

// Mark transaction as paid
try {
    $pdo->prepare(
        "UPDATE {$targetTable} SET payment_status = 'paid', val_id = ?, paid_at = NOW() WHERE tran_id = ?"
    )->execute([$valId, $tranId]);
    
    sync_json_status($tranId, 'paid', $type);
} catch (Throwable $e) {
    error_log('[payment/success.php] DB update to paid failed: ' . $e->getMessage());
}

// Fetch dynamic title of the tee time / window for the SMS dispatcher
$teeTitle = 'TBA';
try {
    if ($type === 'golfer') {
        $teeStmt = $pdo->prepare('SELECT title FROM tee_time_options WHERE id = ?');
        $teeStmt->execute([(int)$registration['schedule_group']]);
        $row = $teeStmt->fetch();
        if ($row) {
            $teeTitle = $row['title'];
        }
    } else {
        $winStmt = $pdo->prepare('SELECT title FROM arrival_window_options_non_golfer WHERE id = ?');
        $winStmt->execute([$registration['arrival_window']]);
        $row = $winStmt->fetch();
        if ($row) {
            $teeTitle = $row['title'];
        }
    }
} catch (Throwable $e) {
    error_log('[payment/success.php] Fetching schedule group title for SMS failed: ' . $e->getMessage());
}

// Dispatch confirmation SMS
SMSGateway::send(
    $registration['contact'],
    $registration['full_name'],
    $teeTitle
);

// Redirect to public success receipt page
header('Location: ' . APP_BASE_URL . '/success.php?uid=' . urlencode($registration['unique_id']));
exit;

/**
 * Synchronize local JSON backup status
 */
function sync_json_status(string $tranId, string $status, string $regType) {
    $file = dirname(__DIR__) . '/data/' . ($regType === 'golfer' ? 'registrations.json' : 'registrations_non_golfer.json');
    if (!is_readable($file)) {
        return;
    }
    
    $data = json_decode(file_get_contents($file) ?: '[]', true);
    if (!is_array($data)) {
        return;
    }
    
    foreach ($data as &$record) {
        if (($record['tran_id'] ?? '') === $tranId) {
            $record['payment_status'] = $status;
            if ($status === 'paid') {
                $record['paid_at'] = date('Y-m-d H:i:s');
            }
            break;
        }
    }
    unset($record);
    
    $enc = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($enc) {
        file_put_contents($file, $enc, LOCK_EX);
    }
}
