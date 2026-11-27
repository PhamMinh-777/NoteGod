<?php
$title = 'Register'; $page = 'register';
include __DIR__.'/../partials/head.php';
include_once __DIR__.'/../partials/icons.php';
?>
<main class="auth-wrap">
  <form id="register-form" class="auth-card" novalidate>
    <div class="auth-top">
      <div class="logo-row"><span class="logo-mark">F</span><span class="logo-name">FinalNote</span></div>
      <div class="auth-tabs"><a href="/auth/login.php">Sign In</a><a class="active" href="/auth/register.php">Create Account</a></div>
    </div>
    <h1 class="auth-title">Create your FinalNote account</h1>
    <p class="auth-sub">Auto-login enabled: you'll enter your workspace immediately after signup while verifying your email.</p>
    <div id="register-error" class="alert alert-danger d-none" role="alert"></div>

    <div class="form-group">
      <label for="email">Email</label>
      <div class="input-icon"><?= ic('mail') ?>
        <input id="email" type="email" class="form-control" required autocomplete="email" placeholder="you@example.com">
      </div>
    </div>
    <div class="form-group">
      <label for="display-name">Display name</label>
      <div class="input-icon"><?= ic('user') ?>
        <input id="display-name" type="text" class="form-control" required maxlength="50" placeholder="Your name">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-sm-6">
        <label for="password">Password</label>
        <input id="password" type="password" class="form-control plain" required autocomplete="new-password" placeholder="Min. 8 chars">
      </div>
      <div class="form-group col-sm-6">
        <label for="password2">Confirm password</label>
        <input id="password2" type="password" class="form-control plain" required autocomplete="new-password" placeholder="Repeat password">
      </div>
    </div>
    <div class="sec-note"><?= ic('shield') ?>
      <span><strong>Secure by design:</strong> passwords are hashed before they are stored, and you are signed in automatically after signup.</span>
    </div>
    <button class="btn btn-primary btn-block auth-submit" type="submit">Create account &amp; auto-login <?= ic('arrow') ?></button>
    <p class="text-center mt-3 mb-0 text-muted">Already registered? <a href="/auth/login.php" class="font-weight-bold">Log in</a></p>
  </form>
</main>
<?php include __DIR__.'/../partials/foot.php'; ?>