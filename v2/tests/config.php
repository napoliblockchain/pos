<?php
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$key = getenv('COOKIE_VALIDATION_KEY');
$mode = getenv('POS_MODE');
$checks = 0;
$load = static fn() => require dirname(__DIR__) . '/config/web.php';
$config = $load();
foreach ([!isset($config['components']['db']), !isset($config['modules']),
    $config['components']['request']['csrfCookie']['secure'],
    $config['components']['request']['csrfCookie']['sameSite'] === 'Strict',
    $config['components']['session']['name'] === 'POSSESSID',
    $config['components']['log']['targets'][0]['logVars'] === [],
    $config['components']['session']['savePath'] === '@runtime/sessions',
    $config['components']['session']['useStrictMode'] === true,
    array_keys($config['components']['request']['trustedHosts']) === ['10.251.0.0/24'],
    $config['components']['napayLogin']['baseUrl'] === 'https://napay.local/api/pos/v1'] as $condition) {
    if (!$condition) throw new RuntimeException('POS isolation configuration failed.');
    ++$checks;
}
try {
    foreach ([['', 'preview'], ['short', 'preview'], [$key, 'live']] as [$invalidKey, $invalidMode]) {
        putenv('COOKIE_VALIDATION_KEY=' . $invalidKey); putenv('POS_MODE=' . $invalidMode);
        $rejected = false;
        try { $load(); } catch (RuntimeException $error) {
            $rejected = !str_contains($error->getMessage(), $key);
        }
        if (!$rejected) throw new RuntimeException('Unsafe POS configuration accepted.');
        ++$checks;
    }
} finally {
    putenv('COOKIE_VALIDATION_KEY=' . $key); putenv('POS_MODE=' . $mode);
}
echo "PASS $checks POS configuration checks\n";
