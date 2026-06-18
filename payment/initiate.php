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

/**
 * Optimizes an uploaded image to a web-safe JPEG with a max dimension of 600px.
 * Falls back to direct copy if GD is unavailable or fails.
 */
function optimizeProfilePhoto(array $file, string $targetPath): bool {
    $srcPath = $file['tmp_name'];
    
    // Check if GD library is available
    if (!extension_loaded('gd')) {
        return move_uploaded_file($srcPath, $targetPath);
    }
    
    $info = getimagesize($srcPath);
    if (!$info) {
        return move_uploaded_file($srcPath, $targetPath);
    }
    
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $srcImg = @imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($srcPath);
            break;
        case 'image/gif':
            $srcImg = @imagecreatefromgif($srcPath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $srcImg = @imagecreatefromwebp($srcPath);
            } else {
                $srcImg = false;
            }
            break;
        default:
            $srcImg = false;
            break;
    }
    
    if (!$srcImg) {
        return move_uploaded_file($srcPath, $targetPath);
    }
    
    $origWidth = imagesx($srcImg);
    $origHeight = imagesy($srcImg);
    
    $maxDimension = 600;
    $newWidth = $origWidth;
    $newHeight = $origHeight;
    
    if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
        if ($origWidth > $origHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int)round(($origHeight / $origWidth) * $maxDimension);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int)round(($origWidth / $origHeight) * $maxDimension);
        }
    }
    
    $destImg = imagecreatetruecolor($newWidth, $newHeight);
    if (!$destImg) {
        imagedestroy($srcImg);
        return move_uploaded_file($srcPath, $targetPath);
    }
    
    // Handle PNG transparency / background
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($destImg, false);
        imagesavealpha($destImg, true);
        $transparent = imagecolorallocatealpha($destImg, 255, 255, 255, 127);
        imagefilledrectangle($destImg, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    if (!imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
        imagedestroy($srcImg);
        imagedestroy($destImg);
        return move_uploaded_file($srcPath, $targetPath);
    }
    
    // Save as JPEG with 80% quality
    $success = imagejpeg($destImg, $targetPath, 80);
    
    imagedestroy($srcImg);
    imagedestroy($destImg);
    
    return $success;
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
$scheduleGroup    = xss($input['scheduleGroup']    ?? ''); // tee_time_options.id or active tee times
$nameOnPolo       = xss($input['nameOnPolo']       ?? '');

$handicap = ($regType === 'golfer') ? xss($input['handicap'] ?? '') : '';
$golfSetBrand = ($regType === 'golfer') ? xss($input['golfSetBrand'] ?? '') : '';
$puttingContest = ($regType === 'non_golfer') ? xss($input['puttingContestInterest'] ?? '') : '';

// Validation
$required = [
    'Player Category' => $playerCategory,
    'Full Name'       => $fullName,
    'Designation'     => $designation,
    'Organization'    => $organization,
    'Nationality'     => $nationality,
    'Contact'         => $contact,
    'Email'           => $email,
    'T-Shirt Size'    => $tshirtSize,
    'Schedule Group'  => $scheduleGroup,
    'Name on Polo'    => $nameOnPolo
];

if ($regType === 'golfer') {
    $required['Handicap'] = $handicap;
    $required['Golf Set Brand'] = $golfSetBrand;
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

// Profile photo upload validation
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    bail('Please upload a profile photo.');
}
$file = $_FILES['profile_photo'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    bail('Invalid profile photo file type. Please upload a JPG, PNG, GIF, or WebP image.');
}
if ($file['size'] > 5 * 1024 * 1024) {
    bail('Profile photo is too large. Max file size is 5MB.');
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

// Process and save optimized profile picture
$uploadDir = dirname(__DIR__) . '/uploads/profile_pics/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$fileName = $uniqueId . '.jpg';
$targetPath = $uploadDir . $fileName;
$relativeWebPath = 'uploads/profile_pics/' . $fileName;

if (!optimizeProfilePhoto($file, $targetPath)) {
    bail('Failed to save and optimize profile photo.');
}

// MySQL persistence
if ($dbOk) {
    try {
        if ($regType === 'golfer') {
            $stmt = $pdo->prepare(
                'INSERT INTO registrations 
                   (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, profile_photo, name_on_polo, golf_set_brand, contact, email, mailing_address, handicap, tshirt_size, home_club, schedule_group, player_category, reference_name, reference_mission, reference_contact, payment_status, amount, currency, submitted_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                ACTIVE_TOURNAMENT_ID, $uniqueId, $tranId, $fullName, $designation, $organization, $nationality, null, $relativeWebPath, $nameOnPolo, $golfSetBrand, $contact, $email, $mailingAddress, $handicap, $tshirtSize, null, $scheduleGroup, $playerCategory,
                $referenceName ?: null, $referenceMission ?: null, $referenceContact ?: null, 'pending', $amount, $currency, $now
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO registrations_non_golfer 
                   (tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, profile_photo, name_on_polo, contact, email, mailing_address, tshirt_size, arrival_window, putting_contest_interest, player_category, reference_name, reference_mission, reference_contact, payment_status, amount, currency, submitted_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                ACTIVE_TOURNAMENT_ID, $uniqueId, $tranId, $fullName, $designation, $organization, $nationality, null, $relativeWebPath, $nameOnPolo, $contact, $email, $mailingAddress, $tshirtSize, $scheduleGroup, $puttingContest, $playerCategory,
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
        'currency' => $currency,
        'profile_photo' => $relativeWebPath,
        'name_on_polo' => $nameOnPolo
    ];
    if ($regType === 'golfer') {
        $record['schedule_group'] = $scheduleGroup;
        $record['handicap'] = $handicap;
        $record['golf_set_brand'] = $golfSetBrand;
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
