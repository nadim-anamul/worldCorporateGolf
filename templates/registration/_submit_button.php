      <p class="reg-submit-notice text-center text-muted mb-2">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        Participant contribution will be processed securely via SSLCommerz
      </p>
      <div class="reg-submit-wrap">
        <button type="button" id="submitBtn" class="btn btn-complete-registration reg-submit-btn w-100">
          <span class="reg-submit-btn__label">
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
            <span>Complete Registration</span>
          </span>
          <?php if (IS_EARLY_BIRD_ACTIVE): ?>
            <?php $pricingVariant = 'btn'; require dirname(__DIR__) . '/_event_pricing.php'; ?>
          <?php else: ?>
            <span class="reg-submit-btn__fee"><?= htmlspecialchars(EVENT_CURRENCY, ENT_QUOTES, 'UTF-8') ?> <?= number_format(EVENT_FEE) ?></span>
          <?php endif; ?>
        </button>
      </div>
