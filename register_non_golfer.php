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
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/ScheduleService.php';

$windowOptions = [];

try {
    $schedule = new ScheduleService(db());
    $windowOptions = $schedule->getNonGolferWindowOptions(ACTIVE_TOURNAMENT_ID);
} catch (Throwable $e) {
    error_log('[register_non_golfer.php] Failed to load DB window options: ' . $e->getMessage());
}

// Fallback window options if DB fails
if (empty($windowOptions)) {
    $windowOptions = [
        [
            'id'          => 'window1',
            'title'       => 'Window-1',
            'reporting'   => '8:00 AM - 10:30 AM',
            'group_photo' => '09:45 AM',
            'tee_off'     => '8:00 AM - 10:30 AM',
            'slots_left'  => 30
        ],
        [
            'id'          => 'window2',
            'title'       => 'Window-2',
            'reporting'   => '10:00 AM - 12:00 PM',
            'group_photo' => '09:45 AM',
            'tee_off'     => '10:00 AM - 12:00 PM',
            'slots_left'  => 30
        ]
    ];
}

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
<div class="container">
  <div class="form-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-gold me-2"></i>Guest Details</h4>
      <a href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-house-door"></i> Home
      </a>
    </div>

    <!-- Early Bird Announcement Alert -->
    <?php if (IS_EARLY_BIRD_ACTIVE): ?>
      <div class="alert p-3 mb-4 early-bird-form-alert border-0 shadow-sm">
        <i class="bi bi-gift-fill text-warning fs-3 flex-shrink-0"></i>
        <div class="early-bird-form-alert__body">
          <strong class="text-warning-emphasis d-block mb-1" style="font-size: 1.05rem;"><i class="bi bi-stars"></i> Early Bird Discount Active!</strong>
          <span class="text-muted d-block small mb-2">You get a special discount rate of <strong><?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(CURRENT_FEE) ?></strong> (instead of the standard <?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(EVENT_FEE) ?>).</span>
          <span class="d-inline-flex align-items-center gap-2 badge bg-dark text-warning font-monospace py-1.5 px-3 rounded-pill early-bird-form-alert__countdown">
            <i class="bi bi-clock"></i> Ends in: <span id="earlyBirdFormCountdown">00d 00h 00m 00s</span>
          </span>
        </div>
      </div>
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
            <label for="puttingContest" class="form-label">Interested in Guest Putting Contest? <span class="text-danger">*</span></label>
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

      <!-- Arrival Window Preference -->
      <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-clock-fill text-gold me-2"></i>Preferred Arrival Window <span class="text-danger">*</span></h5>
      
      <div class="row g-3 mb-4">
        <?php foreach ($windowOptions as $opt): ?>
          <div class="col-md-6">
            <input type="radio" name="arrivalWindow" id="window_<?= $opt['id'] ?>" class="slot-option-input d-none" value="<?= htmlspecialchars($opt['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $opt['slots_left'] <= 0 ? 'disabled' : '' ?> />
            <label for="window_<?= $opt['id'] ?>" class="w-100 h-100">
              <div class="slot-option-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($opt['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                  <span class="badge-slots">
                    <?= $opt['slots_left'] > 0 ? $opt['slots_left'] . ' slots left' : 'SOLD OUT' ?>
                  </span>
                </div>
                <div class="text-muted" style="font-size: 0.8rem; line-height: 1.5;">
                  <div><i class="bi bi-clock"></i> Reporting: <strong><?= htmlspecialchars($opt['reporting'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                  <div><i class="bi bi-camera"></i> Photos: <strong><?= htmlspecialchars($opt['group_photo'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                  <div><i class="bi bi-flag"></i> Tee Off: <strong><?= htmlspecialchars($opt['tee_off'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Submit Button -->
      <div class="d-grid mt-4">
        <button type="button" id="submitBtn" class="btn btn-complete-registration btn-lg py-3">
          <i class="bi bi-lock-fill"></i> Complete Registration (<?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(CURRENT_FEE) ?>)
        </button>
      </div>

    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<script src="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/registration.js"></script>
<script>
  RegistrationForm.init({
    scheduleSelector: '[name="arrivalWindow"]:checked',
    scheduleError: 'Please select your preferred arrival window.',
    extendPayload: function (payload) {
      payload.puttingContestInterest = document.getElementById('puttingContest').value;
    }
  });
</script>

<?php if (IS_EARLY_BIRD_ACTIVE && EARLY_BIRD_DEADLINE): ?>
<script>
  (function () {
    const deadline = new Date("<?= date('c', strtotime(EARLY_BIRD_DEADLINE)) ?>").getTime();
    const $timer = document.getElementById('earlyBirdFormCountdown');
    if (!$timer) return;

    function update() {
      const now = new Date().getTime();
      const diff = deadline - now;

      if (diff <= 0) {
        $timer.textContent = "Expired";
        setTimeout(() => location.reload(), 2000);
        return;
      }

      const d = Math.floor(diff / (1000 * 60 * 60 * 24));
      const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const s = Math.floor((diff % (1000 * 60)) / 1000);

      const pad = (num) => String(num).padStart(2, '0');
      $timer.textContent = `${pad(d)}d ${pad(h)}h ${pad(m)}m ${pad(s)}s`;
    }

    update();
    setInterval(update, 1000);
  })();
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
