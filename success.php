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
  }
</style>

<section class="hero py-4 page-header">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-1">Registration Successful</h1>
    <p class="mb-0 opacity-75" style="font-size: 0.9rem;">Thank you for registering for the tournament.</p>
  </div>
</section>

<div class="container my-5">
  <div class="premium-card p-4 p-md-5 mx-auto receipt-card" style="max-width: 680px; border-top: 5px solid var(--gold);">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
        <i class="bi bi-patch-check-fill text-success" style="font-size: 3rem;"></i>
      </div>
      <h3 class="fw-bold mb-1 text-dark">Confirmed</h3>
      <p class="text-muted small">Your payment has been successfully verified.</p>
    </div>

    <?php if ($registration): ?>
      <div class="table-responsive">
        <table class="table table-borderless align-middle mb-4">
          <tbody>
            <tr class="border-bottom">
              <td class="text-muted py-2">Registrant Name</td>
              <td class="fw-bold text-end py-2"><?= esc($registration['full_name']) ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Category</td>
              <td class="fw-bold text-end py-2">
                <span class="badge bg-dark"><?= $type === 'golfer' ? 'Golfer' : 'Non-Golfer (Guest)' ?></span>
              </td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Email</td>
              <td class="fw-bold text-end py-2"><?= esc($registration['email']) ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Mobile Contact</td>
              <td class="fw-bold text-end py-2"><?= esc($registration['contact']) ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Organization</td>
              <td class="fw-bold text-end py-2"><?= esc($registration['organization'] ?? 'N/A') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Schedule Time</td>
              <td class="fw-bold text-end py-2 text-wrap" style="max-width: 250px;"><?= esc($registration['schedule_details']) ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Transaction ID</td>
              <td class="fw-bold text-end py-2 text-gold"><?= esc($registration['tran_id']) ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Amount Paid</td>
              <td class="fw-bold text-end py-2"><?= esc($registration['currency']) ?> <?= number_format((float)$registration['amount'], 2) ?></td>
            </tr>
            <tr>
              <td class="text-muted py-2">Registration ID</td>
              <td class="fw-bold text-end py-2 text-secondary small"><?= esc($registration['unique_id']) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
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
