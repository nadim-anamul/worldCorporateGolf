<?php
/**
 * Global Header Template
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

// Safe fallbacks for SEO
$title = isset($pageTitle) ? $pageTitle . ' — ' . EVENT_NAME : EVENT_NAME . ' — Register Online';
$description = isset($metaDescription) ? $metaDescription : 'Official registration portal for the ' . EVENT_NAME . '. Secure your slot now.';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <!-- Primary SEO Meta Tags -->
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="canonical" href="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>" />
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image" content="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/brand.png', ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="twitter:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="twitter:image" content="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/brand.png', ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">

  <!-- Frameworks & CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="<?= htmlspecialchars(APP_BASE_URL . '/assets/css/style.css', ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet" />
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/brand.png', ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body>
