<?php
/**
 * Global Footer Template
 */

declare(strict_types=1);
?>
<!-- Footer -->
<footer class="text-center py-4 mt-auto">
  <div class="container">
    <p class="mb-0 opacity-75" style="font-size: .85rem; letter-spacing: 0.03em;">
      &copy; <?= date('Y') ?> <?= htmlspecialchars(EVENT_NAME, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.
    </p>
  </div>
</footer>

<!-- JavaScript Bundles -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
