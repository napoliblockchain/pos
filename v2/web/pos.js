'use strict';
const input = document.querySelector('#amount');
if (input) {
  document.querySelectorAll('[data-key]').forEach(button => button.addEventListener('click', () => {
    const key = button.dataset.key;
    let candidate = key === 'back' ? input.value.slice(0, -1) : input.value + key;
    if (key === ',' && input.value === '') candidate = '0,';
    if (input.value === '0' && /^[0-9]$/.test(key)) candidate = key;
    if (candidate === '' || /^(?:0|[1-9][0-9]{0,4})?(?:[.,][0-9]{0,2})?$/.test(candidate)) input.value = candidate;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }));
  document.querySelector('[data-clear]').addEventListener('click', () => { input.value = ''; input.focus(); });
}

// Segnale di vita dell'apertura ogni 30 s (solo informativo per il responsabile, mai sblocco).
const till = document.querySelector('[data-heartbeat]');
if (till) {
  const token = document.querySelector('meta[name="csrf-token"]');
  const beat = () => fetch(till.dataset.heartbeat, {
    method: 'POST', credentials: 'same-origin', cache: 'no-store',
    headers: { 'X-CSRF-Token': token ? token.content : '', 'Accept': 'application/json' },
  }).then(response => response.ok ? response.json() : { status: 'retry' }).then(result => {
    if (result.status === 'closed') window.location.assign('/site/account');
    if (result.status === 'logout') window.location.assign('/site/login');
  }).catch(() => {});
  setInterval(beat, 30000);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') beat(); });
}

const network = document.querySelector('[data-network]');
const showNetwork = () => {
  if (!network) return;
  network.textContent = navigator.onLine ? 'ONLINE' : 'OFFLINE';
  network.classList.toggle('offline', !navigator.onLine);
};
window.addEventListener('online', showNetwork);
window.addEventListener('offline', showNetwork);
showNetwork();

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}
