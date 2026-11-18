<?php
// SIP-021 fase 2: client e flusso POS delle aperture esclusive, trasporto simulato. Lock e fencing reali
// sono provati dalla suite Napay tests/application/pos-openings.php.
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$work = sys_get_temp_dir() . '/pos-opening-test-' . bin2hex(random_bytes(6));
mkdir($work, 0700); mkdir($work . '/sessions', 0700);
file_put_contents($work . '/key', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
file_put_contents($work . '/ca', 'mock transport only');
class FakeOpeningClient extends app\components\NapayLoginClient {
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
    && (str_contains(urldecode((string) $response->headers->get('Location')), 'r=' . $route) || str_ends_with((string) $response->headers->get('Location'), '/' . $route));
try {
    $client = new FakeOpeningClient(['clientKeyFile' => $work . '/key', 'caFile' => $work . '/ca']);
    $token = 'napos_' . str_repeat('A', 43);
    [$terminal, $instance, $key] = [$uuid(), $uuid(), $uuid()];
    $opening = ['id' => $uuid(), 'terminal_id' => $terminal, 'instance_id' => $instance, 'generation' => '7', 'state' => 'active'];
    $base = 'https://napay.local/api/pos/v1';

    // --- client
    $client->responses = [$json($opening, 201)];
    $check($client->openTerminal($token, $terminal, $instance, $key) === ['ok' => true, 'opening' => $opening], 'Opening accepted');
    $check(end($client->sent) === [$base . '/terminals/' . $terminal . '/openings', 'POST', json_encode(['instance_id' => $instance]), $token, $key], 'POST with instance body, bearer and Idempotency-Key');
    $client->responses = [$json($opening, 200)];
    $check($client->openTerminal($token, $terminal, $instance, $key)['ok'], 'Idempotent replay (200) accepted');
    foreach ([['terminal_id' => $uuid()], ['instance_id' => $uuid()], ['state' => 'revoked'], ['generation' => '0'], ['generation' => 7], ['extra' => 1]] as $bad) {
        $client->responses = [$json(array_merge($opening, $bad), 201)];
        $check($client->openTerminal($token, $terminal, $instance, $key) === $unavailable, 'Mismatched or malformed opening refused: ' . json_encode(array_keys($bad)));
    }
    foreach (['terminal_in_use' => 409, 'idempotency_conflict' => 409, 'opening_invalid' => 409, 'not_found' => 404, 'invalid_token' => 401, 'validation_failed' => 400] as $code => $status) {
        $client->responses = [$error($code, $status)];
        $check($client->openTerminal($token, $terminal, $instance, $key) === ['ok' => false, 'error' => $code], 'Public error mapped: ' . $code);
    }
    $client->responses = [$error('terminal_in_use', 404)];
    $check($client->openTerminal($token, $terminal, $instance, $key) === $unavailable, 'Code with unexpected status fails closed');
    $client->responses = [['status' => 429, 'type' => 'application/json', 'body' => json_encode(['error' => ['code' => 'rate_limited', 'message' => 'x']]), 'retry' => '7']];
    $check($client->openTerminal($token, $terminal, $instance, $key) === ['ok' => false, 'error' => 'rate_limited', 'retry' => 7], 'Rate limit with Retry-After');
    foreach ([[], ['status' => 201, 'type' => 'text/html', 'body' => json_encode($opening)], $json($opening, 201) + ['setCookie' => true], ['status' => 201, 'type' => 'application/json', 'body' => '{bad'], ['status' => 503, 'type' => 'application/json', 'body' => '{}']] as $reply) {
        $client->responses = [$reply];
        $check($client->openTerminal($token, $terminal, $instance, $key) === $unavailable, 'Unexpected transport reply fails closed');
    }
    $calls = $client->calls;
    $check($client->openTerminal($token, 'POS-1', $instance, $key) === $unavailable && $client->openTerminal($token, $terminal, $instance, 'key') === $unavailable
        && $client->openTerminal("bad\r\nX: y", $terminal, $instance, $key) === $unavailable && $client->calls === $calls, 'Malformed IDs or bearer blocked before transport');
    $client->responses = [$json($opening, 200)];
    $check($client->viewOpening($token, $opening) === ['ok' => true, 'opening' => $opening] && end($client->sent)[1] === 'GET' && end($client->sent)[2] === '', 'Verification GET without body');
    $client->responses = [$json(array_merge($opening, ['generation' => '8']), 200)];
    $check($client->viewOpening($token, $opening) === $unavailable, 'Verification must return the very same opening');
    $client->responses = [['status' => 204, 'body' => '']];
    $check($client->heartbeat($token, $opening) === ['ok' => true], 'Heartbeat 204 accepted');
    $check(end($client->sent)[0] === $base . '/openings/' . $opening['id'] . '/heartbeat' && end($client->sent)[2] === json_encode(['instance_id' => $instance, 'generation' => '7']), 'Heartbeat sends instance and generation proof');
    $client->responses = [['status' => 204, 'body' => 'x']];
    $check($client->heartbeat($token, $opening) === $unavailable, '204 with body refused');
    $client->responses = [['status' => 204, 'body' => '']];
    $check($client->release($token, $opening) === ['ok' => true] && str_ends_with(end($client->sent)[0], '/release'), 'Release 204 accepted');
    $calls = $client->calls;
    $check($client->release($token, ['id' => 'x'] + $opening) === $unavailable && $client->calls === $calls, 'Malformed custody never sent');

    // --- controller: apertura custodita solo sul server, verificata a ogni schermata cassa
    $app = new yii\web\Application(['id' => 'pos-opening-test', 'basePath' => dirname(__DIR__), 'language' => 'it-IT', 'components' => [
        'request' => ['cookieValidationKey' => 'fixture-only', 'hostInfo' => 'https://pos.localhost', 'url' => '/', 'scriptUrl' => '/index.php', 'scriptFile' => dirname(__DIR__) . '/web/index.php', 'enableCsrfValidation' => false],
        'session' => ['name' => 'POSOPENINGTEST', 'savePath' => $work . '/sessions'],
        'napayLogin' => $client, 'operatorSession' => ['class' => app\components\OperatorSession::class],
    ]]);
    $controller = new app\controllers\SiteController('site', $app); $controller->layout = false; $app->controller = $controller;
    $app->operatorSession->establish(['token' => $token, 'expires' => time() + 3600, 'name' => 'Fixture']);
    $posInstance = $app->operatorSession->instance();
    $check(is_string($posInstance) && preg_match('/^[0-9a-f-]{36}$/', $posInstance), 'Login creates one POS instance per server session');
    $check($goes($controller->actionIndex(), 'site/account'), 'Cash screen without opening redirects to the terminal list');
    $app->request->setBodyParams(['terminal' => $terminal, 'key' => $key, 'label' => 'Negozio <b> - Cassa']);
    $client->responses = [$json(array_merge($opening, ['instance_id' => $posInstance]), 201)];
    $result = $controller->actionOpen();
    $kept = $app->operatorSession->opening();
    $check($goes($result, 'site/index') && $kept['opening']['instance_id'] === $posInstance && $kept['label'] === 'Negozio <b> - Cassa', 'Open keeps the opening in the server session');
    $check(end($client->sent)[4] === $key && json_decode(end($client->sent)[2], true) === ['instance_id' => $posInstance], 'Open uses the form key and this session instance');
    $client->responses = [$json($kept['opening'], 200)];
    $html = $controller->actionIndex();
    $check(is_string($html) && str_contains($html, 'Negozio &lt;b&gt; - Cassa') && str_contains($html, 'data-heartbeat') && !str_contains($html, $token), 'Verified cash screen: encoded label, heartbeat hook, no token');
    $client->responses = [['status' => 503, 'type' => 'application/json', 'body' => '{}']];
    $html = $controller->actionIndex();
    $check($app->response->statusCode === 503 && str_contains($html, 'non verificata') && $app->operatorSession->opening() !== null, 'Napay unreachable: warning, custody kept');
    $app->response->statusCode = 200;
    $client->responses = [['status' => 204, 'body' => '']];
    $check($controller->actionHeartbeat() === ['status' => 'ok'], 'Heartbeat ok');
    $client->responses = [$error('opening_invalid', 409)];
    $check($controller->actionHeartbeat() === ['status' => 'closed'] && $app->operatorSession->opening() === null, 'Unlocked by manager: heartbeat drops local custody');
    $app->operatorSession->keepOpening($kept['opening'], 'Cassa');
    $client->responses = [$error('opening_invalid', 409)];
    $check($goes($controller->actionIndex(), 'site/account') && $app->operatorSession->opening() === null, 'Invalid opening on cash screen: back to list');
    $app->operatorSession->keepOpening($kept['opening'], 'Cassa');
    $client->responses = [['status' => 503, 'type' => 'application/json', 'body' => '{}']];
    $html = $controller->actionRelease();
    $check(is_string($html) && str_contains($html, 'Chiusura non confermata') && $app->operatorSession->opening() !== null, 'Unconfirmed release keeps custody for retry');
    $client->responses = [['status' => 204, 'body' => '']];
    $check($goes($controller->actionRelease(), 'site/account') && $app->operatorSession->opening() === null, 'Confirmed release clears custody');
    $app->request->setBodyParams(['terminal' => $terminal, 'key' => $uuid(), 'label' => 'Cassa']);
    $client->responses = [$error('terminal_in_use', 409)];
    $check($goes($controller->actionOpen(), 'site/account') && $app->operatorSession->opening() === null
        && str_contains((string) $app->session->getFlash('pos'), 'occupato'), 'Busy terminal: message, nothing kept');
    $app->request->setBodyParams(['terminal' => $terminal, 'key' => $uuid(), 'label' => 'Cassa']);
    $client->responses = [$error('invalid_token', 401)];
    $check($goes($controller->actionOpen(), 'site/login') && $app->operatorSession->current() === null, 'Revoked session: local custody removed');
    $check($controller->actionHeartbeat() === ['status' => 'logout'], 'Heartbeat without session asks for login');
    $app->session->close();
    echo "PASS $checks POS opening client/controller checks (mock transport)\n";
} finally {
    foreach (glob($work . '/sessions/*') as $file) unlink($file);
    rmdir($work . '/sessions'); unlink($work . '/key'); unlink($work . '/ca'); rmdir($work);
}
