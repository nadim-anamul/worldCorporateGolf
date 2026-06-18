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

$windowOptions = [];

try {
    $pdo = db();
    
    // Load active non-golfer arrival windows for current active tournament
    $stmt = $pdo->prepare(
        "SELECT id, title, window_time, group_photo_time, slot_number 
         FROM arrival_window_options_non_golfer 
         WHERE tournament_id = ? AND is_active = 1 
         ORDER BY display_order DESC, id ASC"
    );
    $stmt->execute([ACTIVE_TOURNAMENT_ID]);
    $options = $stmt->fetchAll();

    // Fetch slot counts for paid non-golfers of this tournament
    $counts = [];
    $countStmt = $pdo->prepare(
        "SELECT arrival_window, COUNT(*) as cnt 
         FROM registrations_non_golfer 
         WHERE tournament_id = ? AND payment_status = 'paid' 
         GROUP BY arrival_window"
    );
    $countStmt->execute([ACTIVE_TOURNAMENT_ID]);
    $countRows = $countStmt->fetchAll();

    foreach ($countRows as $row) {
        $counts[(string)$row['arrival_window']] = (int)$row['cnt'];
    }

    foreach ($options as $opt) {
        $id = (string)$opt['id'];
        $used = $counts[$id] ?? 0;
        $slotsLeft = max(0, (int)$opt['slot_number'] - $used);

        $windowOptions[] = [
            'id'          => $id,
            'title'       => (string)$opt['title'],
            'window'      => (string)$opt['window_time'],
            'group_photo' => (string)$opt['group_photo_time'],
            'slots_left'  => $slotsLeft
        ];
    }
} catch (Throwable $e) {
    error_log('[register_non_golfer.php] Failed to load DB window options: ' . $e->getMessage());
}

// Fallback window options if DB fails
if (empty($windowOptions)) {
    $windowOptions = [
        [
            'id'          => 'window1',
            'title'       => 'Window-1',
            'window'      => '8:00 AM - 10:30 AM',
            'group_photo' => '09:45 AM',
            'slots_left'  => 30
        ],
        [
            'id'          => 'window2',
            'title'       => 'Window-2',
            'window'      => '10:00 AM - 12:00 PM',
            'group_photo' => '09:45 AM',
            'slots_left'  => 30
        ]
    ];
}

$pageTitle = 'Non-Golfer Registration';
require_once __DIR__ . '/templates/header.php';
?>

<!-- Hero Header -->
<section class="hero py-4">
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
      <div class="alert p-3 mb-4 d-flex align-items-center gap-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #fef9c3 0%, #fef3c7 100%); border-left: 5px solid #d97706 !important; border-radius: 0.75rem;">
        <i class="bi bi-gift-fill text-warning fs-3 flex-shrink-0"></i>
        <div>
          <strong class="text-warning-emphasis d-block mb-1" style="font-size: 1.05rem;"><i class="bi bi-stars"></i> Early Bird Discount Active!</strong>
          <span class="text-muted d-block small mb-2">You get a special discount rate of <strong><?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(CURRENT_FEE) ?></strong> (instead of the standard <?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(EVENT_FEE) ?>).</span>
          <span class="d-inline-flex align-items-center gap-2 badge bg-dark text-warning font-monospace py-1.5 px-3 rounded-pill" style="font-size: 0.82rem;">
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

      <!-- Player Category -->
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="playerCategory" class="form-label">Category <span class="text-danger">*</span></label>
          <select class="form-select" id="playerCategory" required>
            <option value="Diplomats" selected>Diplomat</option>
            <option value="Non-Diplomats">Non-Diplomat (Corporate / Guest)</option>
          </select>
        </div>
        <div class="col-md-6">
          <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
          <select class="form-select" id="gender" required>
            <option value="Male" selected>Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>

      <!-- Reference Section (Only shown for Non-Diplomats) -->
      <div id="referenceSection" class="p-3 bg-light rounded-3 mb-3" style="display: none; border-left: 4px solid var(--gold);">
        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-people-fill me-1"></i>Reference / Sponsor Diplomat</h6>
        <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 0.75rem;">Non-Diplomat registrations require a diplomat sponsor.</p>
        <div class="row g-2">
          <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" id="referenceName" placeholder="Diplomat Full Name" />
          </div>
          <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" id="referenceMission" placeholder="Mission / Embassy" />
          </div>
          <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" id="referenceContact" placeholder="Contact Phone" />
          </div>
        </div>
      </div>

      <!-- Primary Participant Info -->
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="fullName" required placeholder="Name on certificate" />
        </div>
        <div class="col-md-6">
          <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
          <input type="email" class="form-control" id="email" required placeholder="name@domain.com" />
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="contact" class="form-label">Contact Mobile <span class="text-danger">*</span></label>
          <input type="tel" class="form-control" id="contact" required placeholder="e.g. +8801700000000" />
        </div>
        <div class="col-md-6">
          <label for="nationality" class="form-label">Nationality <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="nationality" required placeholder="Embassy country or origin" />
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="designation" required placeholder="e.g. Ambassador, CEO, GM" />
        </div>
        <div class="col-md-6">
          <label for="organization" class="form-label">Organization <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="organization" required placeholder="Embassy name or Corporate office" />
        </div>
      </div>

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
          </select>
        </div>
        <div class="col-md-6">
          <label for="puttingContest" class="form-label">Interested in Guest Putting Contest? <span class="text-danger">*</span></label>
          <select class="form-select" id="puttingContest" required>
            <option value="Yes" selected>Yes</option>
            <option value="No">No</option>
          </select>
        </div>
      </div>

      <div class="mb-4">
        <label for="mailingAddress" class="form-label">Mailing Address</label>
        <textarea class="form-control" id="mailingAddress" rows="2" placeholder="Full postal address for invites"></textarea>
      </div>

      <!-- Arrival Window Preference -->
      <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi py-3 clock-fill text-gold me-2"></i>Preferred Arrival Window</h5>
      
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
                  <div><i class="bi bi-clock"></i> Window: <strong><?= htmlspecialchars($opt['window'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                  <div><i class="bi bi-camera"></i> Photos: <strong><?= htmlspecialchars($opt['group_photo'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Submit Button -->
      <div class="d-grid mt-4">
        <button type="button" id="submitBtn" class="btn btn-gold btn-lg py-3">
          <i class="bi bi-lock-fill"></i> Proceed to Secure Payment (<?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(CURRENT_FEE) ?>)
        </button>
      </div>

    </form>
  </div>
</div>

<!-- Scripts -->
<script>
  (function () {
    'use strict';

    var categorySelect = document.getElementById('playerCategory');
    var refSection = document.getElementById('referenceSection');
    
    // Toggle Sponsor section based on player category selection
    categorySelect.addEventListener('change', function () {
      var isNonDiplomat = this.value === 'Non-Diplomats';
      refSection.style.display = isNonDiplomat ? 'block' : 'none';
      if (!isNonDiplomat) {
        document.getElementById('referenceName').value = '';
        document.getElementById('referenceMission').value = '';
        document.getElementById('referenceContact').value = '';
      }
    });

    var form = document.getElementById('regForm');
    var btn = document.getElementById('submitBtn');
    var errorBox = document.getElementById('errorBox');

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lock-fill"></i> Proceed to Secure Payment';
      window.scrollTo({ top: errorBox.offsetTop - 20, behavior: 'smooth' });
    }

    btn.addEventListener('click', function () {
      errorBox.style.display = 'none';

      // HTML5 Validation Check
      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showError('Please check that all required fields are filled correctly.');
        return;
      }

      var windowSelected = form.querySelector('[name="arrivalWindow"]:checked');
      if (!windowSelected) {
        showError('Please select your preferred arrival window.');
        return;
      }

      // Build submit request
      var payload = {
        csrf_token: form.querySelector('[name="csrf_token"]').value,
        registration_type: document.getElementById('registration_type').value,
        playerCategory: categorySelect.value,
        gender: document.getElementById('gender').value,
        referenceName: document.getElementById('referenceName').value.trim(),
        referenceMission: document.getElementById('referenceMission').value.trim(),
        referenceContact: document.getElementById('referenceContact').value.trim(),
        fullName: document.getElementById('fullName').value.trim(),
        email: document.getElementById('email').value.trim(),
        contact: document.getElementById('contact').value.trim(),
        nationality: document.getElementById('nationality').value.trim(),
        designation: document.getElementById('designation').value.trim(),
        organization: document.getElementById('organization').value.trim(),
        mailingAddress: document.getElementById('mailingAddress').value.trim(),
        tshirtSize: document.getElementById('tshirtSize').value,
        puttingContestInterest: document.getElementById('puttingContest').value,
        scheduleGroup: windowSelected.value // Maps internally to the window choice
      };

      // Loading state
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Connecting to SSLCommerz...';

      var bodyParams = new URLSearchParams();
      bodyParams.append('cart_json', JSON.stringify(payload));

      fetch('payment/initiate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: bodyParams.toString()
      })
      .then(function (res) {
        return res.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('API response error: ' + text);
            throw new Error('Server returned invalid response structure.');
          }
        });
      })
      .then(function (data) {
        if (data.status === 'success' && data.payment_page_url) {
          window.location.href = data.payment_page_url;
        } else {
          showError(data.message || 'An error occurred during payment setup. Please retry.');
        }
      })
      .catch(function (err) {
        showError(err.message || 'Connection failure. Please check your internet connection.');
      });
    });

  })();
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
