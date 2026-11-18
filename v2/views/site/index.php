<?php
use yii\helpers\Html;
use app\components\OperatorSession;
/* @var $model app\models\AmountForm */
/* @var $label string */
/* @var $methods array{tts:bool, btc:bool, btc_currency:?string}|null metodi dichiarati da Napay (null = non noti: solo TTS) */
$btc = !empty($methods['btc']);
?>
<section class="terminal" aria-label="<?= Html::encode(Yii::t('app', 'Till')) ?>" data-heartbeat="/site/heartbeat">
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'POS OPEN')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if (!empty($notice)): ?><p class="notice-line" role="status"><?= Html::encode($notice) ?></p><?php endif ?>
<?php if (!empty($unverified)): ?><p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: opening not verified. Retry shortly.')) ?></p><?php endif ?>
<?php if (!empty($releaseFailed)): ?><p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Closing not confirmed by Napay. Retry.')) ?></p><?php endif ?>
<?php if (!empty($paymentError)): ?><p class="error" role="alert"><?= Html::encode($paymentError) ?></p><?php endif ?>
<?= Html::beginForm(['/payment/create'], 'post', ['id' => 'amount-form']) ?>
<?= Html::hiddenInput('key', OperatorSession::newKey()) ?>
<label for="amount"><?= Html::encode(Yii::t('app', 'Amount')) ?> <span><?= Html::encode($btc ? 'TTS / ' . $methods['btc_currency'] : 'TTS') ?></span></label>
<?= Html::activeTextInput($model, 'amount', ['id' => 'amount', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00', 'maxlength' => 7, 'aria-describedby' => 'amount-error']) ?>
<div id="amount-error" class="error" role="alert"><?= Html::error($model, 'amount') ?></div>
<div class="keypad" aria-label="<?= Html::encode(Yii::t('app', 'Amount keys')) ?>">
<?php foreach (['1','2','3','4','5','6','7','8','9',',','0','back'] as $key): ?>
<button type="button" data-key="<?= Html::encode($key) ?>" aria-label="<?= $key === 'back' ? Html::encode(Yii::t('app', 'Delete last digit')) : Html::encode($key) ?>"><?= $key === 'back' ? '&larr;' : Html::encode($key) ?></button>
<?php endforeach ?>
</div>
<?php // metodi come simboli (stella TTS, B di Bitcoin) per stare in una riga; il nome resta per lettori di schermo e tooltip ?>
<div class="actions<?= $btc ? ' with-btc' : '' ?>"><button type="button" data-clear><?= Html::encode(Yii::t('app', 'Clear')) ?></button><button type="submit" class="primary pay-symbol tts" aria-label="<?= Html::encode(Yii::t('app', 'Collect TTS')) ?>" title="<?= Html::encode(Yii::t('app', 'Collect TTS')) ?>"><span aria-hidden="true">&#9733;</span></button>
<?php if ($btc): ?><button type="submit" class="primary pay-symbol btc" formaction="<?= Html::encode(\yii\helpers\Url::to(['/payment/btc-create'])) ?>" data-pay-btc aria-label="<?= Html::encode(Yii::t('app', 'Collect in BTC')) ?>" title="<?= Html::encode(Yii::t('app', 'Collect in BTC')) ?>"><span aria-hidden="true">&#8383;</span></button><?php endif ?></div>
<?= Html::endForm() ?>
<div class="till-foot single"><?= Html::a(Html::encode(Yii::t('app', 'Payment history')), ['/payment/history'], ['class' => 'button']) ?></div>
<div class="till-foot">
<?= Html::beginForm(['/site/release'], 'post') ?><button type="submit"><?= Html::encode(Yii::t('app', 'Close POS')) ?></button><?= Html::endForm() ?>
<?= Html::beginForm(['/site/logout'], 'post') ?><button type="submit"><?= Html::encode(Yii::t('app', 'Log out')) ?></button><?= Html::endForm() ?>
</div>
</section>
