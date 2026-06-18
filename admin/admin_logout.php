<?php
/**
 * Admin Logout Page
 */

declare(strict_types=1);

session_start();
$_SESSION = [];
session_destroy();

header('Location: ./index.php');
exit;
