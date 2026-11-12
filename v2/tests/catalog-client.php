<?php
if (PHP_SAPI !== 'cli') exit(1);
define('YII_ENABLE_ERROR_HANDLER',false);
require dirname(__DIR__).'/vendor/autoload.php'; require dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
$work=sys_get_temp_dir().'/pos-catalog-test-'.bin2hex(random_bytes(6));
mkdir($work,0700); mkdir($work.'/sessions',0700);
file_put_contents($work.'/key',str_repeat('K',43)); file_put_contents($work.'/ca','simulated transport');
class FakeCatalogClient extends app\components\NapayLoginClient {
    public $response; public $sent; public $calls=0;
    protected function transport(string $url,string $method,string $body,string $key,?string $ip,?string $token):array {
        ++$this->calls; $this->sent=[$url,$method,$body,$ip,$token]; return $this->response;
    }
}
$checks=0;
$check=static function($value,string $message)use(&$checks){if(!$value)throw new RuntimeException($message);++$checks;};
$reply=static fn($data,int $status=200)=>['status'=>$status,'type'=>'application/json','body'=>json_encode($data)];
$token='napos_'.str_repeat('A',43);
$item=['id'=>'00000000-0000-4000-8000-000000000001','store_name'=>'Negozio <svg onload=alert(1)>','name'=>'Cassa','availability'=>'unknown'];
$valid=['items'=>[$item],'next_cursor'=>'next_cursor'];
try {
    $client=new FakeCatalogClient(['clientKeyFile'=>$work.'/key','caFile'=>$work.'/ca']);
    $client->response=$reply($valid);
    $result=$client->catalog($token);
    $check($result['ok']&&$result['catalog']===$valid,'Approved catalog accepted');
    $check($client->sent===['https://napay.local/api/pos/v1/terminals?limit=50','GET','',null,$token],'GET empty body with server bearer');
    $client->response=$reply(['items'=>[],'next_cursor'=>null]);
    $check($client->catalog($token)['ok'],'Real empty catalog accepted');
    $cases=[['items'=>(object)[],'next_cursor'=>null],['items'=>[]],['items'=>[],'next_cursor'=>'x'],['items'=>[],'next_cursor'=>null,'extra'=>1],['items'=>[$item,$item],'next_cursor'=>null],['items'=>[$item],'next_cursor'=>['bad']]];
    foreach ([['id','sin-code'],['name',[]],['store_name',''],['name',str_repeat('x',257)],['availability','available']] as [$key,$value]) {$bad=$item;$bad[$key]=$value;$cases[]=['items'=>[$bad],'next_cursor'=>null];}
    $bad=$item;$bad['SIN']='secret';$cases[]=['items'=>[$bad],'next_cursor'=>null];
    foreach($cases as $data){$client->response=$reply($data);$check(!$client->catalog($token)['ok'],'Malformed catalog rejected');}
    foreach ([['body'=>'{bad'],['body'=>str_repeat('x',16385)],['type'=>'text/html'],['setCookie'=>true]] as $override){$client->response=array_replace($reply($valid),$override);$check(!$client->catalog($token)['ok'],'Invalid transport reply rejected');}
    foreach ([[401,'invalid_token','invalid_token'],[401,'unauthenticated','backend_unavailable'],[400,'validation_failed','validation_failed'],[429,'rate_limited','rate_limited'],[503,'backend_unavailable','backend_unavailable']] as [$status,$code,$expected]) {
        $client->response=$reply(['error'=>['code'=>$code,'message'=>'DO NOT DISPLAY']],$status);
        $r=$client->catalog($token);$check($r['error']===$expected&&!str_contains(json_encode($r),'DO NOT DISPLAY'),'Fixed upstream error mapping');
    }
    $check($r['error']==='backend_unavailable','Service failure does not claim expired operator');
    $calls=$client->calls;
    foreach ([['bad',null,50],[$token,'../bad',50],[$token,null,101]] as $args) $check(!$client->catalog(...$args)['ok']&&$client->calls===$calls,'Invalid input blocked before transport');
    $client->baseUrl='http://attacker.invalid';$check(!$client->catalog($token)['ok']&&$client->calls===$calls,'Unapproved origin blocked');$client->baseUrl='https://napay.local/api/pos/v1';
    $app=new yii\web\Application(['id'=>'catalog-client-test','basePath'=>dirname(__DIR__),'components'=>[
        'request'=>['cookieValidationKey'=>'fixture-only','hostInfo'=>'https://pos.localhost','url'=>'/site/account','scriptUrl'=>'/index.php','scriptFile'=>dirname(__DIR__).'/web/index.php'],
        'session'=>['name'=>'POSCATALOGTEST','savePath'=>$work.'/sessions'], 'napayLogin'=>$client,'operatorSession'=>['class'=>app\components\OperatorSession::class],
    ]]);
    $controller=new app\controllers\SiteController('site',$app);$controller->layout=false;$app->controller=$controller;
    $app->operatorSession->establish(['token'=>$token,'expires'=>time()+3600,'name'=>'Fixture']);
    $client->response=$reply($valid);$html=$controller->actionAccount();
    $check(str_contains($html,'&lt;svg')&&!str_contains($html,'<svg onload'),'Catalog labels HTML encoded');
    $check(str_contains($html,'Non verificata')&&!str_contains($html,$token),'Availability honest and no token in HTML');
    $check(!str_contains($html,$item['id']),'Terminal technical identifier not rendered without opening flow');
    $check(str_contains($html,'Pagina successiva'),'Pagination link rendered');
    $client->response=$reply(['items'=>[],'next_cursor'=>null]);$check(str_contains($controller->actionAccount(),'Non risultano POS autorizzati'),'Empty catalog explicit');
    $client->response=$reply(['error'=>['code'=>'backend_unavailable']],503);$html=$controller->actionAccount();
    $check($app->response->statusCode===503&&str_contains($html,'Catalogo non disponibile')&&!str_contains($html,'Cassa'),'Failure does not reuse previous catalog');
    $check($app->operatorSession->current()!==null,'Backend outage retains local session');
    $client->response=$reply(['error'=>['code'=>'invalid_token']],401);$redirect=$controller->actionAccount();
    $check($redirect->statusCode===302&&$app->operatorSession->current()===null,'Invalid remote session clears local custody');
    $app->session->close();echo "PASS $checks POS catalog client/view/controller checks (mock transport)\n";
} finally {foreach(glob($work.'/sessions/*') as $f)unlink($f);rmdir($work.'/sessions');unlink($work.'/key');unlink($work.'/ca');rmdir($work);}
