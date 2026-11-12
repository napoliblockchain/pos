<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">CREDENZIALI VERIFICATE</p><h1><?= Html::encode($displayName ?: 'Operatore') ?></h1><p class="subtitle">I punti cassa dei negozi assegnati</p><p>Nessun POS aperto. Apertura esclusiva e incassi non ancora abilitati.</p></section>
<section class="terminal"><h2>Punti cassa autorizzati</h2>
<?php if (!empty($logoutFailed)): ?><p class="error" role="alert">Revoca non confermata. Sessione locale mantenuta: riprova a uscire.</p><?php endif ?>
<?php if (!empty($catalogError)): ?>
<p class="error" role="alert">Catalogo non disponibile. Riprova o riparti dalla prima pagina.</p>
<?= Html::a('Prima pagina / Riprova',['/site/account']) ?>
<?php elseif (isset($catalog)): ?>
<?php if ($catalog['items'] === []): ?><p>Non risultano POS autorizzati per questo account.</p><?php else: ?>
<ul class="terminal-catalog">
<?php foreach ($catalog['items'] as $item): ?>
<li><strong><?= Html::encode($item['store_name'].' - '.$item['name']) ?></strong><span>Non verificata</span></li>
<?php endforeach ?>
</ul>
<?php if ($catalog['next_cursor'] !== null): ?><?= Html::a('Pagina successiva',['/site/account','cursor'=>$catalog['next_cursor']]) ?><?php endif ?>
<?php endif ?>
<?php else: ?><p>Catalogo non caricato. <?= Html::a('Aggiorna',['/site/account']) ?></p><?php endif ?>
<p class="boundary">Le autorizzazioni sono verificate da Napay a ogni pagina. La disponibilita non e ancora verificata; nessun terminale viene aperto.</p>
<?= Html::beginForm(['/site/logout'], 'post', ['id'=>'operator-logout']) ?>
<button type="submit" class="primary">Esci</button>
<?= Html::endForm() ?>
<p class="boundary">Il logout revoca la sessione Napay prima di eliminare la sessione locale. In caso di errore puoi riprovare.</p></section>
