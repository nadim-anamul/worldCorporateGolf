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
$savingsFmt = number_format((float)EVENT_FEE - (float)CURRENT_FEE);
?>

<div class="early-bird-bar">
  <div class="container">
    <div class="early-bird-bar__inner">
      <div class="early-bird-bar__message-wrap">
        <p class="early-bird-bar__line">
          <i class="bi bi-lightning-charge-fill early-bird-bar__icon" aria-hidden="true"></i>
          <strong>Early Bird Offer Active!</strong>
          <span class="early-bird-bar__text">Save <?= $currency ?> <?= $savingsFmt ?>/- by registering now. Offer ends in:</span>
        </p>
        <span class="early-bird-bar__countdown font-monospace">
          <span id="<?= htmlspecialchars($countdownId, ENT_QUOTES, 'UTF-8') ?>" data-early-bird-countdown>00d 00h 00m 00s</span>
        </span>
      </div>
      <div class="early-bird-bar__pricing" aria-label="Early bird pricing">
        <div class="price-stack price-stack--bar-dark">
          <div class="price-stack__original"><?= $currency ?> <?= $standardFmt ?></div>
          <div class="price-stack__offer"><?= $currency ?> <?= $currentFmt ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
