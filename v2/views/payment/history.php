<?php
use yii\helpers\Html;
/* @var $payments array|null storico del terminale da Napay, null se non leggibile ora */
/* @var $label string */
$states = [
    'pending' => Yii::t('app', 'Waiting'),
    'paid' => Yii::t('app', 'Paid'),
    'expired' => Yii::t('app', 'Expired'),
    'cancelled' => Yii::t('app', 'Cancelled'),
];
$time = static fn(int $at): string => (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('Europe/Rome'))->format('d/m H:i');
?>
<section class="terminal history" aria-label="<?= Html::encode(Yii::t('app', 'Payment history')) ?>" data-heartbeat="/site/heartbeat">
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'HISTORY')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if ($payments === null): ?>
<p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: history not available. Retry shortly.')) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Retry')), ['/payment/history'], ['class' => 'button']) ?>
<?php elseif ($payments === []): ?>
<p class="notice-line"><?= Html::encode(Yii::t('app', 'No payments on this POS in the last 30 days.')) ?></p>
<?php else: ?>
<ul class="history-list">
<?php foreach ($payments as $payment): ?>
<li class="payment-<?= Html::encode($payment['state']) ?>">
<span class="history-time"><?= Html::encode($time($payment['created_at'])) ?></span>
<span class="history-amount"><?= Html::encode($payment['amount']) ?> TTS</span>
<span class="history-state"><?= Html::encode($states[$payment['state']]) ?></span>
<?php if ($payment['state'] === 'paid'): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Receipt')), ['/payment/receipt', 'id' => $payment['id']], ['target' => '_blank', 'rel' => 'noopener']) ?>
<?php elseif ($payment['state'] === 'pending' && Yii::$app->operatorSession->ownsPayment($payment['id'])): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Open')), ['/payment/view', 'id' => $payment['id']]) ?>
<?php endif ?>
</li>
<?php endforeach ?>
</ul>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Last 50 payments of this POS in the last 30 days.')) ?></p>
<?php endif ?>
<div class="till-foot single"><?= Html::a(Html::encode(Yii::t('app', 'Back to the till')), ['/site/index'], ['class' => 'button']) ?></div>
</section>
