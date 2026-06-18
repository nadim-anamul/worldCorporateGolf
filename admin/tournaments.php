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

$errors = [];
$success = '';

function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        $deadline = trim((string)($_POST['deadline'] ?? ''));
        $phone1 = trim((string)($_POST['contact_phone_1'] ?? ''));
        $phone2 = trim((string)($_POST['contact_phone_2'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        // Fetch early bird configurations
        $ebFeeRaw = trim((string)($_POST['early_bird_fee'] ?? ''));
        $ebFee = ($ebFeeRaw !== '') ? (float)$ebFeeRaw : null;
        $ebDeadlineRaw = trim((string)($_POST['early_bird_deadline'] ?? ''));
        $ebDeadline = null;
        if ($ebDeadlineRaw !== '') {
            $ebDeadline = date('Y-m-d H:i:s', strtotime($ebDeadlineRaw));
        }

        if ($name === '' || $date === '' || $venue === '' || $format === '' || $deadline === '') {
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
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO tournaments (name, date, venue, format, fee, early_bird_fee, currency, deadline, early_bird_deadline, contact_phone_1, contact_phone_2, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                    );
                    $stmt->execute([$name, $date, $venue, $format, $fee, $ebFee, $currency, $deadline, $ebDeadline, $phone1 ?: null, $phone2 ?: null]);
                    $success = 'Tournament created successfully.';
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE tournaments 
                         SET name = ?, date = ?, venue = ?, format = ?, fee = ?, early_bird_fee = ?, currency = ?, deadline = ?, early_bird_deadline = ?, contact_phone_1 = ?, contact_phone_2 = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$name, $date, $venue, $format, $fee, $ebFee, $currency, $deadline, $ebDeadline, $phone1 ?: null, $phone2 ?: null, $id]);
                    $success = 'Tournament updated successfully.';
                }
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
                      <span class="d-block text-muted small"><i class="bi bi-hourglass-split me-1"></i>Deadline: <?= esc($t['deadline']) ?></span>
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
          <form method="POST">
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
              <input type="text" class="form-control form-control-sm" id="deadline" name="deadline" required placeholder="e.g. Wednesday, 29 April 2026" value="<?= esc($editTournament['deadline'] ?? '') ?>" />
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
</body>
</html>
