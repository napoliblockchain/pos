<?php
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$work = sys_get_temp_dir() . '/pos-login-test-' . bin2hex(random_bytes(6));
mkdir($work, 0700); mkdir($work . '/sessions', 0700);
file_put_contents($work . '/key', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
file_put_contents($work . '/ca', 'unit fixture - transport mocked');
class FakeLoginClient extends app\components\NapayLoginClient {
    public $response; public $calls = 0; public $sent;
    protected function send(string $body, string $key, ?string $ip): array {
        ++$this->calls; $this->sent = [json_decode($body, true), $ip]; return $this->response;
    }
}
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    ++$checks;
}
$client = new FakeLoginClient(['caFile' => $work . '/ca', 'clientKeyFile' => $work . '/key']);
$token = 'napos_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$valid = ['access_token' => $token, 'token_type' => 'Bearer', 'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 43200), 'operator' => ['display_name' => 'Fixture <svg onload=alert(1)>']];
function response($data, $status = 201) { return ['status' => $status, 'type' => 'application/json; charset=UTF-8', 'body' => json_encode($data)]; }
try {
    $client->response = response($valid);
    $success = $client->login('operator@example.invalid', 'fixture-only', '198.51.100.9');
    check($success['ok'] && $success['session']['token'] === $token, 'Approved token accepted');
    check($success['session']['name'] === $valid['operator']['display_name'], 'Only operator display name retained');
    check($client->sent === [['email' => 'operator@example.invalid', 'password' => 'fixture-only'], '198.51.100.9'], 'Only approved credentials and validated operator IP sent');
    foreach ([[401,'invalid_credentials','invalid_credentials'],[401,'unauthenticated','backend_unavailable'],[400,'validation_failed','backend_unavailable'],[503,'backend_unavailable','backend_unavailable'],[302,'redirect','backend_unavailable']] as [$status,$code,$expected]) {
        $client->response = response(['error' => ['code' => $code, 'message' => 'NEVER EXPOSE THIS']], $status);
        $result = $client->login('operator@example.invalid', 'fixture-only');
        check(!$result['ok'] && $result['error'] === $expected && !str_contains(json_encode($result), 'NEVER EXPOSE'), 'Upstream errors sanitized');
    }
    $client->response = response(['error' => ['code' => 'rate_limited']], 429) + ['retry' => '42'];
    check($client->login('operator@example.invalid', 'fixture-only')['retry'] === 42, 'Bounded retry honored');
    $client->response['retry'] = '999999999999999';
    check($client->login('operator@example.invalid', 'fixture-only')['retry'] === 60, 'Invalid retry bounded');
    $cases = [];
    foreach ([['access_token','bad'],['token_type','Basic'],['expires_at','2020-01-01T00:00:00Z'],['expires_at','tomorrow'],['expires_at',gmdate('Y-m-d\TH:i:s\Z',time()+90000)],['operator',['id'=>'not-in-contract','display_name'=>'name']],['operator',['display_name'=>['bad']]]] as [$field,$value]) {
        $body = $valid; $body[$field] = $value; $cases[] = response($body);
    }
    $cases[] = response($valid + ['extra' => true]);
    $cases[] = ['status' => 201, 'type' => 'application/json', 'body' => '{bad'];
    $cases[] = ['status' => 201, 'type' => 'application/json', 'body' => str_repeat('x', 16385)];
    $cases[] = array_replace(response($valid), ['type' => 'text/html']);
    $cases[] = response($valid) + ['setCookie' => true];
    $cases[] = [];
    foreach ($cases as $case) {
        $client->response = $case;
        check($client->login('operator@example.invalid', 'fixture-only') === ['ok' => false, 'error' => 'backend_unavailable'], 'Malformed upstream response fails closed');
    }
    $client->response = response($valid); $calls = $client->calls;
    check(!$client->login('operator@example.invalid', 'fixture-only', "127.0.0.1\r\nInjected: yes")['ok'] && $calls === $client->calls, 'Header injection blocked before transport');
    $client->baseUrl = 'http://attacker.invalid';
    check(!$client->login('operator@example.invalid', 'fixture-only')['ok'] && $calls === $client->calls, 'Unapproved origin rejected before credentials sent');
    $client->baseUrl = 'https://napay.local/api/pos/v1'; $client->clientKeyFile = $work . '/missing';
    check(!$client->login('operator@example.invalid', 'fixture-only')['ok'], 'Missing secret fails closed');
    foreach ([['email'=>['bad'],'password'=>'x'],['email'=>'operator@example.invalid','password'=>['bad']],['email'=>'bad','password'=>'x'],['email'=>'operator@example.invalid','password'=>str_repeat('x',1025)]] as $data) {
        check(!(new app\models\OperatorLoginForm($data))->validate(), 'Malformed local form rejected');
    }
    $app = new yii\web\Application(['id'=>'pos-session-test','basePath'=>dirname(__DIR__),
        'components'=>['request'=>['cookieValidationKey'=>'fixture-key-only','url'=>'/site/account','scriptUrl'=>'/index.php','scriptFile'=>dirname(__DIR__).'/web/index.php','hostInfo'=>'https://pos.localhost'],
            'session'=>['savePath'=>$work.'/sessions','name'=>'POSTEST','cookieParams'=>['secure'=>true,'httpOnly'=>true,'sameSite'=>'Strict']]]]);
    $app->session->open(); $oldId = $app->session->id;
    $store = new app\components\OperatorSession(); $store->establish($success['session']);
    check($app->session->id !== $oldId, 'Session ID rotated at login');
    check($store->current()['token'] === $token, 'Token stored only in server session');
    $html = $app->view->renderFile(dirname(__DIR__).'/views/site/account.php',['displayName'=>$valid['operator']['display_name']]);
    check(!str_contains($html,'<svg onload=') && str_contains($html,'&lt;svg'), 'Operator name HTML encoded');
    check(!str_contains($html,$token), 'Token absent from rendered account HTML');
    $app->session->set('pos.operator', ['expires'=>time()+100]);
    check($store->current() === null, 'Corrupt session rejected');
    $app->session->set('pos.operator', array_replace($success['session'],['expires'=>time()-1]));
    check($store->current() === null, 'Absolute expiry enforced locally');
    $store->establish($success['session']); $store->forget();
    check($store->current() === null, 'Local logout removes custody');
    $app->session->close();
    echo "PASS $checks POS login client/session checks (mock transport, no backend authorization claim)\n";
} finally {
    foreach (glob($work.'/sessions/*') as $file) unlink($file);
    rmdir($work.'/sessions'); unlink($work.'/key'); unlink($work.'/ca'); rmdir($work);
}
