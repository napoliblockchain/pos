<?php use yii\helpers\Html; ?>
<section class="intro"><p class="eyebrow">IL TUO PUNTO DI INCASSO</p><h1>Semplice.<br>Un importo alla volta.</h1><p class="subtitle"><?= Html::encode(Yii::$app->params['label']) ?></p>
<div class="notice"><strong>Terminale non abbinato</strong><p>La nuova interfaccia e attiva. Pairing, QR di pagamento e ricevute saranno collegati al backend Napay nel prossimo step.</p></div></section>
<section class="terminal" aria-label="Tastierino importo">
<?= Html::beginForm(['/site/index'], 'post', ['id' => 'amount-form']) ?>
<label for="amount">Importo da verificare <span>EUR</span></label>
<?= Html::activeTextInput($model, 'amount', ['id' => 'amount', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00', 'maxlength' => 8, 'aria-describedby' => 'amount-error']) ?>
<div id="amount-error" class="error" role="alert"><?= Html::error($model, 'amount') ?></div>
<div class="keypad" aria-label="Tasti importo">
<?php foreach (['1','2','3','4','5','6','7','8','9',',','0','back'] as $key): ?>
<button type="button" data-key="<?= Html::encode($key) ?>" aria-label="<?= $key === 'back' ? 'Cancella ultima cifra' : Html::encode($key) ?>"><?= $key === 'back' ? '&larr;' : Html::encode($key) ?></button>
<?php endforeach ?>
</div>
<div class="actions"><button type="button" data-clear>Azzera</button><button type="submit" class="primary">Verifica importo</button></div>
<?php if ($verified): ?><p class="verified" role="status">Importo valido. Anteprima soltanto: nessun incasso, fattura o QR generato.</p><?php endif ?>
<p class="boundary">Incassi disabilitati fino all'abbinamento sicuro del terminale.</p>
<?= Html::endForm() ?>
</section>
