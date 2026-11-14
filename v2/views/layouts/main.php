<?php use yii\helpers\Html; ?>
<?php $this->beginPage() ?>
<!doctype html><html lang="<?= Html::encode(substr(Yii::$app->language, 0, 2)) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#162626"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="Napay POS">
<?= Html::csrfMetaTags() ?>
<title>Napay POS</title>
<link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/icons/icon.svg" type="image/svg+xml"><link rel="apple-touch-icon" href="/icons/icon-192.png">
<link rel="stylesheet" href="/pos.css"><script defer src="/pos.js"></script>
<?php $this->head() ?></head><body><?php $this->beginBody() ?>
<header class="masthead"><a href="/" class="brand">na<span>pay</span><small>POINT OF SALE</small></a><span class="status" data-network data-online="<?= Html::encode(Yii::t('app', 'ONLINE')) ?>" data-offline="<?= Html::encode(Yii::t('app', 'OFFLINE')) ?>"><?= Html::encode(Yii::t('app', 'ONLINE')) ?></span></header>
<main><?= $content ?></main>
<footer><span><?= Html::encode(Yii::t('app', 'Test chain only: no real money.')) ?></span></footer>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>
