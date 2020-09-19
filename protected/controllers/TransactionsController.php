<?php

class TransactionsController extends Controller
{
	public function init()
	{
		// if (isset(Yii::app()->user->objUser) && Yii::app()->user->objUser['facade'] != 'dashboard'){
		// 	Yii::app()->user->logout();
		// 	$this->redirect(Yii::app()->homeUrl);
		// }
	}
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	// public $layout='//layouts/main_keypad';
public $layout='//layouts/column1';
	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			// 'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('index','view','delete'),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$save = new Save;
		$this->loadModel(crypt::Decrypt($id))->delete();
		$save->WriteLog('pos','transactions','delete','Transaction ['.crypt::Decrypt($id).'] deleted by '.Yii::app()->user->objUser['email'],false);
		$this->redirect(array('index'));
	}

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		$this->render('view',array(
			'model'=>$this->loadModel(crypt::Decrypt($id)),
		));
	}


	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$modelc=new Transactions('search');
		$modelc->unsetAttributes();

		if(isset($_GET['Transactions']))
			$modelc->attributes=$_GET['Transactions'];

		$this->render('index',array(
			'modelc'=>$modelc,
		));
	}


	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return Transactions the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=Transactions::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}




}
