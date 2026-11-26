<?php
// Run on stdin in disposable containers only. Never contacts the shared Napay backend.
if (PHP_SAPI !== 'cli' || getenv('POS_TLS_TEST') !== 'isolated-fixture') exit(1);
require '/srv/pos/vendor/autoload.php'; require '/srv/pos/vendor/yiisoft/yii2/Yii.php';
$client = new app\components\NapayLoginClient([
    'baseUrl' => getenv('NAPAY_API_BASE_URL'), 'caFile' => getenv('NAPAY_CA_FILE') ?: null,
    'clientKeyFile' => getenv('NAPAY_POS_CLIENT_KEY_FILE') ?: '/run/pos-secrets/client-key',
]);
$expected = getenv('POS_TLS_EXPECT');
if (!in_array($expected, ['invalid_credentials', 'backend_unavailable'], true)
    || ($client->login('fixture@example.invalid', 'not-a-real-password')['error'] ?? null) !== $expected) throw new RuntimeException('Production TLS trust check failed.');
echo "PASS: production TLS trust boundary verified\n";
