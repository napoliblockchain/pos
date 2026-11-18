<?php
use yii\helpers\Html;
/* @var $payment array|null pagamento BTC verificato da Napay, null se non leggibile ora */
/* @var $label string */
/* @var $qr string|null SVG del QR BIP21, solo mentre il pagamento è aperto */
// SIP-033: D1 "ricevuto" (0 conferme) chiude la vendita, "confermato" arriva dopo; D2 QR nel POS, pagina BTCPay come ripiego.
$notice = Yii::$app->session->getFlash('pos');
$states = [
    'creating' => Yii::t('app', 'Creating the BTC request...'),
    'pending' => Yii::t('app', 'Waiting for payment'),
    'processing' => Yii::t('app', 'Payment received: you can close the sale. Waiting for the network confirmation.'),
    'settled' => Yii::t('app', 'Payment confirmed'),
    'expired' => Yii::t('app', 'Expired: no payment received in time.'),
    'invalid' => Yii::t('app', 'Payment not valid: do not close the sale.'),
    'failed' => Yii::t('app', 'BTC request not created: create a new payment.'),
    'reconcile_required' => Yii::t('app', 'Outcome of the BTC request unknown: create a new payment.'),
];
$open = $payment !== null && in_array($payment['state'], ['creating', 'pending', 'processing'], true);
$rome = new DateTimeZone('Europe/Rome');
// SIP-033 passo 5: cambio "61700.00" -> "61.700,00" (testo decimale di Napay, mai float)
$money = static function (string $value): string {
    [$whole, $cents] = explode('.', $value);
    return ltrim(strrev(implode('.', str_split(strrev($whole), 3))), '.') . ',' . $cents;
};
$rate = $payment !== null && $payment['rate'] !== null ? Yii::t('app', '1 BTC = {rate} {currency}', ['rate' => $money($payment['rate']), 'currency' => $payment['currency']]) : null;
$expired = $payment !== null && $payment['state'] === 'expired';
?>
<section class="terminal payment" aria-label="<?= Html::encode(Yii::t('app', 'BTC payment')) ?>" data-heartbeat="/site/heartbeat"
<?php if ($open): ?> data-payment-status="<?= Html::encode('/payment/btc-status?id=' . $payment['id']) ?>" data-expires="<?= (int) $payment['expires_at'] ?>" data-state="<?= Html::encode($payment['state']) ?>"<?php endif ?>>
<div class="till-head"><span class="badge"><?= Html::encode(Yii::t('app', 'BTC PAYMENT')) ?></span><strong><?= Html::encode($label) ?></strong></div>
<?php if (!empty($notice)): ?><p class="notice-line" role="status"><?= Html::encode($notice) ?></p><?php endif ?>
<?php if ($payment === null): ?>
<p class="error" role="alert"><?= Html::encode(Yii::t('app', 'Napay unreachable: payment state not verified. Retry shortly.')) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Retry')), Yii::$app->request->url, ['class' => 'button']) ?>
<?php else: ?>
<?php if ($expired): ?>
<div class="payment-expired-box" role="status" data-payment-state>
<div class="payment-expired-icon" aria-hidden="true">&#10005;</div>
<h2><?= Html::encode(Yii::t('app', 'Payment request expired')) ?></h2>
<p class="payment-meta"><?= Html::encode($payment['amount']) ?> <?= Html::encode($payment['currency']) ?> &middot; <?= Html::encode(Yii::t('app', 'Payment {id}', ['id' => substr($payment['id'], 0, 8)])) ?></p>
<p><?= Html::encode(Yii::t('app', 'A BTC payment request is valid for 15 minutes. Create a new payment if the customer wants to pay again.')) ?></p>
<?= Html::a(Html::encode(Yii::t('app', 'Back to the till')), ['/site/index'], ['class' => 'button primary']) ?>
</div>
<?php else: ?>
<p class="payment-amount"><?= Html::encode($payment['amount']) ?> <span><?= Html::encode($payment['currency']) ?></span></p>
<p class="payment-state payment-<?= Html::encode($payment['state']) ?>" role="status" data-payment-state><?= Html::encode($states[$payment['state']]) ?></p>
<?php endif ?>
<?php if ($payment['state'] === 'pending' && $qr !== null): ?>
<figure class="payment-qr"><?= $qr ?><figcaption><?= Html::encode(Yii::t('app', 'Scan with a Bitcoin wallet: address and amount are already filled in.')) ?></figcaption></figure>
<p class="payment-btc"><?= Html::encode($payment['amount_btc']) ?> <span>BTC</span></p>
<p class="payment-address"><?= Html::encode($payment['btc_address']) ?></p>
<?php if ($rate !== null || $payment['order_btc'] !== null): ?>
<p class="payment-meta" data-btc-detail><?= Html::encode(implode(' · ', array_filter([$rate, $payment['order_btc'] === null ? null
    : Yii::t('app', 'order {order} BTC + network fee {fee} BTC', ['order' => $payment['order_btc'], 'fee' => $payment['network_fee_btc']])]))) ?></p>
<?php endif ?>
<?php elseif ($payment['state'] === 'pending'): ?>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'QR not available yet: it appears in a few seconds, or open the BTCPay payment page.')) ?></p>
<?php endif ?>
<?php if ($payment['state'] === 'pending'): ?>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Expires at {time}', ['time' => (new DateTimeImmutable('@' . $payment['expires_at']))->setTimezone($rome)->format('H:i')])) ?>
<span data-countdown></span></p>
<?php if ($payment['checkout_url'] !== null): ?>
<?= Html::a(Html::encode(Yii::t('app', 'Open the BTCPay payment page')), $payment['checkout_url'], ['class' => 'button', 'target' => '_blank', 'rel' => 'noopener noreferrer']) ?>
<?php endif ?>
<?php elseif ($payment['state'] === 'processing' || $payment['state'] === 'settled'): ?>
<p class="payment-meta"><?= Html::encode(Yii::t('app', 'Paid {btc} BTC, confirmations: {count}', ['btc' => $payment['paid_btc'] ?? $payment['amount_btc'] ?? '-', 'count' => $payment['confirmations']])) ?></p>
<?php if ($rate !== null): ?><p class="payment-meta" data-btc-detail><?= Html::encode($rate) ?></p><?php endif ?>
<?= Html::a(Html::encode(Yii::t('app', 'Receipt')), ['/payment/btc-receipt', 'id' => $payment['id']], ['class' => 'button', 'target' => '_blank', 'rel' => 'noopener']) ?>
<?php endif ?>
<?php endif ?>
<div class="till-foot<?= $expired ? ' single' : '' ?>"><?= $expired ? '' : Html::a(Html::encode(Yii::t('app', 'New payment')), ['/site/index'], ['class' => 'button']) ?><?= Html::a(Html::encode(Yii::t('app', 'Payment history')), ['/payment/history'], ['class' => 'button']) ?></div>
</section>
