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
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET', 'POST'], 'health' => ['GET'],
            'login' => ['GET', 'POST'], 'account' => ['GET'], 'logout' => ['POST']]]];
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
        $model = new OperatorLoginForm();
        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post('OperatorLoginForm');
            if (!is_array($data)) throw new BadRequestHttpException('Richiesta non valida.');
            $model->setAttributes($data);
            if ($model->validate()) {
                $result = Yii::$app->napayLogin->login($model->email, $model->password, Yii::$app->request->userIP);
                $model->password = '';
                if ($result['ok']) {
                    Yii::$app->operatorSession->establish($result['session']);
                    return $this->redirect(['account']);
                }
                $message = 'Servizio di accesso temporaneamente non disponibile.';
                if ($result['error'] === 'backend_unavailable') Yii::$app->response->statusCode = 503;
                if ($result['error'] === 'invalid_credentials') $message = 'Email o password non validi.';
                if ($result['error'] === 'rate_limited') {
                    $message = 'Troppi tentativi. Riprova piu tardi.';
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
        return $this->render('account', ['displayName' => $operator['name']]);
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

    public function actionIndex()
    {
        $model = new AmountForm();
        $verified = false;
        if (Yii::$app->request->isPost) {
            $data = Yii::$app->request->post('AmountForm');
            if (!is_array($data)) throw new BadRequestHttpException('Richiesta non valida.');
            $model->setAttributes($data);
            $verified = $model->validate();
        }
        return $this->render('index', ['model' => $model, 'verified' => $verified]);
    }

    public function actionHealth()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['status' => 'ok', 'mode' => 'preview', 'payments' => false];
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;
        Yii::$app->response->statusCode = $exception instanceof \yii\web\HttpException ? $exception->statusCode : 500;
        return $this->render('error');
    }
}
