<?php
/**
 * Non-Golfer Registration Form
 */

declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Non-Golfer Registration';
require_once __DIR__ . '/templates/header.php';
?>

<!-- Hero Header -->
<section class="hero hero-solid py-4">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-1">Non-Golfer Registration</h1>
    <p class="mb-0 opacity-75" style="font-size: 0.9rem;">Join the event as an esteemed guest.</p>
  </div>
</section>

<!-- Form Container -->
<div class="container container--reg">
  <div class="form-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-gold me-2"></i>Guest Details</h4>
      <a href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-house-door"></i> Home
      </a>
    </div>

    <!-- Early Bird -->
    <?php if (IS_EARLY_BIRD_ACTIVE): ?>
      <?php
        $promoVariant = 'form';
        $countdownId = 'earlyBirdFormCountdown';
        require __DIR__ . '/templates/_early_bird_promo.php';
      ?>
    <?php endif; ?>

    <!-- Error Box -->
    <div id="errorBox" class="alert alert-danger" style="display: none;"></div>

    <form id="regForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" id="registration_type" value="non_golfer" />

      <?php require_once __DIR__ . '/templates/registration/_participant_fields.php'; ?>

      <!-- Guest Preferences -->
      <div class="form-section-card">
        <h6 class="form-section-title"><i class="bi bi-gift-fill text-gold"></i> Guest Preferences</h6>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="tshirtSize" class="form-label">T-Shirt Size <span class="text-danger">*</span></label>
            <select class="form-select" id="tshirtSize" required>
              <option value="" disabled selected>Select Size</option>
              <option value="M">M</option>
              <option value="L">L</option>
              <option value="XL">XL</option>
              <option value="2XL">2XL</option>
              <option value="3XL">3XL</option>
              <option value="Oversize">Custom / Oversize</option>
            </select>
            <div id="customTshirtContainer" class="mt-2" style="display: none;">
              <input type="text" class="form-control" id="customTshirtSize" placeholder="Enter custom width & length (e.g. 25x32)" />
            </div>
          </div>
          <div class="col-md-6">
            <label for="puttingContest" class="form-label">Putting Contest? <span class="text-danger">*</span></label>
            <select class="form-select" id="puttingContest" required>
              <option value="Yes" selected>Yes</option>
              <option value="No">No</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <label for="nameOnPolo" class="form-label">Name on Polo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nameOnPolo" required placeholder="Name to be printed on Polo" />
          </div>
        </div>
      </div>

      <?php require __DIR__ . '/templates/registration/_submit_button.php'; ?>

    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<script src="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/registration.js"></script>
<script>
  RegistrationForm.init({
    skipSchedule: true,
    extendPayload: function (payload) {
      payload.puttingContestInterest = document.getElementById('puttingContest').value;
    }
  });
</script>

<?php if (IS_EARLY_BIRD_ACTIVE && EARLY_BIRD_DEADLINE): ?>
<script>
  (function () {
    const deadline = new Date("<?= date('c', strtotime(EARLY_BIRD_DEADLINE)) ?>").getTime();
    const timers = document.querySelectorAll('[data-early-bird-countdown]');
    if (!timers.length) return;

    function update() {
      const now = new Date().getTime();
      const diff = deadline - now;

      if (diff <= 0) {
        timers.forEach((timer) => { timer.textContent = 'Expired'; });
        setTimeout(() => location.reload(), 2000);
        return;
      }

      const d = Math.floor(diff / (1000 * 60 * 60 * 24));
      const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const s = Math.floor((diff % (1000 * 60)) / 1000);
      const pad = (num) => String(num).padStart(2, '0');
      const label = `${pad(d)}d ${pad(h)}h ${pad(m)}m ${pad(s)}s`;
      timers.forEach((timer) => { timer.textContent = label; });
    }

    update();
    setInterval(update, 1000);
  })();
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
