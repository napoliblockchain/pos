<?php
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require dirname(__DIR__) . '/vendor/autoload.php'; require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$checks = 0;
function check($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); ++$checks; echo "PASS: $label\n"; }
$vars = ['POS_MODE', 'COOKIE_VALIDATION_KEY', 'NAPAY_API_BASE_URL', 'NAPAY_CA_FILE', 'POS_TRUSTED_PROXIES']; $old = [];
foreach ($vars as $name) $old[$name] = getenv($name);
$work = sys_get_temp_dir() . '/pos-prod-' . bin2hex(random_bytes(6)); mkdir($work, 0700); mkdir($work . '/sessions', 0700);
file_put_contents($work . '/key', str_repeat('K', 43));
$load = static fn() => require dirname(__DIR__) . '/config/web.php';
try {
    putenv('COOKIE_VALIDATION_KEY=' . str_repeat('C', 40)); putenv('POS_MODE=production');
    putenv('NAPAY_API_BASE_URL=https://napay.example.invalid:8443/api/pos/v1'); putenv('NAPAY_CA_FILE'); putenv('POS_TRUSTED_PROXIES=10.44.0.0/24,10.45.0.2/32');
    $config = $load();
    check($config['components']['napayLogin']['caFile'] === null, 'Production uses system CA store without an additional file');
    check(array_keys($config['components']['request']['trustedHosts']) === ['10.44.0.0/24', '10.45.0.2/32'], 'Explicit bounded proxy CIDRs accepted');
    check($config['components']['napayLogin']['baseUrl'] === 'https://napay.example.invalid:8443/api/pos/v1', 'Configured production backend kept server-side');
    foreach (['', 'live', 'prod', 'Production'] as $bad) {
        putenv('POS_MODE=' . $bad); $refused = false; try { $load(); } catch (RuntimeException) { $refused = true; }
        check($refused, 'Unsupported mode refused');
    }
    putenv('POS_MODE=production');
    foreach (['http://napay.example.invalid/api/pos/v1', 'https://user:pass@napay.example.invalid/api/pos/v1', 'https://napay.example.invalid/api/pos/v1?x=1', 'https://napay.example.invalid/api/pos/v1#fragment', 'https://napay.example.invalid/api/pos/v1/', 'https://bad..host/api/pos/v1', 'https://napay.example.invalid:0/api/pos/v1'] as $bad) {
        putenv('NAPAY_API_BASE_URL=' . $bad); $refused = false; try { $load(); } catch (RuntimeException) { $refused = true; }
        check($refused, 'Unsafe backend URL refused');
    }
    putenv('NAPAY_API_BASE_URL=https://napay.example.invalid/api/pos/v1');
    foreach (['', '*', '0.0.0.0/0', '10.44.0.0/33', '300.1.2.0/24', '10.0.0.1', '10.0.0.0/24,', '10.0.0.0/24,evil', '::/0'] as $bad) {
        putenv('POS_TRUSTED_PROXIES=' . $bad); $refused = false; try { $load(); } catch (RuntimeException) { $refused = true; }
        check($refused, 'Unsafe proxy setting refused');
    }
    putenv('POS_TRUSTED_PROXIES=10.44.0.0/24');
    $config = $load();
    $config['components']['session']['savePath'] = $work . '/sessions';
    $config['components']['request'] += ['hostInfo' => 'https://pos.example.invalid', 'scriptUrl' => '/index.php', 'scriptFile' => dirname(__DIR__) . '/web/index.php'];
    $app = new yii\web\Application($config);
    $site = new app\controllers\SiteController('site', $app);
    check($site->actionHealth() === ['status' => 'ok', 'mode' => 'production', 'payments' => 'tts-btc'], 'Health reports real mode and no internal data');
    $payment = new app\controllers\PaymentController('payment', $app); $app->controller = $payment;
    $refused = false; try { $payment->actionRequest('anything'); } catch (yii\web\NotFoundHttpException) { $refused = true; }
    check($refused, 'Test-chain payment request is 404 before session/backend access');
    $data = ['id' => '11111111-1111-4111-8111-111111111111', 'amount' => '1.00', 'fiat_amount' => '1.00', 'rate' => '1.00', 'state' => 'pending', 'expires_at' => time() + 900, 'to_address' => '0x' . str_repeat('a', 40)];
    $html = $app->view->renderFile(dirname(__DIR__) . '/views/payment/view.php', ['payment' => $data, 'label' => 'Fixture', 'qr' => '<svg></svg>']);
    check(!str_contains($html, 'data-payment-request=') && !str_contains($html, 'chain di test'), 'Production page has no test-chain copy action');
    class ProductionClientProbe extends app\components\NapayLoginClient {
        public int $calls = 0;
        protected function transport(string $url, string $method, string $body, string $key, ?string $ip, ?string $token, ?string $idempotencyKey = null): array {
            ++$this->calls; return ['status' => 401, 'type' => 'application/json', 'body' => '{"error":{"code":"invalid_credentials"}}'];
        }
    }
    $client = new ProductionClientProbe(['baseUrl' => 'https://napay.example.invalid/api/pos/v1', 'caFile' => null, 'clientKeyFile' => $work . '/key']);
    check($client->login('fixture@example.invalid', 'fixture')['error'] === 'invalid_credentials' && $client->calls === 1, 'Production client permits configured HTTPS and optional additional CA');
    putenv('POS_MODE=preview'); $calls = $client->calls;
    check($client->login('fixture@example.invalid', 'fixture')['error'] === 'backend_unavailable' && $client->calls === $calls, 'Preview keeps its existing local backend/CA restriction');
    putenv('NAPAY_API_BASE_URL'); putenv('POS_TRUSTED_PROXIES');
    $preview = $load();
    check($preview['components']['napayLogin']['caFile'] === '/run/pos-secrets/ca.crt' && array_keys($preview['components']['request']['trustedHosts']) === ['10.251.0.0/24'], 'Preview defaults unchanged');
    putenv('POS_MODE=production');
    check(app\components\PaymentDocuments::modeNotice() === Yii::t('app', 'Payments managed by Napay.'), 'Production receipt and pages without the test chain notice');
    putenv('POS_MODE=preview');
    check(app\components\PaymentDocuments::modeNotice() === Yii::t('app', 'Test chain only: no real money.'), 'Preview receipt and pages keep the test chain notice');
    $app->session->close();
    echo "PASS $checks production mode checks\n";
} finally {
    foreach ($old as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
    foreach (glob($work . '/sessions/*') ?: [] as $file) unlink($file);
    rmdir($work . '/sessions'); unlink($work . '/key'); rmdir($work);
}
