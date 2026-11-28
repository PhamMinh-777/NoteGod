/* ===== 1. Config ===== */
// Relative URL only (brief forbids hardcoded hosts). Change when backend is ready.
const API_BASE = '/api';
const USE_MOCK = false; // set false once your friends' endpoints exist

/* ===== 2. Mock data (replace with fetch(API_BASE + '/notes') later) ===== */
const MOCK_NOTES = [
  { id: 1, title: 'Shopping list', body: 'Milk, eggs, rice, coffee beans', pinned: true, locked: false,
    shared: null, color: 'butter', attachments: 0, labels: ['Home'], updated: '2026-11-24' },
  { id: 2, title: 'Project ideas', body: 'FinalNote presentation outline and demo script for Friday.', pinned: true,
    locked: false, shared: 'edit', color: 'lavender', attachments: 2, labels: ['Work', 'School'], updated: '2026-11-23' },
  { id: 3, title: 'Diary', body: 'secret', pinned: false, locked: true, shared: null,
    color: 'default', attachments: 0, labels: [], updated: '2026-11-20' },
  { id: 4, title: 'Lecture notes: PHP', body: 'Sessions, PDO prepared statements, password_hash.', pinned: false,
    locked: false, shared: 'read', color: 'sky', attachments: 1, labels: ['School'], updated: '2026-11-19' },
  { id: 5, title: 'Gift ideas', body: 'Books, a mug, a plant.', pinned: false, locked: false,
    shared: null, color: 'rose', attachments: 0, labels: ['Home'], updated: '2026-11-15' },
  { id: 6, title: 'Recipe', body: 'Pho broth: charred onion, ginger, star anise, simmer 3 hours.', pinned: false,
    locked: false, shared: null, color: 'mint', attachments: 0, labels: ['Home'], updated: '2026-11-10' }
];

/* ===== 3. Helpers ===== */
const esc = s => $('<div>').text(s ?? '').html();
const store = () => (localStorage.getItem('fn_user') ? localStorage : sessionStorage);
const getUser = () => {
  try {
    const u = JSON.parse(localStorage.getItem('fn_user') || sessionStorage.getItem('fn_user') || 'null');
    if (u && !u.name) u.name = u.display_name || (u.email ? u.email.split('@')[0] : 'User');
    return u;
  } catch (e) { return null; }
};
const setUser = u => {
  if (u && !u.name) u.name = u.display_name || (u.email ? u.email.split('@')[0] : 'User');
  store().setItem('fn_user', JSON.stringify(u));
};

async function api(endpoint, options = {}) {
  const defaults = {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    credentials: 'same-origin'
  };
  if (options.body instanceof FormData) {
    delete defaults.headers['Content-Type'];
  }
  const config = { ...defaults, ...options, headers: { ...defaults.headers, ...options.headers } };
  const res = await fetch(API_BASE + endpoint, config);
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.success === false) {
    const errorMsg = data.error?.message || data.message || `Request failed (${res.status})`;
    const err = new Error(errorMsg);
    err.details = data.error?.details || null;
    err.status = res.status;
    throw err;
  }
  return data;
}

let NOTES_CACHE = []; // notes from the API; the rest of the code keeps calling loadNotes()
function loadNotes() { return NOTES_CACHE; }
function saveNotes(list) { NOTES_CACHE = list; }


/* ===== 3b. Note password + sharing helpers (mock; real hashing/cooldown belong on the server) ===== */
const hashPw = s => { let h = 5381; for (const c of s) h = ((h << 5) + h + c.charCodeAt(0)) | 0; return 'h' + (h >>> 0).toString(16); };
const checkPw = (n, pw) => n.pw ? hashPw(pw) === n.pw : pw === '1234'; // demo: the "Diary" note password is 1234
const isUnlocked = id => sessionStorage.getItem('fn_unlocked_' + id) === '1';
const setUnlocked = id => sessionStorage.setItem('fn_unlocked_' + id, '1');
const MAX_TRIES = 5, COOLDOWN_MS = 30000;
const lockInfo = id => JSON.parse(localStorage.getItem('fn_lock_' + id) || '{"tries":0,"until":0}');
const saveLockInfo = (id, o) => localStorage.setItem('fn_lock_' + id, JSON.stringify(o));
const MOCK_USERS = ['linh@example.com', 'minh@example.com', 'an@example.com']; // registered accounts
const escAttr = s => esc(s).replace(/"/g, '&quot;');
const fmtDate = d => new Date(d).toLocaleString('en-GB',
  { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

/* ===== 3c. Labels (a label can exist with no notes, so they are stored separately too) ===== */
let LABELS_CACHE = [];
const allLabels = () => [...LABELS_CACHE].sort((a, b) => a.localeCompare(b));
const labelApi = (method, body, query = '') =>
  api('/labels' + query, { method, body: body ? JSON.stringify(body) : undefined })
    .catch(err => alert(err.message || 'Label change failed.'));
function validateLabel(v, ignore) {
  if (!v) return 'Label name is required.';
  if (v.length > 30) return 'Label must be 30 characters or less.';
  if (allLabels().some(l => l !== ignore && l.toLowerCase() === v.toLowerCase())) return 'That label already exists.';
  return '';
}
function addLabel(name) { LABELS_CACHE = [...LABELS_CACHE, name]; labelApi('POST', { name }); }
function renameLabel(oldN, newN) {
  LABELS_CACHE = LABELS_CACHE.map(l => l === oldN ? newN : l);
  NOTES_CACHE = NOTES_CACHE.map(n => ({ ...n, labels: [...new Set(n.labels.map(l => l === oldN ? newN : l))] }));
  labelApi('PUT', { old_name: oldN, name: newN });
}
function deleteLabel(name) { // notes are kept, only the label is removed from them
  LABELS_CACHE = LABELS_CACHE.filter(l => l !== name);
  NOTES_CACHE = NOTES_CACHE.map(n => ({ ...n, labels: n.labels.filter(l => l !== name) }));
  labelApi('DELETE', null, '?name=' + encodeURIComponent(name));
}
const mgr = { editing: null, deleting: null };

/* ===== 4. Theme + preferences ===== */
function applyTheme(t) { document.documentElement.setAttribute('data-theme', t); localStorage.setItem('fn_theme', t); }
applyTheme(localStorage.getItem('fn_theme') || 'light');
document.documentElement.style.setProperty('--font-size', (localStorage.getItem('fn_font') || 16) + 'px');

/* ===== 5. Login page ===== */
function initLogin() {
  $('#login-form').on('submit', async function (e) {
    e.preventDefault();
    const email = $('#email').val().trim(), pw = $('#password').val();
    if (!/^\S+@\S+\.\S+$/.test(email) || pw.length < 6) {
      return $('#login-error').removeClass('d-none').text('Enter a valid email and a password of 6+ characters.');
    }
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#login-error').addClass('d-none');
    try {
      const res = await api('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password: pw })
      });
      const user = res.data.user;
      user.name = user.display_name || user.email.split('@')[0];
      ($('#remember').is(':checked') ? localStorage : sessionStorage).setItem('fn_user', JSON.stringify(user));
      location.href = '/';
    } catch (err) {
      $('#login-error').removeClass('d-none').text(err.message || 'Login failed. Please check your credentials.');
    } finally {
      $btn.prop('disabled', false);
    }
  });
}

/* ===== 6. Notes feed ===== */
const state = { q: '', view: localStorage.getItem('fn_view') || 'grid', filter: 'all', labels: [] };
/* ===== 6h. Note card markup (matches the Figma NoteCard) ===== */
const LABEL_DOTS = ['#6366f1', '#10b981', '#f59e0b', '#0ea5e9', '#ec4899', '#8b5cf6'];
const labelDot = l => LABEL_DOTS[[...l].reduce((h, c) => h + c.charCodeAt(0), 0) % LABEL_DOTS.length];
const nbadge = (cls, ic, text, pulse) =>
  `<span class="nbadge nb-${cls}">${icon(ic, text)}<span>${text}</span>${pulse ? '<i class="pulse"></i>' : ''}</span>`;
function ago(d) {
  const days = Math.floor((Date.now() - new Date(d)) / 86400000);
  return days < 1 ? 'today' : days === 1 ? '1d ago' : days + 'd ago';
}

function cardHtml(n) {
  const rec = (n.recipients || []).length;
  const head = '<div class="nbadges">' + [
    n.pinned ? nbadge('gold', 'pin', 'Pinned') : '',
    n.locked ? nbadge('amber', 'lock', 'Protected') : '',
    n.shared === 'edit' ? nbadge('emerald', 'edit', 'Co-Edit', true) : '',
    n.shared === 'read' ? nbadge('soft', 'eye', 'Shared (View)') : '',
    rec ? nbadge('soft', 'users', 'Shared') : ''
  ].join('') + '</div>';

  if (n.locked) { // placeholder only, no preview of the content
    return `<article class="note-card note-locked" data-id="${n.id}"><div>${head}
      <div class="note-title">${esc(n.title)}</div>
      <div class="lock-box"><span>${icon('lock', 'Locked')} Locked note • Password required</span>
      <span class="lock-btn">Enter password to view</span></div></div></article>`;
  }

  const chips = n.labels.map(l =>
    `<span class="lchip"><i class="ldot" style="background:${labelDot(l)}"></i>${esc(l)}</span>`).join('');
  const att = n.attachments ? `<span class="acount">${icon('clip', 'Attachments')}${n.attachments}</span>` : '';

  let strip = ''; // Shared With Me: sharer, timestamp, permission badge, presence pill
  if (state.filter === 'shared' && n.shared) {
    const edit = n.shared === 'edit', by = n.sharedBy || 'Linh Tran';
    strip = `<div class="share-strip"><span><i class="sav" style="background:#e11d48">${esc(by[0])}</i>Shared by
      <strong>${esc(by)}</strong> <span class="text-muted">• ${fmtDate(n.sharedAt || n.updated + 'T09:00:00')}</span></span>
      <span>${nbadge('emerald', edit ? 'edit' : 'eye', edit ? 'Edit rights' : 'Read-only')}${edit ? '<span class="presence-pill"><i></i>2 editing</span>' : ''}</span></div>`;
  }

  return `<article class="note-card c-${n.color}" data-id="${n.id}">
    <div>${strip}${head}<div class="note-title">${esc(n.title || 'Untitled note')}</div>
      <div class="note-body">${esc(n.body) || 'Empty note'}</div></div>
    <div class="note-foot"><div class="note-meta">${chips}${att}</div>
      <div class="note-time">Edited ${ago(n.updated)}</div></div></article>`;
}

function renderFeed() {
  const q = state.q.toLowerCase();
  const list = loadNotes().filter(n =>
    (state.filter !== 'shared' || n.shared) &&
    (!state.labels.length || n.labels.some(l => state.labels.includes(l))) &&
    (!q || (n.title + ' ' + (n.locked ? '' : n.body)).toLowerCase().includes(q)));
  const pinned = list.filter(n => n.pinned).sort((a, b) => new Date(b.pinned_at || 0) - new Date(a.pinned_at || 0)),
        others = list.filter(n => !n.pinned);
  $('#pinned-title').html(`${icon('pin', 'Pinned')} Pinned notes (${pinned.length}) <span class="cap">Ordered by pinned timestamp</span>`);
  $('#feed-h1').text(state.filter === 'shared' ? 'Shared With Me' : 'All Notes');
  $('#note-count').text(list.length);
  const everyNote = loadNotes();
  $('#cnt-all').text(everyNote.length); $('#cnt-shared').text(everyNote.filter(n => n.shared).length);
  $('#pinned').html(pinned.map(cardHtml).join(''));
  $('#others').html(others.map(cardHtml).join(''));
  $('#pinned-section').toggleClass('d-none', !pinned.length);
  $('#others-title').toggleClass('d-none', !pinned.length || !others.length);
  $('#empty').text(state.filter === 'shared' && !q ? 'Nothing has been shared with you yet.' : 'No notes found.')
    .toggleClass('d-none', list.length > 0);
  $('.notes').removeClass('grid list').addClass(state.view);
  $('.view-toggle button').removeClass('active').filter(`[data-view=${state.view}]`).addClass('active');
}

function renderSidebarLabels() {
  $('#label-list').html(allLabels().map(l =>
    `<a class="side-link${state.labels.includes(l) ? ' active' : ''}" href="#" data-label="${escAttr(l)}"><i class="ldot" style="background:${labelDot(l)}"></i>${esc(l)}</a>`).join('')
    || '<div class="small text-muted px-3">No labels yet</div>');
  renderLabelChips();
}

function renderLabelChips() { // the horizontal filter row above the feed
  $('#label-chips').html(`<button class="fchip${state.labels.length ? '' : ' active'}" data-label="">All Labels</button>` +
    allLabels().map(l => `<button class="fchip${state.labels.includes(l) ? ' active' : ''}" data-label="${escAttr(l)}"><i class="ldot" style="background:${labelDot(l)}"></i>${esc(l)}</button>`).join(''));
}

function renderLabelManager() {
  const counts = {};
  loadNotes().forEach(n => n.labels.forEach(l => counts[l] = (counts[l] || 0) + 1));
  const labels = allLabels();
  $('#label-manage-list').html(labels.length ? labels.map(l => {
    const a = escAttr(l);
    if (mgr.editing === l) return `<li class="list-group-item d-flex px-0">
      <input class="form-control form-control-sm mr-2 label-edit" value="${a}" maxlength="30" aria-label="Rename label">
      <button class="btn btn-sm btn-primary mr-1 label-save" data-label="${a}">Save</button>
      <button class="btn btn-sm btn-light label-cancel">Cancel</button></li>`;
    if (mgr.deleting === l) return `<li class="list-group-item d-flex align-items-center px-0">
      <span class="flex-grow-1 small">Delete "${esc(l)}"? Your notes are kept.</span>
      <button class="btn btn-sm btn-light mr-1 label-cancel">Cancel</button>
      <button class="btn btn-sm btn-danger label-del-yes" data-label="${a}">Delete</button></li>`;
    return `<li class="list-group-item d-flex align-items-center px-0">
      <span class="flex-grow-1 text-truncate">🏷 ${esc(l)} <span class="text-muted small">(${counts[l] || 0})</span></span>
      <button class="btn btn-sm btn-link label-edit-btn" data-label="${a}">Rename</button>
      <button class="btn btn-sm btn-link text-danger label-del" data-label="${a}">Delete</button></li>`;
  }).join('') : '<li class="list-group-item px-0 text-muted">No labels yet. Add one above.</li>');
}

function openUnlock(id) {
  let tick, wasCooling = false;
  const refresh = () => {
    const left = Math.ceil((lockInfo(id).until - Date.now()) / 1000), cooling = left > 0;
    $('#unlock-pw, #unlock-submit').prop('disabled', cooling);
    if (cooling) $('#unlock-error').removeClass('d-none').text(`Too many wrong attempts. Try again in ${left}s.`);
    else if (wasCooling) { $('#unlock-error').addClass('d-none'); $('#unlock-pw').trigger('focus'); }
    wasCooling = cooling;
  };
  $('#unlock-pw').val(''); $('#unlock-error').addClass('d-none');
  refresh(); tick = setInterval(refresh, 500);
  $('#unlock-modal').off('hidden.bs.modal shown.bs.modal')
    .on('hidden.bs.modal', () => clearInterval(tick))
    .on('shown.bs.modal', () => $('#unlock-pw').trigger('focus'));
  $('#unlock-form').off('submit').on('submit', function (e) {
    e.preventDefault();
    if (lockInfo(id).until > Date.now()) return;
    const n = loadNotes().find(x => x.id === id);
    if (n && checkPw(n, $('#unlock-pw').val())) {
      saveLockInfo(id, { tries: 0, until: 0 }); setUnlocked(id);
      location.href = '/editor.php?id=' + id; return;
    }
    const info = lockInfo(id); info.tries++;
    if (info.tries >= MAX_TRIES) { info.tries = 0; info.until = Date.now() + COOLDOWN_MS; saveLockInfo(id, info); refresh(); }
    else { saveLockInfo(id, info);
      $('#unlock-error').removeClass('d-none').text(`Wrong password. ${MAX_TRIES - info.tries} tries left.`); }
    $('#unlock-pw').val('').trigger('focus');
  });
  $('#unlock-modal').modal('show');
}

function initNotes() {
  const user = getUser();
  if (!user) { location.href = '/auth/login.php'; return; } // logged-out redirect

  applyAvatar($('#user-avatar'), user.name);
  $('#user-name').text(user.email);

  renderSidebarLabels();

  let timer; // live search, 300ms debounce, no button
  $('#search').on('input', function () {
    clearTimeout(timer);
    const v = this.value;
    timer = setTimeout(() => { state.q = v; renderFeed(); }, 300);
  });
  $('.view-toggle button').on('click', function () {
    state.view = $(this).data('view'); localStorage.setItem('fn_view', state.view); renderFeed();
  });
  $('.side-link[data-filter]').on('click', function (e) {
    e.preventDefault(); state.filter = $(this).data('filter'); state.labels = [];
    $('.side-link[data-filter]').removeClass('active'); $(this).addClass('active');
    renderSidebarLabels(); renderFeed();
  });
  $('#label-list').on('click', '.side-link', function (e) { // click labels to filter; select several
    e.preventDefault();
    const l = $(this).attr('data-label'), i = state.labels.indexOf(l);
    if (i < 0) state.labels.push(l); else state.labels.splice(i, 1);
    state.filter = 'all';
    $('.side-link[data-filter]').removeClass('active').filter('[data-filter=all]').toggleClass('active', !state.labels.length);
    renderSidebarLabels(); renderFeed();
  });
  $('#new-note, #header-new').on('click', () => location.href = '/editor.php');
  $('#theme-toggle, #theme-btn').on('click', () =>
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));
  $('#logout').on('click', async () => {
    try { await api('/auth/logout', { method: 'POST' }); } catch (e) {}
    localStorage.removeItem('fn_user'); sessionStorage.removeItem('fn_user'); location.href = '/auth/login.php';
  });
  $('#user-label').text(user.name);
  $('#label-chips').on('click', '.fchip', function () {
    const l = $(this).attr('data-label');
    if (!l) state.labels = [];
    else { const i = state.labels.indexOf(l); if (i < 0) state.labels.push(l); else state.labels.splice(i, 1); }
    state.filter = 'all';
    $('.side-link[data-filter]').removeClass('active').filter('[data-filter=all]').toggleClass('active', !state.labels.length);
    renderSidebarLabels(); renderFeed();
  });
  $(document).on('click', '.note-card', function () {
  const id = +$(this).data('id');
  if ($(this).hasClass('note-locked') && !isUnlocked(id)) return openUnlock(id); // locked: ask for password
  location.href = '/editor.php?id=' + id;
});

  // --- label manager ---
  const labelErr = m => $('#label-error').removeClass('d-none').text(m);
  const refreshLabels = () => {
    state.labels = state.labels.filter(l => allLabels().includes(l));
    if (!state.labels.length && state.filter === 'all')
      $('.side-link[data-filter]').removeClass('active').filter('[data-filter=all]').addClass('active');
    renderLabelManager(); renderSidebarLabels(); renderFeed();
  };
  $('#manage-labels').on('click', () => {
    mgr.editing = mgr.deleting = null; $('#label-error').addClass('d-none');
    renderLabelManager(); $('#labels-modal').modal('show');
  });
  $('#label-add-form').on('submit', function (e) {
    e.preventDefault();
    const v = $('#label-new').val().trim(), err = validateLabel(v);
    if (err) return labelErr(err);
    addLabel(v); $('#label-new').val(''); $('#label-error').addClass('d-none'); refreshLabels();
  });
  $('#label-manage-list')
    .on('click', '.label-edit-btn', function () {
      mgr.editing = $(this).attr('data-label'); mgr.deleting = null; $('#label-error').addClass('d-none');
      renderLabelManager(); $('.label-edit').trigger('focus').trigger('select');
    })
    .on('click', '.label-del', function () { mgr.deleting = $(this).attr('data-label'); mgr.editing = null; renderLabelManager(); })
    .on('click', '.label-cancel', function () { mgr.editing = mgr.deleting = null; $('#label-error').addClass('d-none'); renderLabelManager(); })
    .on('keydown', '.label-edit', function (e) { if (e.key === 'Enter') $(this).siblings('.label-save').trigger('click'); })
    .on('click', '.label-save', function () {
      const old = $(this).attr('data-label'), v = $('.label-edit').val().trim();
      if (v !== old) { const err = validateLabel(v, old); if (err) return labelErr(err); renameLabel(old, v); }
      $('#label-error').addClass('d-none'); mgr.editing = null; refreshLabels();
    })
    .on('click', '.label-del-yes', function () { deleteLabel($(this).attr('data-label')); mgr.deleting = null; refreshLabels(); });
  const syncOnline = () => $('#offline-banner').toggleClass('d-none', navigator.onLine);
  window.addEventListener('online', syncOnline); window.addEventListener('offline', syncOnline); syncOnline();
    if (user.is_verified === false || user.verified === false) {
      $('#unverified-banner').removeClass('d-none');
    }
    $('#resend-email').off('click').on('click', async function () {
      const $btn = $(this).prop('disabled', true).text('Sending...');
      try {
        await api('/auth/resend-verification', { method: 'POST' });
        alert('Activation link resent! Please check your email inbox.');
      } catch (err) {
        alert(err.message || 'Could not resend email.');
      } finally {
        $btn.prop('disabled', false).text('Resend Email');
      }
    });
  $('#feed-retry').on('click', loadFeed);
  loadFeed();
  initAsk();
}

/* ===== 5b. Register / forgot / reset / verify ===== */
function showErr(sel, msg) { $(sel).removeClass('d-none').text(msg); }

function initRegister() {
  $('#register-form').on('submit', async function (e) {
    e.preventDefault();
    const email = $('#email').val().trim(), name = $('#display-name').val().trim();
    const p1 = $('#password').val(), p2 = $('#password2').val();
    if (!/^\S+@\S+\.\S+$/.test(email)) return showErr('#register-error', 'Enter a valid email.');
    if (!name) return showErr('#register-error', 'Display name is required.');
    if (p1.length < 8) return showErr('#register-error', 'Password must be at least 8 characters.');
    if (p1 !== p2) return showErr('#register-error', 'Passwords do not match.');

    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#register-error').addClass('d-none');
    try {
      const res = await api('/auth/register', {
        method: 'POST',
        body: JSON.stringify({ email, display_name: name, password: p1, confirm_password: p2 })
      });
      const user = res.data.user;
      user.name = user.display_name || name;
      sessionStorage.setItem('fn_user', JSON.stringify(user));
      location.href = '/';
    } catch (err) {
      showErr('#register-error', err.message || 'Registration failed. Please try again.');
    } finally {
      $btn.prop('disabled', false);
    }
  });
}

function initForgot() {
  $('#forgot-form').on('submit', async function (e) {
    e.preventDefault();
    const email = $('#email').val().trim();
    if (!/^\S+@\S+\.\S+$/.test(email)) return showErr('#forgot-error', 'Enter a valid email.');
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#forgot-error').addClass('d-none');
    try {
      await api('/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email })
      });
      sessionStorage.setItem('fn_reset_email', email);
      location.href = '/auth/reset.php?email=' + encodeURIComponent(email);
    } catch (err) {
      showErr('#forgot-error', err.message || 'Could not send reset code. Please try again.');
    } finally {
      $btn.prop('disabled', false);
    }
  });
}

function initReset() {
  const emailParam = new URLSearchParams(location.search).get('email') || sessionStorage.getItem('fn_reset_email') || '';
  if (emailParam) $('#reset-email').val(emailParam);

  $('#reset-form').on('submit', async function (e) {
    e.preventDefault();
    const email = $('#reset-email').val().trim();
    const otp = $('#otp').val().trim(), p1 = $('#password').val(), p2 = $('#password2').val();
    if (!email) return showErr('#reset-error', 'Email address is required.');
    if (!/^\d{6}$/.test(otp)) return showErr('#reset-error', 'Enter the 6-digit code.');
    if (p1.length < 8) return showErr('#reset-error', 'Password must be at least 8 characters.');
    if (p1 !== p2) return showErr('#reset-error', 'Passwords do not match.');

    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#reset-error').addClass('d-none');
    try {
      await api('/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify({ email, otp, password: p1, confirm_password: p2 })
      });
      alert('Password reset successfully! Please log in with your new password.');
      location.href = '/auth/login.php';
    } catch (err) {
      showErr('#reset-error', err.message || 'Invalid or expired OTP code.');
    } finally {
      $btn.prop('disabled', false);
    }
  });
}

function initVerify() {
  const token = new URLSearchParams(location.search).get('token');
  if (token) {
    api('/auth/verify', {
      method: 'POST',
      body: JSON.stringify({ token })
    }).then(() => {
      $('#verify-success').removeClass('d-none');
      $('#verify-expired').addClass('d-none');
      const u = getUser();
      if (u) { u.is_verified = true; u.verified = true; setUser(u); }
    }).catch(err => {
      $('#verify-expired').removeClass('d-none');
      $('#verify-success').addClass('d-none');
    });
  } else {
    const status = new URLSearchParams(location.search).get('status') || 'success';
    $(status === 'expired' ? '#verify-expired' : '#verify-success').removeClass('d-none');
  }

  $('#resend-verify').on('click', async () => {
    try {
      await api('/auth/resend-verification', { method: 'POST' });
      $('#resend-msg').removeClass('d-none text-danger').addClass('text-success').text('A new activation link has been sent to your email.');
    } catch (err) {
      $('#resend-msg').removeClass('d-none text-success').addClass('text-danger').text(err.message || 'Could not resend email.');
    }
  });
}

/* ===== 6c. Note editor ===== */
const NOTE_COLORS = ['default', 'lavender', 'butter', 'mint', 'rose', 'sky'];

async function initEditor() {
  if (!getUser()) { location.href = '/auth/login.php'; return; }
  const id = +new URLSearchParams(location.search).get('id') || 0;
  let note;
  if (id) {
    try { note = (await api('/notes/' + id)).data.note; }
    catch (err) { location.href = '/'; return; } // not found, not yours, or logged out
  } else {
    note = { id: 0, title: '', body: '', html: '', pinned: false, locked: false, shared: null,
      color: localStorage.getItem('fn_note_color') || 'default', attachments: 0,
      files: [], labels: [], updated: new Date().toISOString() };
  }
  note.files = note.files || [];
  note.recipients = note.recipients || [];
  if (note.locked) { location.href = '/'; return; } // locked notes come in Phase 4
  const readOnly = note.shared === 'read';
  let timer, pending = false, inFlight = false, dirty = false, deleted = false;

  // --- status + autosave (no Save button) ---
  const setStatus = s => $('#save-status').text(
    s === 'saving' ? 'Saving...' : s === 'error' ? 'Could not save. Check your connection.' : 'All changes saved');

  async function persist() {
    if (inFlight) { dirty = true; return; } // a save is running: save again when it finishes
    pending = false;
    note.title = $('#note-title').val().trim();
    note.html = $('#content').html();
    note.body = $('#content').text().trim();
    if (!note.id && !note.title && !note.body) return setStatus('saved'); // never create empty notes
    inFlight = true;
    try {
      const payload = JSON.stringify({ title: note.title, content_rich: note.html, labels: note.labels });
      if (!note.id) { // first save creates the note
        const res = await api('/notes', { method: 'POST', body: payload, keepalive: true });
        note.id = res.data.id;
        history.replaceState(null, '', '/editor.php?id=' + note.id);
      } else {
        await api('/notes/' + note.id, { method: 'PUT', body: payload, keepalive: true });
      }
      setStatus(dirty ? 'saving' : 'saved');
    } catch (err) {
      setStatus('error');
    } finally {
      inFlight = false;
      if (dirty) { dirty = false; persist(); }
    }
  }
  function touch() {
    if (readOnly) return;
    pending = true; setStatus('saving'); clearTimeout(timer); timer = setTimeout(persist, 800);
  }

  // --- fill the form ---
  $('#note-title').val(note.title);
  $('#content').html(note.html || esc(note.body));
  if (readOnly) {
    $('body').addClass('readonly');
    $('#ro-badge').removeClass('d-none');
    $('#note-title').prop('disabled', true);
    $('#content').attr('contenteditable', 'false');
  }
  $('#note-title, #content').on('input', touch);
  $('#toolbar button').on('mousedown', e => e.preventDefault()).on('click', function () {
    document.execCommand($(this).data('cmd')); touch();
  });

  // --- labels ---
  function renderLabels() {
    $('#note-labels').html(note.labels.map((l, i) =>
      `<span class="chip">${esc(l)}<button class="x" data-i="${i}" aria-label="Remove">×</button></span> `).join(''));
    const all = allLabels().filter(l => !note.labels.includes(l));
    $('#label-options').html(all.map(l => `<option value="${escAttr(l)}">`).join(''));
  }
  $('#label-input').on('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const v = this.value.trim();
    if (v && !note.labels.includes(v)) { note.labels.push(v); renderLabels(); touch(); }
    this.value = '';
  });
  $('#note-labels').on('click', '.x', function () {
    note.labels.splice($(this).data('i'), 1); renderLabels(); touch();
  });

  // --- card color ---
  $('#colors').html(NOTE_COLORS.map(c =>
    `<button class="swatch" data-c="${c}" title="${c}" style="background:var(--c-${c})"></button>`).join(''));
  function applyColor() {
    $('#paper').attr('class', 'paper c-' + note.color);
    $('.swatch').removeClass('active').filter(`[data-c=${note.color}]`).addClass('active');
  }
  $('#colors').on('click', '.swatch', function () { note.color = $(this).data('c'); applyColor(); touch(); });

  // --- attachments (mock: stores file name and size only) ---
  function renderFiles() {
    $('#files').html(note.files.map((f, i) =>
      `<li>📎 ${esc(f.name)} <button class="btn btn-link btn-sm p-0 file-x" data-i="${i}">remove</button></li>`).join('')
      || '<li class="text-muted">No attachments</li>');
  }
  $('#file-input').on('change', function () {
    const f = this.files[0]; if (!f) return;
    note.files.push({ name: f.name, size: f.size }); this.value = ''; renderFiles(); touch();
  });
  $('#files').on('click', '.file-x', function () { note.files.splice($(this).data('i'), 1); renderFiles(); touch(); });

  // --- presence avatars (mock; real ones need the WebSocket server) ---
  if (note.shared) $('#presence').html('<span class="avatar" style="background:#e11d48">L</span><span class="avatar" style="background:#0891b2">M</span>');

  // --- options menu ---
  const syncPin = () => $('#opt-pin').text(note.pinned ? 'Unpin' : 'Pin');

    // --- password lock: enable / change / disable ---
  let lockMode = 'enable';
  const syncLock = () => $('#opt-lock').text(note.locked ? 'Change / disable password' : 'Lock with password');
  function setLockMode(m) {
    lockMode = m;
    $('#lock-title').text({ enable: 'Lock with password', change: 'Change password', disable: 'Disable password' }[m]);
    $('#g-current').toggleClass('d-none', m === 'enable');
    $('#g-new, #g-new2').toggleClass('d-none', m === 'disable');
    $('#lock-error').addClass('d-none');
    $('#lock-form input[type=password]').val('');
  }
  $('#opt-lock').on('click', () => {
    $('#lock-mode').toggleClass('d-none', !note.locked);
    $('#mode-change').prop('checked', true);
    setLockMode(note.locked ? 'change' : 'enable');
    $('#lock-modal').modal('show');
  });
  $('input[name=lock-mode]').on('change', function () { setLockMode(this.value); });
  $('#lock-form').on('submit', function (e) {
    e.preventDefault();
    const err = m => $('#lock-error').removeClass('d-none').text(m);
    const cur = $('#lock-current').val(), p1 = $('#lock-new').val(), p2 = $('#lock-new2').val();
    if (lockMode !== 'enable' && !checkPw(note, cur)) return err('Current password is incorrect.');
    if (lockMode !== 'disable') {
      if (p1.length < 4) return err('Password must be at least 4 characters.');
      if (p1 !== p2) return err('Passwords do not match.');
    }
    if (lockMode === 'disable') { note.locked = false; delete note.pw; }
    else { note.locked = true; note.pw = hashPw(p1); }
    setUnlocked(note.id); syncLock(); touch();
    $('#lock-modal').modal('hide');
  });

  // --- sharing: recipients, permissions, revoke ---
  function renderShare() {
    $('#share-list').html(note.recipients.length ? note.recipients.map((r, i) =>
      `<li class="list-group-item d-flex align-items-center px-0">
        <span class="flex-grow-1 text-truncate">${esc(r.email)} ${r.accepted ? '' : '<span class="badge badge-warning">Pending</span>'}</span>
        <select class="custom-select custom-select-sm w-auto mx-2 share-perm" data-i="${i}" aria-label="Permission">
          <option value="read"${r.perm === 'read' ? ' selected' : ''}>Can view</option>
          <option value="edit"${r.perm === 'edit' ? ' selected' : ''}>Can edit</option>
        </select>
        <button class="btn btn-sm btn-link text-danger share-x" data-i="${i}">Revoke</button></li>`).join('')
      : '<li class="list-group-item px-0 text-muted">Not shared with anyone yet.</li>');
  }
  $('#opt-share').on('click', () => { $('#share-error').addClass('d-none'); renderShare(); $('#share-modal').modal('show'); });
  $('#share-form').on('submit', function (e) {
    e.preventDefault();
    const err = m => $('#share-error').removeClass('d-none').text(m);
    const email = $('#share-email').val().trim().toLowerCase();
    if (!/^\S+@\S+\.\S+$/.test(email)) return err('Enter a valid email.');
    if (email === getUser().email.toLowerCase()) return err("You can't share a note with yourself.");
    if (!MOCK_USERS.includes(email)) return err('No registered user has this email.'); // TODO: server check
    if (note.recipients.some(r => r.email === email)) return err('Already shared with this person.');
    note.recipients.push({ email, perm: $('#share-perm').val(), at: new Date().toISOString() });
    $('#share-error').addClass('d-none'); $('#share-email').val(''); renderShare(); touch();
  });
  $('#share-list').on('change', '.share-perm', function () {
    note.recipients[$(this).data('i')].perm = this.value; touch();
  }).on('click', '.share-x', function () {
    note.recipients.splice($(this).data('i'), 1); renderShare(); touch();
  });
  if (note.shared) $('#opt-lock, #opt-share').hide(); // only the owner can lock or share

  // --- delete (confirmation dialog, then DELETE /api/notes/{id}) ---
  $('#opt-delete').on('click', () => $('#delete-modal').modal('show'));
  $('#confirm-delete').on('click', async function () {
    const $b = $(this).prop('disabled', true);
    clearTimeout(timer); deleted = true;
    try {
      if (note.id) await api('/notes/' + note.id, { method: 'DELETE' });
      location.href = '/';
    } catch (err) {
      deleted = false; $b.prop('disabled', false);
      alert(err.message || 'Could not delete the note.');
    }
  });

  const syncOnline = () => $('#offline-banner').toggleClass('d-none', navigator.onLine);
  window.addEventListener('online', syncOnline); window.addEventListener('offline', syncOnline); syncOnline();
  window.addEventListener('beforeunload', () => {
    if (pending && !readOnly && !deleted) { clearTimeout(timer); persist(); }
  });
    $('#opt-pin').on('click', async () => {
    if (!note.id) await persist(); // a brand-new note must be saved before it can be pinned
    if (!note.id) return;          // still empty: nothing to pin
    try {
      const res = await api('/notes/' + note.id + '/pin', { method: 'POST' });
      note.pinned = res.data.pinned;
      $('#opt-pin').text(note.pinned ? 'Unpin' : 'Pin');
    } catch (err) { alert(err.message || 'Could not pin the note.'); }
  });
  initSummary(() => $('#content').text().trim());
  renderLabels(); renderFiles(); applyColor(); syncPin(); syncLock(); setStatus('saved');
}

/* ===== 6d. Profile & preferences ===== */
function applyAvatar($el, name, avatarUrl) { // photo if one is saved, otherwise the default initial avatar
  const img = avatarUrl || localStorage.getItem('fn_avatar');
  if (img) $el.addClass('has-img').css('background-image', `url("${img}")`).text('');
  else $el.removeClass('has-img').css('background-image', '').text((name || '?')[0].toUpperCase());
}

async function initProfile() {
  const msg = (sel, text, ok) => $(sel).removeClass('d-none alert-danger alert-success')
    .addClass(ok ? 'alert-success' : 'alert-danger').text(text);

  let profile = null;
  try {
    const res = await api('/user/profile');
    profile = res.data.user;
    profile.name = profile.display_name || profile.name;
    setUser(profile);
  } catch (e) {
    profile = getUser();
    if (!profile) { location.href = '/auth/login.php'; return; }
  }

  // --- profile: name + avatar ---
  $('#profile-email').val(profile.email);
  $('#profile-name').val(profile.name || profile.display_name);
  applyAvatar($('#avatar-big'), profile.name, profile.avatar_url);

  $('#name-form').on('submit', async function (e) {
    e.preventDefault();
    const name = $('#profile-name').val().trim();
    if (!name) return msg('#name-msg', 'Display name is required.');
    if (name.length > 50) return msg('#name-msg', 'Display name must be 50 characters or less.');
    try {
      const res = await api('/user/profile', {
        method: 'PATCH',
        body: JSON.stringify({ display_name: name })
      });
      profile = res.data.user;
      profile.name = profile.display_name;
      setUser(profile);
      applyAvatar($('#avatar-big'), name, profile.avatar_url);
      msg('#name-msg', 'Name saved successfully.', true);
    } catch (err) {
      msg('#name-msg', err.message || 'Could not update name.');
    }
  });

  $('#avatar-input').on('change', async function () {
    const f = this.files[0]; this.value = '';
    if (!f) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type))
      return msg('#avatar-msg', 'Only JPG, PNG or WebP images are allowed.');
    if (f.size > 2 * 1024 * 1024) return msg('#avatar-msg', 'The image must be 2MB or smaller.');

    const formData = new FormData();
    formData.append('avatar', f);
    try {
      msg('#avatar-msg', 'Uploading photo...', true);
      const res = await api('/user/profile', {
        method: 'POST',
        body: formData
      });
      profile = res.data.user;
      profile.name = profile.display_name;
      localStorage.setItem('fn_avatar', profile.avatar_url);
      setUser(profile);
      applyAvatar($('#avatar-big'), profile.name, profile.avatar_url);
      msg('#avatar-msg', 'Avatar updated successfully.', true);
    } catch (err) {
      msg('#avatar-msg', err.message || 'Could not upload avatar image.');
    }
  });

  $('#avatar-remove').on('click', async () => {
    try {
      const res = await api('/user/avatar', { method: 'DELETE' });
      profile = res.data.user;
      profile.name = profile.display_name;
      localStorage.removeItem('fn_avatar');
      setUser(profile);
      applyAvatar($('#avatar-big'), profile.name, null);
      msg('#avatar-msg', 'Avatar removed. Default avatar restored.', true);
    } catch (err) {
      msg('#avatar-msg', err.message || 'Could not remove avatar.');
    }
  });

  // --- change password ---
  $('#pw-form').on('submit', async function (e) {
    e.preventDefault();
    const cur = $('#pw-current').val(), p1 = $('#pw-new').val(), p2 = $('#pw-new2').val();
    if (!cur) return msg('#pw-msg', 'Enter your current password.');
    if (p1.length < 8) return msg('#pw-msg', 'New password must be at least 8 characters.');
    if (p1 !== p2) return msg('#pw-msg', 'New passwords do not match.');
    if (p1 === cur) return msg('#pw-msg', 'New password must be different from the current one.');

    try {
      await api('/user/change-password', {
        method: 'POST',
        body: JSON.stringify({ current_password: cur, new_password: p1, confirm_password: p2 })
      });
      this.reset();
      msg('#pw-msg', 'Password changed successfully.', true);
    } catch (err) {
      msg('#pw-msg', err.message || 'Current password is incorrect.');
    }
  });

  // --- preferences apply instantly ---
  let prefTimer;
  const saved = () => { $('#pref-msg').text('Saved'); clearTimeout(prefTimer); prefTimer = setTimeout(() => $('#pref-msg').text(''), 1500); };

  const currentTheme = profile.theme || localStorage.getItem('fn_theme') || 'light';
  $(`input[name=theme][value=${currentTheme}]`).prop('checked', true);
  $('input[name=theme]').on('change', async function () {
    applyTheme(this.value);
    saved();
    api('/user/preferences', { method: 'PUT', body: JSON.stringify({ theme: this.value }) }).catch(() => {});
  });

  const size = profile.font_size || +localStorage.getItem('fn_font') || 16;
  $('#font-range').val(size); $('#font-val').text(size);
  let fontTimer;
  $('#font-range').on('input', function () {
    const val = this.value;
    document.documentElement.style.setProperty('--font-size', val + 'px');
    localStorage.setItem('fn_font', val);
    $('#font-val').text(val);
    saved();
    clearTimeout(fontTimer);
    fontTimer = setTimeout(() => {
      api('/user/preferences', { method: 'PUT', body: JSON.stringify({ font_size: val }) }).catch(() => {});
    }, 500);
  });

  $('#pref-colors').html(NOTE_COLORS.map(c =>
    `<button type="button" class="swatch" data-c="${c}" title="${c}" style="background:var(--c-${c})"></button>`).join(''));
  const currentNoteColor = profile.default_note_color || localStorage.getItem('fn_note_color') || 'default';
  const syncColor = (color) => {
    $('#pref-colors .swatch').removeClass('active')
      .filter(`[data-c=${color || 'default'}]`).addClass('active');
  };
  $('#pref-colors').on('click', '.swatch', function () {
    const c = $(this).data('c');
    localStorage.setItem('fn_note_color', c);
    syncColor(c);
    saved();
    api('/user/preferences', { method: 'PUT', body: JSON.stringify({ default_note_color: c }) }).catch(() => {});
  });
  syncColor(currentNoteColor);

  const syncOnline = () => $('#offline-banner').toggleClass('d-none', navigator.onLine);
  window.addEventListener('online', syncOnline); window.addEventListener('offline', syncOnline); syncOnline();
}

/* ===== 6e. AI (mock: replace mockSummary / mockAnswer with fetch calls to your backend) ===== */
const MIN_WORDS = 20; // summaries need at least this many words
const AI_STOP = new Set(['what', 'is', 'on', 'my', 'the', 'did', 'i', 'note', 'notes', 'about', 'are', 'how',
  'do', 'make', 'a', 'an', 'of', 'in', 'to', 'and', 'which', 'me', 'tell', 'show']);
const wordCount = s => s.split(/\s+/).filter(Boolean).length;
const aiFails = () => !navigator.onLine || localStorage.getItem('fn_ai_fail') === '1'; // test switch for the error state

function mockSummary(text) { // TODO: POST API_BASE + '/notes/:id/summary'
  return new Promise((resolve, reject) => setTimeout(() => {
    if (aiFails()) return reject(new Error('AI unavailable'));
    const s = (text.match(/[^.!?]+[.!?]*/g) || [text]).slice(0, 2).join(' ').trim();
    resolve(s.length > 220 ? s.slice(0, 220) + '...' : s);
  }, 1200));
}

function mockAnswer(q) { // TODO: stream from API_BASE + '/ai/ask'; the server returns the answer and its source notes
  const words = (q.toLowerCase().match(/[\p{L}\p{N}]+/gu) || []).filter(w => !AI_STOP.has(w));
  const hits = loadNotes().filter(n => !n.locked).map(n => { // locked notes are never searched
    const t = n.title.toLowerCase(), b = (n.body || '').toLowerCase();
    return { n, score: words.reduce((s, w) => s + (t.includes(w) ? 2 : 0) + (b.includes(w) ? 1 : 0), 0) };
  }).filter(h => h.score > 0).sort((a, b) => b.score - a.score).slice(0, 3);
  if (!hits.length) return { text: "I couldn't find anything about that in your notes.", cites: [] };
  const top = hits[0].n, body = (top.body || '').slice(0, 300);
  return { cites: hits.map(h => h.n),
    text: `According to your note "${top.title}": ${body}` +
      (hits.length > 1 ? ` I also found ${hits.length - 1} other related note${hits.length > 2 ? 's' : ''}.` : '') };
}

function initSummary(getText) { // states: idle, loading, done (regenerate), error, too short
  let busy = false;
  function setAi(state, text) {
    const $b = $('#ai-body').removeClass('err');
    if (state === 'loading') $b.html('<span class="ai-spin"></span>Summarizing...');
    if (state === 'done') $b.removeClass('text-muted').text(text);
    if (state === 'short') $b.addClass('text-muted').text(`This note is too short to summarize. Write at least ${MIN_WORDS} words.`);
    if (state === 'error') $b.addClass('err').text('Could not generate a summary. Please try again.');
    $('#ai-run').prop('disabled', state === 'loading')
      .text(state === 'done' ? 'Regenerate' : state === 'error' ? 'Try again' : 'Summarize');
  }
  $('#ai-run').on('click', () => {
    if (busy) return;
    const text = getText();
    if (wordCount(text) < MIN_WORDS) return setAi('short');
    busy = true; setAi('loading');
    mockSummary(text).then(s => setAi('done', s)).catch(() => setAi('error')).finally(() => busy = false);
  });
}

function initAsk() {
  const STARTERS = ['What is on my shopping list?', 'What did I note about PHP?',
    'What are my project ideas?', 'How do I make pho broth?'];
  let busy = false;
  $('#qa-chips').html(STARTERS.map(q => `<button type="button" class="chip qa-chip">${esc(q)}</button>`).join(''));
  const open = () => { $('#qa-drawer').addClass('open').attr('aria-hidden', 'false'); $('#qa-backdrop').removeClass('d-none'); $('#qa-input').trigger('focus'); };
  const close = () => { $('#qa-drawer').removeClass('open').attr('aria-hidden', 'true'); $('#qa-backdrop').addClass('d-none'); };
  $('#ask-ai').on('click', open);
  $('#qa-close, #qa-backdrop').on('click', close);
  $(document).on('keydown', e => { if (e.key === 'Escape') close(); });

  function ask(q) {
    if (busy || !q) return;
    busy = true; $('#qa-input').val(''); $('#qa-send').prop('disabled', true);
    $('#qa-chips').parent().find('p, #qa-chips').addClass('d-none'); // hide the starters after the first question
    const $log = $('#qa-log'), $a = $('<div class="qa-msg qa-ai">').text('Thinking...');
    $log.append($('<div class="qa-msg qa-user">').text(q), $a);
    const done = () => { busy = false; $('#qa-send').prop('disabled', false); $log.scrollTop($log[0].scrollHeight); };
    setTimeout(() => {
      if (aiFails()) { $a.addClass('qa-err').text('Could not reach the AI service. Please try again.'); return done(); }
      const { text, cites } = mockAnswer(q), words = text.split(' ');
      let i = 0; $a.text('');
      const t = setInterval(() => { // streaming: the answer appears word by word
        $a.text(words.slice(0, ++i).join(' ')); $log.scrollTop($log[0].scrollHeight);
        if (i < words.length) return;
        clearInterval(t);
        if (cites.length) $log.append('<div class="qa-cites">' + cites.map(n =>
          `<a class="cite-card" href="/editor.php?id=${+n.id}"><strong>${esc(n.title)}</strong><span>${esc((n.body || '').slice(0, 70))}</span></a>`).join('') + '</div>');
        done();
      }, 45);
    }, 600);
  }
  $('#qa-chips').on('click', '.qa-chip', function () { ask($(this).text()); });
  $('#qa-form').on('submit', e => { e.preventDefault(); ask($('#qa-input').val().trim()); });
}

/* ===== 6f. Offline cache (IndexedDB) + feed loading/error states ===== */
const NoteCache = (() => {
  const open = () => new Promise((res, rej) => {
    const r = indexedDB.open('finalnote', 1);
    r.onupgradeneeded = () => r.result.createObjectStore('notes', { keyPath: 'id' });
    r.onsuccess = () => res(r.result); r.onerror = () => rej(r.error);
  });
  const run = (mode, fn) => open().then(db => new Promise((res, rej) => {
    const tx = db.transaction('notes', mode), out = fn(tx.objectStore('notes'));
    tx.oncomplete = () => { db.close(); res(out && out.result); };
    tx.onerror = () => { db.close(); rej(tx.error); };
  }));
  // locked notes are cached without their content, so a locked note never leaks while offline
  const safe = n => n.locked ? { ...n, body: '', html: '' } : n;
  return {
    save: list => run('readwrite', st => { st.clear(); list.map(safe).forEach(n => st.put(n)); }).catch(() => {}),
    all: () => run('readonly', st => st.getAll()).then(r => r || []).catch(() => [])
  };
})();

async function loadFeed() {
  $('.feed').removeClass('is-error').addClass('is-loading');
  $('#feed-error').addClass('d-none');
  $('#feed-skeleton').html('<div class="skel"></div>'.repeat(6)).removeClass('d-none');
  const finish = () => { $('.feed').removeClass('is-loading'); $('#feed-skeleton').addClass('d-none'); };

  try {
    const res = await api('/notes');
    LABELS_CACHE = (await api('/labels')).data.labels.map(l => l.name);
    NOTES_CACHE = res.data.notes;
    NoteCache.save(NOTES_CACHE);
  } catch (err) {
    if (err.status === 401) { // session expired
      localStorage.removeItem('fn_user'); sessionStorage.removeItem('fn_user');
      location.href = '/auth/login.php'; return;
    }
    const cached = await NoteCache.all();
    finish();
    if (cached.length) { // server unreachable: show what we cached last time
      NOTES_CACHE = cached;
      $('#offline-banner').removeClass('d-none').text("Couldn't refresh. Showing cached notes.");
      renderFeed();
    } else { $('.feed').addClass('is-error'); $('#feed-error').removeClass('d-none'); }
    return;
  }
  finish(); renderSidebarLabels(); renderFeed();
}

/* ===== 6g. Icons (SVG, colored by CSS) ===== */
const ICON_PATHS = {
  pin: '<line x1="12" x2="12" y1="17" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>',
  lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  eye: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
  edit: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  clip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>'
};
const icon = (name, label) =>
  `<svg class="ic ic-${name}" viewBox="0 0 24 24" role="img" aria-label="${label}"><title>${label}</title>${ICON_PATHS[name]}</svg>`;
function noteIcons(n) {
  return '<span class="note-icons">' +
    (n.pinned ? icon('pin', 'Pinned') : '') + (n.locked ? icon('lock', 'Locked') : '') +
    (n.shared === 'edit' ? icon('edit', 'Shared, can edit') : n.shared === 'read' ? icon('eye', 'Shared, view only') : '') +
    ((n.recipients || []).length ? icon('users', 'Shared with others') : '') + '</span>';
}

/* ===== 7. Router (runs the section for the current page) ===== */
$(document).ready(() => {
  const page = document.body.dataset.page;
  $(document).on('click', '.pw-toggle', function () {
    const $i = $(this).siblings('input'), show = $i.attr('type') === 'password';
    $i.attr('type', show ? 'text' : 'password'); $(this).toggleClass('on', show);
  });
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  const pages = { login: initLogin, register: initRegister, forgot: initForgot,
                  reset: initReset, verify: initVerify, notes: initNotes, editor: initEditor,
                  profile: initProfile };
  if (pages[page]) pages[page]();
});