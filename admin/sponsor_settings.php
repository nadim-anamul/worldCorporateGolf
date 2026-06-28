<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/SponsorLogoService.php';
require_once dirname(__DIR__) . '/src/TournamentSponsorRepository.php';

requireAdminAuth();

$errors = [];
$success = '';
$logoService = new SponsorLogoService();
$sponsorRepo = null;

try {
    $pdo = db();
    $sponsorRepo = new TournamentSponsorRepository($pdo);
} catch (Throwable $e) {
    $errors[] = 'Database connection failed.';
}

$allTournaments = [];
$selectedTournamentId = (int)ACTIVE_TOURNAMENT_ID;

try {
    if (isset($pdo)) {
        $allTournaments = $pdo->query('SELECT id, name, is_active FROM tournaments ORDER BY id DESC')->fetchAll();
    }
} catch (Throwable $e) {
    error_log('[admin] Failed to load tournaments: ' . $e->getMessage());
}

$selectedTournamentId = (int)($_GET['tournament_id'] ?? 0);
if ($selectedTournamentId <= 0) {
    $selectedTournamentId = (int)ACTIVE_TOURNAMENT_ID;
}

$adminCsrf = ensureCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sponsorRepo !== null) {
    requireAdminPostCsrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $websiteUrl = TournamentSponsorRepository::normalizeWebsiteUrl((string)($_POST['website_url'] ?? ''));
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($name === '' || $websiteUrl === null) {
            $errors[] = 'Sponsor name and a valid website URL are required.';
        }

        if (!$logoService->hasUpload($_FILES['logo'] ?? [])) {
            $errors[] = 'Please upload a sponsor logo.';
        }

        if (empty($errors)) {
            try {
                $logoPath = $logoService->saveForSponsor($_FILES['logo'], $selectedTournamentId);
                $sponsorRepo->create($selectedTournamentId, $name, $websiteUrl, $logoPath, $displayOrder);
                $success = 'Sponsor added successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to add sponsor: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $websiteUrl = TournamentSponsorRepository::normalizeWebsiteUrl((string)($_POST['website_url'] ?? ''));
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        $existing = $id > 0 ? $sponsorRepo->findById($id) : null;
        if ($existing === null || (int)$existing['tournament_id'] !== $selectedTournamentId) {
            $errors[] = 'Sponsor not found for this tournament.';
        }

        if ($name === '' || $websiteUrl === null) {
            $errors[] = 'Sponsor name and a valid website URL are required.';
        }

        if (empty($errors) && $existing !== null) {
            try {
                $logoPath = (string)$existing['logo_path'];
                if ($logoService->hasUpload($_FILES['logo'] ?? [])) {
                    $newLogoPath = $logoService->saveForSponsor($_FILES['logo'], $selectedTournamentId);
                    $logoService->deleteIfExists($logoPath);
                    $logoPath = $newLogoPath;
                }

                $sponsorRepo->update($id, $name, $websiteUrl, $logoPath, $displayOrder);
                $success = 'Sponsor updated successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to update sponsor: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $sponsorRepo->toggleActive($id);
                $success = 'Sponsor visibility updated.';
            } catch (Throwable $e) {
                $errors[] = 'Failed to toggle sponsor: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $existing = $sponsorRepo->findById($id);
                if ($existing !== null && (int)$existing['tournament_id'] === $selectedTournamentId) {
                    $logoService->deleteIfExists($existing['logo_path'] ?? null);
                    $sponsorRepo->delete($id);
                    $success = 'Sponsor deleted successfully.';
                } else {
                    $errors[] = 'Sponsor not found for this tournament.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete sponsor: ' . $e->getMessage();
            }
        }
    }
}

$sponsorList = [];
if ($sponsorRepo !== null) {
    try {
        $sponsorList = $sponsorRepo->listForTournament($selectedTournamentId);
    } catch (Throwable $e) {
        $errors[] = 'Could not load sponsor list.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Sponsor Settings</title>
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
    .sponsor-logo-preview {
      max-height: 42px;
      max-width: 120px;
      object-fit: contain;
    }
    .sponsor-logo-preview--form {
      max-height: 56px;
      max-width: 160px;
      object-fit: contain;
      margin-top: 0.5rem;
    }
  </style>
</head>
<body>

<div class="page-header">
  <h5 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2"></i>Event Partners &amp; Sponsors</h5>
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
    <p class="text-muted small mb-0">Manage partner logos and website links for the homepage marquee.</p>
    <?php if (!empty($allTournaments)): ?>
      <div class="d-flex align-items-center gap-2">
        <label for="tournamentFilter" class="fw-semibold text-muted mb-0 small">Tournament:</label>
        <select id="tournamentFilter" class="form-select form-select-sm" style="width:auto" onchange="location.href='sponsor_settings.php?tournament_id=' + this.value;">
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
    <div class="col-lg-4">
      <div class="panel-card">
        <h5 class="fw-bold mb-3" id="formHeader">Add Sponsor</h5>
        <form id="sponsorForm" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= esc($adminCsrf) ?>" />
          <input type="hidden" name="action" id="actionField" value="create" />
          <input type="hidden" name="id" id="idField" value="" />

          <div class="mb-2">
            <label for="name" class="form-label fw-semibold mb-1">Sponsor Name</label>
            <input type="text" class="form-control form-control-sm" name="name" id="name" required placeholder="e.g. GolfHouse" />
          </div>

          <div class="mb-2">
            <label for="website_url" class="form-label fw-semibold mb-1">Website URL</label>
            <input type="url" class="form-control form-control-sm" name="website_url" id="website_url" required placeholder="https://example.com" />
          </div>

          <div class="mb-2">
            <label for="logo" class="form-label fw-semibold mb-1">Logo</label>
            <input type="file" class="form-control form-control-sm" name="logo" id="logo" accept="image/jpeg,image/png,image/gif,image/webp" />
            <div class="form-text">PNG/JPG/WebP up to 3MB. Resized to max 320px.</div>
            <img id="currentLogoPreview" src="" alt="" class="sponsor-logo-preview--form d-none" />
          </div>

          <div class="mb-3">
            <label for="display_order" class="form-label fw-semibold mb-1">Display Order</label>
            <input type="number" class="form-control form-control-sm" name="display_order" id="display_order" value="0" />
            <div class="form-text">Higher numbers appear first.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-submit btn-sm w-100" id="submitBtn">Add Sponsor</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cancelEditBtn">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="panel-card">
        <h5 class="fw-bold mb-3">Sponsor List</h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Logo</th>
                <th>Name</th>
                <th>Website</th>
                <th>Order</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($sponsorList)): ?>
                <tr><td colspan="6" class="text-center text-muted">No sponsors configured for this tournament.</td></tr>
              <?php else: ?>
                <?php foreach ($sponsorList as $sponsor):
                  $logoUrl = TournamentSponsorRepository::logoPublicUrl((string)$sponsor['logo_path']);
                ?>
                  <tr>
                    <td>
                      <?php if ($logoUrl !== ''): ?>
                        <img src="<?= esc($logoUrl) ?>" alt="" class="sponsor-logo-preview" />
                      <?php endif; ?>
                    </td>
                    <td><strong><?= esc($sponsor['name']) ?></strong></td>
                    <td>
                      <a href="<?= esc($sponsor['website_url']) ?>" target="_blank" rel="noopener noreferrer" class="small">
                        <?= esc($sponsor['website_url']) ?>
                      </a>
                    </td>
                    <td><?= (int)$sponsor['display_order'] ?></td>
                    <td>
                      <span class="badge <?= (int)$sponsor['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                        <?= (int)$sponsor['is_active'] === 1 ? 'Active' : 'Hidden' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <button
                          type="button"
                          class="btn btn-xs btn-outline-primary edit-btn"
                          data-detail='<?= json_encode([
                              'id' => (int)$sponsor['id'],
                              'name' => (string)$sponsor['name'],
                              'website_url' => (string)$sponsor['website_url'],
                              'display_order' => (int)$sponsor['display_order'],
                              'logo_url' => $logoUrl,
                          ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>'
                        >
                          Edit
                        </button>
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= esc($adminCsrf) ?>" />
                          <input type="hidden" name="action" value="toggle" />
                          <input type="hidden" name="id" value="<?= (int)$sponsor['id'] ?>" />
                          <button type="submit" class="btn btn-xs btn-outline-warning">Toggle</button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this sponsor?');">
                          <input type="hidden" name="csrf_token" value="<?= esc($adminCsrf) ?>" />
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id" value="<?= (int)$sponsor['id'] ?>" />
                          <button type="submit" class="btn btn-xs btn-outline-danger">Delete</button>
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
  $(function () {
    $('.edit-btn').on('click', function () {
      const d = $(this).data('detail');
      if (!d) return;

      $('#formHeader').text('Edit Sponsor');
      $('#actionField').val('update');
      $('#idField').val(d.id);
      $('#name').val(d.name);
      $('#website_url').val(d.website_url);
      $('#display_order').val(d.display_order);
      $('#logo').prop('required', false);

      if (d.logo_url) {
        $('#currentLogoPreview').attr('src', d.logo_url).removeClass('d-none');
      } else {
        $('#currentLogoPreview').addClass('d-none').attr('src', '');
      }

      $('#submitBtn').text('Update Sponsor');
      $('#cancelEditBtn').removeClass('d-none');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    $('#cancelEditBtn').on('click', function () {
      $('#formHeader').text('Add Sponsor');
      $('#actionField').val('create');
      $('#idField').val('');
      $('#sponsorForm')[0].reset();
      $('#logo').prop('required', true);
      $('#currentLogoPreview').addClass('d-none').attr('src', '');
      $('#submitBtn').text('Add Sponsor');
      $(this).addClass('d-none');
    });
  });
</script>

</body>
</html>
