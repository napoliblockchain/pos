<?php
Yii::import('libs.crypt.crypt');
Yii::import('libs.NaPacks.Settings');
Yii::import('libs.NaPacks.WebApp');
Yii::import('libs.NaPacks.SaveModels');
Yii::import('libs.NaPacks.Save');
Yii::import('libs.NaPacks.Push');
Yii::import('libs.NaPacks.Notifi');
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

	//scrive a video e nel file log le informazioni richieste
	private function log($text){
		$save = new Save;
		$save->WriteLog('pos','qrcode','getInvoiceStatus', $text);
	}
	/**
	 * @param POST integer id_token the ID of the model to be searched
	 */
	public function actionGetInvoiceStatus(){
	// 	$model = $this->loadModel(crypt::Decrypt($_POST['id_token']));
	// 	echo CJSON::encode(array("status"=>$model->status));
	// }
	// public function a2GetInvoiceStatus(){
		$id = $_POST['id_token'];
		// $this->log("Start Check invoice #: $id");

		//carico l'invoice
		$invoice = $this->loadModel(crypt::Decrypt($id));
		// $this->log("Invoice $id loaded. Status is $invoice->status");

		$expiring_seconds = $invoice->expiration_timestamp +1 - time();
		$transactionValue = $invoice->token_ricevuti;

		$ipnflag = false;
		if ($invoice->status == 'new' || $invoice->status == 'expired'){ //se il valore è new proseguo
			// cerco le transazioni su bolt_tokens in pending
			$criteria=new CDbCriteria;

			$criteria->compare('type','token');
			//$criteria->compare('status','new',true);
			$criteria->compare('item_desc','wallet');
			$criteria->compare('to_address',$invoice->to_address);
			$criteria->addCondition("invoice_timestamp > " .$invoice->invoice_timestamp);
			$criteria->addCondition("blocknumber > " .$invoice->blocknumber);
			$criteria->compare('token_price',$invoice->token_price,true); // false -> valore identico

			$transactions = Tokens::model()->findAll($criteria);

			if (!empty($transactions))
			{
				$this->log("Transazioni trovate.");
				foreach ($transactions as $transaction)
				{
					$this->log("Transazione n. $transaction->id_token");
					if ($transaction->status <> 'new'){
						$this->log("Transazione n. $transaction->id_token COMPLETATA.");
						$this->log('<pre>'.print_r($transaction->attributes,true).'</pre>');
						$invoice->status = $transaction->status;
						$invoice->token_ricevuti = $transaction->token_price;
						$invoice->from_address = $transaction->from_address;
						$invoice->blocknumber = $transaction->blocknumber;
						$invoice->txhash = $transaction->txhash;

						// carico il memo di tokens e lo aggiorno con quello del pos
						$memoToken = TokensMemo::model()->findByAttributes(['id_token'=>$transaction->id_token]);
						if (null === $memoToken)
							$memoToken = new TokensMemo;

						$this->log("TokensMemo: ($transaction->id_token)".'<pre>'.print_r(crypt::Decrypt($memoToken->memo),true).'</pre>');
						$invoiceMemo = PosInvoicesMemo::model()->findByAttributes(['id_token'=>$invoice->id_token]);
						if (null === $invoiceMemo)
							$invoiceMemo = new PosInvoicesMemo;

						$this->log("PosInvoicesMemo: ($invoice->id_token)".'<pre>'.print_r(crypt::Decrypt($invoiceMemo->memo),true).'</pre>');
						$memoToken->memo = $invoiceMemo->memo;
						$memoToken->id_token = $transaction->id_token;

						// aggiorno il messaggio
						$memoToken->save();


						$invoice->save();

						$ipnflag = true;
					}else{
						$this->log("Transazione n. $transaction->id_token ancora in new...");
					}
				}//foreach loop
			}
			if ($ipnflag === false && $expiring_seconds < 0){//invoice expired
				$invoice->status = 'expired';
				$ipnflag = true;
			}
		}
		if ($expiring_seconds < 0){//invoice expired
			$ipnflag = true;
		}
		if ($ipnflag){ //send ipn in case flag is true: può venire
			// aggiorno l'invoice e invio il messaggio push
			if ($invoice->save()){
				//$this->log("End : invoice #: $id, Status: $invoice->status, Received: $invoice->token_ricevuti");
				$this->sendIpn($invoice->attributes);
			}else{
				$this->log("Error : Cannot save invoice #. $id, Status: $invoice->status.");
			}
		}
		//conto alla rovescia fino alla scadenza dell'invoice
		$this->log("Invoice: $id, Status: ".$invoice->status.", Seconds: ".$expiring_seconds."\n");
		// ritorno lo stato
		echo CJSON::encode(array("status"=>$invoice->status));
	}

	private function sendIpn($ipn){
		$tokens = (object) $ipn;

		if (true === empty($tokens)) {
			$this->log("Error. Could not decode the JSON payload from Token Server.");
			throw new \Exception('Could not decode the JSON payload from Token Server.');
		}else{
			#fwrite($myfile, $date . " : 2. Json ok.\n");
		}

		//QUINDI INVIO UN MESSAGGIO DI NOTIFICA
		$notification = array(
			'type_notification' => 'invoice',
			'id_user' => $tokens->id_user,
			'id_tocheck' => $tokens->id_token,
			'status' => $tokens->status,
			// 'description' => ' da '. $tokens->item_desc,
			'description' => Notifi::description($tokens->status,'token'),
			'url' => "index.php?r=tokens/view&id=".crypt::Encrypt($tokens->id_token),
			'timestamp' => time(),
			'price' => $tokens->token_price,
			'deleted' => 0,
		);
		$save = new Save;
		Push::Send($save->Notification($notification,true),'dashboard');

		//ADESSO POSSO USCIRE CON UN MESSAGGIO POSITIVO ;^)
		$this->log("IPN received for Invoice #: ".crypt::Encrypt($tokens->id_token).", Status=" .$tokens->status.", Price=". $tokens->token_price.", Received: ".$tokens->token_ricevuti);
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
