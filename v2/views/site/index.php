<?php use yii\helpers\Html; ?>
<section class="terminal" aria-label="Cassa" data-heartbeat="/site/heartbeat">
<div class="till-head"><span class="badge">POS APERTO</span><strong><?= Html::encode($label) ?></strong></div>
<?php if (!empty($unverified)): ?><p class="error" role="alert">Napay non raggiungibile: apertura non verificata. Riprova tra poco.</p><?php endif ?>
<?php if (!empty($releaseFailed)): ?><p class="error" role="alert">Chiusura non confermata da Napay. Riprova.</p><?php endif ?>
<?= Html::beginForm(['/site/index'], 'post', ['id' => 'amount-form']) ?>
<label for="amount">Importo <span>EUR</span></label>
<?= Html::activeTextInput($model, 'amount', ['id' => 'amount', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00', 'maxlength' => 8, 'aria-describedby' => 'amount-error']) ?>
<div id="amount-error" class="error" role="alert"><?= Html::error($model, 'amount') ?></div>
<div class="keypad" aria-label="Tasti importo">
<?php foreach (['1','2','3','4','5','6','7','8','9',',','0','back'] as $key): ?>
<button type="button" data-key="<?= Html::encode($key) ?>" aria-label="<?= $key === 'back' ? 'Cancella ultima cifra' : Html::encode($key) ?>"><?= $key === 'back' ? '&larr;' : Html::encode($key) ?></button>
<?php endforeach ?>
</div>
<div class="actions"><button type="button" data-clear>Azzera</button><button type="submit" class="primary">Verifica importo</button></div>
<?php if ($verified): ?><p class="verified" role="status">Importo valido. Incasso non ancora attivo: nessun QR o ricevuta.</p><?php endif ?>
<?= Html::endForm() ?>
<div class="till-foot">
<?= Html::beginForm(['/site/release'], 'post') ?><button type="submit">Chiudi POS</button><?= Html::endForm() ?>
<?= Html::beginForm(['/site/logout'], 'post') ?><button type="submit">Esci</button><?= Html::endForm() ?>
</div>
</section>
