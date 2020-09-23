<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.Push');
Yii::import('libs.ethereum.eth');
Yii::import('libs.Utils.Utils');


require_once Yii::app()->params['libsPath'] . '/ethereum/web3/vendor/autoload.php';

use Web3\Web3;
use Web3\Contract;

class InvoicesController extends Controller
{
	public function init()
	{
		if (!(isset(Yii::app()->user->objUser))){
			$this->redirect(array('site/logout'));
		}
	}

	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
			),

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
				'actions'=>array(
					'token'
				),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * CREA UNA invoice su PosInvoices con stato new che verrà mostrata
	 * per il successivo pagamento tramite qr-code
	 * @param _POST['amount'] the amount to be send
	 * @param _POST['wallet_address'] the wallet_address will receive
	 * @param _POST['id_pos'] the pos creating invoice
	 * @exec  il comando in background di ricerca di una transazione
	 * @return URL con la pagina per visualizzare il qrcode
	 */
	public function actionToken()
	{

		if (true === isset($_POST['amount']) && trim($_POST['amount']) <> 0) {
 			$amount = (float)trim($_POST['amount']);
 		} else {
 			echo CJSON::encode(array("error"=>"Amount invalid!"));
 			exit;
 		}

		if (true === isset($_POST['wallet_address'])) {
 			$toAccount = trim($_POST['wallet_address']);
 		} else {
 			echo CJSON::encode(array("error"=>"Wallet address invalid!"));
 			exit;
 		}

		if (true === isset($_POST['id_pos']) && trim($_POST['id_pos']) <> 0) {
 			$idPos = trim($_POST['id_pos']);
 		} else {
 			echo CJSON::encode(array("error"=>"Id POS invalid!"));
 			exit;
 		}

		$pos=Pos::model()->findByPk($idPos);
		if($pos===null){
			echo CJSON::encode(array("error"=>"The requested ID Pos does not exist!"));
 			exit;
		}

		$wallets = Wallets::model()->findByAttributes(array('wallet_address'=>$toAccount));
		if($wallets===null){
			echo CJSON::encode(array("error"=>"The requested Wallet address does not exist!"));
 			exit;
		}

		$poaNode = WebApp::getPoaNode();
		if (!$poaNode){
			$save = new Save;
			$save->WriteLog('pos','invoices','token',"All Nodes are down.");
			$send_json =  array(
				'success'=> 0,
				"error"=>'Error: All Nodes are down.',
			);
		}else{
			$web3 = new Web3($poaNode);

			// blocco in cui presumibilmente avviene la transazione
			$block = null;
			$web3->eth->getBlockByNumber('latest',false, function ($err, $response) use (&$block){
				if ($err !== null) {
					//throw new CHttpException(404,'Errore: '.$err->getMessage());
					echo CJSON::encode(array("error"=>'404 Error: '.$err->getMessage()));
					exit;
				}
				$block = $response;
			});

			//salva la transazione ERC20
	 		$timestamp = time();
	 		$invoice_timestamp = $timestamp;

	 		//calcolo expiration time
			// $storeSettings = Settings::loadStore($pos->id_store);
	 		// $totalseconds = $storeSettings->invoice_expiration * 60; //invoice_expiration è in minuti, * 60 lo trasforma in secondi
			$storeSettings = Settings::load();
	 		$totalseconds = $storeSettings->poa_expiration * 60; //invoice_expiration è in minuti, * 60 lo trasforma in secondi
	 		$expiration_timestamp = $timestamp + $totalseconds; //DEFAULT = 15 MINUTES

			// TODO: al momento il token è peggato 1/1 sull'euro
			$rate = eth::getFiatRate('token');
			$fromAccount = '';

			$attributes = array(
	 			'id_user' => Yii::app()->user->objUser['id_user'],
	 			'status'	=> 'new',
				'type'	=> 'token',
	 			'token_price'	=> $amount,
	 			'token_ricevuti'	=> 0,
	 			'fiat_price'		=> $rate * $amount,
	 			'currency'	=> 'EUR',
	 			'id_pos' => $idPos,
	 			'invoice_timestamp' => $invoice_timestamp,
	 			'expiration_timestamp' => $expiration_timestamp,
	 			'rate' => $rate,
	 			'from_address' => $fromAccount,
				'to_address' => $toAccount,
				'blocknumber' => hexdec($block->number), // numero del blocco in base 10
				'txhash'	=> '0x0',
	 		);

			// echo '<pre>'.print_r($attributes,true).'</pre>';
			// exit;

			//salvo la transazione in db. Restituisce object
			$save = new Save;
			$invoice = $save->PosInvoices($attributes);

			// echo '<pre>'.print_r($invoice,true).'</pre>';
			// exit;

			$memo = 'Pagamento a '.$pos->denomination;

			// salvo l'eventuale messaggio inserito
			if (!empty($memo)){
				$save = new Save;
				$message = $save->PosMemo([
					'id_token'=>$invoice->id_token,
					'memo'=>crypt::Encrypt($memo)
				]);
			}

	 		//salva la notifica
	 		$notification = array(
	 			'type_notification' => 'invoice',
	 			'id_user' => $invoice->id_user,
	 			'id_tocheck' => $invoice->id_token,
	 			'status' => 'new',
				'description' => 'You generated a new token invoice.',
				// 'url' => Yii::app()->createUrl("tokens/view",['id'=>crypt::Encrypt($invoice->id_token)]),
				'url' => 'index.php?r=tokens/view&id='.crypt::Encrypt($invoice->id_token),
	 			'timestamp' => $timestamp,
	 			'price' => $rate * $amount,
	 			'deleted' => 0,
	 		);

			// echo '<pre>'.print_r($notification,true).'</pre>';
			$save = new Save;
			Push::Send($save->Notification($notification,true),'dashboard');

			//eseguo lo script che si occuperà in background di verificare lo stato dell'invoice appena creata...
			$cmd = Yii::app()->basePath.DIRECTORY_SEPARATOR.'yiic receive --id='.crypt::Encrypt($invoice->id_token);
			Utils::execInBackground($cmd);

			//finalmente ritorno all'app e restituisco l'url con il qr-code della transazione da pagare!!!
			$send_json = array(
				'success'=> 1,
				'url' => Yii::app()->createUrl('qrcode/index',array(
					'id'=>crypt::Encrypt($invoice->id_token),
					'id_pos'=>crypt::Encrypt($idPos), // serve per recuperare le info del POS, visto che ormai il wallet è scollegato dal merchant id
				)),
			);
		}
     	echo CJSON::encode($send_json);
	}
}
