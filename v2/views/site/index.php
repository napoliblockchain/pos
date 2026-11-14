<?php
use yii\helpers\Html;
use app\components\OperatorSession;
/* @var $model app\models\AmountForm */
/* @var $label string */
?>
<section class="terminal" aria-label="<?= Html::encode(Yii::t('app', 'Till')) ?>" data-heartbeat="/site/heartbeat">
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'POS OPEN')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if (!empty($notice)): ?><p class="notice-line" role="status"><?= Html::encode($notice) ?></p><?php endif ?>
<?php if (!empty($unverified)): ?><p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: opening not verified. Retry shortly.')) ?></p><?php endif ?>
<?php if (!empty($releaseFailed)): ?><p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Closing not confirmed by Napay. Retry.')) ?></p><?php endif ?>
<?php if (!empty($paymentError)): ?><p class="error" role="alert"><?= Html::encode($paymentError) ?></p><?php endif ?>
<?= Html::beginForm(['/payment/create'], 'post', ['id' => 'amount-form']) ?>
<?= Html::hiddenInput('key', OperatorSession::newKey()) ?>
<label for="amount"><?= Html::encode(Yii::t('app', 'Amount')) ?> <span>TTS</span></label>
<?= Html::activeTextInput($model, 'amount', ['id' => 'amount', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00', 'maxlength' => 7, 'aria-describedby' => 'amount-error']) ?>
<div id="amount-error" class="error" role="alert"><?= Html::error($model, 'amount') ?></div>
<div class="keypad" aria-label="<?= Html::encode(Yii::t('app', 'Amount keys')) ?>">
<?php foreach (['1','2','3','4','5','6','7','8','9',',','0','back'] as $key): ?>
<button type="button" data-key="<?= Html::encode($key) ?>" aria-label="<?= $key === 'back' ? Html::encode(Yii::t('app', 'Delete last digit')) : Html::encode($key) ?>"><?= $key === 'back' ? '&larr;' : Html::encode($key) ?></button>
<?php endforeach ?>
</div>
<div class="actions"><button type="button" data-clear><?= Html::encode(Yii::t('app', 'Clear')) ?></button><button type="submit" class="primary"><?= Html::encode(Yii::t('app', 'Collect TTS')) ?></button></div>
<?= Html::endForm() ?>
<div class="till-foot single"><?= Html::a(Html::encode(Yii::t('app', 'Payment history')), ['/payment/history'], ['class' => 'button']) ?></div>
<div class="till-foot">
<?= Html::beginForm(['/site/release'], 'post') ?><button type="submit"><?= Html::encode(Yii::t('app', 'Close POS')) ?></button><?= Html::endForm() ?>
<?= Html::beginForm(['/site/logout'], 'post') ?><button type="submit"><?= Html::encode(Yii::t('app', 'Log out')) ?></button><?= Html::endForm() ?>
</div>
</section>
