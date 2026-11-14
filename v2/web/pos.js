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

// SIP-021 fase 3: stato del pagamento in attesa, ogni 3 s solo a pagina visibile; ricarica su esito finale.
const payment = document.querySelector('[data-payment-status]');
if (payment) {
  const expires = Number(payment.dataset.expires) * 1000;
  const countdown = payment.querySelector('[data-countdown]');
  let timer = null;
  const tick = () => {
    if (!countdown) return;
    const left = Math.max(0, Math.round((expires - Date.now()) / 1000));
    countdown.textContent = ' (' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') + ')';
  };
  const poll = () => {
    if (document.visibilityState !== 'visible') return;
    fetch(payment.dataset.paymentStatus, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(response => response.ok ? response.json() : { status: 'retry' })
      .then(result => {
        if (['paid', 'expired', 'cancelled'].includes(result.status)) { clearInterval(timer); window.location.reload(); }
        if (result.status === 'closed') window.location.assign('/site/account');
        if (result.status === 'logout') window.location.assign('/site/login');
      }).catch(() => {});
  };
  tick();
  setInterval(tick, 1000);
  timer = setInterval(poll, 3000);
}

const network = document.querySelector('[data-network]');
const showNetwork = () => {
  if (!network) return;
  network.textContent = navigator.onLine ? network.dataset.online : network.dataset.offline;
  network.classList.toggle('offline', !navigator.onLine);
};
window.addEventListener('online', showNetwork);
window.addEventListener('offline', showNetwork);
showNetwork();

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}
