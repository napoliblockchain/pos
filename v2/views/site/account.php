<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">CREDENZIALI VERIFICATE</p><h1><?= Html::encode($displayName ?: 'Operatore') ?></h1><p class="subtitle">Accesso effettuato tramite Napay</p></section>
<section class="terminal"><h2>Nessun POS aperto</h2><p>Catalogo negozi e POS non ancora collegato. Il login non assegna autorizzazioni e non abilita gli incassi.</p><p class="boundary">Questa pagina conserva la sessione locale; verifica remota continua e revoca tramite logout saranno collegate alle prossime API.</p>
<?= Html::beginForm(['/site/logout'], 'post', ['id' => 'operator-logout']) ?>
<button type="submit" class="primary">Chiudi sessione locale</button>
<?= Html::endForm() ?>
<p class="boundary">La chiusura locale non revoca ancora il token sul backend; resta soggetto alla sua scadenza assoluta.</p></section>
