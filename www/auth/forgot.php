<?php
$title = 'Forgot password'; $page = 'forgot';
include __DIR__.'/../partials/head.php';
?>
<main class="auth-wrap">
  <form id="forgot-form" class="auth-card" novalidate>
    <h1 class="auth-brand">FinalNote</h1>
    <p class="text-muted">Enter your email and we'll send you a code.</p>
    <div id="forgot-error" class="alert alert-danger d-none" role="alert"></div>
    <div class="form-group">
      <label for="email">Email</label>
      <input id="email" type="email" class="form-control" required autocomplete="email">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Send code</button>
    <p class="text-center mt-3 mb-0"><a href="/auth/login.php">Back to login</a></p>
  </form>
</main>
<?php include __DIR__.'/../partials/foot.php'; ?>