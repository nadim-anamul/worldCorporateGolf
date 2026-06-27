<?php
/**
 * Main Landing Page — Migrated from original index.html
 * Dynamically populated from config settings for easy rebranding.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(EVENT_VENUE, ENT_QUOTES, 'UTF-8') ?></title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Register online for the <?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?> at <?= htmlspecialchars(EVENT_VENUE, ENT_QUOTES, 'UTF-8') ?>. Secure checkout via SSLCommerz." />
  <link rel="canonical" href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" />
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="<?= htmlspecialchars(APP_BASE_URL . '/assets/css/style.css', ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet" />
  <style>
    :root {
      --green-dark:  #0d3640;
      --green-mid:   #144e58;
      --green-light: #e4f0f2;
      --gold:        #c9a84c;
      --text-dark:   #1a1a1a;
    }

    /* ── Hero (homepage overrides) ───────────────────────────────────── */
    .hero-home {
      padding: 3.5rem 1rem 3rem;
    }
    .hero-home__content {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 2;
    }
    .hero-title--fallback {
      font-size: clamp(1.35rem, 4vw, 2.2rem);
      font-weight: 700;
      letter-spacing: .03em;
      text-shadow: 0 2px 8px rgba(0,0,0,.4);
      margin: 0 0 0.25rem;
      color: #fff;
    }
    /* ── Section titles ─────────────────────────────────────────────────── */
    .section-title {
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--green-dark);
      border-left: 4px solid var(--gold);
      padding-left: .75rem;
      margin-bottom: 1.25rem;
    }

    /* ── Info cards ─────────────────────────────────────────────────────── */
    .info-card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.08);
      height: 100%;
    }
    .info-card .card-body { padding: 1.5rem; }
    .info-icon {
      font-size: 2rem;
      color: var(--green-mid);
      margin-bottom: .5rem;
    }

    /* ── Schedule cards ─────────────────────────────────────────────────── */
    .schedule-card {
      border: 2px solid var(--green-mid);
      border-radius: 1rem;
      padding: 1.25rem;
      background: var(--green-light);
      height: 100%;
    }
    .schedule-card .badge-shotgun {
      display: inline-block;
      background: var(--green-dark);
      color: #fff;
      font-size: .75rem;
      font-weight: 600;
      border-radius: 6px;
      padding: .2rem .65rem;
      margin-bottom: .75rem;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .schedule-card .time-row {
      display: flex;
      justify-content: space-between;
      font-size: .92rem;
      padding: .25rem 0;
      border-bottom: 1px dashed #9dc4cb;
    }
    .schedule-card .time-row:last-child { border-bottom: none; }
    .schedule-card .time-val { font-weight: 700; color: var(--green-dark); }

    /* ── Activity list ──────────────────────────────────────────────────── */
    .activity-item {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .6rem 0;
      border-bottom: 1px solid #eee;
      font-size: .95rem;
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-item i { color: var(--green-mid); font-size: 1.2rem; flex-shrink: 0; }

    /* ── Fee banner ─────────────────────────────────────────────────────── */
    .fee-banner {
      background: var(--green-mid);
      color: #fff;
      border-radius: 1rem;
      padding: 2rem 1.5rem;
      text-align: center;
    }
    .fee-banner .amount {
      font-size: 2.4rem;
      font-weight: 800;
      color: var(--gold);
      line-height: 1.1;
    }
    .fee-banner .amount-note { font-size: .85rem; opacity: .8; margin-top: .25rem; }

    /* ── CTA button ─────────────────────────────────────────────────────── */
    .btn-register {
      background: var(--green-mid);
      color: #fff;
      font-weight: 700;
      font-size: 1.05rem;
      border: none;
      border-radius: 50px;
      padding: .75rem 2.5rem;
      transition: transform .15s, box-shadow .15s;
      text-decoration: none;
      display: inline-block;
    }
    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,.25);
      background: var(--green-dark);
      color: #fafafa;
    }

    /* ── Footer ─────────────────────────────────────────────────────────── */
    footer {
      background: var(--green-dark);
      color: rgba(255,255,255,.75);
      font-size: .85rem;
      padding: 1.5rem 1rem;
    }

    /* ── Misc utilities ─────────────────────────────────────────────────── */
    .deadline-chip {
      background: rgb(255, 255, 255);
      border: 1px solid #ffc107;
      border-radius: 8px;
      padding: .4rem .9rem;
      font-size: .88rem;
      font-weight: 600;
      color: #b30303;
      display: inline-block;
    }
    .rules-list li { padding: .3rem 0; font-size: .93rem; }

    /* ── About cards with logo ───────────────────────────────────────────── */
    .about-card {
      background: #fff;
      border: none;
      border-radius: 1rem;
      box-shadow: 0 2px 14px rgba(0,0,0,.09);
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .about-card-logo {
      background: #fff;
      border-bottom: 1px solid #eef0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 1.25rem;
      min-height: 140px;
    }
    .about-card-logo img {
      max-height: 100px;
      max-width: 160px;
      object-fit: contain;
    }
    .about-card-body {
      padding: 1.1rem 1.25rem 1.35rem;
      flex: 1;
    }
    .about-card-body h6 {
      color: var(--green-dark);
      font-weight: 700;
      margin-bottom: .45rem;
      font-size: .95rem;
    }
    .about-card-body p {
      color: #555;
      font-size: .87rem;
      line-height: 1.65;
      margin: 0;
    }

    /* ── Contact section ────────────────────────────────────────────────── */
    .contact-section {
      background: #fff;
      border-top: 3px solid var(--green-mid);
    }
    .contact-chip {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: var(--green-light);
      border: 1px solid #9dc4cb;
      border-radius: 50px;
      padding: .45rem 1.1rem;
      font-weight: 600;
      font-size: .9rem;
      color: var(--green-dark);
      text-decoration: none;
      transition: background .15s;
    }
    .contact-chip:hover { background: #cce4e8; color: var(--green-dark); }
    .contact-chip .bi-whatsapp { color: #25d366; }

    /* ── Registration Option Cards ──────────────────────────────────────── */
    .reg-option-card {
      background: #fff;
      border-radius: 1.25rem;
      border-top: 5px solid var(--gold) !important;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,.05);
    }
    .reg-option-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(13, 54, 64, 0.1) !important;
    }
    .option-icon {
      font-size: 2.25rem;
      color: var(--green-mid);
      width: 70px;
      height: 70px;
      background: var(--green-light);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
    }
    .btn-register-card {
      background: var(--green-mid);
      color: #fff;
      font-weight: 600;
      border: none;
      border-radius: 50px;
      transition: background 0.2s;
      text-decoration: none;
    }
    .btn-register-card:hover {
      background: var(--green-dark);
      color: #fff;
    }
    .btn-register-card-secondary {
      background: #fff;
      color: var(--green-mid);
      font-weight: 600;
      border: 2px solid var(--green-mid);
      border-radius: 50px;
      transition: all 0.2s;
      text-decoration: none;
    }
    .btn-register-card-secondary:hover {
      background: var(--green-light);
      color: var(--green-dark);
      border-color: var(--green-dark);
    }

    /* ── Partner Ticker ─────────────────────────────────────────────────── */
    .partner-section {
      background: linear-gradient(180deg, #eef6f7 0%, #e4f0f2 100%);
      padding: 3.5rem 0;
      overflow: hidden;
      border-top: 1px solid #c5dde0;
      border-bottom: 1px solid #c5dde0;
    }
    .partner-section__heading {
      color: var(--green-dark);
      letter-spacing: 0.05em;
      font-size: 0.95rem;
    }
    .marquee-container {
      display: flex;
      width: 100%;
      overflow: hidden;
      position: relative;
    }
    .marquee-content {
      display: flex;
      gap: 5rem;
      animation: marquee 25s linear infinite;
      min-width: 100%;
      align-items: center;
      justify-content: space-around;
    }
    .marquee-content:hover {
      animation-play-state: paused;
    }
    .partner-logo-link {
      display: block;
      transition: transform 0.25s ease, filter 0.25s ease, opacity 0.25s ease;
      filter: grayscale(70%);
      opacity: 0.7;
    }
    .partner-logo-link:hover {
      filter: grayscale(0%);
      opacity: 1;
      transform: scale(1.06);
    }
    .partner-logo-link img {
      max-height: 48px;
      max-width: 160px;
      object-fit: contain;
    }
    @keyframes marquee {
      0% { transform: translateX(0%); }
      100% { transform: translateX(-50%); }
    }

    .registration-options__intro {
      margin-bottom: 3rem;
    }
    .registration-options__lead {
      max-width: 600px;
      font-size: 0.95rem;
      line-height: 1.55;
    }
    .registration-options__promo {
      max-width: 36rem;
      margin: 0.75rem auto 0;
    }

    @media (max-width: 768px) {
      .container--landing {
        padding-left: 0.65rem;
        padding-right: 0.65rem;
      }

      .hero-home {
        padding: 2.25rem 0.65rem 2rem;
      }
      .btn-register.btn-lg {
        font-size: 0.95rem;
        padding: 0.65rem 1.75rem;
      }

      .section-title {
        font-size: 1rem;
      }
      .section-title--event-info {
        text-align: center;
        border-left: none;
        padding-left: 0;
      }

      section.py-5 {
        padding-top: 2.25rem !important;
        padding-bottom: 2.25rem !important;
      }

      .info-card .card-body {
        padding: 1.1rem 0.85rem;
      }
      .info-icon {
        font-size: 1.65rem;
      }

      .activities-rules-row {
        --bs-gutter-y: 1.75rem;
      }
      .activity-item {
        font-size: 0.88rem;
        padding: 0.5rem 0;
      }
      .rules-list li {
        font-size: 0.86rem;
      }

      .registration-options-section {
        padding-top: 1.75rem !important;
        padding-bottom: 1.75rem !important;
      }
      .registration-options__intro {
        margin-bottom: 1.15rem !important;
      }
      .registration-options__title {
        font-size: 0.95rem;
        letter-spacing: 0.02em;
        line-height: 1.35;
        margin-bottom: 0.5rem !important;
      }
      .registration-options__lead {
        font-size: 0.84rem;
        line-height: 1.5;
        margin-bottom: 0 !important;
        padding: 0;
      }
      .registration-options__promo {
        max-width: 100%;
        margin-top: 0.65rem;
      }
      .registration-options-section .row {
        --bs-gutter-y: 0.85rem;
      }
      .reg-option-card .card-body {
        padding: 1.1rem 0.9rem !important;
      }
      .reg-option-card h4 {
        font-size: 1.05rem;
      }
      .reg-option-card p.small {
        font-size: 0.82rem !important;
        margin-bottom: 0.85rem !important;
      }
      .option-icon {
        width: 56px;
        height: 56px;
        font-size: 1.6rem;
        margin-bottom: 0.65rem !important;
      }
      .btn-register-card,
      .btn-register-card-secondary {
        font-size: 0.9rem;
        padding-top: 0.55rem !important;
        padding-bottom: 0.55rem !important;
      }

      .about-card-logo {
        min-height: 110px;
        padding: 1rem;
      }
      .about-card-logo img {
        max-height: 72px;
      }
      .about-card-body {
        padding: 0.9rem 1rem 1.1rem;
      }

      .contact-chip {
        font-size: 0.84rem;
        padding: 0.4rem 0.9rem;
      }
      .partner-section {
        padding: 2rem 0;
      }
      .marquee-content {
        gap: 2.5rem;
      }
      .partner-logo-link img {
        max-height: 36px;
        max-width: 120px;
      }
    }

    .animate-pulse-slow {
      animation: pulse-glow 2.5s infinite ease-in-out;
    }
    @keyframes pulse-glow {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.82; }
    }
  </style>
</head>
<body>

<?php if (IS_EARLY_BIRD_ACTIVE): ?>
  <div class="early-bird-bar-shell">
    <?php
      $countdownId = 'earlyBirdCountdown';
      require __DIR__ . '/templates/_early_bird_bar.php';
    ?>
  </div>
<?php endif; ?>

<?php
$heroBackgroundUrl = defined('EVENT_HERO_BACKGROUND_URL') && EVENT_HERO_BACKGROUND_URL !== ''
    ? EVENT_HERO_BACKGROUND_URL
    : APP_BASE_URL . '/assets/images/event-details.jpg';
?>
<!-- ══════════════════════  HERO  ══════════════════════ -->
<section
  class="hero hero-home"
  aria-label="Event registration"
  style="--hero-bg-url: url('<?= htmlspecialchars($heroBackgroundUrl, ENT_QUOTES, 'UTF-8') ?>')"
>
  <div class="container container--landing position-relative">
    <div class="hero-home__content">

      <?php if (defined('EVENT_LOGO_URL') && EVENT_LOGO_URL !== ''): ?>
        <div class="hero-logo-wrap">
          <img
            class="hero-logo"
            src="<?= htmlspecialchars(EVENT_LOGO_URL, ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?>"
          />
        </div>
      <?php else: ?>
        <h1 class="hero-title hero-title--fallback"><?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
      <?php endif; ?>

      <a href="#registration-options" class="btn-register btn-register--hero btn-lg" id="heroRegisterBtn">
        <i class="bi bi-person-plus-fill me-1"></i> Register Now
      </a>

      <p class="hero-closing-info">
        <i class="bi bi-calendar-x" aria-hidden="true"></i>
        Registration closes on <strong><?= htmlspecialchars(EVENT_DEADLINE, ENT_QUOTES, 'UTF-8') ?></strong> (or until slots are filled)
      </p>

      <?php if (defined('REGISTRATION_DEADLINE_AT') && REGISTRATION_DEADLINE_AT): ?>
        <div class="hero-countdown" role="timer" aria-live="polite" data-registration-countdown-wrap>
          <span class="hero-countdown__label">Closes in</span>
          <div class="hero-countdown__segments">
            <div class="hero-countdown__segment">
              <span class="hero-countdown__value" data-reg-days>00</span>
              <span class="hero-countdown__unit">Days</span>
            </div>
            <div class="hero-countdown__segment">
              <span class="hero-countdown__value" data-reg-hours>00</span>
              <span class="hero-countdown__unit">Hours</span>
            </div>
            <div class="hero-countdown__segment">
              <span class="hero-countdown__value" data-reg-mins>00</span>
              <span class="hero-countdown__unit">Mins</span>
            </div>
            <div class="hero-countdown__segment">
              <span class="hero-countdown__value" data-reg-secs>00</span>
              <span class="hero-countdown__unit">Secs</span>
            </div>
          </div>
          <div class="hero-countdown__closed d-none" data-registration-closed>Registration Closed</div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<!-- ══════════════════════  EVENT INFO  ══════════════════════ -->
<section class="py-5 bg-white">
  <div class="container container--landing">
    <h2 class="section-title section-title--event-info"><i class="bi bi-info-circle"></i>&nbsp; Event Information</h2>
    <div class="row g-4">

      <div class="col-sm-6 col-lg-3">
        <div class="card info-card text-center">
          <div class="card-body">
            <div class="info-icon"><i class="bi bi-calendar-event"></i></div>
            <h6 class="fw-bold mb-1">Date</h6>
            <p class="mb-0 text-muted"><?= htmlspecialchars(EVENT_DATE, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card info-card text-center">
          <div class="card-body">
            <div class="info-icon"><i class="bi bi-pin-map"></i></div>
            <h6 class="fw-bold mb-1">Course &amp; Venue</h6>
            <p class="mb-0 text-muted"><?= htmlspecialchars(EVENT_VENUE, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card info-card text-center">
          <div class="card-body">
            <div class="info-icon"><i class="bi bi-trophy"></i></div>
            <h6 class="fw-bold mb-1">Format</h6>
            <p class="mb-0 text-muted"><?= htmlspecialchars(EVENT_FORMAT, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-lg-3">
        <div class="card info-card text-center">
          <div class="card-body">
            <div class="info-icon"><i class="bi bi-people"></i></div>
            <h6 class="fw-bold mb-1">Hosted By</h6>
            <p class="mb-0 text-muted">GolfHouse &amp; Corporate Tour</p>
          </div>
        </div>
      </div>

    </div>

    <!-- Lunch & Prize -->
    <div class="alert mt-4 mb-0 d-flex align-items-start gap-3"
         style="background:var(--green-light); border:1px solid #9dc4cb; border-radius:.75rem;">
      <i class="bi bi-award-fill fs-4 mt-1 flex-shrink-0" style="color:var(--green-mid);"></i>
      <div>
        <strong class="section-title" style="border-left:none; padding-left:0; font-size:1.05rem;">Dinner &amp; Prize Giving Ceremony</strong>
        <div class="d-flex flex-column flex-sm-row flex-wrap gap-sm-3 mt-1" style="font-size:.92rem;">
          <div><i class="bi bi-clock" style="color:var(--green-mid);"></i>&nbsp;  <strong>7:30 PM - 10:00 PM</strong></div>
          <div><i class="bi bi-geo-alt" style="color:var(--green-mid);"></i>&nbsp; <strong>Crowne Plaza Dhaka Airport</strong></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════  ACTIVITIES  ══════════════════════ -->
<section class="py-5 bg-white border-top">
  <div class="container container--landing">
    <div class="row g-5 activities-rules-row">

      <div class="col-md-6">
        <h2 class="section-title"><i class="bi bi-stars"></i>&nbsp; Activities</h2>
        <div class="activity-item"><i class="bi bi-people-fill"></i> Networking &amp; Business Connections</div>
        <div class="activity-item"><i class="bi bi-bullseye"></i> Putting Contest</div>
        <div class="activity-item"><i class="bi bi-flag-fill"></i> 9-Hole Golfing Contest with 108 Top Executives</div>
        <div class="activity-item"><i class="bi bi-bag-heart-fill"></i> Exclusive Goodies</div>
        <div class="activity-item"><i class="bi bi-geo-alt-fill"></i> Driving Range Experience</div>
      </div>

      <div class="col-md-6">
        <h2 class="section-title"><i class="bi bi-card-checklist"></i>&nbsp; Rules &amp; Guidelines</h2>
        <ul class="rules-list ps-3">
          <li>Respect for course rules, staff, and fellow participants is mandatory.</li>
          <li>Dress code: Golf Attire</li>
          <li>Participants must bring their own clubs and gear.</li>
          <li>Arrive according to Reporting Times, and at least 30 minutes before tee-off time.</li>
          <li>Format: Best Ball Scramble; Shotgun Method.</li>
          <li>Non-golfers may participate in Driving Range &amp; Putting Contest only.</li>
          <li>This is a friendly corporate golf tournament focused on networking and enjoyment. It follows a social format and may not suit highly competitive golfers.</li>
          <li>All participant contributions are strictly non-refundable under any circumstances.</li>
        </ul>
        <p class="text-muted" style="font-size:.8rem;">
          * Organizers reserve the right to amend rules and dates prior to or during the event.
        </p>
      </div>

    </div>
  </div>
</section>

<!-- ══════════════════════  REGISTRATION OPTIONS  ══════════════════════ -->
<section id="registration-options" class="registration-options-section py-5" style="background:var(--green-light);">
  <div class="container container--landing">
    
    <div class="registration-options__intro text-center">
      <h2 class="registration-options__title fw-bold mb-2 text-uppercase" style="color:var(--green-dark);">Choose Your Registration Type</h2>
      <p class="registration-options__lead text-muted mx-auto">
        Registration closes on <strong><?= htmlspecialchars(EVENT_DEADLINE, ENT_QUOTES, 'UTF-8') ?></strong> (or until slots are filled). 
        A participant contribution will be processed securely via SSLCommerz.
      </p>
      
      <?php if (IS_EARLY_BIRD_ACTIVE): ?>
        <div class="registration-options__promo">
          <?php
            $promoVariant = 'section';
            $countdownId = 'earlyBirdSectionCountdown';
            require __DIR__ . '/templates/_early_bird_promo.php';
          ?>
        </div>
      <?php else: ?>
        <div class="early-bird-pricing-wrap">
          <?php $pricingVariant = 'default'; require __DIR__ . '/templates/_event_pricing.php'; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="row g-4 justify-content-center">
      <!-- Golfer Option Card -->
      <div class="col-md-6 col-lg-5 d-flex">
        <div class="card reg-option-card w-100 shadow-sm border-0">
          <div class="card-body p-4 d-flex flex-column text-center">
            <div class="option-icon mb-3">
              <i class="bi bi-flag-fill"></i>
            </div>
            <h4 class="fw-bold mb-2" style="color:var(--green-dark);">Golfer Participant</h4>
            <p class="text-muted flex-grow-1 small mb-4">
              Participate in the main tournament with 18-hole shotgun format. Includes green fee, networking lunch, exclusive tournament goodies, and eligibility for prizes and trophies.
            </p>
            <a href="register.php" class="btn btn-register-card py-2.5 w-100">
              <i class="bi bi-person-plus-fill me-1"></i> Register as Golfer
            </a>
          </div>
        </div>
      </div>

      <!-- Guest Option Card -->
      <div class="col-md-6 col-lg-5 d-flex">
        <div class="card reg-option-card w-100 shadow-sm border-0">
          <div class="card-body p-4 d-flex flex-column text-center">
            <div class="option-icon mb-3">
              <i class="bi bi-stars"></i>
            </div>
            <h4 class="fw-bold mb-2" style="color:var(--green-dark);">Guest / Non-Golfer</h4>
            <p class="text-muted flex-grow-1 small mb-4">
              Join as a networking guest. Includes entry to the driving range, guest putting contest experience, lucky draw entry, networking lunch, and prize ceremony access.
            </p>
            <a href="register_non_golfer.php" class="btn btn-register-card-secondary py-2.5 w-100">
              <i class="bi bi-people-fill me-1"></i> Register as Guest
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ══════════════════════  ABOUT  ══════════════════════ -->
<section class="py-5 bg-white">
  <div class="container container--landing">
    <h2 class="section-title"><i class="bi bi-building"></i>&nbsp; About Us</h2>
    <div class="row g-4">

      <div class="col-md-4 d-flex">
        <div class="about-card w-100">
          <div class="about-card-logo">
            <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse Logo" />
          </div>
          <div class="about-card-body">
            <h6>GolfHouse</h6>
            <p>Bangladesh's leading golf media and event platform, publishing the country's signature monthly golf magazine since 2015 and organizing premium tournaments and corporate engagements through enhanced collaboration.</p>
          </div>
        </div>
      </div>

      <div class="col-md-4 d-flex">
        <div class="about-card w-100">
          <div class="about-card-logo">
            <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/corporate-tour-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Corporate Tour Logo" />
          </div>
          <div class="about-card-body">
            <h6>Corporate Tour</h6>
            <p>An exclusive initiative by GolfHouse Holdings Ltd. bringing together business and golf through high-impact events, tournaments, and networking platforms for corporate leaders, diplomats, and decision-makers.</p>
          </div>
        </div>
      </div>

      <div class="col-md-4 d-flex">
        <div class="about-card w-100">
          <div class="about-card-logo">
            <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/jolshiri-golf-club-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Jolshiri Golf Club Logo" />
          </div>
          <div class="about-card-body">
            <h6>Jolshiri Golf Club (JGC)</h6>
            <p>Dhaka's newest golf destination, located in Sector 17 of Jolshiri Abashon, between
            Purbachal Expressway and Madani Avenue — offering a refreshing blend of nature, competition,
            and community.</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══════════════════════  CONTACT  ══════════════════════ -->
<section class="contact-section py-4">
  <div class="container container--landing text-center">
    <p class="fw-semibold mb-3" style="color:var(--green-dark); font-size:1rem;">
      <i class="bi bi-headset"></i>&nbsp; Contact — Relationship Officer
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <?php if (defined('CONTACT_PHONE_1') && CONTACT_PHONE_1 !== ''): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', CONTACT_PHONE_1) ?>" target="_blank" class="contact-chip">
          <i class="bi bi-whatsapp"></i> <?= htmlspecialchars(CONTACT_PHONE_1, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endif; ?>
      <?php if (defined('CONTACT_PHONE_2') && CONTACT_PHONE_2 !== ''): ?>
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', CONTACT_PHONE_2) ?>" target="_blank" class="contact-chip">
          <i class="bi bi-whatsapp"></i> <?= htmlspecialchars(CONTACT_PHONE_2, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endif; ?>
    </div>
    <a href="#registration-options" class="btn-register btn-lg mt-3">
      <i class="bi bi-person-plus-fill me-1"></i> Register Now
    </a>
  </div>
</section>

<!-- ══════════════════════  PARTNERS  ══════════════════════ -->
<section class="partner-section">
  <div class="container text-center mb-4">
    <h5 class="fw-bold text-uppercase mb-0 partner-section__heading"><i class="bi bi-shield-check me-1 text-gold"></i> Event Partners &amp; Sponsors</h5>
  </div>
  <div class="marquee-container">
    <div class="marquee-content">
      <!-- Duplicate logos twice for a smooth looping marquee (two identical groups of 6 logos for seamless translation) -->
      <!-- Group 1 -->
      <a href="https://golfhouse.com.bd" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse" />
      </a>
      <a href="https://worldcorporategolftour.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/corporate-tour-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Corporate Tour" />
      </a>
      <a href="https://jolshirigolfclub.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/jolshiri-golf-club-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Jolshiri Golf Club" />
      </a>
      <a href="https://golfhouse.com.bd" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse" />
      </a>
      <a href="https://worldcorporategolftour.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/corporate-tour-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Corporate Tour" />
      </a>
      <a href="https://jolshirigolfclub.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/jolshiri-golf-club-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Jolshiri Golf Club" />
      </a>
      <!-- Group 2 (identical copy) -->
      <a href="https://golfhouse.com.bd" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse" />
      </a>
      <a href="https://worldcorporategolftour.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/corporate-tour-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Corporate Tour" />
      </a>
      <a href="https://jolshirigolfclub.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/jolshiri-golf-club-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Jolshiri Golf Club" />
      </a>
      <a href="https://golfhouse.com.bd" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse" />
      </a>
      <a href="https://worldcorporategolftour.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/corporate-tour-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Corporate Tour" />
      </a>
      <a href="https://jolshirigolfclub.com" target="_blank" class="partner-logo-link">
        <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/jolshiri-golf-club-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="Jolshiri Golf Club" />
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════  FOOTER  ══════════════════════ -->
<footer class="text-center">
  <div class="container">
    <p class="mb-0 opacity-75" style="font-size:.82rem;">
      &copy; <?= date('Y') ?> GolfHouse &amp; Corporate Tour. All rights reserved.
    </p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if (defined('REGISTRATION_DEADLINE_AT') && REGISTRATION_DEADLINE_AT): ?>
<script>
(function () {
  const deadline = new Date("<?= date('c', strtotime((string)REGISTRATION_DEADLINE_AT)) ?>").getTime();
  const wrap = document.querySelector('[data-registration-countdown-wrap]');
  if (!wrap || Number.isNaN(deadline)) return;

  const daysEl = wrap.querySelector('[data-reg-days]');
  const hoursEl = wrap.querySelector('[data-reg-hours]');
  const minsEl = wrap.querySelector('[data-reg-mins]');
  const secsEl = wrap.querySelector('[data-reg-secs]');
  const segments = wrap.querySelector('.hero-countdown__segments');
  const closedEl = wrap.querySelector('[data-registration-closed]');
  const ctaBtn = document.getElementById('heroRegisterBtn');
  const pad = (num) => String(num).padStart(2, '0');

  function setUrgent(isUrgent) {
    wrap.classList.toggle('hero-countdown--urgent', isUrgent);
  }

  function showClosed() {
    if (segments) segments.classList.add('d-none');
    if (closedEl) closedEl.classList.remove('d-none');
    if (ctaBtn) {
      ctaBtn.classList.add('btn-register--disabled');
      ctaBtn.setAttribute('aria-disabled', 'true');
    }
  }

  function update() {
    const diff = deadline - Date.now();
    if (diff <= 0) {
      showClosed();
      return;
    }

    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((diff % (1000 * 60)) / 1000);

    if (daysEl) daysEl.textContent = pad(d);
    if (hoursEl) hoursEl.textContent = pad(h);
    if (minsEl) minsEl.textContent = pad(m);
    if (secsEl) secsEl.textContent = pad(s);
    setUrgent(diff < 24 * 60 * 60 * 1000);
  }

  update();
  setInterval(update, 1000);
})();
</script>
<?php endif; ?>

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
</body>
</html>
