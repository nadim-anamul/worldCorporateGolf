<?php
/**
 * Admin Tournaments Manager
 */

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./index.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/TournamentLogoService.php';
require_once dirname(__DIR__) . '/src/TournamentHeroBackgroundService.php';

$errors = [];
$success = '';
$logoService = new TournamentLogoService();
$heroBackgroundService = new TournamentHeroBackgroundService();

function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatTournamentDeadline(mixed $deadline): string {
    if ($deadline === null || $deadline === '') {
        return '—';
    }
    $ts = strtotime((string)$deadline);
    return $ts !== false ? date('M j, Y g:i A', $ts) : esc($deadline);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    $errors[] = 'Database connection failed.';
}

$editTournament = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    
    // CSRF token validation
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $errors[] = 'Security check failed. Please try again.';
        $action = ''; // Cancel action
    }

    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        $venue = trim((string)($_POST['venue'] ?? ''));
        $format = trim((string)($_POST['format'] ?? ''));
        $fee = (float)($_POST['fee'] ?? 0.0);
        $currency = trim((string)($_POST['currency'] ?? 'BDT'));
        $deadlineRaw = trim((string)($_POST['deadline'] ?? ''));
        $phone1 = trim((string)($_POST['contact_phone_1'] ?? ''));
        $phone2 = trim((string)($_POST['contact_phone_2'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        $deadline = null;

        // Fetch early bird configurations
        $ebFeeRaw = trim((string)($_POST['early_bird_fee'] ?? ''));
        $ebFee = ($ebFeeRaw !== '') ? (float)$ebFeeRaw : null;
        $ebDeadlineRaw = trim((string)($_POST['early_bird_deadline'] ?? ''));
        $ebDeadline = null;
        if ($ebDeadlineRaw !== '') {
            $ebDeadline = date('Y-m-d H:i:s', strtotime($ebDeadlineRaw));
        }

        if ($deadlineRaw !== '') {
            $deadlineTs = strtotime($deadlineRaw);
            if ($deadlineTs === false) {
                $errors[] = 'Registration deadline must be a valid date and time.';
            } else {
                $deadline = date('Y-m-d H:i:s', $deadlineTs);
            }
        }

        if ($name === '' || $date === '' || $venue === '' || $format === '' || $deadline === null) {
            $errors[] = 'Tournament name, date, venue, format, and registration deadline are required.';
        }

        if ($fee < 0) {
            $errors[] = 'Registration fee cannot be negative.';
        }

        if ($ebFee !== null && $ebFee < 0) {
            $errors[] = 'Early bird fee cannot be negative.';
        }

        if (($ebFee !== null && $ebDeadline === null) || ($ebFee === null && $ebDeadline !== null)) {
            $errors[] = 'Both early bird fee and deadline must be set, or both left blank.';
        }

        if (empty($errors)) {
            try {
                $tournamentId = $action === 'update' ? $id : 0;
                $logoPath = null;
                $heroBackgroundPath = null;

                if ($action === 'create') {
                    $insertStmt = $pdo->prepare(
                        "INSERT INTO tournaments (name, date, venue, format, fee, early_bird_fee, currency, deadline, early_bird_deadline, contact_phone_1, contact_phone_2, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    $insertStmt->execute([$name, $date, $venue, $format, $fee, $ebFee, $currency, $deadline, $ebDeadline, $phone1 ?: null, $phone2 ?: null]);
                    $tournamentId = (int)$pdo->lastInsertId();
                } else {
                    $assetStmt = $pdo->prepare('SELECT logo_path, hero_background_path FROM tournaments WHERE id = ?');
                    $assetStmt->execute([$id]);
                    $assetRow = $assetStmt->fetch() ?: [];
                    $logoPath = $assetRow['logo_path'] ?? null;
                    $heroBackgroundPath = $assetRow['hero_background_path'] ?? null;
                }

                if ($logoService->hasUpload($_FILES['logo'] ?? [])) {
                    $newLogoPath = $logoService->saveForTournament($_FILES['logo'], $tournamentId);
                    $logoService->deleteIfExists(is_string($logoPath) ? $logoPath : null);
                    $logoPath = $newLogoPath;
                }

                if ($heroBackgroundService->hasUpload($_FILES['hero_background'] ?? [])) {
                    $newHeroBgPath = $heroBackgroundService->saveForTournament($_FILES['hero_background'], $tournamentId);
                    $heroBackgroundService->deleteIfExists(is_string($heroBackgroundPath) ? $heroBackgroundPath : null);
                    $heroBackgroundPath = $newHeroBgPath;
                }

                $updateStmt = $pdo->prepare(
                    "UPDATE tournaments
                     SET name = ?, date = ?, venue = ?, format = ?, fee = ?, early_bird_fee = ?, currency = ?, deadline = ?, early_bird_deadline = ?, contact_phone_1 = ?, contact_phone_2 = ?, logo_path = ?, hero_background_path = ?
                     WHERE id = ?"
                );
                $updateStmt->execute([
                    $name, $date, $venue, $format, $fee, $ebFee, $currency, $deadline, $ebDeadline,
                    $phone1 ?: null, $phone2 ?: null, $logoPath, $heroBackgroundPath, $tournamentId,
                ]);

                $success = $action === 'create'
                    ? 'Tournament created successfully.'
                    : 'Tournament updated successfully.';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                $errors[] = 'Database operation failed: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->query("UPDATE tournaments SET is_active = 0");
                $pdo->prepare("UPDATE tournaments SET is_active = 1 WHERE id = ?")->execute([$id]);
                $pdo->commit();
                $success = 'Tournament activated successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to activate tournament: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Check if any golfer or non-golfer registrations exist
                $gStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations WHERE tournament_id = ?");
                $gStmt->execute([$id]);
                $gCount = (int)$gStmt->fetch()['cnt'];

                $ngStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations_non_golfer WHERE tournament_id = ?");
                $ngStmt->execute([$id]);
                $ngCount = (int)$ngStmt->fetch()['cnt'];

                // Check active status
                $actStmt = $pdo->prepare("SELECT is_active FROM tournaments WHERE id = ?");
                $actStmt->execute([$id]);
                $isActive = (int)($actStmt->fetch()['is_active'] ?? 0);

                if ($gCount > 0 || $ngCount > 0) {
                    $errors[] = 'Cannot delete this tournament because it has registrations associated with it.';
                } elseif ($isActive === 1) {
                    $errors[] = 'Cannot delete the currently active tournament. Please activate another tournament first.';
                } else {
                    $pdo->prepare("DELETE FROM tournaments WHERE id = ?")->execute([$id]);
                    $success = 'Tournament deleted successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete tournament: ' . $e->getMessage();
            }
        }
    }
}

// Fetch edit target if requested
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0 && empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$editId]);
        $editTournament = $stmt->fetch();
    } catch (Throwable $e) {
        $errors[] = 'Failed to fetch tournament for editing.';
    }
}

// Load tournaments list
$tournaments = [];
$counts = [];
try {
    $tournaments = $pdo->query("SELECT * FROM tournaments ORDER BY id DESC")->fetchAll();
    
    // Get golfer + guest counts
    $gCounts = $pdo->query("SELECT tournament_id, COUNT(*) as cnt FROM registrations WHERE payment_status = 'paid' GROUP BY tournament_id")->fetchAll();
    foreach ($gCounts as $row) {
        $counts[(int)$row['tournament_id']]['golfers'] = (int)$row['cnt'];
    }

    $ngCounts = $pdo->query("SELECT tournament_id, COUNT(*) as cnt FROM registrations_non_golfer WHERE payment_status = 'paid' GROUP BY tournament_id")->fetchAll();
    foreach ($ngCounts as $row) {
        $counts[(int)$row['tournament_id']]['guests'] = (int)$row['cnt'];
    }
} catch (Throwable $e) {
    $errors[] = 'Could not load tournament lists.';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Tournaments Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;550;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary:      #144e58;
      --primary-dark: #0d3840;
      --primary-light:#e8f4f6;
      --gold:         #c9a84c;
    }
    body {
      font-family: 'Outfit', sans-serif;
      background: #f8fafc;
      font-size: 0.875rem;
    }
    .page-header {
      background: var(--primary);
      color: #fff;
      padding: 1.25rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 4px solid var(--gold);
    }
    .card-custom {
      background: #fff;
      border-radius: 0.85rem;
      box-shadow: 0 4px 25px rgba(13, 54, 64, 0.05);
      border: none;
      margin-bottom: 1.5rem;
    }
    .card-custom .card-header {
      background: #fff;
      border-bottom: 1px solid #f1f5f9;
      padding: 1.25rem 1.5rem;
    }
    .table thead th {
      background: var(--primary);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 600;
      white-space: nowrap;
      padding: 0.8rem 0.75rem;
      border: none;
    }
    .table tbody td {
      padding: 0.75rem;
      vertical-align: middle;
      border-color: #f1f5f9;
      font-size: 0.82rem;
    }
    .btn-primary-custom {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 50px;
      padding: 0.5rem 1.5rem;
      font-weight: 600;
      transition: background 0.2s;
    }
    .btn-primary-custom:hover {
      background: var(--primary-dark);
      color: #fff;
    }
    .badge-active {
      background: #d1fae5;
      color: #065f46;
      font-weight: 600;
      font-size: 0.75rem;
      padding: 0.35em 0.75em;
      border-radius: 999px;
    }
    .badge-inactive {
      background: #f1f5f9;
      color: #475569;
      font-size: 0.75rem;
      padding: 0.35em 0.75em;
      border-radius: 999px;
    }
    .logo-upload-zone {
      border: 2px dashed #cbd5e1;
      border-radius: 0.75rem;
      padding: 1rem;
      text-align: center;
      background: #f8fafc;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
    }
    .logo-upload-zone:hover,
    .logo-upload-zone.is-dragover {
      border-color: var(--gold);
      background: #fffbeb;
    }
    .logo-upload-zone__preview {
      max-height: 72px;
      max-width: 100%;
      object-fit: contain;
      margin-top: 0.5rem;
      display: none;
    }
    .logo-upload-zone__preview.is-visible {
      display: inline-block;
    }
    .logo-upload-zone__current {
      max-height: 64px;
      max-width: 100%;
      object-fit: contain;
      margin-bottom: 0.5rem;
      border-radius: 0.35rem;
      background: #fff;
      padding: 0.35rem;
      border: 1px solid #e2e8f0;
    }
    .hero-bg-upload-zone__current {
      width: 100%;
      max-height: 120px;
      object-fit: cover;
      margin-bottom: 0.5rem;
      border-radius: 0.5rem;
      border: 1px solid #e2e8f0;
    }
    .hero-bg-upload-zone__preview {
      width: 100%;
      max-height: 100px;
      object-fit: cover;
      margin-top: 0.5rem;
      border-radius: 0.45rem;
      display: none;
    }
    .hero-bg-upload-zone__preview.is-visible {
      display: block;
    }
  </style>
</head>
<body>

<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>Tournaments Manager</h5>
  </div>
  <div class="d-flex gap-2">
    <a href="view_registration.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
  </div>
</div>

<div class="container-fluid py-4 px-md-4">
  <?php if ($success !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show py-2.5 small mb-4 shadow-sm" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= esc($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.85rem;"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2.5 small mb-4 shadow-sm" role="alert">
      <?php foreach ($errors as $e): ?>
        <div><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc($e) ?></div>
      <?php endforeach; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.85rem;"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- List of Tournaments -->
    <div class="col-lg-8">
      <div class="card card-custom">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark" style="color: var(--primary) !important;"><i class="bi bi-list-stars me-2"></i>Tournaments List</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tournament Name</th>
                <th>Details</th>
                <th>Fee</th>
                <th>Registrations</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($tournaments)): ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No tournaments found. Create one on the right.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($tournaments as $t): 
                  $tid = (int)$t['id'];
                  $gCount = $counts[$tid]['golfers'] ?? 0;
                  $ngCount = $counts[$tid]['guests'] ?? 0;
                ?>
                  <tr>
                    <td><?= $tid ?></td>
                    <td>
                      <strong class="text-dark d-block"><?= esc($t['name']) ?></strong>
                      <span class="text-muted small d-block"><i class="bi bi-geo-alt me-1"></i><?= esc($t['venue']) ?></span>
                    </td>
                    <td>
                      <span class="d-block text-secondary"><i class="bi bi-calendar-event me-1"></i><?= esc($t['date']) ?></span>
                      <span class="d-block text-muted small"><i class="bi bi-hourglass-split me-1"></i>Deadline: <?= esc(formatTournamentDeadline($t['deadline'])) ?></span>
                    </td>
                    <td>
                      <strong><?= esc($t['currency']) ?> <?= number_format((float)$t['fee'], 0) ?></strong>
                      <?php if ($t['early_bird_fee'] !== null): ?>
                        <span class="d-block text-success small mt-1" style="font-size: 0.72rem; font-weight: 550;">
                          <i class="bi bi-lightning-charge-fill me-0.5"></i> Early Bird: <?= esc($t['currency']) ?> <?= number_format((float)$t['early_bird_fee'], 0) ?>
                        </span>
                        <span class="d-block text-muted small" style="font-size: 0.72rem;">
                          <i class="bi bi-clock me-0.5"></i> Ends: <?= esc(substr((string)$t['early_bird_deadline'], 0, 16)) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-primary rounded-pill text-white me-1" title="Paid Golfers">Golfers: <?= $gCount ?></span>
                      <span class="badge bg-info rounded-pill text-dark" title="Paid Guests">Guests: <?= $ngCount ?></span>
                    </td>
                    <td>
                      <?php if ((int)$t['is_active'] === 1): ?>
                        <span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                      <?php else: ?>
                        <span class="badge-inactive">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex gap-1 justify-content-end">
                        <?php if ((int)$t['is_active'] !== 1): ?>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Make this tournament the active one? This will deactivate the current active tournament.');">
                            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>" />
                            <input type="hidden" name="action" value="activate" />
                            <input type="hidden" name="id" value="<?= $tid ?>" />
                            <button type="submit" class="btn btn-sm btn-outline-success px-2 py-1" title="Set Active"><i class="bi bi-check-circle"></i> Activate</button>
                          </form>
                        <?php endif; ?>

                        <a href="tournaments.php?edit=<?= $tid ?>" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this tournament? This cannot be undone.');">
                          <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>" />
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id" value="<?= $tid ?>" />
                          <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1" title="Delete" <?= ($gCount > 0 || $ngCount > 0 || (int)$t['is_active'] === 1) ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Form Column -->
    <div class="col-lg-4">
      <div class="card card-custom">
        <div class="card-header">
          <h5 class="fw-bold mb-0 text-dark" style="color: var(--primary) !important;">
            <i class="bi <?= $editTournament ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2"></i>
            <?= $editTournament ? 'Edit Tournament' : 'Add New Tournament' ?>
          </h5>
        </div>
        <div class="card-body p-4">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>" />
            <input type="hidden" name="action" value="<?= $editTournament ? 'update' : 'create' ?>" />
            <?php if ($editTournament): ?>
              <input type="hidden" name="id" value="<?= (int)$editTournament['id'] ?>" />
            <?php endif; ?>

            <div class="mb-3">
              <label for="name" class="form-label fw-semibold">Tournament Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="name" name="name" required placeholder="e.g. 3rd GolfHouse Diplomatic Cup 2027" value="<?= esc($editTournament['name'] ?? '') ?>" />
            </div>

            <div class="mb-3">
              <label for="date" class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="date" name="date" required placeholder="e.g. Saturday, 02 May 2026" value="<?= esc($editTournament['date'] ?? '') ?>" />
            </div>

            <div class="mb-3">
              <label for="venue" class="form-label fw-semibold">Venue <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="venue" name="venue" required placeholder="e.g. Jolshiri Golf Club, Dhaka" value="<?= esc($editTournament['venue'] ?? '') ?>" />
            </div>

            <div class="mb-3">
              <label for="format" class="form-label fw-semibold">Game Format <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm" id="format" name="format" required placeholder="e.g. Best Ball Scramble (Shotgun Start)" value="<?= esc($editTournament['format'] ?? '') ?>" />
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Tournament Logo</label>
              <?php
                $currentLogoUrl = '';
                if (!empty($editTournament['logo_path'])) {
                    $currentLogoUrl = APP_BASE_URL . '/' . ltrim((string)$editTournament['logo_path'], '/');
                }
              ?>
              <?php if ($currentLogoUrl !== ''): ?>
                <div class="mb-2 text-center">
                  <img src="<?= esc($currentLogoUrl) ?>" alt="Current tournament logo" class="logo-upload-zone__current" id="currentLogoPreview" />
                  <div class="small text-muted">Current logo — upload below to replace</div>
                </div>
              <?php endif; ?>
              <label for="logo" class="logo-upload-zone d-block mb-1" id="logoUploadZone">
                <i class="bi bi-cloud-arrow-up fs-4 text-secondary d-block mb-1"></i>
                <span class="small fw-semibold text-secondary"><?= $currentLogoUrl !== '' ? 'Replace logo' : 'Upload logo' ?></span>
                <span class="d-block small text-muted mt-1">PNG or JPG, max 5MB. Wide logos work best (approx. 800×200px).</span>
                <img src="" alt="" class="logo-upload-zone__preview" id="logoPreview" />
              </label>
              <input type="file" class="form-control form-control-sm d-none" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" />
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Hero Background Image</label>
              <?php
                $currentHeroBgUrl = '';
                if (!empty($editTournament['hero_background_path'])) {
                    $currentHeroBgUrl = APP_BASE_URL . '/' . ltrim((string)$editTournament['hero_background_path'], '/');
                }
              ?>
              <?php if ($currentHeroBgUrl !== ''): ?>
                <div class="mb-2">
                  <img src="<?= esc($currentHeroBgUrl) ?>" alt="Current hero background" class="hero-bg-upload-zone__current" id="currentHeroBgPreview" />
                  <div class="small text-muted">Current hero background — upload below to replace</div>
                </div>
              <?php endif; ?>
              <label for="hero_background" class="logo-upload-zone d-block mb-1" id="heroBgUploadZone">
                <i class="bi bi-image fs-4 text-secondary d-block mb-1"></i>
                <span class="small fw-semibold text-secondary"><?= $currentHeroBgUrl !== '' ? 'Replace hero background' : 'Upload hero background' ?></span>
                <span class="d-block small text-muted mt-1">JPG or PNG, max 8MB. Landscape photos work best (approx. 1920×800px).</span>
                <img src="" alt="" class="hero-bg-upload-zone__preview" id="heroBgPreview" />
              </label>
              <input type="file" class="form-control form-control-sm d-none" id="hero_background" name="hero_background" accept="image/jpeg,image/png,image/webp" />
            </div>

            <div class="row g-2 mb-3">
              <div class="col-sm-8">
                <label for="fee" class="form-label fw-semibold">Registration Fee <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="fee" name="fee" required placeholder="2000.00" value="<?= esc($editTournament['fee'] ?? '2000.00') ?>" />
              </div>
              <div class="col-sm-4">
                <label for="currency" class="form-label fw-semibold">Currency</label>
                <input type="text" class="form-control form-control-sm" id="currency" name="currency" required placeholder="BDT" value="<?= esc($editTournament['currency'] ?? 'BDT') ?>" />
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-sm-6">
                <label for="early_bird_fee" class="form-label fw-semibold">Early Bird Fee</label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="early_bird_fee" name="early_bird_fee" placeholder="e.g. 1500.00" value="<?= esc($editTournament['early_bird_fee'] ?? '') ?>" />
              </div>
              <div class="col-sm-6">
                <label for="early_bird_deadline" class="form-label fw-semibold">Early Bird Deadline</label>
                <input type="datetime-local" class="form-control form-control-sm" id="early_bird_deadline" name="early_bird_deadline" value="<?= isset($editTournament['early_bird_deadline']) ? date('Y-m-d\TH:i', strtotime($editTournament['early_bird_deadline'])) : '' ?>" />
              </div>
            </div>

            <div class="mb-3">
              <label for="deadline" class="form-label fw-semibold">Registration Deadline <span class="text-danger">*</span></label>
              <input type="datetime-local" class="form-control form-control-sm" id="deadline" name="deadline" required value="<?= isset($editTournament['deadline']) ? date('Y-m-d\TH:i', strtotime((string)$editTournament['deadline'])) : '' ?>" />
              <div class="form-text">Used for the homepage countdown and displayed closing date.</div>
            </div>

            <div class="mb-3">
              <label for="contact_phone_1" class="form-label fw-semibold">Contact Phone 1</label>
              <input type="text" class="form-control form-control-sm" id="contact_phone_1" name="contact_phone_1" placeholder="e.g. 01610 801 081" value="<?= esc($editTournament['contact_phone_1'] ?? '') ?>" />
            </div>

            <div class="mb-3">
              <label for="contact_phone_2" class="form-label fw-semibold">Contact Phone 2</label>
              <input type="text" class="form-control form-control-sm" id="contact_phone_2" name="contact_phone_2" placeholder="e.g. 01842 324 232" value="<?= esc($editTournament['contact_phone_2'] ?? '') ?>" />
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-sm btn-primary-custom py-2">
                <i class="bi <?= $editTournament ? 'bi-save' : 'bi-plus-circle' ?> me-1"></i>
                <?= $editTournament ? 'Save Changes' : 'Create Tournament' ?>
              </button>
              <?php if ($editTournament): ?>
                <a href="tournaments.php" class="btn btn-sm btn-outline-secondary rounded-pill py-2 text-center">Cancel Edit</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const logoInput = document.getElementById('logo');
  const logoPreview = document.getElementById('logoPreview');
  const logoZone = document.getElementById('logoUploadZone');
  const currentLogo = document.getElementById('currentLogoPreview');

  if (!logoInput || !logoPreview || !logoZone) return;

  function showPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      logoPreview.src = e.target.result;
      logoPreview.classList.add('is-visible');
      if (currentLogo) currentLogo.style.opacity = '0.45';
    };
    reader.readAsDataURL(file);
  }

  logoInput.addEventListener('change', function () {
    showPreview(logoInput.files && logoInput.files[0]);
  });

  ['dragenter', 'dragover'].forEach(function (evtName) {
    logoZone.addEventListener(evtName, function (e) {
      e.preventDefault();
      logoZone.classList.add('is-dragover');
    });
  });

  ['dragleave', 'drop'].forEach(function (evtName) {
    logoZone.addEventListener(evtName, function (e) {
      e.preventDefault();
      logoZone.classList.remove('is-dragover');
    });
  });

  logoZone.addEventListener('drop', function (e) {
    const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    logoInput.files = dt.files;
    showPreview(file);
  });
})();

(function () {
  const bgInput = document.getElementById('hero_background');
  const bgPreview = document.getElementById('heroBgPreview');
  const bgZone = document.getElementById('heroBgUploadZone');
  const currentBg = document.getElementById('currentHeroBgPreview');

  if (!bgInput || !bgPreview || !bgZone) return;

  function showBgPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      bgPreview.src = e.target.result;
      bgPreview.classList.add('is-visible');
      if (currentBg) currentBg.style.opacity = '0.45';
    };
    reader.readAsDataURL(file);
  }

  bgInput.addEventListener('change', function () {
    showBgPreview(bgInput.files && bgInput.files[0]);
  });

  ['dragenter', 'dragover'].forEach(function (evtName) {
    bgZone.addEventListener(evtName, function (e) {
      e.preventDefault();
      bgZone.classList.add('is-dragover');
    });
  });

  ['dragleave', 'drop'].forEach(function (evtName) {
    bgZone.addEventListener(evtName, function (e) {
      e.preventDefault();
      bgZone.classList.remove('is-dragover');
    });
  });

  bgZone.addEventListener('drop', function (e) {
    const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    bgInput.files = dt.files;
    showBgPreview(file);
  });
})();
</script>
</body>
</html>
