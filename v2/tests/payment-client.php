<?php
// SIP-021 fase 3: client e flusso POS dei pagamenti TTS, trasporto simulato. Abbinamento on-chain reale
// provato dalla suite Napay tests/docker/pos-tts-payments.sh e dall'e2e POS.
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$work = sys_get_temp_dir() . '/pos-payment-test-' . bin2hex(random_bytes(6));
mkdir($work, 0700); mkdir($work . '/sessions', 0700);
file_put_contents($work . '/key', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
file_put_contents($work . '/ca', 'mock transport only');
class FakePaymentClient extends app\components\NapayLoginClient {
    public $responses = []; public $sent = []; public $calls = 0;
    protected function transport(string $url, string $method, string $body, string $key, ?string $ip, ?string $token, ?string $idempotencyKey = null): array {
        ++$this->calls; $this->sent[] = [$url, $method, $body, $token, $idempotencyKey];
        return array_shift($this->responses) ?? [];
    }
}
$checks = 0;
$check = static function ($condition, string $label) use (&$checks) { if (!$condition) throw new RuntimeException($label); ++$checks; };
$uuid = static function (): string { $b = random_bytes(16); $b[6] = chr(ord($b[6]) & 0x0f | 0x40); $b[8] = chr(ord($b[8]) & 0x3f | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); };
$json = static fn($data, int $status) => ['status' => $status, 'type' => 'application/json', 'body' => json_encode($data)];
$error = static fn(string $code, int $status) => $json(['error' => ['code' => $code, 'message' => 'x']], $status);
$unavailable = ['ok' => false, 'error' => 'backend_unavailable'];
$goes = static fn($response, string $route): bool => $response instanceof yii\web\Response && $response->statusCode === 302
    && (str_contains(urldecode((string) $response->headers->get('Location')), 'r=' . $route) || str_contains((string) $response->headers->get('Location'), '/' . $route));
try {
    $client = new FakePaymentClient(['clientKeyFile' => $work . '/key', 'caFile' => $work . '/ca']);
    $token = 'napos_' . str_repeat('A', 43);
    $opening = ['id' => $uuid(), 'terminal_id' => $uuid(), 'instance_id' => $uuid(), 'generation' => '3', 'state' => 'active'];
    $base = 'https://napay.local/api/pos/v1';
    $address = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';
    $reference = bin2hex(random_bytes(16));
    $pending = ['id' => $uuid(), 'opening_id' => $opening['id'], 'amount' => '12.34', 'currency' => 'TTS', 'state' => 'pending', 'to_address' => $address,
        'expires_at' => time() + 900, 'qr' => $address . '&amount=12.34&message=napay:' . $reference, 'tx_hash' => null, 'paid_at' => null];
    $paid = array_replace($pending, ['state' => 'paid', 'tx_hash' => '0x' . str_repeat('ab', 32), 'paid_at' => time()]);
    $key = $uuid();

    // --- client
    $client->responses = [$json($pending, 201)];
    $check($client->createPayment($token, $opening, 1234, $key) === ['ok' => true, 'payment' => $pending], 'Payment created');
    $check(end($client->sent) === [$base . '/openings/' . $opening['id'] . '/payments', 'POST',
        json_encode(['instance_id' => $opening['instance_id'], 'generation' => '3', 'amount_cents' => 1234]), $token, $key], 'POST with opening proof, integer cents, bearer and Idempotency-Key');
    $client->responses = [$json($pending, 200)];
    $check($client->createPayment($token, $opening, 1234, $key)['ok'], 'Idempotent replay (200) accepted');
    foreach ([['opening_id' => $uuid()], ['state' => 'refunded'], ['currency' => 'EUR'], ['amount' => '12.3'], ['qr' => $address . '&amount=99.00&message=napay:' . $reference],
        ['qr' => '0x' . str_repeat('1', 40) . '&amount=12.34&message=napay:' . $reference], ['tx_hash' => '0x' . str_repeat('ab', 32)], ['expires_at' => '1'], ['extra' => 1]] as $bad) {
        $client->responses = [$json(array_merge($pending, $bad), 201)];
        $check($client->createPayment($token, $opening, 1234, $key) === $unavailable, 'Mismatched or malformed payment refused: ' . json_encode(array_keys($bad)));
    }
    $client->responses = [$json(array_replace($paid, ['tx_hash' => null]), 200)];
    $check($client->viewPayment($token, $opening, $pending['id']) === $unavailable, 'Paid without tx hash refused');
    foreach (['opening_invalid' => 409, 'idempotency_conflict' => 409, 'payment_unavailable' => 503, 'not_found' => 404, 'invalid_token' => 401, 'validation_failed' => 400] as $code => $status) {
        $client->responses = [$error($code, $status)];
        $check($client->createPayment($token, $opening, 1234, $key) === ['ok' => false, 'error' => $code], 'Public error mapped: ' . $code);
    }
    $client->responses = [$error('payment_unavailable', 409)];
    $check($client->createPayment($token, $opening, 1234, $key) === $unavailable, 'Code with unexpected status fails closed');
    $client->responses = [['status' => 429, 'type' => 'application/json', 'body' => json_encode(['error' => ['code' => 'rate_limited', 'message' => 'x']]), 'retry' => '5']];
    $check($client->createPayment($token, $opening, 1234, $key) === ['ok' => false, 'error' => 'rate_limited', 'retry' => 5], 'Rate limit with Retry-After');
    foreach ([[], ['status' => 201, 'type' => 'text/html', 'body' => json_encode($pending)], $json($pending, 201) + ['setCookie' => true], ['status' => 201, 'type' => 'application/json', 'body' => '{bad']] as $reply) {
        $client->responses = [$reply];
        $check($client->createPayment($token, $opening, 1234, $key) === $unavailable, 'Unexpected transport reply fails closed');
    }
    $calls = $client->calls;
    $check($client->createPayment($token, $opening, 0, $key) === $unavailable && $client->createPayment($token, $opening, 100001, $key) === $unavailable
        && $client->createPayment($token, $opening, 100, 'key') === $unavailable && $client->createPayment($token, ['id' => 'x'] + $opening, 100, $key) === $unavailable
        && $client->viewPayment($token, $opening, 'not-a-uuid') === $unavailable && $client->calls === $calls, 'Invalid amount, key, custody or ID blocked before transport');
    $client->responses = [$json($paid, 200)];
    $check($client->viewPayment($token, $opening, $pending['id']) === ['ok' => true, 'payment' => $paid] && end($client->sent)[1] === 'GET' && end($client->sent)[2] === ''
        && end($client->sent)[0] === $base . '/payments/' . $pending['id'], 'Status GET without body');
    $client->responses = [$json(array_replace($paid, ['id' => $uuid()]), 200)];
    $check($client->viewPayment($token, $opening, $pending['id']) === $unavailable, 'Status must describe the requested payment');
    $client->responses = [$json(array_replace($pending, ['state' => 'cancelled']), 200)];
    $check($client->cancelPayment($token, $opening, $pending['id'])['payment']['state'] === 'cancelled'
        && end($client->sent)[2] === json_encode(['instance_id' => $opening['instance_id'], 'generation' => '3']), 'Cancel sends the opening proof');
    $client->responses = [$error('payment_not_pending', 409)];
    $check($client->cancelPayment($token, $opening, $pending['id']) === ['ok' => false, 'error' => 'payment_not_pending'], 'Cancel of a closed payment: payment_not_pending');

    // --- controller: pagamenti solo dalla sessione server, browser senza token
    $app = new yii\web\Application(['id' => 'pos-payment-test', 'basePath' => dirname(__DIR__), 'language' => 'it-IT', 'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'hostInfo' => 'https://pos.localhost', 'url' => '/', 'scriptUrl' => '/index.php', 'scriptFile' => dirname(__DIR__) . '/web/index.php', 'enableCsrfValidation' => false],
        'session' => ['name' => 'POSPAYMENTTEST', 'savePath' => $work . '/sessions'],
        'napayLogin' => $client, 'operatorSession' => ['class' => app\components\OperatorSession::class],
    ]]);
    $controller = new app\controllers\PaymentController('payment', $app); $controller->layout = false; $app->controller = $controller;
    $check($goes($controller->actionCreate(), 'site/login'), 'No operator: login');
    $app->operatorSession->establish(['token' => $token, 'expires' => time() + 3600, 'name' => 'Mario <Rossi>']);
    $check($goes($controller->actionCreate(), 'site/account'), 'No opening: terminal list');
    $app->operatorSession->keepOpening($opening, 'Negozio <b> - Cassa');
    foreach (['0', '1000,01', '12,345', 'abc'] as $bad) {
        $app->request->setBodyParams(['AmountForm' => ['amount' => $bad], 'key' => $key]);
        $calls = $client->calls;
        $html = $controller->actionCreate();
        $check(is_string($html) && $app->response->statusCode === 422 && $client->calls === $calls, 'Invalid amount stays on the till, nothing sent: ' . $bad);
        $app->response->statusCode = 200;
    }
    $app->request->setBodyParams(['AmountForm' => ['amount' => '12,34'], 'key' => $key]);
    $client->responses = [$json($pending, 201)];
    $result = $controller->actionCreate();
    $check($goes($result, 'payment/view') && $app->operatorSession->ownsPayment($pending['id']) && json_decode(end($client->sent)[2], true)['amount_cents'] === 1234
        && end($client->sent)[4] === $key, 'Create: integer cents, form key, payment remembered, redirect to its page');
    $client->responses = [$json($pending, 200)];
    $html = $controller->actionView($pending['id']);
    $check(is_string($html) && str_contains($html, '<svg') && !str_contains($html, '<?xml') && !str_contains($html, '<desc') && str_contains($html, '12.34')
        && str_contains($html, 'data-payment-status') && str_contains($html, 'Negozio &lt;b&gt; - Cassa') && !str_contains($html, $token)
        && str_contains($html, 'In attesa del pagamento'), 'Pending page: inline QR without prolog or text, polling hook, encoded label, no token');
    $client->responses = [$json($paid, 200)];
    $check($controller->actionStatus($pending['id']) === ['status' => 'paid'], 'Status JSON carries only the state');
    $check($controller->actionStatus($uuid()) === ['status' => 'closed'], 'Status of a payment not created here: closed, no call');
    try { $controller->actionView($uuid()); $check(false, 'Foreign payment page is 404'); } catch (yii\web\NotFoundHttpException $e) { $check(true, 'Foreign payment page is 404'); }
    $client->responses = [$json($paid, 200)];
    $html = $controller->actionView($pending['id']);
    $check(str_contains($html, 'Pagato') && str_contains(urldecode($html), 'payment/receipt') && !str_contains($html, '<svg') && !str_contains($html, 'data-payment-status'), 'Paid page: receipt link, no QR, no polling');
    $client->responses = [$json($paid, 200)];
    $response = $controller->actionReceipt($pending['id']);
    $pdf = $response->content ?? stream_get_contents($response->stream[0]);
    $check(str_starts_with((string) $pdf, '%PDF-') && str_contains((string) $response->headers->get('Content-Type'), 'application/pdf')
        && str_contains((string) $response->headers->get('Content-Disposition'), 'inline') && !str_contains((string) $pdf, $token), 'Receipt: inline A7 PDF, no token');
    $app->response->clear();
    $client->responses = [$json($pending, 200)];
    try { $controller->actionReceipt($pending['id']); $check(false, 'No receipt for unpaid payment'); } catch (yii\web\NotFoundHttpException $e) { $check(true, 'No receipt for unpaid payment'); }
    $client->responses = [$error('payment_not_pending', 409)];
    $check($goes($controller->actionCancel($pending['id']), 'payment/view') && str_contains((string) $app->session->getFlash('pos'), 'non è più in attesa'), 'Cancel refused: back to the payment with message');
    $client->responses = [['status' => 503, 'type' => 'application/json', 'body' => '{}']];
    $html = $controller->actionView($pending['id']);
    $check($app->response->statusCode === 503 && str_contains($html, 'non verificato') && $app->operatorSession->opening() !== null, 'Napay unreachable: warning, custody kept');
    $app->response->statusCode = 200;
    $app->request->setBodyParams(['AmountForm' => ['amount' => '5'], 'key' => $uuid()]);
    $client->responses = [$error('payment_unavailable', 503)];
    $html = $controller->actionCreate();
    $check($app->response->statusCode === 503 && str_contains($html, 'wallet del merchant'), 'Merchant without wallet: clear message on the till');
    $app->response->statusCode = 200;
    $client->responses = [$error('opening_invalid', 409)];
    $app->request->setBodyParams(['AmountForm' => ['amount' => '5'], 'key' => $uuid()]);
    $check($goes($controller->actionCreate(), 'site/account') && $app->operatorSession->opening() === null && !$app->operatorSession->ownsPayment($pending['id']),
        'Opening revoked: back to the list, payments of the opening forgotten');
    $app->operatorSession->keepOpening($opening, 'Cassa');
    $app->operatorSession->rememberPayment($pending['id']);
    $client->responses = [$error('invalid_token', 401)];
    $check($controller->actionStatus($pending['id']) === ['status' => 'logout'] && $app->operatorSession->current() === null, 'Revoked session: logout');
    $app->session->close();
    echo "PASS $checks POS payment client/controller checks (mock transport)\n";
} finally {
    foreach (glob($work . '/sessions/*') as $file) unlink($file);
    rmdir($work . '/sessions'); unlink($work . '/key'); unlink($work . '/ca'); rmdir($work);
}
