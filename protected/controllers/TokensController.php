<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.ethereum.eth');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.NaPacks.Logo');
Yii::import('libs.Utils.Utils');

Yii::import('libs.bitstamp-real-time-price.BitstampRTP');

class TokensController extends Controller
{
	public function init()
	{
		if (isset(Yii::app()->user->objUser) && Yii::app()->user->objUser['facade'] != 'pos'){
			Yii::app()->user->logout();
			$this->redirect(Yii::app()->homeUrl);
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
				'actions'=>array('index','view','check'),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
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
		$modelc=new PosInvoices('search');
		$modelc->unsetAttributes();

		if(isset($_GET['PosInvoices']))
			$modelc->attributes=$_GET['PosInvoices'];

		//carico il wallet selezionato nei settings
		$settings=Settings::loadUser(Yii::app()->user->objUser['id_user']);
		if (!(isset($settings->id_wallet))){
			$from_address = '0x0000000000000000000000000000000000000000';
		}else{
			$wallet = Wallets::model()->findByPk($settings->id_wallet);
			if (null === $wallet){
				$wallet = new Wallets;
				$from_address = '0x0000000000000000000000000000000000000000';
			}else{
				$from_address = $wallet->wallet_address;
			}
		}
		$modelc->to_address = $from_address;

		$this->render('index',array(
			'modelc'=>$modelc,
			//'wallet'=>$wallet, //il wallet selezionato
			'from_address'=>$from_address, // indirizzo del wallet dell'utente
			//'actualBlockNumberDec' => eth::latestBlockNumberDec(), // numero attuale del blocco sulla blockchain
		));
	}

	public function actionCheck($id)
	{

		#echo '<pre>'.print_r($id,true).'</pre>';
		#exit;
		$model=$this->loadModel(crypt::Decrypt($id));
		$command = 'receive';

		//eseguo lo script che si occuperà in background di verificare lo stato dell'invoice appena creata...
		$cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic ' .$command. ' --id='.$id;
		Utils::execInBackground($cmd);

		$response['message'] = 'Please wait the page reloading... ';
		$response['success'] = 1;
		sleep(2);
		echo CJSON::encode($response,true);
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
		$model=PosInvoices::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

}
