<?php
$key = getenv('COOKIE_VALIDATION_KEY');
$mode = getenv('POS_MODE');
$api = getenv('NAPAY_API_BASE_URL') ?: app\components\RuntimeConfig::LOCAL_API;
if (!is_string($key) || strlen($key) < 32 || !in_array($mode, ['preview', 'production'], true)
    || !app\components\RuntimeConfig::apiUrl($api, $mode === 'production')) {
    throw new RuntimeException('Private POS configuration missing or unsupported mode.');
}
return [
    'id' => 'napay-pos-v2', 'name' => 'Napay POS', 'basePath' => dirname(__DIR__),
    'language' => 'it-IT', 'bootstrap' => ['log'],
    'components' => [
        'request' => ['cookieValidationKey' => $key, 'csrfParam' => '_pos_csrf',
            'trustedHosts' => app\components\RuntimeConfig::proxies(getenv('POS_TRUSTED_PROXIES') === false ? '10.251.0.0/24' : getenv('POS_TRUSTED_PROXIES')),
            'csrfCookie' => ['name' => '_pos_csrf', 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Strict']],
        'session' => ['name' => 'POSSESSID', 'savePath' => '@runtime/sessions', 'timeout' => 86400, 'useStrictMode' => true,
            'cookieParams' => ['httpOnly' => true, 'secure' => true, 'sameSite' => 'Strict']],
        'napayLogin' => ['class' => app\components\NapayLoginClient::class,
            'baseUrl' => $api,
            'caFile' => getenv('NAPAY_CA_FILE') ?: ($mode === 'production' ? null : '/run/pos-secrets/ca.crt'),
            'clientKeyFile' => getenv('NAPAY_POS_CLIENT_KEY_FILE') ?: '/run/pos-secrets/client-key'],
        'operatorSession' => ['class' => app\components\OperatorSession::class],
        'user' => ['identityClass' => null, 'enableSession' => false],
        'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false, 'rules' => ['health' => 'site/health']],
        'errorHandler' => ['errorAction' => 'site/error'],
        'log' => ['targets' => [['class' => yii\log\FileTarget::class, 'levels' => ['error', 'warning'], 'logVars' => [],
            'prefix' => static fn() => '[pos-v2]']]],
    ],
    'params' => ['label' => getenv('POS_LABEL') ?: 'Napay POS'],
];
