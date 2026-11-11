<?php
namespace app\controllers;

use Yii;
use app\models\AmountForm;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET', 'POST'], 'health' => ['GET']]]];
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
