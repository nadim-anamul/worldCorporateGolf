<?php
/**
 * SSLCommerz Instant Payment Notification (IPN) Handler
 * Processes payments asynchronously in the background.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';
require_once dirname(__DIR__) . '/src/SMSGateway.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$tranId = trim((string)($_POST['tran_id'] ?? ''));
$valId  = trim((string)($_POST['val_id']  ?? ''));
$status = strtoupper(trim((string)($_POST['status']  ?? '')));

if ($tranId === '' || $valId === '') {
    error_log('[payment/ipn.php] Missing tran_id or val_id in IPN POST.');
    http_response_code(400);
    exit('Bad Request');
}

// 1. Verify md5 hash signature of IPN data
try {
    $sslCommerz = new SSLCommerz();
    $validHash = $sslCommerz->validateIpnHash($_POST);
    
    if (!$validHash) {
        error_log('[payment/ipn.php] Hash signature check failed for transaction: ' . $tranId);
        http_response_code(400);
        exit('Invalid Hash Signature');
    }
} catch (Throwable $e) {
    error_log('[payment/ipn.php] Hash signature exception: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}

// 2. Fetch record from MySQL
$registration = null;
$type = ''; // golfer or non_golfer
$targetTable = 'registrations';

try {
    $pdo = db();
    
    // Check golfer
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE tran_id = ? LIMIT 1');
    $stmt->execute([$tranId]);
    $row = $stmt->fetch();
    
    if ($row) {
        $registration = $row;
        $type = 'golfer';
        $targetTable = 'registrations';
    } else {
        // Check non-golfer
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
    error_log('[payment/ipn.php] DB lookup exception: ' . $e->getMessage());
    http_response_code(500);
    exit('Database Error');
}

if (!$registration) {
    error_log('[payment/ipn.php] Registration record not found for transaction: ' . $tranId);
    http_response_code(404);
    exit('Record Not Found');
}

// Already processed, no action needed
if ($registration['payment_status'] === 'paid') {
    http_response_code(200);
    exit('IPN processed previously');
}

// 3. Process according to status
if ($status === 'VALID' || $status === 'VALIDATED') {
    
    // Confirm amount matches to avoid tampering
    $expectedAmount = (float)$registration['amount'];
    $returnedAmount = (float)(($_POST['currency'] ?? 'BDT') === 'BDT' ? ($_POST['amount'] ?? 0) : ($_POST['currency_amount'] ?? 0));
    
    if (abs($returnedAmount - $expectedAmount) >= 1) {
        error_log('[payment/ipn.php] Amount mismatch. Expected ' . $expectedAmount . ', got ' . $returnedAmount);
        http_response_code(400);
        exit('Amount Mismatch');
    }
    
    // Mark transaction as paid
    try {
        $pdo->prepare(
            "UPDATE {$targetTable} SET payment_status = 'paid', val_id = ?, paid_at = NOW() WHERE tran_id = ?"
        )->execute([$valId, $tranId]);
        
        sync_json_status($tranId, 'paid', $type);
        
        // Fetch group title
        $teeTitle = 'TBA';
        if ($type === 'golfer') {
            $teeStmt = $pdo->prepare('SELECT title FROM tee_time_options WHERE id = ?');
            $teeStmt->execute([(int)$registration['schedule_group']]);
            $r = $teeStmt->fetch();
            if ($r) $teeTitle = $r['title'];
        } else {
            $winStmt = $pdo->prepare('SELECT title FROM arrival_window_options_non_golfer WHERE id = ?');
            $winStmt->execute([$registration['arrival_window']]);
            $r = $winStmt->fetch();
            if ($r) $teeTitle = $r['title'];
        }
        
        // Send SMS confirmation
        SMSGateway::send(
            $registration['contact'],
            $registration['full_name'],
            $teeTitle
        );
        
        http_response_code(200);
        exit('IPN success processed');
    } catch (Throwable $e) {
        error_log('[payment/ipn.php] DB Update to paid failed: ' . $e->getMessage());
        http_response_code(500);
        exit('DB Update Failed');
    }
    
} elseif ($status === 'FAILED') {
    
    try {
        $pdo->prepare("UPDATE {$targetTable} SET payment_status = 'failed' WHERE tran_id = ?")->execute([$tranId]);
        sync_json_status($tranId, 'failed', $type);
        http_response_code(200);
        exit('IPN fail processed');
    } catch (Throwable $e) {
        error_log('[payment/ipn.php] DB update to failed failed: ' . $e->getMessage());
        http_response_code(500);
        exit('DB update failed');
    }
    
} elseif ($status === 'CANCELLED') {
    
    try {
        $pdo->prepare("UPDATE {$targetTable} SET payment_status = 'cancelled' WHERE tran_id = ?")->execute([$tranId]);
        sync_json_status($tranId, 'cancelled', $type);
        http_response_code(200);
        exit('IPN cancel processed');
    } catch (Throwable $e) {
        error_log('[payment/ipn.php] DB update to cancelled failed: ' . $e->getMessage());
        http_response_code(500);
        exit('DB update failed');
    }
    
}

http_response_code(200);
exit('IPN status ignored');

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
