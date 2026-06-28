<?php
/**
 * Golfer Registration Form
 */

declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/ScheduleService.php';

$teeOptions = [];

try {
    $schedule = new ScheduleService(db());
    $teeOptions = $schedule->getGolferTeeOptions(ACTIVE_TOURNAMENT_ID);
} catch (Throwable $e) {
    error_log('[register.php] Failed to load DB tee options: ' . $e->getMessage());
}

// Fallback options if DB fails
if (empty($teeOptions)) {
    $teeOptions = [
        [
            'id'          => '1',
            'title'       => 'Shotgun-1 (Early)',
            'reporting'   => '07:00 AM',
            'group_photo' => '07:15 AM',
            'tee_off'     => '07:30 AM',
            'slots_left'  => 36
        ],
        [
            'id'          => '2',
            'title'       => 'Shotgun-2 (Late)',
            'reporting'   => '09:30 AM',
            'group_photo' => '09:45 AM',
            'tee_off'     => '10:00 AM',
            'slots_left'  => 36
        ]
    ];
}

$pageTitle = 'Golfer Registration';
require_once __DIR__ . '/templates/header.php';
?>

<!-- Hero Header -->
<section class="hero hero-solid">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-0">Golfer Registration</h1>
  </div>
</section>

<!-- Form Container -->
<div class="container container--reg">
  <div class="form-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-gold me-2"></i>Participant Details</h4>
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
      <input type="hidden" id="registration_type" value="golfer" />

      <?php require_once __DIR__ . '/templates/registration/_participant_fields.php'; ?>

      <!-- Golfing Credentials & Apparel -->
      <div class="form-section-card">
        <h6 class="form-section-title"><i class="bi bi-trophy-fill text-gold"></i> Golfing Credentials &amp; Apparel</h6>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="handicap" class="form-label">Handicap <span class="text-danger">*</span></label>
            <select class="form-select" id="handicap" required>
              <option value="" disabled selected>Select Handicap Range</option>
              <option value="0-12">0-12</option>
              <option value="13-18">13-18</option>
              <option value="19-24">19-24</option>
              <option value="25-above">25-above</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="golfSetBrand" class="form-label">Golf Set Brand <span class="text-danger">*</span></label>
            <select class="form-select" id="golfSetBrand" required>
              <option value="" disabled selected>Select Brand</option>
              <option value="Callaway">Callaway</option>
              <option value="TaylorMade">TaylorMade</option>
              <option value="Ping">Ping</option>
              <option value="Titleist">Titleist</option>
              <option value="Mizuno">Mizuno</option>
              <option value="Cobra">Cobra</option>
              <option value="Wilson">Wilson</option>
              <option value="Srixon">Srixon</option>
              <option value="PXG">PXG</option>
              <option value="Bridgestone">Bridgestone</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="row">
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
            <label for="nameOnPolo" class="form-label">Name on Polo <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nameOnPolo" required placeholder="Name to be printed on Polo" />
          </div>
        </div>
      </div>

      <?php
        $scheduleHeading = 'Preferred Slot';
        require __DIR__ . '/templates/registration/_schedule_section_open.php';
      ?>
        <?php foreach ($teeOptions as $opt): ?>
          <div class="col-12 col-md-6">
            <input type="radio" name="scheduleGroup" id="tee_<?= $opt['id'] ?>" class="slot-option-input d-none" value="<?= htmlspecialchars($opt['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $opt['slots_left'] <= 0 ? 'disabled' : '' ?> />
            <label for="tee_<?= $opt['id'] ?>" class="w-100 h-100">
              <div class="slot-option-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($opt['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                  <span class="badge-slots">
                    <?= $opt['slots_left'] > 0 ? $opt['slots_left'] . ' slots left' : 'SOLD OUT' ?>
                  </span>
                </div>
                <div class="slot-option-meta">
                  <div><i class="bi bi-clock"></i> Reporting: <strong><?= htmlspecialchars($opt['reporting'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                  <div><i class="bi bi-camera"></i> Photos: <strong><?= htmlspecialchars($opt['group_photo'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                  <div><i class="bi bi-flag"></i> Tee Off: <strong><?= htmlspecialchars($opt['tee_off'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
      <?php require __DIR__ . '/templates/registration/_schedule_section_close.php'; ?>

      <?php require __DIR__ . '/templates/registration/_submit_button.php'; ?>

    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<script src="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/registration.js"></script>
<script>
  RegistrationForm.init({
    scheduleSelector: '[name="scheduleGroup"]:checked',
    scheduleError: 'Please select your preferred slot.',
    extendPayload: function (payload) {
      payload.handicap = document.getElementById('handicap').value;
      payload.golfSetBrand = document.getElementById('golfSetBrand').value;
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
