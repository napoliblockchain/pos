<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">CREDENZIALI VERIFICATE</p><h1><?= Html::encode($displayName ?: 'Operatore') ?></h1><p class="subtitle">Accesso effettuato tramite Napay</p></section>
<section class="terminal"><h2>Nessun POS aperto</h2><p>Catalogo negozi e POS non ancora collegato. Il login non assegna autorizzazioni e non abilita gli incassi.</p><p class="boundary">Questa pagina conserva la sessione locale; le future operazioni protette dovranno verificare autorizzazioni e revoche sul backend.</p>
<?php if (!empty($logoutFailed)): ?><p class="error" role="alert">Revoca non confermata. Sessione locale mantenuta: riprova a uscire.</p><?php endif ?>
<?= Html::beginForm(['/site/logout'], 'post', ['id' => 'operator-logout']) ?>
<button type="submit" class="primary">Esci</button>
<?= Html::endForm() ?>
<p class="boundary">Il logout revoca la sessione Napay prima di eliminare la sessione locale. In caso di errore puoi riprovare.</p></section>
