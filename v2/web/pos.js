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
