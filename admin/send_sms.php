<?php
/**
 * Admin Action Handler: Dispatch Manual SMS Confirmation
 */

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/SMSGateway.php';

$phone = trim((string)($_POST['contact'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$uid = trim((string)($_POST['uid'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'golfer'));

if ($phone === '' || $name === '') {
    http_response_code(400);
    exit('Phone and Name are required parameters.');
}

$teeTitle = 'confirmed schedule';
$eventName = EVENT_NAME;

// Dynamically lookup selected tee time/window and tournament name if we have the registration ID
if ($uid !== '') {
    try {
        $pdo = db();
        if ($type === 'non_golfer') {
            $stmt = $pdo->prepare('SELECT arrival_window, tournament_id FROM registrations_non_golfer WHERE unique_id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row) {
                $resolved = false;
                if ((int)$row['tournament_id'] === (int)ACTIVE_TOURNAMENT_ID) {
                    $teeStmt = $pdo->prepare('SELECT title FROM tee_time_options WHERE id = ?');
                    $teeStmt->execute([(int)$row['arrival_window']]);
                    $w = $teeStmt->fetch();
                    if ($w) {
                        $teeTitle = $w['title'];
                        $resolved = true;
                    }
                }
                if (!$resolved) {
                    $winStmt = $pdo->prepare('SELECT title FROM arrival_window_options_non_golfer WHERE id = ?');
                    $winStmt->execute([(int)$row['arrival_window']]);
                    $w = $winStmt->fetch();
                    if ($w) $teeTitle = $w['title'];
                }
                
                $tourStmt = $pdo->prepare('SELECT name FROM tournaments WHERE id = ?');
                $tourStmt->execute([(int)$row['tournament_id']]);
                $t = $tourStmt->fetch();
                if ($t) $eventName = $t['name'];
            }
        } else {
            $stmt = $pdo->prepare('SELECT schedule_group, tournament_id FROM registrations WHERE unique_id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row) {
                $teeStmt = $pdo->prepare('SELECT title FROM tee_time_options WHERE id = ?');
                $teeStmt->execute([(int)$row['schedule_group']]);
                $t = $teeStmt->fetch();
                if ($t) $teeTitle = $t['title'];
                
                $tourStmt = $pdo->prepare('SELECT name FROM tournaments WHERE id = ?');
                $tourStmt->execute([(int)$row['tournament_id']]);
                $t = $tourStmt->fetch();
                if ($t) $eventName = $t['name'];
            }
        }
    } catch (Throwable $e) {
        error_log('[admin/send_sms.php] Dynamic tee time/tournament lookup failed: ' . $e->getMessage());
    }
}

// Send SMS using the core gateway dispatcher
$res = SMSGateway::send($phone, $name, $teeTitle, $eventName);

if ($res['status'] === 'success') {
    echo 'SUCCESS: SMS sent successfully.';
} else {
    echo 'ERROR: ' . ($res['message'] ?? 'Sending failed.');
}
exit;
