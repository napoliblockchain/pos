<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">OPERATORE</p><h1><?= Html::encode($displayName ?: 'Operatore') ?></h1><p class="subtitle">Scegli il POS da aprire</p></section>
<section class="terminal">
<?php if (!empty($notice)): ?><p class="notice-line" role="status"><?= Html::encode($notice) ?></p><?php endif ?>
<?php if (!empty($logoutFailed)): ?><p class="error" role="alert">Revoca non confermata. Sessione locale mantenuta: riprova a uscire.</p><?php endif ?>
<?php if (!empty($catalogError)): ?>
<p class="error" role="alert">Catalogo non disponibile. Riprova o riparti dalla prima pagina.</p>
<?= Html::a('Riprova', ['/site/account'], ['class' => 'button']) ?>
<?php elseif (isset($catalog)): ?>
<?php if ($catalog['items'] === []): ?><p>Non risultano POS autorizzati per questo account.</p><?php else: ?>
<ul class="terminal-catalog">
<?php foreach ($catalog['items'] as $item): $label = $item['store_name'] . ' - ' . $item['name']; ?>
<li><?= Html::beginForm(['/site/open'], 'post') ?>
<?= Html::hiddenInput('terminal', $item['id']) ?><?= Html::hiddenInput('label', $label) ?><?= Html::hiddenInput('key', \app\components\OperatorSession::newKey()) ?>
<button type="submit" class="open-terminal"><strong><?= Html::encode($item['name']) ?></strong><span><?= Html::encode($item['store_name']) ?></span></button>
<?= Html::endForm() ?></li>
<?php endforeach ?>
</ul>
<?php if ($catalog['next_cursor'] !== null): ?><?= Html::a('Pagina successiva', ['/site/account', 'cursor' => $catalog['next_cursor']], ['class' => 'button']) ?><?php endif ?>
<?php endif ?>
<?php else: ?><p>Elenco non caricato. <?= Html::a('Aggiorna', ['/site/account']) ?></p><?php endif ?>
<?= Html::beginForm(['/site/logout'], 'post', ['id' => 'operator-logout']) ?>
<button type="submit">Esci</button>
<?= Html::endForm() ?>
</section>
