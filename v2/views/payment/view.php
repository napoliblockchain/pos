<?php
use yii\helpers\Html;
/* @var $payment array|null pagamento verificato da Napay, null se non leggibile ora */
/* @var $label string */
/* @var $qr string|null SVG del QR, solo se in attesa */
$notice = Yii::$app->session->getFlash('pos');
$states = [
    'pending' => Yii::t('app', 'Waiting for payment'),
    'paid' => Yii::t('app', 'Paid'),
    'expired' => Yii::t('app', 'Expired: no payment received in time.'),
    'cancelled' => Yii::t('app', 'Cancelled'),
];
?>
<section class="terminal payment" aria-label="<?= Html::encode(Yii::t('app', 'TTS payment')) ?>" data-heartbeat="/site/heartbeat"
<?php if ($payment !== null && $payment['state'] === 'pending'): ?> data-payment-status="<?= Html::encode('/payment/status?id=' . $payment['id']) ?>" data-expires="<?= (int) $payment['expires_at'] ?>"<?php endif ?>>
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'TTS PAYMENT')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if (!empty($notice)): ?><p class="notice-line" role="status"><?= Html::encode($notice) ?></p><?php endif ?>
<?php if ($payment === null): ?>
<p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: payment state not verified. Retry shortly.')) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Retry')), Yii::$app->request->url, ['class' => 'button']) ?>
<?php else: ?>
<p class="payment-amount"><?= Html::encode($payment['amount']) ?> <span>TTS</span></p>
<p class="payment-state payment-<?= Html::encode($payment['state']) ?>" role="status" data-payment-state><?= Html::encode($states[$payment['state']]) ?></p>
<?php if ($qr !== null): ?>
<figure class="payment-qr"><?= $qr ?><figcaption><?= Html::encode(Yii::t('app', 'Scan with the TTS wallet: amount and reference are already filled in.')) ?></figcaption></figure>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Expires at {time}', ['time' => (new DateTimeImmutable('@' . $payment['expires_at']))->setTimezone(new DateTimeZone('Europe/Rome'))->format('H:i')])) ?>
<span data-countdown></span></p>
<p class="payment-address"><?= Html::encode($payment['to_address']) ?></p>
<?= Html::beginForm(['/payment/cancel', 'id' => $payment['id']], 'post') ?><button type="submit"><?= Html::encode(Yii::t('app', 'Cancel payment')) ?></button><?= Html::endForm() ?>
<?php elseif ($payment['state'] === 'paid'): ?>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Transaction {hash}', ['hash' => substr($payment['tx_hash'], 0, 12) . '...' . substr($payment['tx_hash'], -10)])) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Receipt (PDF)')), ['/payment/receipt', 'id' => $payment['id']], ['class' => 'button primary', 'target' => '_blank', 'rel' => 'noopener']) ?>
<?php endif ?>
<?php endif ?>
<div class="till-foot"><?= Html::a(Html::encode(Yii::t('app', 'New payment')), ['/site/index'], ['class' => 'button']) ?><?= Html::a(Html::encode(Yii::t('app', 'Payment history')), ['/payment/history'], ['class' => 'button']) ?></div>
</section>
