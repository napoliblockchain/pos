<?php
// Simulated transport; HTTP/DB revocation is verified by the Napay API suite.
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$work = sys_get_temp_dir() . '/pos-logout-test-' . bin2hex(random_bytes(6));
mkdir($work, 0700); mkdir($work . '/sessions', 0700);
file_put_contents($work . '/key', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
file_put_contents($work . '/ca', 'mock transport only');
class FakeLogoutClient extends app\components\NapayLoginClient {
    public $response = []; public $sent; public $calls = 0;
    protected function request(string $method, string $body, string $key, ?string $ip, ?string $token = null): array {
        ++$this->calls; $this->sent = [$method, $body, $ip, $token]; return $this->response;
    }
}
$checks = 0;
$check = static function ($condition, $label) use (&$checks) {
    if (!$condition) throw new RuntimeException($label);
    ++$checks;
};
try {
    $client = new FakeLogoutClient(['clientKeyFile' => $work . '/key', 'caFile' => $work . '/ca']);
    $token = 'napos_' . str_repeat('A', 43);
    $client->response = ['status' => 204, 'body' => ''];
    $check($client->logout($token) === ['ok' => true], 'Empty 204 accepted');
    $check($client->sent === ['DELETE', '', null, $token], 'Only DELETE with empty body and server bearer');
    foreach ([[], ['status'=>204], ['status'=>204,'body'=>'unexpected'], ['status'=>204,'body'=>'','setCookie'=>true],
        ['status'=>200,'body'=>'{}'], ['status'=>401,'body'=>'secret error'], ['status'=>429,'body'=>'{}'],
        ['status'=>503,'body'=>'{}'], ['status'=>302,'body'=>'']] as $response) {
        $client->response = $response;
        $check($client->logout($token) === ['ok'=>false,'error'=>'backend_unavailable'], 'Unexpected reply fails closed');
    }
    $calls = $client->calls;
    $check(!$client->logout("bad\r\nInjected: yes")['ok'] && $client->calls === $calls, 'Malformed bearer blocked before transport');
    $client->baseUrl = 'http://attacker.invalid';
    $check(!$client->logout($token)['ok'] && $client->calls === $calls, 'Unapproved origin blocked');
    $client->baseUrl = 'https://napay.local/api/pos/v1';
    $app = new yii\web\Application(['id'=>'pos-logout-test','basePath'=>dirname(__DIR__), 'components'=>[
        'request'=>['cookieValidationKey'=>'fixture-only','hostInfo'=>'https://pos.localhost','url'=>'/site/logout','scriptUrl'=>'/index.php','scriptFile'=>dirname(__DIR__).'/web/index.php'],
        'session'=>['name'=>'POSLOGOUTTEST','savePath'=>$work.'/sessions'],
        'napayLogin'=>$client, 'operatorSession'=>['class'=>app\components\OperatorSession::class],
    ]]);
    $controller = new app\controllers\SiteController('site', $app); $controller->layout = false; $app->controller = $controller;
    $app->operatorSession->establish(['token'=>$token,'expires'=>time()+3600,'name'=>'Fixture']);
    $client->response = ['status'=>503,'body'=>'{}'];
    $html = $controller->actionLogout();
    $check($app->response->statusCode === 503 && str_contains($html, 'Revoca non confermata'), 'Failed revocation shows retry error');
    $check($app->operatorSession->current()['token'] === $token, 'Failure retains server custody for retry');
    $check(!str_contains($html, $token), 'Failure never renders backend token');
    $client->response = ['status'=>204,'body'=>''];
    $result = $controller->actionLogout();
    $check($result->statusCode === 302 && $app->operatorSession->current() === null, 'Confirmed retry clears local session and redirects');
    $calls = $client->calls;
    $controller->actionLogout();
    $check($client->calls === $calls, 'Anonymous logout does not contact backend');
    $app->session->close();
    echo "PASS $checks POS logout client/controller checks (mock transport)\n";
} finally {
    foreach (glob($work.'/sessions/*') as $file) unlink($file);
    rmdir($work.'/sessions'); unlink($work.'/key'); unlink($work.'/ca'); rmdir($work);
}
