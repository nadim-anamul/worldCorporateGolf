<?php
/**
 * Application Configuration
 *
 * Dynamically loads event properties from the active database tournament.
 * Falls back to .env configurations if database is unreachable.
 */

declare(strict_types=1);

// Load .env file
$_envFile = dirname(__DIR__) . '/.env';
if (!is_readable($_envFile)) {
    http_response_code(500);
    die('Configuration error: .env file not found at ' . $_envFile);
}

$_env = [];
foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
    $_line = trim($_line);
    if ($_line === '' || $_line[0] === '#') {
        continue;
    }
    $_pos = strpos($_line, '=');
    if ($_pos === false) {
        continue;
    }
    $_key = trim(substr($_line, 0, $_pos));
    $_val = trim(substr($_line, $_pos + 1));
    if (strlen($_val) >= 2 &&
        (($_val[0] === '"' && $_val[-1] === '"') ||
         ($_val[0] === "'" && $_val[-1] === "'"))) {
        $_val = substr($_val, 1, -1);
    }
    $_env[$_key] = $_val;
}
unset($_envFile, $_line, $_pos, $_key, $_val);

// Helper function to define constant if not exists
function _def(string $name, $value): void {
    if (!defined($name)) {
        define($name, $value);
    }
}

// 1. Establish DB Server Constants
$dbHost = $_env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $_env['DB_PORT'] ?? '3306';
$dbName = $_env['DB_NAME'] ?? 'wcc';
$dbUser = $_env['DB_USER'] ?? 'root';
$dbPass = $_env['DB_PASS'] ?? '';

_def('DB_HOST', $dbHost);
_def('DB_PORT', $dbPort);
_def('DB_NAME', $dbName);
_def('DB_USER', $dbUser);
_def('DB_PASS', $dbPass);

// 2. Fetch Active Tournament Details dynamically from DB
$activeTournament = null;
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $localPdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5 // Fast fail timeout
    ]);
    
    $stmt = $localPdo->query("SELECT * FROM tournaments WHERE is_active = 1 LIMIT 1");
    $activeTournament = $stmt->fetch() ?: null;
    unset($localPdo, $stmt, $dsn);
} catch (Throwable $e) {
    error_log('[config.php] Database lookup for active tournament failed: ' . $e->getMessage());
}

// 3. Define Constants (DB values first, fallback to .env values)
_def('ACTIVE_TOURNAMENT_ID', (int)($activeTournament['id'] ?? 1));
_def('EVENT_NAME', $activeTournament['name'] ?? $_env['EVENT_NAME'] ?? 'Golf Tournament');
_def('EVENT_DATE', $activeTournament['date'] ?? $_env['EVENT_DATE'] ?? 'TBA');
_def('EVENT_VENUE', $activeTournament['venue'] ?? $_env['EVENT_VENUE'] ?? 'TBA');
_def('EVENT_FORMAT', $activeTournament['format'] ?? $_env['EVENT_FORMAT'] ?? 'TBA');
_def('EVENT_DEADLINE', $activeTournament['deadline'] ?? $_env['EVENT_DEADLINE'] ?? 'TBA');
_def('EVENT_FEE', (float)($activeTournament['fee'] ?? $_env['EVENT_FEE'] ?? 2000));
_def('EVENT_CURRENCY', $activeTournament['currency'] ?? $_env['EVENT_CURRENCY'] ?? 'BDT');
_def('CONTACT_PHONE_1', $activeTournament['contact_phone_1'] ?? $_env['CONTACT_PHONE_1'] ?? '');
_def('CONTACT_PHONE_2', $activeTournament['contact_phone_2'] ?? $_env['CONTACT_PHONE_2'] ?? '');

// SSL Commerz Settings
_def('SSL_STORE_ID', $_env['SSL_STORE_ID'] ?? '');
_def('SSL_STORE_PASSWORD', $_env['SSL_STORE_PASSWORD'] ?? '');
$_isSandbox = filter_var($_env['SSL_IS_SANDBOX'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
_def('SSL_IS_SANDBOX', $_isSandbox);

// App settings
_def('APP_BASE_URL', rtrim($_env['APP_BASE_URL'] ?? 'http://localhost:8000', '/'));

// Admin Settings
_def('ADMIN_USER', $_env['ADMIN_USER'] ?? 'helloadmin');
_def('ADMIN_PASS', $_env['ADMIN_PASS'] ?? 'g0Lf1shC0mp');

// SMS Settings
_def('SMS_API_URL', $_env['SMS_API_URL'] ?? '');
_def('SMS_API_TOKEN', $_env['SMS_API_TOKEN'] ?? '');
_def('SMS_MESSAGE_TEMPLATE', $_env['SMS_MESSAGE_TEMPLATE'] ?? '');

unset($_env, $_isSandbox, $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $activeTournament);

// Return config array for SSLCommerz compatibility
return [
    'success_url' => 'payment/success.php',
    'failed_url'  => 'payment/fail.php',
    'cancel_url'  => 'payment/cancel.php',
    'ipn_url'     => 'payment/ipn.php',

    'projectPath' => APP_BASE_URL,
    'apiDomain'   => SSL_IS_SANDBOX
        ? 'https://sandbox.sslcommerz.com'
        : 'https://securepay.sslcommerz.com',

    'apiCredentials' => [
        'store_id'       => SSL_STORE_ID,
        'store_password' => SSL_STORE_PASSWORD,
    ],

    'apiUrl' => [
        'make_payment'   => '/gwprocess/v4/api.php',
        'order_validate' => '/validator/api/validationserverAPI.php',
    ],

    'connect_from_localhost' => SSL_IS_SANDBOX,
    'verify_hash' => true,
];
