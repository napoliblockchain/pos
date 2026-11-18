<?php
namespace app\controllers;

use Yii;
use app\models\AmountForm;
use app\models\OperatorLoginForm;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET'], 'health' => ['GET'],
            'login' => ['GET', 'POST'], 'account' => ['GET'], 'logout' => ['POST'],
            'open' => ['POST'], 'heartbeat' => ['POST'], 'release' => ['POST']]]];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return parent::beforeAction($action);
    }

    public function actionLogin()
    {
        if (Yii::$app->operatorSession->current() !== null) return $this->redirect(['account']);
        $this->layout = 'auth';
        $model = new OperatorLoginForm();
        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post('OperatorLoginForm');
            if (!is_array($data)) throw new BadRequestHttpException(Yii::t('app', 'Invalid request.'));
            $model->setAttributes($data);
            if ($model->validate()) {
                $result = Yii::$app->napayLogin->login($model->email, $model->password, Yii::$app->request->userIP);
                $model->password = '';
                if ($result['ok']) {
                    Yii::$app->operatorSession->establish($result['session']);
                    return $this->redirect(['account']);
                }
                $message = Yii::t('app', 'Login service temporarily unavailable.');
                if ($result['error'] === 'backend_unavailable') Yii::$app->response->statusCode = 503;
                if ($result['error'] === 'invalid_credentials') $message = Yii::t('app', 'Invalid email or password.');
                if ($result['error'] === 'rate_limited') {
                    $message = Yii::t('app', 'Too many attempts. Retry later.');
                    Yii::$app->response->statusCode = 429;
                    Yii::$app->response->headers->set('Retry-After', (string) $result['retry']);
                }
                $model->addError('password', $message);
            }
            $model->password = '';
        }
        return $this->render('login', ['model' => $model]);
    }

    public function actionAccount()
    {
        $operator = Yii::$app->operatorSession->current();
        if ($operator === null) return $this->redirect(['login']);
        $cursor = Yii::$app->request->get('cursor');
        if ($cursor !== null && (!is_string($cursor) || !preg_match('/^[A-Za-z0-9_-]{1,512}$/D',$cursor))) {
            Yii::$app->response->statusCode=400;
            return $this->render('account',['displayName'=>$operator['name'],'catalogError'=>true]);
        }
        $result = Yii::$app->napayLogin->catalog($operator['token'],$cursor);
        if (!$result['ok'] && $result['error'] === 'invalid_token') {
            Yii::$app->operatorSession->forget();
            return $this->redirect(['login']);
        }
        if (!$result['ok']) {
            Yii::$app->response->statusCode = $result['error'] === 'validation_failed' ? 400 : ($result['error'] === 'rate_limited' ? 429 : 503);
            if ($result['error'] === 'rate_limited') Yii::$app->response->headers->set('Retry-After',(string)$result['retry']);
        }
        if (Yii::$app->operatorSession->opening() !== null) return $this->redirect(['index']);
        return $this->render('account', ['displayName'=>$operator['name'],'catalog'=>$result['catalog'] ?? null,'catalogError'=>!$result['ok'],
            'notice'=>Yii::$app->session->getFlash('pos')]);
    }

    /** SIP-021 fase 2: apertura esclusiva del terminale scelto; la chiave del form rende innocuo un doppio invio. */
    public function actionOpen()
    {
        $operator = Yii::$app->operatorSession->current();
        if ($operator === null) return $this->redirect(['login']);
        if (Yii::$app->operatorSession->opening() !== null) return $this->redirect(['index']);
        $terminal = Yii::$app->request->post('terminal'); $key = Yii::$app->request->post('key'); $label = Yii::$app->request->post('label');
        if (!is_string($terminal) || !is_string($key) || !is_string($label) || mb_strlen($label) > 520 || !mb_check_encoding($label, 'UTF-8')) throw new BadRequestHttpException(Yii::t('app', 'Invalid request.'));
        $result = Yii::$app->napayLogin->openTerminal($operator['token'], $terminal, (string) Yii::$app->operatorSession->instance(), $key);
        if ($result['ok']) {
            Yii::$app->operatorSession->keepOpening($result['opening'], $label);
            return $this->redirect(['index']);
        }
        if ($result['error'] === 'invalid_token') { Yii::$app->operatorSession->forget(); return $this->redirect(['login']); }
        Yii::$app->session->setFlash('pos', [
            'terminal_in_use' => Yii::t('app', 'POS in use on another device. If it is stuck, ask the manager to unlock it.'),
            'not_found' => Yii::t('app', 'This POS is no longer available for your account.'),
            'opening_invalid' => Yii::t('app', 'Opening no longer valid: retry.'),
            'rate_limited' => Yii::t('app', 'Too many attempts: wait a few seconds and retry.'),
        ][$result['error']] ?? Yii::t('app', 'Napay unreachable: retry.'));
        return $this->redirect(['account']);
    }

    /** Segnale di vita ogni 30 s (solo informativo per il responsabile). JSON per pos.js. */
    public function actionHeartbeat()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $operator = Yii::$app->operatorSession->current();
        $opening = Yii::$app->operatorSession->opening();
        if ($operator === null) return ['status' => 'logout'];
        if ($opening === null) return ['status' => 'closed'];
        $result = Yii::$app->napayLogin->heartbeat($operator['token'], $opening['opening']);
        if ($result['ok']) return ['status' => 'ok'];
        if ($result['error'] === 'invalid_token') { Yii::$app->operatorSession->forget(); return ['status' => 'logout']; }
        if (in_array($result['error'], ['opening_invalid', 'not_found'], true)) {
            Yii::$app->operatorSession->dropOpening();
            Yii::$app->session->setFlash('pos', Yii::t('app', 'The POS was closed or unlocked by the manager.'));
            return ['status' => 'closed'];
        }
        return ['status' => 'retry'];
    }

    /** Chiusura normale: la sessione locale lascia l'apertura solo dopo conferma di Napay (o se Napay la dà già chiusa). */
    public function actionRelease()
    {
        $operator = Yii::$app->operatorSession->current();
        $opening = Yii::$app->operatorSession->opening();
        if ($operator === null) return $this->redirect(['login']);
        if ($opening === null) return $this->redirect(['account']);
        $result = Yii::$app->napayLogin->release($operator['token'], $opening['opening']);
        if ($result['ok'] || in_array($result['error'], ['opening_invalid', 'not_found'], true)) {
            Yii::$app->operatorSession->dropOpening();
            Yii::$app->session->setFlash('pos', Yii::t('app', 'POS closed.'));
            return $this->redirect(['account']);
        }
        if ($result['error'] === 'invalid_token') { Yii::$app->operatorSession->forget(); return $this->redirect(['login']); }
        Yii::$app->response->statusCode = 503;
        return $this->render('index', ['model' => new AmountForm(), 'label' => $opening['label'], 'releaseFailed' => true]);
    }

    public function actionLogout()
    {
        $operator = Yii::$app->operatorSession->current();
        if ($operator !== null && !Yii::$app->napayLogin->logout($operator['token'])['ok']) {
            Yii::$app->response->statusCode = 503;
            return $this->render('account', ['displayName' => $operator['name'], 'logoutFailed' => true]);
        }
        Yii::$app->operatorSession->forget();
        return $this->redirect(['login']);
    }

    /** Schermata cassa: solo con un'apertura che Napay conferma valida a ogni caricamento. */
    public function actionIndex()
    {
        $operator = Yii::$app->operatorSession->current();
        if ($operator === null) return $this->redirect(['login']);
        $opening = Yii::$app->operatorSession->opening();
        if ($opening === null) return $this->redirect(['account']);
        $check = Yii::$app->napayLogin->viewOpening($operator['token'], $opening['opening']);
        if (!$check['ok'] && $check['error'] === 'invalid_token') { Yii::$app->operatorSession->forget(); return $this->redirect(['login']); }
        if (!$check['ok'] && in_array($check['error'], ['opening_invalid', 'not_found'], true)) {
            Yii::$app->operatorSession->dropOpening();
            Yii::$app->session->setFlash('pos', Yii::t('app', 'The POS was closed or unlocked by the manager.'));
            return $this->redirect(['account']);
        }
        if (!$check['ok']) Yii::$app->response->statusCode = 503;
        // SIP-021 fase 3: il form del tastierino crea il pagamento (PaymentController::actionCreate)
        // SIP-033: tasto BTC solo se Napay lo dichiara disponibile per questa apertura (Napay non raggiungibile = solo TTS)
        $methods = $check['ok'] ? Yii::$app->napayLogin->paymentMethods($operator['token'], $opening['opening']) : ['ok' => false];
        return $this->render('index', ['model' => new AmountForm(), 'label' => $opening['label'], 'unverified' => !$check['ok'],
            'notice' => Yii::$app->session->getFlash('pos'), 'methods' => $methods['ok'] ? $methods['methods'] : null]);
    }

    public function actionHealth()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['status' => 'ok', 'mode' => 'preview', 'payments' => 'tts-test-chain'];
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;
        Yii::$app->response->statusCode = $exception instanceof \yii\web\HttpException ? $exception->statusCode : 500;
        return $this->render('error');
    }
}
