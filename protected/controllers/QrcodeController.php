<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.Push');
Yii::import('libs.ethereum.eth');
Yii::import('libs.Utils.Utils');
Yii::import('libs.NaPacks.Logo');

require_once Yii::app()->params['libsPath'] . '/ethereum/web3/vendor/autoload.php';
use Web3\Web3;
use Web3\Contract;

class QrcodeController extends Controller
{
	public $balance = null;
	public $account = null;
	//public $listAccounts = null;

	public function init()
	{
	}
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	// public $layout='//layouts/column_shopping';



	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(

			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array(
					'index',
					'getInvoiceStatus'
				),
				'users'=>array('@'),
			),
			// array('allow', // allow admin user to perform 'admin' and 'delete' actions
			// 	'actions'=>array('admin','delete'),
			// 	'users'=>array('admin'),
			// ),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}



	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return PosInvoices the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=PosInvoices::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param Pos $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='pos-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

	/**
	 * @param POST integer id_token the ID of the model to be searched
	 */
	public function actionGetInvoiceStatus(){
		$model = $this->loadModel(crypt::Decrypt($_POST['id_token']));
		echo CJSON::encode(array("status"=>$model->status));
	}

	/**
	 * Displays a qrcode.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionIndex($id,$id_pos)
	{
		$this->layout='//layouts/column_qrcode';
		$model = $this->loadModel(crypt::Decrypt($id));
		$pos = Pos::model()->findByPk(crypt::Decrypt($id_pos));
		$storeSettings = Settings::loadStore($pos->id_store);
		$merchants = Merchants::model()->findByPk($pos->id_merchant);

		$this->render('index',array(
			'model'=>$model,
			'pos'=>$pos,
			'merchants'=>$merchants,
			'storeSettings'=>$storeSettings,
		));
	}


}
