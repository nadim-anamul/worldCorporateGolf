<?php
/**
 * Admin Non-Golfer Arrival Window Settings
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
        $id = strtolower(trim((string)($_POST['id'] ?? '')));
        $title = trim((string)($_POST['title'] ?? ''));
        $windowTime = trim((string)($_POST['window_time'] ?? ''));
        $groupPhoto = trim((string)($_POST['group_photo_time'] ?? ''));
        $slotNumber = (int)($_POST['slot_number'] ?? 30);
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($id === '' || $title === '' || $windowTime === '' || $groupPhoto === '') {
            $errors[] = 'All arrival window fields are required.';
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            $errors[] = 'ID must contain only alphanumeric characters, dashes, and underscores.';
        }

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "INSERT INTO arrival_window_options_non_golfer (id, tournament_id, title, window_time, group_photo_time, slot_number, display_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
                )->execute([$id, $selectedTournamentId, $title, $windowTime, $groupPhoto, $slotNumber, $displayOrder]);
                $success = 'Arrival window created successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to create: ID might already exist. details: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update') {
        $id = trim((string)($_POST['id'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $windowTime = trim((string)($_POST['window_time'] ?? ''));
        $groupPhoto = trim((string)($_POST['group_photo_time'] ?? ''));
        $slotNumber = (int)($_POST['slot_number'] ?? 0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($id === '' || $title === '' || $windowTime === '' || $groupPhoto === '') {
            $errors[] = 'All fields are required for update.';
        }

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "UPDATE arrival_window_options_non_golfer 
                     SET title = ?, window_time = ?, group_photo_time = ?, slot_number = ?, display_order = ? 
                     WHERE id = ?"
                )->execute([$title, $windowTime, $groupPhoto, $slotNumber, $displayOrder, $id]);
                $success = 'Arrival window updated successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id !== '') {
            try {
                $pdo->prepare("UPDATE arrival_window_options_non_golfer SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
                $success = 'Arrival window status toggled successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to toggle status: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id !== '') {
            try {
                // Check if any guest registrations are using this arrival window
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM registrations_non_golfer WHERE arrival_window = ? AND tournament_id = ? AND payment_status = 'paid'");
                $stmt->execute([$id, $selectedTournamentId]);
                $count = (int)$stmt->fetch()['cnt'];

                if ($count > 0) {
                    $errors[] = 'Cannot delete this window as it contains active paid registrations.';
                } else {
                    $pdo->prepare("DELETE FROM arrival_window_options_non_golfer WHERE id = ?")->execute([$id]);
                    $success = 'Arrival window option deleted successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete: ' . $e->getMessage();
            }
        }
    }
}

// Fetch active lists
$windowList = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, title, window_time, group_photo_time, slot_number, display_order, is_active 
         FROM arrival_window_options_non_golfer 
         WHERE tournament_id = ?
         ORDER BY display_order DESC, id ASC"
    );
    $stmt->execute([$selectedTournamentId]);
    $windowList = $stmt->fetchAll();
    
    // Get guest counts per window group
    $counts = [];
    $countStmt = $pdo->prepare("SELECT arrival_window, COUNT(*) as cnt FROM registrations_non_golfer WHERE tournament_id = ? AND payment_status = 'paid' GROUP BY arrival_window");
    $countStmt->execute([$selectedTournamentId]);
    $countRows = $countStmt->fetchAll();
    foreach ($countRows as $row) {
        $counts[(string)$row['arrival_window']] = (int)$row['cnt'];
    }
} catch (Throwable $e) {
    $errors[] = 'Could not load arrival window list.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Non-Golfer Windows Settings</title>
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
  <h5 class="mb-0 fw-bold"><i class="bi bi-clock me-2"></i>Non-Golfer Windows</h5>
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
        <select id="tournamentFilter" class="form-select form-select-sm" style="width:auto" onchange="location.href='non_golfer_window_settings.php?tournament_id=' + this.value;">
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
        <h5 class="fw-bold mb-3" id="formHeader">Create Arrival Window</h5>
        <form id="windowForm" method="POST">
          <input type="hidden" name="action" id="actionField" value="create" />

          <div class="mb-2" id="idFieldContainer">
            <label for="id" class="form-label fw-semibold mb-1">Window ID Key <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="id" id="id" required placeholder="e.g. window3 (unique key)" />
            <small class="text-muted" style="font-size:0.75rem;">Only alphanumeric, dashes, or underscores allowed. Cannot edit this key once created.</small>
          </div>
          
          <div class="mb-2">
            <label for="title" class="form-label fw-semibold mb-1">Title</label>
            <input type="text" class="form-control form-control-sm" name="title" id="title" required placeholder="e.g. Window-3" />
          </div>

          <div class="mb-2">
            <label for="window_time" class="form-label fw-semibold mb-1">Window Time Interval</label>
            <input type="text" class="form-control form-control-sm" name="window_time" id="window_time" required placeholder="e.g. 11:30 AM - 1:00 PM" />
          </div>

          <div class="mb-2">
            <label for="group_photo_time" class="form-label fw-semibold mb-1">Group Photo Time</label>
            <input type="text" class="form-control form-control-sm" name="group_photo_time" id="group_photo_time" required placeholder="e.g. 12:00 PM" />
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label for="slot_number" class="form-label fw-semibold mb-1">Slot Count</label>
              <input type="number" class="form-control form-control-sm" name="slot_number" id="slot_number" required min="1" max="500" value="30" />
            </div>
            <div class="col-6">
              <label for="display_order" class="form-label fw-semibold mb-1">Display Order</label>
              <input type="number" class="form-control form-control-sm" name="display_order" id="display_order" required value="0" />
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-submit btn-sm w-100" id="submitBtn">
              Create Window
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
        <h5 class="fw-bold mb-3">Arrival Windows List</h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Key ID</th>
                <th>Title</th>
                <th>Window Interval</th>
                <th>Slots Allowed</th>
                <th>Registered (Paid)</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($windowList)): ?>
                <tr><td colspan="7" class="text-center text-muted">No arrival window configurations found.</td></tr>
              <?php else: ?>
                <?php foreach ($windowList as $opt): 
                  $used = $counts[(string)$opt['id']] ?? 0; ?>
                  <tr>
                    <td><code><?= esc($opt['id']) ?></code></td>
                    <td><strong><?= esc($opt['title']) ?></strong></td>
                    <td><?= esc($opt['window_time']) ?></td>
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
                          <input type="hidden" name="id" value="<?= esc($opt['id']) ?>" />
                          <button type="submit" class="btn btn-xs btn-outline-warning">
                            Toggle
                          </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this window option?');">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id" value="<?= esc($opt['id']) ?>" />
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

      $('#formHeader').text('Edit Arrival Window');
      $('#actionField').val('update');
      
      $('#id').val(d.id).prop('readonly', true);
      
      $('#title').val(d.title);
      $('#window_time').val(d.window_time);
      $('#group_photo_time').val(d.group_photo_time);
      $('#slot_number').val(d.slot_number);
      $('#display_order').val(d.display_order);

      $('#submitBtn').text('Update Window');
      $('#cancelEditBtn').removeClass('d-none');
    });

    $('#cancelEditBtn').on('click', function() {
      $('#formHeader').text('Create Arrival Window');
      $('#actionField').val('create');
      
      $('#id').val('').prop('readonly', false);
      $('#windowForm')[0].reset();
      
      $('#submitBtn').text('Create Window');
      $(this).addClass('d-none');
    });
  });
</script>

</body>
</html>
