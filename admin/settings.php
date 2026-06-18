<?php
/**
 * Admin Capacity Settings Page
 */

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./index.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxSlots = (int)($_POST['max_slots'] ?? 0);

    if ($maxSlots < 1 || $maxSlots > 1000) {
        $errors[] = 'Max Slots must be between 1 and 1000.';
    }

    if (empty($errors)) {
        try {
            db()->prepare(
                "INSERT INTO app_settings (setting_key, setting_value)
                 VALUES ('max_slots', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([$maxSlots]);
            $success = true;
        } catch (Throwable $e) {
            error_log('[settings] Save capacity failed: ' . $e->getMessage());
            $errors[] = 'Database error. Could not save settings.';
        }
    }
}

$currentMaxSlots = 72; // default
try {
    $row = db()->query("SELECT setting_value FROM app_settings WHERE setting_key = 'max_slots' LIMIT 1")->fetch();
    if ($row) {
        $currentMaxSlots = (int)$row['setting_value'];
    }
} catch (Throwable $e) {
    $errors[] = 'Could not load capacity limit from database.';
}

function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Capacity Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary:      #144e58;
      --primary-dark: #0d3840;
      --primary-light:#e8f4f6;
      --gold:         #c9a84c;
    }
    body {
      font-family: 'Outfit', sans-serif;
      background: var(--primary-light);
      font-size: 0.9rem;
    }
    .page-header {
      background: var(--primary);
      color: #fff;
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 4px solid var(--gold);
    }
    .settings-card {
      max-width: 500px;
      margin: 3rem auto;
      background: #fff;
      border-radius: 1rem;
      box-shadow: 0 4px 20px rgba(13, 54, 64, 0.08);
      border-top: 5px solid var(--gold);
      padding: 2.5rem 2rem;
    }
    .btn-save {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 50px;
      padding: 0.6rem 2rem;
      font-weight: 600;
      transition: background 0.2s;
    }
    .btn-save:hover {
      background: var(--primary-dark);
      color: #fff;
    }
  </style>
</head>
<body>

<div class="page-header">
  <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Portal Settings</h5>
  <a href="view_registration.php" class="btn btn-sm btn-outline-light">
    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
  </a>
</div>

<div class="container">
  <div class="settings-card">
    <h5 class="fw-bold mb-3" style="color: var(--primary);"><i class="bi bi-people me-2"></i>Registration Capacity</h5>
    
    <?php if ($success): ?>
      <div class="alert alert-success py-2 d-flex align-items-center gap-2 small">
        <i class="bi bi-check-circle-fill"></i> Capacity settings saved successfully.
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e): ?>
          <div><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= esc($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-4">
        <label for="max_slots" class="form-label fw-semibold">Maximum Slots Limit <span class="text-danger">*</span></label>
        <input type="number" class="form-control" id="max_slots" name="max_slots" min="1" max="1000" required value="<?= esc($currentMaxSlots) ?>" />
        <div class="text-muted small mt-2">
          This limits the aggregate capacity across all tee time sessions. Current value: <strong><?= esc($currentMaxSlots) ?></strong>.
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-save">
          <i class="bi bi-floppy"></i> Save Capacity
        </button>
        <a href="view_registration.php" class="btn btn-outline-secondary rounded-pill px-4 align-self-center">Cancel</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
