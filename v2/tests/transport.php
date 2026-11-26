<?php
// Real TLS negative tests only; no valid operator credentials or tokens are printed.
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$client = new app\components\NapayLoginClient();
$result = $client->login('transport-check@example.invalid', 'not-a-real-password', '198.51.100.201');
if (($result['error'] ?? null) !== 'invalid_credentials') throw new RuntimeException('Verified TLS/backend handshake failed.');
$file = tempnam('/tmp', 'bad-pos-ca-');
try {
    file_put_contents($file, 'invalid certificate');
    $bad = new app\components\NapayLoginClient(['caFile' => $file]);
    if (($bad->login('transport-check@example.invalid', 'not-a-real-password')['error'] ?? null) !== 'backend_unavailable') throw new RuntimeException('Untrusted CA accepted.');
} finally {
    unlink($file);
}
echo "PASS 2 real TLS transport checks\n";
