<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/RegistrationRepository.php';
require_once __DIR__ . '/src/ScheduleService.php';

$uid = trim((string)($_GET['uid'] ?? ''));
$registration = null;
$type = '';

if ($uid !== '') {
    try {
        $pdo = db();
        $repo = new RegistrationRepository($pdo);
        $schedule = new ScheduleService($pdo);
        $row = $repo->findByUniqueId($uid);

        if ($row && ($row['payment_status'] ?? '') === 'paid') {
            $registration = $row;
            $type = (string)$row['registration_type'];
            $details = $schedule->resolveScheduleDetails($type, $row);
            if ($details) {
                if ($type === 'golfer') {
                    $registration['schedule_details'] = $details['title'] . ' (Reporting: ' . $details['reporting_time'] . ' | Tee Off: ' . $details['tee_off_time'] . ')';
                } else {
                    $registration['schedule_details'] = $details['title'] . ' (' . $details['reporting_time'] . ')';
                }
            } else {
                $registration['schedule_details'] = $type === 'golfer' ? 'Selected Tee Off Group' : 'Selected Arrival Window';
            }
        }
    } catch (Throwable $e) {
        error_log('[success.php] DB lookup error: ' . $e->getMessage());
    }
}

$pageTitle = 'Registration Confirmed';
require_once __DIR__ . '/templates/header.php';
?>

<style>
  @media print {
    body { background: #fff !important; }
    .page-header, footer, .btn-print-group { display: none !important; }
    .receipt-card { box-shadow: none !important; border: 1px solid #ddd !important; margin: 0 !important; padding: 1rem !important; }
    .receipt-details__row { grid-template-columns: minmax(7.5rem, 38%) 1fr !important; }
    .receipt-details__value { text-align: right !important; }
  }
</style>

<section class="hero hero-solid">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-1">Registration Successful</h1>
    <p class="mb-0 opacity-75" style="font-size: 0.9rem;">Thank you for registering for the tournament.</p>
  </div>
</section>

<div class="container success-page">
  <div class="premium-card p-4 p-md-5 mx-auto receipt-card">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
        <i class="bi bi-patch-check-fill text-success" style="font-size: 3rem;"></i>
      </div>
      <h3 class="fw-bold mb-1 text-dark">Confirmed</h3>
      <p class="text-muted small mb-0">Your payment has been successfully verified.</p>
    </div>

    <?php if ($registration): ?>
      <dl class="receipt-details">
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Registrant Name</dt>
          <dd class="receipt-details__value"><?= esc($registration['full_name']) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Category</dt>
          <dd class="receipt-details__value">
            <span class="badge bg-dark"><?= $type === 'golfer' ? 'Golfer' : 'Non-Golfer (Guest)' ?></span>
          </dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Email</dt>
          <dd class="receipt-details__value"><?= esc($registration['email']) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Mobile Contact</dt>
          <dd class="receipt-details__value"><?= esc($registration['contact']) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Organization</dt>
          <dd class="receipt-details__value"><?= esc($registration['organization'] ?? 'N/A') ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Schedule Time</dt>
          <dd class="receipt-details__value"><?= esc($registration['schedule_details']) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Transaction ID</dt>
          <dd class="receipt-details__value receipt-details__value--gold"><?= esc($registration['tran_id']) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Amount Paid</dt>
          <dd class="receipt-details__value"><?= esc($registration['currency']) ?> <?= number_format((float)$registration['amount'], 2) ?></dd>
        </div>
        <div class="receipt-details__row">
          <dt class="receipt-details__label">Registration ID</dt>
          <dd class="receipt-details__value receipt-details__value--muted"><?= esc($registration['unique_id']) ?></dd>
        </div>
      </dl>
      <div class="d-flex gap-2 justify-content-center btn-print-group">
        <button onclick="window.print()" class="btn btn-gold px-4"><i class="bi bi-printer"></i> Print Receipt</button>
        <a href="<?= esc(APP_BASE_URL) ?>" class="btn btn-outline-green px-4"><i class="bi bi-house-door"></i> Back to Home</a>
      </div>
    <?php else: ?>
      <div class="alert alert-warning text-center my-3">
        <i class="bi bi-exclamation-circle-fill me-2"></i>
        Unable to load registration receipt details. If you completed payment, check your confirmation SMS or contact support.
      </div>
      <div class="text-center btn-print-group mt-4">
        <a href="<?= esc(APP_BASE_URL) ?>" class="btn btn-gold px-4"><i class="bi bi-house-door"></i> Return to Homepage</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
