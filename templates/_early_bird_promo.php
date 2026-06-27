<?php

declare(strict_types=1);

/**
 * Early bird promo panel: icon, title, countdown, and stacked pricing.
 *
 * @var string $promoVariant   panel|banner|section|form
 * @var string $countdownId    DOM id for countdown span
 */
$promoVariant = $promoVariant ?? 'panel';
$countdownId = $countdownId ?? 'earlyBirdCountdown';

if (!IS_EARLY_BIRD_ACTIVE) {
    return;
}

$variantClass = 'early-bird-promo--' . preg_replace('/[^a-z-]/', '', $promoVariant);
$pricingVariant = $promoVariant === 'banner' ? 'promo-mini' : 'promo';
$titleText = in_array($promoVariant, ['banner', 'form', 'section'], true)
    ? 'Early Bird Active!'
    : 'Early Bird Discount Active!';
?>

<div class="early-bird-promo <?= htmlspecialchars($variantClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="early-bird-promo__main">
    <div class="early-bird-promo__icon" aria-hidden="true">
      <i class="bi bi-gift-fill"></i>
    </div>
    <div class="early-bird-promo__copy">
      <p class="early-bird-promo__title">
        <i class="bi bi-stars"></i> <?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?>
      </p>
      <p class="early-bird-promo__countdown">
        <i class="bi bi-clock"></i>
        <span>Ends in: <strong id="<?= htmlspecialchars($countdownId, ENT_QUOTES, 'UTF-8') ?>" data-early-bird-countdown>00d 00h 00m 00s</strong></span>
      </p>
    </div>
    <div class="early-bird-promo__pricing">
      <?php require __DIR__ . '/_event_pricing.php'; ?>
    </div>
  </div>
</div>
