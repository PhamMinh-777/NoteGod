<?php
require_once __DIR__ . '/../api/helpers/Auth.php';
if (Auth::getCurrentUser()) {
    header('Location: /');
    exit;
}
$title = 'Login'; $page = 'login';
include __DIR__.'/../partials/head.php';
include_once __DIR__.'/../partials/icons.php';
?>
<main class="auth-wrap">
  <form id="login-form" class="auth-card" novalidate>
    <div class="auth-top">
      <div class="logo-row"><span class="logo-mark">F</span><span class="logo-name">FinalNote</span></div>
      <div class="auth-tabs"><a class="active" href="/auth/login.php">Sign In</a><a href="/auth/register.php">Create Account</a></div>
    </div>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to access your synced notes, shared workspaces and AI assistant.</p>
    <div id="login-error" class="alert alert-danger d-none" role="alert"></div>

    <div class="form-group">
      <label for="email">Email address</label>
      <div class="input-icon"><?= ic('mail') ?>
        <input id="email" type="email" class="form-control" required autocomplete="email" placeholder="you@example.com">
      </div>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-baseline">
        <label for="password">Password</label>
        <a href="/auth/forgot.php" class="small font-weight-bold">Forgot password?</a>
      </div>
      <div class="input-icon"><?= ic('lock') ?>
        <input id="password" type="password" class="form-control" required autocomplete="current-password">
        <button type="button" class="pw-toggle" aria-label="Show or hide password"><?= ic('eye', 'eye') ?><?= ic('eye-off', 'off') ?></button>
      </div>
    </div>
    <div class="custom-control custom-checkbox mb-3">
      <input type="checkbox" class="custom-control-input" id="remember">
      <label class="custom-control-label" for="remember">Remember me for 30 days</label>
    </div>
    <button class="btn btn-primary btn-block auth-submit" type="submit">Sign in to workspace <?= ic('arrow') ?></button>
    <p class="text-center mt-3 mb-0 text-muted">New to FinalNote? <a href="/auth/register.php" class="font-weight-bold">Create an account</a></p>
  </form>
</main>
<?php include __DIR__.'/../partials/foot.php'; ?>