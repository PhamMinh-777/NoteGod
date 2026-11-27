<?php
require_once __DIR__ . '/api/helpers/Auth.php';
$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    header('Location: /auth/login.php');
    exit;
}
$title = 'Note'; $page = 'editor';
include __DIR__.'/partials/head.php';
?>
<div id="offline-banner" class="banner d-none">You're offline. Changes are saved on this device.</div>

<header class="topbar">
  <a class="btn btn-icon" href="/" aria-label="Back to notes">←</a>
  <span id="save-status" class="save-status" aria-live="polite">All changes saved</span>
  <span id="ro-badge" class="badge badge-secondary d-none">Read-only</span>
  <div id="presence" class="presence ml-auto"></div>
  <div class="dropdown" id="options-wrap">
    <button class="btn btn-icon" data-toggle="dropdown" aria-label="Options">⋮</button>
    <div class="dropdown-menu dropdown-menu-right">
      <button class="dropdown-item" id="opt-pin">Pin</button>
      <button class="dropdown-item" id="opt-lock">Lock with password</button>
      <button class="dropdown-item" id="opt-share">Share</button>
      <div class="dropdown-divider"></div>
      <button class="dropdown-item text-danger" id="opt-delete">Delete</button>
    </div>
  </div>
</header>

<main class="editor-wrap">
  <div id="paper" class="paper">
    <input id="note-title" class="note-title-input" placeholder="Title" maxlength="120" aria-label="Title">
    <div class="toolbar" id="toolbar">
      <button type="button" class="btn btn-icon" data-cmd="bold"><b>B</b></button>
      <button type="button" class="btn btn-icon" data-cmd="italic"><i>I</i></button>
      <button type="button" class="btn btn-icon" data-cmd="underline"><u>U</u></button>
      <button type="button" class="btn btn-icon" data-cmd="insertUnorderedList">• List</button>
      <button type="button" class="btn btn-icon" data-cmd="insertOrderedList">1. List</button>
    </div>
    <div id="content" class="note-content" contenteditable="true" data-placeholder="Start writing..."></div>
  </div>

  <aside class="editor-side">
    <h3 class="side-h">Labels</h3>
    <div id="note-labels" class="mb-2"></div>
    <input id="label-input" class="form-control form-control-sm" list="label-options" placeholder="Add label, press Enter" maxlength="30">
    <datalist id="label-options"></datalist>

    <h3 class="side-h">Card color</h3>
    <div id="colors" class="colors"></div>

    <h3 class="side-h">Attachments</h3>
    <ul id="files" class="list-unstyled small mb-2"></ul>
    <label class="btn btn-sm btn-outline-secondary mb-0" for="file-input">＋ Add file</label>
    <input id="file-input" type="file" class="d-none">
    <h3 class="side-h">AI summary</h3>
    <div id="ai-body" class="ai-body small text-muted">Get a short summary of this note.</div>
    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="ai-run">Summarize</button>
  </aside>
</main>

<div class="modal fade" id="delete-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">Delete this note? This can't be undone.</div>
      <div class="modal-footer">
        <button class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="confirm-delete">Delete</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="lock-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="lock-form" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="lock-title">Lock with password</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div id="lock-error" class="alert alert-danger d-none" role="alert"></div>
        <div id="lock-mode" class="mb-3 d-none">
          <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="mode-change" name="lock-mode" value="change" class="custom-control-input" checked>
            <label class="custom-control-label" for="mode-change">Change password</label>
          </div>
          <div class="custom-control custom-radio custom-control-inline">
            <input type="radio" id="mode-disable" name="lock-mode" value="disable" class="custom-control-input">
            <label class="custom-control-label" for="mode-disable">Disable password</label>
          </div>
        </div>
        <div class="form-group" id="g-current">
          <label for="lock-current">Current password</label>
          <input id="lock-current" type="password" class="form-control" autocomplete="current-password">
        </div>
        <div class="form-group" id="g-new">
          <label for="lock-new">New password</label>
          <input id="lock-new" type="password" class="form-control" autocomplete="new-password">
        </div>
        <div class="form-group" id="g-new2">
          <label for="lock-new2">Confirm new password</label>
          <input id="lock-new2" type="password" class="form-control" autocomplete="new-password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="share-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Share note</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <form id="share-form" class="form-row" novalidate>
          <div class="col-12 col-sm-6 mb-2">
            <input id="share-email" type="email" class="form-control" placeholder="Recipient email" aria-label="Recipient email">
          </div>
          <div class="col-6 col-sm-3 mb-2">
            <select id="share-perm" class="custom-select" aria-label="Permission">
              <option value="read">Can view</option>
              <option value="edit">Can edit</option>
            </select>
          </div>
          <div class="col-6 col-sm-3 mb-2"><button class="btn btn-primary btn-block" type="submit">Add</button></div>
        </form>
        <div id="share-error" class="alert alert-danger d-none py-2" role="alert"></div>
        <ul id="share-list" class="list-group list-group-flush mt-2"></ul>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-dismiss="modal">Done</button></div>
    </div>
  </div>
</div>

<?php include __DIR__.'/partials/foot.php'; ?>