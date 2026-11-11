<?php use yii\helpers\Html; ?>
<?php $this->beginPage() ?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Napay POS | Anteprima v2</title>
<link rel="stylesheet" href="/pos.css"><script defer src="/pos.js"></script>
<?php $this->head() ?></head><body><?php $this->beginBody() ?>
<header class="masthead"><a href="/" class="brand">na<span>pay</span><small>POINT OF SALE</small></a><span class="status">ANTEPRIMA LOCALE</span></header>
<main><?= $content ?></main>
<footer>Yii2 / PHP 8.3 <span>Nessun pagamento viene eseguito</span></footer>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>
