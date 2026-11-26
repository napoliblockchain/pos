<?php
// The development preview never exposes framework debugging or legacy bootstrap.
define('YII_DEBUG', false);
define('YII_ENV', 'prod');
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
(new yii\web\Application(require dirname(__DIR__) . '/config/web.php'))->run();
