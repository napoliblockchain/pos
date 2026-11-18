<?php
use yii\helpers\Html;
/* @var $payments array|null storico del terminale da Napay (TTS e BTC), null se non leggibile ora */
/* @var $totals array|null totali di oggi per metodo */
/* @var $label string */
$states = [
    'tts' => [
        'pending' => Yii::t('app', 'Waiting'),
        'paid' => Yii::t('app', 'Paid'),
        'expired' => Yii::t('app', 'Expired'),
        'cancelled' => Yii::t('app', 'Cancelled'),
    ],
    'btc' => [
        'creating' => Yii::t('app', 'Waiting'),
        'pending' => Yii::t('app', 'Waiting'),
        'processing' => Yii::t('app', 'Received'),
        'settled' => Yii::t('app', 'Confirmed'),
        'expired' => Yii::t('app', 'Expired'),
        'invalid' => Yii::t('app', 'Not valid'),
        'failed' => Yii::t('app', 'Not created'),
        'reconcile_required' => Yii::t('app', 'Unknown outcome'),
    ],
];
// SIP-033: metodo come simbolo (stella TTS, B di Bitcoin), nome per lettori di schermo
$symbol = static fn(string $method): string => Html::tag('span', $method === 'btc' ? '&#8383;' : '&#9733;', ['class' => 'method-symbol ' . $method, 'aria-hidden' => 'true'])
    . Html::tag('span', Html::encode($method === 'btc' ? 'BTC' : 'TTS'), ['class' => 'visually-hidden']);
// "61700.00" -> "61.700,00" (testo decimale di Napay, mai float)
$money = static function (string $value): string {
    [$whole, $cents] = explode('.', $value);
    return ltrim(strrev(implode('.', str_split(strrev($whole), 3))), '.') . ',' . $cents;
};
$time = static fn(int $at): string => (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('Europe/Rome'))->format('d/m H:i');
?>
<section class="terminal history" aria-label="<?= Html::encode(Yii::t('app', 'Payment history')) ?>" data-heartbeat="/site/heartbeat">
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'HISTORY')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if ($payments === null): ?>
<p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: history not available. Retry shortly.')) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Retry')), ['/payment/history'], ['class' => 'button']) ?>
<?php else: ?>
<?php if ($totals !== null): ?>
<div class="history-totals" data-totals>
<h2><?= Html::encode(Yii::t('app', 'Collected today')) ?></h2>
<?php if ($totals['tts']['count'] === 0 && $totals['btc'] === []): ?>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'No payments collected today.')) ?></p>
<?php else: ?>
<?php if ($totals['tts']['count'] > 0): ?>
<p class="history-total"><?= $symbol('tts') ?> <strong><?= Html::encode($money($totals['tts']['amount'])) ?> TTS</strong>
<span><?= Html::encode(Yii::t('app', 'Payments: {count}', ['count' => $totals['tts']['count']])) ?></span></p>
<?php endif ?>
<?php foreach ($totals['btc'] as $row): ?>
<p class="history-total"><?= $symbol('btc') ?> <strong><?= Html::encode($money($row['amount'])) ?> <?= Html::encode($row['currency']) ?></strong>
<span><?= Html::encode(Yii::t('app', 'Payments: {count}', ['count' => $row['count']])) ?> &middot; <?= Html::encode($row['btc']) ?> BTC</span></p>
<?php endforeach ?>
<?php endif ?>
</div>
<?php endif ?>
<?php if ($payments === []): ?>
<p class="notice-line"><?= Html::encode(Yii::t('app', 'No payments on this POS in the last 30 days.')) ?></p>
<?php else: ?>
<ul class="history-list">
<?php foreach ($payments as $payment): ?>
<li class="payment-<?= Html::encode($payment['state']) ?>" data-method="<?= Html::encode($payment['method']) ?>">
<span class="history-time"><?= Html::encode($time($payment['created_at'])) ?></span>
<span class="history-amount"><?= $symbol($payment['method']) ?> <?= Html::encode($payment['amount']) ?> <?= Html::encode($payment['currency']) ?></span>
<span class="history-state"><?= Html::encode($states[$payment['method']][$payment['state']]) ?></span>
<?php if ($payment['method'] === 'tts' && $payment['state'] === 'paid'): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Receipt')), ['/payment/receipt', 'id' => $payment['id']], ['target' => '_blank', 'rel' => 'noopener']) ?>
<?php elseif ($payment['method'] === 'tts' && $payment['state'] === 'pending' && Yii::$app->operatorSession->ownsPayment($payment['id'])): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Open')), ['/payment/view', 'id' => $payment['id']]) ?>
<?php elseif ($payment['method'] === 'btc' && in_array($payment['state'], ['processing', 'settled'], true)): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Receipt')), ['/payment/btc-receipt', 'id' => $payment['id']], ['target' => '_blank', 'rel' => 'noopener']) ?>
<?php elseif ($payment['method'] === 'btc' && $payment['state'] === 'pending' && Yii::$app->operatorSession->ownsPayment($payment['id'])): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Open')), ['/payment/btc-view', 'id' => $payment['id']]) ?>
<?php endif ?>
<?php if ($payment['method'] === 'btc' && ($payment['paid_btc'] !== null && $payment['paid_btc'] !== '0.00000000' || $payment['rate'] !== null)): ?>
<span class="history-detail"><?= Html::encode(implode(' · ', array_filter([
    $payment['paid_btc'] !== null && $payment['paid_btc'] !== '0.00000000' ? $payment['paid_btc'] . ' BTC' : null,
    $payment['rate'] !== null ? Yii::t('app', '1 BTC = {rate} {currency}', ['rate' => $money($payment['rate']), 'currency' => $payment['currency']]) : null,
]))) ?></span>
<?php endif ?>
</li>
<?php endforeach ?>
</ul>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Last 50 payments of this POS in the last 30 days.')) ?></p>
<?php endif ?>
<?php endif ?>
<div class="till-foot single"><?= Html::a(Html::encode(Yii::t('app', 'Back to the till')), ['/site/index'], ['class' => 'button']) ?></div>
</section>
