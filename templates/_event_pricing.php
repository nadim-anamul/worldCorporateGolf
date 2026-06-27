<?php

declare(strict_types=1);

/**
 * Stacked event pricing: struck-through standard fee + prominent offer fee when early bird is active.
 *
 * @var string $pricingVariant  default|compact|btn|on-dark
 */
$pricingVariant = $pricingVariant ?? 'default';
$currency = htmlspecialchars((string)EVENT_CURRENCY, ENT_QUOTES, 'UTF-8');
$standardFmt = number_format((float)EVENT_FEE);
$currentFmt = number_format((float)CURRENT_FEE);
$variantClass = 'price-stack--' . preg_replace('/[^a-z-]/', '', $pricingVariant);

if (!IS_EARLY_BIRD_ACTIVE): ?>
<div class="price-stack <?= htmlspecialchars($variantClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="price-stack__offer price-stack__offer--solo"><?= $currency ?> <?= $standardFmt ?></div>
</div>
<?php return; endif; ?>

<div class="price-stack <?= htmlspecialchars($variantClass, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($pricingVariant === 'default'): ?>
    <div class="price-stack__eyebrow">Early Bird Offer</div>
  <?php endif; ?>
  <div class="price-stack__original"><?= $currency ?> <?= $standardFmt ?></div>
  <div class="price-stack__offer"><?= $currency ?> <?= $currentFmt ?></div>
</div>
