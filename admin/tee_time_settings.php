<?php
/**
 * Admin Golfer Tee Time Settings
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

$allTournaments = [];
$selectedTournamentId = 1;

try {
    if (isset($pdo)) {
        $allTournaments = $pdo->query("SELECT id, name, is_active FROM tournaments ORDER BY id DESC")->fetchAll();
    }
} catch (Throwable $e) {
    error_log('[admin] Failed to load tournaments: ' . $e->getMessage());
}

$selectedTournamentId = (int)($_GET['tournament_id'] ?? 0);
if ($selectedTournamentId <= 0) {
    $selectedTournamentId = (int)ACTIVE_TOURNAMENT_ID;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $reporting = trim((string)($_POST['reporting_time'] ?? ''));
        $groupPhoto = trim((string)($_POST['group_photo_time'] ?? ''));
        $teeOff = trim((string)($_POST['tee_off_time'] ?? ''));
        $slotNumber = (int)($_POST['slot_number'] ?? 36);
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($title === '' || $reporting === '' || $groupPhoto === '' || $teeOff === '') {
            $errors[] = 'All tee-time fields are required.';
        }

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "INSERT INTO tee_time_options (tournament_id, title, reporting_time, group_photo_time, tee_off_time, slot_number, display_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                )->execute([$selectedTournamentId, $title, $reporting, $groupPhoto, $teeOff, $slotNumber, $displayOrder]);
                $success = 'Tee time created successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to create tee time: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $reporting = trim((string)($_POST['reporting_time'] ?? ''));
        $groupPhoto = trim((string)($_POST['group_photo_time'] ?? ''));
        $teeOff = trim((string)($_POST['tee_off_time'] ?? ''));
        $slotNumber = (int)($_POST['slot_number'] ?? 0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($id < 1 || $title === '' || $reporting === '' || $groupPhoto === '' || $teeOff === '') {
            $errors[] = 'All fields are required for update.';
        }

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "UPDATE tee_time_options 
                     SET title = ?, reporting_time = ?, group_photo_time = ?, tee_off_time = ?, slot_number = ?, display_order = ? 
                     WHERE id = ?"
                )->execute([$title, $reporting, $groupPhoto, $teeOff, $slotNumber, $displayOrder, $id]);
                $success = 'Tee time updated successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("UPDATE tee_time_options SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
                $success = 'Tee time status toggled successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to toggle status: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Check if any golfer registrations are using this tee time
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations WHERE schedule_group = ? AND tournament_id = ? AND payment_status = 'paid'");
                $stmt->execute([(string)$id, $selectedTournamentId]);
                $count = (int)$stmt->fetch()['cnt'];

                if ($count > 0) {
                    $errors[] = 'Cannot delete this tee time as it contains active paid registrations.';
                } else {
                    $pdo->prepare("DELETE FROM tee_time_options WHERE id = ?")->execute([$id]);
                    $success = 'Tee time option deleted successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete: ' . $e->getMessage();
            }
        }
    }
}

// Fetch active lists
$teeList = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, title, reporting_time, group_photo_time, tee_off_time, slot_number, display_order, is_active 
         FROM tee_time_options 
         WHERE tournament_id = ?
         ORDER BY display_order DESC, id ASC"
    );
    $stmt->execute([$selectedTournamentId]);
    $teeList = $stmt->fetchAll();
    
    // Get golfer counts per group
    $counts = [];
    $countStmt = $pdo->prepare("SELECT schedule_group, COUNT(*) as cnt FROM registrations WHERE tournament_id = ? AND payment_status = 'paid' GROUP BY schedule_group");
    $countStmt->execute([$selectedTournamentId]);
    $countRows = $countStmt->fetchAll();
    foreach ($countRows as $row) {
        $counts[(string)$row['schedule_group']] = (int)$row['cnt'];
    }
} catch (Throwable $e) {
    $errors[] = 'Could not load tee time list.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Tee Time Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background: #f8fafc;
      font-size: 0.875rem;
    }
    .page-header {
      background: #144e58; color: #fff;
      padding: 1.25rem 1.5rem;
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 4px solid #c9a84c;
    }
    .panel-card {
      background: #fff; border-radius: 0.85rem;
      box-shadow: 0 4px 20px rgba(13, 54, 64, 0.05);
      padding: 1.5rem; margin-top: 1.5rem;
    }
    .btn-submit {
      background: #144e58; color: #fff; border: none;
      border-radius: 50px; padding: 0.5rem 1.5rem; font-weight: 600;
    }
    .btn-submit:hover { background: #0d3840; color: #fff; }
  </style>
</head>
<body>

<div class="page-header">
  <h5 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>Golfer Tee Times</h5>
  <div class="d-flex gap-2">
    <a href="tournaments.php" class="btn btn-sm btn-outline-light"><i class="bi bi-trophy me-1"></i>Tournaments</a>
    <a href="view_registration.php?tournament_id=<?= $selectedTournamentId ?>" class="btn btn-sm btn-outline-light">
      <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
  </div>
</div>

<div class="container py-4">

  <?php if ($success !== ''): ?>
    <div class="alert alert-success py-2 d-flex align-items-center gap-2 small">
      <i class="bi bi-check-circle-fill"></i> <?= esc($success) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small">
      <?php foreach ($errors as $e): ?>
        <div><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= esc($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
    <div></div>
    <?php if (!empty($allTournaments)): ?>
      <div class="d-flex align-items-center gap-2">
        <label for="tournamentFilter" class="fw-semibold text-muted mb-0 small">Filter Tournament:</label>
        <select id="tournamentFilter" class="form-select form-select-sm" style="width:auto" onchange="location.href='tee_time_settings.php?tournament_id=' + this.value;">
          <?php foreach ($allTournaments as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $selectedTournamentId === (int)$t['id'] ? 'selected' : '' ?>>
              <?= esc($t['name']) ?> <?= (int)$t['is_active'] === 1 ? ' (Active)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
  </div>

  <div class="row">
    <!-- Form create/edit -->
    <div class="col-lg-4">
      <div class="panel-card">
        <h5 class="fw-bold mb-3" id="formHeader">Create Tee Time</h5>
        <form id="teeForm" method="POST">
          <input type="hidden" name="action" id="actionField" value="create" />
          <input type="hidden" name="id" id="idField" value="" />

          <div class="mb-2">
            <label for="title" class="form-label fw-semibold mb-1">Title</label>
            <input type="text" class="form-control form-control-sm" name="title" id="title" required placeholder="e.g. Shotgun-1 (Early)" />
          </div>
          
          <div class="mb-2">
            <label for="reporting_time" class="form-label fw-semibold mb-1">Reporting Time</label>
            <input type="text" class="form-control form-control-sm" name="reporting_time" id="reporting_time" required placeholder="e.g. 07:00 AM" />
          </div>

          <div class="mb-2">
            <label for="group_photo_time" class="form-label fw-semibold mb-1">Group Photo Time</label>
            <input type="text" class="form-control form-control-sm" name="group_photo_time" id="group_photo_time" required placeholder="e.g. 07:15 AM" />
          </div>

          <div class="mb-2">
            <label for="tee_off_time" class="form-label fw-semibold mb-1">Tee Off Time</label>
            <input type="text" class="form-control form-control-sm" name="tee_off_time" id="tee_off_time" required placeholder="e.g. 07:30 AM" />
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label for="slot_number" class="form-label fw-semibold mb-1">Slot Count</label>
              <input type="number" class="form-control form-control-sm" name="slot_number" id="slot_number" required min="1" max="500" value="36" />
            </div>
            <div class="col-6">
              <label for="display_order" class="form-label fw-semibold mb-1">Display Order</label>
              <input type="number" class="form-control form-control-sm" name="display_order" id="display_order" required value="0" />
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-submit btn-sm w-100" id="submitBtn">
              Create Option
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cancelEditBtn">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table List -->
    <div class="col-lg-8">
      <div class="panel-card">
        <h5 class="fw-bold mb-3">Tee Time List</h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Title</th>
                <th>Reporting</th>
                <th>Tee Off</th>
                <th>Slots Allowed</th>
                <th>Registered (Paid)</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($teeList)): ?>
                <tr><td colspan="7" class="text-center text-muted">No tee time configurations found.</td></tr>
              <?php else: ?>
                <?php foreach ($teeList as $opt): 
                  $used = $counts[(string)$opt['id']] ?? 0; ?>
                  <tr>
                    <td><strong><?= esc($opt['title']) ?></strong></td>
                    <td><?= esc($opt['reporting_time']) ?></td>
                    <td><?= esc($opt['tee_off_time']) ?></td>
                    <td><?= esc($opt['slot_number']) ?></td>
                    <td><strong><?= $used ?></strong></td>
                    <td>
                      <span class="badge <?= $opt['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $opt['is_active'] ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <button class="btn btn-xs btn-outline-primary edit-btn" data-detail='<?= json_encode($opt, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>
                          Edit
                        </button>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="action" value="toggle" />
                          <input type="hidden" name="id" value="<?= $opt['id'] ?>" />
                          <button type="submit" class="btn btn-xs btn-outline-warning">
                            Toggle
                          </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this option?');">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id" value="<?= $opt['id'] ?>" />
                          <button type="submit" class="btn btn-xs btn-outline-danger">
                            Delete
                          </button>
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
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(function() {
    $('.edit-btn').on('click', function() {
      const d = $(this).data('detail');
      if (!d) return;

      $('#formHeader').text('Edit Tee Time');
      $('#actionField').value = 'update';
      $('#actionField').val('update');
      $('#idField').val(d.id);
      
      $('#title').val(d.title);
      $('#reporting_time').val(d.reporting_time);
      $('#group_photo_time').val(d.group_photo_time);
      $('#tee_off_time').val(d.tee_off_time);
      $('#slot_number').val(d.slot_number);
      $('#display_order').val(d.display_order);

      $('#submitBtn').text('Update Option');
      $('#cancelEditBtn').removeClass('d-none');
    });

    $('#cancelEditBtn').on('click', function() {
      $('#formHeader').text('Create Tee Time');
      $('#actionField').val('create');
      $('#idField').val('');
      
      $('#teeForm')[0].reset();
      
      $('#submitBtn').text('Create Option');
      $(this).addClass('d-none');
    });
  });
</script>

</body>
</html>
