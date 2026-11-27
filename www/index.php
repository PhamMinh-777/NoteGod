<?php
require_once __DIR__ . '/api/helpers/Auth.php';
$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    header('Location: /auth/login.php');
    exit;
}
$title = 'All Notes'; $page = 'notes';
include __DIR__.'/partials/head.php';
?>
<div id="offline-banner" class="banner d-none">You're offline. Showing cached notes.</div>
<div id="unverified-banner" class="banner banner-warn d-none">
  <span>Your account is unverified. Please check your email for the activation link.</span>
  <button class="btn btn-link p-0" id="resend-email">Resend Email</button>
</div>

<header class="topbar">
  <button class="btn btn-icon d-md-none" data-toggle="collapse" data-target="#sidebar" aria-label="Menu">☰</button>
  <a class="brand" href="/">FinalNote</a>
  <input id="search" type="search" class="form-control search" placeholder="Search notes, lectures, tags..." aria-label="Search notes">
  <div class="btn-group view-toggle" role="group" aria-label="View">
    <button class="btn btn-icon active" data-view="grid" title="Grid">▦</button>
    <button class="btn btn-icon" data-view="list" title="List">☰</button>
  </div>
  <button class="btn btn-primary btn-new" id="header-new">＋<span class="d-none d-md-inline"> New Note</span></button>
  <button class="btn btn-icon" id="ask-ai" title="Ask AI about your notes">AI<span class="d-none d-sm-inline"> Q&amp;A</span></button>
  <button class="btn btn-icon d-none d-sm-inline-flex" id="theme-btn" aria-label="Toggle theme">
    <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
  </button>
  <div class="dropdown">
    <button class="userbtn" data-toggle="dropdown" aria-label="User menu">
      <span class="avatar" id="user-avatar">?</span><span id="user-label" class="d-none d-lg-inline"></span>
    </button>
    <div class="dropdown-menu dropdown-menu-right">
      <span class="dropdown-item-text" id="user-name"></span>
      <a class="dropdown-item" href="/profile.php">Profile &amp; Preferences</a>
      <button class="dropdown-item" id="theme-toggle">Toggle dark mode</button>
      <div class="dropdown-divider"></div>
      <button class="dropdown-item" id="logout">Log out</button>
    </div>
  </div>
</header>

<div class="shell">
  <nav id="sidebar" class="sidebar collapse d-md-block">
    <div class="side-title">Workspace</div>
    <a class="side-link active" href="#" data-filter="all">All Notes <span class="cnt" id="cnt-all"></span></a>
    <a class="side-link" href="#" data-filter="shared">Shared With Me <span class="cnt" id="cnt-shared"></span></a>
    <div class="side-title d-flex justify-content-between align-items-center">Labels <button class="btn btn-sm btn-link p-0" id="manage-labels">Manage</button></div>
    <div id="label-list"></div>
  </nav>

  <main class="feed">
    <h1 class="feed-h1"><span id="feed-h1">All Notes</span> <span class="count" id="note-count">0</span></h1>
    <div id="label-chips" class="label-chips"></div>
    <div id="feed-skeleton" class="notes grid d-none" aria-label="Loading notes"></div>
    <div id="feed-error" class="empty d-none">
      <p>Couldn't load your notes. Check your connection and try again.</p>
      <button class="btn btn-primary" id="feed-retry">Try again</button>
    </div>
    <section id="pinned-section" class="d-none">
      <h2 class="feed-title pinned-title" id="pinned-title">Pinned</h2>
      <div id="pinned" class="notes grid"></div>
    </section>
    <section>
      <h2 class="feed-title" id="others-title">Others</h2>
      <div id="others" class="notes grid"></div>
    </section>
    <div id="empty" class="empty d-none">No notes found.</div>
  </main>
</div>

<button class="fab" id="new-note" aria-label="New note">＋</button>

<div class="modal fade" id="unlock-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form id="unlock-form" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">🔒 Locked note</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div id="unlock-error" class="alert alert-danger d-none" role="alert"></div>
        <label for="unlock-pw">Password</label>
        <input id="unlock-pw" type="password" class="form-control" autocomplete="off">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="unlock-submit">Unlock</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="labels-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Manage labels</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <form id="label-add-form" class="form-row mb-2" novalidate>
          <div class="col"><input id="label-new" class="form-control" maxlength="30" placeholder="New label" aria-label="New label"></div>
          <div class="col-auto"><button class="btn btn-primary" type="submit">Add</button></div>
        </form>
        <div id="label-error" class="alert alert-danger d-none py-2" role="alert"></div>
        <ul id="label-manage-list" class="list-group list-group-flush"></ul>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-dismiss="modal">Done</button></div>
    </div>
  </div>
</div>

<div id="qa-backdrop" class="qa-backdrop d-none"></div>
<aside id="qa-drawer" class="qa-drawer" aria-hidden="true" aria-label="Ask AI">
  <div class="qa-head">
    <strong>Ask about your notes</strong>
    <button type="button" class="close" id="qa-close" aria-label="Close">&times;</button>
  </div>
  <div id="qa-log" class="qa-log">
    <p class="text-muted small mb-2">Try one of these, or type your own question:</p>
    <div id="qa-chips" class="qa-chips"></div>
  </div>
  <form id="qa-form" class="qa-form" novalidate>
    <input id="qa-input" class="form-control" maxlength="200" placeholder="Ask a question" aria-label="Question" autocomplete="off">
    <button class="btn btn-primary" type="submit" id="qa-send">Send</button>
  </form>
</aside>

<?php include __DIR__.'/partials/foot.php'; ?>