<?php
/**
 * Admin Login Page
 */

declare(strict_types=1);

session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ./view_registration.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Portal Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background-color: #f1f5f9;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      background: #fff;
      border-radius: 1rem;
      box-shadow: 0 10px 30px rgba(13, 54, 64, 0.08);
      border-top: 5px solid #c9a84c;
      padding: 2.5rem 2rem;
    }
    .btn-login {
      background: #144e58;
      color: #fff;
      font-weight: 600;
      border: none;
      border-radius: 50px;
      padding: 0.65rem;
      transition: background 0.2s;
    }
    .btn-login:hover {
      background: #0d3640;
      color: #fff;
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="text-center mb-4">
    <h4 class="fw-bold mb-1" style="color: #0d3640;">Admin Portal</h4>
    <p class="text-muted small">Enter credentials to manage registrations</p>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2 small text-center">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form action="./admin_authenticate.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />
    
    <div class="mb-3">
      <label for="username" class="form-label fw-semibold" style="font-size: 0.9rem;">Username</label>
      <input type="text" class="form-control" id="username" name="username" required autocomplete="username" />
    </div>
    
    <div class="mb-4">
      <label for="password" class="form-label fw-semibold" style="font-size: 0.9rem;">Password</label>
      <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" />
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-login">
        Sign In
      </button>
    </div>
  </form>
</div>

</body>
</html>
