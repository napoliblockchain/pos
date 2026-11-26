const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const cp = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE || 'playwright');
const origin = 'https://pos.localhost';
const { localState } = require('./local-state.cjs');
const ca = process.env.POS_CA_FILE || path.join(localState('NAPAY_LOCAL_STATE'), 'caddy-root.crt');
let checks = 0;
function check(value, label) { if (!value) throw new Error(label); ++checks; console.log('PASS: ' + label); }
(async () => {
  cp.execFileSync('curl', ['--noproxy', '*', '--resolve', 'pos.localhost:443:127.0.0.1', '--cacert', ca, '--fail', '--silent', origin + '/health']);
  check(true, 'TLS chain verified with shared Caddy CA');
  const certificate = cp.execFileSync('openssl', ['s_client', '-connect', '127.0.0.1:443', '-servername', 'pos.localhost', '-showcerts'], { input: '', stdio: ['pipe', 'pipe', 'ignore'] });
  const pub = cp.execFileSync('openssl', ['x509', '-pubkey', '-noout'], { input: certificate });
  const der = cp.execFileSync('openssl', ['pkey', '-pubin', '-outform', 'der'], { input: pub });
  const pin = crypto.createHash('sha256').update(der).digest('base64');
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', args: ['--ignore-certificate-errors-spki-list=' + pin], headless: true });
  try {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
    const page = await context.newPage(); const errors = [];
    page.on('pageerror', error => errors.push(error.name));
    const response = await page.goto(origin);
    check(response.status() === 200, 'POS Yii2 loads');
    check(response.headers()['content-security-policy'].includes("frame-ancestors 'none'"), 'Restrictive CSP active');
    check(response.headers()['cache-control'].includes('no-store'), 'Terminal responses not cached');
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 950 });
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow at ' + width);
    }
    for (const key of ['1', '2', ',', '3', '4']) await page.locator('[data-key="' + key + '"]').click();
    check(await page.locator('#amount').inputValue() === '12,34', 'Keypad enters two-decimal amount');
    await page.locator('[data-key="back"]').click();
    check(await page.locator('#amount').inputValue() === '12,3', 'Backspace works');
    await page.locator('[data-clear]').click(); await page.locator('[data-key=","]').click();
    check(await page.locator('#amount').inputValue() === '0,', 'Initial decimal separator normalized');
    await page.locator('[data-key="5"]').click();
    await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);
    check(await page.locator('.verified').isVisible(), 'Server validates amount without creating payment');
    const cookie = (await context.cookies()).find(item => item.name === '_pos_csrf');
    check(cookie && cookie.secure && cookie.httpOnly && cookie.sameSite === 'Strict', 'POS CSRF cookie secure and isolated');
    async function post(entries) {
      return page.evaluate(async values => {
        const response = await fetch('/site/index', { method: 'POST', body: new URLSearchParams(values) });
        return { status: response.status, body: await response.text() };
      }, entries);
    }
    check((await post([['AmountForm[amount]', '1']])).status === 400, 'POST without CSRF rejected');
    const token = await page.locator('[name="_pos_csrf"]').inputValue();
    const attack = '<svg onload=alert(1)>';
    const malicious = await post([['_pos_csrf', token], ['AmountForm[amount]', attack]]);
    check(malicious.status === 200 && !malicious.body.includes(attack) && malicious.body.includes('&lt;svg'), 'Invalid text safely encoded');
    check((await post([['_pos_csrf', token], ['AmountForm[amount][]', 'bad']])).status === 200, 'Array input handled without 500');
    check((await post([['_pos_csrf', token], ['AmountForm', 'bad']])).status === 400, 'Malformed form structure rejected');
    for (const route of ['/config/web.php', '/vendor/autoload.php', '/.env', '/protected/config/main.php', '/debug', '/gii', '/site/pay']) {
      const result = await page.goto(origin + route);
      check(result.status() === 404, 'Private or unimplemented route blocked: ' + route);
    }
    await page.goto(origin + '/health');
    const health = JSON.parse(await page.locator('body').innerText());
    check(health.payments === false && health.mode === 'preview', 'Preview cannot execute financial operations');
    check(errors.length === 0, 'No uncaught browser JavaScript errors');
    await page.goto(origin); await page.setViewportSize({ width: 390, height: 950 });
    if (process.env.POS_SCREENSHOT_DIR) {
      fs.mkdirSync(process.env.POS_SCREENSHOT_DIR, { recursive: true, mode: 0o700 });
      await page.screenshot({ path: path.join(process.env.POS_SCREENSHOT_DIR, 'pos-mobile.png'), fullPage: true, animations: 'disabled' });
    }
    console.log('PASS ' + checks + ' POS HTTPS/browser checks');
    await context.close();
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message.replace(/https?:\/\/\S+/g, '[URL]')); process.exitCode = 1; });
