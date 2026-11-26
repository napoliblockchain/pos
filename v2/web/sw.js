'use strict';
// Napay POS: solo guscio statico in cache. Pagine, form e API restano sempre dalla rete
// (contengono dati operatore e verifiche Napay); senza rete la navigazione mostra offline.html.
const CACHE = 'napay-pos-shell-v7';
const SHELL = ['/tabler/tabler.min.css', '/pos.css', '/pos.js', '/offline.html', '/manifest.webmanifest', '/brand/icon.svg', '/brand/icon-192.png', '/brand/icon-512.png', '/brand/apple-touch-icon.png', '/favicon.ico'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    return;
  }
  if (SHELL.includes(url.pathname)) {
    // Rete prima: aggiornamenti subito disponibili; la copia in cache serve solo offline.
    event.respondWith(fetch(request).then(response => {
      // copia presa subito: dopo il return il corpo della risposta viene letto dalla pagina e non si puo' piu' clonare
      if (response.ok) {
        const copy = response.clone();
        caches.open(CACHE).then(cache => cache.put(request, copy));
      }
      return response;
    }).catch(() => caches.match(request)));
  }
});
