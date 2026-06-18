<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/RegistrationValidator.php';
require_once dirname(__DIR__) . '/src/ScheduleService.php';

$failures = 0;

function assertTrue(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        $failures++;
    } else {
        echo "OK: {$message}\n";
    }
}

try {
    $pdo = db();
    $schedule = new ScheduleService($pdo);
    $validator = new RegistrationValidator();

    $golferPayload = [
        'playerCategory' => 'Diplomats',
        'fullName' => 'Mr. Test User',
        'designation' => 'Ambassador',
        'organization' => 'Test Org',
        'nationality' => 'BD',
        'contact' => '+8801700000000',
        'email' => 'test@example.com',
        'tshirtSize' => 'L',
        'scheduleGroup' => '1',
        'nameOnPolo' => 'Test',
        'handicap' => '0-12',
        'golfSetBrand' => 'Callaway',
    ];
    $validated = $validator->validate($golferPayload, 'golfer');
    assertTrue($validated['full_name'] === 'Mr. Test User', 'Golfer validation passes');

    try {
        $validator->validate(['playerCategory' => 'Non-Diplomats', 'fullName' => 'X'], 'golfer');
        assertTrue(false, 'Non-diplomat sponsor should be required');
    } catch (RuntimeException) {
        assertTrue(true, 'Non-diplomat sponsor required');
    }

    $labels = $schedule->buildScheduleLabels();
    assertTrue(is_array($labels), 'Schedule labels built');

    $windows = $schedule->getNonGolferWindowOptions(ACTIVE_TOURNAMENT_ID);
    assertTrue(is_array($windows), 'Non-golfer windows loaded');
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
    $failures++;
}

exit($failures > 0 ? 1 : 0);
