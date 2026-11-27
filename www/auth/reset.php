<?php
$title = 'Reset password'; $page = 'reset';
include __DIR__.'/../partials/head.php';
?>
<main class="auth-wrap">
  <form id="reset-form" class="auth-card" novalidate>
    <h1 class="auth-brand">FinalNote</h1>
    <p class="text-muted">Enter the 6-digit code from your email and choose a new password.</p>
    <div id="reset-error" class="alert alert-danger d-none" role="alert"></div>
    <div class="form-group">
      <label for="reset-email">Email address</label>
      <input id="reset-email" type="email" class="form-control" required autocomplete="email">
    </div>
    <div class="form-group">
      <label for="otp">6-digit Code</label>
      <input id="otp" type="text" inputmode="numeric" maxlength="6" class="form-control" required placeholder="e.g. 123456">
    </div>
    <div class="form-group">
      <label for="password">New password</label>
      <input id="password" type="password" class="form-control" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="password2">Confirm new password</label>
      <input id="password2" type="password" class="form-control" required autocomplete="new-password">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Reset password</button>
  </form>
</main>
<?php include __DIR__.'/../partials/foot.php'; ?>