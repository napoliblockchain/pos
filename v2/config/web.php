<?php
$key = getenv('COOKIE_VALIDATION_KEY');
if (!is_string($key) || strlen($key) < 32 || getenv('POS_MODE') !== 'preview') {
    throw new RuntimeException('Private POS configuration missing or unsupported mode.');
}
return [
    'id' => 'napay-pos-v2', 'name' => 'Napay POS', 'basePath' => dirname(__DIR__),
    'language' => 'it-IT', 'bootstrap' => ['log'],
    'components' => [
        'request' => ['cookieValidationKey' => $key, 'csrfParam' => '_pos_csrf',
            'csrfCookie' => ['name' => '_pos_csrf', 'httpOnly' => true, 'secure' => true, 'sameSite' => 'Strict']],
        'session' => ['name' => 'POSSESSID', 'cookieParams' => ['httpOnly' => true, 'secure' => true, 'sameSite' => 'Strict']],
        'user' => ['identityClass' => null, 'enableSession' => false],
        'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false, 'rules' => ['health' => 'site/health']],
        'errorHandler' => ['errorAction' => 'site/error'],
        'log' => ['targets' => [['class' => yii\log\FileTarget::class, 'levels' => ['error', 'warning'], 'logVars' => [],
            'prefix' => static fn() => '[pos-v2]']]],
    ],
    'params' => ['label' => getenv('POS_LABEL') ?: 'Napay POS'],
];
