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
    // --- storico
    // SIP-033 passo 5: voci con il metodo (TTS/BTC) e totali di oggi
    $other = ['id' => $uuid(), 'method' => 'tts', 'amount' => '5.00', 'currency' => 'TTS', 'state' => 'paid', 'created_at' => time() - 600, 'paid_at' => time() - 590,
        'tx_hash' => '0x' . str_repeat('cd', 32), 'rate' => null, 'paid_btc' => null, 'own' => false];
    $mine = ['id' => $pending['id'], 'method' => 'tts', 'amount' => '12.34', 'currency' => 'TTS', 'state' => 'pending', 'created_at' => time(), 'paid_at' => null, 'tx_hash' => null,
        'rate' => null, 'paid_btc' => null, 'own' => true];
    $btcItem = ['id' => $uuid(), 'method' => 'btc', 'amount' => '1.00', 'currency' => 'EUR', 'state' => 'settled', 'created_at' => time() - 300, 'paid_at' => time() - 290,
        'tx_hash' => null, 'rate' => '61700.00', 'paid_btc' => '0.00024680', 'own' => false];
    $totals = ['since' => time() - 3600, 'tts' => ['count' => 1, 'amount' => '5.00'], 'btc' => [['currency' => 'EUR', 'count' => 1, 'amount' => '1.00', 'btc' => '0.00024680']]];
    $client->responses = [$json(['payments' => [$mine, $other], 'totals' => $totals], 200)];
    $check($client->paymentHistory($token, $opening) === ['ok' => true, 'payments' => [$mine, $other], 'totals' => $totals] && end($client->sent)[1] === 'GET' && end($client->sent)[2] === ''
        && end($client->sent)[0] === $base . '/openings/' . $opening['id'] . '/payments', 'History: GET on the opening, list returned');
    foreach ([['qr' => 'x'], ['to_address' => $address], ['own' => 1], ['state' => 'refunded'], ['tx_hash' => null], ['amount' => '5'], ['created_at' => '1'],
        ['method' => 'eth'], ['rate' => '61700.00'], ['paid_btc' => '0.00000001'], ['method' => 'btc']] as $bad) {
        $client->responses = [$json(['payments' => [$mine, array_merge($other, $bad)], 'totals' => $totals], 200)];
        $check($client->paymentHistory($token, $opening) === $unavailable, 'Malformed history item refused: ' . json_encode(array_keys($bad)));
    }
    foreach ([['payments' => array_fill(0, 51, $other), 'totals' => $totals], ['payments' => [$other], 'totals' => $totals, 'next' => 1], ['payments' => ['a' => $other], 'totals' => $totals], [$other],
        ['payments' => [$other]], ['payments' => [$other], 'totals' => ['tts' => $totals['tts']] + $totals], ['payments' => [$other], 'totals' => array_replace($totals, ['btc' => [['currency' => 'BTC'] + $totals['btc'][0]]])],
        ['payments' => [$other], 'totals' => array_replace($totals, ['tts' => ['count' => -1, 'amount' => '5.00']])], ['payments' => [array_replace($btcItem, ['tx_hash' => '0x' . str_repeat('ab', 32)])], 'totals' => $totals],
        ['payments' => [array_replace($btcItem, ['paid_at' => null])], 'totals' => $totals], ['payments' => [array_replace($btcItem, ['currency' => 'TTS'])], 'totals' => $totals]] as $bad) {
        $client->responses = [$json($bad, 200)];
        $check($client->paymentHistory($token, $opening) === $unavailable, 'Malformed history list refused');
    }
    $client->responses = [$error('opening_invalid', 409)];
    $check($client->paymentHistory($token, $opening) === ['ok' => false, 'error' => 'opening_invalid'], 'History error mapped');
    $calls = $client->calls;
    $check($client->paymentHistory($token, ['id' => 'x'] + $opening) === $unavailable && $client->calls === $calls, 'History without a valid opening: nothing sent');

    // --- controller: pagamenti solo dalla sessione server, browser senza token
    putenv('POS_MODE=preview'); // come la sandbox e l'e2e: pulsante di copia della richiesta visibile
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
        $sent = count($client->sent);
        $html = $controller->actionCreate();
        // solo la lettura dei metodi per ridisegnare il tastierino, mai un pagamento
        $check(is_string($html) && $app->response->statusCode === 422 && !in_array('POST', array_column(array_slice($client->sent, $sent), 1), true), 'Invalid amount stays on the till, nothing sent: ' . $bad);
        $app->response->statusCode = 200;
    }
    $app->request->setBodyParams(['AmountForm' => ['amount' => '12,34'], 'key' => $key]);
    $client->responses = [$json($pending, 201)];
    $result = $controller->actionCreate();
    $check($goes($result, 'payment/view') && $app->operatorSession->ownsPayment($pending['id']) && json_decode(end($client->sent)[2], true)['amount_cents'] === 1234
        && end($client->sent)[4] === $key, 'Create: integer cents, form key, payment remembered, redirect to its page');
    $client->responses = [$json($pending, 200)];
    $html = $controller->actionView($pending['id']);
    $check(str_contains($html, 'data-payment-request="/payment/request?id=' . $pending['id'] . '"') && !str_contains($html, 'napay:' . $reference), 'Preview copy button present, reference not in the page');
    $check(is_string($html) && str_contains($html, '<svg') && !str_contains($html, '<?xml') && !str_contains($html, '<desc') && str_contains($html, '12.34')
        && str_contains($html, 'data-payment-status') && str_contains($html, 'Negozio &lt;b&gt; - Cassa') && !str_contains($html, $token)
        && str_contains($html, 'In attesa del pagamento'), 'Pending page: inline QR without prolog or text, polling hook, encoded label, no token');
    $client->responses = [$json($paid, 200)];
    $check($controller->actionStatus($pending['id']) === ['status' => 'paid'], 'Status JSON carries only the state');
    $check($controller->actionStatus($uuid()) === ['status' => 'closed'], 'Status of a payment not created here: closed, no call');
    // richiesta da copiare (solo anteprima): testo del QR, mai per pagamenti altrui o non più in attesa
    putenv('POS_MODE=preview');
    $client->responses = [$json($pending, 200)];
    $check($controller->actionRequest($pending['id']) === ['request' => $pending['qr']], 'Preview: payment request (QR text) for the own pending payment');
    foreach (['foreign payment' => [$uuid(), null], 'paid payment' => [$pending['id'], $json($paid, 200)]] as $label => [$id, $reply]) {
        if ($reply !== null) $client->responses = [$reply];
        try { $controller->actionRequest($id); $check(false, 'No payment request: ' . $label); } catch (yii\web\NotFoundHttpException $e) { $check(true, 'No payment request: ' . $label); }
    }
    putenv('POS_MODE=live');
    $calls = $client->calls;
    try { $controller->actionRequest($pending['id']); $check(false, 'No payment request outside preview'); } catch (yii\web\NotFoundHttpException $e) { $check($client->calls === $calls, 'No payment request outside preview'); }
    putenv('POS_MODE=preview');
    $app->response->format = yii\web\Response::FORMAT_HTML;
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
    $check($app->response->statusCode === 503 && str_contains($html, 'wallet del negozio'), 'Store without wallet: clear message on the till');
    $app->response->statusCode = 200;
    $client->responses = [$json(['payments' => [$mine, $other, array_replace($other, ['id' => $uuid(), 'state' => 'expired', 'tx_hash' => null, 'paid_at' => null])], 'totals' => $totals], 200)];
    $html = $controller->actionHistory();
    $check(is_string($html) && str_contains($html, 'Storico incassi') && str_contains($html, '12.34 TTS') && str_contains($html, 'Scaduto')
        && substr_count(urldecode($html), 'payment/receipt') === 1
        && str_contains(urldecode($html), $other['id']) && !str_contains($html, $token), 'History page: states, one receipt link for the paid item, no token');
    $check(str_contains(urldecode($html), 'id=' . $pending['id']) && str_contains($html, 'Apri'), 'History: own pending payment links to its page');
    $check(str_contains($html, 'Incassato oggi') && str_contains($html, '5,00 TTS') && str_contains($html, '1,00 EUR') && str_contains($html, 'Incassi: 1 &middot; 0.00024680 BTC'),
        'History totals of today: TTS and BTC per method, BTC also in BTC');
    $client->responses = [$json(['payments' => [$btcItem, $mine], 'totals' => $totals], 200)];
    $html = $controller->actionHistory();
    $check(str_contains($html, 'data-method="btc"') && str_contains($html, '&#8383;') && str_contains($html, '1.00 EUR') && str_contains($html, 'Confermato')
        && str_contains($html, '0.00024680 BTC · 1 BTC = 61.700,00 EUR') && substr_count(urldecode($html), 'payment/receipt') === 0, 'History: BTC item with symbol, state, BTC paid and rate (no TTS receipt link)');
    $client->responses = [$json(['payments' => [], 'totals' => ['since' => time(), 'tts' => ['count' => 0, 'amount' => '0.00'], 'btc' => []]], 200)];
    $html = $controller->actionHistory();
    $check(str_contains($html, 'Nessun incasso oggi') && str_contains($html, 'Nessun incasso su questo POS'), 'History empty: no totals today, no payments');
    $client->responses = [['status' => 503, 'type' => 'application/json', 'body' => '{}']];
    $html = $controller->actionHistory();
    $check($app->response->statusCode === 503 && str_contains($html, 'storico non disponibile'), 'History with Napay unreachable: warning');
    $app->response->statusCode = 200;
    $client->responses = [$json(['payments' => [$mine, $other], 'totals' => $totals], 200)];
    $response = $controller->actionReceipt($other['id']);
    $pdf = $response->content ?? stream_get_contents($response->stream[0]);
    $check(str_starts_with((string) $pdf, '%PDF-') && end($client->sent)[0] === $base . '/openings/' . $opening['id'] . '/payments', 'Receipt of a paid payment from the terminal history');
    $app->response->clear();
    foreach (['not paid' => $mine['id'], 'not in history' => $uuid()] as $label => $id) {
        $app->operatorSession->keepOpening($opening, 'Cassa');
        $client->responses = [$json(['payments' => [$mine, $other], 'totals' => $totals], 200)];
        if ($label === 'not paid') $app->session->remove('pos.payments');
        try { $controller->actionReceipt($id); $check(false, 'No receipt from history: ' . $label); } catch (yii\web\NotFoundHttpException $e) { $check(true, 'No receipt from history: ' . $label); }
    }
    // --- SIP-033: pagamenti BTC (client e controller)
    $app->operatorSession->keepOpening($opening, 'Cassa');
    $addr = 'bcrt1qnynpu9auq7msewe4t5lwkv042x6vvp6wxcdmtx';
    $btcPending = ['id' => $uuid(), 'opening_id' => $opening['id'], 'method' => 'btc', 'amount' => '12.34', 'currency' => 'EUR', 'state' => 'pending', 'expires_at' => time() + 900,
        'btc_address' => $addr, 'amount_btc' => '0.00024680', 'qr' => 'bitcoin:' . $addr . '?amount=0.0002468', 'checkout_url' => 'https://btcpay.local/i/INV0000000001',
        'confirmations' => 0, 'rate' => '61700.00', 'order_btc' => '0.00020000', 'network_fee_btc' => '0.00004680', 'paid_btc' => '0.00000000',
        'processing_at' => null, 'settled_at' => null, 'error' => null];
    $btcProcessing = array_replace($btcPending, ['state' => 'processing', 'processing_at' => time(), 'paid_btc' => '0.00024680']);
    $btcSettled = array_replace($btcProcessing, ['state' => 'settled', 'settled_at' => time(), 'qr' => null, 'checkout_url' => null, 'confirmations' => 1]);
    $methods = ['tts' => true, 'btc' => true, 'btc_currency' => 'EUR'];
    $client->responses = [$json($methods, 200)];
    $check($client->paymentMethods($token, $opening) === ['ok' => true, 'methods' => $methods] && end($client->sent)[0] === $base . '/openings/' . $opening['id'] . '/methods'
        && end($client->sent)[1] === 'GET', 'BTC: methods read from the opening');
    foreach ([['btc_currency' => null], ['btc_currency' => 'BTC'], ['extra' => 1], ['btc' => 1]] as $bad) {
        $client->responses = [$json(array_merge($methods, $bad), 200)];
        $check($client->paymentMethods($token, $opening) === $unavailable, 'BTC: malformed methods refused: ' . json_encode($bad));
    }
    $client->responses = [$json(['tts' => true, 'btc' => false, 'btc_currency' => 'EUR'], 200)];
    $check($client->paymentMethods($token, $opening) === $unavailable, 'BTC: currency without BTC refused');
    $client->responses = [$json($btcPending, 201)];
    $check($client->createBtcPayment($token, $opening, 1234, $key) === ['ok' => true, 'payment' => $btcPending]
        && end($client->sent) === [$base . '/openings/' . $opening['id'] . '/btc-payments', 'POST',
            json_encode(['instance_id' => $opening['instance_id'], 'generation' => '3', 'amount_cents' => 1234]), $token, $key], 'BTC: POST with opening proof, cents and Idempotency-Key');
    foreach ([['qr' => 'bitcoin:' . $addr . '?amount=1'], ['qr' => 'bitcoin:bcrt1qother000000000000?amount=0.0002468'], ['checkout_url' => 'http://btcpay.local/i/INV0000000001'],
        ['checkout_url' => 'https://btcpay.local/apps/x/pos'], ['method' => 'tts'], ['currency' => 'TTS'], ['amount_btc' => null], ['amount_btc' => '0.0002468'],
        ['state' => 'paid'], ['state' => 'processing'], ['settled_at' => time()], ['error' => 'Bad Code'], ['opening_id' => $uuid()], ['extra' => 1],
        ['rate' => 61700.0], ['rate' => '61.700,00'], ['order_btc' => '0.0002'], ['network_fee_btc' => null], ['paid_btc' => -1]] as $bad) {
        $client->responses = [$json(array_merge($btcPending, $bad), 201)];
        $check($client->createBtcPayment($token, $opening, 1234, $key) === $unavailable, 'BTC: malformed payment refused: ' . json_encode($bad));
    }
    $client->responses = [$json(array_replace($btcSettled, ['qr' => $btcPending['qr']]), 200)];
    $check($client->viewBtcPayment($token, $opening, $btcPending['id']) === $unavailable, 'BTC: QR on a closed payment refused');
    $client->responses = [$json($btcSettled, 200)];
    $check($client->viewBtcPayment($token, $opening, $btcPending['id']) === ['ok' => true, 'payment' => $btcSettled]
        && end($client->sent)[0] === $base . '/btc-payments/' . $btcPending['id'], 'BTC: status GET on the payment');
    $client->responses = [$json(array_replace($btcSettled, ['id' => $uuid()]), 200)];
    $check($client->viewBtcPayment($token, $opening, $btcPending['id']) === $unavailable, 'BTC: status must describe the requested payment');
    // tastierino: tasto BTC solo se dichiarato da Napay
    $app->request->setBodyParams(['AmountForm' => ['amount' => '0'], 'key' => $key]);
    $client->responses = [$json($methods, 200)];
    $html = $controller->actionBtcCreate();
    $check(is_string($html) && $app->response->statusCode === 422 && str_contains($html, 'Incassa in BTC') && str_contains(urldecode($html), 'payment/btc-create')
        && str_contains($html, 'TTS / EUR') && str_contains($html, 'Incassa TTS'), 'Till: BTC button (formaction) and currency when Napay offers BTC');
    $app->response->statusCode = 200;
    $client->responses = [$json(['tts' => true, 'btc' => false, 'btc_currency' => null], 200)];
    $html = $controller->actionBtcCreate();
    $check(!str_contains($html, 'Incassa in BTC') && !str_contains($html, 'TTS / ') && str_contains($html, 'Incassa TTS'), 'Till: no BTC button when not available');
    $app->response->statusCode = 200;
    $client->responses = [[]];
    $html = $controller->actionBtcCreate();
    $check(!str_contains($html, 'Incassa in BTC') && str_contains($html, 'Incassa TTS'), 'Till: methods unknown (Napay unreachable) = TTS only');
    $app->response->statusCode = 200;
    // creazione e pagina
    $app->request->setBodyParams(['AmountForm' => ['amount' => '12,34'], 'key' => $key]);
    $client->responses = [$json($btcPending, 201)];
    $check($goes($controller->actionBtcCreate(), 'payment/btc-view') && $app->operatorSession->ownsPayment($btcPending['id'])
        && json_decode(end($client->sent)[2], true)['amount_cents'] === 1234, 'BTC create: integer cents, payment remembered, redirect to its page');
    $client->responses = [$json($btcPending, 200)];
    $html = $controller->actionBtcView($btcPending['id']);
    $check(str_contains($html, '<svg') && !str_contains($html, '<?xml') && str_contains($html, '0.00024680') && str_contains($html, $addr) && str_contains($html, '12.34')
        && str_contains($html, 'EUR') && str_contains($html, 'data-state="pending"') && str_contains($html, 'data-payment-status="/payment/btc-status?id=' . $btcPending['id'] . '"')
        && str_contains($html, 'https://btcpay.local/i/INV0000000001') && str_contains($html, 'noopener noreferrer') && str_contains($html, 'data-countdown')
        && !str_contains($html, $token), 'BTC pending page: inline QR, BTC amount, address, countdown, BTCPay fallback link, polling');
    $check(str_contains($html, '1 BTC = 61.700,00 EUR') && str_contains($html, 'ordine 0.00020000 BTC + fee di rete 0.00004680 BTC'),
        'BTC pending page: BTCPay rate and order amount plus network fee (step 5)');
    $client->responses = [$json(array_replace($btcPending, ['btc_address' => null, 'amount_btc' => null, 'qr' => null]), 200)];
    $html = $controller->actionBtcView($btcPending['id']);
    $check(!str_contains($html, '<svg') && str_contains($html, 'QR non ancora disponibile') && str_contains($html, 'https://btcpay.local/i/INV0000000001'), 'BTC without address yet: no QR, fallback link (D2)');
    $client->responses = [$json($btcProcessing, 200)];
    $html = $controller->actionBtcView($btcPending['id']);
    $check(str_contains($html, 'puoi chiudere la vendita') && !str_contains($html, '<svg') && str_contains($html, 'data-state="processing"') && str_contains($html, 'Pagati 0.00024680 BTC, conferme: 0')
        && str_contains($html, '1 BTC = 61.700,00 EUR'),
        'BTC received (0 conf): sale can be closed, still polling for the confirmation (D1)');
    $client->responses = [$json($btcSettled, 200)];
    $html = $controller->actionBtcView($btcPending['id']);
    $check(str_contains($html, 'Pagamento confermato') && !str_contains($html, 'data-payment-status') && !str_contains($html, 'btcpay.local'), 'BTC confirmed: final, no polling, no link');
    foreach (['failed' => 'non creata', 'reconcile_required' => 'sconosciuto', 'invalid' => 'non chiudere la vendita', 'expired' => 'Richiesta di pagamento scaduta'] as $state => $text) {
        $client->responses = [$json(array_replace($btcPending, ['state' => $state, 'qr' => null, 'checkout_url' => null, 'error' => $state === 'expired' ? null : 'invoice_rejected']
            + ($state === 'failed' || $state === 'reconcile_required' ? ['btc_address' => null, 'amount_btc' => null] : [])), 200)];
        $html = $controller->actionBtcView($btcPending['id']);
        $check(str_contains($html, $text) && !str_contains($html, 'data-payment-status') && !str_contains($html, '<svg'), 'BTC closed state shown: ' . $state);
        if ($state === 'expired') $check(str_contains($html, 'Torna alla cassa') && substr_count(urldecode($html), 'site/index"') === 1 && str_contains($html, 'vale 15 minuti'),
            'BTC expired: like BTCPay, expired request and a single Back to the till button');
    }
    $client->responses = [$json($btcProcessing, 200)];
    $check($controller->actionBtcStatus($btcPending['id']) === ['status' => 'processing'], 'BTC status JSON carries only the state');
    $check($controller->actionBtcStatus($uuid()) === ['status' => 'closed'], 'BTC status of a payment not created here: closed, no call');
    try { $controller->actionBtcView($uuid()); $check(false, 'Foreign BTC payment page is 404'); } catch (yii\web\NotFoundHttpException $e) { $check(true, 'Foreign BTC payment page is 404'); }
    $app->request->setBodyParams(['AmountForm' => ['amount' => '5'], 'key' => $uuid()]);
    $client->responses = [$error('payment_unavailable', 503), $json(['tts' => true, 'btc' => false, 'btc_currency' => null], 200)];
    $html = $controller->actionBtcCreate();
    $check($app->response->statusCode === 503 && str_contains($html, 'Pagamenti BTC non disponibili'), 'BTC unavailable: clear message on the till');
    $app->response->statusCode = 200;
    $app->response->format = yii\web\Response::FORMAT_HTML;
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
