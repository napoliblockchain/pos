<?php
namespace app\controllers;

use Yii;
use app\components\PaymentDocuments;
use app\models\AmountForm;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * SIP-021 fase 3 (POS-CLIENT-PAGAMENTO): incasso TTS dall'apertura corrente. Il browser parla solo con il server POS:
 * token, apertura e chiave client restano qui. Pagamenti consultabili solo se creati da questa sessione; lo storico
 * del terminale mostra anche quelli delle aperture precedenti, con ricevuta per i pagati.
 */
class PaymentController extends Controller
{
    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['create' => ['POST'], 'view' => ['GET'], 'status' => ['GET'],
            'cancel' => ['POST'], 'receipt' => ['GET'], 'history' => ['GET'], 'request' => ['GET']]]];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return parent::beforeAction($action);
    }

    /** Dal tastierino: importo validato sul server POS, chiave del form per rendere innocuo un doppio invio. */
    public function actionCreate()
    {
        [$operator, $opening] = $this->context();
        if ($operator === null) return $this->redirect(['/site/login']);
        if ($opening === null) return $this->redirect(['/site/account']);
        $data = Yii::$app->request->post('AmountForm');
        $key = Yii::$app->request->post('key');
        if (!is_array($data) || !is_string($key)) throw new BadRequestHttpException(Yii::t('app', 'Invalid request.'));
        $model = new AmountForm();
        $model->setAttributes($data);
        if (!$model->validate()) {
            Yii::$app->response->statusCode = 422;
            return $this->renderTill($model, $opening['label']);
        }
        $result = Yii::$app->napayLogin->createPayment($operator['token'], $opening['opening'], $model->cents(), $key);
        if ($result['ok']) {
            Yii::$app->operatorSession->rememberPayment($result['payment']['id']);
            return $this->redirect(['view', 'id' => $result['payment']['id']]);
        }
        if ($redirect = $this->leave($result['error'])) return $redirect;
        Yii::$app->response->statusCode = $result['error'] === 'rate_limited' ? 429 : ($result['error'] === 'validation_failed' ? 422 : 503);
        if ($result['error'] === 'rate_limited') Yii::$app->response->headers->set('Retry-After', (string) $result['retry']);
        return $this->renderTill($model, $opening['label'], [
            'payment_unavailable' => Yii::t('app', 'TTS payments are not available for this POS: ask the manager to set the merchant wallet.'),
            'idempotency_conflict' => Yii::t('app', 'Request already sent with different data: enter the amount again.'),
            'validation_failed' => Yii::t('app', 'Amount not accepted by Napay.'),
            'rate_limited' => Yii::t('app', 'Too many attempts: wait a few seconds and retry.'),
        ][$result['error']] ?? Yii::t('app', 'Napay unreachable: retry.'));
    }

    public function actionView(string $id)
    {
        [$operator, $opening] = $this->context();
        if ($operator === null) return $this->redirect(['/site/login']);
        if ($opening === null) return $this->redirect(['/site/account']);
        $this->requireOwned($id);
        $result = Yii::$app->napayLogin->viewPayment($operator['token'], $opening['opening'], $id);
        if (!$result['ok'] && ($redirect = $this->leave($result['error'], true))) return $redirect;
        if (!$result['ok']) Yii::$app->response->statusCode = $result['error'] === 'rate_limited' ? 429 : 503;
        $payment = $result['payment'] ?? null;
        return $this->render('view', ['payment' => $payment, 'label' => $opening['label'],
            'qr' => $payment !== null && $payment['state'] === 'pending' ? self::inlineQr($payment['qr']) : null]);
    }

    /** Polling di pos.js: solo lo stato, mai indirizzo, QR o hash. */
    public function actionStatus(string $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        [$operator, $opening] = $this->context();
        if ($operator === null) return ['status' => 'logout'];
        if ($opening === null || !Yii::$app->operatorSession->ownsPayment($id)) return ['status' => 'closed'];
        $result = Yii::$app->napayLogin->viewPayment($operator['token'], $opening['opening'], $id);
        if ($result['ok']) return ['status' => $result['payment']['state']];
        if ($result['error'] === 'invalid_token') { Yii::$app->operatorSession->forget(); return ['status' => 'logout']; }
        if (in_array($result['error'], ['opening_invalid', 'not_found'], true)) return ['status' => 'closed'];
        return ['status' => 'retry'];
    }

    /**
     * Solo in anteprima (chain di test): testo del QR da copiare, per pagare da un wallet sullo stesso PC senza fotocamera.
     * Pagamento della sessione e ancora in attesa; nella pagina il riferimento resta solo dentro il QR.
     */
    public function actionRequest(string $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (getenv('POS_MODE') !== 'preview') throw new NotFoundHttpException(Yii::t('app', 'Payment not found.'));
        [$operator, $opening] = $this->context();
        if ($operator === null || $opening === null || !Yii::$app->operatorSession->ownsPayment($id)) throw new NotFoundHttpException(Yii::t('app', 'Payment not found.'));
        $result = Yii::$app->napayLogin->viewPayment($operator['token'], $opening['opening'], $id);
        if (!$result['ok'] || $result['payment']['state'] !== 'pending') throw new NotFoundHttpException(Yii::t('app', 'Payment not found.'));
        return ['request' => $result['payment']['qr']];
    }

    public function actionCancel(string $id)
    {
        [$operator, $opening] = $this->context();
        if ($operator === null) return $this->redirect(['/site/login']);
        if ($opening === null) return $this->redirect(['/site/account']);
        $this->requireOwned($id);
        $result = Yii::$app->napayLogin->cancelPayment($operator['token'], $opening['opening'], $id);
        if (!$result['ok'] && ($redirect = $this->leave($result['error'], true))) return $redirect;
        if (!$result['ok']) {
            Yii::$app->session->setFlash('pos', $result['error'] === 'payment_not_pending'
                ? Yii::t('app', 'The payment is no longer pending: check its outcome.')
                : Yii::t('app', 'Cancellation not confirmed by Napay: retry.'));
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /** Storico incassi del terminale dell'apertura corrente, come restituito da Napay. */
    public function actionHistory()
    {
        [$operator, $opening] = $this->context();
        if ($operator === null) return $this->redirect(['/site/login']);
        if ($opening === null) return $this->redirect(['/site/account']);
        $result = Yii::$app->napayLogin->paymentHistory($operator['token'], $opening['opening']);
        if (!$result['ok'] && ($redirect = $this->leave($result['error']))) return $redirect;
        if (!$result['ok']) Yii::$app->response->statusCode = $result['error'] === 'rate_limited' ? 429 : 503;
        return $this->render('history', ['payments' => $result['payments'] ?? null, 'label' => $opening['label']]);
    }

    /**
     * Ricevuta PDF A7 solo per pagamenti confermati da Napay: di questa sessione (stato riletto) o dallo storico
     * del terminale (operatore non indicato: può essere un altro).
     */
    public function actionReceipt(string $id)
    {
        [$operator, $opening] = $this->context();
        if ($operator === null) return $this->redirect(['/site/login']);
        if ($opening === null) return $this->redirect(['/site/account']);
        $owned = Yii::$app->operatorSession->ownsPayment($id);
        $result = $owned ? Yii::$app->napayLogin->viewPayment($operator['token'], $opening['opening'], $id)
            : Yii::$app->napayLogin->paymentHistory($operator['token'], $opening['opening']);
        if (!$result['ok'] && ($redirect = $this->leave($result['error'], $owned))) return $redirect;
        $payment = $owned ? ($result['payment'] ?? null) : (array_column($result['payments'] ?? [], null, 'id')[$id] ?? null);
        if ($payment === null || $payment['state'] !== 'paid') throw new NotFoundHttpException(Yii::t('app', 'Receipt not available.'));
        $pdf = PaymentDocuments::receiptPdf(['label' => $opening['label'], 'operator' => $owned ? $operator['name'] : null, 'amount' => $payment['amount'],
            'paid_at' => $payment['paid_at'], 'id' => $payment['id'], 'tx_hash' => $payment['tx_hash']]);
        return Yii::$app->response->sendContentAsFile($pdf, 'ricevuta-' . substr($payment['id'], 0, 8) . '.pdf',
            ['mimeType' => 'application/pdf', 'inline' => true]);
    }

    /** @return array{0:?array, 1:?array} operatore e apertura correnti (custoditi solo nella sessione server) */
    private function context(): array
    {
        $operator = Yii::$app->operatorSession->current();
        return [$operator, $operator === null ? null : Yii::$app->operatorSession->opening()];
    }

    private function requireOwned(string $id): void
    {
        if (!Yii::$app->operatorSession->ownsPayment($id)) throw new NotFoundHttpException(Yii::t('app', 'Payment not found.'));
    }

    /**
     * Esiti che chiudono il flusso: sessione scaduta o apertura non più valida. Per un pagamento gia creato,
     * not_found significa pagamento non visibile a questa sessione: pagina 404, l'apertura resta.
     */
    private function leave(string $error, bool $payment = false): ?Response
    {
        if ($payment && $error === 'not_found') throw new NotFoundHttpException(Yii::t('app', 'Payment not found.'));
        if ($error === 'invalid_token') { Yii::$app->operatorSession->forget(); return $this->redirect(['/site/login']); }
        if (in_array($error, ['opening_invalid', 'not_found'], true)) {
            Yii::$app->operatorSession->dropOpening();
            Yii::$app->session->setFlash('pos', Yii::t('app', 'The POS was closed or unlocked by the manager.'));
            return $this->redirect(['/site/account']);
        }
        return null;
    }

    private function renderTill(AmountForm $model, string $label, ?string $paymentError = null): string
    {
        return $this->render('/site/index', ['model' => $model, 'label' => $label, 'paymentError' => $paymentError]);
    }

    /** SVG del QR senza prologo XML ne descrizione testuale (il contenuto e gia mostrato e validato). */
    private static function inlineQr(string $content): string
    {
        return (string) preg_replace('~<desc>.*?</desc>~s', '', PaymentDocuments::qrSvg($content));
    }
}
