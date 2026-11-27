<?php
$title = 'Email verification'; $page = 'verify';
include __DIR__.'/../partials/head.php';
?>
<main class="auth-wrap">
  <div class="auth-card text-center">
    <h1 class="auth-brand">FinalNote</h1>
    <div id="verify-success" class="d-none">
      <div class="verify-icon">✅</div>
      <h2 class="h5">Email verified</h2>
      <p class="text-muted">Your account is ready to use.</p>
      <a class="btn btn-primary btn-block" href="/">Go to my notes</a>
    </div>
    <div id="verify-expired" class="d-none">
      <div class="verify-icon">⏰</div>
      <h2 class="h5">Link expired</h2>
      <p class="text-muted">This verification link is no longer valid.</p>
      <button id="resend-verify" class="btn btn-primary btn-block">Resend email</button>
      <p id="resend-msg" class="text-success mt-2 mb-0 d-none">A new email has been sent.</p>
    </div>
  </div>
</main>
<?php include __DIR__.'/../partials/foot.php'; ?>