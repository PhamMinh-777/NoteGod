<?php
require_once __DIR__ . '/api/helpers/Auth.php';
$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    header('Location: /auth/login.php');
    exit;
}
$title = 'Profile & Preferences'; $page = 'profile';
include __DIR__.'/partials/head.php';
?>
<div id="offline-banner" class="banner d-none">You're offline. Changes are saved on this device.</div>

<header class="topbar">
  <a class="btn btn-icon" href="/" aria-label="Back to notes">←</a>
  <strong>Profile &amp; Preferences</strong>
</header>

<main class="profile-wrap">

  <section class="pref-card">
    <h2>Profile</h2>
    <div class="d-flex align-items-center mb-3">
      <div id="avatar-big" class="avatar avatar-lg mr-3"></div>
      <div>
        <label class="btn btn-sm btn-outline-secondary mb-1" for="avatar-input">Change photo</label>
        <button type="button" class="btn btn-sm btn-link" id="avatar-remove">Remove</button>
        <input id="avatar-input" type="file" class="d-none" accept="image/jpeg,image/png,image/webp">
        <div class="small text-muted">JPG, PNG or WebP, up to 2MB.</div>
      </div>
    </div>
    <div id="avatar-msg" class="alert d-none py-2" role="alert"></div>

    <form id="name-form" novalidate>
      <div class="form-group">
        <label for="profile-email">Email</label>
        <input id="profile-email" class="form-control" disabled>
      </div>
      <div class="form-group">
        <label for="profile-name">Display name</label>
        <input id="profile-name" class="form-control" maxlength="50">
      </div>
      <div id="name-msg" class="alert d-none py-2" role="alert"></div>
      <button class="btn btn-primary" type="submit">Save name</button>
    </form>
  </section>

  <section class="pref-card">
    <h2>Change password</h2>
    <form id="pw-form" novalidate>
      <div class="form-group">
        <label for="pw-current">Current password</label>
        <input id="pw-current" type="password" class="form-control" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="pw-new">New password</label>
        <input id="pw-new" type="password" class="form-control" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="pw-new2">Confirm new password</label>
        <input id="pw-new2" type="password" class="form-control" autocomplete="new-password">
      </div>
      <div id="pw-msg" class="alert d-none py-2" role="alert"></div>
      <button class="btn btn-primary" type="submit">Change password</button>
    </form>
  </section>

  <section class="pref-card">
    <h2>Preferences <small id="pref-msg" class="text-muted ml-2"></small></h2>

    <div class="form-group">
      <div class="mb-1">Theme</div>
      <div class="custom-control custom-radio custom-control-inline">
        <input type="radio" id="theme-light" name="theme" value="light" class="custom-control-input">
        <label class="custom-control-label" for="theme-light">Light</label>
      </div>
      <div class="custom-control custom-radio custom-control-inline">
        <input type="radio" id="theme-dark" name="theme" value="dark" class="custom-control-input">
        <label class="custom-control-label" for="theme-dark">Dark</label>
      </div>
    </div>

    <div class="form-group">
      <label for="font-range">Font size: <strong id="font-val">16</strong> px</label>
      <input id="font-range" type="range" class="custom-range" min="12" max="24" step="1">
      <p class="font-preview mb-0">The quick brown fox jumps over the lazy dog.</p>
    </div>

    <div class="form-group mb-0">
      <div class="mb-1">Default note color</div>
      <div id="pref-colors" class="colors"></div>
    </div>
  </section>

</main>
<?php include __DIR__.'/partials/foot.php'; ?>