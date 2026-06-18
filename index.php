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
  <style>
    :root {
      --green-dark:  #0d3640;
      --green-mid:   #144e58;
      --green-light: #e4f0f2;
      --gold:        #c9a84c;
      --text-dark:   #1a1a1a;
    }

    /* ── Early Bird Banner ────────────────────────────────────────────────── */
    .early-bird-banner {
      background: rgba(13, 54, 64, 0.98);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      color: #fff;
      font-weight: 550;
      border-bottom: 2.5px solid var(--gold);
      z-index: 1050;
      transition: all 0.3s ease;
      font-size: 0.91rem;
    }
    .early-bird-banner strong {
      color: var(--gold);
    }
    .countdown-badge {
      letter-spacing: 0.06em;
      background: rgba(201, 168, 76, 0.15);
      color: #ffd666;
      border: 1px solid var(--gold);
      font-weight: 700;
      box-shadow: 0 0 10px rgba(201, 168, 76, 0.15);
      border-radius: 50px;
      padding: 0.35rem 1rem !important;
      display: inline-block;
    }
    .animate-pulse-slow {
      animation: pulse-glow 2.5s infinite ease-in-out;
    }
    @keyframes pulse-glow {
      0%, 100% { transform: scale(1); opacity: 1; filter: drop-shadow(0 0 2px rgba(201, 168, 76, 0.5)); }
      50% { transform: scale(1.1); opacity: 0.85; filter: drop-shadow(0 0 6px rgba(201, 168, 76, 0.8)); }
    }

    /* ── Hero ────────────────────────────────────────────────────────────── */
    .hero {
      background: linear-gradient(160deg, var(--green-dark) 0%, #144e58 60%, #1e6b78 100%);
      color: #fff;
      padding: 5rem 1rem 4rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url('<?= htmlspecialchars(APP_BASE_URL . '/assets/images/event-details.jpg', ENT_QUOTES, 'UTF-8') ?>') center/cover no-repeat;
      opacity: 0.18;
    }
    .hero-title {
      font-size: clamp(1.5rem, 4vw, 2.8rem);
      font-weight: 700;
      letter-spacing: .03em;
      text-shadow: 0 2px 8px rgba(0,0,0,.4);
      margin-top: 1.25rem;
      position: relative;
      z-index: 2;
    }
    .hero-subtitle {
      font-size: clamp(.95rem, 2.5vw, 1.15rem);
      opacity: .9;
      margin-top: .4rem;
      position: relative;
      z-index: 2;
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
      background: #f8fafc;
      padding: 3.5rem 0;
      overflow: hidden;
      border-top: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
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
      filter: grayscale(100%);
      opacity: 0.5;
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

    @media (max-width: 768px) {
      .section-title {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

<?php if (IS_EARLY_BIRD_ACTIVE): ?>
  <div class="early-bird-banner py-2.5 px-3 text-center position-sticky top-0 w-100 shadow-sm">
    <div class="container d-flex flex-wrap align-items-center justify-content-center gap-2">
      <span class="d-inline-flex align-items-center"><i class="bi bi-lightning-charge-fill text-warning fs-6 me-1.5 animate-pulse-slow"></i> <strong>Early Bird Offer Active!</strong>&nbsp;Save BDT <?= number_format(EVENT_FEE - EARLY_BIRD_FEE) ?>/- by registering now. Offer ends in:</span>
      <span id="earlyBirdCountdown" class="countdown-badge font-monospace text-warning fs-6">00d 00h 00m 00s</span>
    </div>
  </div>
<?php endif; ?>

<!-- ══════════════════════  HERO  ══════════════════════ -->
<section class="hero">
  <div class="container position-relative">
    <h1 class="hero-title"><?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="mt-3">
      <span class="deadline-chip"><i class="bi bi-clock"></i>&nbsp; Registration Deadline: <?= htmlspecialchars(EVENT_DEADLINE, ENT_QUOTES, 'UTF-8') ?> (or until slots are filled)</span>
    </div>
    <div class="mt-4">
      <a href="#registration-options" class="btn-register btn-lg">
        <i class="bi bi-person-plus-fill me-1"></i> Register Now
      </a>
    </div>
  </div>
</section>

<!-- ══════════════════════  EVENT INFO  ══════════════════════ -->
<section class="py-5 bg-white">
  <div class="container">
    <h2 class="section-title"><i class="bi bi-info-circle"></i>&nbsp; Event Information</h2>
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
      <i class="bi bi-cup-hot-fill fs-4 mt-1 flex-shrink-0" style="color:var(--green-mid);"></i>
      <div>
        <strong class="section-title" style="border-left:none; padding-left:0; font-size:1.05rem;">Lunch &amp; Prize Giving Ceremony</strong>
        <div class="d-flex flex-column flex-sm-row flex-wrap gap-sm-3 mt-1" style="font-size:.92rem;">
          <div><i class="bi bi-clock" style="color:var(--green-mid);"></i>&nbsp; Time: <strong>12:30 PM – 3:00 PM</strong></div>
          <div><i class="bi bi-geo-alt" style="color:var(--green-mid);"></i>&nbsp; Venue: <strong>The Banquet Hall, <?= htmlspecialchars(str_replace(', Dhaka', '', EVENT_VENUE), ENT_QUOTES, 'UTF-8') ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════  ACTIVITIES  ══════════════════════ -->
<section class="py-5 bg-white border-top">
  <div class="container">
    <div class="row g-5">

      <div class="col-md-6">
        <h2 class="section-title"><i class="bi bi-stars"></i>&nbsp; Activities</h2>
        <div class="activity-item"><i class="bi bi-people-fill"></i> Networking &amp; Business Connections</div>
        <div class="activity-item"><i class="bi bi-bullseye"></i> Putting Contest</div>
        <div class="activity-item"><i class="bi bi-gift-fill"></i> Prizes &amp; Lucky Draw</div>
        <div class="activity-item"><i class="bi bi-bag-heart-fill"></i> Exclusive Goodies</div>
        <div class="activity-item"><i class="bi bi-geo-alt-fill"></i> Driving Range Experience</div>
      </div>

      <div class="col-md-6">
        <h2 class="section-title"><i class="bi bi-card-checklist"></i>&nbsp; Rules &amp; Guidelines</h2>
        <ul class="rules-list ps-3">
          <li>Open to diplomatic community, foreign professionals, and distinguished invited guests.</li>
          <li>Dress code: Proper golf attire required.</li>
          <li>Participants must bring their own clubs and gear.</li>
          <li>Arrive at least 30 minutes before tee time.</li>
          <li>Format: Best Ball Scramble; Shotgun Method.</li>
          <li>Respect for course rules, staff, and fellow participants is mandatory.</li>
          <li>Non-golfers may participate in Driving Range &amp; Putting Contest only.</li>
          <li>This is a friendly corporate golf tournament focused on networking and enjoyment. It follows a social format and may not suit highly competitive golfers.</li>
          <li>All participant contributions are strictly non-refundable under any circumstances.</li>
        </ul>
        <p class="text-muted" style="font-size:.8rem;">
          * Organizers reserve the right to amend rules prior to or during the event.
        </p>
      </div>

    </div>
  </div>
</section>

<!-- ══════════════════════  REGISTRATION OPTIONS  ══════════════════════ -->
<section id="registration-options" class="py-5" style="background:var(--green-light);">
  <div class="container">
    
    <div class="text-center mb-5">
      <h2 class="fw-bold mb-2 text-uppercase" style="color:var(--green-dark);">Choose Your Registration Type</h2>
      <p class="text-muted mx-auto" style="max-width: 600px;">
        Registration closes on <strong><?= htmlspecialchars(EVENT_DEADLINE, ENT_QUOTES, 'UTF-8') ?></strong> (or until slots are filled). 
        A participant contribution will be processed securely via SSLCommerz.
      </p>
      
      <?php if (IS_EARLY_BIRD_ACTIVE): ?>
        <div class="badge bg-warning text-dark px-4 py-2 fs-6 rounded-pill shadow-sm animate-pulse-slow">
          <i class="bi bi-gift-fill me-1"></i> Early Bird Discount Active: BDT <?= number_format(CURRENT_FEE) ?> (Standard BDT <?= number_format(EVENT_FEE) ?>)
        </div>
      <?php else: ?>
        <div class="badge bg-secondary text-white px-4 py-2 fs-6 rounded-pill shadow-sm">
          Participant Contribution: BDT <?= number_format(EVENT_FEE) ?>
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
  <div class="container">
    <h2 class="section-title"><i class="bi bi-building"></i>&nbsp; About Us</h2>
    <div class="row g-4">

      <div class="col-md-4 d-flex">
        <div class="about-card w-100">
          <div class="about-card-logo">
            <img src="<?= htmlspecialchars(APP_BASE_URL . '/assets/images/golfhouse-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="GolfHouse Logo" />
          </div>
          <div class="about-card-body">
            <h6>GolfHouse</h6>
            <p>Bangladesh's leading golf media and event platform, publishing the country's only regular
            monthly golf magazine since 2015 and organising premium tournaments and corporate engagements.</p>
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
            <p>An exclusive initiative by GolfHouse Holdings Ltd. bringing together business and golf through
            high-impact events, tournaments, and networking platforms for corporate leaders, diplomats,
            and decision-makers.</p>
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
  <div class="container text-center">
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
    <h5 class="fw-bold text-uppercase mb-0" style="color:var(--green-dark); letter-spacing: 0.05em; font-size: 0.95rem;"><i class="bi bi-shield-check me-1 text-gold"></i> Event Partners &amp; Sponsors</h5>
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

<?php if (IS_EARLY_BIRD_ACTIVE && EARLY_BIRD_DEADLINE): ?>
<script>
(function () {
  const deadline = new Date("<?= date('c', strtotime(EARLY_BIRD_DEADLINE)) ?>").getTime();
  const $timer = document.getElementById('earlyBirdCountdown');
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
</body>
</html>
