<?php

class MessagesController extends Controller
{
	public function init()
	{
		if (!(isset(Yii::app()->user->objUser))){
			$this->redirect(array('site/logout'));
		}
	}
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column1';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
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
				'actions'=>array('index'),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

		/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		if(isset($_POST['selectedNotifications'])){
			foreach ($_POST['selectedNotifications'] as $x => $id_notification){
				#echo "<br>".$id_notification;
				$criteriaReaders=new CDbCriteria();
				$criteriaReaders->compare('id_notification',$id_notification,false);

				$allReaders=new CActiveDataProvider('Notifications_readers', array(
				    'criteria'=>$criteriaReaders,
				));

				if ($allReaders){
					$iterator = new CDataProviderIterator($allReaders);
					foreach($iterator as $item) {
						$singleReader=Notifications_readers::model()->findByPk($item->id_notifications_reader);
						if($singleReader!==null){
							$singleReader->delete();
						}
					}
				}
			}
		}

		$criteria = new CDbCriteria();
		$criteria->compare('id_user',Yii::app()->user->objUser['id_user'],false);
		$news = Notifications_readers::model()->orderById()->findAll($criteria);

		$arrayCondition = array();
		foreach ($news as $key => $item) {
			$arrayCondition[] = $item->id_notification;
		}


		$MessageCriteria = new CDbCriteria();
		$MessageCriteria->addInCondition('id_notification', $arrayCondition);
		// le notifiche appartengono al pos quindi != da token
		$MessageCriteria->addCondition('type_notification != "token"');

		$dataProvider=new CActiveDataProvider('Notifications', array(
			'sort'=>array(
	    		'defaultOrder'=>array(
	      			'timestamp'=>true // viene prima la più recente
	    		)
	  		),
		    'criteria'=>$MessageCriteria,
		));
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
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

	/**
	 * Performs the AJAX validation.
	 * @param Transactions $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='transactions-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}


}
