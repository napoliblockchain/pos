<?php use yii\helpers\Html; ?>
<?php $this->beginPage() ?>
<!doctype html><html lang="<?= Html::encode(substr(Yii::$app->language, 0, 2)) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#071d36"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="Napay POS">
<?= Html::csrfMetaTags() ?>
<title>Napay POS</title>
<link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/brand/icon.svg" type="image/svg+xml"><link rel="icon" href="/favicon.ico" sizes="48x48"><link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
<link rel="stylesheet" href="/tabler/tabler.min.css"><link rel="stylesheet" href="/auth/auth.css"><script defer src="/auth/auth-chain.js"></script><script defer src="/pos.js"></script>
<?php $this->head() ?></head><body class="auth auth--pos"><?php $this->beginBody() ?>
<div class="auth-card">
<aside class="auth-brand"><canvas class="auth-chain" aria-hidden="true"></canvas><img class="auth-icon" src="/brand/icon-192.png" alt=""><div class="auth-divider"></div>
<p class="auth-tag"><?= Html::encode(Yii::t('app', 'Accept TTS payments in your shop, in a few taps')) ?></p></aside>
<main class="auth-main"><div class="auth-form"><a class="auth-wordmark" href="/" aria-label="Napay POS">na<span>pay</span><small>POS</small></a><?= $content ?></div>
<div class="auth-supporters"><p><?= Html::encode(Yii::t('app', 'Our supporters')) ?></p><div class="auth-logos"><img class="auth-logo-nb" src="/auth/napoli-blockchain.png" alt="Napoli Blockchain"><img class="auth-logo-uni" src="/auth/parthenope.jpg" alt="Università degli Studi di Napoli Parthenope"></div>
<p class="auth-footer"><?= Html::encode(Yii::t('app', getenv('POS_MODE') === 'production' ? 'Payments managed by Napay.' : 'Test chain only: no real money.')) ?></p></div></main>
</div>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>
