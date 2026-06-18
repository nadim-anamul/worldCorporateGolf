<?php
/**
 * Payment Initiation Endpoint
 * Initiates the payment session and inserts the pending record.
 */

declare(strict_types=1);

ob_start();
session_start();

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/SSLCommerz.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

function bail(string $msg) {
    ob_clean();
    echo json_encode(['status' => 'fail', 'message' => $msg]);
    exit;
}

function xss($value): string {
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bail('Method not allowed.');
}

$raw = $_POST['cart_json'] ?? '';
$input = json_decode($raw, true);

if (!is_array($input) || empty($input)) {
    bail('Invalid request payload.');
}

// CSRF validation
$postedToken  = (string)($input['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
    bail('Security check failed. Please refresh and try again.');
}

$regType = xss($input['registration_type'] ?? 'golfer');

// Sanitize fields
$playerCategory   = xss($input['playerCategory']   ?? '');
$gender           = xss($input['gender']           ?? '');
$referenceName    = xss($input['referenceName']    ?? '');
$referenceMission = xss($input['referenceMission'] ?? '');
$referenceContact = xss($input['referenceContact'] ?? '');
$fullName         = xss($input['fullName']         ?? '');
$designation      = xss($input['designation']      ?? '');
$organization     = xss($input['organization']     ?? '');
$nationality      = xss($input['nationality']      ?? '');
$contact          = xss($input['contact']          ?? '');
$email            = strtolower(trim(filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL)));
$mailingAddress   = xss($input['mailingAddress']   ?? '');
$tshirtSize       = xss($input['tshirtSize']       ?? '');
$scheduleGroup    = xss($input['scheduleGroup']    ?? ''); // tee_time_options.id or arrival_window_options_non_golfer.id

$handicap = ($regType === 'golfer') ? xss($input['handicap'] ?? '') : '';
$homeClub = ($regType === 'golfer') ? xss($input['homeClub'] ?? '') : '';
$puttingContest = ($regType === 'non_golfer') ? xss($input['puttingContestInterest'] ?? '') : '';

// Validation
$required = [
    'Player Category' => $playerCategory,
    'Gender'          => $gender,
    'Full Name'       => $fullName,
    'Designation'     => $designation,
    'Organization'    => $organization,
    'Nationality'     => $nationality,
    'Contact'         => $contact,
    'Email'           => $email,
    'T-Shirt Size'    => $tshirtSize,
    'Schedule Group'  => $scheduleGroup
];

if ($regType === 'golfer') {
    $required['Handicap'] = $handicap;
    $required['Home Club'] = $homeClub;
} else {
    $required['Putting Contest Interest'] = $puttingContest;
}

foreach ($required as $label => $val) {
    if ($val === '') {
        bail("Please fill in: $label.");
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bail('Invalid email address.');
}

// Connect & check capacity & duplicates
$dbOk = false;
try {
    $pdo = db();
    $dbOk = true;

    if ($regType === 'golfer') {
        // Check Golfer capacity
        $teeStmt = $pdo->prepare("SELECT slot_number, title FROM tee_time_options WHERE id = ? AND tournament_id = ? AND is_active = 1 LIMIT 1");
        $teeStmt->execute([(int)$scheduleGroup, ACTIVE_TOURNAMENT_ID]);
        $tee = $teeStmt->fetch();
        if (!$tee) {
            bail('Invalid tee time selection. Please reload and try again.');
        }

        $capStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations WHERE schedule_group = ? AND tournament_id = ? AND payment_status = 'paid'");
        $capStmt->execute([$scheduleGroup, ACTIVE_TOURNAMENT_ID]);
        $used = (int)$capStmt->fetch()['cnt'];
        if ($used >= (int)$tee['slot_number']) {
            bail('Selected tee time group is full. Please select a different schedule group.');
        }

        // Check duplicate paid email
        $dupStmt = $pdo->prepare("SELECT id FROM registrations WHERE email = ? AND tournament_id = ? AND payment_status = 'paid' LIMIT 1");
        $dupStmt->execute([$email, ACTIVE_TOURNAMENT_ID]);
        if ($dupStmt->fetch()) {
            bail('This email has already completed golfer registration.');
        }

        // Delete previous abandoned golfer attempts for this email
        $pdo->prepare("DELETE FROM registrations WHERE email = ? AND tournament_id = ? AND payment_status IN ('pending','failed','cancelled')")->execute([$email, ACTIVE_TOURNAMENT_ID]);

    } else {
        // Check Non-Golfer capacity
        $winStmt = $pdo->prepare("SELECT slot_number, title FROM tee_time_options WHERE id = ? AND tournament_id = ? AND is_active = 1 LIMIT 1");
        $winStmt->execute([(int)$scheduleGroup, ACTIVE_TOURNAMENT_ID]);
        $win = $winStmt->fetch();
        if (!$win) {
            bail('Invalid tee time selection. Please reload and try again.');
        }

        $capStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations_non_golfer WHERE arrival_window = ? AND tournament_id = ? AND payment_status = 'paid'");
        $capStmt->execute([$scheduleGroup, ACTIVE_TOURNAMENT_ID]);
        $used = (int)$capStmt->fetch()['cnt'];
        if ($used >= (int)$win['slot_number']) {
            bail('Selected tee time group is full. Please select a different tee time.');
        }

        // Check duplicate paid email
        $dupStmt = $pdo->prepare("SELECT id FROM registrations_non_golfer WHERE email = ? AND tournament_id = ? AND payment_status = 'paid' LIMIT 1");
        $dupStmt->execute([$email, ACTIVE_TOURNAMENT_ID]);
        if ($dupStmt->fetch()) {
            bail('This email has already completed non-golfer registration.');
        }

        // Delete previous abandoned guest attempts
        $pdo->prepare("DELETE FROM registrations_non_golfer WHERE email = ? AND tournament_id = ? AND payment_status IN ('pending','failed','cancelled')")->execute([$email, ACTIVE_TOURNAMENT_ID]);
    }
} catch (Throwable $e) {
    error_log('[initiate.php] DB Capacity/Dup Check Failed: ' . $e->getMessage());
}

// Generate IDs
$uniqueId = bin2hex(random_bytes(16));
$tranId = 'WCC-' . strtoupper(substr($uniqueId, 0, 24));
$now = date('Y-m-d H:i:s');
$amount = (float)CURRENT_FEE;
$currency = EVENT_CURRENCY;

// MySQL persistence
if ($dbOk) {
    try {
        if ($regType === 'golfer') {
            $stmt = $pdo->prepare(
                'INSERT INTO registrations 
                   (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, contact, email, mailing_address, handicap, tshirt_size, home_club, schedule_group, player_category, reference_name, reference_mission, reference_contact, payment_status, amount, currency, submitted_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                ACTIVE_TOURNAMENT_ID, $uniqueId, $tranId, $fullName, $designation, $organization, $nationality, $gender, $contact, $email, $mailingAddress, $handicap, $tshirtSize, $homeClub, $scheduleGroup, $playerCategory,
                $referenceName ?: null, $referenceMission ?: null, $referenceContact ?: null, 'pending', $amount, $currency, $now
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO registrations_non_golfer 
                   (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, contact, email, mailing_address, tshirt_size, arrival_window, putting_contest_interest, player_category, reference_name, reference_mission, reference_contact, payment_status, amount, currency, submitted_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                ACTIVE_TOURNAMENT_ID, $uniqueId, $tranId, $fullName, $designation, $organization, $nationality, $gender, $contact, $email, $mailingAddress, $tshirtSize, $scheduleGroup, $puttingContest, $playerCategory,
                $referenceName ?: null, $referenceMission ?: null, $referenceContact ?: null, 'pending', $amount, $currency, $now
            ]);
        }
    } catch (Throwable $e) {
        error_log('[initiate.php] DB Insert pending registration failed: ' . $e->getMessage());
        bail('Could not save registration details. Please try again.');
    }
}

// Local JSON backup dual-write
$jsonFile = dirname(__DIR__) . '/data/' . ($regType === 'golfer' ? 'registrations.json' : 'registrations_non_golfer.json');
if (is_writable(dirname($jsonFile))) {
    if (!file_exists($jsonFile)) {
        file_put_contents($jsonFile, '[]', LOCK_EX);
    }
    $all = json_decode(file_get_contents($jsonFile) ?: '[]', true) ?: [];
    
    $record = [
        'unique_id' => $uniqueId,
        'tran_id' => $tranId,
        'full_name' => $fullName,
        'email' => $email,
        'contact' => $contact,
        'payment_status' => 'pending',
        'registration_type' => $regType,
        'submitted_at' => $now,
        'amount' => $amount,
        'currency' => $currency
    ];
    if ($regType === 'golfer') {
        $record['schedule_group'] = $scheduleGroup;
        $record['handicap'] = $handicap;
        $record['home_club'] = $homeClub;
    } else {
        $record['arrival_window'] = $scheduleGroup;
        $record['putting_contest_interest'] = $puttingContest;
    }
    
    $all[] = $record;
    $enc = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($enc) {
        file_put_contents($jsonFile, $enc, LOCK_EX);
    }
}

// Session state
$_SESSION['pending_tran_id'] = $tranId;
$_SESSION['pending_unique_id'] = $uniqueId;
$_SESSION['pending_reg_type'] = $regType;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Rotate token

// Call SSLCommerz Gateway
try {
    $sslCommerz = new SSLCommerz();
    
    $sslParams = [
        'total_amount'     => $amount,
        'currency'         => $currency,
        'tran_id'          => $tranId,
        'product_category' => 'Sports Event',
        'product_name'     => EVENT_NAME . ' Registration',
        'product_profile'  => 'non-physical-goods',
        'num_of_item'      => 1,
        
        // Customer Info
        'cus_name'         => $fullName,
        'cus_email'        => $email,
        'cus_phone'        => $contact,
        'cus_add1'         => $mailingAddress !== '' ? $mailingAddress : 'N/A',
        'cus_city'         => 'Dhaka',
        'cus_country'      => 'Bangladesh',
        
        // Shipping Info (Required, mirror Customer Info)
        'ship_name'        => $fullName,
        'ship_add1'        => $mailingAddress !== '' ? $mailingAddress : 'N/A',
        'ship_city'        => 'Dhaka',
        'ship_country'     => 'Bangladesh',
        
        // Extra trace fields returned by Gateway callback POST
        'value_a'          => $uniqueId,
        'value_b'          => $regType,
        'value_c'          => $scheduleGroup
    ];

    $paymentUrl = $sslCommerz->initiatePayment($sslParams);
    
    ob_clean();
    echo json_encode([
        'status'           => 'success',
        'payment_page_url' => $paymentUrl
    ]);
    exit;
} catch (Throwable $e) {
    error_log('[initiate.php] SSLCommerz Call Failed: ' . $e->getMessage());
    bail('Could not establish connection with SSLCommerz payment gateway: ' . $e->getMessage());
}
