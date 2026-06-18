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

$teeOptions = [];

try {
    $pdo = db();
    
    // Load active tee options for the current active tournament
    $stmt = $pdo->prepare(
        "SELECT id, title, reporting_time, group_photo_time, tee_off_time, slot_number 
         FROM tee_time_options 
         WHERE tournament_id = ? AND is_active = 1 
         ORDER BY display_order DESC, id ASC"
    );
    $stmt->execute([ACTIVE_TOURNAMENT_ID]);
    $options = $stmt->fetchAll();

    // Fetch slot counts for paid golfers of this tournament
    $counts = [];
    $countStmt = $pdo->prepare(
        "SELECT schedule_group, COUNT(*) as cnt 
         FROM registrations 
         WHERE tournament_id = ? AND payment_status = 'paid' 
         GROUP BY schedule_group"
    );
    $countStmt->execute([ACTIVE_TOURNAMENT_ID]);
    $countRows = $countStmt->fetchAll();

    foreach ($countRows as $row) {
        $counts[(string)$row['schedule_group']] = (int)$row['cnt'];
    }

    foreach ($options as $opt) {
        $id = (string)$opt['id'];
        $used = $counts[$id] ?? 0;
        $slotsLeft = max(0, (int)$opt['slot_number'] - $used);

        $teeOptions[] = [
            'id'          => $id,
            'title'       => (string)$opt['title'],
            'reporting'   => (string)$opt['reporting_time'],
            'group_photo' => (string)$opt['group_photo_time'],
            'tee_off'     => (string)$opt['tee_off_time'],
            'slots_left'  => $slotsLeft
        ];
    }
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
<section class="hero py-4">
  <div class="container text-center">
    <h1 class="font-serif text-white h2 mb-1">Golfer Registration</h1>
    <p class="mb-0 opacity-75" style="font-size: 0.9rem;">Please fill out your tournament details accurately.</p>
  </div>
</section>

<!-- Form Container -->
<div class="container">
  <div class="form-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-fill text-gold me-2"></i>Participant Details</h4>
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
      <input type="hidden" id="registration_type" value="golfer" />

      <!-- Player Category -->
      <div class="form-section-card">
        <h6 class="form-section-title"><i class="bi bi-person-fill text-gold"></i> Player Category</h6>
        <div class="row">
          <div class="col-md-12">
            <label for="playerCategory" class="form-label">Player Category <span class="text-danger">*</span></label>
            <select class="form-select" id="playerCategory" required>
              <option value="Diplomats" selected>Diplomat</option>
              <option value="Non-Diplomats">Non-Diplomat (Corporate / Guest)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Reference Section (Only shown for Non-Diplomats) -->
      <div id="referenceSection" class="p-3 bg-light rounded-3 mb-4" style="display: none; border-left: 4px solid var(--gold);">
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

      <!-- Contact & Personal Details -->
      <div class="form-section-card">
        <h6 class="form-section-title"><i class="bi bi-card-text text-gold"></i> Personal &amp; Contact Details</h6>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
            <div class="input-group">
              <select class="form-select" id="nameTitle" style="max-width: 90px;" required>
                <option value="Mr." selected>Mr.</option>
                <option value="Mrs.">Mrs.</option>
                <option value="Ms.">Ms.</option>
                <option value="Dr.">Dr.</option>
                <option value="Prof.">Prof.</option>
              </select>
              <input type="text" class="form-control" id="fullName" required placeholder="Name on certificate" />
            </div>
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
          <div class="col-md-12">
            <label for="profilePhoto" class="form-label">Profile Photo <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="profilePhoto" accept="image/*" required />
            <div class="form-text text-muted">Upload a clear passport-sized photo. Supported formats: JPG, PNG.</div>
          </div>
        </div>

        <div class="mb-0">
          <label for="mailingAddress" class="form-label">Mailing Address</label>
          <textarea class="form-control" id="mailingAddress" rows="2" placeholder="Full postal address for invites"></textarea>
        </div>
      </div>

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

      <!-- Tee Time Preference -->
      <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-clock-fill text-gold me-2"></i>Preferred Tee Time <span class="text-danger">*</span></h5>
      
      <div class="row g-3 mb-4">
        <?php foreach ($teeOptions as $opt): ?>
          <div class="col-md-6">
            <input type="radio" name="scheduleGroup" id="tee_<?= $opt['id'] ?>" class="slot-option-input d-none" value="<?= htmlspecialchars($opt['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $opt['slots_left'] <= 0 ? 'disabled' : '' ?> />
            <label for="tee_<?= $opt['id'] ?>" class="w-100 h-100">
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

    // Custom T-shirt size toggler
    var tshirtSelect = document.getElementById('tshirtSize');
    var customTshirtContainer = document.getElementById('customTshirtContainer');
    var customTshirtSize = document.getElementById('customTshirtSize');
    tshirtSelect.addEventListener('change', function () {
      if (this.value === 'Oversize') {
        customTshirtContainer.style.display = 'block';
        customTshirtSize.required = true;
      } else {
        customTshirtContainer.style.display = 'none';
        customTshirtSize.required = false;
        customTshirtSize.value = '';
      }
    });

    var form = document.getElementById('regForm');
    var btn = document.getElementById('submitBtn');
    var errorBox = document.getElementById('errorBox');
    var photoInput = document.getElementById('profilePhoto');

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lock-fill"></i> Complete Registration';
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

      var teeSelected = form.querySelector('[name="scheduleGroup"]:checked');
      if (!teeSelected) {
        showError('Please select your preferred tee time.');
        return;
      }

      if (photoInput.files.length === 0) {
        showError('Please upload a profile photo.');
        return;
      }

      var tshirtVal = tshirtSelect.value;
      if (tshirtVal === 'Oversize' && !customTshirtSize.value.trim()) {
        showError('Please enter your custom body width and length sizes.');
        return;
      }

      // Build submit request
      var payload = {
        csrf_token: form.querySelector('[name="csrf_token"]').value,
        registration_type: document.getElementById('registration_type').value,
        playerCategory: categorySelect.value,
        referenceName: document.getElementById('referenceName').value.trim(),
        referenceMission: document.getElementById('referenceMission').value.trim(),
        referenceContact: document.getElementById('referenceContact').value.trim(),
        fullName: document.getElementById('nameTitle').value + ' ' + document.getElementById('fullName').value.trim(),
        email: document.getElementById('email').value.trim(),
        contact: document.getElementById('contact').value.trim(),
        nationality: document.getElementById('nationality').value.trim(),
        designation: document.getElementById('designation').value.trim(),
        organization: document.getElementById('organization').value.trim(),
        mailingAddress: document.getElementById('mailingAddress').value.trim(),
        handicap: document.getElementById('handicap').value,
        golfSetBrand: document.getElementById('golfSetBrand').value,
        nameOnPolo: document.getElementById('nameOnPolo').value.trim(),
        tshirtSize: tshirtVal === 'Oversize' ? 'Oversize (' + customTshirtSize.value.trim() + ')' : tshirtVal,
        scheduleGroup: teeSelected.value
      };

      // Loading state
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting Registration...';

      var formData = new FormData();
      formData.append('cart_json', JSON.stringify(payload));
      formData.append('profile_photo', photoInput.files[0]);

      fetch('payment/initiate.php', {
        method: 'POST',
        body: formData
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
