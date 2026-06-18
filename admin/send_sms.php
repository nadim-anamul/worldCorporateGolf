<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/RegistrationRepository.php';
require_once dirname(__DIR__) . '/src/ScheduleService.php';
require_once dirname(__DIR__) . '/src/SMSGateway.php';

requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

requireAdminPostCsrf();

$uid = trim((string)($_POST['uid'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'golfer'));

if ($uid === '') {
    http_response_code(400);
    exit('Registration ID is required.');
}

try {
    $pdo = db();
    $repo = new RegistrationRepository($pdo);
    $schedule = new ScheduleService($pdo);
    $registration = $repo->findByUniqueId($uid);

    if (!$registration || ($registration['registration_type'] ?? '') !== $type) {
        http_response_code(404);
        exit('Registration not found.');
    }

    if (($registration['payment_status'] ?? '') !== 'paid') {
        http_response_code(400);
        exit('SMS can only be sent for paid registrations.');
    }

    $phone = (string)$registration['contact'];
    $name = (string)$registration['full_name'];
    $teeTitle = $schedule->resolveScheduleTitle($type, $registration);

    $eventName = EVENT_NAME;
    $tourStmt = $pdo->prepare('SELECT name FROM tournaments WHERE id = ? LIMIT 1');
    $tourStmt->execute([(int)($registration['tournament_id'] ?? ACTIVE_TOURNAMENT_ID)]);
    $t = $tourStmt->fetch();
    if ($t) {
        $eventName = (string)$t['name'];
    }

    $res = SMSGateway::send($phone, $name, $teeTitle, $eventName);
    if ($res['status'] === 'success') {
        echo 'SUCCESS: SMS sent successfully.';
    } else {
        echo 'ERROR: ' . ($res['message'] ?? 'Sending failed.');
    }
} catch (Throwable $e) {
    appLog('[admin/send_sms.php] failed', ['error' => $e->getMessage(), 'uid' => $uid]);
    echo 'ERROR: Server failure.';
}
exit;
