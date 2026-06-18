<?php
/**
 * User Confirmation & Receipt Printing Page
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$uid = trim((string)($_GET['uid'] ?? ''));
$registration = null;
$type = '';

if ($uid !== '') {
    try {
        $pdo = db();
        
        // Try golfer table
        $stmt = $pdo->prepare(
            'SELECT * FROM registrations WHERE unique_id = ? AND payment_status = \'paid\' LIMIT 1'
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        
        if ($row) {
            $registration = $row;
            $type = 'golfer';
            
            // Get tee time details
            $teeStmt = $pdo->prepare('SELECT title, reporting_time, tee_off_time FROM tee_time_options WHERE id = ?');
            $teeStmt->execute([(int)$row['schedule_group']]);
            $tee = $teeStmt->fetch();
            $registration['schedule_details'] = $tee 
                ? $tee['title'] . ' (Reporting: ' . $tee['reporting_time'] . ' | Tee Off: ' . $tee['tee_off_time'] . ')'
                : 'Selected Tee Off Group';
        } else {
            // Try non-golfer table
            $stmt2 = $pdo->prepare(
                'SELECT * FROM registrations_non_golfer WHERE unique_id = ? AND payment_status = \'paid\' LIMIT 1'
            );
            $stmt2->execute([$uid]);
            $row2 = $stmt2->fetch();
            
            if ($row2) {
                $registration = $row2;
                $type = 'non_golfer';
                
                // Get arrival window details
                $winStmt = $pdo->prepare('SELECT title, window_time FROM arrival_window_options_non_golfer WHERE id = ?');
                $winStmt->execute([$row2['arrival_window']]);
                $win = $winStmt->fetch();
                $registration['schedule_details'] = $win 
                    ? $win['title'] . ' (' . $win['window_time'] . ')'
                    : 'Selected Arrival Window';
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

<!-- Hero Header -->
<section class="hero py-4 page-header">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-1">Registration Successful</h1>
    <p class="mb-0 opacity-75" style="font-size: 0.9rem;">Thank you for registering for the tournament.</p>
  </div>
</section>

<!-- Receipt Container -->
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
              <td class="fw-bold text-end py-2"><?= htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Category</td>
              <td class="fw-bold text-end py-2">
                <span class="badge bg-dark">
                  <?= $type === 'golfer' ? 'Golfer' : 'Non-Golfer (Guest)' ?>
                </span>
              </td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Email</td>
              <td class="fw-bold text-end py-2"><?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Mobile Contact</td>
              <td class="fw-bold text-end py-2"><?= htmlspecialchars($registration['contact'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Organization</td>
              <td class="fw-bold text-end py-2"><?= htmlspecialchars($registration['organization'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Schedule Time</td>
              <td class="fw-bold text-end py-2 text-wrap" style="max-width: 250px;">
                <?= htmlspecialchars($registration['schedule_details'], ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Transaction ID</td>
              <td class="fw-bold text-end py-2 text-gold"><?= htmlspecialchars($registration['tran_id'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr class="border-bottom">
              <td class="text-muted py-2">Amount Paid</td>
              <td class="fw-bold text-end py-2">
                <?= htmlspecialchars($registration['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$registration['amount'], 2) ?>
              </td>
            </tr>
            <tr>
              <td class="text-muted py-2">Registration ID</td>
              <td class="fw-bold text-end py-2 text-secondary small"><?= htmlspecialchars($registration['unique_id'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex gap-2 justify-content-center btn-print-group">
        <button onclick="window.print()" class="btn btn-gold px-4">
          <i class="bi bi-printer"></i> Print Receipt
        </button>
        <a href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-green px-4">
          <i class="bi bi-house-door"></i> Back to Home
        </a>
      </div>

    <?php else: ?>
      <div class="alert alert-warning text-center my-3">
        <i class="bi bi-exclamation-circle-fill me-2"></i>
        Unable to load registration receipt details dynamically. However, your payment has been received successfully.
      </div>
      <div class="text-center btn-print-group mt-4">
        <a href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-gold px-4">
          <i class="bi bi-house-door"></i> Return to Homepage
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
