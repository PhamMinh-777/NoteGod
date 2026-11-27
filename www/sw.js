/* FinalNote service worker. Must live at the web root (/sw.js). */
const VERSION = 'finalnote-v4'; // change this number to force every browser to refresh its cache
const SHELL = ['/', '/editor.php', '/profile.php', '/auth/login.php', '/auth/register.php',
  '/auth/forgot.php', '/auth/reset.php', '/auth/verify.php', '/assets/css/style.css', '/assets/js/main.js',
  '/manifest.json', '/assets/images/icon-192.png', '/assets/images/icon-512.png'];
const CDN = [
  'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css',
  'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js',
  'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap'
];
const CDN_HOSTS = ['maxcdn.bootstrapcdn.com', 'ajax.googleapis.com', 'cdnjs.cloudflare.com',
  'fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(cache => Promise.allSettled([
    ...SHELL.map(u => cache.add(u)),
    ...CDN.map(u => fetch(new Request(u, { mode: 'no-cors' })).then(r => cache.put(u, r)))
  ])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.pathname.startsWith('/api/')) return; // API calls always use the network

  if (url.origin === location.origin) {
    // Network first, so your edits show up while developing. The cache is the offline fallback.
    e.respondWith(fetch(req).then(res => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy)); }
      return res;
    }).catch(() => caches.match(req, { ignoreSearch: true }).then(hit =>
      hit || (req.mode === 'navigate' ? caches.match('/') : Response.error()))));
  } else if (CDN_HOSTS.includes(url.hostname)) {
    // Cache first for Bootstrap, jQuery and fonts, so they load with no internet.
    e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(res => {
      const copy = res.clone(); caches.open(VERSION).then(c => c.put(req, copy));
      return res;
    })));
  }
});