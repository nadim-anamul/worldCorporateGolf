<?php

declare(strict_types=1);

/**
 * Homepage partner / sponsor marquee.
 *
 * @var list<array<string, mixed>> $tournamentSponsors
 */

if (empty($tournamentSponsors)) {
    return;
}

$marqueeSponsors = TournamentSponsorRepository::expandForMarquee($tournamentSponsors);
?>

<section class="partner-section">
  <div class="container text-center mb-4">
    <h5 class="fw-bold text-uppercase mb-0 partner-section__heading">
      <i class="bi bi-shield-check me-1 text-gold"></i> Event Partners &amp; Sponsors
    </h5>
  </div>
  <div class="marquee-container">
    <div class="marquee-content">
      <?php foreach ($marqueeSponsors as $sponsor): ?>
        <?php
          $logoUrl = TournamentSponsorRepository::logoPublicUrl((string)$sponsor['logo_path']);
          if ($logoUrl === '') {
              continue;
          }
        ?>
        <a
          href="<?= htmlspecialchars((string)$sponsor['website_url'], ENT_QUOTES, 'UTF-8') ?>"
          target="_blank"
          rel="noopener noreferrer"
          class="partner-logo-link"
        >
          <img
            src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars((string)$sponsor['name'], ENT_QUOTES, 'UTF-8') ?>"
            loading="lazy"
          />
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
