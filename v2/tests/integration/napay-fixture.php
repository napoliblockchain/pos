<?php
// Fixture Napay per l'e2e aperture e pagamenti: eseguita nel container web del progetto usa-e-getta, letta da stdin.
// Il runner antepone $e2ePassword (casuale per esecuzione) e $e2eWallet (indirizzo TTS del merchant, vuoto senza
// pagamenti). Stampa solo dati pubblici in JSON, mai la password.
if (PHP_SAPI !== 'cli' || !isset($e2ePassword) || !preg_match('/^[0-9a-f]{48}$/D', $e2ePassword)) exit(1);
if (!isset($e2eWallet) || ($e2eWallet !== '' && !preg_match('/^0x[0-9a-f]{40}$/D', $e2eWallet))) exit(1);
$root = '/var/www/html';
require $root . '/config/env.php';
if (env('NAPAY_TEST_DB_CONFIRM') !== 'isolated-fixture' || env('DB_DATABASE') !== 'napay_test_runtime') exit(1);
define('YII_ENABLE_ERROR_HANDLER', false);
require $root . '/vendor/autoload.php'; require $root . '/vendor/yiisoft/yii2/Yii.php';
$app = new yii\console\Application(require $root . '/config/console.php');
$db = $app->db;
if ($db->createCommand('SELECT DATABASE()')->queryScalar() !== 'napay_test_runtime') exit(1);
$hash = password_hash($e2ePassword, PASSWORD_BCRYPT);
unset($e2ePassword);
$now = time();
$transaction = $db->beginTransaction();
// Gestore PG qualificato del merchant fixture: password per esecuzione.
$manager = (int) $db->createCommand("SELECT id FROM users WHERE username='runtime-merchant'")->queryScalar();
$merchant = (int) $db->createCommand('SELECT id FROM merchants WHERE user_id=:u', [':u' => $manager])->queryScalar();
if ($manager < 1 || $merchant < 1) exit(1);
$db->createCommand()->update('users', ['password' => $hash], ['id' => $manager])->execute();
if ($db->createCommand('SELECT COUNT(*) FROM member_profiles WHERE user_id=:u', [':u' => $manager])->queryScalar() == 0) {
    $db->createCommand()->insert('member_profiles', ['user_id' => $manager, 'is_corporate' => 1, 'denomination' => 'E2E PG'])->execute();
}
$level0 = (int) $db->createCommand('SELECT id FROM privileges WHERE level=0 ORDER BY id LIMIT 1')->queryScalar();
$base = ['email' => 'e2e@example.invalid', 'phone' => '', 'mobile' => '', 'addressStreet' => '', 'addressNumberHouse' => '', 'addressCity' => '', 'addressZip' => '', 'addressProvince' => '', 'addressCountry' => ''];
$db->createCommand()->insert('stores', ['merchant_id' => $merchant, 'description' => 'E2E Negozio'] + $base)->execute();
$store = (int) $db->lastInsertID;
$operators = [];
foreach (['e2e-op1', 'e2e-op2'] as $name) {
    $db->createCommand()->insert('users', ['username' => $name, 'email' => $name . '@example.invalid', 'password' => $hash, 'first_name' => 'E2E', 'last_name' => $name,
        'oauth_provider' => 'fixture', 'oauth_uid' => $name, 'authKey' => 'fixture-' . $name, 'accessToken' => 'fixture-' . $name, 'picture' => '/favicon.ico',
        'privilege_id' => $level0, 'is_merchant' => 0, 'is_active' => 1])->execute();
    $user = (int) $db->lastInsertID;
    $db->createCommand()->insert('pos_operators', ['user_id' => $user, 'merchant_id' => $merchant, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now])->execute();
    $db->createCommand()->insert('pos_store_operators', ['user_id' => $user, 'merchant_id' => $merchant, 'store_id' => $store, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now])->execute();
    $operators[] = $name . '@example.invalid';
}
foreach (['E2E Cassa 1', 'E2E Cassa 2'] as $name) {
    $terminal = new app\models\PosTerminals(['merchant_id' => $merchant, 'store_id' => $store, 'name' => $name]);
    $terminal->is_enabled = 1;
    if (!$terminal->save()) exit(1);
}
if ($e2eWallet !== '') {
    $db->createCommand()->insert('merchant_tts_wallets', ['merchant_id' => $merchant, 'address' => $e2eWallet, 'updated_by' => $manager, 'updated_at' => $now])->execute();
}
$transaction->commit();
echo json_encode(['manager' => 'merchant@example.invalid', 'operators' => $operators, 'store' => 'E2E Negozio', 'terminals' => ['E2E Cassa 1', 'E2E Cassa 2'],
    'wallet' => $e2eWallet]), "\n";
