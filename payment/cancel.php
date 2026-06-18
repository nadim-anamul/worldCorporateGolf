<?php
/**
 * Payment Cancellation Page
 */

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

$tranId = trim((string)($_POST['tran_id'] ?? $_SESSION['pending_tran_id'] ?? ''));
$regType = trim((string)($_SESSION['pending_reg_type'] ?? 'golfer'));
$targetTable = ($regType === 'non_golfer') ? 'registrations_non_golfer' : 'registrations';

if ($tranId !== '') {
    try {
        $pdo = db();
        $pdo->prepare("UPDATE {$targetTable} SET payment_status = 'cancelled' WHERE tran_id = ? AND payment_status = 'pending'")->execute([$tranId]);
    } catch (Throwable $e) {
        error_log('[payment/cancel.php] DB status update failed: ' . $e->getMessage());
    }
}

unset($_SESSION['pending_tran_id'], $_SESSION['pending_unique_id']);

$pageTitle = 'Payment Cancelled';
require_once dirname(__DIR__) . '/templates/header.php';
?>

<div class="container my-5">
  <div class="premium-card p-4 p-md-5 mx-auto text-center" style="max-width: 580px; border-top: 5px solid #eab308;">
    
    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-4" style="width: 80px; height: 80px;">
      <i class="bi bi-dash-circle-fill text-warning" style="font-size: 3rem;"></i>
    </div>
    
    <h3 class="fw-bold mb-2 text-dark">Payment Cancelled</h3>
    <p class="text-muted mb-4">
      The transaction session was cancelled by you. If you would like to complete your registration, please try again.
    </p>

    <div class="d-flex gap-2 justify-content-center">
      <a href="<?= htmlspecialchars(APP_BASE_URL . '/' . ($regType === 'non_golfer' ? 'register_non_golfer' : 'register'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-gold px-4">
        <i class="bi bi-arrow-repeat"></i> Retry Registration
      </a>
      <a href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-green px-4">
        <i class="bi bi-house-door"></i> Back to Home
      </a>
    </div>

  </div>
</div>

<?php
require_once dirname(__DIR__) . '/templates/footer.php';
?>
