<?php
/**
 * Admin Registrations Dashboard
 */

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./index.php');
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusBadge(string $status): string {
    return match ($status) {
        'paid'      => '<span class="badge badge-paid">Paid</span>',
        'pending'   => '<span class="badge badge-pending">Pending</span>',
        'failed'    => '<span class="badge badge-failed">Failed</span>',
        'cancelled' => '<span class="badge badge-cancelled">Cancelled</span>',
        default     => '<span class="badge bg-light text-dark border">' . esc($status) . '</span>',
    };
}

function exportHeaders(): array {
    return [
        'SL', 'Registration Type', 'Unique ID', 'Transaction ID', 'Full Name', 
        'Designation', 'Organization', 'Nationality', 'Gender', 'Contact', 'Email', 
        'Mailing Address', 'Category', 'Reference Name', 'Reference Mission', 
        'Reference Contact', 'T-Shirt Size', 'Schedule/Window Key', 'Schedule/Window Title', 
        'Home Club', 'Handicap', 'Putting Contest Interest', 'Profile Photo', 'Name on Polo', 'Golf Set Brand', 'Payment Status', 'Amount', 
        'Currency', 'SSL Val ID', 'Submitted At', 'Paid At'
    ];
}

function exportRow(array $r, int $serial, array $labels): array {
    $type = (string)($r['registration_type'] ?? '');
    $tourId = (int)($r['tournament_id'] ?? 1);
    $groupId = (string)($r['schedule_group'] ?? '');
    $key = "{$type}_{$tourId}_{$groupId}";
    $label = $labels[$key] ?? $groupId;
    return [
        $serial, $r['registration_type'] ?? '', $r['unique_id'] ?? '', $r['tran_id'] ?? '',
        $r['full_name'] ?? '', $r['designation'] ?? '', $r['organization'] ?? '', $r['nationality'] ?? '', $r['gender'] ?? '',
        $r['contact'] ?? '', $r['email'] ?? '', $r['mailing_address'] ?? '', $r['player_category'] ?? '',
        $r['reference_name'] ?? '', $r['reference_mission'] ?? '', $r['reference_contact'] ?? '',
        $r['tshirt_size'] ?? '', $groupId, $label, $r['home_club'] ?? '', $r['handicap'] ?? '', $r['putting_contest_interest'] ?? '',
        $r['profile_photo'] ?? '', $r['name_on_polo'] ?? '', $r['golf_set_brand'] ?? '',
        $r['payment_status'] ?? '', $r['amount'] ?? '', $r['currency'] ?? 'BDT',
        $r['val_id'] ?? '', $r['submitted_at'] ?? '', $r['paid_at'] ?? ''
    ];
}

function rowMatchesFilters(array $r, string $statusFilter, string $typeFilter, string $searchFilter, array $labels): bool {
    $status = strtolower((string)($r['payment_status'] ?? ''));
    $type = strtolower((string)($r['registration_type'] ?? ''));
    if ($statusFilter !== '' && $status !== $statusFilter) return false;
    if ($typeFilter !== '' && $type !== $typeFilter) return false;
    if ($searchFilter === '') return true;
    
    $groupId = (string)($r['schedule_group'] ?? '');
    $tourId = (int)($r['tournament_id'] ?? 1);
    $key = "{$type}_{$tourId}_{$groupId}";
    $label = (string)($labels[$key] ?? $groupId);
    $hay = strtolower(implode(' ', [
        $r['full_name'] ?? '', $r['organization'] ?? '', $r['email'] ?? '', 
        $r['contact'] ?? '', $label, $r['player_category'] ?? '', $r['registration_type'] ?? ''
    ]));
    return strpos($hay, $searchFilter) !== false;
}

$registrations = [];
$scheduleLabels = [];

try {
    $pdo = db();
    
    // 1. Load tee_time_options (golfers + active guests)
    $rows = $pdo->query("SELECT id, tournament_id, title, tee_off_time FROM tee_time_options ORDER BY display_order DESC, id DESC")->fetchAll();
    foreach ($rows as $row) {
        $tourId = (int)$row['tournament_id'];
        $optId = (string)$row['id'];
        
        $scheduleLabels["golfer_{$tourId}_{$optId}"] = trim((string)$row['title']) . ' · ' . trim((string)$row['tee_off_time']);
        
        if ($tourId === (int)ACTIVE_TOURNAMENT_ID) {
            $scheduleLabels["non_golfer_{$tourId}_{$optId}"] = trim((string)$row['title']) . ' · ' . trim((string)$row['tee_off_time']);
        }
    }
    
    // 2. Load arrival_window_options_non_golfer (legacy fallback)
    $winRows = $pdo->query("SELECT id, tournament_id, title, window_time FROM arrival_window_options_non_golfer ORDER BY display_order DESC, id ASC")->fetchAll();
    foreach ($winRows as $w) {
        $tourId = (int)$w['tournament_id'];
        $optId = (string)$w['id'];
        
        $key = "non_golfer_{$tourId}_{$optId}";
        if (!isset($scheduleLabels[$key])) {
            $scheduleLabels[$key] = trim((string)$w['title']) . ' · ' . trim((string)$w['window_time']);
        }
    }
} catch (Throwable $e) {
    error_log('[admin] Failed to load schedule labels: ' . $e->getMessage());
}

$allTournaments = [];
$selectedTournamentId = 1;

try {
    $pdo = db();
    $allTournaments = $pdo->query("SELECT id, name, is_active FROM tournaments ORDER BY id DESC")->fetchAll();
} catch (Throwable $e) {
    error_log('[admin] Failed to load tournaments: ' . $e->getMessage());
}

$selectedTournamentId = (int)($_GET['tournament_id'] ?? 0);
if ($selectedTournamentId <= 0) {
    $selectedTournamentId = (int)ACTIVE_TOURNAMENT_ID;
}

try {
    $pdo = db();
    
    // Load Golfers for selected tournament
    $gStmt = $pdo->prepare(
        "SELECT id, tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, profile_photo, name_on_polo, golf_set_brand, contact, email, mailing_address,
                handicap, tshirt_size, home_club, schedule_group, player_category, reference_name, reference_mission,
                reference_contact, payment_status, amount, currency, val_id, ssl_session_key, submitted_at, paid_at, 
                'golfer' AS registration_type, '' AS putting_contest_interest
         FROM registrations
         WHERE tournament_id = ?"
    );
    $gStmt->execute([$selectedTournamentId]);
    $g = $gStmt->fetchAll();
    
    // Load Non-Golfers for selected tournament
    $ngStmt = $pdo->prepare(
        "SELECT id, tournament_id, unique_id, tran_id, full_name, designation, organization, nationality, gender, profile_photo, name_on_polo, '' AS golf_set_brand, contact, email, mailing_address,
                '' AS handicap, tshirt_size, '' AS home_club, arrival_window AS schedule_group, player_category, reference_name, reference_mission,
                reference_contact, payment_status, amount, currency, val_id, ssl_session_key, submitted_at, paid_at, 
                'non_golfer' AS registration_type, putting_contest_interest
         FROM registrations_non_golfer
         WHERE tournament_id = ?"
    );
    $ngStmt->execute([$selectedTournamentId]);
    $ng = $ngStmt->fetchAll();
    
    $registrations = array_merge($g, $ng);
    usort($registrations, static fn($a, $b) => strcmp((string)($b['submitted_at'] ?? ''), (string)($a['submitted_at'] ?? '')));
} catch (Throwable $e) {
    error_log('[admin] MySQL retrieval failed: ' . $e->getMessage());
}

// Handle exports
$exportType = (string)($_GET['export'] ?? '');
if ($exportType === 'csv') {
    $statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
    $typeFilter = strtolower(trim((string)($_GET['type'] ?? '')));
    $searchFilter = strtolower(trim((string)($_GET['search'] ?? '')));
    
    if (!in_array($statusFilter, ['paid', 'pending', 'failed', 'cancelled'], true)) $statusFilter = '';
    if (!in_array($typeFilter, ['golfer', 'non_golfer'], true)) $typeFilter = '';
    
    $filtered = [];
    foreach ($registrations as $r) {
        if (rowMatchesFilters($r, $statusFilter, $typeFilter, $searchFilter, $scheduleLabels)) {
            $filtered[] = $r;
        }
    }

    $headers = exportHeaders();
    $stamp = date('Ymd_His');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations_' . $stamp . '.csv"');
    $out = fopen('php://output', 'w');
    if ($out === false) exit;
    fputcsv($out, $headers);
    foreach ($filtered as $i => $r) {
        fputcsv($out, exportRow($r, $i + 1, $scheduleLabels));
    }
    fclose($out);
    exit;
}

$total = count($registrations);
$paid = count(array_filter($registrations, fn($r) => (($r['payment_status'] ?? '') === 'paid')));
$pending = count(array_filter($registrations, fn($r) => (($r['payment_status'] ?? '') === 'pending')));
$failed = count(array_filter($registrations, fn($r) => in_array(($r['payment_status'] ?? ''), ['failed', 'cancelled'], true)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard — Registrations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;550;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background: #f8fafc;
      font-size: 0.875rem;
    }
    .page-header {
      background: #144e58;
      color: #fff;
      padding: 1.25rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.75rem;
      border-bottom: 4px solid #c9a84c;
    }
    .table-card {
      background: #fff;
      border-radius: 0.85rem;
      box-shadow: 0 4px 25px rgba(13, 54, 64, 0.05);
      overflow: hidden;
      margin-top: 1.5rem;
    }
    .table-toolbar {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
      align-items: center;
    }
    .table thead th {
      background: #144e58;
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
    .table tbody tr {
      cursor: pointer;
      transition: background 0.15s;
    }
    .table tbody tr:hover {
      background: #f1f5f9;
    }
    .badge {
      font-size: 0.73rem;
      padding: 0.3em 0.65em;
      border-radius: 999px;
      font-weight: 500;
    }
    .badge-paid { background: #d1fae5; color: #065f46; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-failed { background: #fee2e2; color: #991b1b; }
    .badge-cancelled { background: #f1f5f9; color: #475569; }
    .badge-type {
      font-size: 0.72rem;
      padding: 0.2rem 0.5rem;
      border-radius: 999px;
      font-weight: 550;
    }
    .type-golfer { background: #dbeafe; color: #1e3a8a; }
    .type-non { background: #ecfdf5; color: #166534; }
    .btn-sms {
      background: #144e58;
      color: #fff;
      border: none;
      border-radius: 999px;
      font-size: 0.75rem;
      padding: 0.3rem 0.85rem;
      white-space: nowrap;
      transition: background 0.15s;
    }
    .btn-sms:hover {
      background: #0d3840;
      color: #fff;
    }
    #photoLightbox {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(0, 0, 0, 0.85);
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      cursor: zoom-out;
    }
    #photoLightbox.is-open { display: flex; }
    #photoLightbox img {
      max-width: min(90vw, 900px);
      max-height: 90vh;
      object-fit: contain;
      border-radius: 0.5rem;
      box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.4);
      cursor: default;
    }
    #photoLightbox .photo-lightbox-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      z-index: 1;
    }
  </style>
</head>
<body>

<div class="page-header">
  <div class="d-flex align-items-center gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard2-data me-2 text-warning"></i><?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?> Portal</h5>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="tournaments.php" class="btn btn-sm btn-outline-light"><i class="bi bi-trophy me-1"></i>Tournaments</a>
    <a href="tee_time_settings.php" class="btn btn-sm btn-outline-light"><i class="bi bi-calendar3 me-1"></i>Tee Times</a>
    <a href="non_golfer_window_settings.php" class="btn btn-sm btn-outline-light"><i class="bi bi-clock me-1"></i>Arrival Windows</a>
    <a href="settings.php" class="btn btn-sm btn-outline-light"><i class="bi bi-gear me-1"></i>Capacity</a>
    <a href="admin_logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
  </div>
</div>

<div class="container-fluid py-4 px-md-4">
  <!-- Status Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="p-3 rounded text-white shadow-sm" style="background:#144e58">
        <small class="opacity-75 d-block text-uppercase font-size-xs fw-semibold">Total Submissions</small>
        <div class="h2 mb-0 fw-bold"><?= $total ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 rounded text-white bg-success shadow-sm">
        <small class="opacity-75 d-block text-uppercase font-size-xs fw-semibold">Paid Signups</small>
        <div class="h2 mb-0 fw-bold"><?= $paid ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 rounded text-white bg-warning shadow-sm" style="color: #3f3f46 !important;">
        <small class="opacity-75 d-block text-uppercase font-size-xs fw-semibold">Pending Checkout</small>
        <div class="h2 mb-0 fw-bold"><?= $pending ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="p-3 rounded text-white bg-danger shadow-sm">
        <small class="opacity-75 d-block text-uppercase font-size-xs fw-semibold">Failed / Cancelled</small>
        <div class="h2 mb-0 fw-bold"><?= $failed ?></div>
      </div>
    </div>
  </div>

  <div class="table-card">
    <div class="table-toolbar">
      <input type="text" id="searchInput" class="form-control" style="max-width:240px" placeholder="Search name, email, org..." />
      
      <?php if (!empty($allTournaments)): ?>
        <select id="tournamentFilter" class="form-select" style="width:auto" onchange="location.href='view_registration.php?tournament_id=' + this.value;">
          <?php foreach ($allTournaments as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $selectedTournamentId === (int)$t['id'] ? 'selected' : '' ?>>
              <?= esc($t['name']) ?> <?= (int)$t['is_active'] === 1 ? ' (Active)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <select id="statusFilter" class="form-select" style="width:auto">
        <option value="">All statuses</option>
        <option value="paid">Paid</option>
        <option value="pending">Pending</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select id="typeFilter" class="form-select" style="width:auto">
        <option value="">All types</option>
        <option value="golfer">Golfer</option>
        <option value="non_golfer">Non-Golfer</option>
      </select>
      <a href="view_registration.php?export=csv&tournament_id=<?= $selectedTournamentId ?>" class="btn btn-sm btn-outline-primary" id="exportCsvBtn"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
      
      <span class="text-muted ms-auto fw-semibold" id="rowCount"></span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0" id="regTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Name</th>
            <th>Organization</th>
            <th>Category</th>
            <th>Contact</th>
            <th>Email</th>
            <th>T-Shirt</th>
            <th>Schedule/Window</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Submitted</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="regBody">
        <?php foreach ($registrations as $i => $r): 
            $detail = json_encode($r, JSON_HEX_QUOT | JSON_HEX_APOS); ?>
            <tr data-status="<?= esc($r['payment_status']) ?>" data-type="<?= esc($r['registration_type']) ?>" data-detail='<?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?>'>
              <td><?= $i + 1 ?></td>
              <td><?= ($r['registration_type'] ?? '') === 'non_golfer' ? '<span class="badge-type type-non">Non-Golfer</span>' : '<span class="badge-type type-golfer">Golfer</span>' ?></td>
              <td><strong><?= esc($r['full_name']) ?></strong></td>
              <td><?= esc($r['organization']) ?></td>
              <td><?= esc($r['player_category']) ?></td>
              <td><?= esc($r['contact']) ?></td>
              <td><?= esc($r['email']) ?></td>
              <td><?= esc($r['tshirt_size']) ?></td>
              <td><?= esc($scheduleLabels[($r['registration_type'] ?? '') . '_' . ($r['tournament_id'] ?? 1) . '_' . ($r['schedule_group'] ?? '')] ?? $r['schedule_group']) ?></td>
              <td><?= statusBadge((string)$r['payment_status']) ?></td>
              <td><?= $r['amount'] !== '' ? 'BDT ' . number_format((float)$r['amount'], 0) : '—' ?></td>
              <td><?= esc(substr((string)$r['submitted_at'], 0, 16)) ?></td>
              <td onclick="event.stopPropagation()">
                <?php if (($r['payment_status'] ?? '') === 'paid'): ?>
                  <button class="btn-sms send-btn" data-contact="<?= esc($r['contact']) ?>" data-name="<?= esc($r['full_name']) ?>" data-uid="<?= esc($r['unique_id']) ?>" data-type="<?= esc($r['registration_type']) ?>"><i class="bi bi-chat-dots-fill"></i> SMS</button>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Full-size profile photo lightbox (above Bootstrap modals) -->
<div id="photoLightbox" role="dialog" aria-modal="true" aria-label="Profile photo preview">
  <button type="button" class="btn-close btn-close-white photo-lightbox-close" aria-label="Close"></button>
  <img id="photoLightboxImg" src="" alt="Profile photo">
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 rounded-4">
      <div class="modal-header bg-dark text-white p-3 border-0">
        <h5 class="modal-title fw-bold" id="modalTitle">Registration Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="modalBody">
        <!-- Rendered dynamically -->
      </div>
      <div class="modal-footer border-0 p-3 bg-light d-flex justify-content-between">
        <div id="deleteControls">
          <button type="button" class="btn btn-sm btn-outline-danger" id="deleteBtn"><i class="bi bi-trash"></i> Delete Record</button>
        </div>
        <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function () {
  let activeRow = null;
  let activeUid = null;
  let activeType = null;
  const $deleteControls = $('#deleteControls');

  function row(label, value) {
    if (!value) return '';
    return '<div class="mb-2"><small class="text-muted d-block text-uppercase fw-semibold" style="font-size:0.68rem; letter-spacing:0.04em;">'+label+'</small><strong>'+value+'</strong></div>';
  }

  function statusBadgeHtml(s) {
    const map = {
      paid: ['badge-paid', 'Paid'],
      pending: ['badge-pending', 'Pending'],
      failed: ['badge-failed', 'Failed'],
      cancelled: ['badge-cancelled', 'Cancelled']
    };
    const m = map[s] || ['bg-light text-dark border', s];
    return '<span class="badge '+m[0]+'">'+m[1]+'</span>';
  }

  function resetDeleteBtn() {
    $deleteControls.html('<button type="button" class="btn btn-sm btn-outline-danger" id="deleteBtn"><i class="bi bi-trash"></i> Delete Record</button>');
    $('#deleteBtn').on('click', onDeleteClick);
  }

  function onDeleteClick() {
    $deleteControls.html('<span class="text-danger fw-semibold me-2 small"><i class="bi bi-exclamation-triangle"></i> Delete permanently?</span><button class="btn btn-sm btn-danger me-1" id="confirmDeleteBtn">Delete</button><button class="btn btn-sm btn-outline-secondary" id="cancelDeleteBtn">Cancel</button>');
    $('#cancelDeleteBtn').on('click', resetDeleteBtn);
    $('#confirmDeleteBtn').on('click', function () {
      const $btn = $(this).prop('disabled', true).text('Deleting...');
      $.post('delete_registration.php', { unique_id: activeUid, registration_type: activeType })
        .done(function (res) {
          if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
            if (activeRow) activeRow.remove();
            applyFilters();
          } else {
            alert('Error: ' + (res.message || 'Could not delete record.'));
            resetDeleteBtn();
          }
        })
        .fail(function () {
          alert('Server connection error.');
          resetDeleteBtn();
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  }

  function applyFilters() {
    const search = ($('#searchInput').val() || '').toLowerCase();
    const status = $('#statusFilter').val() || '';
    const type = $('#typeFilter').val() || '';
    
    $('#regBody tr').each(function () {
      const txt = $(this).text().toLowerCase();
      const st = $(this).data('status');
      const ty = $(this).data('type');
      const ok = (search === '' || txt.includes(search)) && (status === '' || st === status) && (type === '' || ty === type);
      $(this).toggleClass('d-none', !ok);
    });
    $('#rowCount').text($('#regBody tr:not(.d-none)').length + ' registrations');
  }

  function updateExportLinks() {
    const qs = new URLSearchParams();
    qs.set('tournament_id', '<?= $selectedTournamentId ?>');
    const s = ($('#statusFilter').val() || '').toString();
    const t = ($('#typeFilter').val() || '').toString();
    const q = ($('#searchInput').val() || '').toString().trim();
    
    if (s) qs.set('status', s);
    if (t) qs.set('type', t);
    if (q) qs.set('search', q);
    
    const suffix = '&' + qs.toString();
    $('#exportCsvBtn').attr('href', 'view_registration.php?export=csv' + suffix);
  }

  $('#searchInput, #statusFilter, #typeFilter').on('input change', function () {
    applyFilters();
    updateExportLinks();
  });

  const $photoLightbox = $('#photoLightbox');
  const $photoLightboxImg = $('#photoLightboxImg');

  function openPhotoLightbox(src) {
    if ($photoLightbox.hasClass('is-open')) return;
    $photoLightboxImg.attr('src', src);
    $photoLightbox.addClass('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closePhotoLightbox() {
    $photoLightbox.removeClass('is-open');
    $photoLightboxImg.attr('src', '');
    if (!document.querySelector('.modal.show')) {
      document.body.style.overflow = '';
    }
  }

  $('#modalBody').on('click', '.profile-photo-thumb', function (e) {
    e.preventDefault();
    e.stopPropagation();
    openPhotoLightbox($(this).data('full'));
  });

  $photoLightbox.on('click', function (e) {
    if (e.target === this || $(e.target).hasClass('photo-lightbox-close')) {
      closePhotoLightbox();
    }
  });

  $photoLightboxImg.on('click', function (e) {
    e.stopPropagation();
  });

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && $photoLightbox.hasClass('is-open')) {
      e.stopPropagation();
      closePhotoLightbox();
    }
  });

  document.getElementById('detailModal').addEventListener('hidden.bs.modal', function () {
    closePhotoLightbox();
    resetDeleteBtn();
  });

  $('#regBody').on('click', 'tr', function () {
    const d = $(this).data('detail');
    if (!d) return;
    
    activeRow = $(this);
    activeUid = d.unique_id || null;
    activeType = d.registration_type || null;
    resetDeleteBtn();
    
    const scheduleLabels = <?= json_encode($scheduleLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const typeLabel = d.registration_type === 'non_golfer' ? 'Non-Golfer' : 'Golfer';
    const labelKey = d.registration_type + '_' + (d.tournament_id || 1) + '_' + d.schedule_group;
    
    const eventSpecific = d.registration_type === 'non_golfer'
      ? row('Arrival Window', scheduleLabels[labelKey] || d.schedule_group) + row('Guest Putting Contest Interest', d.putting_contest_interest) + row('Name on Polo', d.name_on_polo)
      : row('Tee Time Schedule', scheduleLabels[labelKey] || d.schedule_group) + row('Handicap', d.handicap) + row('Golf Set Brand', d.golf_set_brand) + row('Name on Polo', d.name_on_polo);
      
    const sponsorSection = d.player_category === 'Non-Diplomats'
      ? '<hr><div class="p-3 bg-light rounded"><h6 class="fw-bold mb-2">Diplomatic Sponsor</h6><div class="row"><div class="col-md-4">'+row('Sponsor Name', d.reference_name)+'</div><div class="col-md-4">'+row('Sponsor Mission', d.reference_mission)+'</div><div class="col-md-4">'+row('Sponsor Contact', d.reference_contact)+'</div></div></div>'
      : '';

    const photoHtml = d.profile_photo 
      ? '<div class="text-center mb-4"><img src="../' + d.profile_photo + '" class="img-thumbnail rounded-circle shadow-sm profile-photo-thumb" style="width: 120px; height: 120px; object-fit: cover; cursor: zoom-in;" alt="Profile Picture" title="Click to view full size" data-full="../' + d.profile_photo + '"></div>'
      : '';

    $('#modalTitle').text((d.full_name || 'Registration') + ' (' + typeLabel + ')');
    $('#modalBody').html(
      photoHtml +
      '<div class="row"><div class="col-md-6">' +
      row('Registration Type', typeLabel) + row('Full Name', d.full_name) + row('Designation', d.designation) + row('Organization', d.organization) + row('Nationality', d.nationality) + row('Gender', d.gender) + row('Category', d.player_category) +
      '</div><div class="col-md-6">' +
      row('Contact Phone', d.contact) + row('Email', d.email) + row('T-Shirt Size', d.tshirt_size) + row('Mailing Address', d.mailing_address) + eventSpecific +
      '</div></div>' + sponsorSection + '<hr>' +
      '<div class="row"><div class="col-md-6">' + 
      row('Payment Method', 'SSLCommerz') + row('Payment Status', statusBadgeHtml(d.payment_status)) + row('Amount', d.amount ? ('BDT ' + parseInt(d.amount).toLocaleString()) : '') + 
      '</div><div class="col-md-6">' + 
      row('Tran ID', d.tran_id) + row('SSL Val ID', d.val_id) + row('Submitted At', d.submitted_at) + row('Paid At', d.paid_at) + 
      '</div></div>'
    );
    new bootstrap.Modal(document.getElementById('detailModal')).show();
  });

  $(document).on('click', '.send-btn', function (e) {
    e.stopPropagation();
    const $btn = $(this).prop('disabled', true);
    $.post('send_sms.php', { 
      contact: $btn.data('contact'), 
      name: $btn.data('name'),
      uid: $btn.data('uid'),
      type: $btn.data('type')
    })
    .done(function (res) {
      alert('SMS dispatch response: ' + res);
    })
    .fail(function () {
      alert('Failed to contact SMS dispatcher.');
    })
    .always(function () {
      $btn.prop('disabled', false);
    });
  });

  applyFilters();
  updateExportLinks();
})();
</script>
</body>
</html>
