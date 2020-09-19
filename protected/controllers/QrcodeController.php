<?php
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

	/**
	 * action Create Token Invoice
	 */
	// public function actionInvoice()
	// {
	// 	if (true === isset($_POST['amount']) && trim($_POST['amount']) != 0) {
 	// 		$amount = (float)trim($_POST['amount']);
 	// 	} else {
 	// 		echo CJSON::encode(array("error"=>"Amount invalid!"));
 	// 		exit;
 	// 	}
	//
	// 	$pos = new Pos;
	// 	$pos=Pos::model()->findByPk($_POST['id_pos']);
	// 	if($pos===null){
	// 		echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 	// 		exit;
	// 	}
	//
	// 	$wallets = Wallets::model()->findByAttributes(array('wallet_address'=>$_POST['wallet_address']));
	// 	if($wallets===null){
	// 		echo CJSON::encode(array("error"=>"The requested Wallet address does not exist!"));
 	// 		exit;
	// 	}
	//
	// 	//Carico i parametri
	// 	$settings=Settings::load();  //$settings=SettingsNapos::model()->findByPk(1); // la PK è 1 per i settings dell'applicazione Napos
	// 	if ($settings === null || empty($settings->poa_url)){//} || empty($settings->poa_port)){
	// 		echo CJSON::encode(array("error"=>'Errore: I parametri di configurazione Token non sono stati trovati'));
	// 		exit;
	// 	}
	//
	// 	// mi connetto al nodo poa
	// 	$web3 = new Web3($settings->poa_url);
	// 	$contract = new Contract($web3->provider, $settings->poa_abi);
	// 	$eth = $web3->eth;
	// 	$utils = $web3->utils;
	// 	$balance = 0;
	//
	// 	// utilizzo questo campo per salvare il numero di blocco in cui avviene la transazione
	// 	$response = null;
	// 	$eth->getBlockByNumber('latest',false, function ($err, $block) use (&$response){
	// 		if ($err !== null) {
	// 			throw new CHttpException(404,'Errore: '.$err->getMessage());
	// 		}
	// 		$response = $block;
	// 	});
	//
	// 	//salva la transazione del token
	// 	$timestamp = time();
	// 	$invoice_timestamp = $timestamp;
	//
	// 	//calcolo expiration time
	// 	//$settings=SettingsNapos::model()->findByPk(1); // la PK è 1 per i settings dell'applicazione Napos
	// 	$totalseconds = $settings->poa_expiration * 60; //poa_expiration è in minuti, * 60 lo trasforma in secondi
	// 	$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES
	//
	// 	$rate = NaPay::getFiatRate('token'); //
	// 	//echo '<pre>'.print_r(strlen($amount),true).'</pre>';
	// 	//exit;
	//
	// 	$attributes = array(
	// 		'id_pos'	=> $_POST['id_pos'],
	// 		'id_merchant' => $_POST['id_merchant'],
	// 		'status'	=> 'new',
	// 		'type'	=> 'token',
	// 		'token_price'	=> $rate * $amount,
	// 		'token_ricevuti'	=> 0,
	// 		'fiat_price'		=> $amount,
	// 		'currency'	=> 'EUR',
	// 		'item_desc' => $pos->denomination,
	// 		'item_code' => $pos->id_pos,
	// 		'invoice_timestamp' => $invoice_timestamp,
	// 		'expiration_timestamp' => $expiration_timestamp,
	// 		'rate' => $rate,
	// 		'wallet_address' => $wallets->wallet_address,
	// 		'balance' => hexdec($response->number), // numero del blocco in base 10
	// 		'txhash'	=> '',
	// 		'poa_url' => '',//$wallets->poa_url,	//inserisco poa_url e poa_port così ogni token è legato alla propria poa e nn può funzionare su un'altra
	// 		'poa_port' => '',//$wallets->poa_port,
	// 		'id_bill' => 0
	// 	);
	// 	//restituisce un object
	// 	$tokens = $this->save_tokenTransaction($attributes);
	//
	// 	//salva la notifica
	// 	$notification = array(
	// 		'type_notification' => $tokens->type,
	// 		'id_merchant' => $tokens->id_merchant,
	// 		'id_tocheck' => $tokens->id_token,
	// 		'status' => $tokens->status,
	// 		'description' => ' '.ucfirst($tokens->type).' da '. $tokens->item_desc,
	// 		'url' => "index.php?r=tokens/view&id=".Utility::encryptURL($tokens->id_token),
	// 		'timestamp' => $timestamp,
	// 		'price' => $_POST['amount'],
	// 		'deleted' => 0,
	// 	);
	// 	NaPay::save_notification($notification);
	//
	// 	//eseguo lo script che si occuperà in background di verificare lo stato dell'invoice appena creata...
	// 	$cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic receive --id='.Utility::encryptURL($tokens->id_token);
	// 	NaPay::execInBackground($cmd);
	//
	// 	//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
	// 	$send_json = array(
	// 		'url' => Yii::app()->createUrl('webtoken/qrcode',array('id'=>Utility::encryptURL($tokens->id_token))),
	// 	);
    // 	echo CJSON::encode($send_json);
	// }

	// private function setBalance($balance){
	// 	$value = (string) $balance * 1;
	// 	$this->balance = $value / 1000000000000000000;;
	// }
	// private function setBalance($balance){
	// 	$value = (string) $balance * 1;
	// 	$this->balance = $value;
	// }
	// private function getBalance(){
	// 	return $this->balance;
	// }

	// private function save_tokenTransaction($array){
	// 	$tokens = new Tokens;
	// 	$tokens->attributes = $array;
	// 	#echo '<pre>'.print_r($array,true).'</pre>';
	// 	#exit;
	// 	if (!$tokens->save()){
	// 		echo CJSON::encode(array("error"=>'Error: Cannot save transaction.'));
	// 		exit;
	// 	}
	// 	#echo '<pre>'.print_r($tokens->attributes,true).'</pre>';
	// 	#exit;
	// 	return (object) $tokens->attributes;
	// }
}
