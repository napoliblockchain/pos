<?php
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(['id' => 'pos-unit', 'basePath' => dirname(__DIR__)]);
$checks = 0;
foreach (['1' => 100, '1,23' => 123, '0.01' => 1, '1000' => 100000, '999,99' => 99999, ' 12,3 ' => 1230] as $input => $cents) {
    $model = new app\models\AmountForm(['amount' => (string) $input]);
    if (!$model->validate() || $model->cents() !== $cents) throw new RuntimeException('Amount validation failed.');
    ++$checks;
}
foreach (['', '0', '0,00', '-1', '1e3', '100000', '1000,01', '99999.99', '01', '1.234', '1,2.3', '<script>', ['bad'], null, 123] as $input) {
    $model = new app\models\AmountForm(['amount' => $input]);
    if ($model->validate()) throw new RuntimeException('Invalid amount accepted.');
    ++$checks;
}
echo "PASS $checks amount checks\n";
