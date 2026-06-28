<?php

declare(strict_types=1);

/**
 * Slim dark sticky early-bird bar for the homepage header.
 *
 * @var string $countdownId
 */
$countdownId = $countdownId ?? 'earlyBirdCountdown';

if (!IS_EARLY_BIRD_ACTIVE) {
    return;
}

$currency = htmlspecialchars((string)EVENT_CURRENCY, ENT_QUOTES, 'UTF-8');
$standardFmt = number_format((float)EVENT_FEE);
$currentFmt = number_format((float)CURRENT_FEE);
$countdownIdEsc = htmlspecialchars($countdownId, ENT_QUOTES, 'UTF-8');
?>

<div class="early-bird-bar">
  <div class="container">
    <div class="early-bird-bar__inner">

      <div class="early-bird-bar__desktop">
        <div class="early-bird-bar__label">
          <i class="bi bi-lightning-charge-fill early-bird-bar__icon early-bird-bar__icon--animated" aria-hidden="true"></i>
          <strong>Early Bird Offer</strong>
        </div>
        <span class="early-bird-bar__countdown font-monospace">
          <span id="<?= $countdownIdEsc ?>" data-early-bird-countdown>00d 00h 00m 00s</span>
        </span>
        <div class="early-bird-bar__pricing" aria-label="Early bird pricing">
          <div class="price-stack price-stack--bar-dark">
            <div class="price-stack__original"><?= $currency ?> <?= $standardFmt ?></div>
            <div class="price-stack__offer"><?= $currency ?> <?= $currentFmt ?></div>
          </div>
        </div>
      </div>

      <div class="early-bird-bar__mobile">
        <div class="early-bird-bar__label">
          <i class="bi bi-lightning-charge-fill early-bird-bar__icon early-bird-bar__icon--animated" aria-hidden="true"></i>
          <strong>Early Bird Offer</strong>
        </div>
        <div class="early-bird-bar__pricing early-bird-bar__pricing--mobile" aria-label="Early bird pricing">
          <div class="price-stack price-stack--bar-dark price-stack--bar-dark-mobile">
            <div class="price-stack__original"><?= $currency ?> <?= $standardFmt ?></div>
            <div class="price-stack__offer"><?= $currency ?> <?= $currentFmt ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
